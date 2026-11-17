<?php
/**
 * reschedule-appointment-ajax.php
 * Allows a resident to reschedule a pending/approved appointment to a new date/time.
 * Enforces: ownership, status check, blackout dates, slot limits, daily limit.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rate-limit.php';

header('Content-Type: application/json');

if (!isLoggedIn() || isStaff() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Rate limit: max 3 reschedules per 10 minutes
checkRateLimit((int)$_SESSION['user_id'], 'reschedule_appointment', 3, 10);

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$appointment_id   = (int) ($data['appointment_id'] ?? 0);
$new_date         = sanitize($data['new_date'] ?? '');
$new_time         = sanitize($data['new_time'] ?? '');

if (!$appointment_id || !$new_date || !$new_time) {
    echo json_encode(['success' => false, 'message' => 'Kumpleto ang lahat ng fields.']);
    exit;
}

// Must be a future date
if (strtotime($new_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Ang bagong petsa ay hindi maaaring nasa nakaraan.']);
    exit;
}

$database = new Database();
$db       = $database->getConnection();

// Verify ownership and reschedulable status
$owner_stmt = $db->prepare(
    "SELECT appointment_id, status, appointment_date, appointment_time, service_id
     FROM appointments
     WHERE appointment_id = :id AND user_id = :uid
     LIMIT 1"
);
$owner_stmt->bindParam(':id',  $appointment_id);
$owner_stmt->bindParam(':uid', $_SESSION['user_id']);
$owner_stmt->execute();
$appt = $owner_stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    echo json_encode(['success' => false, 'message' => 'Hindi mahanap ang appointment.']);
    exit;
}

$reschedulable_statuses = ['pending', 'approved', 'scheduled', 'confirmed'];
if (!in_array($appt['status'], $reschedulable_statuses)) {
    echo json_encode(['success' => false, 'message' => "Hindi maaaring i-reschedule ang appointment na may status na \"{$appt['status']}\". Tanging pending o approved lang ang maaaring i-reschedule."]);
    exit;
}

// Check blackout date
$blackout_stmt = $db->prepare("SELECT reason FROM blackout_dates WHERE blackout_date = :date LIMIT 1");
$blackout_stmt->bindParam(':date', $new_date);
$blackout_stmt->execute();
$blackout = $blackout_stmt->fetch(PDO::FETCH_ASSOC);
if ($blackout) {
    echo json_encode(['success' => false, 'message' => "Hindi available ang petsang iyon: {$blackout['reason']}. Mangyaring pumili ng ibang araw."]);
    exit;
}

// Check daily limit (exclude this appointment from count)
$daily_stmt = $db->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE user_id = :uid AND appointment_date = :date
       AND appointment_id != :id
       AND status IN ('pending','approved','scheduled','confirmed')"
);
$daily_stmt->bindParam(':uid',  $_SESSION['user_id']);
$daily_stmt->bindParam(':date', $new_date);
$daily_stmt->bindParam(':id',   $appointment_id);
$daily_stmt->execute();
if ($daily_stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Mayroon ka nang ibang appointment sa petsang iyon. Mangyaring pumili ng ibang petsa.']);
    exit;
}

// Get max slots from system_settings
$slot_limit_stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_slots_per_timeslot'");
$max_slots = (int) ($slot_limit_stmt->fetchColumn() ?: 5);

// Check slot availability (exclude this appointment from count)
$slot_stmt = $db->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE appointment_date = :date AND appointment_time = :time
       AND appointment_id != :id
       AND status != 'cancelled'"
);
$slot_stmt->bindParam(':date', $new_date);
$slot_stmt->bindParam(':time', $new_time);
$slot_stmt->bindParam(':id',   $appointment_id);
$slot_stmt->execute();
if ($slot_stmt->fetchColumn() >= $max_slots) {
    $time_fmt = date('h:i A', strtotime($new_time));
    echo json_encode(['success' => false, 'message' => "Puno na ang time slot na {$time_fmt}. Mangyaring pumili ng ibang oras."]);
    exit;
}

// Perform the reschedule in a transaction
$db->beginTransaction();
try {
    $old_date_fmt = date('F d, Y', strtotime($appt['appointment_date']));
    $old_time_fmt = date('h:i A',  strtotime($appt['appointment_time']));
    $new_date_fmt = date('F d, Y', strtotime($new_date));
    $new_time_fmt = date('h:i A',  strtotime($new_time));

    $update_stmt = $db->prepare(
        "UPDATE appointments
         SET appointment_date = :new_date, appointment_time = :new_time, status = 'pending', updated_at = NOW()
         WHERE appointment_id = :id"
    );
    $update_stmt->bindParam(':new_date', $new_date);
    $update_stmt->bindParam(':new_time', $new_time);
    $update_stmt->bindParam(':id',       $appointment_id);
    $update_stmt->execute();

    // Notification
    $notif_msg = "Na-reschedule ang iyong appointment mula {$old_date_fmt} {$old_time_fmt} papunta {$new_date_fmt} {$new_time_fmt}.";
    $notif_stmt = $db->prepare(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (:uid, 'Appointment Rescheduled', :msg, 'appointment')"
    );
    $notif_stmt->bindParam(':uid', $_SESSION['user_id']);
    $notif_stmt->bindParam(':msg', $notif_msg);
    $notif_stmt->execute();

    $db->commit();

    echo json_encode([
        'success'  => true,
        'message'  => "Matagumpay na na-reschedule ang iyong appointment sa {$new_date_fmt} ng {$new_time_fmt}.",
        'new_date' => $new_date,
        'new_time' => $new_time,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Nabigo ang reschedule. Subukang muli.']);
}
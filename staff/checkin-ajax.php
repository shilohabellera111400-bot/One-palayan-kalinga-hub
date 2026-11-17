<?php
// staff/checkin-ajax.php - Staff QR Check-in & Intake Processing Handler
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

if (!isLoggedIn() || (!isStaff() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Staff authentication required.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$staff_id = $_SESSION['user_id'];

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$action = $data['action'] ?? 'lookup';
$input_query = trim($data['query'] ?? ($data['reference_number'] ?? ''));

if (empty($input_query) && empty($data['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid Reference Number or scan a QR code.']);
    exit;
}

// Parse QR token format: "PAL-SOC-2026-000001|qr_token" or simple reference
$ref_number = $input_query;
$qr_token = '';
if (strpos($input_query, '|') !== false) {
    $parts = explode('|', $input_query, 2);
    $ref_number = trim($parts[0]);
    $qr_token = trim($parts[1] ?? '');
}

// Function to find appointment
function findAppointment($db, $ref, $qrToken = '', $appId = 0) {
    $sql = "SELECT a.*, s.service_name, s.duration_minutes,
                   u.first_name, u.last_name, u.email, u.contact_number,
                   r.barangay, r.street as street_address, u.verification_status
            FROM appointments a
            JOIN services s ON a.service_id = s.service_id
            JOIN users u ON a.user_id = u.user_id
            LEFT JOIN residents r ON u.user_id = r.user_id
            WHERE 1=1 ";
    $params = [];

    if (!empty($appId)) {
        $sql .= " AND a.appointment_id = :app_id";
        $params[':app_id'] = $appId;
    } elseif (!empty($qrToken)) {
        $sql .= " AND (a.qr_token = :qr_token OR a.reference_number = :ref_number)";
        $params[':qr_token'] = $qrToken;
        $params[':ref_number'] = $ref;
    } else {
        $sql .= " AND (a.reference_number = :ref OR a.qr_token = :ref_token OR a.appointment_id = :ref_id)";
        $params[':ref'] = $ref;
        $params[':ref_token'] = $ref;
        $params[':ref_id'] = is_numeric($ref) ? (int)$ref : -1;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$appointment_id = (int)($data['appointment_id'] ?? 0);
$appointment = findAppointment($db, $ref_number, $qr_token, $appointment_id);

if (!$appointment) {
    echo json_encode([
        'success' => false,
        'message' => 'No matching appointment found with Reference: ' . htmlspecialchars($ref_number)
    ]);
    exit;
}

$app_id = (int)$appointment['appointment_id'];

// Get attached documents count
$doc_stmt = $db->prepare("SELECT COUNT(*) FROM appointment_documents WHERE appointment_id = :id");
$doc_stmt->execute([':id' => $app_id]);
$appointment['documents_count'] = (int)$doc_stmt->fetchColumn();

// Get status history
$hist_stmt = $db->prepare("
    SELECT h.*, h.new_status as status, h.remarks as notes, CONCAT(u.first_name, ' ', u.last_name) as action_by_name 
    FROM appointment_status_history h 
    LEFT JOIN users u ON h.performed_by = u.user_id 
    WHERE h.appointment_id = :id 
    ORDER BY h.created_at DESC
");
$hist_stmt->execute([':id' => $app_id]);
$appointment['history'] = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Actions
// Rule: Once an appointment has been marked as Completed, no further status modifications or actions are permitted
if ($action !== 'lookup' && strtolower($appointment['status']) === 'completed') {
    echo json_encode([
        'success' => false,
        'message' => 'This appointment is already marked as Completed. Once completed, appointment records are permanently locked against modifications.'
    ]);
    exit;
}

if ($action === 'lookup') {
    echo json_encode([
        'success' => true,
        'appointment' => $appointment,
        'badge_html' => getAppointmentBadge($appointment['status'])
    ]);
    exit;
}

if ($action === 'checkin') {
    $allowed_statuses = ['confirmed', 'for_review', 'pending', 'rescheduled'];
    if (!in_array($appointment['status'], $allowed_statuses)) {
        if ($appointment['status'] === 'checked_in') {
            echo json_encode([
                'success' => true, 
                'already_checked_in' => true,
                'message' => 'Resident is ALREADY CHECKED IN at ' . date('h:i A', strtotime($appointment['checked_in_at'] ?? 'now')),
                'appointment' => $appointment
            ]);
            exit;
        }
        echo json_encode([
            'success' => false,
            'message' => 'Cannot check in appointment currently marked as: ' . strtoupper($appointment['status'])
        ]);
        exit;
    }

    $window = sanitize($data['window'] ?? ($appointment['window_assignment'] ?: 'Window 1 - Intake'));
    $notes = sanitize($data['notes'] ?? 'Citizen arrived on-site and verified at intake counter.');

    $update = $db->prepare("
        UPDATE appointments 
        SET status = 'checked_in',
            window_assignment = :window,
            checked_in_at = NOW(),
            checked_in_by = :staff_id
        WHERE appointment_id = :id
    ");
    $update->execute([
        ':window' => $window,
        ':staff_id' => $staff_id,
        ':id' => $app_id
    ]);

    logAppointmentHistory($db, $app_id, 'checked_in', $notes, $staff_id);
    notifyAppointmentUpdate($db, $app_id, 'checked_in', 'You have been checked in at ' . $window . '. Please wait for your name or reference number to be called.', $staff_id);

    $appointment = findAppointment($db, '', '', $app_id);
    echo json_encode([
        'success' => true,
        'message' => 'Resident successfully checked in! Assigned to ' . $window,
        'appointment' => $appointment,
        'badge_html' => getAppointmentBadge($appointment['status'])
    ]);
    exit;
}

if ($action === 'start_service') {
    if (!in_array($appointment['status'], ['checked_in'])) {
        echo json_encode(['success' => false, 'message' => 'Resident must be checked in first before starting the transaction.']);
        exit;
    }

    $notes = sanitize($data['notes'] ?? 'Social worker began processing client transaction.');

    $update = $db->prepare("
        UPDATE appointments 
        SET status = 'in_progress',
            started_at = NOW(),
            started_by = :staff_id
        WHERE appointment_id = :id
    ");
    $update->execute([':staff_id' => $staff_id, ':id' => $app_id]);

    logAppointmentHistory($db, $app_id, 'in_progress', $notes, $staff_id);
    notifyAppointmentUpdate($db, $app_id, 'in_progress', 'Your appointment interview and document processing is now in progress.', $staff_id);

    $appointment = findAppointment($db, '', '', $app_id);
    echo json_encode([
        'success' => true,
        'message' => 'Service transaction started (IN PROGRESS).',
        'appointment' => $appointment,
        'badge_html' => getAppointmentBadge($appointment['status'])
    ]);
    exit;
}

if ($action === 'complete_service') {
    if (!in_array($appointment['status'], ['in_progress', 'checked_in'])) {
        echo json_encode(['success' => false, 'message' => 'Appointment cannot be marked completed directly without being checked in or in progress.']);
        exit;
    }

    $outcome = sanitize($data['outcome'] ?? ($data['notes'] ?? 'Transaction successfully processed and resolved.'));

    $update = $db->prepare("
        UPDATE appointments 
        SET status = 'completed',
            completed_at = NOW(),
            completed_by = :staff_id,
            service_outcome = :outcome
        WHERE appointment_id = :id
    ");
    $update->execute([
        ':staff_id' => $staff_id,
        ':outcome' => $outcome,
        ':id' => $app_id
    ]);

    logAppointmentHistory($db, $app_id, 'completed', 'Service completed. Outcome: ' . $outcome, $staff_id);
    notifyAppointmentUpdate($db, $app_id, 'completed', 'Your appointment has been marked as Completed. Thank you for visiting City Social Welfare & Development.', $staff_id);

    $appointment = findAppointment($db, '', '', $app_id);
    echo json_encode([
        'success' => true,
        'message' => 'Appointment marked as COMPLETED successfully!',
        'appointment' => $appointment,
        'badge_html' => getAppointmentBadge($appointment['status'])
    ]);
    exit;
}

if ($action === 'mark_no_show') {
    $notes = sanitize($data['notes'] ?? 'Client did not show up during their scheduled appointment slot.');

    $update = $db->prepare("
        UPDATE appointments 
        SET status = 'no_show',
            service_outcome = :notes
        WHERE appointment_id = :id
    ");
    $update->execute([':notes' => $notes, ':id' => $app_id]);

    logAppointmentHistory($db, $app_id, 'no_show', $notes, $staff_id);
    notifyAppointmentUpdate($db, $app_id, 'no_show', 'You were marked as No-Show for this appointment slot.', $staff_id);

    $appointment = findAppointment($db, '', '', $app_id);
    echo json_encode([
        'success' => true,
        'message' => 'Appointment marked as NO-SHOW.',
        'appointment' => $appointment,
        'badge_html' => getAppointmentBadge($appointment['status'])
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unrecognized action: ' . htmlspecialchars($action)]);

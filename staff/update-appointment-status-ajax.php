<?php
// staff/update-appointment-status-ajax.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

header('Content-Type: application/json');

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$appointment_id = intval($input['appointment_id'] ?? 0);
$new_status = sanitize($input['status'] ?? '');
$status_notes = sanitize($input['notes'] ?? ($input['status_notes'] ?? ''));
$window_assignment = sanitize($input['window_assignment'] ?? '');
$rejection_reason = sanitize($input['rejection_reason'] ?? '');

$valid_statuses = ['pending', 'for_review', 'confirmed', 'checked_in', 'in_progress', 'completed', 'rescheduled', 'rejected', 'no_show', 'cancelled', 'needs_information'];

if ($appointment_id <= 0 || !in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID or status parameter.']);
    exit;
}

try {
    // Fetch appointment record & check RBAC
    $fetch_appt = $db->prepare("SELECT a.*, s.service_name, s.category_id, sc.category_name, u.user_id, u.first_name, u.last_name, u.email 
                                FROM appointments a 
                                LEFT JOIN services s ON a.service_id = s.service_id 
                                LEFT JOIN service_categories sc ON s.category_id = sc.category_id
                                JOIN users u ON a.user_id = u.user_id 
                                WHERE a.appointment_id = :id");
    $fetch_appt->execute([':id' => $appointment_id]);
    $appt_info = $fetch_appt->fetch(PDO::FETCH_ASSOC);

    if (!$appt_info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment record not found.']);
        exit;
    }

    $current_status = strtolower($appt_info['status']);

    // Prevent modifying completed appointments
    if ($current_status === 'completed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This appointment has already been completed and permanently locked.']);
        exit;
    }

    if ($current_status === 'cancelled' && $new_status !== 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cancelled appointments cannot be reopened.']);
        exit;
    }

    $update_sql = "UPDATE appointments SET status = :status";
    $update_params = [':status' => $new_status, ':id' => $appointment_id];

    if (!empty($window_assignment)) {
        $update_sql .= ", window_assignment = :window";
        $update_params[':window'] = $window_assignment;
    }

    if ($new_status === 'checked_in') {
        $update_sql .= ", checked_in_at = NOW(), checked_in_by = :staff_uid";
        $update_params[':staff_uid'] = $_SESSION['user_id'];
    } elseif ($new_status === 'in_progress') {
        $update_sql .= ", started_at = NOW(), started_by = :staff_uid";
        $update_params[':staff_uid'] = $_SESSION['user_id'];
    } elseif ($new_status === 'completed') {
        $update_sql .= ", completed_at = NOW(), completed_by = :staff_uid, service_outcome = :outcome";
        $update_params[':staff_uid'] = $_SESSION['user_id'];
        $update_params[':outcome'] = $status_notes ?: 'Service completed successfully.';
    } elseif ($new_status === 'cancelled') {
        $update_sql .= ", cancelled_at = NOW(), cancelled_by = :staff_uid";
        $update_params[':staff_uid'] = $_SESSION['user_id'];
    } elseif ($new_status === 'rejected') {
        $update_sql .= ", rejection_reason = :rejection";
        $update_params[':rejection'] = $rejection_reason ?: ($status_notes ?: 'Declined by intake staff');
    }

    $update_sql .= " WHERE appointment_id = :id";
    $stmt = $db->prepare($update_sql);
    $stmt->execute($update_params);

    $audit_note = $status_notes ?: ("Status updated to " . strtoupper($new_status) . ($window_assignment ? " at " . $window_assignment : ""));
    if ($new_status === 'rejected' && !empty($rejection_reason)) {
        $audit_note .= " Reason: " . $rejection_reason;
    }

    logAppointmentHistory($db, $appointment_id, $new_status, $audit_note, $_SESSION['user_id']);
    notifyAppointmentUpdate($db, $appointment_id, $new_status, $audit_note, $_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'message' => 'Appointment ' . ($appt_info['reference_number'] ?? $appointment_id) . ' status changed to ' . strtoupper($new_status),
        'appointment_id' => $appointment_id,
        'new_status' => $new_status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

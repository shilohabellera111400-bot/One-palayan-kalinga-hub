<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to book an appointment']);
    exit;
}

// Staff and Admin cannot book appointments
if (isStaff() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Staff members cannot book appointments as residents']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$service_id = sanitize($data['service_id']);
$appointment_date = sanitize($data['appointment_date']);
$appointment_time = sanitize($data['appointment_time']);
$purpose = sanitize($data['purpose'] ?? '');

// Validate date
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if slot is available
$check_query = "SELECT COUNT(*) as count FROM appointments 
                WHERE appointment_date = :date AND appointment_time = :time 
                AND status != 'cancelled'";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':date', $appointment_date);
$check_stmt->bindParam(':time', $appointment_time);
$check_stmt->execute();
$result = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] >= 5) {
    echo json_encode(['success' => false, 'message' => 'This time slot is fully booked']);
    exit;
}

// Get service details
$service_query = "SELECT service_name FROM services WHERE service_id = :id";
$service_stmt = $db->prepare($service_query);
$service_stmt->bindParam(':id', $service_id);
$service_stmt->execute();
$service = $service_stmt->fetch(PDO::FETCH_ASSOC);

// Get user email
$user_query = "SELECT email, first_name, last_name FROM users WHERE user_id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Start transaction
$db->beginTransaction();

try {
    // Create appointment with status 'pending'
    $query = "INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, purpose, status) 
              VALUES (:user_id, :service_id, :date, :time, :purpose, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->bindParam(':date', $appointment_date);
    $stmt->bindParam(':time', $appointment_time);
    $stmt->bindParam(':purpose', $purpose);
    $stmt->execute();
    
    $appointment_id = $db->lastInsertId();
    
    // Get full appointment details for email
    $appointment_query = "SELECT a.*, s.service_name 
                          FROM appointments a
                          JOIN services s ON a.service_id = s.service_id
                          WHERE a.appointment_id = :id";
    $appointment_stmt = $db->prepare($appointment_query);
    $appointment_stmt->bindParam(':id', $appointment_id);
    $appointment_stmt->execute();
    $appointment = $appointment_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create notification
    $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (:user_id, 'Appointment Booked', 
                    'Your appointment has been scheduled for " . date('F d, Y', strtotime($appointment_date)) . " at " . date('h:i A', strtotime($appointment_time)) . "', 'appointment')";
    $notif_stmt = $db->prepare($notif_query);
    $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $notif_stmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment booked successfully!',
        'appointment_id' => $appointment_id,
        'status' => 'pending'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $e->getMessage()]);
}
?>
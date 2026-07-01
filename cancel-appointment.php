<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to cancel appointment']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$appointment_id = sanitize($data['appointment_id']);

$database = new Database();
$db = $database->getConnection();

// Verify appointment belongs to user
$check_query = "SELECT * FROM appointments WHERE appointment_id = :id AND user_id = :user_id";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':id', $appointment_id);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();

if ($check_stmt->rowCount() == 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

// Update appointment status
$query = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $appointment_id);

if ($stmt->execute()) {
    // Create notification
    $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (:user_id, 'Appointment Cancelled', 
                    'Your appointment has been cancelled', 'appointment')";
    $notif_stmt = $db->prepare($notif_query);
    $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $notif_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
}
?>
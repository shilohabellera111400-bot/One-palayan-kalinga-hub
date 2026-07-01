<?php
require_once __DIR__ . '/config/database.php';

// Set header to return JSON
header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$database = new Database();
$db = $database->getConnection();

// Define available time slots
$time_slots = [
    '09:00:00' => '9:00 AM',
    '10:00:00' => '10:00 AM',
    '11:00:00' => '11:00 AM',
    '13:00:00' => '1:00 PM',
    '14:00:00' => '2:00 PM',
    '15:00:00' => '3:00 PM',
    '16:00:00' => '4:00 PM'
];

$slots = [];

foreach ($time_slots as $time => $label) {
    // Count appointments for this time slot
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE appointment_date = :date AND appointment_time = :time 
              AND status != 'cancelled'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':time', $time);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $booked_count = $result['count'];
    $max_slots = 5; // Maximum 5 appointments per time slot
    
    $slots[] = [
        'time' => $label,
        'value' => $time,
        'available' => $booked_count < $max_slots,
        'booked' => $booked_count,
        'remaining' => $max_slots - $booked_count
    ];
}

echo json_encode(['slots' => $slots, 'date' => $date]);
?>
<?php
// get-available-slots.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;

$database = new Database();
$db = $database->getConnection();

$result = getAvailableSlotsForDate($db, $date, $service_id);

if ($result['status'] === 'blackout') {
    echo json_encode([
        'slots'       => [],
        'date'        => $date,
        'is_blackout' => true,
        'reason'      => $result['message'],
    ]);
    exit;
}

if ($result['status'] === 'closed_day' || $result['status'] === 'fully_booked_day') {
    echo json_encode([
        'slots'   => [],
        'date'    => $date,
        'error'   => $result['message'],
        'status'  => $result['status']
    ]);
    exit;
}

$slots = [];
foreach ($result['slots'] as $s) {
    $slots[] = [
        'time'      => $s['label'],
        'value'     => $s['time'],
        'available' => ($s['status'] === 'available'),
        'remaining' => $s['remaining'],
        'total'     => $s['capacity'],
        'booked'    => $s['booked'],
        'status'    => $s['status']
    ];
}

echo json_encode([
    'slots' => $slots,
    'date'  => $date
]);
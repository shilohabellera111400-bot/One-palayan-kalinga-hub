<?php
// get-slots-ajax.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view appointment slots']);
    exit;
}

$date = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Date parameter is required']);
    exit;
}

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Cannot query dates in the past
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Cannot view slots for past dates', 'status' => 'past_date', 'slots' => []]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$result = getAvailableSlotsForDate($db, $date, $service_id);

echo json_encode([
    'success' => true,
    'date'    => $date,
    'status'  => $result['status'],
    'message' => $result['message'] ?? '',
    'slots'   => $result['slots']
]);

<?php
/**
 * get-blackout-dates.php
 * JSON endpoint — returns blackout dates for the next 90 days.
 * Used by the booking date picker to disable non-working days client-side.
 */
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

$database = new Database();
$db       = $database->getConnection();

$from = date('Y-m-d');
$to   = date('Y-m-d', strtotime('+90 days'));

$stmt = $db->prepare(
    "SELECT blackout_date, reason
     FROM blackout_dates
     WHERE blackout_date BETWEEN :from AND :to
     ORDER BY blackout_date ASC"
);
$stmt->bindParam(':from', $from);
$stmt->bindParam(':to',   $to);
$stmt->execute();

$dates = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dates[] = ['date' => $row['blackout_date'], 'reason' => $row['reason']];
}

echo json_encode(['blackout_dates' => $dates]);
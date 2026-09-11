<?php
// get-live-notifications-sse.php - Realtime SSE notifications endpoint
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

if (!isLoggedIn()) {
    echo "event: error\ndata: " . json_encode(['message' => 'Unauthorized']) . "\n\n";
    ob_flush();
    flush();
    exit;
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// Stream unread notifications
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$query = "SELECT notification_id, title, message, type, created_at 
          FROM notifications 
          WHERE user_id = :user_id AND is_read = 0 AND notification_id > :last_id 
          ORDER BY notification_id ASC LIMIT 5";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':last_id', $last_id);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($notifications)) {
    foreach ($notifications as $notif) {
        echo "event: notification\n";
        echo "data: " . json_encode($notif) . "\n\n";
    }
} else {
    echo ": heartbeat\n\n";
}

ob_flush();
flush();
?>

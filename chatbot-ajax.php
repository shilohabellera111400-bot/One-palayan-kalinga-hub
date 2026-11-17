<?php
/**
 * chatbot-ajax.php
 * One Palayan-Kalinga Hub - AI Citizen's Charter Chatbot AJAX Handler
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/CitizensCharterAI.php';

// Support both standard POST and JSON raw body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'error' => 'Message cannot be empty.'
    ]);
    exit;
}

// Resolve resident name if logged in
$userName = 'Kababayan';
if (!empty($_SESSION['full_name'])) {
    $userName = $_SESSION['full_name'];
} elseif (!empty($_SESSION['user_id'])) {
    try {
        $db = (new Database())->getConnection();
        $st = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = :uid LIMIT 1");
        $st->execute([':uid' => $_SESSION['user_id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $userName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Kababayan';
        }
    } catch (\Throwable $e) {}
}

try {
    $bot = new CitizensCharterAI();
    $result = $bot->chat($message, $history, $userName);
    echo json_encode($result);
} catch (\Throwable $err) {
    error_log("Chatbot AJAX Error: " . $err->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request. Please try again.'
    ]);
}

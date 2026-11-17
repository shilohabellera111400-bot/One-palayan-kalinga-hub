<?php
// admin/test-openai-ajax.php - Test OpenAI API Connection for Admin Settings
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/OpenAIHelper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: $_POST;

$apiKey = trim($data['api_key'] ?? '');
$model = trim($data['model'] ?? 'gpt-4o-mini');

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'message' => 'Please enter an OpenAI API key before testing.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$helper = new OpenAIHelper($db);

$res = $helper->testConnection($apiKey, $model);
echo json_encode($res);
exit;

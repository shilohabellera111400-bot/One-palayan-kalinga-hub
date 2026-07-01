<?php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters (same as audit-logs.php)
$action_filter = isset($_GET['action']) ? sanitize($_GET['action']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$user_filter = isset($_GET['user_id']) ? sanitize($_GET['user_id']) : 'all';

// Build query
$query = "SELECT al.*, 
          u.first_name, u.last_name, u.email, u.role,
          CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($action_filter != 'all') {
    $query .= " AND al.action = :action";
    $params[':action'] = $action_filter;
}

if ($user_filter != 'all') {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(al.created_at) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (al.action LIKE :search OR al.ip_address LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY al.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, ['Date', 'Time', 'User', 'Role', 'Action', 'Description', 'IP Address']);

// Add data rows
while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        date('Y-m-d', strtotime($log['created_at'])),
        date('H:i:s', strtotime($log['created_at'])),
        $log['user_name'] ?? 'System',
        $log['role'] ?? 'N/A',
        ucfirst($log['action']),
        $log['description'] ?? '',
        $log['ip_address']
    ]);
}

fclose($output);
exit;
<?php
// admin/admin-search-ajax.php - Multi-Table Administrative Real-Time Search
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'count' => 0,
        'results' => []
    ]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}

$like = '%' . $query . '%';
$results = [];
$total_count = 0;

try {
    // 1. Users & Residents
    $u_stmt = $db->prepare("
        SELECT user_id, first_name, last_name, email, role, verification_status, contact_number 
        FROM users 
        WHERE first_name LIKE :q1 OR last_name LIKE :q2 OR email LIKE :q3 OR username LIKE :q4 OR contact_number LIKE :q5
        ORDER BY created_at DESC LIMIT 6
    ");
    $u_stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like]);
    $users = [];
    while ($r = $u_stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = trim($r['first_name'] . ' ' . $r['last_name']);
        $role = ucfirst($r['role'] ?? 'user');
        $target_url = ($r['role'] === 'staff') ? 'manage-staff.php?search=' . urlencode($r['email']) : 'manage-residents.php?search=' . urlencode($r['email']);
        $users[] = [
            'id' => $r['user_id'],
            'title' => $name ?: $r['email'],
            'subtitle' => $r['email'] . ' • ' . $role . ' (' . ucfirst($r['verification_status'] ?? 'unverified') . ')',
            'badge' => $role,
            'url' => $target_url,
            'icon' => ($r['role'] === 'staff') ? 'fas fa-user-tie' : 'fas fa-user'
        ];
    }
    if (!empty($users)) {
        $results['Users & Residents'] = $users;
        $total_count += count($users);
    }

    // 2. Verification Requests
    $v_stmt = $db->prepare("
        SELECT v.verification_id, v.status, v.id_type, v.submitted_date, u.first_name, u.last_name 
        FROM verification_requests v 
        JOIN users u ON v.user_id = u.user_id 
        WHERE u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR v.verification_id LIKE :q3 OR v.id_number LIKE :q4
        ORDER BY v.submitted_date DESC LIMIT 5
    ");
    $v_stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
    $verifs = [];
    while ($r = $v_stmt->fetch(PDO::FETCH_ASSOC)) {
        $verifs[] = [
            'id' => $r['verification_id'],
            'title' => 'Verification #' . str_pad($r['verification_id'], 4, '0', STR_PAD_LEFT) . ' - ' . $r['first_name'] . ' ' . $r['last_name'],
            'subtitle' => ($r['id_type'] ?: 'Valid ID') . ' • Submitted ' . date('M d, Y', strtotime($r['submitted_date'])),
            'badge' => ucfirst($r['status'] ?? 'pending'),
            'url' => 'manage-verifications.php?search=' . urlencode($r['first_name']),
            'icon' => 'fas fa-shield-alt'
        ];
    }
    if (!empty($verifs)) {
        $results['Residency Verifications'] = $verifs;
        $total_count += count($verifs);
    }

    // 3. System Audit Logs
    $log_stmt = $db->prepare("
        SELECT log_id, user_id, action, table_affected, new_values, created_at 
        FROM audit_logs 
        WHERE action LIKE :q1 OR table_affected LIKE :q2 OR new_values LIKE :q3
        ORDER BY created_at DESC LIMIT 5
    ");
    $log_stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $logs = [];
    while ($r = $log_stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = [
            'id' => $r['log_id'],
            'title' => 'Audit: ' . ucfirst($r['action']) . ' (' . ($r['table_affected'] ?: 'system') . ')',
            'subtitle' => 'User #' . $r['user_id'] . ' • ' . date('M d, Y h:i A', strtotime($r['created_at'])),
            'badge' => 'Audit Log',
            'url' => 'audit-logs.php?search=' . urlencode($r['action']),
            'icon' => 'fas fa-history'
        ];
    }
    if (!empty($logs)) {
        $results['Audit Logs'] = $logs;
        $total_count += count($logs);
    }

    // 8. Announcements
    $anc_stmt = $db->prepare("
        SELECT announcement_id, title, category, created_at 
        FROM announcements 
        WHERE title LIKE :q1 OR content LIKE :q2 
        ORDER BY created_at DESC LIMIT 5
    ");
    $anc_stmt->execute([':q1' => $like, ':q2' => $like]);
    $announcements = [];
    while ($r = $anc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $announcements[] = [
            'id' => $r['announcement_id'],
            'title' => $r['title'],
            'subtitle' => 'Category: ' . ucfirst($r['category'] ?? 'General') . ' • ' . date('M d, Y', strtotime($r['created_at'])),
            'badge' => ucfirst($r['category'] ?? 'General'),
            'url' => 'announcements.php',
            'icon' => 'fas fa-bullhorn'
        ];
    }
    if (!empty($announcements)) {
        $results['Announcements'] = $announcements;
        $total_count += count($announcements);
    }

    echo json_encode([
        'success' => true,
        'count' => $total_count,
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search error: ' . $e->getMessage()
    ]);
}

<?php
// staff/delete-aics-ajax.php - AJAX endpoint to delete an AICS request
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$is_admin = function_exists('isAdmin') && isAdmin();
if (!$is_admin && !isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if staff has AICS service category permission (bypass check for Admins)
if (!$is_admin) {
    $staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
    $staff_id_stmt = $db->prepare($staff_id_query);
    $staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $staff_id_stmt->execute();
    $staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
    $staff_id = $staff_data ? $staff_data['id'] : 0;

    $cat_query = "SELECT DISTINCT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->bindParam(':staff_id', $staff_id);
    $cat_stmt->execute();
    $assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($assigned_categories) && !in_array('AICS', $assigned_categories)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage or delete AICS applications.']);
        exit;
    }
}

// Support both JSON input and standard form POST
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];

$aics_id = intval($json_data['aics_id'] ?? $_POST['aics_id'] ?? 0);

if ($aics_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing AICS ID.']);
    exit;
}

try {
    // 1. Fetch request details before deletion for audit logging and verification
    $req_stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name, u.email 
                              FROM aics_requests a 
                              LEFT JOIN users u ON a.user_id = u.user_id 
                              WHERE a.aics_id = :id");
    $req_stmt->execute([':id' => $aics_id]);
    $request = $req_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found or already deleted.']);
        exit;
    }

    if (strtolower($request['status'] ?? '') === 'released') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Disbursed/released assistance records cannot be deleted to preserve financial audit history.']);
        exit;
    }

    $tracking_code = 'AICS-' . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
    $applicant_name = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
    $type_label = ucfirst(str_replace('_', ' ', $request['request_type'] ?? ''));

    // 2. Collect uploaded documents to delete from disk
    $doc_stmt = $db->prepare("SELECT document_path FROM aics_documents WHERE aics_id = :id");
    $doc_stmt->execute([':id' => $aics_id]);
    $doc_files = $doc_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Collect comments attachments to delete from disk
    $comm_stmt = $db->prepare("SELECT attachment_path FROM aics_comments WHERE aics_id = :id");
    $comm_stmt->execute([':id' => $aics_id]);
    $comm_files = $comm_stmt->fetchAll(PDO::FETCH_COLUMN);

    $db->beginTransaction();

    // 4. Delete physical document files
    foreach ($doc_files as $doc_path) {
        if (!empty($doc_path)) {
            $filepath = __DIR__ . '/../' . ltrim($doc_path, '/\\');
            if (file_exists($filepath) && is_file($filepath)) {
                @unlink($filepath);
            }
        }
    }

    // 5. Delete physical comment attachment files
    foreach ($comm_files as $att_path) {
        if (!empty($att_path)) {
            $filepath = __DIR__ . '/../' . ltrim($att_path, '/\\');
            if (file_exists($filepath) && is_file($filepath)) {
                @unlink($filepath);
            }
        }
    }

    // 6. Delete comment records
    $del_comm_stmt = $db->prepare("DELETE FROM aics_comments WHERE aics_id = :id");
    $del_comm_stmt->execute([':id' => $aics_id]);

    // 7. Delete main AICS request (child tables cascade automatically)
    $del_req_stmt = $db->prepare("DELETE FROM aics_requests WHERE aics_id = :id");
    $del_req_stmt->execute([':id' => $aics_id]);

    // 8. Record audit trail
    $audit_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) 
                                VALUES (:user_id, 'delete_aics_request', 'aics_requests', :details)");
    $audit_details = "Deleted $tracking_code ($type_label, Status: {$request['status']}) for applicant $applicant_name";
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':details' => $audit_details
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Application $tracking_code has been permanently deleted.",
        'aics_id' => $aics_id,
        'tracking_code' => $tracking_code
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while deleting application: ' . $e->getMessage()
    ]);
}

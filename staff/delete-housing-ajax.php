<?php
// staff/delete-housing-ajax.php - AJAX endpoint to delete a housing application
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if staff has Housing service category permission
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

if (!in_array('Housing', $assigned_categories)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete Housing applications.']);
    exit;
}

// Support both JSON input and standard form POST
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];

$beneficiary_id = intval($json_data['beneficiary_id'] ?? $_POST['beneficiary_id'] ?? 0);

if ($beneficiary_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing Housing beneficiary ID.']);
    exit;
}

try {
    // 1. Fetch application details before deletion for audit trail
    $app_stmt = $db->prepare("SELECT h.*, p.program_name, u.first_name, u.last_name, u.email 
                              FROM housing_beneficiaries h 
                              JOIN housing_programs p ON h.program_id = p.program_id 
                              JOIN users u ON h.user_id = u.user_id 
                              WHERE h.beneficiary_id = :id");
    $app_stmt->execute([':id' => $beneficiary_id]);
    $application = $app_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Housing application not found or already deleted.']);
        exit;
    }

    if (strtolower($application['status'] ?? '') === 'released') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Released housing allocations cannot be deleted to preserve city land and housing audit history.']);
        exit;
    }

    $app_year = !empty($application['application_date']) ? date('Y', strtotime($application['application_date'])) : date('Y');
    $tracking_code = 'HSG-' . $app_year . '-' . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);
    $applicant_name = trim(($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? ''));

    $db->beginTransaction();

    // 2. Delete housing beneficiary record
    $del_stmt = $db->prepare("DELETE FROM housing_beneficiaries WHERE beneficiary_id = :id");
    $del_stmt->execute([':id' => $beneficiary_id]);

    // 3. Record audit trail
    $audit_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) 
                                VALUES (:user_id, 'delete_housing_application', 'housing_beneficiaries', :details)");
    $audit_details = "Deleted $tracking_code (Program: {$application['program_name']}, Status: {$application['status']}) for applicant $applicant_name";
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':details' => $audit_details
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Application $tracking_code has been permanently deleted.",
        'beneficiary_id' => $beneficiary_id,
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

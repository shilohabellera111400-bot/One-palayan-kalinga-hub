<?php
// staff/export-housing.php - Export Housing Beneficiaries Data to CSV
require_once __DIR__ . '/../config/database.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Check housing permission for staff users
if (isStaff() && !isAdmin()) {
    $staff_query = "SELECT id FROM staff WHERE user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $stf = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    $staff_id = $stf ? $stf['id'] : 0;
    
    $cat_query = "SELECT COUNT(*) FROM staff_service_assignments ssa
                  JOIN service_categories sc ON ssa.category_id = sc.category_id
                  WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1 AND sc.category_name = 'Housing'";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute([':staff_id' => $staff_id]);
    if ($cat_stmt->fetchColumn() == 0) {
        $_SESSION['error'] = "You don't have permission to export housing records.";
        redirect('manage-housing.php');
    }
}

// Get filter parameters matching manage-housing.php
$program_filter = isset($_GET['program']) ? sanitize($_GET['program']) : 'all';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT h.*, 
                 p.program_name, p.location as program_location,
                 u.first_name, u.last_name, u.contact_number, u.email, u.address,
                 r.barangay, r.street,
                 s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          LEFT JOIN residents r ON h.user_id = r.user_id
          LEFT JOIN staff s ON h.approved_by = s.user_id
          WHERE 1=1";

$params = [];

if ($program_filter !== 'all' && !empty($program_filter)) {
    $query .= " AND h.program_id = :program";
    $params[':program'] = $program_filter;
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $query .= " AND h.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter !== 'all' && !empty($type_filter)) {
    $query .= " AND h.housing_type = :housing_type";
    $params[':housing_type'] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY h.application_date DESC, h.beneficiary_id DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output filename
$filename = 'housing_applications_' . date('Y-m-d_His');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header Columns
fputcsv($output, [
    'Tracking Number',
    'Application ID',
    'Applicant Name',
    'Contact Number',
    'Email Address',
    'Resident Barangay',
    'Resident Street',
    'Housing Track',
    'Program Name',
    'Property / Resettlement Location',
    'Land Ownership Status',
    'Lot Area (sq.m)',
    'Construction Scope',
    'Household Size',
    'Monthly Income (PHP)',
    'Housing Need / Assessment Summary',
    'Application Date',
    'Status',
    'Unit Assigned / Grant Code',
    'Interview / Site Visit Date',
    'Processed By Staff',
    'Approval Date'
]);

// Data Rows
foreach ($records as $row) {
    $tracking_no = 'HSG-' . date('Y', strtotime($row['application_date'] ?? 'now')) . '-' . str_pad($row['beneficiary_id'], 5, '0', STR_PAD_LEFT);
    $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $staff_name = !empty($row['staff_first_name']) ? trim($row['staff_first_name'] . ' ' . ($row['staff_last_name'] ?? '')) : 'N/A';
    $track = ($row['housing_type'] === 'in_site') ? 'In-Site Housing (Sariling Lupa)' : 'Relocation Housing (Resettlement)';
    $location = ($row['housing_type'] === 'in_site') 
        ? (!empty($row['property_location']) ? $row['property_location'] : $row['program_location']) 
        : $row['program_location'];
    
    // Strip multi-line breaks for single-cell readability
    $need_assessment = str_replace(["\r\n", "\r", "\n"], " | ", $row['housing_need_assessment'] ?? '');

    fputcsv($output, [
        $tracking_no,
        $row['beneficiary_id'],
        $full_name,
        $row['contact_number'] ?? '',
        $row['email'] ?? '',
        $row['barangay'] ?? '',
        $row['street'] ?? '',
        $track,
        $row['program_name'] ?? '',
        $location,
        $row['land_ownership_status'] ?? 'N/A',
        $row['lot_area'] ?? 'N/A',
        $row['construction_type'] ?? 'N/A',
        $row['household_composition'] ?? '',
        number_format((float)($row['monthly_income'] ?? 0), 2, '.', ''),
        $need_assessment,
        $row['application_date'] ?? '',
        ucfirst($row['status'] ?? 'pending'),
        $row['unit_assigned'] ?? '',
        $row['interview_date'] ?? '',
        $staff_name,
        $row['approval_date'] ?? ''
    ]);
}

fclose($output);
exit();

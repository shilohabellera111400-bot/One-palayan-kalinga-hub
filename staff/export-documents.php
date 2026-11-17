<?php
// staff/export-documents.php - Export Document Verification Data to CSV
require_once __DIR__ . '/../config/database.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT d.*, 
                 u.first_name, u.last_name, u.email, u.contact_number,
                 v.first_name as verified_by_fn, v.last_name as verified_by_ln
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          LEFT JOIN users v ON d.verified_by = v.user_id
          WHERE 1=1";

$params = [];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $query .= " AND d.verification_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR d.document_type LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.upload_date DESC, d.document_id DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'document_verifications_' . date('Y-m-d_His');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header Columns
fputcsv($output, [
    'Document ID',
    'Applicant Name',
    'Contact Number',
    'Email Address',
    'Document Type',
    'File Storage Path',
    'Uploaded Date & Time',
    'Verification Status',
    'Verified / Reviewed By',
    'Verification Date',
    'Review Remarks / Disapproval Reason'
]);

// Data Rows
foreach ($records as $doc) {
    $applicantName = trim(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? ''));
    $verifierName = trim(($doc['verified_by_fn'] ?? '') . ' ' . ($doc['verified_by_ln'] ?? ''));
    if (empty($verifierName) && !empty($doc['verified_by'])) {
        $verifierName = 'Staff ID #' . $doc['verified_by'];
    }

    $statusLabel = ucfirst($doc['verification_status'] ?? 'pending');
    if ($statusLabel === 'Rejected') {
        $statusLabel = 'Disapproved';
    }

    fputcsv($output, [
        'DOC-' . str_pad($doc['document_id'], 5, '0', STR_PAD_LEFT),
        $applicantName,
        $doc['contact_number'] ?? 'N/A',
        $doc['email'] ?? 'N/A',
        ucfirst(str_replace('_', ' ', $doc['document_type'] ?? '')),
        $doc['document_path'] ?? '',
        !empty($doc['upload_date']) ? date('Y-m-d H:i:s', strtotime($doc['upload_date'])) : '',
        $statusLabel,
        $verifierName ?: 'Unassigned',
        !empty($doc['verification_date']) ? date('Y-m-d H:i:s', strtotime($doc['verification_date'])) : 'Pending',
        $doc['remarks'] ?? ''
    ]);
}

fclose($output);
exit();

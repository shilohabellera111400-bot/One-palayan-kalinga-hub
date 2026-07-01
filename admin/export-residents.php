<?php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'csv';

// Build query
$query = "SELECT * FROM residents WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR resident_number LIKE :search OR contact_number LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($barangay_filter)) {
    $query .= " AND barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

if ($status_filter !== '') {
    $query .= " AND is_active = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set filename
$filename = 'residents_export_' . date('Y-m-d_H-i-s');

// Export based on format
if ($format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Resident Number', 'First Name', 'Last Name', 'Middle Name', 'Suffix', 
        'Gender', 'Birth Date', 'Age', 'Civil Status', 'Contact Number', 
        'Email', 'Street', 'Barangay', 'City', 'Province', 'Postal Code',
        'Household Number', 'Senior Citizen', 'PWD', 'Occupation', 
        'Monthly Income', 'Educational Attainment', 'Emergency Contact Name',
        'Emergency Contact Number', 'Relationship', 'Status', 'Registered Date'
    ]);
    
    // Data rows
    foreach ($residents as $resident) {
        $age = date_diff(date_create($resident['birth_date']), date_create('today'))->y;
        fputcsv($output, [
            $resident['resident_number'],
            $resident['first_name'],
            $resident['last_name'],
            $resident['middle_name'] ?? '',
            $resident['suffix'] ?? '',
            $resident['gender'],
            $resident['birth_date'],
            $age,
            $resident['civil_status'],
            $resident['contact_number'] ?? '',
            $resident['email'] ?? '',
            $resident['street'] ?? '',
            $resident['barangay'] ?? '',
            $resident['city'] ?? '',
            $resident['province'] ?? '',
            $resident['postal_code'] ?? '',
            $resident['household_number'] ?? '',
            $resident['senior_citizen'] ? 'Yes' : 'No',
            $resident['pwd'] ? 'Yes' : 'No',
            $resident['occupation'] ?? '',
            $resident['monthly_income'] ?? 0,
            $resident['educational_attainment'] ?? '',
            $resident['emergency_contact_name'] ?? '',
            $resident['emergency_contact_number'] ?? '',
            $resident['relationship'] ?? '',
            $resident['is_active'] ? 'Active' : 'Inactive',
            date('Y-m-d', strtotime($resident['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($format == 'excel') {
    // For Excel, we'll use CSV with .xls extension (simple approach)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Resident Number</th><th>First Name</th><th>Last Name</th><th>Middle Name</th><th>Suffix</th>';
    echo '<th>Gender</th><th>Birth Date</th><th>Age</th><th>Civil Status</th><th>Contact Number</th>';
    echo '<th>Email</th><th>Street</th><th>Barangay</th><th>City</th><th>Province</th><th>Postal Code</th>';
    echo '<th>Household Number</th><th>Senior Citizen</th><th>PWD</th><th>Occupation</th>';
    echo '<th>Monthly Income</th><th>Educational Attainment</th><th>Emergency Contact Name</th>';
    echo '<th>Emergency Contact Number</th><th>Relationship</th><th>Status</th><th>Registered Date</th>';
    echo '</tr>';
    
    foreach ($residents as $resident) {
        $age = date_diff(date_create($resident['birth_date']), date_create('today'))->y;
        echo '<tr>';
        echo '<td>' . htmlspecialchars($resident['resident_number']) . '</td>';
        echo '<td>' . htmlspecialchars($resident['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($resident['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($resident['middle_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['suffix'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['gender']) . '</td>';
        echo '<td>' . $resident['birth_date'] . '</td>';
        echo '<td>' . $age . '</td>';
        echo '<td>' . htmlspecialchars($resident['civil_status']) . '</td>';
        echo '<td>' . htmlspecialchars($resident['contact_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['street'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['barangay'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['city'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['province'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['postal_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['household_number'] ?? '') . '</td>';
        echo '<td>' . ($resident['senior_citizen'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . ($resident['pwd'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . htmlspecialchars($resident['occupation'] ?? '') . '</td>';
        echo '<td>' . number_format($resident['monthly_income'] ?? 0, 2) . '</td>';
        echo '<td>' . htmlspecialchars($resident['educational_attainment'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['emergency_contact_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['emergency_contact_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($resident['relationship'] ?? '') . '</td>';
        echo '<td>' . ($resident['is_active'] ? 'Active' : 'Inactive') . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($resident['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
    
} elseif ($format == 'pdf') {
    // For PDF, we'll redirect to a PDF generation page or use a library
    // For now, redirect back with message
    header('Location: export-residents.php?error=PDF export requires additional library setup. Please use CSV or Excel format.');
    exit;
}
?>
<?php
// staff/reports.php - CSWDO Staff Reports & Analytics Hub
require_once __DIR__ . '/../config/database.php';

if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    $features_path = __DIR__ . '/../config/features.php';
    if (file_exists($features_path)) {
        require_once $features_path;
    }
}
$is_pwd_senior_enabled = defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR;

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Staff service access check
$staff_id_query = "SELECT s.id, s.position, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_info = $staff_data;
$staff_id = $staff_data ? $staff_data['id'] : 0;

$assigned_categories = [];
if (!isAdmin()) {
    $cat_query = "SELECT DISTINCT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->bindParam(':staff_id', $staff_id);
    $cat_stmt->execute();
    $assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Active Report Category (Item 10)
$category = isset($_GET['category']) ? sanitize($_GET['category']) : (isset($_GET['type']) ? sanitize($_GET['type']) : 'residents');
$valid_categories = ['residents', 'aics', 'housing', 'senior', 'pwd', 'appointments', 'request_status', 'documents'];
if (!$is_pwd_senior_enabled && in_array($category, ['senior', 'pwd'])) {
    $category = 'residents';
}
if (!in_array($category, $valid_categories)) {
    $category = 'residents';
}

// Date Range Filters
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01', strtotime('-2 months'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// ==========================================
// CSV EXPORT ENGINE
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'cswdo_report_' . $category . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    
    if ($category === 'residents') {
        fputcsv($out, ['Palayan City CSWDO - Master Resident Registry Report']);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s'), 'Total Records']);
        fputcsv($out, []);
        fputcsv($out, ['Resident ID', 'Full Name', 'Gender', 'Birth Date', 'Barangay', 'Street', 'Contact Number', 'Senior', 'PWD', 'Status', 'Registered Date']);
        $r_stmt = $db->query("SELECT * FROM residents ORDER BY barangay ASC, last_name ASC");
        while ($r = $r_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['resident_number'],
                $r['first_name'] . ' ' . $r['last_name'],
                $r['gender'],
                $r['birth_date'],
                $r['barangay'],
                $r['street'] ?? '',
                $r['contact_number'] ?? 'N/A',
                $r['senior_citizen'] ? 'Yes' : 'No',
                $r['pwd'] ? 'Yes' : 'No',
                $r['is_active'] ? 'Active' : 'Inactive',
                $r['created_at']
            ]);
        }
    } elseif ($category === 'aics') {
        fputcsv($out, ['Palayan City CSWDO - AICS Assistance Program Report']);
        fputcsv($out, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($out, []);
        fputcsv($out, ['Tracking #', 'Applicant Name', 'Category', 'Requested Amount', 'Approved Amount', 'Status', 'Date Submitted']);
        $a_stmt = $db->prepare("SELECT a.*, c.category_name, u.first_name, u.last_name 
                                FROM aics_requests a 
                                LEFT JOIN aics_categories c ON a.category_id = c.category_id 
                                LEFT JOIN users u ON a.user_id = u.user_id 
                                WHERE DATE(a.submission_date) BETWEEN :from AND :to 
                                ORDER BY a.submission_date DESC");
        $a_stmt->execute([':from' => $date_from, ':to' => $date_to]);
        while ($a = $a_stmt->fetch(PDO::FETCH_ASSOC)) {
            $track = 'AICS-' . str_pad($a['request_id'], 6, '0', STR_PAD_LEFT);
            fputcsv($out, [
                $track,
                ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''),
                $a['category_name'] ?? 'General Assistance',
                number_format($a['amount_requested'] ?? 0, 2),
                number_format($a['amount_approved'] ?? 0, 2),
                ucfirst($a['status']),
                $a['submission_date']
            ]);
        }
    } elseif ($category === 'housing') {
        fputcsv($out, ['Palayan City CSWDO - Housing Beneficiaries Report']);
        fputcsv($out, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($out, []);
        fputcsv($out, ['Tracking #', 'Applicant Name', 'Program', 'Location', 'Housing Type', 'Status', 'Application Date']);
        $h_stmt = $db->prepare("SELECT h.*, p.program_name, p.location, u.first_name, u.last_name 
                                FROM housing_beneficiaries h 
                                JOIN housing_programs p ON h.program_id = p.program_id 
                                JOIN users u ON h.user_id = u.user_id 
                                WHERE DATE(h.application_date) BETWEEN :from AND :to 
                                ORDER BY h.application_date DESC");
        $h_stmt->execute([':from' => $date_from, ':to' => $date_to]);
        while ($h = $h_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $h['tracking_number'] ?? ('HSG-' . str_pad($h['beneficiary_id'], 5, '0', STR_PAD_LEFT)),
                ($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''),
                $h['program_name'],
                $h['location'],
                ucfirst($h['housing_type'] ?? 'N/A'),
                ucfirst($h['status']),
                $h['application_date']
            ]);
        }
    } elseif ($category === 'senior') {
        fputcsv($out, ['Palayan City CSWDO - Senior Citizen (OSCA) Reports']);
        fputcsv($out, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($out, []);
        fputcsv($out, ['Application ID', 'Senior Citizen Name', 'Birth Date', 'Barangay', 'OSCA ID Number', 'Booklet Number', 'Status', 'Date Applied']);
        $s_stmt = $db->prepare("SELECT s.*, u.first_name, u.last_name, u.birth_date, r.barangay 
                                FROM senior_applications s 
                                JOIN users u ON s.user_id = u.user_id 
                                LEFT JOIN residents r ON s.user_id = r.user_id 
                                WHERE DATE(s.created_at) BETWEEN :from AND :to 
                                ORDER BY s.created_at DESC");
        $s_stmt->execute([':from' => $date_from, ':to' => $date_to]);
        while ($s = $s_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                'OSCA-' . str_pad($s['application_id'], 5, '0', STR_PAD_LEFT),
                ($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''),
                $s['birth_date'] ?? 'N/A',
                $s['barangay'] ?? 'Palayan City',
                $s['osca_id_number'] ?? 'Pending',
                $s['booklet_number'] ?? 'Pending',
                ucfirst($s['status']),
                $s['created_at']
            ]);
        }
    } elseif ($category === 'pwd') {
        fputcsv($out, ['Palayan City CSWDO - PWD Sectoral Applications Report']);
        fputcsv($out, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($out, []);
        fputcsv($out, ['Application ID', 'Applicant Name', 'Disability Type', 'PWD ID Number', 'Booklet Number', 'Status', 'Date Applied']);
        $p_stmt = $db->prepare("SELECT p.*, u.first_name, u.last_name 
                                FROM pwd_applications p 
                                JOIN users u ON p.user_id = u.user_id 
                                WHERE DATE(p.created_at) BETWEEN :from AND :to 
                                ORDER BY p.created_at DESC");
        $p_stmt->execute([':from' => $date_from, ':to' => $date_to]);
        while ($p = $p_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                'PWD-' . str_pad($p['application_id'], 5, '0', STR_PAD_LEFT),
                ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''),
                $p['disability_type'] ?? 'N/A',
                $p['pwd_id_number'] ?? 'Pending',
                $p['booklet_number'] ?? 'Pending',
                ucfirst($p['status']),
                $p['created_at']
            ]);
        }
    } elseif ($category === 'appointments') {
        fputcsv($out, ['Palayan City CSWDO - Citizen Appointments & Visits Report']);
        fputcsv($out, ['Period: ' . $date_from . ' to ' . $date_to]);
        fputcsv($out, []);
        fputcsv($out, ['Reference #', 'Citizen Name', 'Service Requested', 'Schedule Date', 'Time', 'Window Assignment', 'Status']);
        $ap_stmt = $db->prepare("SELECT a.*, s.service_name, u.first_name, u.last_name 
                                 FROM appointments a 
                                 LEFT JOIN services s ON a.service_id = s.service_id 
                                 JOIN users u ON a.user_id = u.user_id 
                                 WHERE a.appointment_date BETWEEN :from AND :to 
                                 ORDER BY a.appointment_date DESC, a.appointment_time ASC");
        $ap_stmt->execute([':from' => $date_from, ':to' => $date_to]);
        while ($ap = $ap_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $ap['reference_number'] ?? ('PAL-SOC-' . $ap['appointment_id']),
                ($ap['first_name'] ?? '') . ' ' . ($ap['last_name'] ?? ''),
                $ap['service_name'] ?? 'Social Welfare Service',
                $ap['appointment_date'],
                $ap['appointment_time'],
                $ap['window_assignment'] ?? 'General Queue',
                ucfirst($ap['status'])
            ]);
        }
    } elseif ($category === 'request_status') {
        fputcsv($out, ['Palayan City CSWDO - Consolidated Request Status Overview']);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []);
        fputcsv($out, ['Program / Service Area', 'Total Filed', 'Pending Intake', 'In Review', 'Approved / Qualified', 'Released / Completed', 'Disapproved', 'Fulfillment Rate %']);
        
        // Compute matrix
        $matrix = [
            'AICS Financial Relief' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM aics_requests")->fetch(PDO::FETCH_ASSOC),
            'Housing Beneficiaries' => $db->query("SELECT COUNT(*) as total, SUM(status='pending') as p, SUM(status='qualified') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM housing_beneficiaries")->fetch(PDO::FETCH_ASSOC),
            'Senior Citizen (OSCA)' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM senior_applications")->fetch(PDO::FETCH_ASSOC),
            'PWD Assistance' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM pwd_applications")->fetch(PDO::FETCH_ASSOC),
            'Citizen Appointments' => $db->query("SELECT COUNT(*) as total, SUM(status='pending') as p, SUM(status='confirmed') as r, SUM(status='checked_in') as a, SUM(status='completed') as rel, SUM(status='cancelled') as rej FROM appointments")->fetch(PDO::FETCH_ASSOC),
            'Document Verifications' => $db->query("SELECT COUNT(*) as total, SUM(verification_status='pending') as p, 0 as r, SUM(verification_status='verified') as a, SUM(verification_status='verified') as rel, SUM(verification_status='rejected') as rej FROM documents")->fetch(PDO::FETCH_ASSOC),
        ];

        foreach ($matrix as $prog => $m) {
            $tot = (int)($m['total'] ?? 0);
            $done = (int)($m['rel'] ?? 0) + (int)($m['a'] ?? 0);
            $rate = $tot > 0 ? round(($done / $tot) * 100, 1) . '%' : '0%';
            fputcsv($out, [$prog, $tot, (int)($m['p'] ?? 0), (int)($m['r'] ?? 0), (int)($m['a'] ?? 0), (int)($m['rel'] ?? 0), (int)($m['rej'] ?? 0), $rate]);
        }
    }
    
    fclose($out);
    exit();
}

// ==========================================
// DATA FETCHING PER CATEGORY
// ==========================================
$data = [];
$stats = [];

if ($category === 'residents') {
    $res_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN senior_citizen = 1 THEN 1 ELSE 0 END) as senior,
        SUM(CASE WHEN pwd = 1 THEN 1 ELSE 0 END) as pwd,
        COUNT(DISTINCT barangay) as barangays_count
        FROM residents";
    $stats = $db->query($res_stats_q)->fetch(PDO::FETCH_ASSOC);

    // Breakdown by Barangay
    $brgy_q = "SELECT 
        barangay,
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN senior_citizen = 1 THEN 1 ELSE 0 END) as senior,
        SUM(CASE WHEN pwd = 1 THEN 1 ELSE 0 END) as pwd,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as males,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as females
        FROM residents
        GROUP BY barangay
        ORDER BY total DESC";
    $data['barangays'] = $db->query($brgy_q)->fetchAll(PDO::FETCH_ASSOC);

    // Recent registered
    $rec_q = "SELECT * FROM residents ORDER BY created_at DESC LIMIT 15";
    $data['recent'] = $db->query($rec_q)->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'aics') {
    $aics_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(amount_requested) as sum_requested,
        SUM(amount_approved) as sum_approved
        FROM aics_requests
        WHERE DATE(submission_date) BETWEEN :from AND :to";
    $stmt = $db->prepare($aics_stats_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Daily breakdown
    $daily_q = "SELECT 
        DATE(submission_date) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(amount_approved) as approved_amount
        FROM aics_requests
        WHERE DATE(submission_date) BETWEEN :from AND :to
        GROUP BY DATE(submission_date)
        ORDER BY date DESC LIMIT 20";
    $stmt = $db->prepare($daily_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $data['breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'housing') {
    $hsg_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_beneficiaries
        WHERE DATE(application_date) BETWEEN :from AND :to";
    $stmt = $db->prepare($hsg_stats_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Breakdown by Program
    $prog_q = "SELECT 
        p.program_name, p.location,
        COUNT(h.beneficiary_id) as total_applicants,
        SUM(CASE WHEN h.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN h.status = 'qualified' THEN 1 ELSE 0 END) as qualified,
        SUM(CASE WHEN h.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN h.status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_programs p
        LEFT JOIN housing_beneficiaries h ON p.program_id = h.program_id AND DATE(h.application_date) BETWEEN :from AND :to
        GROUP BY p.program_id
        ORDER BY total_applicants DESC";
    $stmt = $db->prepare($prog_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $data['programs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'senior') {
    $snr_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(osca_id_number) as osca_ids,
        COUNT(booklet_number) as booklets
        FROM senior_applications
        WHERE DATE(created_at) BETWEEN :from AND :to";
    $stmt = $db->prepare($snr_stats_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $snr_list_q = "SELECT s.*, u.first_name, u.last_name, u.birth_date, r.barangay 
                   FROM senior_applications s 
                   JOIN users u ON s.user_id = u.user_id 
                   LEFT JOIN residents r ON s.user_id = r.user_id 
                   WHERE DATE(s.created_at) BETWEEN :from AND :to 
                   ORDER BY s.created_at DESC LIMIT 20";
    $stmt = $db->prepare($snr_list_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $data['applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'pwd') {
    $pwd_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(pwd_id_number) as pwd_ids,
        COUNT(booklet_number) as booklets
        FROM pwd_applications
        WHERE DATE(created_at) BETWEEN :from AND :to";
    $stmt = $db->prepare($pwd_stats_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $pwd_list_q = "SELECT p.*, u.first_name, u.last_name 
                   FROM pwd_applications p 
                   JOIN users u ON p.user_id = u.user_id 
                   WHERE DATE(p.created_at) BETWEEN :from AND :to 
                   ORDER BY p.created_at DESC LIMIT 20";
    $stmt = $db->prepare($pwd_list_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $data['applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'appointments') {
    $app_stats_q = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to";
    $stmt = $db->prepare($app_stats_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Schedule breakdown
    $daily_app_q = "SELECT 
        appointment_date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as active_visit,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to
        GROUP BY appointment_date
        ORDER BY appointment_date DESC LIMIT 20";
    $stmt = $db->prepare($daily_app_q);
    $stmt->execute([':from' => $date_from, ':to' => $date_to]);
    $data['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($category === 'request_status') {
    // Cross-system matrix
    $matrix_query = [
        [
            'module' => 'AICS Assistance Relief',
            'icon' => 'fa-hand-holding-heart',
            'color' => '#7F0303',
            'link' => 'manage-aics.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM aics_requests")->fetch(PDO::FETCH_ASSOC)
        ],
        [
            'module' => 'Housing & Resettlement',
            'icon' => 'fa-home',
            'color' => '#2563EB',
            'link' => 'manage-housing.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(status='pending') as p, SUM(status='qualified') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM housing_beneficiaries")->fetch(PDO::FETCH_ASSOC)
        ],
        [
            'module' => 'Senior Citizen (OSCA)',
            'icon' => 'fa-person-cane',
            'color' => '#D97706',
            'link' => 'manage-senior.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM senior_applications")->fetch(PDO::FETCH_ASSOC)
        ],
        [
            'module' => 'PWD Assistance & ID',
            'icon' => 'fa-wheelchair',
            'color' => '#4F46E5',
            'link' => 'manage-pwd.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(status='submitted') as p, SUM(status='reviewing') as r, SUM(status='approved') as a, SUM(status='released') as rel, SUM(status='rejected') as rej FROM pwd_applications")->fetch(PDO::FETCH_ASSOC)
        ],
        [
            'module' => 'Intake Desk Appointments',
            'icon' => 'fa-calendar-check',
            'color' => '#059669',
            'link' => 'manage-appointments.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(status='pending') as p, SUM(status='confirmed') as r, SUM(status='checked_in') as a, SUM(status='completed') as rel, SUM(status='cancelled') as rej FROM appointments")->fetch(PDO::FETCH_ASSOC)
        ],
        [
            'module' => 'Document Verifications',
            'icon' => 'fa-file-shield',
            'color' => '#0284C7',
            'link' => 'manage-residents.php',
            'data' => $db->query("SELECT COUNT(*) as total, SUM(verification_status='pending') as p, 0 as r, SUM(verification_status='verified') as a, SUM(verification_status='verified') as rel, SUM(verification_status='rejected') as rej FROM documents")->fetch(PDO::FETCH_ASSOC)
        ]
    ];
    $data['matrix'] = $matrix_query;

    $total_all = 0;
    $total_done = 0;
    $total_pending = 0;
    $total_reviewing = 0;
    foreach ($matrix_query as $m) {
        $total_all += (int)($m['data']['total'] ?? 0);
        $total_done += (int)($m['data']['rel'] ?? 0) + (int)($m['data']['a'] ?? 0);
        $total_pending += (int)($m['data']['p'] ?? 0);
        $total_reviewing += (int)($m['data']['r'] ?? 0);
    }
    $stats = [
        'total' => $total_all,
        'completed' => $total_done,
        'pending' => $total_pending,
        'reviewing' => $total_reviewing,
        'fulfillment_rate' => $total_all > 0 ? round(($total_done / $total_all) * 100, 1) : 0
    ];
}

// --- CHART DATASETS (Chart.js) ---
$aics_monthly_labels = [];
$aics_monthly_counts = [];
$aics_monthly_disbursed = [];
try {
    $aics_m_stmt = $db->query("SELECT DATE_FORMAT(submission_date, '%b %Y') as m_lbl,
                                      COUNT(*) as total_apps,
                                      SUM(COALESCE(amount_approved, 0)) as sum_rel
                               FROM aics_requests
                               GROUP BY DATE_FORMAT(submission_date, '%Y-%m'), m_lbl
                               ORDER BY MIN(submission_date) ASC
                               LIMIT 6");
    while ($m = $aics_m_stmt->fetch(PDO::FETCH_ASSOC)) {
        $aics_monthly_labels[] = $m['m_lbl'];
        $aics_monthly_counts[] = (int)$m['total_apps'];
        $aics_monthly_disbursed[] = (float)$m['sum_rel'];
    }
} catch(Exception $e) {}

if (empty($aics_monthly_labels)) {
    $aics_monthly_labels = [date('M', strtotime('-2 months')), date('M', strtotime('-1 month')), date('M')];
    $aics_monthly_counts = [12, 18, 25];
    $aics_monthly_disbursed = [15000, 24000, 35000];
}

$aics_cat_labels = [];
$aics_cat_counts = [];
try {
    $aics_c_stmt = $db->query("SELECT request_type, COUNT(*) as cnt FROM aics_requests GROUP BY request_type ORDER BY cnt DESC");
    while ($c = $aics_c_stmt->fetch(PDO::FETCH_ASSOC)) {
        $aics_cat_labels[] = ucwords(str_replace('_', ' ', $c['request_type']));
        $aics_cat_counts[] = (int)$c['cnt'];
    }
} catch(Exception $e) {}
if (empty($aics_cat_labels)) {
    $aics_cat_labels = ['Medical', 'Burial', 'Food', 'Cash Relief'];
    $aics_cat_counts = [15, 6, 9, 12];
}

$hsg_labels = [];
$hsg_counts = [];
try {
    $hsg_c_stmt = $db->query("SELECT COALESCE(status, 'pending') as st, COUNT(*) as cnt FROM housing_beneficiaries GROUP BY status");
    while ($h = $hsg_c_stmt->fetch(PDO::FETCH_ASSOC)) {
        $hsg_labels[] = ucfirst($h['st']);
        $hsg_counts[] = (int)$h['cnt'];
    }
} catch(Exception $e) {}
if (empty($hsg_labels)) {
    $hsg_labels = ['Pending', 'Qualified', 'Awarded', 'Turnover'];
    $hsg_counts = [5, 8, 3, 2];
}

$brgy_chart_labels = [];
$brgy_chart_counts = [];
try {
    $brgy_stmt = $db->query("SELECT barangay, COUNT(*) as cnt FROM residents WHERE barangay IS NOT NULL AND barangay != '' GROUP BY barangay ORDER BY cnt DESC LIMIT 6");
    while ($b = $brgy_stmt->fetch(PDO::FETCH_ASSOC)) {
        $brgy_chart_labels[] = $b['barangay'];
        $brgy_chart_counts[] = (int)$b['cnt'];
    }
} catch(Exception $e) {}

$page_title = 'Reports & Analytics - CSWDO Staff Portal';
$active_nav = 'reports';
require_once __DIR__ . '/includes/staff-head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Charts Grid & Cards */
.report-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 22px;
}
.report-chart-box {
    background: var(--ref-card);
    border-radius: 18px;
    padding: 20px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
    display: flex;
    flex-direction: column;
}
.report-chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--ref-border);
}
.report-chart-header h4 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--ref-text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-canvas-wrap {
    position: relative;
    height: 250px;
    width: 100%;
}
@media (max-width: 900px) {
    .report-charts-grid {
        grid-template-columns: 1fr;
    }
}
/* Compact Reports Category Toolbar - Part 2, Item 7 */
.report-category-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    border-radius: 14px;
    padding: 4px;
    margin-bottom: 14px;
    overflow-x: auto;
    scrollbar-width: none;
}
.report-category-nav::-webkit-scrollbar {
    display: none;
}
.cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    background: transparent;
    border: 1px solid transparent;
    color: var(--ref-muted);
    transition: all 0.18s ease;
}
.cat-pill:hover {
    color: var(--ref-text);
    background: rgba(255, 255, 255, 0.4);
}
.cat-pill.active {
    background: #FFFFFF;
    color: var(--ref-maroon, #7F0303);
    border-color: var(--ref-border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
[data-theme="dark"] .cat-pill.active {
    background: #1B292F;
    color: #FFFFFF;
    border-color: rgba(255, 255, 255, 0.12);
}
.cat-pill.active i {
    color: var(--ref-maroon) !important;
}
[data-theme="dark"] .cat-pill.active i {
    color: #FEF7DF !important;
}

/* Compact Filter Bar & Info Header Toolbar */
.reports-control-bar {
    background: var(--ref-card);
    border-radius: 16px;
    padding: 10px 18px;
    margin-bottom: 20px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.reports-info-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(127, 3, 3, 0.06);
    color: var(--ref-maroon, #7F0303);
    border: 1px solid rgba(127, 3, 3, 0.12);
    border-radius: 50px;
    padding: 7px 16px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

[data-theme="dark"] .reports-info-badge {
    background: rgba(248, 113, 113, 0.1);
    color: #F87171;
    border-color: rgba(248, 113, 113, 0.2);
}

.reports-info-badge i {
    font-size: 0.88rem;
}
.reports-filter-form {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.reports-filter-form .input-group {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ref-muted);
}
.reports-filter-form input[type="date"] {
    height: 38px;
    padding: 0 10px;
    border: 1px solid var(--ref-border);
    border-radius: 8px;
    font-size: 0.8rem;
    background: var(--ref-canvas);
    color: var(--ref-text);
    outline: none;
}
.btn-filter-go {
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    color: var(--ref-text);
    height: 38px;
    padding: 0 14px;
    border-radius: 50px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-filter-go:hover { background: var(--ref-card); border-color: var(--ref-maroon); color: var(--ref-maroon); }

.btn-export-csv {
    background: #065F46;
    color: #FFFFFF;
    border: none;
    height: 38px;
    padding: 0 18px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 2px 6px rgba(6, 95, 70, 0.2);
    transition: all 0.18s;
}
.btn-export-csv:hover { background: #047857; transform: translateY(-1px); color: #FFFFFF; }

/* Minimalist Stat Cards (Part 2, Item 7) */
.reports-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.report-stat-card {
    background: var(--ref-card);
    border-radius: 16px;
    padding: 14px 18px;
    border: 1px solid var(--ref-border);
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 14px;
}
.report-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.report-stat-info h3 {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--ref-text);
    margin: 0 0 2px 0;
    line-height: 1.1;
}
.report-stat-info p {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0;
}

/* Report Table Container */
.report-data-card {
    background: var(--ref-card);
    border-radius: 18px;
    padding: 20px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
}
.report-data-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ref-border);
}
.report-data-card .card-header h2 {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ref-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.report-table-scroll {
    overflow-x: auto;
}
.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.report-table th {
    background: var(--ref-canvas);
    color: var(--ref-muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    padding: 10px 14px;
    border-bottom: 1px solid var(--ref-border);
    text-align: left;
}
.report-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ref-border);
    color: var(--ref-text);
    vertical-align: middle;
}
.report-table tr:hover td { background: #FAF8F5; }
.badge-rate {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
}
.badge-rate.high { background: #DCFCE7; color: #15803D; }
.badge-rate.med { background: #FEF3C7; color: #B45309; }
.badge-rate.low { background: #FEE2E2; color: #B91C1C; }

@media (max-width: 768px) {
    .reports-control-bar { flex-direction: column; align-items: stretch; }
    .reports-filter-form { flex-direction: column; align-items: stretch; }
    .btn-export-csv { justify-content: center; }
}
</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Reports & Analytics Hub';
            $banner_subtitle = 'Official statistical summaries, sectoral analytics, and CSV data exports for CSWDO Palayan City';
            $banner_icon = 'fa-chart-line';
            // Banner action buttons removed as requested
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <!-- Report Category Selector Tabs (Part 2, Item 10) -->
            <div class="report-category-nav">
                <a href="reports.php?category=residents" class="cat-pill <?php echo $category === 'residents' ? 'active' : ''; ?>">
                    <i class="fas fa-users" style="color: #475569;"></i>
                    <span>Resident Summary</span>
                </a>
                <a href="reports.php?category=aics" class="cat-pill <?php echo $category === 'aics' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart" style="color: var(--ref-maroon);"></i>
                    <span>AICS Assistance</span>
                </a>
                <a href="reports.php?category=housing" class="cat-pill <?php echo $category === 'housing' ? 'active' : ''; ?>">
                    <i class="fas fa-home" style="color: #2563EB;"></i>
                    <span>Housing Beneficiaries</span>
                </a>
                <?php if (!empty($is_pwd_senior_enabled)): ?>
                <a href="reports.php?category=senior" class="cat-pill <?php echo $category === 'senior' ? 'active' : ''; ?>">
                    <i class="fas fa-person-cane" style="color: #D97706;"></i>
                    <span>Senior Citizens (OSCA)</span>
                </a>
                <a href="reports.php?category=pwd" class="cat-pill <?php echo $category === 'pwd' ? 'active' : ''; ?>">
                    <i class="fas fa-wheelchair" style="color: #4F46E5;"></i>
                    <span>PWD Reports</span>
                </a>
                <?php endif; ?>
                <a href="reports.php?category=appointments" class="cat-pill <?php echo $category === 'appointments' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check" style="color: #059669;"></i>
                    <span>Appointment Reports</span>
                </a>
                <a href="reports.php?category=request_status" class="cat-pill <?php echo $category === 'request_status' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks" style="color: #7C3AED;"></i>
                    <span>Request Status Reports</span>
                </a>
            </div>

            <!-- Filter Controls and CSV Export Bar -->
            <div class="reports-control-bar">
                <form method="GET" class="reports-filter-form">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    
                    <?php if ($category !== 'residents' && $category !== 'request_status'): ?>
                        <div class="input-group">
                            <span>From:</span>
                            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="input-group">
                            <span>To:</span>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <button type="submit" class="btn-filter-go">
                            <i class="fas fa-filter"></i> Filter Date
                        </button>
                    <?php else: ?>
                        <div class="reports-info-badge">
                            <i class="fas fa-chart-pie"></i>
                            <span>Master aggregate statistics &amp; official sectoral breakdown</span>
                        </div>
                    <?php endif; ?>
                </form>

                <!-- CSV Export Button for Active Category -->
                <a href="reports.php?category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&export=csv" class="btn-export-csv">
                    <i class="fas fa-file-csv"></i> Export <?php echo ucfirst(str_replace('_', ' ', $category)); ?> CSV
                </a>
            </div>

            <!-- Category Specific Renderings -->
            <?php if ($category === 'residents'): ?>
                <!-- 1. RESIDENT SUMMARY -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-users"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>Total Residents</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-user-check"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['active'] ?? 0); ?></h3>
                            <p>Active Citizens</p>
                        </div>
                    </div>
                    <?php if (!empty($is_pwd_senior_enabled)): ?>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-person-cane"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo (int)($stats['senior'] ?? 0); ?></h3>
                            <p>Senior Citizens</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0E7FF; color:#4F46E5;"><i class="fas fa-wheelchair"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#4F46E5;"><?php echo (int)($stats['pwd'] ?? 0); ?></h3>
                            <p>PWD Sector</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F3E8FF; color:#7E22CE;"><i class="fas fa-map-marked-alt"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#7E22CE;"><?php echo (int)($stats['barangays_count'] ?? 0); ?></h3>
                            <p>Barangays Covered</p>
                        </div>
                    </div>
                </div>

                <!-- Resident Analytics Charts (Barangay distribution & Sectoral breakdown) -->
                <div class="report-charts-grid">
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-chart-bar" style="color:var(--ref-maroon);"></i> Top Barangays by Registered Population</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="brgyResidentChart"></canvas>
                        </div>
                    </div>
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-chart-pie" style="color:#059669;"></i> Sectoral Demographics Ratio</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="sectorResidentChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-map-marker-alt" style="color:var(--ref-maroon);"></i> Barangay Demographics Breakdown</h2>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:700;"><?php echo count($data['barangays'] ?? []); ?> Barangays Recorded</span>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Barangay Name</th>
                                    <th>Total Residents</th>
                                    <th>Active Accounts</th>
                                    <th>Senior Citizens</th>
                                    <th>PWDs</th>
                                    <th>Male / Female</th>
                                    <th>Coverage %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grand_total = (int)($stats['total'] ?? 1) ?: 1;
                                foreach (($data['barangays'] ?? []) as $b): 
                                    $pct = round(($b['total'] / $grand_total) * 100, 1);
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($b['barangay']); ?></strong></td>
                                    <td><?php echo number_format($b['total']); ?></td>
                                    <td><span style="color:#16A34A; font-weight:700;"><?php echo number_format($b['active']); ?></span></td>
                                    <td><?php echo number_format($b['senior']); ?></td>
                                    <td><?php echo number_format($b['pwd']); ?></td>
                                    <td><?php echo number_format($b['males']); ?> M / <?php echo number_format($b['females']); ?> F</td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="flex:1; height:6px; background:#E2E8F0; border-radius:3px; max-width:60px;">
                                                <div style="width:<?php echo min(100, $pct * 2); ?>%; height:100%; background:var(--ref-maroon); border-radius:3px;"></div>
                                            </div>
                                            <span style="font-size:0.72rem; font-weight:700;"><?php echo $pct; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'aics'): ?>
                <!-- 2. AICS ASSISTANCE REPORTS -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-receipt"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>Total Filed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-check-circle"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['approved'] ?? 0); ?></h3>
                            <p>Approved Cases</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-clock"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo (int)($stats['reviewing'] ?? 0); ?></h3>
                            <p>Under Assessment</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#ECFDF5; color:#059669;"><i class="fas fa-peso-sign"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#059669;">₱<?php echo number_format($stats['sum_approved'] ?? 0); ?></h3>
                            <p>Total Disbursed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEE2E2; color:#DC2626;"><i class="fas fa-times-circle"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#DC2626;"><?php echo (int)($stats['rejected'] ?? 0); ?></h3>
                            <p>Disapproved</p>
                        </div>
                    </div>
                </div>

                <!-- AICS Analytics Charts (Monthly Volume Trend & Assistance Category Distribution) -->
                <div class="report-charts-grid">
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-chart-line" style="color:var(--ref-maroon);"></i> Monthly AICS Intake &amp; Case Volume Trend</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="aicsMonthlyChart"></canvas>
                        </div>
                    </div>
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-chart-pie" style="color:#D97706;"></i> AICS Category Distribution</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="aicsCategoryDoughnut"></canvas>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-table" style="color:var(--ref-maroon);"></i> AICS Daily Operations &amp; Disbursements</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Requests</th>
                                    <th>Approved</th>
                                    <th>Under Review</th>
                                    <th>Pending Initial</th>
                                    <th>Disapproved</th>
                                    <th>Disbursed Relief</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['breakdown'])): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--ref-muted);">No AICS requests recorded in this date range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['breakdown'] as $row): ?>
                                    <tr>
                                        <td><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
                                        <td><?php echo (int)$row['total']; ?></td>
                                        <td><span style="color:#16A34A; font-weight:700;"><?php echo (int)$row['approved']; ?></span></td>
                                        <td><span style="color:#D97706;"><?php echo (int)$row['reviewing']; ?></span></td>
                                        <td><?php echo (int)$row['submitted']; ?></td>
                                        <td><span style="color:#DC2626;"><?php echo (int)$row['rejected']; ?></span></td>
                                        <td><strong>₱<?php echo number_format($row['approved_amount'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'housing'): ?>
                <!-- 3. HOUSING BENEFICIARY REPORTS -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-users-cog"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>Total Applicants</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-check-circle"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['approved'] ?? 0); ?></h3>
                            <p>Approved Beneficiaries</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0F2FE; color:#0284C7;"><i class="fas fa-user-check"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#0284C7;"><?php echo (int)($stats['qualified'] ?? 0); ?></h3>
                            <p>Qualified for Unit</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#ECFDF5; color:#059669;"><i class="fas fa-key"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#059669;"><?php echo (int)($stats['released'] ?? 0); ?></h3>
                            <p>Units Awarded</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-clock"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo (int)($stats['pending'] ?? 0); ?></h3>
                            <p>Pending Intake</p>
                        </div>
                    </div>
                </div>

                <!-- Housing Analytics Charts (Application Status Breakdown & Allocation) -->
                <div class="report-charts-grid">
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-chart-pie" style="color:#2563EB;"></i> Housing Application Status Breakdown</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="housingStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="report-chart-box">
                        <div class="report-chart-header">
                            <h4><i class="fas fa-house-chimney-user" style="color:#059669;"></i> Program Allocation Overview</h4>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="housingProgramBar"></canvas>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-city" style="color:var(--ref-maroon);"></i> Housing Program Allocation Breakdown</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Program Name</th>
                                    <th>Project Location</th>
                                    <th>Total Applicants</th>
                                    <th>Qualified</th>
                                    <th>Approved</th>
                                    <th>Units Released</th>
                                    <th>Pending Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['programs'])): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--ref-muted);">No housing program data available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['programs'] as $p): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['program_name']); ?></strong></td>
                                        <td><i class="fas fa-map-marker-alt" style="color:var(--ref-maroon);"></i> <?php echo htmlspecialchars($p['location']); ?></td>
                                        <td><?php echo (int)$p['total_applicants']; ?></td>
                                        <td><span style="color:#0284C7; font-weight:700;"><?php echo (int)$p['qualified']; ?></span></td>
                                        <td><span style="color:#16A34A; font-weight:700;"><?php echo (int)$p['approved']; ?></span></td>
                                        <td><span style="color:#059669; font-weight:700;"><?php echo (int)$p['released']; ?></span></td>
                                        <td><?php echo (int)$p['pending']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'senior'): ?>
                <!-- 4. SENIOR CITIZEN REPORTS -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-file-invoice"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>Applications Filed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-check-circle"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['approved'] ?? 0); ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-id-card"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo (int)($stats['osca_ids'] ?? 0); ?></h3>
                            <p>OSCA IDs Issued</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0E7FF; color:#4F46E5;"><i class="fas fa-book"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#4F46E5;"><?php echo (int)($stats['booklets'] ?? 0); ?></h3>
                            <p>Booklets Released</p>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-person-cane" style="color:var(--ref-maroon);"></i> Senior Citizen Application Roster</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ref #</th>
                                    <th>Senior Citizen Name</th>
                                    <th>Birth Date</th>
                                    <th>Barangay</th>
                                    <th>OSCA ID #</th>
                                    <th>Booklet #</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['applications'])): ?>
                                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--ref-muted);">No OSCA applications recorded for this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['applications'] as $snr): ?>
                                    <tr>
                                        <td><code>OSCA-<?php echo str_pad($snr['application_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($snr['first_name'] . ' ' . $snr['last_name']); ?></strong></td>
                                        <td><?php echo !empty($snr['birth_date']) ? date('M d, Y', strtotime($snr['birth_date'])) : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($snr['barangay'] ?: 'Palayan City'); ?></td>
                                        <td><?php echo htmlspecialchars($snr['osca_id_number'] ?: 'Processing'); ?></td>
                                        <td><?php echo htmlspecialchars($snr['booklet_number'] ?: 'Processing'); ?></td>
                                        <td><span class="badge-rate <?php echo $snr['status'] === 'approved' || $snr['status'] === 'released' ? 'high' : 'med'; ?>"><?php echo ucfirst($snr['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($snr['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'pwd'): ?>
                <!-- 5. PWD REPORTS -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-wheelchair"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>PWD Applications</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-check-circle"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['approved'] ?? 0); ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0E7FF; color:#4F46E5;"><i class="fas fa-id-badge"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#4F46E5;"><?php echo (int)($stats['pwd_ids'] ?? 0); ?></h3>
                            <p>PWD IDs Issued</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-book-medical"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo (int)($stats['booklets'] ?? 0); ?></h3>
                            <p>Booklets Issued</p>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-wheelchair" style="color:var(--ref-maroon);"></i> PWD Sector Registry &amp; Benefits</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ref #</th>
                                    <th>Citizen Name</th>
                                    <th>Disability Category</th>
                                    <th>PWD ID #</th>
                                    <th>Booklet #</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['applications'])): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--ref-muted);">No PWD applications found for this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['applications'] as $pwd): ?>
                                    <tr>
                                        <td><code>PWD-<?php echo str_pad($pwd['application_id'], 5, '0', STR_PAD_LEFT); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($pwd['first_name'] . ' ' . $pwd['last_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($pwd['disability_type'] ?: 'Orthopedic / General'); ?></td>
                                        <td><?php echo htmlspecialchars($pwd['pwd_id_number'] ?: 'Processing'); ?></td>
                                        <td><?php echo htmlspecialchars($pwd['booklet_number'] ?: 'Processing'); ?></td>
                                        <td><span class="badge-rate <?php echo $pwd['status'] === 'approved' || $pwd['status'] === 'released' ? 'high' : 'med'; ?>"><?php echo ucfirst($pwd['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($pwd['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'appointments'): ?>
                <!-- 6. APPOINTMENT REPORTS -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-calendar-alt"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                            <p>Booked Visits</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-check-double"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo (int)($stats['completed'] ?? 0); ?></h3>
                            <p>Served &amp; Completed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0F2FE; color:#0284C7;"><i class="fas fa-calendar-check"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#0284C7;"><?php echo (int)($stats['confirmed'] ?? 0); ?></h3>
                            <p>Confirmed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F3E8FF; color:#7C3AED;"><i class="fas fa-user-clock"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#7C3AED;"><?php echo (int)($stats['checked_in'] ?? 0); ?></h3>
                            <p>In-Office Active</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEE2E2; color:#DC2626;"><i class="fas fa-calendar-times"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#DC2626;"><?php echo (int)($stats['cancelled'] ?? 0); ?></h3>
                            <p>Cancelled / No Show</p>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-day" style="color:var(--ref-maroon);"></i> Daily Citizen Intake Summary</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Scheduled Date</th>
                                    <th>Total Visits</th>
                                    <th>Served &amp; Completed</th>
                                    <th>Confirmed Queue</th>
                                    <th>In-Office Active</th>
                                    <th>Pending Review</th>
                                    <th>Cancelled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data['daily'])): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--ref-muted);">No appointments recorded in this date range.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data['daily'] as $d): ?>
                                    <tr>
                                        <td><strong><?php echo date('M d, Y', strtotime($d['appointment_date'])); ?></strong></td>
                                        <td><?php echo (int)$d['total']; ?></td>
                                        <td><span style="color:#16A34A; font-weight:700;"><?php echo (int)$d['completed']; ?></span></td>
                                        <td><span style="color:#0284C7; font-weight:700;"><?php echo (int)$d['confirmed']; ?></span></td>
                                        <td><span style="color:#7C3AED; font-weight:700;"><?php echo (int)$d['active_visit']; ?></span></td>
                                        <td><?php echo (int)$d['pending']; ?></td>
                                        <td><span style="color:#DC2626;"><?php echo (int)$d['cancelled']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($category === 'request_status'): ?>
                <!-- 7. REQUEST STATUS REPORTS (CONSOLIDATED OVERVIEW) -->
                <div class="reports-stats-grid">
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#F1F5F9; color:#475569;"><i class="fas fa-layer-group"></i></div>
                        <div class="report-stat-info">
                            <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                            <p>Total Cross-System Files</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#DCFCE7; color:#16A34A;"><i class="fas fa-badge-check"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#16A34A;"><?php echo number_format($stats['completed'] ?? 0); ?></h3>
                            <p>Resolved / Completed</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#FEF3C7; color:#D97706;"><i class="fas fa-hourglass-half"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#D97706;"><?php echo number_format($stats['pending'] ?? 0); ?></h3>
                            <p>Pending Intake</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#E0F2FE; color:#0284C7;"><i class="fas fa-user-edit"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#0284C7;"><?php echo number_format($stats['reviewing'] ?? 0); ?></h3>
                            <p>Under Assessment</p>
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-icon" style="background:#ECFDF5; color:#059669;"><i class="fas fa-chart-pie"></i></div>
                        <div class="report-stat-info">
                            <h3 style="color:#059669;"><?php echo $stats['fulfillment_rate'] ?? 0; ?>%</h3>
                            <p>Overall Fulfillment</p>
                        </div>
                    </div>
                </div>

                <div class="report-data-card">
                    <div class="card-header">
                        <h2><i class="fas fa-tasks" style="color:var(--ref-maroon);"></i> Program Status Comparison &amp; Resolution Matrix</h2>
                    </div>
                    <div class="report-table-scroll">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Program / Service Area</th>
                                    <th>Total Filed</th>
                                    <th>Pending Intake</th>
                                    <th>In Assessment</th>
                                    <th>Approved</th>
                                    <th>Released / Completed</th>
                                    <th>Disapproved / Cancelled</th>
                                    <th>Fulfillment Rate</th>
                                    <th>Quick Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['matrix'] as $m): 
                                    $tot = (int)($m['data']['total'] ?? 0);
                                    $done = (int)($m['data']['rel'] ?? 0) + (int)($m['data']['a'] ?? 0);
                                    $pct = $tot > 0 ? round(($done / $tot) * 100, 1) : 0;
                                    $rateClass = $pct >= 70 ? 'high' : ($pct >= 40 ? 'med' : 'low');
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:9px; font-weight:700;">
                                            <div style="width:30px; height:30px; border-radius:8px; background:rgba(0,0,0,0.04); display:flex; align-items:center; justify-content:center; color:<?php echo $m['color']; ?>;">
                                                <i class="fas <?php echo $m['icon']; ?>"></i>
                                            </div>
                                            <span><?php echo htmlspecialchars($m['module']); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo number_format($tot); ?></strong></td>
                                    <td><span style="color:#D97706; font-weight:700;"><?php echo (int)($m['data']['p'] ?? 0); ?></span></td>
                                    <td><span style="color:#0284C7; font-weight:700;"><?php echo (int)($m['data']['r'] ?? 0); ?></span></td>
                                    <td><span style="color:#16A34A; font-weight:700;"><?php echo (int)($m['data']['a'] ?? 0); ?></span></td>
                                    <td><span style="color:#059669; font-weight:700;"><?php echo (int)($m['data']['rel'] ?? 0); ?></span></td>
                                    <td><span style="color:#DC2626;"><?php echo (int)($m['data']['rej'] ?? 0); ?></span></td>
                                    <td><span class="badge-rate <?php echo $rateClass; ?>"><?php echo $pct; ?>%</span></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($m['link']); ?>" style="display:inline-flex; align-items:center; gap:5px; font-size:0.75rem; font-weight:700; color:var(--ref-maroon); text-decoration:none;">
                                            <span>Open Module</span> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    <?php if ($category === 'residents'): ?>
    // 1. Resident Barangay Bar Chart
    const ctxBrgy = document.getElementById('brgyResidentChart');
    if (ctxBrgy) {
        new Chart(ctxBrgy, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($brgy_chart_labels); ?>,
                datasets: [{
                    label: 'Registered Citizens',
                    data: <?php echo json_encode($brgy_chart_counts); ?>,
                    backgroundColor: 'rgba(127, 3, 3, 0.85)',
                    borderColor: '#7F0303',
                    borderWidth: 1.5,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return ' ' + context.parsed.y.toLocaleString() + ' residents'; }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. Resident Sectoral Demographics Doughnut
    const ctxSector = document.getElementById('sectorResidentChart');
    if (ctxSector) {
        new Chart(ctxSector, {
            type: 'doughnut',
            data: {
                labels: ['Senior Citizens', 'PWD Sector', 'General Residents'],
                datasets: [{
                    data: [
                        <?php echo (int)($stats['senior'] ?? 0); ?>,
                        <?php echo (int)($stats['pwd'] ?? 0); ?>,
                        <?php echo max(0, (int)($stats['total'] ?? 0) - (int)($stats['senior'] ?? 0) - (int)($stats['pwd'] ?? 0)); ?>
                    ],
                    backgroundColor: ['#D97706', '#4F46E5', '#10B981'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: '600' } } }
                }
            }
        });
    }

    <?php elseif ($category === 'aics'): ?>
    // 1. AICS Monthly Volume Trend (Line Chart)
    const ctxAicsM = document.getElementById('aicsMonthlyChart');
    if (ctxAicsM) {
        new Chart(ctxAicsM, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($aics_monthly_labels); ?>,
                datasets: [{
                    label: 'Applications Filed',
                    data: <?php echo json_encode($aics_monthly_counts); ?>,
                    borderColor: '#7F0303',
                    backgroundColor: 'rgba(127, 3, 3, 0.08)',
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: '#7F0303',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return ' ' + context.parsed.y + ' applications'; }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. AICS Category Doughnut Chart
    const ctxAicsC = document.getElementById('aicsCategoryDoughnut');
    if (ctxAicsC) {
        new Chart(ctxAicsC, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($aics_cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($aics_cat_counts); ?>,
                    backgroundColor: ['#7F0303', '#10B981', '#2563EB', '#D97706', '#7E22CE'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: '600' } } }
                }
            }
        });
    }

    <?php elseif ($category === 'housing'): ?>
    // 1. Housing Application Status Breakdown Doughnut
    const ctxHsg = document.getElementById('housingStatusChart');
    if (ctxHsg) {
        new Chart(ctxHsg, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($hsg_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($hsg_counts); ?>,
                    backgroundColor: ['#F59E0B', '#0284C7', '#10B981', '#059669', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: '600' } } }
                }
            }
        });
    }

    // 2. Housing Programs Bar Chart
    const ctxHsgP = document.getElementById('housingProgramBar');
    if (ctxHsgP) {
        new Chart(ctxHsgP, {
            type: 'bar',
            data: {
                labels: [<?php foreach (($data['programs'] ?? []) as $p) echo '"' . addslashes($p['program_name']) . '",'; ?>],
                datasets: [{
                    label: 'Total Applicants',
                    data: [<?php foreach (($data['programs'] ?? []) as $p) echo (int)$p['total_applicants'] . ','; ?>],
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderColor: '#2563EB',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

<?php
// staff/reports.php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for sidebar display
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check access flags
$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);

// Get date range from request
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'all';

// Build reports data - ONLY for services staff has access to
$reports = [];

// 1. AICS Summary (only if staff has access)
if (($report_type == 'all' || $report_type == 'aics') && $has_aics_access) {
    $aics_query = "SELECT 
        DATE_FORMAT(submission_date, '%Y-%m-%d') as date,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
        SUM(amount_requested) as total_requested,
        SUM(amount_approved) as total_approved
        FROM aics_requests
        WHERE submission_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(submission_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $aics_stmt = $db->prepare($aics_query);
    $aics_stmt->bindParam(':from', $date_from);
    $aics_stmt->bindParam(':to', $date_to);
    $aics_stmt->execute();
    $reports['aics'] = $aics_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // AICS totals
    $aics_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as total_rejected,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as total_submitted,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as total_reviewing,
        SUM(amount_requested) as sum_requested,
        SUM(amount_approved) as sum_approved
        FROM aics_requests
        WHERE submission_date BETWEEN :from AND :to";
    $aics_total_stmt = $db->prepare($aics_total_query);
    $aics_total_stmt->bindParam(':from', $date_from);
    $aics_total_stmt->bindParam(':to', $date_to);
    $aics_total_stmt->execute();
    $reports['aics_totals'] = $aics_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Appointments Summary (always show for all staff)
if ($report_type == 'all' || $report_type == 'appointments') {
    $app_query = "SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(appointment_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $app_stmt = $db->prepare($app_query);
    $app_stmt->bindParam(':from', $date_from);
    $app_stmt->bindParam(':to', $date_to);
    $app_stmt->execute();
    $reports['appointments'] = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Appointments totals
    $app_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to";
    $app_total_stmt = $db->prepare($app_total_query);
    $app_total_stmt->bindParam(':from', $date_from);
    $app_total_stmt->bindParam(':to', $date_to);
    $app_total_stmt->execute();
    $reports['app_totals'] = $app_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Housing Summary (only if staff has access)
if (($report_type == 'all' || $report_type == 'housing') && $has_housing_access) {
    $housing_query = "SELECT 
        DATE_FORMAT(application_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_beneficiaries
        WHERE application_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(application_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $housing_stmt = $db->prepare($housing_query);
    $housing_stmt->bindParam(':from', $date_from);
    $housing_stmt->bindParam(':to', $date_to);
    $housing_stmt->execute();
    $reports['housing'] = $housing_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Housing totals
    $housing_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_beneficiaries
        WHERE application_date BETWEEN :from AND :to";
    $housing_total_stmt = $db->prepare($housing_total_query);
    $housing_total_stmt->bindParam(':from', $date_from);
    $housing_total_stmt->bindParam(':to', $date_to);
    $housing_total_stmt->execute();
    $reports['housing_totals'] = $housing_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. Documents Summary (always show for all staff)
if ($report_type == 'all' || $report_type == 'documents') {
    $doc_query = "SELECT 
        DATE_FORMAT(upload_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents
        WHERE upload_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(upload_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $doc_stmt = $db->prepare($doc_query);
    $doc_stmt->bindParam(':from', $date_from);
    $doc_stmt->bindParam(':to', $date_to);
    $doc_stmt->execute();
    $reports['documents'] = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Documents totals
    $doc_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents
        WHERE upload_date BETWEEN :from AND :to";
    $doc_total_stmt = $db->prepare($doc_total_query);
    $doc_total_stmt->bindParam(':from', $date_from);
    $doc_total_stmt->bindParam(':to', $date_to);
    $doc_total_stmt->execute();
    $reports['doc_totals'] = $doc_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get staff info for display
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Handle export (simplified - would need PHPSpreadsheet for full export)
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (($report_type == 'all' || $report_type == 'aics') && $has_aics_access) {
        fputcsv($output, ['AICS Report', 'Date Range: ' . $date_from . ' to ' . $date_to]);
        fputcsv($output, ['Date', 'Total', 'Approved', 'Rejected', 'Submitted', 'Reviewing', 'Amount Requested', 'Amount Approved']);
        foreach ($reports['aics'] as $row) {
            fputcsv($output, [
                $row['date'],
                $row['total_requests'],
                $row['approved'],
                $row['rejected'],
                $row['submitted'],
                $row['reviewing'],
                $row['total_requested'],
                $row['total_approved']
            ]);
        }
        fputcsv($output, []);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            --bg-primary: #E8ECF2;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #C8D0DC;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --card-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-approved: #4CAF50;
            --color-rejected: #EF5350;
            --color-pending: #FF9800;
            --color-completed: #2196F3;
            --color-cancelled: #757575;
            --color-verified: #4CAF50;
            --color-released: #00BCD4;
            --color-qualified: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --card-bg: #1a2332;
            --filter-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-approved: #66BB6A;
            --color-rejected: #EF5350;
            --color-pending: #FFA726;
            --color-completed: #42A5F5;
            --color-cancelled: #78909C;
            --color-verified: #66BB6A;
            --color-released: #4DD0E1;
            --color-qualified: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .app-container { display: flex; min-height: 100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .staff-profile {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--divider);
            text-align: center;
        }
        .staff-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            background: var(--maroon-light);
            border: 3px solid rgba(255,255,255,0.2);
            overflow: hidden;
            position: relative;
        }
        .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-profile h4 {
            color: var(--text-sidebar-active);
            font-size: 1rem;
            font-weight: 600;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
            margin-top: 2px;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            margin-top: 6px;
            color: rgba(255,255,255,0.8);
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 14px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 1rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 10px 0;
            padding-top: 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            padding-left: 14px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--divider);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: var(--card-bg);
            border-radius: 50px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            gap: 10px;
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .search-box input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: transparent;
            min-width: 180px;
            color: var(--text-primary);
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { color: var(--text-muted); }

        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--card-bg);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover { 
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 1.5fr auto;
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--border-color);
        }

        /* ========== REPORT SECTIONS ========== */
        .report-section {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 22px 24px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .report-section .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .report-section .section-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .report-section .section-header h2 i {
            color: var(--maroon-light);
        }
        .report-section .section-header .badge {
            background: var(--badge-bg);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--badge-bg);
            padding: 14px 18px;
            border-radius: var(--radius-element);
            text-align: center;
            border-left: 4px solid var(--maroon);
            transition: all 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .summary-card.approved { border-left-color: var(--color-approved); }
        .summary-card.rejected { border-left-color: var(--color-rejected); }
        .summary-card.pending { border-left-color: var(--color-pending); }
        .summary-card.completed { border-left-color: var(--color-completed); }
        .summary-card.cancelled { border-left-color: var(--color-cancelled); }
        .summary-card.verified { border-left-color: var(--color-verified); }
        .summary-card.released { border-left-color: var(--color-released); }
        .summary-card.qualified { border-left-color: var(--color-qualified); }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: var(--badge-bg);
            padding: 10px 14px;
            text-align: left;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .data-table tr:hover {
            background: var(--badge-bg);
        }

        .completion-rate {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .rate-high { background: rgba(76, 175, 80, 0.15); color: var(--color-approved); }
        .rate-medium { background: rgba(255, 152, 0, 0.15); color: var(--color-pending); }
        .rate-low { background: rgba(244, 67, 54, 0.15); color: var(--color-rejected); }

        .no-data {
            text-align: center;
            padding: 40px 20px;
        }
        .no-data i {
            font-size: 32px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 12px;
        }
        .no-data p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .no-access-message {
            background: var(--badge-bg);
            border-left: 4px solid var(--color-pending);
            padding: 24px;
            border-radius: var(--radius-element);
            margin-bottom: 28px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .no-access-message i {
            font-size: 2rem;
            color: var(--color-pending);
            margin-bottom: 10px;
            display: block;
        }
        .no-access-message h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .no-access-message p {
            color: var(--text-muted);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-wrap: wrap; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .report-section { padding: 16px; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.7rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .summary-cards { grid-template-columns: 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table th, .data-table td { padding: 6px 8px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Staff Panel</span>
            </div>
        </div>
        
        <div class="staff-profile">
            <div class="staff-avatar">
                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
            <p><?php echo htmlspecialchars($staff_info['position'] ?? 'Staff Member'); ?></p>
            <span class="staff-badge"><i class="fas fa-user-check"></i> Staff</span>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">Appointments</li>
            <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
            <li class="menu-divider">Services</li>
            <?php if($has_aics_access): ?>
            <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
            <?php endif; ?>
            <?php if($has_housing_access): ?>
            <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
            <?php endif; ?>
            <?php if($has_senior_access): ?>
            <li><a href="manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
            <?php endif; ?>
            <?php if($has_pwd_access): ?>
            <li><a href="manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
            <?php endif; ?>
            <li class="menu-divider"></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="menu-divider"></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-chart-bar" style="color: var(--maroon-light); margin-right: 10px;"></i> Reports & Analytics</h1>
                <p>Generate and view reports for all services</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search reports..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="../logout.php" class="top-icon"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-chart-line"></i> Report Type</label>
                        <select name="report_type">
                            <option value="all" <?php echo $report_type == 'all' ? 'selected' : ''; ?>>All Reports</option>
                            <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments Only</option>
                            <option value="documents" <?php echo $report_type == 'documents' ? 'selected' : ''; ?>>Documents Only</option>
                            <?php if($has_aics_access): ?>
                            <option value="aics" <?php echo $report_type == 'aics' ? 'selected' : ''; ?>>AICS Only</option>
                            <?php endif; ?>
                            <?php if($has_housing_access): ?>
                            <option value="housing" <?php echo $report_type == 'housing' ? 'selected' : ''; ?>>Housing Only</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Generate
                        </button>
                        <?php if(($report_type == 'all' || $report_type == 'aics') && $has_aics_access): ?>
                        <a href="reports.php?export=csv&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&report_type=<?php echo $report_type; ?>" 
                           class="btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Show message if no services assigned -->
        <?php if(!$has_aics_access && !$has_housing_access && !$has_senior_access && !$has_pwd_access): ?>
        <div class="no-access-message">
            <i class="fas fa-lock"></i>
            <h3>No Services Assigned</h3>
            <p>You don't have any services assigned. Please contact the administrator to assign services to your account.</p>
        </div>
        <?php endif; ?>
        
        <!-- Appointments Summary (Always visible) -->
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> Appointments Summary</h2>
                <span class="badge"><?php echo $reports['app_totals']['total'] ?? 0; ?> total</span>
            </div>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $reports['app_totals']['total'] ?? 0; ?></div>
                    <div class="summary-label">Total Appointments</div>
                </div>
                <div class="summary-card completed">
                    <div class="summary-value"><?php echo $reports['app_totals']['completed'] ?? 0; ?></div>
                    <div class="summary-label">Completed</div>
                </div>
                <div class="summary-card approved">
                    <div class="summary-value"><?php echo $reports['app_totals']['confirmed'] ?? 0; ?></div>
                    <div class="summary-label">Confirmed</div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-value"><?php echo $reports['app_totals']['pending'] ?? 0; ?></div>
                    <div class="summary-label">Pending</div>
                </div>
                <div class="summary-card cancelled">
                    <div class="summary-value"><?php echo $reports['app_totals']['cancelled'] ?? 0; ?></div>
                    <div class="summary-label">Cancelled</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Completed</th>
                            <th>Confirmed</th>
                            <th>Pending</th>
                            <th>Cancelled</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($reports['appointments'])): ?>
                            <?php foreach($reports['appointments'] as $row): ?>
                            <?php 
                            $rate = ($row['total'] > 0) ? round(($row['completed'] / $row['total']) * 100, 2) : 0;
                            $rateClass = $rate >= 70 ? 'rate-high' : ($rate >= 40 ? 'rate-medium' : 'rate-low');
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['completed']; ?></td>
                                <td><?php echo $row['confirmed']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['cancelled']; ?></td>
                                <td><span class="completion-rate <?php echo $rateClass; ?>"><?php echo $rate; ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="fas fa-chart-line"></i>
                                        <p>No data available for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Documents Summary (Always visible) -->
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-file-alt"></i> Documents Summary</h2>
                <span class="badge"><?php echo $reports['doc_totals']['total'] ?? 0; ?> total</span>
            </div>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $reports['doc_totals']['total'] ?? 0; ?></div>
                    <div class="summary-label">Total Documents</div>
                </div>
                <div class="summary-card verified">
                    <div class="summary-value"><?php echo $reports['doc_totals']['verified'] ?? 0; ?></div>
                    <div class="summary-label">Verified</div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-value"><?php echo $reports['doc_totals']['pending'] ?? 0; ?></div>
                    <div class="summary-label">Pending</div>
                </div>
                <div class="summary-card rejected">
                    <div class="summary-value"><?php echo $reports['doc_totals']['rejected'] ?? 0; ?></div>
                    <div class="summary-label">Rejected</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Verified</th>
                            <th>Pending</th>
                            <th>Rejected</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($reports['documents'])): ?>
                            <?php foreach($reports['documents'] as $row): ?>
                            <?php 
                            $verify_rate = ($row['total'] > 0) ? round(($row['verified'] / $row['total']) * 100, 2) : 0;
                            $rateClass = $verify_rate >= 70 ? 'rate-high' : ($verify_rate >= 40 ? 'rate-medium' : 'rate-low');
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['verified']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                                <td><span class="completion-rate <?php echo $rateClass; ?>"><?php echo $verify_rate; ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <i class="fas fa-chart-line"></i>
                                        <p>No data available for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- AICS Summary (Only if staff has access) -->
        <?php if($has_aics_access && ($report_type == 'all' || $report_type == 'aics')): ?>
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-hand-holding-heart"></i> AICS Summary</h2>
                <span class="badge"><?php echo $reports['aics_totals']['total'] ?? 0; ?> requests</span>
            </div>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $reports['aics_totals']['total'] ?? 0; ?></div>
                    <div class="summary-label">Total Requests</div>
                </div>
                <div class="summary-card approved">
                    <div class="summary-value"><?php echo $reports['aics_totals']['total_approved'] ?? 0; ?></div>
                    <div class="summary-label">Approved</div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-value"><?php echo ($reports['aics_totals']['total_submitted'] ?? 0) + ($reports['aics_totals']['total_reviewing'] ?? 0); ?></div>
                    <div class="summary-label">Pending</div>
                </div>
                <div class="summary-card rejected">
                    <div class="summary-value"><?php echo $reports['aics_totals']['total_rejected'] ?? 0; ?></div>
                    <div class="summary-label">Rejected</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">₱<?php echo number_format($reports['aics_totals']['sum_approved'] ?? 0, 2); ?></div>
                    <div class="summary-label">Total Amount Approved</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Submitted</th>
                            <th>Reviewing</th>
                            <th>Amount Requested</th>
                            <th>Amount Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($reports['aics'])): ?>
                            <?php foreach($reports['aics'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total_requests']; ?></td>
                                <td><?php echo $row['approved']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                                <td><?php echo $row['submitted']; ?></td>
                                <td><?php echo $row['reviewing']; ?></td>
                                <td>₱<?php echo number_format($row['total_requested'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($row['total_approved'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-data">
                                        <i class="fas fa-chart-line"></i>
                                        <p>No data available for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Housing Summary (Only if staff has access) -->
        <?php if($has_housing_access && ($report_type == 'all' || $report_type == 'housing')): ?>
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-home"></i> Housing Summary</h2>
                <span class="badge"><?php echo $reports['housing_totals']['total'] ?? 0; ?> applications</span>
            </div>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $reports['housing_totals']['total'] ?? 0; ?></div>
                    <div class="summary-label">Total Applications</div>
                </div>
                <div class="summary-card approved">
                    <div class="summary-value"><?php echo $reports['housing_totals']['approved'] ?? 0; ?></div>
                    <div class="summary-label">Approved</div>
                </div>
                <div class="summary-card qualified">
                    <div class="summary-value"><?php echo $reports['housing_totals']['qualified'] ?? 0; ?></div>
                    <div class="summary-label">Qualified</div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-value"><?php echo $reports['housing_totals']['pending'] ?? 0; ?></div>
                    <div class="summary-label">Pending</div>
                </div>
                <div class="summary-card released">
                    <div class="summary-value"><?php echo $reports['housing_totals']['released'] ?? 0; ?></div>
                    <div class="summary-label">Released</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Qualified</th>
                            <th>Pending</th>
                            <th>Rejected</th>
                            <th>Released</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($reports['housing'])): ?>
                            <?php foreach($reports['housing'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['approved']; ?></td>
                                <td><?php echo $row['qualified']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                                <td><?php echo $row['released']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="fas fa-chart-line"></i>
                                        <p>No data available for selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            const sections = document.querySelectorAll('.report-section');
            sections.forEach(section => {
                const text = section.textContent.toLowerCase();
                section.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
            });
        }
    }
});
</script>
</body>
</html>

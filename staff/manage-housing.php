<?php
// staff/manage-housing.php
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

// If staff doesn't have housing access, redirect
if (!$has_housing_access) {
    $_SESSION['error'] = "You don't have permission to access Housing Management.";
    redirect('../staff-dashboard.php');
}

// Get filter parameters
$program_filter = isset($_GET['program']) ? sanitize($_GET['program']) : 'all';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $beneficiary_id = sanitize($_POST['beneficiary_id']);
    $new_status = sanitize($_POST['new_status']);
    $unit_assigned = sanitize($_POST['unit_assigned'] ?? '');
    $interview_date = sanitize($_POST['interview_date'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $update_query = "UPDATE housing_beneficiaries SET 
                      status = :status,
                      unit_assigned = :unit,
                      interview_date = :interview_date";
    
    if ($new_status == 'approved') {
        $update_query .= ", approved_by = :user_id, approval_date = NOW()";
    } elseif ($new_status == 'rejected') {
        $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nRejection Reason: ', :remarks)";
    }
    
    $update_query .= " WHERE beneficiary_id = :id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':unit', $unit_assigned);
    $update_stmt->bindParam(':interview_date', $interview_date);
    
    if ($new_status == 'approved') {
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    } elseif ($new_status == 'rejected') {
        $update_stmt->bindParam(':remarks', $remarks);
    }
    
    $update_stmt->bindParam(':id', $beneficiary_id);
    
    if ($update_stmt->execute()) {
        // Get user_id for notification
        $user_query = "SELECT user_id FROM housing_beneficiaries WHERE beneficiary_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $beneficiary_id);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Housing Application Updated', 
                        'Your housing application status has been updated to: " . ucfirst($new_status) . "', 'housing')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $user['user_id']);
        $notif_stmt->execute();
        
        $success = "Application status updated successfully!";
    }
}

// Get all housing programs for filter
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->query($programs_query);

// Build main query
$query = "SELECT h.*, p.program_name, p.location, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($program_filter != 'all') {
    $query .= " AND h.program_id = :program";
    $params[':program'] = $program_filter;
}

if ($status_filter != 'all') {
    $query .= " AND h.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY h.application_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
    FROM housing_beneficiaries";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Housing - Staff Panel</title>
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
            
            --bg-primary: #F8F9FC;
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
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --stat-bg: #FFFFFF;
            --quick-bg: #0F414A;
            --filter-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-pending: #FF9800;
            --color-qualified: #3949AB;
            --color-approved: #4CAF50;
            --color-rejected: #EF5350;
            --color-released: #8E24AA;
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
            
            --stat-bg: #1a2332;
            --quick-bg: #1a2332;
            --filter-bg: #1a2332;
            --table-bg: #1a2332;
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-pending: #FFA726;
            --color-qualified: #5C6BC0;
            --color-approved: #66BB6A;
            --color-rejected: #EF5350;
            --color-released: #AB47BC;
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

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: #4CAF50;
            border-left-color: #4CAF50;
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
            border-left-color: #EF5350;
        }
        .alert i { font-size: 1.2rem; }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--stat-bg);
            border-radius: var(--radius-card);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--maroon), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: var(--radius-card);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card:hover::after {
            opacity: 0.03;
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.pending { background: rgba(255, 152, 0, 0.12); color: var(--color-pending); }
        .stat-icon.qualified { background: rgba(57, 73, 171, 0.12); color: var(--color-qualified); }
        .stat-icon.approved { background: rgba(76, 175, 80, 0.12); color: var(--color-approved); }
        .stat-icon.released { background: rgba(142, 36, 170, 0.12); color: var(--color-released); }
        .stat-info {
            position: relative;
            z-index: 1;
        }
        .stat-info h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .stat-info p {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== QUICK STATS BAR ========== */
        .quick-stats-bar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .program-info h4 {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .program-info .count {
            font-size: 1.3rem;
            font-weight: 800;
        }
        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-btn {
            background: rgba(255,255,255,0.15);
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
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
        .filter-group select,
        .filter-group input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
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

        /* ========== TABLE ========== */
        .table-container {
            background: var(--table-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead {
            background: var(--badge-bg);
            border-bottom: 2px solid var(--border-color);
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: var(--badge-bg);
        }

        .applicant-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .applicant-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .applicant-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .applicant-avatar .initials {
            font-weight: 700;
            font-size: 0.7rem;
            color: var(--text-primary);
        }
        .applicant-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .applicant-contact {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-pending);
        }
        .status-badge.qualified {
            background: rgba(57, 73, 171, 0.15);
            color: var(--color-qualified);
        }
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-approved);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-rejected);
        }
        .status-badge.released {
            background: rgba(142, 36, 170, 0.15);
            color: var(--color-released);
        }

        .action-buttons-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-icon {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-update {
            background: rgba(255, 152, 0, 0.12);
            color: var(--color-pending);
        }
        .btn-update:hover {
            background: var(--color-pending);
            color: white;
        }
        .btn-view {
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon-light);
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
        }
        .no-data i {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .no-data p {
            color: var(--text-muted);
        }

        /* ========== MODAL ========== */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(6px);
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--card-bg);
            border-radius: 24px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--badge-bg);
            border-radius: 24px 24px 0 0;
        }
        .modal-header h2 {
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i {
            color: var(--maroon-light);
        }
        .modal-header .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .modal-header .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 24px;
        }

        .info-card-mini {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .info-row-mini {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .info-label-mini {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .info-value-mini {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--badge-bg);
            border-radius: 0 0 24px 24px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-wrap: wrap; }
            .data-table { display: block; overflow-x: auto; }
            .quick-stats-bar { flex-direction: column; text-align: center; }
            .quick-actions { justify-content: center; }
            .modal-content { width: 95%; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.7rem; }
            .btn-icon { font-size: 0.6rem; padding: 3px 8px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-home"></i></div>
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
            <li><a href="manage-housing.php" class="active"><i class="fas fa-home"></i> Manage Housing</a></li>
            <?php endif; ?>
            <?php if($has_senior_access): ?>
            <li><a href="manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
            <?php endif; ?>
            <?php if($has_pwd_access): ?>
            <li><a href="manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
            <?php endif; ?>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="menu-divider"></li>
        </ul>
        
        <div class="sidebar-footer">
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-home" style="color: var(--maroon-light); margin-right: 10px;"></i> Manage Housing Applications</h1>
                <p>Review and manage housing applications</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search applications..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="reports.php" class="top-icon"><i class="fas fa-chart-bar"></i></a>
                <a href="../logout.php" class="top-icon"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=all'">
                <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=pending'">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=qualified'">
                <div class="stat-icon qualified"><i class="fas fa-search"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['qualified']; ?></h3>
                    <p>Qualified</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=approved'">
                <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=released'">
                <div class="stat-icon released"><i class="fas fa-gift"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['released']; ?></h3>
                    <p>Released</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Bar -->
        <div class="quick-stats-bar">
            <div class="program-info">
                <h4>Active Housing Programs</h4>
                <div class="count"><?php echo $programs_stmt->rowCount(); ?> Programs</div>
            </div>
            <div class="quick-actions">
                <a href="reports.php?type=housing" class="quick-btn">
                    <i class="fas fa-chart-line"></i> View Reports
                </a>
                <a href="export-housing.php" class="quick-btn">
                    <i class="fas fa-download"></i> Export Data
                </a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Program</label>
                        <select name="program" id="programFilter">
                            <option value="all">All Programs</option>
                            <?php 
                            $programs_stmt = $db->query("SELECT * FROM housing_programs WHERE status = 'active'");
                            while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                            <option value="<?php echo $program['program_id']; ?>" 
                                <?php echo $program_filter == $program['program_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" id="statusFilter">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="qualified" <?php echo $status_filter == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Name or contact..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <?php if($program_filter != 'all' || $status_filter != 'all' || !empty($search)): ?>
                        <a href="manage-housing.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Household</th>
                        <th>Income</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $app_initials = strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1));
                            $app_pic = !empty($app['profile_picture']) && file_exists(__DIR__ . '/../' . $app['profile_picture']) 
                                ? '../' . $app['profile_picture'] 
                                : '';
                        ?>
                        <tr>
                            <td><strong style="color: var(--maroon-light);">#<?php echo $app['beneficiary_id']; ?></strong></td>
                            <td>
                                <div class="applicant-info">
                                    <div class="applicant-avatar">
                                        <?php if(!empty($app_pic)): ?>
                                            <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Profile">
                                        <?php else: ?>
                                            <span class="initials"><?php echo $app_initials; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="applicant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                        <div class="applicant-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($app['contact_number']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($app['program_name']); ?></td>
                            <td><?php echo $app['household_composition']; ?></td>
                            <td>₱<?php echo number_format($app['monthly_income'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $app['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $app['status'] == 'pending' ? 'clock' : 
                                            ($app['status'] == 'qualified' ? 'search' : 
                                            ($app['status'] == 'approved' ? 'check-circle' : 
                                            ($app['status'] == 'released' ? 'gift' : 'times-circle'))); 
                                    ?>"></i>
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-buttons-group" style="justify-content: center;">
                                    <button onclick="showUpdateModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                                            class="btn-icon btn-update">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                    <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="btn-icon btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No housing applications found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Housing Application</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="beneficiary_id" id="modal_beneficiary_id">
                
                <div class="info-card-mini">
                    <div class="info-row-mini">
                        <span class="info-label-mini">Applicant:</span>
                        <span class="info-value-mini" id="modal_applicant"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Program:</span>
                        <span class="info-value-mini" id="modal_program"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleHousingFields()">
                        <option value="qualified">📋 Mark as Qualified</option>
                        <option value="approved">✅ Approve & Assign Unit</option>
                        <option value="rejected">❌ Reject</option>
                        <option value="released">💰 Mark as Released</option>
                        <option value="pending">⏰ Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group" id="unit_field" style="display: none;">
                    <label for="unit_assigned">Unit Number / House Assignment</label>
                    <input type="text" name="unit_assigned" id="unit_assigned" 
                           placeholder="e.g., Block 1, Lot 5, Unit 203">
                    <small>The housing unit assigned to the beneficiary</small>
                </div>
                
                <div class="form-group" id="interview_field" style="display: none;">
                    <label for="interview_date">Interview / Site Visit Date</label>
                    <input type="date" name="interview_date" id="interview_date"
                           min="<?php echo date('Y-m-d'); ?>">
                    <small>Schedule for interview or site inspection</small>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="remarks">Rejection Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" 
                              placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="update_status" class="btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
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
            window.location.href = 'manage-housing.php?search=' + encodeURIComponent(query);
        }
    }
});

// ========== MODAL FUNCTIONS ==========
function showUpdateModal(application) {
    document.getElementById('modal_beneficiary_id').value = application.beneficiary_id;
    document.getElementById('modal_applicant').textContent = 
        application.first_name + ' ' + application.last_name;
    document.getElementById('modal_program').textContent = application.program_name;
    document.getElementById('updateModal').classList.add('active');
}

function closeModal() {
    document.getElementById('updateModal').classList.remove('active');
    document.getElementById('unit_field').style.display = 'none';
    document.getElementById('interview_field').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('unit_assigned').value = '';
    document.getElementById('interview_date').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'qualified';
}

function toggleHousingFields() {
    const status = document.getElementById('modal_status').value;
    const unitField = document.getElementById('unit_field');
    const interviewField = document.getElementById('interview_field');
    const remarksField = document.getElementById('remarks_field');
    
    unitField.style.display = 'none';
    interviewField.style.display = 'none';
    remarksField.style.display = 'none';
    
    if (status === 'approved') {
        unitField.style.display = 'block';
        interviewField.style.display = 'block';
        document.getElementById('unit_assigned').required = true;
        document.getElementById('interview_date').required = true;
    } else if (status === 'qualified') {
        interviewField.style.display = 'block';
        document.getElementById('interview_date').required = true;
    } else if (status === 'rejected') {
        remarksField.style.display = 'block';
        document.getElementById('remarks').required = true;
    }
}

function filterByStatus(status) {
    document.getElementById('statusFilter').value = status;
    document.getElementById('filterForm').submit();
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('updateModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
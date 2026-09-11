<?php
// staff-dashboard.php
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user is staff or admin
if (!isStaff() && !isAdmin()) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services - Get staff_id from staff table using user_id
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories for this staff
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_id, sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if staff has specific service access
$has_aics_access = false;
$has_housing_access = false;
$has_senior_access = false;
$has_pwd_access = false;

foreach ($assigned_categories as $category) {
    $cat_name = $category['category_name'];
    if ($cat_name == 'AICS')
        $has_aics_access = true;
    if ($cat_name == 'Housing')
        $has_housing_access = true;
    if ($cat_name == 'Senior Citizen')
        $has_senior_access = true;
    if ($cat_name == 'PWD')
        $has_pwd_access = true;
}

// Get staff details - FIXED: JOIN with users to get profile_picture
$staff_query = "SELECT s.*, u.email, u.contact_number, u.last_login, u.profile_picture 
                FROM staff s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$stats = [];

// Total AICS requests
if ($has_aics_access) {
    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status IN ('submitted', 'reviewing')";
    $stats['pending_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'approved'";
    $stats['approved_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'rejected'";
    $stats['rejected_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
}

// Total Housing applications
if ($has_housing_access) {
    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status IN ('pending', 'qualified')";
    $stats['pending_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status = 'approved'";
    $stats['approved_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
}

// Documents to verify
$query = "SELECT COUNT(*) as count FROM documents WHERE verification_status = 'pending'";
$stats['pending_documents'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending', 'confirmed')";
$stats['today_appointments'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Pending verifications
$query = "SELECT COUNT(*) as count FROM verification_requests WHERE status = 'pending'";
$stats['pending_verifications'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Recent AICS requests (if staff has access)
$recent_aics = [];
if ($has_aics_access) {
    $query = "SELECT a.*, u.first_name, u.last_name 
              FROM aics_requests a
              JOIN users u ON a.user_id = u.user_id
              WHERE a.status IN ('submitted', 'reviewing')
              ORDER BY a.submission_date DESC
              LIMIT 5";
    $recent_aics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

// Recent Housing applications (if staff has access)
$recent_housing = [];
if ($has_housing_access) {
    $tables_check = $db->query("SHOW TABLES LIKE 'housing_beneficiaries'");
    if ($tables_check->rowCount() > 0) {
        $query = "SELECT h.*, u.first_name, u.last_name 
                  FROM housing_beneficiaries h
                  JOIN users u ON h.user_id = u.user_id
                  WHERE h.status IN ('pending', 'qualified')
                  ORDER BY h.application_date DESC
                  LIMIT 5";
        $recent_housing = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Recent documents
$recent_docs = [];
$query = "SELECT d.*, u.first_name, u.last_name 
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          WHERE d.verification_status = 'pending'
          ORDER BY d.upload_date DESC
          LIMIT 5";
$recent_docs = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Recent verification requests
$recent_verifications = [];
$query = "SELECT v.*, u.first_name, u.last_name, u.email 
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          WHERE v.status = 'pending'
          ORDER BY v.submitted_date DESC
          LIMIT 5";
$recent_verifications = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get initials for avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path - FIXED: Check both users table and staff_info
$profile_picture = '';
if (isset($staff_info['profile_picture']) && !empty($staff_info['profile_picture'])) {
    $profile_picture = $staff_info['profile_picture'];
}

// Check if the profile picture exists in the filesystem
$avatar_url = '';
if (!empty($profile_picture)) {
    $full_path = __DIR__ . '/../' . $profile_picture;
    if (file_exists($full_path)) {
        $avatar_url = '../' . $profile_picture;
    } else {
        // Try alternative paths
        $alt_path = __DIR__ . '/' . $profile_picture;
        if (file_exists($alt_path)) {
            $avatar_url = $profile_picture;
        }
    }
}

// If no profile picture found, use initials
$use_initials = empty($avatar_url);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - One Palayan-Kalinga Hub</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
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
            --bg-sidebar-hover: rgba(255, 255, 255, 0.12);
            --bg-sidebar-active: rgba(255, 255, 255, 0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255, 255, 255, 0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #C8D0DC;

            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 40px rgba(15, 65, 74, 0.15), 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);

            --stat-bg: #FFFFFF;
            --quick-bg: #EFE8DF;
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255, 255, 255, 0.08);

            --radius-card: 20px;
            --radius-element: 12px;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.06);
            --bg-sidebar-active: rgba(255, 255, 255, 0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255, 255, 255, 0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;

            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);

            --stat-bg: #1a2332;
            --quick-bg: #1a2332;
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255, 255, 255, 0.05);

            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ========== LAYOUT ========== */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

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

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

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
            background: rgba(255, 255, 255, 0.10);
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
            color: rgba(255, 255, 255, 0.4);
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
            border: 3px solid rgba(255, 255, 255, 0.2);
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
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            margin-top: 2px;
        }

        .staff-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 14px;
            flex: 1;
        }

        .sidebar-menu li {
            margin-bottom: 2px;
        }

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

        .sidebar-menu li a i {
            width: 20px;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.4);
        }

        .sidebar-menu li a:hover {
            background: var(--bg-sidebar-hover);
            color: var(--text-sidebar-active);
        }

        .sidebar-menu li a:hover i {
            color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }

        .sidebar-menu li a.active i {
            color: var(--text-sidebar-active);
        }

        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 10px 0;
            padding-top: 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255, 255, 255, 0.3);
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
            background: rgba(255, 255, 255, 0.04);
            transition: all 0.25s;
        }

        .sidebar-footer a:hover {
            background: rgba(255, 255, 255, 0.10);
            color: var(--text-sidebar-active);
        }

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
            background: var(--bg-card);
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

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .search-box i {
            color: var(--text-muted);
        }

        .theme-toggle {
            background: var(--bg-card);
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
            background: var(--bg-card);
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

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--stat-bg);
            border-radius: 20px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
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
            border-radius: 20px;
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
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }

        .stat-icon.aics {
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon-light);
        }

        .stat-icon.housing {
            background: rgba(15, 65, 74, 0.12);
            color: var(--midnight);
        }

        .stat-icon.verification {
            background: rgba(0, 150, 136, 0.12);
            color: #009688;
        }

        .stat-icon.appointment {
            background: rgba(255, 152, 0, 0.12);
            color: #FF9800;
        }

        .stat-icon.document {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }

        .stat-info {
            position: relative;
            z-index: 1;
        }

        .stat-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }

        .stat-info p {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== QUICK ACTIONS ========== */
        .quick-section {
            background: var(--quick-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .quick-section h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-section h3 i {
            color: var(--maroon-light);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: var(--bg-card);
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }

        .action-btn:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon);
        }

        .action-btn:hover i {
            color: white;
        }

        /* ========== TWO COLUMNS ========== */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: var(--maroon-light);
        }

        .card-header a {
            color: var(--maroon-light);
            font-size: 0.75rem;
            text-decoration: none;
            font-weight: 600;
        }

        .card-header a:hover {
            text-decoration: underline;
        }

        .request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .request-info h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .request-info p {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .status-badge {
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .status-badge.submitted,
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: #FF9800;
        }

        .status-badge.reviewing {
            background: rgba(33, 150, 243, 0.15);
            color: #2196F3;
        }

        .status-badge.approved,
        .status-badge.qualified {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }

        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }

        .status-badge.released {
            background: rgba(0, 150, 136, 0.15);
            color: #009688;
        }

        /* ========== SERVICES CARD ========== */
        .services-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .services-card h3 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .services-card h3 i {
            color: var(--maroon-light);
        }

        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .service-tag {
            background: var(--badge-bg);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            color: var(--text-primary);
            font-weight: 500;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .service-tag.no-service {
            background: rgba(244, 67, 54, 0.1);
            color: #EF5350;
            border-color: rgba(244, 67, 54, 0.2);
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .no-data i {
            display: block;
            font-size: 32px;
            margin-bottom: 8px;
            opacity: 0.3;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .two-columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .top-bar-actions {
                justify-content: stretch;
                flex-wrap: wrap;
            }

            .search-box {
                flex: 1;
            }

            .search-box input {
                min-width: 120px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .staff-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .dashboard-card {
                padding: 16px;
            }

            .services-card {
                padding: 16px;
            }

            .quick-section {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Staff Panel</span>
                </div>
            </div>

            <div class="staff-profile">
                <div class="staff-avatar">
                    <?php if (!$use_initials && !empty($avatar_url)): ?>
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
                <li><a href="staff-dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="menu-divider">Appointments</li>
                <li><a href="staff/manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage
                        Appointments</a></li>

                <?php if ($has_aics_access || $has_housing_access || $has_senior_access || $has_pwd_access): ?>
                    <li class="menu-divider">Services</li>
                <?php endif; ?>

                <?php if ($has_aics_access): ?>
                    <li><a href="staff/manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
                <?php endif; ?>
                <?php if ($has_housing_access): ?>
                    <li><a href="staff/manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
                <?php endif; ?>
                <?php if ($has_senior_access): ?>
                    <li><a href="staff/manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
                <?php endif; ?>
                <?php if ($has_pwd_access): ?>
                    <li><a href="staff/manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
                <?php endif; ?>
                <li><a href="staff/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>

                <?php if (isAdmin()): ?>
                    <li class="menu-divider">Administration</li>
                    <li><a href="admin/dashboard.php"><i class="fas fa-crown"></i> Admin Dashboard</a></li>
                    <li><a href="admin/manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <li><a href="admin/manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
                <?php endif; ?>

                <li class="menu-divider"></li>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👋</h1>
                    <p><?php echo date('l, F d, Y'); ?> • Staff Dashboard</p>
                </div>
                <div class="top-bar-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search..." id="globalSearch">
                    </div>
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="staff/reports.php" class="top-icon"><i class="fas fa-chart-bar"></i></a>
                    <a href="logout.php" class="top-icon"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <?php if ($has_aics_access): ?>
                    <div class="stat-card" onclick="window.location.href='staff/manage-aics.php?status=pending'">
                        <div class="stat-icon aics"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['pending_aics'] ?? 0); ?></h3>
                            <p>Pending AICS</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has_housing_access): ?>
                    <div class="stat-card" onclick="window.location.href='staff/manage-housing.php?status=pending'">
                        <div class="stat-icon housing"><i class="fas fa-home"></i></div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['pending_housing'] ?? 0); ?></h3>
                            <p>Pending Housing</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="stat-card" onclick="window.location.href='admin/manage-verifications.php?status=pending'">
                    <div class="stat-icon verification"><i class="fas fa-shield-alt"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['pending_verifications'] ?? 0); ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                </div>

                <div class="stat-card" onclick="window.location.href='staff/manage-appointments.php'">
                    <div class="stat-icon appointment"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                        <p>Today's Appointments</p>
                    </div>
                </div>

                <div class="stat-card" onclick="window.location.href='staff/manage-documents.php'">
                    <div class="stat-icon document"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['pending_documents'] ?? 0); ?></h3>
                        <p>Pending Documents</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-section">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <?php if ($has_aics_access): ?>
                        <a href="staff/manage-aics.php" class="action-btn"><i class="fas fa-hand-holding-heart"></i> Process
                            AICS</a>
                    <?php endif; ?>
                    <?php if ($has_housing_access): ?>
                        <a href="staff/manage-housing.php" class="action-btn"><i class="fas fa-home"></i> Review Housing</a>
                    <?php endif; ?>
                    <a href="staff/manage-appointments.php" class="action-btn"><i class="fas fa-calendar-check"></i>
                        View Appointments</a>
                    <a href="admin/manage-verifications.php" class="action-btn"><i class="fas fa-shield-alt"></i> Verify
                        Residents</a>
                    <a href="staff/reports.php" class="action-btn"><i class="fas fa-chart-bar"></i> Generate Reports</a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="two-columns">
                <?php if ($has_aics_access && !empty($recent_aics)): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-hand-holding-heart"></i> Recent AICS Requests</h3>
                            <a href="staff/manage-aics.php">View all →</a>
                        </div>
                        <?php foreach ($recent_aics as $aics): ?>
                            <div class="request-item">
                                <div class="request-info">
                                    <h4><?php echo htmlspecialchars($aics['first_name'] . ' ' . $aics['last_name']); ?></h4>
                                    <p><?php echo date('M d, Y', strtotime($aics['submission_date'])); ?></p>
                                </div>
                                <span
                                    class="status-badge <?php echo $aics['status']; ?>"><?php echo ucfirst($aics['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_housing_access && !empty($recent_housing)): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-home"></i> Recent Housing Applications</h3>
                            <a href="staff/manage-housing.php">View all →</a>
                        </div>
                        <?php foreach ($recent_housing as $housing): ?>
                            <div class="request-item">
                                <div class="request-info">
                                    <h4><?php echo htmlspecialchars($housing['first_name'] . ' ' . $housing['last_name']); ?>
                                    </h4>
                                    <p><?php echo date('M d, Y', strtotime($housing['application_date'])); ?></p>
                                </div>
                                <span
                                    class="status-badge <?php echo $housing['status']; ?>"><?php echo ucfirst($housing['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($recent_aics) && empty($recent_housing)): ?>
                    <div class="dashboard-card">
                        <div class="no-data">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity to display</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assigned Services -->
            <div class="services-card">
                <h3><i class="fas fa-clipboard-list"></i> Your Assigned Services</h3>
                <div class="service-tags">
                    <?php if (empty($assigned_categories)): ?>
                        <span class="service-tag no-service">
                            <i class="fas fa-exclamation-circle"></i> No services assigned yet. Contact administrator.
                        </span>
                    <?php else: ?>
                        <?php foreach ($assigned_categories as $category): ?>
                            <span class="service-tag">
                                <?php
                                switch ($category['category_name']) {
                                    case 'AICS':
                                        echo '🏥 AICS';
                                        break;
                                    case 'Housing':
                                        echo '🏠 Housing';
                                        break;
                                    case 'Senior Citizen':
                                        echo '👴 Senior Citizen';
                                        break;
                                    case 'PWD':
                                        echo '♿ PWD';
                                        break;
                                    default:
                                        echo htmlspecialchars($category['category_name']);
                                }
                                ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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
        document.getElementById('globalSearch')?.addEventListener('keyup', function (e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = 'staff/manage-residents.php?search=' + encodeURIComponent(query);
                }
            }
        });
    </script>
</body>

</html>
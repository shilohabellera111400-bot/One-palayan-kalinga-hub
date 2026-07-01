<?php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// ========== COMPREHENSIVE STATISTICS ==========
$stats = [];

// Total users
$query = "SELECT COUNT(*) as count FROM users";
$stats['total_users'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Total staff
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'staff'";
$stats['total_staff'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Total residents
$query = "SELECT COUNT(*) as count FROM users WHERE role = 'resident'";
$stats['total_residents'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Total appointments
$query = "SELECT COUNT(*) as count FROM appointments";
$stats['total_appointments'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Total AICS requests
$query = "SELECT COUNT(*) as count FROM aics_requests";
$stats['total_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Total housing applications
$query = "SELECT COUNT(*) as count FROM housing_beneficiaries";
$stats['total_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Pending AICS
$query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'submitted'";
$stats['pending_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Pending housing
$query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status = 'pending'";
$stats['pending_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Pending verifications
$query = "SELECT COUNT(*) as count FROM verification_requests WHERE status = 'pending'";
$stats['pending_verifications'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Verified residents
$query = "SELECT COUNT(*) as count FROM users WHERE verification_status = 'verified'";
$stats['verified_residents'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// AICS by category
$query = "SELECT request_type, COUNT(*) as count FROM aics_requests GROUP BY request_type";
$aics_by_category = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Recent AICS applications
$query = "SELECT a.*, u.first_name, u.last_name 
          FROM aics_requests a 
          JOIN users u ON a.user_id = u.user_id 
          ORDER BY a.submission_date DESC LIMIT 5";
$recent_aics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Recent housing applications
$query = "SELECT h.*, u.first_name, u.last_name, p.program_name 
          FROM housing_beneficiaries h 
          JOIN users u ON h.user_id = u.user_id 
          LEFT JOIN housing_programs p ON h.program_id = p.program_id 
          ORDER BY h.application_date DESC LIMIT 5";
$recent_housing = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Monthly AICS trend (last 6 months)
$query = "SELECT MONTH(submission_date) as month, YEAR(submission_date) as year, COUNT(*) as count 
          FROM aics_requests 
          WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY YEAR(submission_date), MONTH(submission_date)
          ORDER BY year ASC, month ASC";
$monthly_trend = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Status breakdown
$query = "SELECT status, COUNT(*) as count FROM aics_requests GROUP BY status";
$status_breakdown = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$query = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10";
$recent_activities = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Prepare month data for chart
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthly_data = array_fill(0, 12, 0);
foreach ($monthly_trend as $row) {
    $monthly_data[$row['month'] - 1] = $row['count'];
}

// Status colors
$status_colors = [
    'submitted' => '#FFC107',
    'reviewing' => '#42A5F5',
    'approved' => '#66BB6A',
    'rejected' => '#EF5350',
    'released' => '#26A69A'
];

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            /* Light theme defaults */
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
            
            /* Enhanced Shadows - visible in light mode */
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --search-bg: #FFFFFF;
            --stat-bg: #FFFFFF;
            --chart-bg: #FFFFFF;
            --recent-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
        }

        /* ========== DARK THEME ========== */
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
            
            /* Enhanced Shadows - visible in dark mode */
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.70);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --search-bg: #1a2332;
            --stat-bg: #1a2332;
            --chart-bg: #1a2332;
            --recent-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            /* Adjust maroon for dark mode */
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
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

        /* ========== LAYOUT ========== */
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

        .sidebar-menu {
            list-style: none;
            padding: 16px 14px;
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
            margin: 12px 0;
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
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }
        .sidebar-footer .avatar {
            width: 38px;
            height: 38px;
            background: var(--maroon-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.8rem;
        }
        .sidebar-footer .user-details h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-footer .user-details p {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
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
            background: var(--search-bg);
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
            grid-template-columns: repeat(4, 1fr);
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
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card:hover::after {
            opacity: 0.03;
        }
        .stat-card:active {
            transform: translateY(-2px);
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
        .stat-icon.maroon { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.green { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-icon.blue { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .stat-icon.orange { background: rgba(255, 152, 0, 0.12); color: #FF9800; }
        .stat-icon.purple { background: rgba(156, 39, 176, 0.12); color: #9C27B0; }
        .stat-icon.teal { background: rgba(0, 150, 136, 0.12); color: #009688; }

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
        .stat-info .trend {
            font-size: 0.65rem;
            margin-top: 4px;
            font-weight: 600;
        }
        .stat-info .trend.up { color: #4CAF50; }
        .stat-info .trend.down { color: #EF5350; }

        /* ========== CHARTS SECTION ========== */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .chart-card {
            background: var(--chart-bg);
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .chart-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .chart-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .chart-card .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }
        .chart-card .card-header .badge {
            font-size: 0.65rem;
            color: var(--text-muted);
            background: var(--badge-bg);
            padding: 4px 12px;
            border-radius: 20px;
            transition: background 0.3s ease, color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .chart-container {
            position: relative;
            height: 200px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            padding-top: 10px;
        }
        .bar-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            height: 100%;
            justify-content: flex-end;
        }
        .bar {
            width: 100%;
            max-width: 32px;
            border-radius: 6px 6px 0 0;
            min-height: 4px;
            transition: height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(180deg, var(--maroon-light), var(--maroon));
            box-shadow: 0 2px 8px rgba(127, 3, 3, 0.3);
        }
        .bar:hover {
            opacity: 0.8;
            transform: scaleX(1.1);
        }
        .bar-label {
            font-size: 0.6rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Status Breakdown */
        .status-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 0;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .status-item .status-label {
            flex: 1;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }
        .status-item .status-count {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }
        .status-item .status-bar {
            width: 80px;
            height: 6px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            transition: background 0.3s ease;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }
        .status-item .status-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }

        /* ========== BOTTOM SECTIONS ========== */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .recent-card {
            background: var(--recent-bg);
            border-radius: 20px;
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .recent-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .recent-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .recent-card .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }
        .recent-card .card-header a {
            font-size: 0.75rem;
            color: var(--maroon-light);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .recent-card .card-header a:hover { 
            text-decoration: underline;
            transform: translateX(4px);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            border-radius: 8px;
            padding-left: 4px;
            padding-right: 4px;
        }
        .activity-item:hover {
            background: var(--badge-bg);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }
        .activity-icon.aics { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .activity-icon.housing { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .activity-icon.verification { background: rgba(0, 150, 136, 0.12); color: #009688; }
        .activity-icon.appointment { background: rgba(255, 152, 0, 0.12); color: #FF9800; }
        .activity-icon.system { background: rgba(156, 39, 176, 0.12); color: #9C27B0; }

        .activity-content {
            flex: 1;
            min-width: 0;
        }
        .activity-content .title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            transition: color 0.3s ease;
        }
        .activity-content .desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .activity-time {
            font-size: 0.65rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.submitted { background: rgba(255, 152, 0, 0.2); color: #FF9800; }
        .status-badge.reviewing { background: rgba(33, 150, 243, 0.2); color: #42A5F5; }
        .status-badge.approved { background: rgba(76, 175, 80, 0.2); color: #66BB6A; }
        .status-badge.rejected { background: rgba(244, 67, 54, 0.2); color: #EF5350; }
        .status-badge.released { background: rgba(0, 150, 136, 0.2); color: #26A69A; }
        .status-badge.pending { background: rgba(156, 39, 176, 0.2); color: #AB47BC; }
        .status-badge.qualified { background: rgba(33, 150, 243, 0.2); color: #42A5F5; }

        .no-data {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; align-items: stretch; }
            .search-box input { min-width: 120px; }
            .bottom-grid { grid-template-columns: 1fr; }
            .charts-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 14px 16px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .stat-info h3 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-crown"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Admin Panel</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            <li class="menu-divider">System</li>
            <li><a href="audit-logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo $initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Overview of the One Palayan-Kalinga Hub</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="audit-logs.php" class="top-icon"><i class="fas fa-bell"></i></a>
                <a href="settings.php" class="top-icon"><i class="fas fa-cog"></i></a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-verifications.php'">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_verifications']); ?></h3>
                    <p>Pending Verifications</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-verifications.php?status=verified'">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['verified_residents']); ?></h3>
                    <p>Verified Residents</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-staff.php'">
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_staff']); ?></h3>
                    <p>Staff Members</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-residents.php'">
                <div class="stat-icon purple"><i class="fas fa-user-friends"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_residents']); ?></h3>
                    <p>Total Residents</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='../staff/manage-aics.php'">
                <div class="stat-icon maroon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_aics']); ?></h3>
                    <p>AICS Requests</p>
                    <?php if ($stats['pending_aics'] > 0): ?>
                    <div class="trend up"><?php echo $stats['pending_aics']; ?> pending</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='../staff/manage-housing.php'">
                <div class="stat-icon teal"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_housing']); ?></h3>
                    <p>Housing Apps</p>
                    <?php if ($stats['pending_housing'] > 0): ?>
                    <div class="trend up"><?php echo $stats['pending_housing']; ?> pending</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Monthly Trend Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar" style="color: var(--maroon-light); margin-right: 8px;"></i> AICS Applications Trend</h3>
                    <span class="badge">Last 6 months</span>
                </div>
                <div class="chart-container">
                    <?php 
                    $max_val = max($monthly_data) > 0 ? max($monthly_data) : 1;
                    $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $start_month = date('n') - 5;
                    if ($start_month < 1) $start_month += 12;
                    for ($i = 0; $i < 6; $i++):
                        $idx = ($start_month + $i - 1) % 12;
                        $val = $monthly_data[$idx];
                        $height = ($val / $max_val) * 170;
                    ?>
                    <div class="bar-wrapper">
                        <div class="bar" style="height: <?php echo max($height, 4); ?>px;"></div>
                        <span class="bar-label"><?php echo $month_names[$idx]; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie" style="color: var(--maroon-light); margin-right: 8px;"></i> AICS Status</h3>
                    <span class="badge">Total: <?php echo $stats['total_aics']; ?></span>
                </div>
                <div class="status-list">
                    <?php 
                    $status_labels = ['submitted' => 'Submitted', 'reviewing' => 'Reviewing', 'approved' => 'Approved', 'rejected' => 'Rejected', 'released' => 'Released'];
                    foreach ($status_labels as $key => $label):
                        $count = 0;
                        foreach ($status_breakdown as $s) {
                            if ($s['status'] == $key) $count = $s['count'];
                        }
                        $pct = $stats['total_aics'] > 0 ? round(($count / $stats['total_aics']) * 100) : 0;
                    ?>
                    <div class="status-item">
                        <span class="status-dot" style="background: <?php echo $status_colors[$key] ?? '#999'; ?>;"></span>
                        <span class="status-label"><?php echo $label; ?></span>
                        <div class="status-bar">
                            <div class="fill" style="width: <?php echo $pct; ?>%; background: <?php echo $status_colors[$key] ?? '#999'; ?>;"></div>
                        </div>
                        <span class="status-count"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity Sections -->
        <div class="bottom-grid">
            <!-- Recent AICS -->
            <div class="recent-card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-holding-heart" style="color: var(--maroon-light); margin-right: 8px;"></i> Recent AICS Requests</h3>
                    <a href="../staff/manage-aics.php">View All →</a>
                </div>
                <?php if (count($recent_aics) > 0): ?>
                    <?php foreach ($recent_aics as $a): ?>
                    <div class="activity-item">
                        <div class="activity-icon aics"><i class="fas fa-file-alt"></i></div>
                        <div class="activity-content">
                            <div class="title"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></div>
                            <div class="desc"><?php echo ucfirst($a['request_type']); ?> • ₱<?php echo number_format($a['amount_requested'] ?? 0, 2); ?></div>
                        </div>
                        <span class="status-badge <?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span>
                        <span class="activity-time"><?php echo date('M d', strtotime($a['submission_date'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data">No recent AICS requests</p>
                <?php endif; ?>
            </div>

            <!-- Recent Housing -->
            <div class="recent-card">
                <div class="card-header">
                    <h3><i class="fas fa-home" style="color: var(--maroon-light); margin-right: 8px;"></i> Recent Housing Applications</h3>
                    <a href="../staff/manage-housing.php">View All →</a>
                </div>
                <?php if (count($recent_housing) > 0): ?>
                    <?php foreach ($recent_housing as $h): ?>
                    <div class="activity-item">
                        <div class="activity-icon housing"><i class="fas fa-building"></i></div>
                        <div class="activity-content">
                            <div class="title"><?php echo htmlspecialchars($h['first_name'] . ' ' . $h['last_name']); ?></div>
                            <div class="desc"><?php echo htmlspecialchars($h['program_name'] ?? 'N/A'); ?></div>
                        </div>
                        <span class="status-badge <?php echo $h['status']; ?>"><?php echo ucfirst($h['status']); ?></span>
                        <span class="activity-time"><?php echo date('M d', strtotime($h['application_date'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data">No recent housing applications</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activities (Audit Logs) -->
        <div class="recent-card" style="margin-top: 0;">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color: var(--maroon-light); margin-right: 8px;"></i> Recent System Activities</h3>
                <a href="audit-logs.php">View All →</a>
            </div>
            <?php if (!empty($recent_activities)): ?>
                <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon system"><i class="fas fa-user-check"></i></div>
                    <div class="activity-content">
                        <div class="title">User #<?php echo $activity['user_id']; ?> • <?php echo ucfirst($activity['action']); ?></div>
                        <div class="desc"><?php echo $activity['table_affected'] ? 'Table: ' . $activity['table_affected'] : 'System action'; ?></div>
                    </div>
                    <span class="activity-time"><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">No recent activities</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// Check for saved theme preference
const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

// Apply theme
function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Update icon
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    
    // Set cookie for PHP
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

// Initialize theme
setTheme(currentTheme);

// Toggle on click
themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// ========== SEARCH FUNCTIONALITY ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'manage-residents.php?search=' + encodeURIComponent(query);
        }
    }
});

// ========== ANIMATE BARS ON LOAD ==========
document.addEventListener('DOMContentLoaded', function() {
    const bars = document.querySelectorAll('.bar');
    bars.forEach((bar, index) => {
        const height = bar.style.height;
        bar.style.height = '4px';
        setTimeout(() => {
            bar.style.height = height;
        }, 200 + (index * 100));
    });
});
</script>
</body>
</html>
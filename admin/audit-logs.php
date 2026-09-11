<?php
// admin/audit-logs.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$action_filter = isset($_GET['action']) ? sanitize($_GET['action']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$user_filter = isset($_GET['user_id']) ? sanitize($_GET['user_id']) : 'all';

// Build query
$query = "SELECT al.*, 
          u.first_name, u.last_name, u.email, u.role,
          CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM audit_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($action_filter != 'all') {
    $query .= " AND al.action = :action";
    $params[':action'] = $action_filter;
}

if ($user_filter != 'all') {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(al.created_at) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (al.action LIKE :search OR al.ip_address LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR al.description LIKE :search)";
    $params[':search'] = "%$search%";
}

// Check if CSV export is requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $query .= " ORDER BY al.created_at DESC";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add headers
    fputcsv($output, ['Date', 'Time', 'User', 'Role', 'Action', 'Description', 'IP Address']);

    // Add data rows
    while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            date('Y-m-d', strtotime($log['created_at'])),
            date('H:i:s', strtotime($log['created_at'])),
            $log['user_name'] ?? 'System',
            $log['role'] ?? 'N/A',
            ucfirst($log['action']),
            $log['description'] ?? '',
            $log['ip_address']
        ]);
    }

    fclose($output);
    exit;
}

// Query distinct actions for dropdown
$actions_list = [];
try {
    $act_res = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
    $actions_list = $act_res->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error
}

// Query distinct users who have logs
$users_list = [];
try {
    $usr_res = $db->query("SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.role 
                           FROM audit_logs al 
                           JOIN users u ON al.user_id = u.user_id 
                           ORDER BY u.last_name ASC");
    $users_list = $usr_res->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Count total records
$count_query = "SELECT COUNT(*) as count FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id WHERE 1=1";
if ($action_filter != 'all') {
    $count_query .= " AND al.action = :action";
}
if ($user_filter != 'all') {
    $count_query .= " AND al.user_id = :user_id";
}
if (!empty($date_from) && !empty($date_to)) {
    $count_query .= " AND DATE(al.created_at) BETWEEN :date_from AND :date_to";
}
if (!empty($search)) {
    $count_query .= " AND (al.action LIKE :search OR al.ip_address LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR al.description LIKE :search)";
}

$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$total_pages = max(1, ceil($total_records / $per_page));

// Execute query with LIMIT
$query .= " ORDER BY al.created_at DESC LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name'] ?? 'Admin');
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== THEME COLORS ========== */
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
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #1A2530;
            --text-secondary: #4A5568;
            --text-muted: #718096;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E2E8F0;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.08);
            --shadow-md: 0 6px 20px rgba(15,65,74,0.1);
            --shadow-lg: 0 12px 35px rgba(15,65,74,0.12);
            --shadow-hover: 0 12px 30px rgba(127,3,3,0.15);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #F44336;
            --color-info: #2196F3;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
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
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
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
        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
        }
        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-image {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,0.10);
            padding: 4px;
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
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
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
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-info .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .user-details h4 {
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .user-details p {
            color: rgba(255,255,255,0.5);
            font-size: 0.68rem;
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
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        .filter-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            outline: none;
            font-family: inherit;
            font-size: 0.85rem;
            transition: border-color 0.2s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--maroon-light);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
            width: 100%;
            justify-content: flex-end;
        }
        .btn {
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-csv {
            background: #2e7d32;
            color: white;
        }
        .btn-csv:hover {
            background: #1b5e20;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .btn-secondary:hover {
            background: var(--border-color);
        }

        /* ========== LOGS TABLE ========== */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }
        .log-table th {
            background: var(--bg-primary);
            padding: 14px 18px;
            color: var(--text-primary);
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
        }
        .log-table td {
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .log-table tr:last-child td {
            border-bottom: none;
        }
        .log-table tr:hover td {
            background: rgba(15, 65, 74, 0.02);
        }
        .role-badge {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 20px;
            background: var(--badge-bg);
            color: var(--text-muted);
            display: inline-block;
        }
        .role-badge.admin {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
        }
        .role-badge.staff {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .action-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 4px;
            background: var(--badge-bg);
            color: var(--text-primary);
            border-left: 3px solid var(--text-muted);
        }
        .action-badge.login { border-left-color: var(--color-success); }
        .action-badge.status_update { border-left-color: var(--color-warning); }
        .action-badge.delete_resident { border-left-color: var(--color-danger); }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-card);
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .page-links {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .page-links a,
        .page-links span.disabled {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .page-links a:hover {
            background: var(--badge-bg);
            color: var(--maroon);
        }
        .page-links a.active {
            background: var(--maroon);
            color: white;
        }
        .page-links span.disabled {
            color: var(--text-muted);
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-links span.ellipsis {
            padding: 0 4px;
            color: var(--text-muted);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group select, .filter-group input { width: 100% !important; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <img src="../images/logo.png" alt="Palayan City Logo" class="logo-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            <li><a href="manage-users.php"><i class="fas fa-users-cog"></i> System Users</a></li>
            <li class="menu-divider">System</li>
            <li><a href="audit-logs.php" class="active"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo $initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h4>
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
                <h1><i class="fas fa-history" style="color: var(--maroon-light); margin-right: 10px;"></i> System Audit Logs</h1>
                <p>Track administrator and staff activities inside the platform</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="filter-bar">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label><i class="fas fa-tasks"></i> Action</label>
                    <select name="action">
                        <option value="all" <?php echo $action_filter == 'all' ? 'selected' : ''; ?>>All Actions</option>
                        <?php foreach ($actions_list as $act): ?>
                            <option value="<?php echo htmlspecialchars($act['action']); ?>" <?php echo $action_filter == $act['action'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $act['action'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> User</label>
                    <select name="user_id">
                        <option value="all" <?php echo $user_filter == 'all' ? 'selected' : ''; ?>>All Users</option>
                        <?php foreach ($users_list as $usr): ?>
                            <option value="<?php echo $usr['user_id']; ?>" <?php echo $user_filter == $usr['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usr['last_name'] . ', ' . $usr['first_name'] . ' (' . ucfirst($usr['role']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <label><i class="fas fa-search"></i> Search Keyword</label>
                    <input type="text" name="search" placeholder="IP address, description, etc." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%;">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
                    <button type="submit" name="export" value="csv" class="btn btn-csv"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <?php if($action_filter != 'all' || $user_filter != 'all' || !empty($search)): ?>
                        <a href="audit-logs.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-card">
            <div class="table-container">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): 
                                $badge_class = strtolower($log['action']);
                                $role_class = strtolower($log['role'] ?? '');
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($log['created_at'])); ?></strong><br>
                                        <small style="color: var(--text-muted);"><?php echo date('h:i:A', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System / Anonymous'); ?></strong><br>
                                        <small style="color: var(--text-muted);"><?php echo htmlspecialchars($log['email'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $role_class; ?>">
                                            <?php echo htmlspecialchars($log['role'] ?? 'System'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="action-badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 320px; white-space: normal; line-height: 1.4;">
                                        <?php echo htmlspecialchars($log['description'] ?? 'No description provided'); ?>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                                    No audit logs found matching your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Grid -->
            <?php if($total_pages > 1): 
                $page_params = $_GET;
            ?>
            <div class="pagination">
                <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> records</span>
                
                <div class="page-links">
                    <?php if($page > 1): 
                        $page_params['page'] = $page - 1;
                    ?>
                        <a href="?<?php echo http_build_query($page_params); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        $page_params['page'] = 1;
                        echo '<a href="?' . http_build_query($page_params) . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $page ? 'active' : '';
                        $page_params['page'] = $i;
                        echo '<a href="?' . http_build_query($page_params) . '" class="' . $active . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="ellipsis">...</span>';
                        }
                        $page_params['page'] = $total_pages;
                        echo '<a href="?' . http_build_query($page_params) . '">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if($page < $total_pages): 
                        $page_params['page'] = $page + 1;
                    ?>
                        <a href="?<?php echo http_build_query($page_params); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Dark mode toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            document.cookie = 'dark_mode=' + (newTheme === 'dark' ? 'enabled' : 'disabled') + '; path=/; max-age=' + (365*24*3600);
            
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }
</script>
</body>
</html>



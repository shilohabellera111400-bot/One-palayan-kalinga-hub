<?php
// admin/settings.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        $site_name = sanitize($_POST['site_name']);
        $site_description = sanitize($_POST['site_description']);
        $contact_email = sanitize($_POST['contact_email']);
        $contact_phone = sanitize($_POST['contact_phone']);
        $address = sanitize($_POST['address']);
        
        // Update settings in database
        $update_query = "UPDATE system_settings SET 
                         setting_value = CASE setting_key
                             WHEN 'site_name' THEN :site_name
                             WHEN 'site_description' THEN :site_description
                             WHEN 'contact_email' THEN :contact_email
                             WHEN 'contact_phone' THEN :contact_phone
                             WHEN 'address' THEN :address
                         END
                         WHERE setting_key IN ('site_name', 'site_description', 'contact_email', 'contact_phone', 'address')";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':site_name', $site_name);
        $update_stmt->bindParam(':site_description', $site_description);
        $update_stmt->bindParam(':contact_email', $contact_email);
        $update_stmt->bindParam(':contact_phone', $contact_phone);
        $update_stmt->bindParam(':address', $address);
        
        if ($update_stmt->execute()) {
            $success = "General settings updated successfully!";
        } else {
            $error = "Failed to update general settings.";
        }
    }
    
    if (isset($_POST['update_maintenance'])) {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $maintenance_message = sanitize($_POST['maintenance_message']);
        
        $update_query = "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key";
        
        $stmt1 = $db->prepare($update_query);
        $stmt1->bindParam(':value', $maintenance_mode);
        $stmt1->bindParam(':key', 'maintenance_mode');
        $stmt1->execute();
        
        $stmt2 = $db->prepare($update_query);
        $stmt2->bindParam(':value', $maintenance_message);
        $stmt2->bindParam(':key', 'maintenance_message');
        $stmt2->execute();
        
        $success = "Maintenance settings updated successfully!";
    }
}

// Get current settings
$settings = [];
$query = "SELECT setting_key, setting_value FROM system_settings";
$result = $db->query($query);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default settings if not set
$default_settings = [
    'site_name' => 'One Palayan-Kalinga Hub',
    'site_description' => 'Integrated Social Services Platform for Palayan City Residents',
    'contact_email' => 'cswdo@palayancity.gov.ph',
    'contact_phone' => '+63 (44) 123 4567',
    'address' => 'City Social Welfare and Development Office, Palayan City, Nueva Ecija',
    'maintenance_mode' => 0,
    'maintenance_message' => 'The system is currently under maintenance. Please check back later.',
    'appointment_lead_days' => 30,
    'max_file_size' => 5,
    'allowed_file_types' => 'pdf,jpg,jpeg,png'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get database info for system info
$dbname_query = $db->query("SELECT DATABASE() as dbname");
$dbname = $dbname_query->fetch(PDO::FETCH_ASSOC);

$size_query = "SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = :dbname";
$size_stmt = $db->prepare($size_query);
$size_stmt->bindParam(':dbname', $dbname['dbname']);
$size_stmt->execute();
$db_size = $size_stmt->fetch(PDO::FETCH_ASSOC);
$size_mb = round($db_size['size'] / 1024 / 1024, 2);

// Get table count
$table_query = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = :dbname";
$table_stmt = $db->prepare($table_query);
$table_stmt->bindParam(':dbname', $dbname['dbname']);
$table_stmt->execute();
$table_count = $table_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get user count
$user_query = "SELECT COUNT(*) as count FROM users";
$user_count = $db->query($user_query)->fetch(PDO::FETCH_ASSOC)['count'];

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
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
    <title>System Settings - Admin</title>
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
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            --input-bg: #FFFFFF;
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
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
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
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
            color: var(--color-success);
            border-left-color: var(--color-success);
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            border-left-color: var(--color-danger);
        }
        .alert i { font-size: 1.2rem; }

        /* ========== STATS ROW ========== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-item {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-item .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-item .icon.db { background: rgba(33, 150, 243, 0.12); color: var(--color-info); }
        .stat-item .icon.users { background: rgba(76, 175, 80, 0.12); color: var(--color-success); }
        .stat-item .icon.tables { background: rgba(255, 152, 0, 0.12); color: var(--color-warning); }
        .stat-item .icon.size { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-item .info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-item .info p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ========== SETTINGS GRID ========== */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .settings-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .settings-card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--maroon);
        }
        .settings-card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .settings-card .card-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .form-group label i {
            color: var(--maroon-light);
            margin-right: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group input:disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--maroon);
            cursor: pointer;
        }
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-success {
            background: var(--color-success);
            color: white;
        }
        .btn-success:hover {
            background: #388E3C;
        }
        .btn-warning {
            background: var(--color-warning);
            color: white;
        }
        .btn-warning:hover {
            background: #F57C00;
        }
        .btn-danger {
            background: var(--color-danger);
            color: white;
        }
        .btn-danger:hover {
            background: #C62828;
        }

        .info-box {
            background: var(--badge-bg);
            padding: 14px 18px;
            border-radius: var(--radius-element);
            margin-top: 16px;
            border-left: 4px solid var(--color-info);
        }
        .info-box p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .info-box p i {
            color: var(--color-info);
            margin-right: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        /* Full width card */
        .full-width {
            grid-column: 1 / -1;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .settings-card { padding: 18px 20px; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-primary { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; }
            .settings-card { padding: 14px 16px; }
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            <li class="menu-divider">System</li>
            <li><a href="audit-logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> System Settings</a></li>
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
                <h1><i class="fas fa-cog" style="color: var(--maroon-light); margin-right: 10px;"></i> System Settings</h1>
                <p>Configure system-wide settings and preferences</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search settings..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="audit-logs.php" class="top-icon"><i class="fas fa-bell"></i></a>
                <a href="backup.php" class="top-icon"><i class="fas fa-database"></i></a>
            </div>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="icon db"><i class="fas fa-server"></i></div>
                <div class="info">
                    <h3><?php echo htmlspecialchars($dbname['dbname']); ?></h3>
                    <p>Database Name</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon tables"><i class="fas fa-table"></i></div>
                <div class="info">
                    <h3><?php echo $table_count; ?></h3>
                    <p>Tables</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon users"><i class="fas fa-users"></i></div>
                <div class="info">
                    <h3><?php echo $user_count; ?></h3>
                    <p>Users</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon size"><i class="fas fa-weight-hanging"></i></div>
                <div class="info">
                    <h3><?php echo $size_mb; ?> MB</h3>
                    <p>Database Size</p>
                </div>
            </div>
        </div>
        
        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- General Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-globe"></i>
                    <h2>General Settings</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Site Name</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Site Description</label>
                        <textarea name="site_description" rows="2"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Contact Email</label>
                        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Contact Phone</label>
                        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Office Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_general" class="btn-primary">
                        <i class="fas fa-save"></i> Save General Settings
                    </button>
                </form>
            </div>
            
            <!-- Maintenance Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-tools"></i>
                    <h2>Maintenance Mode</h2>
                </div>
                <form method="POST">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] == 1 ? 'checked' : ''; ?>>
                        <label for="maintenance_mode">Enable Maintenance Mode</label>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Maintenance Message</label>
                        <textarea name="maintenance_message" rows="3" id="maintenance_message"><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                        <small>This message will be shown to users when maintenance mode is enabled</small>
                    </div>
                    
                    <button type="submit" name="update_maintenance" class="btn-primary">
                        <i class="fas fa-save"></i> Save Maintenance Settings
                    </button>
                </form>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> When maintenance mode is enabled, only administrators can access the system. Regular users will see the maintenance message.</p>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-server"></i>
                    <h2>System Information</h2>
                </div>
                <div class="form-group">
                    <label><i class="fab fa-php"></i> PHP Version</label>
                    <input type="text" value="<?php echo phpversion(); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-database"></i> MySQL Version</label>
                    <?php
                    $mysql_version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
                    ?>
                    <input type="text" value="<?php echo $mysql_version; ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-laptop"></i> Server Software</label>
                    <input type="text" value="<?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-upload"></i> Upload Max File Size</label>
                    <input type="text" value="<?php echo ini_get('upload_max_filesize'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Server Time</label>
                    <input type="text" value="<?php echo date('Y-m-d H:i:s'); ?>" disabled>
                </div>
            </div>
            
            <!-- File Upload & App Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-upload"></i>
                    <h2>Upload & App Settings</h2>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-alt"></i> Allowed File Types</label>
                    <input type="text" value="<?php echo $settings['allowed_file_types']; ?>" disabled>
                    <small>Allowed file extensions for uploads</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-weight-hanging"></i> Max File Size</label>
                    <input type="text" value="<?php echo $settings['max_file_size']; ?> MB" disabled>
                    <small>Maximum file size for uploads</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-week"></i> Appointment Lead Days</label>
                    <input type="text" value="<?php echo $settings['appointment_lead_days']; ?> days" disabled>
                    <small>Maximum days in advance for booking appointments</small>
                </div>
                
                <div class="info-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>Security Notice:</strong> All uploaded files are scanned and stored securely. File paths are encrypted in the database.</p>
                </div>
            </div>
            
            <!-- Database Management - Full Width -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <i class="fas fa-database"></i>
                    <h2>Database Management</h2>
                </div>
                <div class="action-buttons">
                    <a href="backup.php" class="btn-primary btn-success">
                        <i class="fas fa-download"></i> Backup Database
                    </a>
                    <a href="optimize-db.php" class="btn-primary btn-warning">
                        <i class="fas fa-chart-line"></i> Optimize Database
                    </a>
                    <button onclick="if(confirm('Are you sure you want to clear all cache? This action cannot be undone.')) window.location.href='clear-cache.php';" class="btn-primary btn-danger">
                        <i class="fas fa-trash"></i> Clear Cache
                    </button>
                </div>
                <div class="info-box" style="margin-top: 16px;">
                    <p><i class="fas fa-clock"></i> <strong>Last Backup:</strong> <?php echo date('F d, Y h:i A'); ?></p>
                    <p><i class="fas fa-chart-line"></i> <strong>Database Size:</strong> <?php echo $size_mb; ?> MB</p>
                    <p><i class="fas fa-table"></i> <strong>Total Tables:</strong> <?php echo $table_count; ?></p>
                </div>
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

// ========== MAINTENANCE MODE TOGGLE ==========
const maintenanceCheckbox = document.getElementById('maintenance_mode');
const maintenanceMessage = document.getElementById('maintenance_message');

if (maintenanceCheckbox) {
    maintenanceCheckbox.addEventListener('change', function() {
        if (maintenanceMessage) {
            maintenanceMessage.disabled = !this.checked;
            if (!this.checked) {
                maintenanceMessage.style.opacity = '0.6';
            } else {
                maintenanceMessage.style.opacity = '1';
            }
        }
    });
    // Initialize state
    if (maintenanceMessage) {
        maintenanceMessage.disabled = !maintenanceCheckbox.checked;
        maintenanceMessage.style.opacity = maintenanceCheckbox.checked ? '1' : '0.6';
    }
}

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll('.settings-card');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });
    }
});
</script>
</body>
</html>
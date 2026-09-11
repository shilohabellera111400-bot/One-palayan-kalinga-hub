<?php
// staff/kanban-aics.php - Drag & Drop Kanban Board for Staff
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Access check: Get assigned categories for this staff
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);

if (!$has_aics_access) {
    $_SESSION['error'] = "You do not have permission to manage AICS.";
    redirect('../staff-dashboard.php');
}

// Fetch staff details
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all AICS requests
$query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.profile_picture 
          FROM aics_requests a 
          JOIN users u ON a.user_id = u.user_id
          ORDER BY a.submission_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map into 5 columns
$columns = [
    'submitted' => [],
    'reviewing' => [],
    'approved'  => [],
    'released'  => [],
    'rejected'  => []
];

foreach($all_requests as $req) {
    if (array_key_exists($req['status'], $columns)) {
        $columns[$req['status']][] = $req;
    }
}

// Column configuration metadata
$column_config = [
    'submitted' => [
        'title' => 'Submitted',
        'icon'  => 'fa-clock',
        'color' => '#FF9800',
        'bg'    => 'rgba(255, 152, 0, 0.12)'
    ],
    'reviewing' => [
        'title' => 'Reviewing',
        'icon'  => 'fa-search',
        'color' => '#2196F3',
        'bg'    => 'rgba(33, 150, 243, 0.12)'
    ],
    'approved' => [
        'title' => 'Approved',
        'icon'  => 'fa-check-circle',
        'color' => '#4CAF50',
        'bg'    => 'rgba(76, 175, 80, 0.12)'
    ],
    'released' => [
        'title' => 'Released',
        'icon'  => 'fa-hand-holding-usd',
        'color' => '#9C27B0',
        'bg'    => 'rgba(156, 39, 176, 0.12)'
    ],
    'rejected' => [
        'title' => 'Did Not Qualify',
        'icon'  => 'fa-times-circle',
        'color' => '#EF5350',
        'bg'    => 'rgba(244, 67, 54, 0.12)'
    ]
];

// Sidebar initials & avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Dark mode check
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AICS Kanban Board - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 6px 24px rgba(15,65,74,0.12), 0 2px 8px rgba(0,0,0,0.07);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --stat-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 18px;
            --radius-element: 12px;
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
            
            --stat-bg: #1a2332;
            --filter-bg: #1a2332;
            --table-bg: #1a2332;
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

        /* Sidebar */
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
            padding: 24px 20px 18px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--tan);
            font-size: 1.2rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .staff-profile {
            padding: 18px 20px;
            border-bottom: 1px solid var(--divider);
            text-align: center;
        }
        .staff-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            background: var(--maroon-light);
            border: 3px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }
        .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-profile h4 {
            color: var(--text-sidebar-active);
            font-size: 0.95rem;
            font-weight: 600;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.6);
            font-size: 0.72rem;
            margin-top: 2px;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 10px;
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
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 0.95rem; color: rgba(255,255,255,0.4); }
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 32px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-view-toggle {
            background: var(--maroon);
            color: white;
            padding: 9px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
        }
        .btn-view-toggle:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
        }

        /* Filter Controls */
        .kanban-toolbar {
            background: var(--bg-card);
            padding: 14px 20px;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            flex: 1;
            max-width: 380px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: 50px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.82rem;
            outline: none;
            transition: all 0.3s;
        }
        .search-box input:focus {
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-pill {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-pill.active, .filter-pill:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        /* Kanban Grid Board */
        .kanban-container {
            flex: 1;
            overflow-x: auto;
            padding-bottom: 16px;
        }
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(5, minmax(270px, 1fr));
            gap: 18px;
            align-items: start;
            min-width: 1350px;
        }
        @media(max-width: 1550px) {
            .kanban-board {
                min-width: 1300px;
            }
        }
        
        .kanban-column {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 16px;
            min-height: 600px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
        }
        .kanban-column.drag-over {
            border-color: var(--maroon-light);
            background: rgba(127, 3, 3, 0.04);
            box-shadow: var(--shadow-md);
            transform: scale(1.01);
        }

        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .column-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .column-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        .column-header h3 {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .column-count {
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
        }

        .card-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-height: 480px;
        }
        .card-list.empty {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .empty-placeholder {
            text-align: center;
            color: var(--text-muted);
            opacity: 0.5;
            padding: 30px 10px;
            font-size: 0.78rem;
        }
        .empty-placeholder i {
            font-size: 1.8rem;
            margin-bottom: 8px;
            display: block;
        }

        /* Kanban Card */
        .kanban-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 14px;
            cursor: grab;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .kanban-card:active {
            cursor: grabbing;
        }
        .kanban-card.dragging {
            opacity: 0.4;
            transform: scale(0.95);
            border-style: dashed;
        }
        .kanban-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--maroon-light);
            transform: translateY(-2px);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .tracking-id {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--maroon-light);
            background: rgba(127, 3, 3, 0.08);
            padding: 2px 8px;
            border-radius: 6px;
        }
        .type-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            background: var(--badge-bg);
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        .applicant-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mini-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--maroon-light);
            color: white;
            font-size: 0.62rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mini-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-purpose {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-bottom: 10px;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--text-muted);
            border-top: 1px dashed var(--border-color);
            padding-top: 10px;
            margin-top: 8px;
        }
        .card-amount {
            font-weight: 800;
            color: #2E7D32;
            font-size: 0.82rem;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-btn {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .card-btn:hover {
            background: var(--maroon);
            color: white;
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }
        .toast {
            background: var(--midnight);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: toastIn 0.3s ease;
        }
        @keyframes toastIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar Menu -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-columns"></i></div>
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
            <li><a href="manage-aics.php" class="active"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
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
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-columns" style="color: var(--maroon-light);"></i> AICS Kanban Board</h1>
                <p>I-drag at i-drop ang mga request para baguhin ang kanilang status sa realtime.</p>
            </div>
            <div class="top-bar-actions">
                <a href="manage-aics.php" class="btn-view-toggle">
                    <i class="fas fa-table"></i> Table View
                </a>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
            </div>
        </div>

        <!-- Kanban Toolbar / Filter -->
        <div class="kanban-toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="kanbanSearch" placeholder="Filter applicant name, ID or type...">
            </div>
            <div class="filter-pills" id="typePills">
                <button class="filter-pill active" data-type="all"><i class="fas fa-th-large"></i> All Types</button>
                <button class="filter-pill" data-type="medical"><i class="fas fa-file-medical"></i> Medical</button>
                <button class="filter-pill" data-type="funeral"><i class="fas fa-cross"></i> Funeral</button>
                <button class="filter-pill" data-type="food"><i class="fas fa-utensils"></i> Food</button>
                <button class="filter-pill" data-type="transportation"><i class="fas fa-bus"></i> Travel</button>
                <button class="filter-pill" data-type="cash_relief"><i class="fas fa-hand-holding-usd"></i> Cash</button>
            </div>
        </div>

        <!-- Kanban Board Grid -->
        <div class="kanban-container">
            <div class="kanban-board">
                <?php foreach($column_config as $status_key => $config): ?>
                <div class="kanban-column" id="col-<?php echo $status_key; ?>" data-status="<?php echo $status_key; ?>">
                    <div class="column-header">
                        <div class="column-header-title">
                            <div class="column-icon" style="background: <?php echo $config['bg']; ?>; color: <?php echo $config['color']; ?>;">
                                <i class="fas <?php echo $config['icon']; ?>"></i>
                            </div>
                            <h3><?php echo $config['title']; ?></h3>
                        </div>
                        <span class="column-count" id="count-<?php echo $status_key; ?>" style="background: <?php echo $config['color']; ?>;">
                            <?php echo count($columns[$status_key]); ?>
                        </span>
                    </div>

                    <div class="card-list" id="list-<?php echo $status_key; ?>">
                        <?php if(empty($columns[$status_key])): ?>
                            <div class="empty-placeholder">
                                <i class="fas fa-inbox"></i>
                                <span>Walang request</span>
                            </div>
                        <?php else: ?>
                            <?php foreach($columns[$status_key] as $req): 
                                $app_initials = strtoupper(substr($req['first_name'], 0, 1) . substr($req['last_name'], 0, 1));
                                $app_pic = !empty($req['profile_picture']) && file_exists(__DIR__ . '/../' . $req['profile_picture']) 
                                    ? '../' . $req['profile_picture'] 
                                    : '';
                                $type_clean = str_replace('_', ' ', $req['request_type']);
                            ?>
                                <div class="kanban-card" draggable="true" id="req-<?php echo $req['aics_id']; ?>" data-id="<?php echo $req['aics_id']; ?>" data-type="<?php echo strtolower($req['request_type']); ?>" data-search="<?php echo strtolower($req['first_name'] . ' ' . $req['last_name'] . ' AICS-' . str_pad($req['aics_id'], 6, '0', STR_PAD_LEFT) . ' ' . $type_clean); ?>">
                                    <div class="card-top">
                                        <span class="tracking-id">AICS-<?php echo str_pad($req['aics_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        <span class="type-badge"><?php echo ucfirst($type_clean); ?></span>
                                    </div>
                                    <div class="applicant-name">
                                        <div class="mini-avatar">
                                            <?php if(!empty($app_pic)): ?>
                                                <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo $app_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></span>
                                    </div>
                                    <p class="card-purpose"><?php echo htmlspecialchars($req['purpose'] ?? 'Assistance request'); ?></p>
                                    <div class="card-footer-meta">
                                        <span class="card-amount">₱<?php echo number_format($req['amount_requested']); ?></span>
                                        <div class="card-actions">
                                            <span style="font-size:0.68rem; margin-right:4px; opacity:0.7;"><i class="fas fa-calendar-alt"></i> <?php echo date('M d', strtotime($req['submission_date'])); ?></span>
                                            <a href="view-aics.php?id=<?php echo $req['aics_id']; ?>" class="card-btn" title="View details"><i class="fas fa-eye"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    
    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        const icon = themeToggle.querySelector('i');
        icon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        document.cookie = `dark_mode=${next === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
    });

    // Drag and Drop Logic
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', this.id);
            this.classList.add('dragging');
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
    });
    
    columns.forEach(col => {
        col.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        col.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        col.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.getElementById(cardId);
            if (!card) return;
            
            const targetList = this.querySelector('.card-list');
            
            // Remove empty placeholder if existing
            const emptyMsg = targetList.querySelector('.empty-placeholder');
            if (emptyMsg) {
                emptyMsg.remove();
            }
            
            targetList.appendChild(card);
            
            const aicsId = card.getAttribute('data-id');
            const newStatus = this.getAttribute('data-status');
            
            updateStatus(aicsId, newStatus, card);
        });
    });
    
    function updateStatus(aicsId, status, cardElem) {
        fetch('update-aics-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                aics_id: aicsId,
                status: status
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateCounters();
                showToast(`Nababago ang status ng request into ${status.toUpperCase()}!`);
            } else {
                showToast("Bumagsak ang update: " + data.message, true);
                window.location.reload();
            }
        })
        .catch(err => {
            console.error("AJAX Error:", err);
            showToast("Nagkaroon ng problema sa network.", true);
            window.location.reload();
        });
    }
    
    function updateCounters() {
        columns.forEach(col => {
            const status = col.getAttribute('data-status');
            const list = col.querySelector('.card-list');
            const cardCount = list.querySelectorAll('.kanban-card').length;
            
            document.getElementById(`count-${status}`).textContent = cardCount;
            
            if (cardCount === 0 && !list.querySelector('.empty-placeholder')) {
                list.innerHTML = `
                    <div class="empty-placeholder">
                        <i class="fas fa-inbox"></i>
                        <span>Walang request</span>
                    </div>
                `;
            }
        });
    }

    function showToast(msg, isError = false) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast';
        if (isError) toast.style.background = '#8B1A1A';
        toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i> ${msg}`;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Client-side Live Filtering
    const searchInput = document.getElementById('kanbanSearch');
    const pills = document.querySelectorAll('.filter-pill');
    let activeType = 'all';

    function filterCards() {
        const query = searchInput.value.toLowerCase().trim();
        const allCards = document.querySelectorAll('.kanban-card');
        
        allCards.forEach(card => {
            const cardSearch = card.getAttribute('data-search') || '';
            const cardType = card.getAttribute('data-type') || '';
            
            const matchesSearch = query === '' || cardSearch.includes(query);
            const matchesType = activeType === 'all' || cardType === activeType;
            
            if (matchesSearch && matchesType) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterCards);

    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            pills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            activeType = this.getAttribute('data-type');
            filterCards();
        });
    });
});
</script>
</body>
</html>

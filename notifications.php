<?php
// notifications.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Mark all as read
if (isset($_GET['mark_read'])) {
    $update_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $update_stmt->execute();
    redirect('notifications.php');
}

// Delete notification
if (isset($_GET['delete'])) {
    $delete_query = "DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $_GET['delete']);
    $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $delete_stmt->execute();
    redirect('notifications.php');
}

$query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = FALSE";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bindParam(':user_id', $_SESSION['user_id']);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get stats
$total_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = :user_id";
$total_stmt = $db->prepare($total_query);
$total_stmt->bindParam(':user_id', $_SESSION['user_id']);
$total_stmt->execute();
$total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = '';
$avatar_url = '';
$user_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data) {
    $profile_picture = $user_data['profile_picture'] ?? '';
    $avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
        ? $profile_picture 
        : '';
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
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
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
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

        .user-info-sidebar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--maroon-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }
        .user-avatar-sidebar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info-sidebar h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .user-info-sidebar span {
            color: rgba(255,255,255,0.5);
            font-size: 0.65rem;
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
            font-size: 0.82rem;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 16px 20px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .stat-card .stat-icon {
            font-size: 1.2rem;
            color: var(--maroon-light);
            margin-bottom: 4px;
        }

        /* ========== NOTIFICATIONS LIST ========== */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .notification-card {
            background: var(--card-bg);
            border-radius: var(--radius-element);
            padding: 18px 22px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--maroon);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .notification-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-hover);
        }
        .notification-card.unread {
            background: rgba(33, 150, 243, 0.04);
            border-left-color: var(--color-info);
        }
        .notification-icon {
            font-size: 1.8rem;
            min-width: 50px;
            text-align: center;
            flex-shrink: 0;
        }
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        .notification-content .title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .notification-content .title-row h3 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
        }
        .notification-content .title-row .new-badge {
            background: var(--maroon);
            color: white;
            padding: 1px 10px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .notification-content p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        .notification-content .meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .notification-content .meta small {
            color: var(--text-muted);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .notification-content .meta .type-badge {
            background: var(--badge-bg);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .delete-btn {
            color: var(--text-muted);
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
            flex-shrink: 0;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .delete-btn:hover {
            color: var(--color-danger);
            background: rgba(244, 67, 54, 0.08);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-muted);
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .notification-card { flex-direction: column; padding: 14px 16px; }
            .notification-icon { text-align: left; font-size: 1.5rem; }
            .notification-content .title-row { flex-direction: column; align-items: flex-start; gap: 4px; }
            .action-buttons { flex-direction: column; width: 100%; }
            .btn-secondary { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .notification-card { padding: 12px 14px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-bell"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Integrated Service Portal</span>
            </div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="user-avatar-sidebar">
                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <span>Resident</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
            <li><a href="verify-resident.php"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li><a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-bell" style="color: var(--maroon-light); margin-right: 10px;"></i> Notifications</h1>
                <p>Stay updated with your latest notifications</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-bell"></i></div>
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope" style="color: var(--color-info);"></i></div>
                <div class="stat-number"><?php echo $unread_count; ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--color-success);"></i></div>
                <div class="stat-number"><?php echo $total_count - $unread_count; ?></div>
                <div class="stat-label">Read</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
            <div></div>
            <div class="action-buttons">
                <?php if($unread_count > 0): ?>
                <a href="?mark_read=1" class="btn-secondary">
                    <i class="fas fa-check-double"></i> Mark All as Read (<?php echo $unread_count; ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="notifications-list">
            <?php while($notif = $stmt->fetch(PDO::FETCH_ASSOC)): 
                $type_icons = [
                    'appointment' => '📅',
                    'aics' => '💰',
                    'housing' => '🏠',
                    'general' => '📢',
                    'verification' => '✅'
                ];
                $icon = $type_icons[$notif['type']] ?? '📌';
            ?>
            <div class="notification-card <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                <div class="notification-icon">
                    <?php echo $icon; ?>
                </div>
                <div class="notification-content">
                    <div class="title-row">
                        <h3><?php echo htmlspecialchars($notif['title']); ?></h3>
                        <?php if(!$notif['is_read']): ?>
                        <span class="new-badge">New</span>
                        <?php endif; ?>
                    </div>
                    <p><?php echo htmlspecialchars($notif['message']); ?></p>
                    <div class="meta">
                        <small><i class="fas fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?></small>
                        <span class="type-badge"><?php echo ucfirst($notif['type'] ?? 'General'); ?></span>
                    </div>
                </div>
                <a href="?delete=<?php echo $notif['notification_id']; ?>" class="delete-btn" onclick="return confirm('Delete this notification?')" title="Delete">
                    <i class="fas fa-trash-alt"></i>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>No Notifications</h3>
            <p>You don't have any notifications yet. We'll notify you when there are updates.</p>
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
</script>
</body>
</html>
<?php
// aics.php
require_once __DIR__ . '/config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in and verified (only for residents)
if (isLoggedIn() && !isStaff() && !isAdmin()) {
    $check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
    $verify_stmt = $db->prepare($check_verified);
    $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $verify_stmt->execute();
    $user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_verify || $user_verify['verification_status'] != 'verified') {
        redirect('verify-resident.php');
    }
}

// Get statistics for AICS
$stats = [
    'total_served' => '1,247+',
    'amount_released' => '₱5.2M',
    'approval_rate' => '98%',
    'processing_days' => '3-5'
];

// Get initials for sidebar avatar
$initials = '';
$avatar_url = '';
if (isLoggedIn()) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    foreach($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
    
    // Get profile picture
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
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AICS Assistance - One Palayan-Kalinga Hub</title>
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
            --hero-bg: #0F414A;
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
            
            --gradient-start: #7F0303;
            --gradient-end: #5a0202;
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
            --hero-bg: #0a0e17;
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
            
            --gradient-start: #1a2332;
            --gradient-end: #0a0e17;
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
        .main-content.full-width {
            margin-left: 0;
        }

        /* ========== HERO SECTION ========== */
        .aics-hero {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: var(--radius-card);
            padding: 30px 36px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        .aics-hero::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        .aics-hero .hero-content {
            position: relative;
            z-index: 2;
        }
        .aics-hero h1 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .aics-hero h1 i {
            color: var(--tan);
        }
        .aics-hero p {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            max-width: 700px;
        }

        /* ========== STATS ========== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
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
        .stat-number {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 500;
        }

        /* ========== CATEGORY GRID ========== */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .category-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .category-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .card-image {
            height: 120px;
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }
        .card-image .icon-wrapper {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        .card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        .card-badge.urgent {
            background: rgba(244, 67, 54, 0.8);
        }
        .card-badge.popular {
            background: rgba(255, 152, 0, 0.8);
        }
        .card-content {
            padding: 18px 20px 12px;
        }
        .card-content h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .card-content p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.5;
        }
        .card-footer {
            padding: 12px 20px 18px;
            display: flex;
            gap: 10px;
        }
        .btn-apply {
            flex: 2;
            background: var(--maroon);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            text-align: center;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-apply:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-requirements {
            flex: 1;
            background: transparent;
            color: var(--maroon-light);
            border: 2px solid var(--maroon-light);
            padding: 6px 12px;
            border-radius: 50px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s;
        }
        .btn-requirements:hover {
            background: var(--maroon-light);
            color: white;
            transform: translateY(-2px);
        }

        /* ========== INFO CARDS ========== */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-card {
            background: var(--card-bg);
            padding: 20px 24px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .info-card .icon-circle {
            width: 56px;
            height: 56px;
            background: rgba(127, 3, 3, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.4rem;
            color: var(--maroon-light);
        }
        .info-card h4 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .info-card p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.5;
        }

        /* ========== LOGIN PROMPT ========== */
        .login-prompt {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: var(--radius-card);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        .login-prompt .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(127, 3, 3, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2.5rem;
            color: var(--maroon-light);
        }
        .login-prompt h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .login-prompt p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        .btn-login {
            display: inline-block;
            padding: 12px 32px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-container { grid-template-columns: repeat(2, 1fr); }
            .info-cards { grid-template-columns: 1fr; }
            .category-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .stats-container { grid-template-columns: 1fr 1fr; }
            .aics-hero { padding: 20px; }
            .aics-hero h1 { font-size: 1.2rem; }
            .category-grid { grid-template-columns: 1fr; }
            .info-cards { grid-template-columns: 1fr; }
            .card-footer { flex-direction: column; }
            .btn-apply, .btn-requirements { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-container { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 14px; }
            .stat-number { font-size: 1.2rem; }
            .aics-hero { padding: 16px; }
            .aics-hero h1 { font-size: 1rem; }
            .aics-hero p { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar - ONLY SHOWN FOR LOGGED IN RESIDENTS -->
    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
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
            <li><a href="aics.php" class="active"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
        <!-- Top Bar -->
        <div class="top-bar">
            <div></div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <?php if(isLoggedIn() && !isStaff() && !isAdmin()): ?>
                <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="aics-hero">
            <div class="hero-content">
                <h1><i class="fas fa-hand-holding-heart"></i> AICS Assistance</h1>
                <p>Assistance to Individuals in Crisis Situation — Medical, Funeral, Educational, Transportation, Food, Cash Relief, and Material Assistance</p>
            </div>
        </div>

        <?php if (!isLoggedIn()): ?>
        <!-- Login Prompt -->
        <div class="login-prompt">
            <div class="icon-circle"><i class="fas fa-sign-in-alt"></i></div>
            <h3>Login to Apply for AICS</h3>
            <p>Please login to your account to apply for any of our assistance programs.</p>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login Now</a>
        </div>
        <?php elseif (isStaff() || isAdmin()): ?>
        <div class="login-prompt">
            <div class="icon-circle"><i class="fas fa-user-tie"></i></div>
            <h3>Staff Access</h3>
            <p>Staff members cannot apply for AICS as residents. Please use the staff dashboard to manage requests.</p>
            <a href="staff-dashboard.php" class="btn-login"><i class="fas fa-arrow-right"></i> Go to Staff Dashboard</a>
        </div>
        <?php else: ?>
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_served']; ?></div>
                <div class="stat-label">AICS Served</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['amount_released']; ?></div>
                <div class="stat-label">Amount Released</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approval_rate']; ?></div>
                <div class="stat-label">Approval Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['processing_days']; ?></div>
                <div class="stat-label">Days Processing</div>
            </div>
        </div>

        <!-- Category Grid -->
        <div class="category-grid">
            <!-- Medical Assistance -->
            <div class="category-card" onclick="window.location.href='aics-medical.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-hospital-user"></i></div>
                    <span class="card-badge popular">Most Requested</span>
                </div>
                <div class="card-content">
                    <h3>🏥 Medical Assistance</h3>
                    <p>Financial assistance for hospital bills, medicines, laboratory tests, and medical procedures.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-medical.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('medical')">Requirements</button>
                </div>
            </div>

            <!-- Funeral/Burial Assistance -->
            <div class="category-card" onclick="window.location.href='aics-funeral.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-cross"></i></div>
                    <span class="card-badge urgent">Urgent</span>
                </div>
                <div class="card-content">
                    <h3>⚰️ Funeral/Burial Assistance</h3>
                    <p>Financial support for funeral services and burial expenses of a loved one.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-funeral.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('funeral')">Requirements</button>
                </div>
            </div>

            <!-- Educational Assistance -->
            <div class="category-card" onclick="window.location.href='aics-educational.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-graduation-cap"></i></div>
                </div>
                <div class="card-content">
                    <h3>🎓 Educational Assistance</h3>
                    <p>Support for school supplies, tuition fees, and other educational needs.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-educational.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('educational')">Requirements</button>
                </div>
            </div>

            <!-- Transportation Assistance -->
            <div class="category-card" onclick="window.location.href='aics-transportation.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-bus"></i></div>
                </div>
                <div class="card-content">
                    <h3>🚌 Transportation Assistance</h3>
                    <p>Help with transportation expenses for medical appointments, legal matters, or other urgent needs.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-transportation.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('transportation')">Requirements</button>
                </div>
            </div>

            <!-- Food Assistance -->
            <div class="category-card" onclick="window.location.href='aics-food.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-utensils"></i></div>
                </div>
                <div class="card-content">
                    <h3>🍲 Food Assistance</h3>
                    <p>Food packs and grocery items for families in crisis situation.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-food.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('food')">Requirements</button>
                </div>
            </div>

            <!-- Cash Relief Assistance -->
            <div class="category-card" onclick="window.location.href='aics-cash-relief.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-money-bill-wave"></i></div>
                    <span class="card-badge urgent">Emergency</span>
                </div>
                <div class="card-content">
                    <h3>💰 Cash Relief Assistance</h3>
                    <p>Financial assistance for individuals and families in crisis situation. Available for medical emergencies, natural disasters, accidents, and other urgent needs.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-cash-relief.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('cashrelief')">Requirements</button>
                </div>
            </div>

            <!-- Material Assistance -->
            <div class="category-card" onclick="window.location.href='aics-material.php'">
                <div class="card-image">
                    <div class="icon-wrapper"><i class="fas fa-boxes"></i></div>
                </div>
                <div class="card-content">
                    <h3>📦 Material Assistance</h3>
                    <p>Non-food items, hygiene kits, sleeping materials, and other essential supplies.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-material.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('material')">Requirements</button>
                </div>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="info-cards">
            <div class="info-card">
                <div class="icon-circle"><i class="fas fa-question-circle"></i></div>
                <h4>What is AICS?</h4>
                <p>AICS provides medical, financial, burial, transportation, food, and other assistance to individuals and families in crisis.</p>
            </div>
            <div class="info-card">
                <div class="icon-circle"><i class="fas fa-users"></i></div>
                <h4>Who can avail?</h4>
                <p>All residents of Palayan City in urgent need of assistance due to crisis situations.</p>
            </div>
            <div class="info-card">
                <div class="icon-circle"><i class="fas fa-clipboard-list"></i></div>
                <h4>How to apply?</h4>
                <p>Register online, select your assistance type, upload requirements, and wait for assessment.</p>
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

// ========== REQUIREMENTS MODAL ==========
function showRequirements(type) {
    const requirements = {
        medical: '📋 Medical Assistance Requirements:\n\n✓ Valid ID\n✓ Barangay Certificate of Indigency\n✓ Medical Certificate/Clinical Abstract\n✓ Hospital Bill or Statement of Account\n✓ Doctor\'s Prescription (for medicine)\n✓ Laboratory Request (for laboratory)\n\nNote: For amounts over ₱10,000, additional documents required.',
        
        funeral: '📋 Funeral/Burial Assistance Requirements:\n\n✓ Valid ID\n✓ Death Certificate (Original or Certified True Copy)\n✓ Funeral Contract\n✓ Promissory Note or Certificate of Balance\n✓ Transfer Permit (for cadaver transfer)',
        
        educational: '📋 Educational Assistance Requirements:\n\n✓ Valid ID (Parent/Guardian)\n✓ Validated School ID of student\n✓ Enrollment Assessment Form / Certificate of Registration\n✓ Statement of Account (if applicable)',
        
        transportation: '📋 Transportation Assistance Requirements:\n\n✓ Valid ID\n✓ Medical Certificate or Death Certificate or Court Order\n✓ Authorization Letter (if applicable)',
        
        food: '📋 Food Assistance Requirements:\n\n✓ Valid ID\n✓ Barangay Certificate of Indigency\n✓ Written letter of request\n✓ Listahan ng mga kasapi ng pamilya',
        
        cashrelief: '📋 Cash Relief Requirements:\n\n✓ Valid ID\n✓ Barangay Certificate\n✓ Incident Report (for emergencies)\n✓ Medical Certificate (if applicable)\n✓ Police Report (if applicable)\n\nNote: Maximum amount ₱10,000 subject to assessment',
        
        material: '📋 Material Assistance Requirements:\n\n✓ General Intake Sheet (GIS)\n✓ Material Distribution Sheet (MDS)\n✓ Case Summary (for group clients)'
    };
    
    alert(requirements[type] || 'Please contact CSWDO for specific requirements.');
}
</script>
</body>
</html>
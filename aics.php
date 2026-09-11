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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stats-container { grid-template-columns: repeat(2, 1fr); }
            .info-cards { grid-template-columns: 1fr; }
            .category-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
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

        /* Custom Requirements Modal CSS */
        .custom-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .custom-modal-content {
            background-color: var(--bg-card, #ffffff);
            color: var(--text-primary, #333333);
            margin: auto;
            padding: 24px;
            border: 1px solid var(--border-color, #dddddd);
            width: 100%;
            max-width: 550px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalFadeIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .custom-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color, #dddddd);
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .custom-modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--maroon, #7F0303);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .custom-modal-close {
            color: var(--text-muted, #777777);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }
        .custom-modal-close:hover {
            color: var(--text-primary, #111111);
        }
        .custom-modal-body {
            font-size: 0.95rem;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-line;
            padding-right: 8px;
        }
        /* Custom scrollbar for modal body */
        .custom-modal-body::-webkit-scrollbar {
            width: 6px;
        }
        .custom-modal-body::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-modal-body::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.15);
            border-radius: 3px;
        }
        [data-theme="dark"] .custom-modal-body::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.15);
        }
        .custom-modal-footer {
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color, #dddddd);
            padding-top: 14px;
            margin-top: 16px;
        }
        .custom-modal-btn {
            background-color: var(--maroon, #7F0303);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .custom-modal-btn:hover {
            background-color: var(--maroon-light, #9a1515);
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar Overlay -->
    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php endif; ?>

    <!-- Sidebar - ONLY SHOWN FOR LOGGED IN RESIDENTS -->
    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Integrated Service Portal</span>
            </div>
            <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                <i class="fas fa-times"></i>
            </button>
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
        
        <?php include __DIR__ . '/includes/usersidenav.php'; ?>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                    <span>Menu</span>
                </button>
                <?php endif; ?>
                <div class="page-title">
                    <h1>AICS Programs</h1>
                    <p>Assistance to Individuals in Crisis Situations</p>
                </div>
            </div>
            <div class="top-bar-actions">
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
            <!-- Burial Assistance -->
            <div class="category-card" onclick="window.location.href='aics-funeral.php'">
                <div class="card-image" style="background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));">
                    <div class="icon-wrapper"><i class="fas fa-cross"></i></div>
                    <span class="card-badge urgent">Urgent</span>
                </div>
                <div class="card-content">
                    <h3>⚰️ Burial Assistance</h3>
                    <p>Financial support for funeral services and burial expenses of a deceased relative.</p>
                </div>
                <div class="card-footer">
                    <a href="aics-funeral.php" class="btn-apply">Apply Now <i class="fas fa-arrow-right"></i></a>
                    <button class="btn-requirements" onclick="event.stopPropagation();showRequirements('funeral')">Requirements</button>
                </div>
            </div>

            <!-- Food and Transportation Assistance -->
            <div class="category-card">
                <div class="card-image" style="background: linear-gradient(135deg, #2E7D32, #1B5E20);">
                    <div class="icon-wrapper"><i class="fas fa-bus"></i></div>
                </div>
                <div class="card-content">
                    <h3>🍲 Food & Transportation Assistance</h3>
                    <p>Help with groceries/food packs or fare/travel expenses during crisis situations.</p>
                </div>
                <div class="card-footer" style="flex-wrap: wrap; gap: 8px;">
                    <a href="aics-food.php" class="btn-apply" style="flex: 1 1 120px; font-size: 0.72rem; padding: 8px 4px;">Apply for Food</a>
                    <a href="aics-transportation.php" class="btn-apply" style="flex: 1 1 120px; font-size: 0.72rem; padding: 8px 4px; background: var(--midnight);">Apply for Travel</a>
                    <button class="btn-requirements" style="width: 100%; flex: none;" onclick="event.stopPropagation();showRequirements('food_transport')">Requirements</button>
                </div>
            </div>

            <!-- Medical / Financial Assistance -->
            <div class="category-card">
                <div class="card-image" style="background: linear-gradient(135deg, #0288D1, #01579B);">
                    <div class="icon-wrapper"><i class="fas fa-hospital-user"></i></div>
                    <span class="card-badge popular">Popular</span>
                </div>
                <div class="card-content">
                    <h3>🏥 Medical & Financial Assistance</h3>
                    <p>Financial assistance for hospital bills, doctor's prescriptions, or cash relief for emergency situations.</p>
                </div>
                <div class="card-footer" style="flex-wrap: wrap; gap: 8px;">
                    <a href="aics-medical.php" class="btn-apply" style="flex: 1 1 120px; font-size: 0.72rem; padding: 8px 4px;">Apply for Medical</a>
                    <a href="aics-cash-relief.php" class="btn-apply" style="flex: 1 1 120px; font-size: 0.72rem; padding: 8px 4px; background: var(--maroon);">Apply for Cash</a>
                    <button class="btn-requirements" style="width: 100%; flex: none;" onclick="event.stopPropagation();showRequirements('medical_financial')">Requirements</button>
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
// ========== MOBILE SIDEBAR TOGGLE ==========
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebarClose = document.getElementById('sidebarClose');

function openSidebar() {
    if (sidebar && sidebarOverlay) {
        sidebar.classList.add('open');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeSidebar() {
    if (sidebar && sidebarOverlay) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle ? themeToggle.querySelector('i') : null;
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
}
</script>

<!-- Custom Requirements Modal -->
<div id="requirementsModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h3 id="modalTitle">📋 Requirements</h3>
            <span class="custom-modal-close" onclick="closeRequirementsModal()">&times;</span>
        </div>
        <div class="custom-modal-body" id="modalBodyText">
            <!-- Requirements list will load here -->
        </div>
        <div class="custom-modal-footer">
            <button class="custom-modal-btn" onclick="closeRequirementsModal()">Close</button>
        </div>
    </div>
</div>

<script>
// ========== REQUIREMENTS MODAL ==========
function showRequirements(type) {
    const requirements = {
        funeral: {
            title: '📋 Burial Assistance Requirements',
            text: '1. Certified True Copy of relative\'s registered death certificate\n   [From: Client / City Civil Registrar’s Office]\n\n2. Original Barangay Certificate of Residency and Indigency\n   [From: Barangay Hall of the Community where the client resides]\n\n3. Photocopy of Valid ID of the client\n   [From: Client]\n\n4. Original Funeral Contract (Unpaid)\n   [From: Funeral Parlour where funeral service was acquired]\n\n5. Written letter of request\n   [From: Client]\n\n💰 Fee Summary:\n• Total Fees: None — This service is free of charge.'
        },
        
        food_transport: {
            title: '📋 Food & Transportation Assistance Requirements',
            text: '1. Original Barangay Certificate of Residency and Indigency\n   [From: Barangay where the client resides]\n\n2. Photocopy of Valid ID of the client\n   [From: Client]\n\n3. Request letter\n   [From: Client]\n\n💰 Fee Summary:\n• Total Fees: None — This service is free of charge.'
        },
        
        medical_financial: {
            title: '📋 Medical / Financial Assistance Requirements',
            text: '1. Original Barangay Certificate of Residency and Indigency\n   [From: Barangay where the client resides]\n\n2. Photocopy of Valid ID of the client\n   [From: Client]\n\n3. Doctor’s prescription (for medicines or laboratory request)\n   [From: Attending Physician]\n\n4. Medical Abstract / Medical Certificate\n   [From: Attending Physician]\n\n5. Hospital Bill (for payment of hospital bill)\n   [From: Hospital where the client was admitted]\n\n6. Request letter\n   [From: Client]\n\n💰 Fee Summary:\n• Total Fees: None — This service is free of charge.'
        }
    };
    
    const req = requirements[type];
    if (req) {
        document.getElementById('modalTitle').innerText = req.title;
        document.getElementById('modalBodyText').innerText = req.text;
        
        const modal = document.getElementById('requirementsModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Disable page scroll when modal is open
    }
}

function closeRequirementsModal() {
    const modal = document.getElementById('requirementsModal');
    modal.style.display = 'none';
    document.body.style.overflow = ''; // Enable page scroll when modal is closed
}

// Close when clicking outside modal content
window.addEventListener('click', function(event) {
    const modal = document.getElementById('requirementsModal');
    if (event.target == modal) {
        closeRequirementsModal();
    }
});
</script>
</body>
</html>

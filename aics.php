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
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== HERO SECTION ========== */
        .aics-hero {
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 55%, #5A0202 100%) !important;
            border-radius: 24px !important;
            padding: 28px 32px !important;
            margin-bottom: 24px !important;
            position: relative !important;
            overflow: hidden !important;
            box-shadow: 0 10px 30px rgba(127, 3, 3, 0.25) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
        }
        .aics-hero::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }
        .aics-hero .hero-content {
            position: relative;
            z-index: 2;
        }
        .aics-hero h1 {
            color: #FFFFFF !important;
            font-size: 1.5rem !important;
            font-weight: 800 !important;
            margin-bottom: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }
        .aics-hero h1 i {
            color: #FDE68A !important;
        }
        .aics-hero p {
            color: rgba(255, 255, 255, 0.92) !important;
            font-size: 0.88rem !important;
            max-width: 720px !important;
            line-height: 1.5 !important;
        }

        /* ========== CATEGORY GRID (ENLARGED + FADED COLOR ANIMATION) ========== */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 28px;
            margin-bottom: 40px;
        }

        .category-card {
            background: var(--card-bg, #FFFFFF);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color, rgba(0, 0, 0, 0.07));
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .category-card.card-burial:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 50px rgba(127, 3, 3, 0.22);
            border-color: rgba(127, 3, 3, 0.35);
        }

        .category-card.card-food-transport:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 50px rgba(46, 125, 50, 0.22);
            border-color: rgba(46, 125, 50, 0.35);
        }

        .category-card.card-medical-financial:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 50px rgba(2, 136, 209, 0.22);
            border-color: rgba(2, 136, 209, 0.35);
        }

        /* Animated Faded Gradient Header */
        .card-image {
            height: 180px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
            background-size: 250% 250%;
            animation: aicsFadedShift 8s ease infinite alternate;
        }

        /* Card 1: Faded Velvet Maroon */
        .card-image.bg-burial {
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 35%, #5A0202 70%, #B91C1C 100%);
        }

        /* Card 2: Faded Emerald Sage */
        .card-image.bg-food-transport {
            background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 35%, #065F46 70%, #047857 100%);
        }

        /* Card 3: Faded Sapphire Ocean */
        .card-image.bg-medical-financial {
            background: linear-gradient(135deg, #01579B 0%, #0284C7 35%, #0F414A 70%, #1E3A8A 100%);
        }

        @keyframes aicsFadedShift {
            0% {
                background-position: 0% 40%;
                filter: brightness(0.98);
            }
            50% {
                background-position: 100% 60%;
                filter: brightness(1.06);
            }
            100% {
                background-position: 0% 40%;
                filter: brightness(0.98);
            }
        }

        /* Ambient Faded Color Pulse Orb */
        .card-faded-orb {
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
            animation: aicsOrbPulse 6s ease-in-out infinite alternate;
        }

        @keyframes aicsOrbPulse {
            0% {
                transform: scale(0.9) translate(0, 0);
                opacity: 0.6;
            }
            100% {
                transform: scale(1.25) translate(-15px, 15px);
                opacity: 1;
            }
        }

        .card-image .icon-wrapper {
            width: 82px;
            height: 82px;
            background: rgba(255, 255, 255, 0.18);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            color: #FFFFFF;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 2;
        }

        .category-card:hover .icon-wrapper {
            transform: scale(1.1) rotate(-3deg);
            background: rgba(255, 255, 255, 0.28);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
        }

        .card-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            padding: 5px 16px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 3;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        }

        .card-badge.urgent {
            background: #FEE2E2 !important;
            color: #991B1B !important;
            border: 1px solid rgba(239, 68, 68, 0.4) !important;
        }

        .card-badge.popular {
            background: #FEF3C7 !important;
            color: #92400E !important;
            border: 1px solid rgba(245, 158, 11, 0.4) !important;
        }

        .card-badge.essential {
            background: #DCFCE7 !important;
            color: #065F46 !important;
            border: 1px solid rgba(16, 185, 129, 0.4) !important;
        }

        .card-content {
            padding: 26px 28px 16px;
            flex: 1;
        }

        .card-content h3 {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .card-content p {
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .card-benefit-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .benefit-tag {
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            background: var(--ref-canvas, #F1F5F9);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .benefit-tag i {
            color: #10B981;
            font-size: 0.7rem;
        }

        .card-footer {
            padding: 16px 28px 28px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-apply-main {
            background: #7F0303;
            color: #FFFFFF !important;
            padding: 12px 22px;
            border-radius: 50px;
            text-align: center;
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 700;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.25);
        }

        .btn-apply-main:hover {
            background: #9A1515;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(127, 3, 3, 0.35);
        }

        .action-split-btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-split-btns a {
            flex: 1 1 140px;
            padding: 11px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: all 0.25s ease;
        }

        .btn-green-theme {
            background: #2E7D32;
            color: #FFFFFF !important;
            box-shadow: 0 4px 14px rgba(46, 125, 50, 0.25);
        }
        .btn-green-theme:hover {
            background: #1B5E20;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(46, 125, 50, 0.35);
        }

        .btn-forest-theme {
            background: #0F414A;
            color: #FFFFFF !important;
            box-shadow: 0 4px 14px rgba(15, 65, 74, 0.25);
        }
        .btn-forest-theme:hover {
            background: #082930;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(15, 65, 74, 0.35);
        }

        .btn-blue-theme {
            background: #0288D1;
            color: #FFFFFF !important;
            box-shadow: 0 4px 14px rgba(2, 136, 209, 0.25);
        }
        .btn-blue-theme:hover {
            background: #01579B;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(2, 136, 209, 0.35);
        }

        .btn-requirements-pill {
            background: transparent;
            color: var(--text-muted);
            border: 1.5px solid var(--border-color);
            padding: 9px 18px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
        }

        .btn-requirements-pill:hover {
            border-color: #7F0303;
            color: #7F0303;
            background: rgba(127, 3, 3, 0.04);
        }

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
            
            .info-cards { grid-template-columns: 1fr; }
            .category-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
            
            .aics-hero { padding: 20px; }
            .aics-hero h1 { font-size: 1.2rem; }
            .category-grid { grid-template-columns: 1fr; }
            .info-cards { grid-template-columns: 1fr; }
            .card-footer { flex-direction: column; }
            .btn-apply, .btn-requirements { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            
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
    <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
        <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
        <?php include __DIR__ . '/includes/portal-header.php'; ?>

        <!-- Universal Top Hero Banner -->
        <?php
        $banner_title = 'AICS Crisis Assistance';
        $banner_subtitle = 'Medical, Funeral, Educational, Transportation, Food Aid, and Cash Relief Programs';
        $banner_icon = 'fa-hand-holding-heart';
        $banner_actions = [
            ['label' => 'Start Application', 'url' => 'apply-aics.php', 'icon' => 'fa-file-signature', 'type' => 'primary'],
            ['label' => 'Track Request', 'url' => 'track-request.php?type=aics', 'icon' => 'fa-search-location', 'type' => 'secondary']
        ];
        include __DIR__ . '/includes/portal-banner.php';
        ?>

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
        <!-- Category Grid (Upgraded Spacious UI with Faded Color Animation) -->
        <div class="category-grid">
            <!-- Burial / Funeral Assistance -->
            <div class="category-card card-burial">
                <div class="card-image bg-burial">
                    <div class="card-faded-orb"></div>
                    <div class="icon-wrapper"><i class="fas fa-cross"></i></div>
                    <span class="card-badge urgent"><i class="fas fa-bolt"></i> Urgent Service</span>
                </div>
                <div class="card-content">
                    <h3>⚰️ Burial Assistance</h3>
                    <p>Financial and welfare support for funeral services, casket provision, and burial expenses of a deceased family member or relative.</p>
                    <div class="card-benefit-tags">
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Funeral Service</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Casket & Burial Support</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Immediate Release</span>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="aics-funeral.php" class="btn-apply-main">
                        <span>Apply for Burial Assistance</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <button class="btn-requirements-pill" onclick="event.stopPropagation();showRequirements('funeral')">
                        <i class="fas fa-clipboard-list"></i> View Requirements
                    </button>
                </div>
            </div>

            <!-- Food and Transportation Assistance -->
            <div class="category-card card-food-transport">
                <div class="card-image bg-food-transport">
                    <div class="card-faded-orb"></div>
                    <div class="icon-wrapper"><i class="fas fa-utensils"></i></div>
                    <span class="card-badge essential"><i class="fas fa-leaf"></i> Essential Aid</span>
                </div>
                <div class="card-content">
                    <h3>🍲 Food & Transportation Aid</h3>
                    <p>Emergency food packs, essential family nutritional assistance, or travel and fare subsidies for stranded citizens or medical referrals.</p>
                    <div class="card-benefit-tags">
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Emergency Grocery Packs</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Rice & Nutritional Support</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Fare & Inter-Town Subsidy</span>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="action-split-btns">
                        <a href="aics-food.php" class="btn-green-theme">
                            <i class="fas fa-utensils"></i> Apply for Food
                        </a>
                        <a href="aics-transportation.php" class="btn-forest-theme">
                            <i class="fas fa-bus"></i> Apply for Travel
                        </a>
                    </div>
                    <button class="btn-requirements-pill" onclick="event.stopPropagation();showRequirements('food_transport')">
                        <i class="fas fa-clipboard-list"></i> View Requirements
                    </button>
                </div>
            </div>

            <!-- Medical / Financial Cash Relief -->
            <div class="category-card card-medical-financial">
                <div class="card-image bg-medical-financial">
                    <div class="card-faded-orb"></div>
                    <div class="icon-wrapper"><i class="fas fa-hospital-user"></i></div>
                    <span class="card-badge popular"><i class="fas fa-star"></i> Most Requested</span>
                </div>
                <div class="card-content">
                    <h3>🏥 Medical & Financial Assistance</h3>
                    <p>Financial grants for hospitalization expenses, prescribed emergency medicines, laboratory workups, or direct crisis financial support.</p>
                    <div class="card-benefit-tags">
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Hospital Bills</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Doctor Prescriptions</span>
                        <span class="benefit-tag"><i class="fas fa-check-circle"></i> Crisis Cash Grants</span>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="action-split-btns">
                        <a href="aics-medical.php" class="btn-blue-theme">
                            <i class="fas fa-heartbeat"></i> Medical Assistance
                        </a>
                        <a href="aics-cash-relief.php" class="btn-apply-main" style="flex: 1 1 140px; padding: 11px 16px; font-size: 0.82rem;">
                            <i class="fas fa-hand-holding-usd"></i> Cash Relief
                        </a>
                    </div>
                    <button class="btn-requirements-pill" onclick="event.stopPropagation();showRequirements('medical_financial')">
                        <i class="fas fa-clipboard-list"></i> View Requirements
                    </button>
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

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

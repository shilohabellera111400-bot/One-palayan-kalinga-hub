<?php
// senior.php - Senior Citizen Services & Welfare Portal
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should use staff dashboard
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified resident
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Fetch senior citizen info if available
$check_senior = $db->prepare("SELECT * FROM residents WHERE user_id = :id");
$check_senior->bindParam(':id', $_SESSION['user_id']);
$check_senior->execute();
$resident_info = $check_senior->fetch(PDO::FETCH_ASSOC);

// User initials & profile avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$profile_picture = '';
$avatar_url = '';
$user_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data) {
    $profile_picture = $user_data['profile_picture'] ?? '';
    $avatar_url = !empty($profile_picture) && file_exists($profile_picture) ? $profile_picture : '';
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Citizen Portal - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== KEYFRAME ANIMATIONS ========== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(2, 136, 209, 0.2); }
            50% { box-shadow: 0 0 25px rgba(2, 136, 209, 0.4); }
        }
        @keyframes floatAnimation {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }

        .animate-fade-in { animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        .animate-delay-1 { animation-delay: 0.15s; }
        .animate-delay-2 { animation-delay: 0.3s; }
        .animate-delay-3 { animation-delay: 0.45s; }

        /* ========== HERO HEADER ========== */
        .senior-hero {
            background: linear-gradient(135deg, #01579B 0%, #0288D1 50%, #004D40 100%);
            border-radius: var(--radius-card);
            padding: 35px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }
        .senior-hero::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .senior-hero h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .senior-hero p {
            font-size: 0.95rem;
            opacity: 0.92;
            max-width: 700px;
            line-height: 1.6;
        }

        /* ========== STATS ROW ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: #0288D1;
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(2, 136, 209, 0.12);
            color: #0288D1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .stat-content h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .stat-content p {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* ========== TAB NAVIGATION ========== */
        .tabs-header {
            display: flex;
            gap: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 25px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .tab-btn {
            padding: 12px 22px;
            border: none;
            background: none;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover { color: #0288D1; }
        .tab-btn.active {
            color: #0288D1;
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #0288D1;
            border-radius: 3px 3px 0 0;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeInUp 0.4s ease forwards; }

        /* ========== PICTURE CONTAINERS GALLERY ========== */
        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .gallery-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .gallery-header h2 i { color: #0288D1; }

        .picture-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 22px;
            margin-bottom: 35px;
        }
        .picture-card {
            background: var(--bg-card);
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: pointer;
        }
        .picture-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: var(--shadow-md);
            border-color: #0288D1;
        }
        .picture-wrapper {
            width: 100%;
            height: 180px;
            background: #102a45;
            position: relative;
            overflow: hidden;
        }
        .picture-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .picture-card:hover .picture-wrapper img {
            transform: scale(1.08);
        }
        .picture-overlay {
            position: absolute;
            inset: 0;
            background: rgba(1, 87, 155, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .picture-card:hover .picture-overlay { opacity: 1; }
        .picture-caption {
            padding: 14px 16px;
        }
        .picture-caption h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .picture-caption p {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        /* Add Picture Container Placeholder Card */
        .add-picture-card {
            background: var(--bg-card);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-element);
            min-height: 240px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .add-picture-card:hover {
            border-color: #0288D1;
            background: rgba(2, 136, 209, 0.04);
            transform: translateY(-4px);
        }
        .add-icon-circle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: rgba(2, 136, 209, 0.1);
            color: #0288D1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }
        .add-picture-card:hover .add-icon-circle { transform: scale(1.15) rotate(90deg); }
        .add-picture-card h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .add-picture-card p {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        /* Lightbox Modal */
        .lightbox-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 14, 23, 0.85);
            backdrop-filter: blur(6px);
            z-index: 3000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .lightbox-modal.show {
            opacity: 1;
            visibility: visible;
        }
        .lightbox-content {
            max-width: 800px;
            width: 100%;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        .lightbox-image-box {
            max-height: 480px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .lightbox-image-box img {
            max-width: 100%;
            max-height: 480px;
            object-fit: contain;
        }
        .lightbox-details {
            padding: 20px 24px;
        }
        .lightbox-details h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .lightbox-details p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* Action Buttons & Cards */
        .service-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
        }
        .service-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 25px;
            transition: all 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .service-card h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .service-card h3 i { color: #0288D1; }
        .service-card p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: #0288D1;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-action:hover {
            background: #01579B;
            transform: translateY(-2px);
        }

        /* Form Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 14, 23, 0.7);
            backdrop-filter: blur(4px);
            z-index: 2500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-box {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 30px;
            max-width: 520px;
            width: 100%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }
        .modal-box h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar Navigation -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hands-holding-child"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga</h2>
                    <span>Senior Portal</span>
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
                    <span>Senior Citizen Resident</span>
                </div>
            </div>

            <?php include __DIR__ . '/includes/usersidenav.php'; ?>

            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="main-content">
            <!-- Top Action Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-person-cane" style="color: #0288D1; margin-right: 10px;"></i> Senior Citizen Welfare Services</h1>
                        <p>Dedicated assistance, social pension tracking, and privileges for Palayan City Senior Citizens.</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon" title="Dashboard"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

            <!-- Hero Header -->
            <div class="senior-hero animate-fade-in">
                <span class="hero-badge"><i class="fas fa-shield-heart"></i> OSCA Palayan City Office</span>
                <h1>👴👵 Senior Citizen Privileges & Support</h1>
                <p>Welcome to the dedicated portal for Palayan City Senior Citizens. Access social pension schedules, request medical booklet assistance, view registered activities, and manage your welfare privileges effortlessly.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-fade-in animate-delay-1">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-id-card"></i></div>
                    <div class="stat-content">
                        <h3>OSCA Registered</h3>
                        <p>Verified Senior ID Status</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="stat-content">
                        <h3>Social Pension</h3>
                        <p>Quarterly Benefit Distribution</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-content">
                        <h3>20% Privilege</h3>
                        <p>Medicine & Meal Discounts</p>
                    </div>
                </div>
            </div>

            <!-- PICTURE CONTAINERS GALLERY -->
            <div class="gallery-header animate-fade-in animate-delay-2">
                <h2><i class="fas fa-images"></i> Senior Citizen Photo Gallery & Activities</h2>
                <button class="btn-action" style="padding: 8px 16px; font-size: 0.8rem;" onclick="triggerAddPictureModal()">
                    <i class="fas fa-plus-circle"></i> Add Picture Container
                </button>
            </div>

            <div class="picture-gallery-grid animate-fade-in animate-delay-2">
                <!-- Picture Container 1 -->
                <div class="picture-card" onclick="openLightbox('images/senior1.png', 'Senior Citizen ID Release & Registration', 'OSCA Palayan City distribution event for senior citizen identification cards.')">
                    <div class="picture-wrapper">
                        <img src="images/senior1.png" alt="Senior ID Release" onerror="this.src='https://placehold.co/600x400/01579B/ffffff?text=Senior+ID+Release'">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>OSCA Senior ID Distribution</h4>
                        <p>Palayan City Government Center</p>
                    </div>
                </div>

                <!-- Picture Container 2 -->
                <div class="picture-card" onclick="openLightbox('images/senior2.png', 'Quarterly Social Pension Distribution', 'Distribution of social pension stipends to eligible senior citizens.')">
                    <div class="picture-wrapper">
                        <img src="images/senior2.png" alt="Pension Distribution" onerror="this.src='https://placehold.co/600x400/0288D1/ffffff?text=Pension+Distribution'">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>Social Pension Distribution</h4>
                        <p>CSWDO Palayan City Hall</p>
                    </div>
                </div>

                <!-- Picture Container 3 -->
                <div class="picture-card" onclick="openLightbox('images/senior3.png', 'Senior Health & Wellness Checkup', 'Free medical checkups and prescription medicine distribution.')">
                    <div class="picture-wrapper">
                        <img src="images/senior3.png" alt="Senior Medical Checkup" onerror="this.src='https://placehold.co/600x400/004D40/ffffff?text=Health+%26+Wellness'">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>Health & Medical Mission</h4>
                        <p>Barangay Health Center</p>
                    </div>
                </div>

                <!-- Picture Container 4 -->
                <div class="picture-card" onclick="openLightbox('images/senior4.png', 'Elderly Week Celebration', 'Annual Elderly Week gathering and recreational activity for seniors.')">
                    <div class="picture-wrapper">
                        <img src="images/senior4.png" alt="Elderly Week Celebration" onerror="this.src='https://placehold.co/600x400/7F0303/ffffff?text=Elderly+Week'">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>Elderly Week Celebration</h4>
                        <p>Palayan City Gym</p>
                    </div>
                </div>

                <!-- Add Picture Container Placeholder -->
                <div class="add-picture-card" onclick="triggerAddPictureModal()">
                    <div class="add-icon-circle"><i class="fas fa-plus"></i></div>
                    <h4>Add New Picture Container</h4>
                    <p>Click here to drop or upload a new photo container to your gallery.</p>
                </div>
            </div>

            <!-- TABS SECTION -->
            <div class="tabs-header animate-fade-in animate-delay-3">
                <button class="tab-btn active" onclick="switchTab('tab-pension', this)"><i class="fas fa-wallet"></i> Social Pension</button>
                <button class="tab-btn" onclick="switchTab('tab-discounts', this)"><i class="fas fa-tags"></i> Privileges & Discounts</button>
                <button class="tab-btn" onclick="switchTab('tab-assistance', this)"><i class="fas fa-hand-holding-medical"></i> Request Assistance</button>
            </div>

            <!-- TAB 1: SOCIAL PENSION -->
            <div id="tab-pension" class="tab-content active">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-calendar-check"></i> Quarterly Social Pension</h3>
                        <p>Eligible indigent senior citizens receive a monthly stipend distributed every quarter. Check your payout schedule and requirement list.</p>
                        <button class="btn-action" onclick="openModal('seniorRequestModal')"><i class="fas fa-paper-plane"></i> Apply for Social Pension</button>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-book-medical"></i> Purchase Booklet Request</h3>
                        <p>Need a replacement or new Senior Citizen Medicine / Grocery Purchase Booklet? Request an official OSCA booklet online.</p>
                        <button class="btn-action" onclick="openModal('seniorRequestModal')"><i class="fas fa-book"></i> Request Booklet</button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PRIVILEGES & DISCOUNTS -->
            <div id="tab-discounts" class="tab-content">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-prescription-bottle-medical"></i> 20% Medicine Discount</h3>
                        <p>Under RA 9994 (Expanded Senior Citizens Act), seniors enjoy a 20% discount and VAT exemption on generic and branded prescription drugs.</p>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-bus-simple"></i> Fare & Transportation Benefit</h3>
                        <p>Enjoy a 20% fare discount on public land, air, and sea transportation nationwide upon presentation of your OSCA ID.</p>
                    </div>
                </div>
            </div>

            <!-- TAB 3: REQUEST ASSISTANCE -->
            <div id="tab-assistance" class="tab-content">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-file-medical"></i> Medical & Health Aid</h3>
                        <p>Submit requests for financial aid for hospital bills, maintenance medicines, or medical equipment assistance.</p>
                        <a href="appointments.php" class="btn-action"><i class="fas fa-calendar-plus"></i> Book CSWDO Visit</a>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-cross"></i> Burial & Funeral Assistance</h3>
                        <p>Immediate financial relief and funeral assistance support for deceased senior citizen family members.</p>
                        <a href="aics-funeral.php" class="btn-action"><i class="fas fa-arrow-right"></i> Apply for Burial Aid</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX MODAL -->
    <div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox(event)">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <div class="lightbox-image-box">
                <img src="" id="lightboxImg" alt="Enlarged Photo">
            </div>
            <div class="lightbox-details">
                <h3 id="lightboxTitle">Photo Title</h3>
                <p id="lightboxDesc">Photo description will appear here.</p>
            </div>
        </div>
    </div>

    <!-- ASSISTANCE REQUEST MODAL -->
    <div class="modal-overlay" id="seniorRequestModal">
        <div class="modal-box">
            <h2><i class="fas fa-hand-holding-heart" style="color: #0288D1;"></i> Senior Citizen Request</h2>
            <form onsubmit="submitSeniorRequest(event)">
                <div class="form-group">
                    <label>Request Type</label>
                    <select required>
                        <option value="pension">Social Pension Inquiry / Application</option>
                        <option value="booklet">OSCA Medicine / Grocery Booklet</option>
                        <option value="medical">Medical / Prescription Aid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Details / Purpose</label>
                    <textarea rows="3" required placeholder="Describe your request or inquiry details..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-action" style="background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeModal('seniorRequestModal')">Cancel</button>
                    <button type="submit" class="btn-action"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD PICTURE MODAL -->
    <div class="modal-overlay" id="addPictureModal">
        <div class="modal-box">
            <h2><i class="fas fa-image" style="color: #0288D1;"></i> Add New Picture Container</h2>
            <form onsubmit="saveNewPictureContainer(event)">
                <div class="form-group">
                    <label>Picture Title</label>
                    <input type="text" id="picTitle" required placeholder="e.g. Senior Wellness Program 2026">
                </div>
                <div class="form-group">
                    <label>Sub-Location / Caption</label>
                    <input type="text" id="picCaption" placeholder="e.g. Barangay Hall Gymnasium">
                </div>
                <div class="form-group">
                    <label>Select Photo File</label>
                    <input type="file" id="picFile" accept="image/*" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-action" style="background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeModal('addPictureModal')">Cancel</button>
                    <button type="submit" class="btn-action"><i class="fas fa-plus-circle"></i> Add Container</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabId, btnElement) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            btnElement.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // Lightbox
        function openLightbox(imgSrc, title, desc) {
            document.getElementById('lightboxImg').src = imgSrc;
            document.getElementById('lightboxTitle').textContent = title;
            document.getElementById('lightboxDesc').textContent = desc;
            document.getElementById('lightboxModal').classList.add('show');
        }
        function closeLightbox(event) {
            document.getElementById('lightboxModal').classList.remove('show');
        }

        // Modals
        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }
        function triggerAddPictureModal() { openModal('addPictureModal'); }

        function submitSeniorRequest(e) {
            e.preventDefault();
            alert('Your Senior Citizen request has been submitted successfully to CSWDO Palayan City!');
            closeModal('seniorRequestModal');
        }

        function saveNewPictureContainer(e) {
            e.preventDefault();
            const title = document.getElementById('picTitle').value;
            const caption = document.getElementById('picCaption').value;
            const fileInput = document.getElementById('picFile');

            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const grid = document.querySelector('.picture-gallery-grid');
                    const addCard = document.querySelector('.add-picture-card');
                    
                    const newCard = document.createElement('div');
                    newCard.className = 'picture-card animate-fade-in';
                    newCard.onclick = () => openLightbox(evt.target.result, title, caption);
                    newCard.innerHTML = `
                        <div class="picture-wrapper">
                            <img src="${evt.target.result}" alt="${title}">
                            <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                        </div>
                        <div class="picture-caption">
                            <h4>${title}</h4>
                            <p>${caption || 'Senior Citizen Portal Upload'}</p>
                        </div>
                    `;
                    grid.insertBefore(newCard, addCard);
                    alert('New picture container added successfully!');
                    closeModal('addPictureModal');
                };
                reader.readAsDataURL(fileInput.files[0]);
            }
        }

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            themeToggle.querySelector('i').className = isDark ? 'fas fa-moon' : 'fas fa-sun';
            document.cookie = `dark_mode=${newTheme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
        });
    </script>
</body>
</html>

<?php
// pwd.php - Persons with Disability (PWD) Services & Support Portal
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

// Fetch PWD resident info if available
$check_pwd = $db->prepare("SELECT * FROM residents WHERE user_id = :id");
$check_pwd->bindParam(':id', $_SESSION['user_id']);
$check_pwd->execute();
$resident_info = $check_pwd->fetch(PDO::FETCH_ASSOC);

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
    <title>PWD Services & Welfare Portal - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== ANIMATIONS ========== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in { animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards; }
        .animate-delay-1 { animation-delay: 0.15s; }
        .animate-delay-2 { animation-delay: 0.3s; }
        .animate-delay-3 { animation-delay: 0.45s; }

        /* ========== HERO HEADER ========== */
        .pwd-hero {
            background: linear-gradient(135deg, #1A237E 0%, #283593 50%, #0D47A1 100%);
            border-radius: var(--radius-card);
            padding: 35px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }
        .pwd-hero::after {
            content: '';
            position: absolute;
            bottom: -50%;
            right: -10%;
            width: 380px;
            height: 380px;
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
        .pwd-hero h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pwd-hero p {
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
            border-color: #283593;
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(40, 53, 147, 0.12);
            color: #283593;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        [data-theme="dark"] .stat-icon { color: #5C6BC0; background: rgba(92, 107, 192, 0.18); }
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
        .tab-btn:hover { color: #283593; }
        .tab-btn.active { color: #283593; }
        [data-theme="dark"] .tab-btn.active { color: #5C6BC0; }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #283593;
            border-radius: 3px 3px 0 0;
        }
        [data-theme="dark"] .tab-btn.active::after { background: #5C6BC0; }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeInUp 0.4s ease forwards; }

        /* ========== PICTURE GALLERY GRID ========== */
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
        .gallery-header h2 i { color: #283593; }

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
            border-color: #283593;
        }
        .picture-wrapper {
            width: 100%;
            height: 180px;
            background: #1a237e;
            position: relative;
            overflow: hidden;
        }
        .picture-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .picture-card:hover .picture-wrapper img { transform: scale(1.08); }
        .picture-overlay {
            position: absolute;
            inset: 0;
            background: rgba(26, 35, 126, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .picture-card:hover .picture-overlay { opacity: 1; }
        .picture-caption { padding: 14px 16px; }
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
            border-color: #283593;
            background: rgba(40, 53, 147, 0.04);
            transform: translateY(-4px);
        }
        .add-icon-circle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: rgba(40, 53, 147, 0.1);
            color: #283593;
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
        .lightbox-modal.show { opacity: 1; visibility: visible; }
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
        .lightbox-details { padding: 20px 24px; }
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

        /* Action Cards */
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
        .service-card h3 i { color: #283593; }
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
            background: #283593;
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
            background: #1A237E;
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
                <div class="logo-icon"><i class="fas fa-wheelchair"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga</h2>
                    <span>PWD Portal</span>
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
                    <span>PWD Resident</span>
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
                        <h1><i class="fas fa-wheelchair" style="color: #283593; margin-right: 10px;"></i> PWD Welfare & Support Portal</h1>
                        <p>Dedicated assistance, assistive device applications, and privileges for PWD residents of Palayan City.</p>
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
            <div class="pwd-hero animate-fade-in">
                <span class="hero-badge"><i class="fas fa-shield-heart"></i> PWD Affairs Office Palayan City</span>
                <h1>♿ Persons with Disability (PWD) Services</h1>
                <p>Access specialized disability welfare programs, apply for mobility devices (wheelchairs, hearing aids, crutches), track ID renewal status, and request medical subsidies.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid animate-fade-in animate-delay-1">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-id-card"></i></div>
                    <div class="stat-content">
                        <h3>Registered PWD</h3>
                        <p>Verified PWD ID Status</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wheelchair"></i></div>
                    <div class="stat-content">
                        <h3>Assistive Devices</h3>
                        <p>Wheelchairs & Mobility Grants</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-content">
                        <h3>20% Privilege</h3>
                        <p>Medicine & VAT Exemption</p>
                    </div>
                </div>
            </div>

            <!-- PICTURE CONTAINERS GALLERY -->
            <div class="gallery-header animate-fade-in animate-delay-2">
                <h2><i class="fas fa-images"></i> PWD Support Community Photo Gallery</h2>
                <button class="btn-action" style="padding: 8px 16px; font-size: 0.8rem;" onclick="triggerAddPictureModal()">
                    <i class="fas fa-plus-circle"></i> Add Picture Container
                </button>
            </div>

            <div class="picture-gallery-grid animate-fade-in animate-delay-2">
                <!-- Picture Container 1 -->
                <div class="picture-card" onclick="openLightbox('images/cswdo-pwd-support.jpg', 'Wheelchair & Assistive Device Turnover', 'CSWDO Palayan City turnover ceremony for wheelchairs and mobility devices.')">
                    <div class="picture-wrapper">
                        <img src="images/cswdo-pwd-support.jpg" alt="PWD Support Turnover" onerror="this.src='https://placehold.co/600x400/1A237E/ffffff?text=PWD+Device+Turnover'">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>Wheelchair & Assistive Device Turnover</h4>
                        <p>Palayan City Government Center</p>
                    </div>
                </div>

                <!-- Picture Container 2 -->
                <div class="picture-card" onclick="openLightbox('https://placehold.co/600x400/283593/ffffff?text=PWD+ID+Registration', 'PWD ID Renewal & Registration Drive', 'On-site registration and ID renewal for PWD residents.')">
                    <div class="picture-wrapper">
                        <img src="https://placehold.co/600x400/283593/ffffff?text=PWD+ID+Registration" alt="PWD ID Registration">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>PWD ID Registration & Renewal Drive</h4>
                        <p>CSWDO Palayan City</p>
                    </div>
                </div>

                <!-- Picture Container 3 -->
                <div class="picture-card" onclick="openLightbox('https://placehold.co/600x400/0D47A1/ffffff?text=Livelihood+Training', 'PWD Inclusive Livelihood Skills Training', 'Special skills and livelihood workshop for PWD community members.')">
                    <div class="picture-wrapper">
                        <img src="https://placehold.co/600x400/0D47A1/ffffff?text=Livelihood+Training" alt="Livelihood Training">
                        <div class="picture-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <div class="picture-caption">
                        <h4>Inclusive Livelihood Workshop</h4>
                        <p>Palayan Training Center</p>
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
                <button class="tab-btn active" onclick="switchTab('tab-devices', this)"><i class="fas fa-wheelchair"></i> Assistive Devices</button>
                <button class="tab-btn" onclick="switchTab('tab-privileges', this)"><i class="fas fa-tags"></i> PWD Discounts</button>
                <button class="tab-btn" onclick="switchTab('tab-medical', this)"><i class="fas fa-hand-holding-medical"></i> Medical Assistance</button>
            </div>

            <!-- TAB 1: ASSISTIVE DEVICES -->
            <div id="tab-devices" class="tab-content active">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-wheelchair"></i> Wheelchair Grant Application</h3>
                        <p>Apply for a free manual or specialized wheelchair issued by CSWDO Palayan City for qualified PWD residents.</p>
                        <button class="btn-action" onclick="openModal('pwdRequestModal')"><i class="fas fa-paper-plane"></i> Apply for Wheelchair</button>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-ear-listen"></i> Hearing Aid & Mobility Canes</h3>
                        <p>Request assistive hearing aids, white canes, or walking crutches for visual, hearing, or physical impairments.</p>
                        <button class="btn-action" onclick="openModal('pwdRequestModal')"><i class="fas fa-paper-plane"></i> Request Assistive Device</button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DISCOUNTS -->
            <div id="tab-privileges" class="tab-content">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-prescription-bottle-medical"></i> 20% Medicine & Hospital Discount</h3>
                        <p>Under RA 10754, PWD cardholders receive a 20% discount and 12% VAT exemption on medicines, doctor fees, and laboratory tests.</p>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-utensils"></i> Dining & Transport Benefits</h3>
                        <p>Enjoy statutory 20% discounts on restaurants, public transport, railways, domestic flights, and expressways.</p>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MEDICAL ASSISTANCE -->
            <div id="tab-medical" class="tab-content">
                <div class="service-action-grid">
                    <div class="service-card">
                        <h3><i class="fas fa-hospital-user"></i> Medical & Cash Aid</h3>
                        <p>Financial assistance for therapy, rehabilitation sessions, or maintenance prescriptions under the AICS program.</p>
                        <a href="aics-medical.php" class="btn-action"><i class="fas fa-arrow-right"></i> Apply for Medical AICS</a>
                    </div>
                    <div class="service-card">
                        <h3><i class="fas fa-calendar-plus"></i> Schedule CSWDO Appointment</h3>
                        <p>Book a personal consultation visit with our Disability Affairs Officer at CSWDO Palayan City.</p>
                        <a href="appointments.php" class="btn-action"><i class="fas fa-calendar-check"></i> Book Appointment</a>
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
    <div class="modal-overlay" id="pwdRequestModal">
        <div class="modal-box">
            <h2><i class="fas fa-wheelchair" style="color: #283593;"></i> PWD Assistance Request</h2>
            <form onsubmit="submitPwdRequest(event)">
                <div class="form-group">
                    <label>Assistance / Device Type</label>
                    <select required>
                        <option value="wheelchair">Standard Wheelchair</option>
                        <option value="crutches">Walking Crutches / Cane</option>
                        <option value="hearing_aid">Hearing Aid Device</option>
                        <option value="medical_aid">Medical / Prescription Aid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Details / Medical Condition</label>
                    <textarea rows="3" required placeholder="Describe your mobility device requirement or medical condition..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-action" style="background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color);" onclick="closeModal('pwdRequestModal')">Cancel</button>
                    <button type="submit" class="btn-action"><i class="fas fa-paper-plane"></i> Submit Application</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD PICTURE MODAL -->
    <div class="modal-overlay" id="addPictureModal">
        <div class="modal-box">
            <h2><i class="fas fa-image" style="color: #283593;"></i> Add New Picture Container</h2>
            <form onsubmit="saveNewPictureContainer(event)">
                <div class="form-group">
                    <label>Picture Title</label>
                    <input type="text" id="picTitle" required placeholder="e.g. PWD Assistive Device Distribution 2026">
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

        function submitPwdRequest(e) {
            e.preventDefault();
            alert('Your PWD assistance request has been submitted successfully to CSWDO Palayan City!');
            closeModal('pwdRequestModal');
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
                            <p>${caption || 'PWD Portal Upload'}</p>
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

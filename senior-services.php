<?php
// senior-services.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
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

// Check if user is senior
$check_res = $db->prepare("SELECT senior_citizen FROM residents WHERE user_id = :id");
$check_res->bindParam(':id', $_SESSION['user_id']);
$check_res->execute();
$res_info = $check_res->fetch(PDO::FETCH_ASSOC);

if (($res_info['senior_citizen'] ?? 0) != 1) {
    redirect('dashboard.php');
}

// Get initials for sidebar avatar
$initials = '';
$avatar_url = '';
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

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Citizen Services - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #0288d1, #005662);
            border-radius: var(--radius-card);
            padding: 30px 36px;
            margin-bottom: 28px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .hero-section h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hero-section p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 28px;
            margin-bottom: 30px;
        }
        @media (max-width: 992px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .info-card h3 {
            color: var(--maroon);
            margin-bottom: 18px;
            font-size: 1.15rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        [data-theme="dark"] .info-card h3 {
            color: var(--maroon-light);
        }
        .req-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .req-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .req-icon {
            color: #0288d1;
            margin-top: 3px;
        }
        .req-details {
            flex: 1;
        }
        .req-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.92rem;
        }
        .req-source {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .process-table-container {
            overflow-x: auto;
        }
        .process-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
        }
        .process-table th {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-weight: 700;
            padding: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .process-table td {
            padding: 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: top;
        }
        .process-table tr:hover {
            background: rgba(0,0,0,0.02);
        }
        [data-theme="dark"] .process-table tr:hover {
            background: rgba(255,255,255,0.02);
        }
        .duration-badge {
            background: #e3f2fd;
            color: #0d47a1;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
        }
        [data-theme="dark"] .duration-badge {
            background: rgba(13, 71, 161, 0.2);
            color: #4fc3f7;
        }
        .fees-badge {
            color: #2e7d32;
            font-weight: 700;
        }

        /* ========== TOP BAR USER PROFILE STYLING ========== */
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        .avatar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--maroon);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
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
        <?php include 'includes/usersidenav.php'; ?>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <div class="page-title">
                <h1>Senior Citizen Services</h1>
                <p>Citizen's Charter information for OSCA benefits & applications</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <div class="user-profile">
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile" class="user-avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo $initials; ?></div>
                    <?php endif; ?>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
            </div>
        </div>

        <!-- Hero -->
        <div class="hero-section">
            <h1><i class="fas fa-blind"></i> Senior Citizen ID & Booklet Application</h1>
            <p>Complete guide, checklists, and processing workflow to secure your Senior Citizen Identification Card & Endorsed Purchase Booklet in Palayan City.</p>
        </div>

        <div class="info-grid">
            <!-- Checklist Card -->
            <div class="info-card">
                <h3><i class="fas fa-clipboard-list"></i> Checklist of Requirements</h3>
                <ul class="req-list">
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">1. Barangay Clearance</div>
                            <div class="req-source">Secure at: Barangay Hall of Community where client resides / Client / COMELEC</div>
                        </div>
                    </li>
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">2. Voter’s ID / Certification</div>
                            <div class="req-source">Secure at: Senior Citizen’s Organization President / Office from his/her barangay / CSWDO / OSCA</div>
                        </div>
                    </li>
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">3. Duly Accomplished Application Form</div>
                            <div class="req-source">Secure at: Client / CSWDO Office</div>
                        </div>
                    </li>
                </ul>

                <div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin-top: 25px; font-weight: 600; text-align: center; border: 1px solid #c8e6c9;">
                    💰 Service Fee: None — Free of Charge
                </div>
            </div>

            <!-- Steps Card -->
            <div class="info-card">
                <h3><i class="fas fa-project-diagram"></i> Step-by-Step Process</h3>
                <div class="process-table-container">
                    <table class="process-table">
                        <thead>
                            <tr>
                                <th>Client Steps</th>
                                <th>Agency Action</th>
                                <th>Fees</th>
                                <th>Duration</th>
                                <th>Person Responsible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1. Register on the client's log book special lane in Window 3 (Register name, address, gender, contact number and purpose of visit)</td>
                                <td>Assist the client in administering client's logbook.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">1 minute</span></td>
                                <td>Any CSWDO staff (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>2. Submit the complete documentary requirements</td>
                                <td>Receive & Review required documents and check for completeness.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">2 minutes</span></td>
                                <td>Focal Person for Senior Citizen / OSCA (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>3. Fill up/administer Application form for SC and affix signature or thumb mark to the SC Identification Card & Purchase Booklet.</td>
                                <td>Assist Client in filling up Application form for SC and affix signature/thumb mark to the SC Identification Card & Purchase Booklet.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">10 minutes</span></td>
                                <td>Focal Person for Senior Citizen / OSCA (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>4. Undergo photo shoot</td>
                                <td>Processing of Senior Citizen ID.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">5 minutes</span></td>
                                <td>Daycare Worker / Admin, Aide (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>5. Waiting for the approval and release of Senior Citizen Identification Card & Purchase booklet.</td>
                                <td>Approval of Senior Citizen ID and Endorsed Purchase Booklet to CMO for signature of the City Mayor and Released the Booklet.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">10 minutes</span></td>
                                <td>OSCA (CSWDO)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Sidebar toggle
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // Theme Toggle
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
</body>
</html>

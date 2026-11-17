<?php
// pwd-services.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('services.php?service=pwd-id');
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

// Check if user is PWD
$check_res = $db->prepare("SELECT pwd FROM residents WHERE user_id = :id");
$check_res->bindParam(':id', $_SESSION['user_id']);
$check_res->execute();
$res_info = $check_res->fetch(PDO::FETCH_ASSOC);

if (($res_info['pwd'] ?? 0) != 1) {
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
    <title>PWD Services - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #2e7d32, #004d40);
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
            color: #2e7d32;
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
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
        }
        [data-theme="dark"] .duration-badge {
            background: rgba(46, 125, 50, 0.2);
            color: #81c784;
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
    <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include __DIR__ . '/includes/portal-header.php'; ?>

        <div class="portal-page-subheader">
            <div class="portal-page-subheader-title">
                <h2><i class="fas fa-wheelchair" style="color: #2e7d32; margin-right: 8px;"></i> PWD Services</h2>
                <p>Citizen's Charter information for Person with Disability benefits</p>
            </div>
        </div>

        <!-- Hero -->
        <div class="hero-section">
            <h1><i class="fas fa-wheelchair"></i> PWD ID & Booklet Application</h1>
            <p>Complete guide, checklists, and processing workflow to secure your Person with Disability (PWD) Identification Card & Endorsed Purchase Booklet in Palayan City.</p>
        </div>

        <div class="info-grid">
            <!-- Checklist Card -->
            <div class="info-card">
                <h3><i class="fas fa-clipboard-list"></i> Checklist of Requirements</h3>
                <ul class="req-list">
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">1. Three 1x1 ID Photos</div>
                            <div class="req-source">Secure at: Client / Any photo studio (with name and signature at the back)</div>
                        </div>
                    </li>
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">2. Barangay Clearance</div>
                            <div class="req-source">Secure at: Barangay Hall of the community where the client resides</div>
                        </div>
                    </li>
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">3. Any Valid ID</div>
                            <div class="req-source">Secure at: Client</div>
                        </div>
                    </li>
                    <li class="req-item">
                        <i class="fas fa-check-circle req-icon"></i>
                        <div class="req-details">
                            <div class="req-title">4. Original Medical Certificate</div>
                            <div class="req-source">Secure at: Attending Physician (stating the type of disability of the client)</div>
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
                                <td>1. Register on the client's log book. Special lane in Window 1 (Register name, address, gender, contact number and purpose of visit)</td>
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
                                <td>PWD Focal Person (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>3. Fill up/administer Phil. Registration form for PWD and affix signature or thumb mark to the PWD Identification Card.</td>
                                <td>Assist Client in filling up registry form and affixing signature or thumb mark to the PWD Identification Card.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">10 minutes</span></td>
                                <td>PWD Focal Person (CSWDO)</td>
                            </tr>
                            <tr>
                                <td>4. Waiting for the approval and release of PWD Identification Card & Purchase Booklet.</td>
                                <td>Approval of PWD ID and Endorsed Purchase Booklet to CMO for signature of the City Mayor and Released the Booklet.</td>
                                <td class="fees-badge">None</td>
                                <td><span class="duration-badge">10 minutes</span></td>
                                <td>Focal Person (CSWDO)</td>
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
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

<?php
// my-housing.php
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

$query = "SELECT h.*, p.program_name, p.location, p.program_code,
          DATEDIFF(NOW(), h.application_date) as days_since_application
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          WHERE h.user_id = :user_id 
          ORDER BY 
            CASE h.status
                WHEN 'approved' THEN 1
                WHEN 'released' THEN 2
                WHEN 'qualified' THEN 3
                WHEN 'pending' THEN 4
                WHEN 'rejected' THEN 5
                ELSE 6
            END,
            h.application_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM housing_beneficiaries WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get approved/released applications count
$has_approved = ($stats['approved'] ?? 0) > 0;
$has_released = ($stats['released'] ?? 0) > 0;

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
    <title>My Housing - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 14px 16px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }
        .stat-card .stat-icon {
            font-size: 1.1rem;
            color: var(--maroon-light);
            margin-bottom: 4px;
        }

        /* ========== HOUSING SUMMARY ========== */
        .housing-summary {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .housing-summary .summary-text {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .housing-summary .summary-text i {
            font-size: 2rem;
            opacity: 0.8;
        }
        .housing-summary .summary-text h3 {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .housing-summary .summary-text p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        .housing-summary .summary-actions {
            display: flex;
            gap: 12px;
        }
        .housing-summary .summary-actions a {
            background: rgba(255,255,255,0.12);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            color: white;
            font-size: 0.78rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .housing-summary .summary-actions a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ========== APPLICATIONS LIST ========== */
        .applications-list {
            display: grid;
            gap: 18px;
        }
        .application-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--maroon);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .application-card.status-pending { border-left-color: var(--color-warning); }
        .application-card.status-qualified { border-left-color: var(--color-info); }
        .application-card.status-approved { border-left-color: var(--color-success); }
        .application-card.status-released { border-left-color: var(--color-purple); }
        .application-card.status-rejected { border-left-color: var(--color-danger); }

        .card-header {
            padding: 14px 20px;
            background: var(--badge-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header h3 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i {
            color: var(--maroon-light);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.qualified {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.released {
            background: rgba(156, 39, 176, 0.15);
            color: var(--color-purple);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }

        .card-body {
            padding: 18px 20px;
        }
        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .info-row i {
            color: var(--maroon-light);
            width: 18px;
            font-size: 0.8rem;
        }
        .info-row .label {
            color: var(--text-muted);
            min-width: 110px;
        }
        .info-row .value {
            color: var(--text-secondary);
        }
        .info-row .value.highlight {
            color: var(--color-success);
            font-weight: 700;
        }

        .card-footer {
            padding: 12px 20px;
            background: var(--badge-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 6px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }

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
            margin-bottom: 16px;
        }
        .empty-state .btn-view {
            display: inline-flex;
            padding: 10px 24px;
            font-size: 0.85rem;
            background: var(--maroon);
            color: white;
        }
        .empty-state .btn-view:hover {
            background: var(--maroon-light);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .housing-summary { flex-direction: column; text-align: center; }
            .housing-summary .summary-text { flex-direction: column; }
            .housing-summary .summary-actions { justify-content: center; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .info-row { flex-wrap: wrap; }
            .info-row .label { min-width: 80px; }
            .card-footer { flex-direction: column; }
            .btn-view { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .application-card { margin: 0; }
            .card-body { padding: 14px 16px; }
            .card-header { padding: 12px 16px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-home"></i></div>
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

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-building" style="color: var(--maroon-light); margin-right: 10px;"></i> My Housing</h1>
                        <p>Track your housing applications and approved units</p>
                    </div>
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
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock" style="color: var(--color-warning);"></i></div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-search" style="color: var(--color-info);"></i></div>
                <div class="stat-number"><?php echo $stats['qualified'] ?? 0; ?></div>
                <div class="stat-label">Qualified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--color-success);"></i></div>
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-gift" style="color: var(--color-purple);"></i></div>
                <div class="stat-number"><?php echo $stats['released'] ?? 0; ?></div>
                <div class="stat-label">Released</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--color-danger);"></i></div>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Housing Summary -->
        <?php if($has_approved || $has_released): ?>
        <div class="housing-summary">
            <div class="summary-text">
                <i class="fas fa-home"></i>
                <div>
                    <h3>🏠 Housing Status</h3>
                    <p>
                        <?php if($has_released): ?>
                            🎉 You have been assigned a housing unit!
                        <?php elseif($has_approved): ?>
                            ✅ Your application has been approved!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="summary-actions">
                <a href="view-housing.php"><i class="fas fa-eye"></i> View Details</a>
                <a href="housing-applications.php"><i class="fas fa-list"></i> All Applications</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Applications List -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="applications-list">
            <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="application-card status-<?php echo $app['status']; ?>">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> <?php echo htmlspecialchars($app['program_name']); ?></h3>
                    <span class="status-badge <?php echo $app['status']; ?>">
                        <i class="fas fa-<?php 
                            echo $app['status'] == 'pending' ? 'clock' : 
                                ($app['status'] == 'qualified' ? 'search' : 
                                ($app['status'] == 'approved' ? 'check-circle' : 
                                ($app['status'] == 'released' ? 'gift' : 'times-circle'))); 
                        ?>"></i>
                        <?php echo ucfirst($app['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="label">Location:</span>
                        <span class="value"><?php echo htmlspecialchars($app['location']); ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-calendar"></i>
                        <span class="label">Applied on:</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($app['application_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-users"></i>
                        <span class="label">Household Size:</span>
                        <span class="value"><?php echo $app['household_composition']; ?> persons</span>
                    </div>
                    <?php if($app['monthly_income'] > 0): ?>
                    <div class="info-row">
                        <i class="fas fa-money-bill"></i>
                        <span class="label">Monthly Income:</span>
                        <span class="value">₱<?php echo number_format($app['monthly_income'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($app['unit_assigned'])): ?>
                    <div class="info-row">
                        <i class="fas fa-home"></i>
                        <span class="label">Unit Assigned:</span>
                        <span class="value highlight"><?php echo htmlspecialchars($app['unit_assigned']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($app['interview_date'])): ?>
                    <div class="info-row">
                        <i class="fas fa-calendar-check"></i>
                        <span class="label">Interview Date:</span>
                        <span class="value"><?php echo date('M d, Y', strtotime($app['interview_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="btn-view">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-home"></i>
            <h3>No Housing Applications</h3>
            <p>You haven't applied for any housing programs yet.</p>
            <a href="apply-housing.php" class="btn-view">
                <i class="fas fa-plus"></i> Apply for Housing Now
            </a>
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
</body>
</html>

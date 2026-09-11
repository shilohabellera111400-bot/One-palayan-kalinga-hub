<?php
// housing-applications.php
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

// Handle application withdrawal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw_application'])) {
    $beneficiary_id = sanitize($_POST['beneficiary_id']);
    
    // Check if application belongs to user and is withdrawable
    $check_query = "SELECT * FROM housing_beneficiaries 
                    WHERE beneficiary_id = :id AND user_id = :user_id 
                    AND status IN ('pending', 'qualified')";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $beneficiary_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $update_query = "UPDATE housing_beneficiaries SET status = 'rejected', 
                         housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\n\nApplication withdrawn by applicant on ', NOW())
                         WHERE beneficiary_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $beneficiary_id);
        
        if ($update_stmt->execute()) {
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'Application Withdrawn', 
                            'Your housing application has been withdrawn successfully.', 'housing')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            $success = "Application withdrawn successfully.";
            
            header("Location: housing-applications.php");
            exit();
        } else {
            $error = "Failed to withdraw application.";
        }
    } else {
        $error = "Application not found or cannot be withdrawn.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$program_filter = isset($_GET['program']) ? sanitize($_GET['program']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

$query = "SELECT h.*, p.program_name, p.location, p.program_code,
          DATEDIFF(NOW(), h.application_date) as days_since_application
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          WHERE h.user_id = :user_id";

$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter != 'all') {
    $query .= " AND h.status = :status";
    $params[':status'] = $status_filter;
}

if ($program_filter != 'all') {
    $query .= " AND h.program_id = :program";
    $params[':program'] = $program_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(h.application_date) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY 
            CASE h.status
                WHEN 'pending' THEN 1
                WHEN 'qualified' THEN 2
                WHEN 'approved' THEN 3
                WHEN 'released' THEN 4
                WHEN 'rejected' THEN 5
                ELSE 6
            END,
            h.application_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
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

// Get programs for filter
$programs_query = "SELECT program_id, program_name FROM housing_programs ORDER BY program_name";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute();
$programs = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved application
$approved_query = "SELECT h.*, p.program_name, p.location 
                   FROM housing_beneficiaries h
                   JOIN housing_programs p ON h.program_id = p.program_id
                   WHERE h.user_id = :user_id AND h.status = 'approved'
                   ORDER BY h.approval_date DESC LIMIT 1";
$approved_stmt = $db->prepare($approved_query);
$approved_stmt->bindParam(':user_id', $_SESSION['user_id']);
$approved_stmt->execute();
$approved = $approved_stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Housing Applications - One Palayan-Kalinga Hub</title>
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
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid var(--maroon);
            border: 1px solid var(--border-color);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card.pending { border-left-color: var(--color-warning); }
        .stat-card.qualified { border-left-color: var(--color-info); }
        .stat-card.approved { border-left-color: var(--color-success); }
        .stat-card.released { border-left-color: var(--color-purple); }
        .stat-card.rejected { border-left-color: var(--color-danger); }
        .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        /* ========== ACTION BAR ========== */
        .action-bar {
            background: var(--card-bg);
            padding: 18px 22px;
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .apply-btn {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }
        .apply-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-section select {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-section select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .clear-btn {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
        }
        .clear-btn:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        /* ========== APPLICATIONS GRID ========== */
        .applications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
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
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .application-card.status-pending { border-left-color: var(--color-warning); }
        .application-card.status-qualified { border-left-color: var(--color-info); }
        .application-card.status-approved { border-left-color: var(--color-success); }
        .application-card.status-released { border-left-color: var(--color-purple); }
        .application-card.status-rejected { border-left-color: var(--color-danger); }

        .card-header {
            padding: 14px 18px;
            background: var(--badge-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header .program-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.95rem;
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
            padding: 16px 18px;
        }
        .card-body .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .card-body .detail-row i {
            color: var(--maroon-light);
            width: 18px;
            font-size: 0.8rem;
        }

        .card-footer {
            padding: 12px 18px;
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
        .btn-withdraw {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
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
        .btn-withdraw:hover {
            background: var(--color-danger);
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

        /* ========== MODAL ========== */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            width: 90%;
            max-width: 450px;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--badge-bg);
            border-radius: var(--radius-card) var(--radius-card) 0 0;
        }
        .modal-header h2 {
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i {
            color: var(--color-warning);
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-body p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .modal-body .warning-text {
            color: var(--color-danger);
            font-size: 0.8rem;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--badge-bg);
            border-radius: 0 0 var(--radius-card) var(--radius-card);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .applications-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .action-bar { flex-direction: column; align-items: stretch; }
            .filter-section { flex-direction: column; }
            .filter-section select { width: 100%; }
            .filter-section .filter-btn, .filter-section .clear-btn { width: 100%; justify-content: center; }
            .applications-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-footer { flex-direction: column; }
            .btn-view, .btn-withdraw { width: 100%; justify-content: center; }
            .modal-content { width: 95%; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 10px 12px; }
            .stat-number { font-size: 1.1rem; }
            .application-card { margin: 0; }
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
                        <h1><i class="fas fa-home" style="color: var(--maroon-light); margin-right: 10px;"></i> My Housing Applications</h1>
                        <p>Track and manage all your housing program applications</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='housing-applications.php?status=all'">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card pending" onclick="window.location.href='housing-applications.php?status=pending'">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card qualified" onclick="window.location.href='housing-applications.php?status=qualified'">
                <div class="stat-number"><?php echo $stats['qualified'] ?? 0; ?></div>
                <div class="stat-label">Qualified</div>
            </div>
            <div class="stat-card approved" onclick="window.location.href='housing-applications.php?status=approved'">
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card released" onclick="window.location.href='housing-applications.php?status=released'">
                <div class="stat-number"><?php echo $stats['released'] ?? 0; ?></div>
                <div class="stat-label">Released</div>
            </div>
            <div class="stat-card rejected" onclick="window.location.href='housing-applications.php?status=rejected'">
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <a href="apply-housing.php" class="apply-btn">
                <i class="fas fa-plus-circle"></i> New Application
            </a>
            <div class="filter-section">
                <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="qualified" <?php echo $status_filter == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    <?php if($status_filter != 'all'): ?>
                    <a href="housing-applications.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Applications Grid -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="applications-grid">
            <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="application-card status-<?php echo $app['status']; ?>">
                <div class="card-header">
                    <span class="program-name"><?php echo htmlspecialchars($app['program_name']); ?></span>
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
                    <div class="detail-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($app['location']); ?></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-calendar"></i>
                        <span>Applied: <?php echo date('M d, Y', strtotime($app['application_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-users"></i>
                        <span>Household: <?php echo $app['household_composition']; ?> persons</span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-money-bill"></i>
                        <span>Monthly Income: ₱<?php echo number_format($app['monthly_income'], 2); ?></span>
                    </div>
                    <?php if(!empty($app['unit_assigned'])): ?>
                    <div class="detail-row">
                        <i class="fas fa-home"></i>
                        <span>Unit Assigned: <?php echo htmlspecialchars($app['unit_assigned']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="btn-view">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <?php if($app['status'] == 'pending' || $app['status'] == 'qualified'): ?>
                    <button onclick="withdrawApplication(<?php echo $app['beneficiary_id']; ?>)" class="btn-withdraw">
                        <i class="fas fa-times"></i> Withdraw
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-home"></i>
            <h3>No Housing Applications</h3>
            <p>You haven't applied for any housing programs yet.</p>
            <a href="apply-housing.php" class="apply-btn" style="display: inline-flex; margin-top: 16px;">
                <i class="fas fa-plus"></i> Apply for Housing Now
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Withdraw Confirmation Modal -->
<div id="withdrawModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Withdrawal</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to withdraw this housing application?</p>
            <p class="warning-text"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="withdrawForm">
                <input type="hidden" name="beneficiary_id" id="withdraw_id">
                <button type="button" onclick="closeModal()" class="btn-view" style="padding: 8px 20px;">Cancel</button>
                <button type="submit" name="withdraw_application" class="btn-withdraw" style="padding: 8px 20px;">Yes, Withdraw</button>
            </form>
        </div>
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

// ========== MODAL FUNCTIONS ==========
function withdrawApplication(id) {
    document.getElementById('withdraw_id').value = id;
    document.getElementById('withdrawModal').classList.add('show');
}

function closeModal() {
    document.getElementById('withdrawModal').classList.remove('show');
    document.getElementById('withdraw_id').value = '';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('withdrawModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 500);
    });
}, 5000);
</script>
</body>
</html>

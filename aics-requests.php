<?php
// aics-requests.php
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

// Handle cancellation/withdrawal of request
if (isset($_POST['cancel_request'])) {
    $aics_id = sanitize($_POST['aics_id']);
    
    // Check if request belongs to user and is cancellable
    $check_query = "SELECT * FROM aics_requests 
                    WHERE aics_id = :id AND user_id = :user_id 
                    AND status IN ('submitted', 'reviewing')";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $aics_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $update_query = "UPDATE aics_requests SET status = 'rejected', 
                         rejection_reason = 'Cancelled by applicant' 
                         WHERE aics_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $aics_id);
        
        if ($update_stmt->execute()) {
            // Create notification
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'AICS Request Cancelled', 
                            'Your AICS request has been cancelled.', 'aics')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            
            $success = "Request cancelled successfully.";
        } else {
            $error = "Failed to cancel request.";
        }
    } else {
        $error = "Request not found or cannot be cancelled.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build query
$query = "SELECT * FROM aics_requests 
          WHERE user_id = :user_id";

$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter != 'all') {
    $query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter != 'all') {
    $query .= " AND request_type = :type";
    $params[':type'] = $type_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(submission_date) BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY submission_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
    SUM(amount_requested) as total_amount,
    SUM(CASE WHEN status = 'approved' THEN amount_approved ELSE 0 END) as total_approved
    FROM aics_requests WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>My AICS Requests - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .stat-card.submitted { border-left-color: var(--color-warning); }
        .stat-card.reviewing { border-left-color: var(--color-info); }
        .stat-card.approved { border-left-color: var(--color-success); }
        .stat-card.rejected { border-left-color: var(--color-danger); }
        .stat-card.released { border-left-color: var(--color-purple); }
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

        /* ========== SUMMARY CARDS ========== */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: var(--card-bg);
            padding: 18px 20px;
            border-radius: var(--radius-element);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        .summary-icon {
            width: 48px;
            height: 48px;
            background: rgba(127, 3, 3, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--maroon-light);
            flex-shrink: 0;
        }
        .summary-info h4 {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        .summary-info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--card-bg);
            padding: 18px 22px;
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 140px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filter-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
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

        /* ========== TABLE ========== */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow-x: auto;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        .requests-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--badge-bg);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        .requests-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .requests-table tr:hover {
            background: var(--badge-bg);
        }

        .tracking-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: var(--maroon-light);
            font-size: 0.8rem;
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
        .status-badge.submitted {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.reviewing {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }
        .status-badge.released {
            background: rgba(156, 39, 176, 0.15);
            color: var(--color-purple);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }
        .btn-cancel {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-cancel:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            backdrop-filter: blur(4px);
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--card-bg);
            padding: 28px 32px;
            border-radius: var(--radius-card);
            max-width: 420px;
            width: 90%;
            transform: translateY(-20px);
            transition: transform 0.3s;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .modal.active .modal-content {
            transform: translateY(0);
        }
        .modal-content h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .modal-content h3 i {
            color: var(--color-warning);
        }
        .modal-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        .modal-content .warning {
            color: var(--color-danger);
            font-size: 0.8rem;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .modal-buttons .btn-cancel {
            padding: 10px 25px;
            font-size: 0.85rem;
        }
        .modal-buttons .btn-view {
            padding: 10px 25px;
            font-size: 0.85rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .summary-cards { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-actions { flex-direction: column; width: 100%; }
            .filter-actions button, .filter-actions a { width: 100%; justify-content: center; }
            .table-container { padding: 12px 16px; }
            .requests-table th, .requests-table td { padding: 8px 10px; font-size: 0.75rem; }
            .action-buttons { flex-direction: column; align-items: center; }
            .action-buttons .btn-view, .action-buttons .btn-cancel { width: 100%; justify-content: center; }
            .modal-content { padding: 20px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 10px 12px; }
            .stat-number { font-size: 1.1rem; }
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
                        <h1><i class="fas fa-list-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> My AICS Requests</h1>
                        <p>Track and manage all your Assistance to Individuals in Crisis Situation requests</p>
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
            <div class="stat-card" onclick="window.location.href='aics-requests.php?status=all'">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card submitted" onclick="window.location.href='aics-requests.php?status=submitted'">
                <div class="stat-number"><?php echo $stats['submitted'] ?? 0; ?></div>
                <div class="stat-label">Submitted</div>
            </div>
            <div class="stat-card reviewing" onclick="window.location.href='aics-requests.php?status=reviewing'">
                <div class="stat-number"><?php echo $stats['reviewing'] ?? 0; ?></div>
                <div class="stat-label">Reviewing</div>
            </div>
            <div class="stat-card approved" onclick="window.location.href='aics-requests.php?status=approved'">
                <div class="stat-number"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected" onclick="window.location.href='aics-requests.php?status=rejected'">
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card released" onclick="window.location.href='aics-requests.php?status=released'">
                <div class="stat-number"><?php echo $stats['released'] ?? 0; ?></div>
                <div class="stat-label">Released</div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-coins"></i></div>
                <div class="summary-info">
                    <h4>Total Amount Requested</h4>
                    <h3>₱<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></h3>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-check-circle" style="color: var(--color-success);"></i></div>
                <div class="summary-info">
                    <h4>Total Amount Approved</h4>
                    <h3>₱<?php echo number_format($stats['total_approved'] ?? 0, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="reviewing" <?php echo $status_filter == 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Type</label>
                    <select name="type">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="medical" <?php echo $type_filter == 'medical' ? 'selected' : ''; ?>>Medical</option>
                        <option value="funeral" <?php echo $type_filter == 'funeral' ? 'selected' : ''; ?>>Funeral</option>
                        <option value="educational" <?php echo $type_filter == 'educational' ? 'selected' : ''; ?>>Educational</option>
                        <option value="transportation" <?php echo $type_filter == 'transportation' ? 'selected' : ''; ?>>Transportation</option>
                        <option value="food" <?php echo $type_filter == 'food' ? 'selected' : ''; ?>>Food</option>
                        <option value="cash_relief" <?php echo $type_filter == 'cash_relief' ? 'selected' : ''; ?>>Cash Relief</option>
                        <option value="material" <?php echo $type_filter == 'material' ? 'selected' : ''; ?>>Material</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Date From</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Date To</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="filter-btn"><i class="fas fa-search"></i> Apply</button>
                    <?php if($status_filter != 'all' || $type_filter != 'all' || $date_from || $date_to): ?>
                    <a href="aics-requests.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($request = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td class="tracking-code">AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?></td>
                        <td>₱<?php echo number_format($request['amount_requested'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $request['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo $request['status'] == 'submitted' ? 'clock' : 
                                        ($request['status'] == 'reviewing' ? 'search' : 
                                        ($request['status'] == 'approved' ? 'check-circle' : 
                                        ($request['status'] == 'released' ? 'gift' : 'times-circle'))); 
                                ?>"></i>
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div class="action-buttons" style="justify-content: center;">
                                <a href="track-aics.php?tracking=AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="btn-view">
                                    <i class="fas fa-search"></i> Track
                                </a>
                                <?php if($request['status'] == 'submitted' || $request['status'] == 'reviewing'): ?>
                                <button onclick="showCancelModal(<?php echo $request['aics_id']; ?>)" class="btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No AICS Requests Found</h3>
            <p>You haven't submitted any AICS requests yet.</p>
            <a href="aics.php" class="btn-view" style="display: inline-flex; padding: 10px 24px; font-size: 0.85rem; background: var(--maroon); color: white;">
                <i class="fas fa-plus"></i> Apply for AICS
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Cancel Request</h3>
        <p>Are you sure you want to cancel this AICS request?</p>
        <p class="warning"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="aics_id" id="cancel_aics_id">
            <div class="modal-buttons">
                <button type="button" onclick="closeModal()" class="btn-view">No, Keep It</button>
                <button type="submit" name="cancel_request" class="btn-cancel">Yes, Cancel</button>
            </div>
        </form>
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
function showCancelModal(id) {
    document.getElementById('cancel_aics_id').value = id;
    document.getElementById('cancelModal').classList.add('active');
}

function closeModal() {
    document.getElementById('cancelModal').classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('cancelModal');
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

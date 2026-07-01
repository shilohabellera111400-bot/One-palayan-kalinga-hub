<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_document'])) {
    $document_id = sanitize($_POST['document_id']);
    $verification_status = sanitize($_POST['verification_status']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $update_query = "UPDATE documents SET 
                      verification_status = :status,
                      verified_by = :user_id,
                      verification_date = NOW(),
                      remarks = :remarks
                      WHERE document_id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $verification_status);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $update_stmt->bindParam(':remarks', $remarks);
    $update_stmt->bindParam(':id', $document_id);
    
    if ($update_stmt->execute()) {
        // Get user_id for notification
        $user_query = "SELECT user_id FROM documents WHERE document_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $document_id);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Document Verified', 
                        'Your document has been ' . $verification_status . '', 'general')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $user['user_id']);
        $notif_stmt->execute();
        
        $success = "Document verification status updated!";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT d.*, u.first_name, u.last_name, u.email 
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $query .= " AND d.verification_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR d.document_type LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Documents - Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-100: #F1F3F8;
            --gray-200: #E8ECF2;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
        }

        /* ========== LAYOUT ========== */
        .app-container { display: flex; min-height: 100vh; background: var(--gray-50); }

        /* ========== SIDEBAR - MAROON THEME ========== */
        .sidebar {
            width: 280px;
            background: var(--maroon);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 4px; }

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .logo-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.6rem;
        }
        .logo-text h2 {
            color: white;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
        .logo-text p {
            color: rgba(255,255,255,0.65);
            font-size: 0.7rem;
            margin-top: 4px;
        }

        .staff-profile {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 16px;
        }
        .staff-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--tan), var(--light-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--maroon);
        }
        .staff-profile h4 {
            color: white;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 22px; font-size: 1.1rem; color: rgba(255,255,255,0.7); }
        .sidebar-menu li a:hover { background: rgba(255,255,255,0.12); color: white; }
        .sidebar-menu li a.active {
            background: var(--maroon-light);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .sidebar-menu li a.active i { color: white; }
        .menu-divider {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
            padding-top: 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            padding-left: 16px;
        }

        .sidebar-footer {
            padding: 20px 16px 30px 16px;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            background: rgba(255,255,255,0.08);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.18); color: white; }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--maroon);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            background: var(--alabaster);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: var(--maroon);
        }
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--midnight);
        }
        .stat-info p {
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        /* Quick Stats Bar */
        .quick-stats-bar {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a5a66 100%);
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            color: white;
        }
        .document-info h4 {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .document-info .count {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .quick-actions {
            display: flex;
            gap: 12px;
        }
        .quick-btn {
            background: rgba(255,255,255,0.15);
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: var(--radius-card);
            padding: 20px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127,3,3,0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--gray-300);
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        .document-card {
            background: white;
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .document-header {
            padding: 16px 20px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .document-type {
            font-weight: 700;
            color: var(--maroon);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: #fff3e0; color: #f57c00; }
        .status-verified { background: #e8f5e9; color: #43a047; }
        .status-rejected { background: #ffebee; color: #e53935; }

        .document-body {
            padding: 20px;
        }
        .applicant-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--midnight);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .document-meta {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .document-meta i {
            width: 18px;
            color: var(--maroon);
        }
        .document-preview {
            margin: 16px 0;
        }
        .btn-view-doc {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--alabaster);
            color: var(--maroon);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-view-doc:hover {
            background: var(--maroon);
            color: white;
        }
        .document-remarks {
            background: var(--gray-50);
            padding: 12px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-top: 12px;
            border-left: 3px solid var(--maroon);
        }
        .document-actions {
            padding: 16px 20px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            text-align: right;
        }
        .btn-verify {
            background: var(--maroon);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-verify:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        /* Modal */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            color: var(--maroon);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-body {
            padding: 25px;
        }
        .info-card-mini {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-row-mini {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label-mini {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        .info-value-mini {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--midnight);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-700);
        }
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: inherit;
        }
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon);
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .sidebar { width: 250px; }
            .main-content { margin-left: 250px; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
            .documents-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .quick-stats-bar { flex-direction: column; text-align: center; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar - Maroon Theme -->
    <div class="sidebar">
        <div>
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-file-alt"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>
            </div>
            
            <div class="staff-profile">
                <div class="staff-avatar">
                    <?php echo $initials; ?>
                </div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p>Staff Member</p>
                <span class="staff-badge">
                    <i class="fas fa-user-check"></i> Staff
                </span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-divider">Appointments</li>
                <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
                <li class="menu-divider">Services</li>
                <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
                <li class="menu-divider">Documents</li>
                <li><a href="verify-documents.php" class="active"><i class="fas fa-file-alt"></i> Verify Documents</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="menu-divider"></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <h1 style="margin-bottom: 24px; color: var(--midnight);"><i class="fas fa-file-alt"></i> Verify Documents</h1>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="filterByStatus('all')">
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-card" onclick="filterByStatus('pending')">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card" onclick="filterByStatus('verified')">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['verified']; ?></h3>
                    <p>Verified</p>
                </div>
            </div>
            <div class="stat-card" onclick="filterByStatus('rejected')">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Bar -->
        <div class="quick-stats-bar">
            <div class="document-info">
                <h4>Documents Awaiting Verification</h4>
                <div class="count"><?php echo $stats['pending']; ?> Pending</div>
            </div>
            <div class="quick-actions">
                <a href="reports.php?type=documents" class="quick-btn">
                    <i class="fas fa-chart-line"></i> View Reports
                </a>
                <a href="export-documents.php" class="quick-btn">
                    <i class="fas fa-download"></i> Export Data
                </a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" id="statusFilter">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Name or document type..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <?php if($status_filter != 'all' || !empty($search)): ?>
                        <a href="verify-documents.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Documents Grid -->
        <div class="documents-grid">
            <?php if($stmt->rowCount() > 0): ?>
                <?php while($doc = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="document-card">
                    <div class="document-header">
                        <span class="document-type">
                            <i class="fas fa-file-<?php 
                                $ext = pathinfo($doc['document_path'] ?? '', PATHINFO_EXTENSION);
                                echo $ext == 'pdf' ? 'pdf' : ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' ? 'image' : 'alt');
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                        </span>
                        <span class="status-badge status-<?php echo $doc['verification_status']; ?>">
                            <i class="fas fa-<?php 
                                echo $doc['verification_status'] == 'pending' ? 'clock' : 
                                    ($doc['verification_status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                            ?>"></i>
                            <?php echo ucfirst($doc['verification_status']); ?>
                        </span>
                    </div>
                    
                    <div class="document-body">
                        <div class="applicant-name">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                        </div>
                        <div class="document-meta">
                            <span><i class="fas fa-calendar-alt"></i> Uploaded: <?php echo date('M d, Y h:i A', strtotime($doc['upload_date'])); ?></span>
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doc['email']); ?></span>
                        </div>
                        
                        <div class="document-preview">
                            <a href="../<?php echo $doc['document_path']; ?>" target="_blank" class="btn-view-doc">
                                <i class="fas fa-external-link-alt"></i> View Document
                            </a>
                        </div>
                        
                        <?php if(!empty($doc['remarks'])): ?>
                        <div class="document-remarks">
                            <i class="fas fa-comment"></i> <strong>Remarks:</strong> <?php echo htmlspecialchars($doc['remarks']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($doc['verification_status'] == 'pending'): ?>
                    <div class="document-actions">
                        <button onclick="showVerifyModal(<?php echo htmlspecialchars(json_encode($doc)); ?>)" 
                                class="btn-verify">
                            <i class="fas fa-check-circle"></i> Verify Document
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: var(--radius-card);">
                    <i class="fas fa-inbox" style="font-size: 48px; color: var(--gray-200); margin-bottom: 15px; display: block;"></i>
                    <p style="color: var(--gray-600);">No documents found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div id="verifyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-signature"></i> Verify Document</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray-600);">&times;</button>
        </div>
        
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="document_id" id="modal_document_id">
                
                <div class="info-card-mini">
                    <div class="info-row-mini">
                        <span class="info-label-mini">Document Type:</span>
                        <span class="info-value-mini" id="modal_document_info"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Uploaded By:</span>
                        <span class="info-value-mini" id="modal_uploader"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">Verification Status</label>
                    <select name="verification_status" id="modal_status" required onchange="toggleRemarksField()">
                        <option value="verified">✅ Verified - Document is valid</option>
                        <option value="rejected">❌ Rejected - Document is invalid</option>
                    </select>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="modal_remarks">Rejection Reason</label>
                    <textarea name="remarks" id="modal_remarks" rows="3" 
                              placeholder="Please explain why this document is being rejected..."></textarea>
                </div>
                
                <div class="form-group">
                    <a href="#" id="modal_document_link" target="_blank" class="btn-view-doc" style="display: inline-flex;">
                        <i class="fas fa-external-link-alt"></i> Open Document
                    </a>
                </div>
            </div>
            
            <div class="modal-header" style="border-top: 1px solid var(--gray-200); justify-content: flex-end; gap: 12px;">
                <button type="submit" name="verify_document" class="btn-primary">
                    <i class="fas fa-save"></i> Submit Verification
                </button>
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showVerifyModal(document) {
    document.getElementById('modal_document_id').value = document.document_id;
    document.getElementById('modal_document_info').textContent = 
        ucfirst(document.document_type.replace(/_/g, ' '));
    document.getElementById('modal_uploader').textContent = 
        document.first_name + ' ' + document.last_name;
    document.getElementById('modal_document_link').href = '../' + document.document_path;
    document.getElementById('verifyModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('verifyModal').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('modal_remarks').value = '';
    document.getElementById('modal_status').value = 'verified';
}

function toggleRemarksField() {
    const status = document.getElementById('modal_status').value;
    const remarksField = document.getElementById('remarks_field');
    
    if (status === 'rejected') {
        remarksField.style.display = 'block';
        document.getElementById('modal_remarks').required = true;
    } else {
        remarksField.style.display = 'none';
        document.getElementById('modal_remarks').required = false;
    }
}

function filterByStatus(status) {
    document.getElementById('statusFilter').value = status;
    document.getElementById('filterForm').submit();
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

window.onclick = function(event) {
    const modal = document.getElementById('verifyModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
<?php
// staff/staff-dashboard.php - Canonical redirect to unified root staff-dashboard.php
$query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../staff-dashboard.php' . $query_string);
exit();
require_once __DIR__ . '/../config/database.php';
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories for this staff - Direct join to service_categories (NO service_id)
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_id, sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if staff has specific service access
$has_aics_access = false;
$has_housing_access = false;
$has_senior_access = false;
$has_pwd_access = false;

foreach ($assigned_categories as $category) {
    $cat_name = $category['category_name'];
    if ($cat_name == 'AICS') $has_aics_access = true;
    if ($cat_name == 'Housing') $has_housing_access = true;
    if ($cat_name == 'Senior Citizen') $has_senior_access = true;
    if ($cat_name == 'PWD') $has_pwd_access = true;
}

// Get staff details
$staff_query = "SELECT s.*, u.email, u.contact_number, u.last_login 
                FROM staff s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$stats = [];

// Total AICS requests
if ($has_aics_access) {
    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status IN ('submitted', 'reviewing')";
    $stats['pending_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'approved'";
    $stats['approved_aics'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
}

// Total Housing applications
if ($has_housing_access) {
    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status IN ('pending', 'qualified')";
    $stats['pending_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
    
    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status = 'approved'";
    $stats['approved_housing'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
}

// Documents to verify
$query = "SELECT COUNT(*) as count FROM documents WHERE verification_status = 'pending'";
$stats['pending_documents'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Today's appointments
$query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending', 'confirmed')";
$stats['today_appointments'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Pending verifications
$query = "SELECT COUNT(*) as count FROM verification_requests WHERE status = 'pending'";
$stats['pending_verifications'] = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];

// Recent AICS requests (if staff has access)
$recent_aics = [];
if ($has_aics_access) {
    $query = "SELECT a.*, u.first_name, u.last_name 
              FROM aics_requests a
              JOIN users u ON a.user_id = u.user_id
              WHERE a.status IN ('submitted', 'reviewing')
              ORDER BY a.submission_date DESC
              LIMIT 5";
    $recent_aics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

// Recent Housing applications (if staff has access)
$recent_housing = [];
if ($has_housing_access) {
    $tables_check = $db->query("SHOW TABLES LIKE 'housing_beneficiaries'");
    if ($tables_check->rowCount() > 0) {
        $query = "SELECT h.*, u.first_name, u.last_name 
                  FROM housing_beneficiaries h
                  JOIN users u ON h.user_id = u.user_id
                  WHERE h.status IN ('pending', 'qualified')
                  ORDER BY h.application_date DESC
                  LIMIT 5";
        $recent_housing = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Recent documents
$recent_docs = [];
$query = "SELECT d.*, u.first_name, u.last_name 
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          WHERE d.verification_status = 'pending'
          ORDER BY d.upload_date DESC
          LIMIT 5";
$recent_docs = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Recent verification requests
$recent_verifications = [];
$query = "SELECT v.*, u.first_name, u.last_name, u.email 
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          WHERE v.status = 'pending'
          ORDER BY v.submitted_date DESC
          LIMIT 5";
$recent_verifications = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get initials for avatar
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
    <title>Staff Dashboard - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #E8ECF2;
            --gray-100: #F1F3F8;
            --gray-200: #E8ECF2;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
        }

        .app-container { display: flex; min-height: 100vh; background: var(--gray-50); }

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
        }
        .logo-text p {
            color: rgba(255,255,255,0.65);
            font-size: 0.7rem;
        }

        .staff-profile {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
        .staff-profile h4 { color: white; font-size: 1rem; }
        .staff-profile p { color: rgba(255,255,255,0.7); font-size: 0.75rem; }
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
        .sidebar-menu li a i { width: 22px; }
        .sidebar-menu li a:hover { background: rgba(255,255,255,0.12); color: white; }
        .sidebar-menu li a.active {
            background: var(--maroon-light);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .menu-divider {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
            padding-top: 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            padding-left: 16px;
        }

        .sidebar-footer {
            padding: 20px 16px 30px;
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
            background: rgba(255,255,255,0.08);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 32px 40px;
        }

        .welcome-header {
            margin-bottom: 32px;
        }
        .welcome-header h1 {
            font-size: 1.8rem;
            color: var(--midnight);
        }
        .welcome-header p {
            color: var(--gray-600);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.3s;
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

        .quick-section {
            background: var(--alabaster);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 32px;
            border: 1px solid var(--gray-200);
        }
        .quick-section h3 {
            color: var(--maroon);
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .action-btn {
            background: var(--gray-50);
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }
        .action-btn:hover {
            background: var(--maroon);
            color: white;
        }

        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 32px;
        }
        .dashboard-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gray-100);
        }
        .card-header h3 {
            font-size: 1.1rem;
            color: var(--midnight);
        }
        .card-header a {
            color: var(--maroon);
            font-size: 0.75rem;
            text-decoration: none;
        }
        .request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-submitted { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }

        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .btn-small {
            background: var(--maroon);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.7rem;
        }

        .services-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .services-card h3 {
            color: var(--midnight);
            margin-bottom: 16px;
        }
        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .service-tag {
            background: var(--alabaster);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            color: var(--maroon);
            font-weight: 500;
        }

        @media (max-width: 1000px) {
            .sidebar { width: 250px; }
            .main-content { margin-left: 250px; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <div class="sidebar">
        <div>
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>
            </div>
            
            <div class="staff-profile">
                <div class="staff-avatar"><?php echo $initials; ?></div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p><?php echo htmlspecialchars($staff_info['position'] ?? 'Staff Member'); ?></p>
                <span class="staff-badge"><i class="fas fa-user-check"></i> Staff</span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="staff-dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-divider">Appointments</li>
                <li><a href="staff/manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
                
                <?php if($has_aics_access || $has_housing_access || $has_senior_access || $has_pwd_access): ?>
                <li class="menu-divider">Services</li>
                <?php endif; ?>
                
                <?php if($has_aics_access): ?>
                <li><a href="staff/manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
                <?php endif; ?>
                <?php if($has_housing_access): ?>
                <li><a href="staff/manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
                <?php endif; ?>
                <?php if($has_senior_access && isPwdSeniorScopeActive()): ?>
                <li><a href="staff/manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
                <?php endif; ?>
                <?php if($has_pwd_access && isPwdSeniorScopeActive()): ?>
                <li><a href="staff/manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
                <?php endif; ?>
                
                <li><a href="staff/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                
                <?php if(isAdmin()): ?>
                <li class="menu-divider">Administration</li>
                <li><a href="admin/dashboard.php"><i class="fas fa-crown"></i> Admin Dashboard</a></li>
                <li><a href="admin/manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                <li><a href="admin/manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
                <?php endif; ?>
                
                <li class="menu-divider"></li>
                <li><a href="index.php"><i class="fas fa-home"></i> Back to Home</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-globe"></i> View Main Site</a>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-header">
            <h1>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👋</h1>
            <p><?php echo date('l, F d, Y'); ?></p>
        </div>

        <div class="stats-grid">
            <?php if($has_aics_access): ?>
            <div class="stat-card" onclick="window.location.href='staff/manage-aics.php?status=pending'">
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_aics'] ?? 0); ?></h3>
                    <p>Pending AICS</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='staff/manage-aics.php?status=approved'">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['approved_aics'] ?? 0); ?></h3>
                    <p>Approved AICS</p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($has_housing_access): ?>
            <div class="stat-card" onclick="window.location.href='staff/manage-housing.php?status=pending'">
                <div class="stat-icon"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_housing'] ?? 0); ?></h3>
                    <p>Pending Housing</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card" onclick="window.location.href='admin/manage-verifications.php?status=pending'">
                <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_verifications'] ?? 0); ?></h3>
                    <p>Pending Verifications</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='staff/manage-appointments.php'">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                    <p>Today's Appointments</p>
                </div>
            </div>
        </div>

        <div class="quick-section">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <?php if($has_aics_access): ?>
                <a href="staff/manage-aics.php" class="action-btn"><i class="fas fa-hand-holding-heart"></i> Process AICS</a>
                <?php endif; ?>
                <?php if($has_housing_access): ?>
                <a href="staff/manage-housing.php" class="action-btn"><i class="fas fa-home"></i> Review Housing</a>
                <?php endif; ?>
                <a href="staff/manage-appointments.php" class="action-btn"><i class="fas fa-calendar-check"></i> View Appointments</a>
                <a href="admin/manage-verifications.php" class="action-btn"><i class="fas fa-shield-alt"></i> Verify Residents</a>
                <a href="staff/reports.php" class="action-btn"><i class="fas fa-chart-bar"></i> Generate Reports</a>
            </div>
        </div>

        <div class="two-columns">
            <?php if($has_aics_access && !empty($recent_aics)): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-holding-heart"></i> Recent AICS Requests</h3>
                    <a href="staff/manage-aics.php">View all →</a>
                </div>
                <?php foreach($recent_aics as $aics): ?>
                <div class="request-item">
                    <div class="request-info">
                        <h4><?php echo htmlspecialchars($aics['first_name'] . ' ' . $aics['last_name']); ?></h4>
                        <p><?php echo date('M d, Y', strtotime($aics['submission_date'])); ?></p>
                    </div>
                    <span class="status-badge status-<?php echo $aics['status']; ?>"><?php echo ucfirst($aics['status']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if($has_housing_access && !empty($recent_housing)): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-home"></i> Recent Housing Applications</h3>
                    <a href="staff/manage-housing.php">View all →</a>
                </div>
                <?php foreach($recent_housing as $housing): ?>
                <div class="request-item">
                    <div class="request-info">
                        <h4><?php echo htmlspecialchars($housing['first_name'] . ' ' . $housing['last_name']); ?></h4>
                        <p><?php echo date('M d, Y', strtotime($housing['application_date'])); ?></p>
                    </div>
                    <span class="status-badge status-<?php echo $housing['status']; ?>"><?php echo ucfirst($housing['status']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="services-card">
            <h3><i class="fas fa-clipboard-list"></i> Your Assigned Services</h3>
            <div class="service-tags">
                <?php if(empty($assigned_categories)): ?>
                    <span class="service-tag" style="background:#ffebee; color:#e53935;">No services assigned yet. Contact administrator.</span>
                <?php else: ?>
                    <?php foreach($assigned_categories as $category): ?>
                    <span class="service-tag">
                        <?php 
                        switch($category['category_name']) {
                            case 'AICS': echo '🏥 AICS'; break;
                            case 'Housing': echo '🏠 Housing'; break;
                            case 'Senior Citizen': echo '👴 Senior Citizen'; break;
                            case 'PWD': echo '♿ PWD'; break;
                            default: echo htmlspecialchars($category['category_name']);
                        }
                        ?>
                    </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>

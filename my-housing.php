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

// Handle application withdrawal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw_application'])) {
    $beneficiary_id = sanitize($_POST['beneficiary_id'] ?? '');
    
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
                         housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\n\nApplication voluntarily withdrawn by applicant on ', NOW())
                         WHERE beneficiary_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $beneficiary_id);
        
        if ($update_stmt->execute()) {
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'Housing Application Withdrawn', 
                            'Your housing application has been withdrawn successfully as requested.', 'housing')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            
            $_SESSION['success'] = "Housing application withdrawn successfully.";
            redirect("my-housing.php");
        } else {
            $_SESSION['error'] = "Failed to withdraw application. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Application not found or is no longer withdrawable.";
    }
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

// Standardized Housing Tracking Number Helper
function getHousingTrackingNumber($beneficiary_id, $application_date = null) {
    $year = $application_date ? date('Y', strtotime($application_date)) : date('Y');
    return 'HSG-' . $year . '-' . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);
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
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
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

        .pathway-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pathway-pill.insite {
            background: rgba(46, 125, 50, 0.12);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.3);
        }
        [data-theme="dark"] .pathway-pill.insite {
            background: rgba(76, 175, 80, 0.15);
            color: #81c784;
            border-color: rgba(76, 175, 80, 0.3);
        }
        .pathway-pill.relocation {
            background: rgba(15, 65, 74, 0.12);
            color: #0F414A;
            border: 1px solid rgba(15, 65, 74, 0.25);
        }
        [data-theme="dark"] .pathway-pill.relocation {
            background: rgba(232, 213, 168, 0.15);
            color: #E8D5A8;
            border-color: rgba(232, 213, 168, 0.3);
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
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .housing-summary { flex-direction: column; text-align: left; }
            .housing-summary .summary-text { flex-direction: column; }
            .housing-summary .summary-actions { width: 100%; justify-content: flex-start; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .info-row { flex-wrap: wrap; }
            .info-row .label { min-width: 80px; }
            .card-footer { flex-direction: column; gap: 8px; }
            .btn-view { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 8px; }
            .stat-card .stat-number { font-size: 1.15rem; }
            .stat-card .stat-label { font-size: 0.65rem; }
            .application-card { margin: 0; }
            .card-body { padding: 14px 14px; }
            .card-header { padding: 12px 14px; }
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
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "My Housing Applications";
            $banner_subtitle = "Track your resettlement applications and verified community unit allocations";
            $banner_icon = "fa-city";
            $banner_actions = [
                ["label" => "Housing Programs", "url" => "housing-programs.php", "icon" => "fa-building", "type" => "primary"],
                ["label" => "Track Status", "url" => "view-housing.php", "icon" => "fa-search-location", "type" => "secondary"]
            ];
            include __DIR__ . "/includes/portal-banner.php";
            ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div style="background: rgba(76, 175, 80, 0.1); border-left: 4px solid #4CAF50; color: #2e7d32; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border-left: 4px solid #f44336; color: #c62828; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

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
                <div class="stat-label">Disapproved</div>
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
            <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): 
                $tracking_no = getHousingTrackingNumber($app['beneficiary_id'], $app['application_date']);
            ?>
            <div class="application-card status-<?php echo $app['status']; ?>">
                <div class="card-header">
                    <div>
                        <h3>
                            <i class="fas fa-building"></i> 
                            <?php echo htmlspecialchars($app['program_name']); ?>
                            <span style="font-family: monospace; font-size: 0.76rem; font-weight: 800; background: rgba(127,3,3,0.08); color: #7F0303; padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(127,3,3,0.18); margin-left: 8px;">
                                <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($tracking_no); ?>
                            </span>
                        </h3>
                        <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <?php if(($app['housing_type'] ?? 'relocation') === 'in_site'): ?>
                                <span class="pathway-pill insite">
                                    <i class="fas fa-house-chimney-user"></i> In-Site Housing
                                </span>
                                <span style="font-size: 0.72rem; color: #2e7d32; font-weight: 600;">(No Relocation Required)</span>
                            <?php else: ?>
                                <span class="pathway-pill relocation">
                                    <i class="fas fa-truck-ramp-box"></i> Relocation Housing
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
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
                    <?php if(($app['housing_type'] ?? 'relocation') === 'in_site'): ?>
                        <div class="info-row">
                            <i class="fas fa-map-location-dot" style="color: #2e7d32;"></i>
                            <span class="label">Property Address:</span>
                            <span class="value" style="font-weight: 600;"><?php echo htmlspecialchars($app['property_location'] ?: $app['location']); ?></span>
                        </div>
                        <?php if(!empty($app['land_ownership_status'])): ?>
                        <div class="info-row">
                            <i class="fas fa-file-contract" style="color: #2e7d32;"></i>
                            <span class="label">Land Tenure:</span>
                            <span class="value"><?php echo htmlspecialchars($app['land_ownership_status']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($app['lot_area'])): ?>
                        <div class="info-row">
                            <i class="fas fa-ruler-combined" style="color: #2e7d32;"></i>
                            <span class="label">Lot Area:</span>
                            <span class="value"><?php echo htmlspecialchars($app['lot_area']); ?> sq.m</span>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($app['construction_type'])): ?>
                        <div class="info-row">
                            <i class="fas fa-hammer" style="color: #2e7d32;"></i>
                            <span class="label">Construction:</span>
                            <span class="value"><?php echo htmlspecialchars($app['construction_type']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="info-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="label">Location:</span>
                            <span class="value"><?php echo htmlspecialchars($app['location']); ?></span>
                        </div>
                    <?php endif; ?>

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
                        <span class="label"><?php echo ($app['housing_type'] ?? 'relocation') === 'in_site' ? 'Lot/Grant Ref:' : 'Unit Assigned:'; ?></span>
                        <span class="value highlight"><?php echo htmlspecialchars($app['unit_assigned']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($app['interview_date'])): ?>
                    <div class="info-row">
                        <i class="fas fa-calendar-check"></i>
                        <span class="label"><?php echo ($app['housing_type'] ?? 'relocation') === 'in_site' ? 'Site Visit Date:' : 'Interview Date:'; ?></span>
                        <span class="value"><?php echo date('M d, Y', strtotime($app['interview_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer" style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
                    <a href="print-claim-stub.php?type=housing&id=<?php echo $app['beneficiary_id']; ?>" target="_blank" class="btn-view" style="background: rgba(127,3,3,0.08); color: #7F0303; border: 1px solid rgba(127,3,3,0.2);">
                        <i class="fas fa-receipt"></i> Claim Stub
                    </a>
                    <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="btn-view">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <?php if($app['status'] == 'pending' || $app['status'] == 'qualified'): ?>
                    <button type="button" onclick="openWithdrawModal(<?php echo $app['beneficiary_id']; ?>)" class="btn-withdraw" style="background: rgba(244, 67, 54, 0.08); color: #c62828; border: 1px solid rgba(244, 67, 54, 0.25); padding: 8px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
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

// ========== WITHDRAW MODAL ==========
function openWithdrawModal(id) {
    document.getElementById('withdraw_beneficiary_id').value = id;
    document.getElementById('withdrawModal').style.display = 'flex';
}

function closeWithdrawModal() {
    document.getElementById('withdrawModal').style.display = 'none';
    document.getElementById('withdraw_beneficiary_id').value = '';
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('withdrawModal');
    if (event.target === modal) {
        closeWithdrawModal();
    }
});
</script>

<!-- Withdraw Confirmation Modal -->
<div id="withdrawModal" style="position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center;">
    <div style="background: var(--card-bg, #ffffff); border-radius: var(--radius-card, 16px); width: 90%; max-width: 440px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: 1px solid var(--border-color, #E2E8F0); overflow: hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color, #E2E8F0); display: flex; justify-content: space-between; align-items: center; background: rgba(244, 67, 54, 0.06);">
            <h3 style="margin: 0; font-size: 1.1rem; color: #c62828; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-exclamation-triangle"></i> Withdraw Application
            </h3>
            <button type="button" onclick="closeWithdrawModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="beneficiary_id" id="withdraw_beneficiary_id">
            <div style="padding: 24px;">
                <p style="margin: 0 0 12px 0; color: var(--text-primary); font-size: 0.95rem; line-height: 1.5;">
                    Are you sure you want to withdraw this housing application?
                </p>
                <div style="background: rgba(255, 152, 0, 0.08); border-left: 3px solid #ff9800; padding: 10px 14px; border-radius: 6px; font-size: 0.8rem; color: var(--text-secondary);">
                    This will cancel your application from active CSWDO evaluation. You may apply again if program slots are still open.
                </div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-color, #E2E8F0); display: flex; justify-content: flex-end; gap: 10px; background: var(--card-subtle, #f8fafc);">
                <button type="button" onclick="closeWithdrawModal()" style="padding: 8px 18px; border-radius: 8px; border: 1px solid var(--border-color, #CBD5E1); background: #ffffff; color: var(--text-primary); font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" name="withdraw_application" style="padding: 8px 18px; border-radius: 8px; border: none; background: #c62828; color: #ffffff; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-trash-alt"></i> Confirm Withdrawal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

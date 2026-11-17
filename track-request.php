<?php
/**
 * track-request.php - Dedicated Native Resident Track Request Portal
 * Allows logged-in residents to track all their crisis assistance (AICS),
 * socialized housing applications, and service requests in real-time.
 */
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check resident verification status
$check_verified = "SELECT verification_status, first_name, last_name, profile_picture FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_info = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info || $user_info['verification_status'] !== 'verified') {
    redirect('verify-resident.php');
}

$user = $user_info;
$avatar_url = !empty($user['profile_picture']) && file_exists($user['profile_picture']) ? $user['profile_picture'] : '';

// Filter & Search Parameters
$search_ref = isset($_GET['search']) ? trim(sanitize($_GET['search'])) : (isset($_GET['ref']) ? trim(sanitize($_GET['ref'])) : (isset($_GET['tracking']) ? trim(sanitize($_GET['tracking'])) : ''));
$filter_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all'; // all, aics, housing
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';

// 1. Fetch Resident's AICS Requests
$all_requests = [];

if ($filter_type === 'all' || $filter_type === 'aics') {
    $aics_sql = "SELECT a.*, 'AICS Assistance' as service_cat 
                 FROM aics_requests a 
                 WHERE a.user_id = :uid";
    $aics_params = [':uid' => $_SESSION['user_id']];

    if (!empty($search_ref)) {
        $aics_sql .= " AND (CONCAT('AICS-', LPAD(a.aics_id, 6, '0')) LIKE :s OR a.request_type LIKE :s OR a.purpose LIKE :s)";
        $aics_params[':s'] = "%$search_ref%";
    }
    if ($filter_status !== 'all') {
        if ($filter_status === 'pending') {
            $aics_sql .= " AND a.status IN ('submitted', 'reviewing', 'pending')";
        } elseif ($filter_status === 'approved') {
            $aics_sql .= " AND a.status IN ('approved', 'released')";
        } elseif ($filter_status === 'rejected') {
            $aics_sql .= " AND a.status IN ('rejected', 'disapproved')";
        } else {
            $aics_sql .= " AND a.status = :st";
            $aics_params[':st'] = $filter_status;
        }
    }
    $aics_sql .= " ORDER BY a.submission_date DESC";
    $aics_stmt = $db->prepare($aics_sql);
    $aics_stmt->execute($aics_params);
    
    while ($row = $aics_stmt->fetch(PDO::FETCH_ASSOC)) {
        $tracking_no = 'AICS-' . str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT);
        $all_requests[] = [
            'id' => $row['aics_id'],
            'category' => 'AICS',
            'tracking_no' => $tracking_no,
            'title' => ucfirst(str_replace('_', ' ', $row['request_type'] ?? 'Assistance')) . ' Assistance',
            'subtitle' => 'Crisis Intervention Division (CSWDO Main)',
            'status' => $row['status'],
            'date_submitted' => $row['submission_date'],
            'date_updated' => $row['approval_date'] ?? $row['review_date'] ?? $row['submission_date'],
            'amount_requested' => $row['amount_requested'],
            'amount_approved' => $row['amount_approved'],
            'purpose' => $row['purpose'],
            'rejection_reason' => $row['rejection_reason'] ?? '',
            'view_link' => 'view-aics.php?id=' . $row['aics_id']
        ];
    }
}

// 2. Fetch Resident's Housing Applications
if ($filter_type === 'all' || $filter_type === 'housing') {
    $hsg_sql = "SELECT h.*, 'Housing Programs' as service_cat, p.program_name 
                FROM housing_beneficiaries h 
                LEFT JOIN housing_programs p ON h.program_id = p.program_id 
                WHERE h.user_id = :uid";
    $hsg_params = [':uid' => $_SESSION['user_id']];

    if (!empty($search_ref)) {
        $hsg_sql .= " AND (CONCAT('HSG-2026-', LPAD(h.beneficiary_id, 5, '0')) LIKE :s OR h.housing_type LIKE :s OR p.program_name LIKE :s)";
        $hsg_params[':s'] = "%$search_ref%";
    }
    if ($filter_status !== 'all') {
        if ($filter_status === 'pending') {
            $hsg_sql .= " AND h.status IN ('pending', 'qualified', 'for_review')";
        } elseif ($filter_status === 'approved') {
            $hsg_sql .= " AND h.status IN ('approved', 'released', 'awarded')";
        } elseif ($filter_status === 'rejected') {
            $hsg_sql .= " AND h.status IN ('rejected', 'disapproved')";
        } else {
            $hsg_sql .= " AND h.status = :st";
            $hsg_params[':st'] = $filter_status;
        }
    }
    $hsg_sql .= " ORDER BY h.application_date DESC";
    $hsg_stmt = $db->prepare($hsg_sql);
    $hsg_stmt->execute($hsg_params);
    
    while ($row = $hsg_stmt->fetch(PDO::FETCH_ASSOC)) {
        $tracking_no = 'HSG-2026-' . str_pad($row['beneficiary_id'], 5, '0', STR_PAD_LEFT);
        $prog_name = !empty($row['program_name']) ? $row['program_name'] : ('Socialized Housing (' . ucfirst($row['housing_type'] ?? 'Relocation') . ')');
        $all_requests[] = [
            'id' => $row['beneficiary_id'],
            'category' => 'Housing',
            'tracking_no' => $tracking_no,
            'title' => $prog_name,
            'subtitle' => 'Housing & Resettlement Division',
            'status' => $row['status'],
            'date_submitted' => $row['application_date'],
            'date_updated' => $row['approval_date'] ?? $row['application_date'],
            'amount_requested' => null,
            'amount_approved' => null,
            'purpose' => $row['housing_need_assessment'] ?? 'Housing Beneficiary Application',
            'rejection_reason' => '',
            'view_link' => 'view-housing.php?id=' . $row['beneficiary_id']
        ];
    }
}

// Sort all combined requests by date desc
usort($all_requests, function($a, $b) {
    return strtotime($b['date_submitted']) - strtotime($a['date_submitted']);
});

// Count summary statistics
$stat_counts = [
    'total' => count($all_requests),
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'rejected' => 0
];
foreach ($all_requests as $r) {
    $st = strtolower($r['status']);
    if (in_array($st, ['submitted', 'reviewing', 'pending', 'qualified', 'for_review'])) {
        $stat_counts['pending']++;
    } elseif (in_array($st, ['approved', 'scheduled'])) {
        $stat_counts['approved']++;
    } elseif (in_array($st, ['released', 'completed', 'awarded'])) {
        $stat_counts['completed']++;
    } elseif (in_array($st, ['rejected', 'disapproved', 'declined'])) {
        $stat_counts['rejected']++;
    }
}

$portal_current_page = 'track-request.php';
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Requests - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Courier+Prime:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        :root {
            --ref-canvas: #E4EFE7;
            --ref-card: #FFFFFF;
            --ref-card-subtle: #F7FAF8;
            --ref-text: #192126;
            --ref-muted: #71818B;
            --ref-border: rgba(0, 0, 0, 0.07);
            --ref-maroon: #7F0303;
            --ref-maroon-dark: #650000;
        }

        [data-theme="dark"] {
            --ref-canvas: #0C1417;
            --ref-card: #152227;
            --ref-card-subtle: #19272E;
            --ref-text: #E5EFF2;
            --ref-muted: #8E9FA5;
            --ref-border: rgba(255, 255, 255, 0.08);
            --ref-maroon: #9A1515;
            --ref-maroon-dark: #7F0303;
        }

        .portal-main-wrap {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Subheader Banner */
        .track-header-card {
            background: var(--ref-card);
            border-radius: 26px;
            padding: 24px 28px;
            border: 1px solid var(--ref-border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .track-title-left h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--ref-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .track-title-left p {
            margin: 4px 0 0;
            font-size: 0.85rem;
            color: var(--ref-muted);
        }

        /* Search & Filter Bar */
        .track-filter-bar {
            background: var(--ref-card);
            border-radius: 20px;
            padding: 16px 20px;
            border: 1px solid var(--ref-border);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .track-search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            border-radius: 30px;
            padding: 4px 14px;
            flex: 1;
            min-width: 260px;
            max-width: 460px;
        }

        .track-search-box input {
            border: none;
            background: transparent;
            font-size: 0.84rem;
            color: var(--ref-text);
            width: 100%;
            padding: 8px 0;
            outline: none;
        }

        .track-filter-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-pill-opt {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--ref-muted);
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            transition: all 0.18s ease;
        }

        .filter-pill-opt:hover {
            color: #7F0303;
            border-color: #7F0303;
        }

        .filter-pill-opt.active {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
        }

        /* Requests Cards List */
        .track-cards-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .request-track-card {
            background: var(--ref-card);
            border-radius: 22px;
            padding: 22px 24px;
            border: 1px solid var(--ref-border);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .request-track-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border-color: rgba(127, 3, 3, 0.2);
        }

        .req-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .req-id-badge-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .req-type-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .req-icon-aics { background: #FEF3C7; color: #D97706; }
        .req-icon-housing { background: #DBEAFE; color: #2563EB; }

        .req-tracking-number {
            font-family: 'Courier Prime', monospace;
            font-weight: 700;
            font-size: 0.96rem;
            color: #7F0303;
            letter-spacing: 0.05em;
        }

        .req-card-title {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--ref-text);
            margin: 2px 0 0;
        }

        .req-card-sub {
            font-size: 0.74rem;
            color: var(--ref-muted);
        }

        /* Status Badges */
        .req-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .st-submitted, .st-pending, .st-reviewing, .st-qualified {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #FDE68A;
        }

        .st-approved, .st-confirmed, .st-awarded {
            background: #DCFCE7;
            color: #15803D;
            border: 1px solid #BBF7D0;
        }

        .st-released, .st-completed {
            background: #E0E7FF;
            color: #4338CA;
            border: 1px solid #C7D2FE;
        }

        .st-rejected, .st-disapproved, .st-declined {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECDD3;
        }

        /* Step Progress Line - Animated Multi-Stage Timeline */
        .req-timeline-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            padding: 10px 12px 0;
            margin: 6px 0 12px;
        }

        .timeline-progress-track {
            position: absolute;
            top: 22px;
            left: 36px;
            right: 36px;
            height: 4px;
            background: var(--ref-canvas, #E4EFE7);
            border-radius: 999px;
            z-index: 1;
            overflow: hidden;
        }

        .timeline-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981 0%, #7F0303 100%);
            border-radius: 999px;
            box-shadow: 0 0 10px rgba(127, 3, 3, 0.45);
            transition: width 0.9s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .timeline-progress-fill.is-rejected {
            background: linear-gradient(90deg, #F59E0B 0%, #EF4444 100%);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.45);
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 2;
        }

        .step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--ref-card);
            border: 2px solid var(--ref-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--ref-muted);
            transition: all 0.3s ease;
        }

        .timeline-step.completed .step-circle {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            border-color: #FFFFFF;
            color: #FFFFFF;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2), 0 0 12px rgba(16, 185, 129, 0.4);
        }

        @keyframes stepPulseRing {
            0% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0.5), 0 0 10px rgba(220, 38, 38, 0.6);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(127, 3, 3, 0), 0 0 18px rgba(220, 38, 38, 0.85);
                transform: scale(1.1);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0), 0 0 10px rgba(220, 38, 38, 0.6);
                transform: scale(1);
            }
        }

        .timeline-step.active .step-circle {
            background: linear-gradient(135deg, #7F0303 0%, #B91C1C 100%);
            border-color: #FFFFFF;
            color: #FFFFFF;
            animation: stepPulseRing 2s infinite ease-in-out;
        }

        .timeline-step.disapproved .step-circle {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            border-color: #FFFFFF;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }

        .step-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--ref-muted);
            text-align: center;
            white-space: nowrap;
        }

        .timeline-step.active .step-label,
        .timeline-step.completed .step-label {
            color: var(--ref-text);
            font-weight: 700;
        }

        .step-sublabel {
            font-size: 0.64rem;
            color: var(--ref-muted);
            text-align: center;
            max-width: 130px;
            line-height: 1.25;
            display: inline-block;
        }
        .timeline-step.active .step-sublabel {
            color: #7F0303;
            font-weight: 700;
        }
        .timeline-step.completed .step-sublabel {
            color: #059669;
        }

        /* Bottom Details & Actions */
        .req-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--ref-border);
            padding-top: 14px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .req-meta-chips {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.74rem;
            color: var(--ref-muted);
            flex-wrap: wrap;
        }

        .req-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .req-actions-btns {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-track-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            color: var(--ref-text);
            transition: all 0.18s ease;
            cursor: pointer;
        }

        .btn-track-action:hover {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
        }

        .empty-requests-view {
            text-align: center;
            padding: 60px 20px;
            background: var(--ref-card);
            border-radius: 26px;
            border: 1px solid var(--ref-border);
        }

        .empty-requests-view i {
            font-size: 3rem;
            color: var(--ref-muted);
            opacity: 0.35;
            margin-bottom: 14px;
            display: block;
        }

        .empty-requests-view h3 {
            font-size: 1.15rem;
            color: var(--ref-text);
            margin: 0 0 6px;
        }

        .empty-requests-view p {
            color: var(--ref-muted);
            font-size: 0.85rem;
            margin: 0 0 18px;
        }

        /* Modal Details View */
        .track-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2500;
            padding: 16px;
            backdrop-filter: blur(4px);
        }

        .track-modal.active { display: flex; }

        .track-modal-box {
            background: var(--ref-card);
            border-radius: 24px;
            max-width: 580px;
            width: 100%;
            border: 1px solid var(--ref-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            animation: modalPopIn 0.2s ease-out;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .track-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid var(--ref-border);
        }

        .track-modal-head h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--ref-text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .track-modal-body {
            padding: 22px 24px;
            overflow-y: auto;
            max-height: 75vh;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--ref-border);
            font-size: 0.82rem;
        }

        .detail-label { color: var(--ref-muted); font-weight: 600; }
        .detail-val { color: var(--ref-text); font-weight: 700; text-align: right; }

        @media (max-width: 640px) {
            .req-timeline-steps { display: none; }
            .track-search-box { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Native Resident Portal Sidebar -->
        <?php include_once __DIR__ . '/includes/portal-sidebar.php'; ?>

        <main class="main-content">
            <div class="portal-main-wrap">
                <!-- Native Resident Portal Header -->
                <?php include_once __DIR__ . '/includes/portal-header.php'; ?>

                <!-- Universal Top Hero Banner -->
                  <?php
                  if ($filter_type === 'housing') {
                      $banner_title = 'Track Housing Applications';
                      $banner_subtitle = 'Monitor real-time evaluation, verification and unit allocation for your housing applications';
                      $banner_icon = 'fa-house-chimney';
                      $banner_actions = [
                          ['label' => 'Housing Programs', 'url' => 'housing-programs.php', 'icon' => 'fa-city', 'type' => 'primary'],
                          ['label' => 'My Applications', 'url' => 'my-housing.php', 'icon' => 'fa-home', 'type' => 'secondary']
                      ];
                  } else {
                      $banner_title = 'Track Your Requests';
                      $banner_subtitle = 'Monitor real-time case processing status for your crisis assistance and housing applications';
                      $banner_icon = 'fa-search-location';
                      $banner_actions = [
                          ['label' => 'New Case Request', 'url' => 'aics-requests.php', 'icon' => 'fa-plus-circle', 'type' => 'primary'],
                          ['label' => 'Housing Status', 'url' => 'my-housing.php', 'icon' => 'fa-house-chimney', 'type' => 'secondary']
                      ];
                  }
                  include __DIR__ . '/includes/portal-banner.php';
                  ?>

                <!-- Search and Filter Bar -->
                <div class="track-filter-bar">
                    <form method="GET" action="track-request.php" class="track-search-box">
                        <i class="fas fa-search" style="color: var(--ref-muted);"></i>
                        <input type="text" name="search" placeholder="Enter Tracking # (e.g. AICS-000035, HSG-2026-00007)..." value="<?php echo htmlspecialchars($search_ref); ?>">
                        <?php if ($filter_type !== 'all'): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>"><?php endif; ?>
                        <?php if ($filter_status !== 'all'): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>"><?php endif; ?>
                        <?php if (!empty($search_ref)): ?>
                            <a href="track-request.php" style="color: var(--ref-muted); text-decoration: none;" title="Clear search">&times;</a>
                        <?php endif; ?>
                    </form>

                    <div class="track-filter-pills">
                        <a href="track-request.php?type=all<?php echo !empty($search_ref)?'&search='.urlencode($search_ref):''; ?>" class="filter-pill-opt <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                            All Requests (<?php echo count($all_requests); ?>)
                        </a>
                        <a href="track-request.php?type=aics<?php echo !empty($search_ref)?'&search='.urlencode($search_ref):''; ?>" class="filter-pill-opt <?php echo $filter_type === 'aics' ? 'active' : ''; ?>">
                            <i class="fas fa-hand-holding-heart"></i> AICS
                        </a>
                        <a href="track-request.php?type=housing<?php echo !empty($search_ref)?'&search='.urlencode($search_ref):''; ?>" class="filter-pill-opt <?php echo $filter_type === 'housing' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> Housing
                        </a>
                    </div>
                </div>

                <!-- Requests List -->
                <?php if (!empty($all_requests)): ?>
                    <div class="track-cards-stack">
                        <?php foreach ($all_requests as $req): 
                            $st = strtolower($req['status']);
                            $is_disapproved = in_array($st, ['rejected', 'disapproved', 'declined']);
                            
                            // Calculate timeline step indices (1 to 4)
                            // 1: Submitted, 2: Reviewing/Assessing, 3: Approved, 4: Released/Awarded
                            $current_step = 1;
                            if (in_array($st, ['reviewing', 'qualified', 'for_review'])) $current_step = 2;
                            if (in_array($st, ['approved', 'scheduled'])) $current_step = 3;
                            if (in_array($st, ['released', 'completed', 'awarded'])) $current_step = 4;
                            if ($is_disapproved) $current_step = 2; // stops at assessment
                            
                            $progress_pct = 10;
                            if ($current_step === 2) $progress_pct = 36;
                            if ($current_step === 3) $progress_pct = 68;
                            if ($current_step >= 4) $progress_pct = 100;
                        ?>
                            <div class="request-track-card">
                                <div class="req-card-top">
                                    <div class="req-id-badge-wrap">
                                        <div class="req-type-icon <?php echo $req['category'] === 'AICS' ? 'req-icon-aics' : 'req-icon-housing'; ?>">
                                            <i class="fas <?php echo $req['category'] === 'AICS' ? 'fa-hand-holding-heart' : 'fa-home'; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="req-tracking-number"><?php echo htmlspecialchars($req['tracking_no']); ?></div>
                                            <h3 class="req-card-title"><?php echo htmlspecialchars($req['title']); ?></h3>
                                            <span class="req-card-sub"><?php echo htmlspecialchars($req['subtitle']); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="req-status-badge st-<?php echo $st; ?>">
                                            <?php if ($is_disapproved): ?>
                                                <i class="fas fa-times-circle"></i> Disapproved / Declined
                                            <?php elseif (in_array($st, ['approved', 'confirmed'])): ?>
                                                <i class="fas fa-check-circle"></i> Approved
                                            <?php elseif (in_array($st, ['released', 'completed', 'awarded'])): ?>
                                                <i class="fas fa-award"></i> Completed &amp; Released
                                            <?php else: ?>
                                                <i class="fas fa-clock"></i> In Process (<?php echo ucfirst($st); ?>)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Multi-Step Timeline (Enhanced 4-Stage Glowing Timeline for AICS, Standard for Housing) -->
                                <?php if ($req['category'] === 'AICS'): ?>
                                    <?php
                                        // AICS 4-Stage Workflow:
                                        // 1: Documents (Verification)
                                        // 2: Staff Interview ("Please proceed to office for staff interview")
                                        // 3: SCSR & Budget ("Submission to City Budget Office")
                                        // 4: Release / Award
                                        $aics_step = 1;
                                        if (in_array($st, ['submitted', 'pending'])) $aics_step = 1;
                                        if (in_array($st, ['reviewing', 'for_review', 'under_review', 'interview', 'qualified'])) $aics_step = 2;
                                        if (in_array($st, ['approved', 'scheduled'])) $aics_step = 3;
                                        if (in_array($st, ['released', 'completed', 'disbursed', 'awarded'])) $aics_step = 4;
                                        if ($is_disapproved) $aics_step = 1;

                                        $aics_pct = 12;
                                        if ($aics_step === 2) $aics_pct = 42;
                                        if ($aics_step === 3) $aics_pct = 74;
                                        if ($aics_step >= 4) $aics_pct = 100;
                                    ?>
                                    <div class="req-timeline-steps">
                                        <div class="timeline-progress-track">
                                            <div class="timeline-progress-fill <?php echo $is_disapproved ? 'is-rejected' : ''; ?>" style="width: <?php echo $aics_pct; ?>%;"></div>
                                        </div>
                                        <div class="timeline-step <?php echo $is_disapproved ? 'disapproved' : ($aics_step >= 1 ? ($aics_step === 1 ? 'active' : 'completed') : ''); ?>">
                                            <div class="step-circle"><?php echo $is_disapproved ? '<i class="fas fa-times"></i>' : ($aics_step > 1 ? '<i class="fas fa-check"></i>' : '1'); ?></div>
                                            <span class="step-label">1. Documents</span>
                                        </div>
                                        <div class="timeline-step <?php echo $aics_step >= 2 ? ($aics_step === 2 ? 'active' : 'completed') : ''; ?>" title="Please proceed to office for staff interview">
                                            <div class="step-circle"><?php echo $aics_step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                                            <span class="step-label">2. Staff Interview</span>
                                            <span class="step-sublabel"><i class="fas fa-person-walking"></i> Please proceed to office for staff interview</span>
                                        </div>
                                        <div class="timeline-step <?php echo $aics_step >= 3 ? ($aics_step === 3 ? 'active' : 'completed') : ''; ?>" title="Submission to City Budget Office">
                                            <div class="step-circle"><?php echo $aics_step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                                            <span class="step-label">3. SCSR &amp; Budget</span>
                                            <span class="step-sublabel"><i class="fas fa-landmark"></i> Submission to City Budget Office</span>
                                        </div>
                                        <div class="timeline-step <?php echo $aics_step >= 4 ? 'completed' : ''; ?>">
                                            <div class="step-circle"><?php echo $aics_step >= 4 ? '<i class="fas fa-check"></i>' : '4'; ?></div>
                                            <span class="step-label">4. Release / Award</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Housing 4-Stage Timeline -->
                                    <div class="req-timeline-steps">
                                        <div class="timeline-progress-track">
                                            <div class="timeline-progress-fill <?php echo $is_disapproved ? 'is-rejected' : ''; ?>" style="width: <?php echo $progress_pct; ?>%;"></div>
                                        </div>
                                        <div class="timeline-step <?php echo $current_step >= 1 ? 'completed' : ''; ?>">
                                            <div class="step-circle"><i class="fas fa-check"></i></div>
                                            <span class="step-label">1. Submitted</span>
                                        </div>
                                        <div class="timeline-step <?php echo $is_disapproved ? 'disapproved' : ($current_step >= 2 ? ($current_step === 2 ? 'active' : 'completed') : ''); ?>">
                                            <div class="step-circle"><?php echo $is_disapproved ? '<i class="fas fa-times"></i>' : ($current_step > 2 ? '<i class="fas fa-check"></i>' : '2'); ?></div>
                                            <span class="step-label">2. Case Review</span>
                                        </div>
                                        <div class="timeline-step <?php echo $current_step >= 3 ? ($current_step === 3 ? 'active' : 'completed') : ''; ?>">
                                            <div class="step-circle"><?php echo $current_step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                                            <span class="step-label">3. Assessment</span>
                                        </div>
                                        <div class="timeline-step <?php echo $current_step >= 4 ? 'completed' : ''; ?>">
                                            <div class="step-circle"><?php echo $current_step >= 4 ? '<i class="fas fa-check"></i>' : '4'; ?></div>
                                            <span class="step-label">4. Release / Award</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_disapproved): ?>
                                    <div class="disapproved-callout" style="background: #FEE2E2; border: 1px solid #FECDD3; color: #991B1B; padding: 10px 14px; border-radius: 12px; font-size: 0.8rem; margin: 10px 0 6px; display: flex; align-items: flex-start; gap: 8px;">
                                        <i class="fas fa-exclamation-triangle" style="margin-top: 2px;"></i>
                                        <div>
                                            <strong>Application Disapproved:</strong>
                                            <span><?php echo htmlspecialchars($req['rejection_reason'] ?: 'Requirements not met or duplicate application.'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Bottom Row -->
                                <div class="req-card-bottom">
                                    <div class="req-meta-chips">
                                        <span class="req-meta-chip">
                                            <i class="fas fa-calendar-alt"></i> Submitted: <?php echo date('M d, Y', strtotime($req['date_submitted'])); ?>
                                        </span>
                                        <span class="req-meta-chip">
                                            <i class="fas fa-history"></i> Latest Update: <?php echo date('M d, Y', strtotime($req['date_updated'])); ?>
                                        </span>
                                        <?php if (!empty($req['amount_approved'])): ?>
                                            <span class="req-meta-chip" style="color: #059669; font-weight: 700;">
                                                <i class="fas fa-peso-sign"></i> Approved: ₱<?php echo number_format($req['amount_approved'], 2); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="req-actions-btns">
                                        <button type="button" class="btn-track-action" onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES); ?>)">
                                            <i class="fas fa-info-circle"></i> Quick Overview
                                        </button>
                                        <a href="<?php echo htmlspecialchars($req['view_link']); ?>" class="btn-track-action" style="background: rgba(127, 3, 3, 0.08); color: #7F0303; border-color: rgba(127, 3, 3, 0.2);">
                                            <span>Full Case Record</span> <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-requests-view">
                        <i class="fas fa-search-location"></i>
                        <h3>No Application Records Found</h3>
                        <p>You have not submitted any active requests matching this search criteria.</p>
                        <a href="aics-requests.php" class="btn-track-action" style="background:#7F0303; color:white; border:none; padding:10px 24px; border-radius:50px;">
                            <i class="fas fa-hand-holding-heart"></i> Apply for AICS Assistance
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="track-modal" onclick="closeDetailsModal(event)">
        <div class="track-modal-box" onclick="event.stopPropagation()">
            <div class="track-modal-head">
                <h3><i class="fas fa-file-invoice" style="color: #7F0303;"></i> <span id="mRef">Application Details</span></h3>
                <button type="button" class="modal-close-btn" onclick="closeDetailsModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer; color:var(--ref-muted);">&times;</button>
            </div>
            <div class="track-modal-body" id="mBody">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

    <script>
    function openDetailsModal(data) {
        const modal = document.getElementById('detailsModal');
        const refEl = document.getElementById('mRef');
        const bodyEl = document.getElementById('mBody');
        if (!modal || !refEl || !bodyEl) return;

        refEl.textContent = data.tracking_no || 'Application Details';
        bodyEl.innerHTML = `
            <div class="detail-row">
                <span class="detail-label">Service Category</span>
                <span class="detail-val">${escapeHtml(data.category)} &bull; ${escapeHtml(data.title)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Office Desk</span>
                <span class="detail-val">${escapeHtml(data.subtitle)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Processing Status</span>
                <span class="detail-val" style="text-transform: capitalize; color: #7F0303;">${escapeHtml(data.status)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Submission Date</span>
                <span class="detail-val">${escapeHtml(data.date_submitted)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Latest Case Update</span>
                <span class="detail-val">${escapeHtml(data.date_updated)}</span>
            </div>
            ${data.amount_requested ? `
            <div class="detail-row">
                <span class="detail-label">Amount Requested</span>
                <span class="detail-val">&#8369;${Number(data.amount_requested).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
            ` : ''}
            ${data.amount_approved ? `
            <div class="detail-row">
                <span class="detail-label">Approved Assistance</span>
                <span class="detail-val" style="color:#059669;">&#8369;${Number(data.amount_approved).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
            ` : ''}
            ${data.purpose ? `
            <div style="margin-top: 6px;">
                <span class="detail-label" style="display:block; margin-bottom: 4px;">Assistance Purpose / Assessment Notes:</span>
                <div style="background:var(--ref-canvas); padding:10px 14px; border-radius:12px; font-size:0.78rem; color:var(--ref-text);">
                    ${escapeHtml(data.purpose)}
                </div>
            </div>
            ` : ''}
            ${data.rejection_reason ? `
            <div style="margin-top: 6px;">
                <span class="detail-label" style="display:block; margin-bottom: 4px; color:#DC2626;">Disapproval Remarks:</span>
                <div style="background:#FEE2E2; color:#991B1B; padding:10px 14px; border-radius:12px; font-size:0.78rem;">
                    ${escapeHtml(data.rejection_reason)}
                </div>
            </div>
            ` : ''}
            <div style="margin-top: 14px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="${escapeHtml(data.view_link)}" class="btn-track-action" style="background:#7F0303; color:white; border:none; padding:8px 18px;">
                    Open Full Case File <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        `;

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailsModal(e) {
        if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close-btn')) return;
        const modal = document.getElementById('detailsModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDetailsModal();
    });
    </script>
</body>
</html>

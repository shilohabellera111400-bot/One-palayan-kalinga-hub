<?php
/**
 * housing-programs.php - Dedicated Authenticated Housing Programs Portal
 * Allows logged-in residents to view available socialized housing and resettlement
 * programs, check eligibility criteria, and apply directly within the authenticated dashboard.
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
$user_id = (int)$_SESSION['user_id'];

// Check user details and verification status
$user_stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('login.php');
}

$verification_status = $user['verification_status'] ?? 'pending';
$avatar_url = !empty($user['profile_picture']) && file_exists($user['profile_picture']) ? $user['profile_picture'] : '';
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Fetch active housing programs from database
$prog_stmt = $db->prepare("SELECT * FROM housing_programs WHERE status = 'active' ORDER BY program_name ASC");
$prog_stmt->execute();
$programs = $prog_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch occupied counts per program
$occupied_counts = [];
try {
    $occ_stmt = $db->query("SELECT program_id, COUNT(*) as cnt FROM housing_beneficiaries WHERE status IN ('approved', 'released', 'qualified') GROUP BY program_id");
    while ($r = $occ_stmt->fetch(PDO::FETCH_ASSOC)) {
        $occupied_counts[$r['program_id']] = (int)$r['cnt'];
    }
} catch (Exception $e) {}

// Check resident's existing applications
$resident_apps = [];
try {
    $ra_stmt = $db->prepare("SELECT program_id, status, beneficiary_id FROM housing_beneficiaries WHERE user_id = :uid");
    $ra_stmt->execute([':uid' => $user_id]);
    while ($ra = $ra_stmt->fetch(PDO::FETCH_ASSOC)) {
        $resident_apps[$ra['program_id']] = $ra;
    }
} catch (Exception $e) {}

$total_units_sum = 0;
$total_available_sum = 0;
foreach ($programs as $p) {
    $tot = (int)($p['total_units'] ?? 0);
    $occ = $occupied_counts[$p['program_id']] ?? 0;
    $total_units_sum += $tot;
    $total_available_sum += max(0, $tot - $occ);
}

$active_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Housing Programs - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        :root {
            --ref-canvas: #E4EFE7;
            --ref-card: #FFFFFF;
            --ref-text: #192126;
            --ref-muted: #71818B;
            --ref-border: rgba(0, 0, 0, 0.06);
            --ref-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            --maroon: #7F0303;
            --maroon-light: #9A1515;
            --khaki-accent: #FEF7DF;
            --blue-accent: #E7F1FE;
        }

        [data-theme="dark"] {
            --ref-canvas: #0C1417;
            --ref-card: #152227;
            --ref-text: #E5EFF2;
            --ref-muted: #8AA0A9;
            --ref-border: rgba(255, 255, 255, 0.08);
            --ref-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            --khaki-accent: #292415;
            --blue-accent: #142435;
        }

        body {
            background-color: var(--ref-canvas, #E4EFE7) !important;
            font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
            color: var(--ref-text) !important;
            min-height: 100vh;
            padding: 16px 20px 48px !important;
            margin: 0 !important;
            box-sizing: border-box;
        }

        .dashboard-container {
            width: 100% !important;
            display: flex !important;
            gap: 16px !important;
            align-items: flex-start !important;
        }

        .dashboard-main {
            flex: 1 1 0% !important;
            min-width: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px;
        }

        /* Subheader Bar */
        .dash-subheader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 4px 4px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .subheader-title-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-back-arrow {
            color: var(--ref-text);
            text-decoration: none;
            font-size: 1.15rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--ref-card);
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
            transition: all 0.2s ease;
        }

        .btn-back-arrow:hover {
            transform: translateX(-3px);
            color: var(--maroon);
            border-color: rgba(127, 3, 3, 0.3);
        }

        .subheader-title-wrap h1 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--ref-text);
            letter-spacing: -0.02em;
            margin: 0;
        }

        .subheader-title-wrap p {
            font-size: 0.8rem;
            color: var(--ref-muted);
            margin: 2px 0 0;
        }

        /* Stats Row */
        .housing-stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .housing-stat-card {
            background: var(--ref-card);
            border-radius: 20px;
            padding: 18px 20px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .housing-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-icon-programs { background: #FEF7DF; color: #B45309; }
        .stat-icon-units { background: #DCFCE7; color: #15803D; }
        .stat-icon-apps { background: #E0E7FF; color: #4338CA; }

        .housing-stat-info h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--ref-text);
            margin: 0;
            line-height: 1.2;
        }

        .housing-stat-info p {
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--ref-muted);
            margin: 2px 0 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Filter Toolbar */
        .programs-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--ref-card);
            border-radius: 20px;
            padding: 10px 16px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
        }

        .segmented-filter-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--ref-canvas);
            padding: 4px;
            border-radius: 50px;
            border: 1px solid var(--ref-border);
        }

        .seg-filter-btn {
            border: none;
            background: transparent;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--ref-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .seg-filter-btn:hover {
            color: var(--ref-text);
        }

        .seg-filter-btn.active {
            background: #FFFFFF;
            color: var(--maroon);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        [data-theme="dark"] .seg-filter-btn.active {
            background: #1E2D33;
            color: #FFFFFF;
        }

        .filter-search-box {
            position: relative;
            min-width: 240px;
        }

        .filter-search-box input {
            width: 100%;
            height: 38px;
            padding: 0 14px 0 36px;
            border-radius: 50px;
            border: 1px solid var(--ref-border);
            background: var(--ref-canvas);
            color: var(--ref-text);
            font-size: 0.8rem;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .filter-search-box input:focus {
            border-color: var(--maroon);
        }

        .filter-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ref-muted);
            font-size: 0.8rem;
        }

        /* Programs Grid */
        .programs-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 18px;
        }

        .housing-card {
            background: var(--ref-card);
            border-radius: 24px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .housing-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.07);
        }

        .housing-card-banner {
            padding: 18px 20px;
            background: linear-gradient(135deg, #1C242B 0%, #0F171E 100%);
            color: #FFFFFF;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .housing-card-banner.insite {
            background: linear-gradient(135deg, #064E3B 0%, #065F46 100%);
        }

        .housing-card-banner.relocation {
            background: linear-gradient(135deg, #7F0303 0%, #5B0202 100%);
        }

        .card-banner-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .banner-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #FFFFFF;
        }

        .card-banner-meta h4 {
            font-size: 0.96rem;
            font-weight: 800;
            margin: 0;
            color: #FFFFFF;
        }

        .card-banner-meta span {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 600;
        }

        .program-type-tag {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.2);
            color: #FFFFFF;
            letter-spacing: 0.03em;
        }

        .housing-card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
        }

        .program-location-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--ref-muted);
            font-weight: 600;
        }

        .program-location-bar i {
            color: var(--maroon);
        }

        .program-description-text {
            font-size: 0.84rem;
            color: var(--ref-text);
            line-height: 1.5;
            margin: 0;
            flex: 1;
        }

        /* Slots Bar */
        .slots-progress-box {
            background: var(--ref-canvas);
            border-radius: 14px;
            padding: 12px 14px;
            border: 1px solid var(--ref-border);
        }

        .slots-label-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.74rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--ref-text);
        }

        .slots-track {
            height: 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        [data-theme="dark"] .slots-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .slots-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            border-radius: 10px;
        }

        /* Requirements Highlights */
        .requirements-mini-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .requirements-mini-list li {
            font-size: 0.76rem;
            color: var(--ref-muted);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .requirements-mini-list li i {
            color: #10B981;
            font-size: 0.75rem;
        }

        /* Action Buttons */
        .housing-card-footer {
            padding: 16px 20px 20px;
            border-top: 1px solid var(--ref-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-apply-program {
            background: var(--maroon);
            color: #FFFFFF !important;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .btn-apply-program:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            color: #FFFFFF;
        }

        .btn-program-guide {
            background: var(--ref-canvas);
            color: var(--ref-text);
            padding: 9px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--ref-border);
            transition: all 0.2s ease;
        }

        .btn-program-guide:hover {
            border-color: var(--maroon);
            color: var(--maroon);
        }

        .badge-applied-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 800;
            background: #DCFCE7;
            color: #15803D;
        }

        @media (max-width: 820px) {
            .programs-filter-bar {
                padding: 8px 10px !important;
                gap: 8px !important;
                border-radius: 14px !important;
            }
            .segmented-filter-group {
                width: 100% !important;
                overflow-x: auto !important;
                padding: 2px !important;
                gap: 4px !important;
                white-space: nowrap !important;
            }
            .seg-filter-btn {
                padding: 4px 10px !important;
                font-size: 0.72rem !important;
                white-space: nowrap !important;
            }
            .filter-search-box {
                min-width: 100% !important;
                width: 100% !important;
            }
            .filter-search-box input {
                height: 32px !important;
                font-size: 0.75rem !important;
                padding: 0 10px 0 28px !important;
            }
            .filter-search-box i {
                left: 10px !important;
                font-size: 0.72rem !important;
            }
            .programs-cards-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .housing-card {
                border-radius: 16px !important;
            }
            .card-hero-banner {
                padding: 12px 14px !important;
            }
            .banner-code-badge {
                font-size: 0.85rem !important;
            }
            .card-body-content {
                padding: 12px 14px !important;
            }
            .card-body-content h3 {
                font-size: 0.95rem !important;
            }
            .card-desc-text {
                font-size: 0.75rem !important;
                line-height: 1.4 !important;
            }
            .card-stat-pill {
                padding: 4px 8px !important;
                font-size: 0.68rem !important;
            }
            .btn-action-view {
                padding: 7px 14px !important;
                font-size: 0.76rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Authenticated Resident Sidebar -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php require_once __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <main class="dashboard-main <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
            <!-- Authenticated Resident Header -->
            <?php require_once __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Available Housing Programs & Communities';
            $banner_subtitle = 'Official resettlement and in-site shelter assistance by CSWDO Palayan City';
            $banner_icon = 'fa-city';
            $first_app_id = !empty($resident_apps) ? reset($resident_apps)['beneficiary_id'] : null;
            $track_housing_url = $first_app_id ? "view-housing.php?id={$first_app_id}" : "my-housing.php";
            $banner_actions = [
                ['label' => 'Track Application', 'url' => $track_housing_url, 'icon' => 'fa-search-location', 'type' => 'primary'],
                ['label' => 'My Applications', 'url' => 'my-housing.php', 'icon' => 'fa-home', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

            <!-- Stats Overview Strip -->
            <div class="housing-stats-strip">
                <div class="housing-stat-card">
                    <div class="housing-stat-icon stat-icon-programs">
                        <i class="fas fa-city"></i>
                    </div>
                    <div class="housing-stat-info">
                        <h3><?php echo count($programs); ?></h3>
                        <p>Active Programs</p>
                    </div>
                </div>

                <div class="housing-stat-card">
                    <div class="housing-stat-icon stat-icon-units">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="housing-stat-info">
                        <h3><?php echo number_format($total_available_sum); ?> / <?php echo number_format($total_units_sum); ?></h3>
                        <p>Available Housing Units</p>
                    </div>
                </div>

                <div class="housing-stat-card">
                    <div class="housing-stat-icon stat-icon-apps">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div class="housing-stat-info">
                        <h3><?php echo count($resident_apps); ?></h3>
                        <p>My Submissions</p>
                    </div>
                </div>
            </div>

            <!-- Filter and Search Toolbar -->
            <div class="programs-filter-bar">
                <div class="segmented-filter-group" role="tablist">
                    <button type="button" class="seg-filter-btn <?php echo $active_filter === 'all' ? 'active' : ''; ?>" onclick="filterPrograms('all')">
                        <i class="fas fa-border-all"></i> All Programs (<?php echo count($programs); ?>)
                    </button>
                    <button type="button" class="seg-filter-btn <?php echo $active_filter === 'relocation' ? 'active' : ''; ?>" onclick="filterPrograms('relocation')">
                        <i class="fas fa-truck-moving"></i> Relocation
                    </button>
                    <button type="button" class="seg-filter-btn <?php echo $active_filter === 'in_site' ? 'active' : ''; ?>" onclick="filterPrograms('in_site')">
                        <i class="fas fa-hammer"></i> In-Site
                    </button>
                </div>

                <div class="filter-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="programSearchInput" placeholder="Search program name or location..." onkeyup="searchPrograms()">
                </div>
            </div>

            <!-- Programs Grid -->
            <div class="programs-cards-grid" id="programsContainer">
                <?php foreach ($programs as $prog): 
                    $p_id = (int)$prog['program_id'];
                    $p_type = $prog['housing_type'] ?? 'relocation';
                    $is_insite = ($p_type === 'in_site');
                    $tot_units = (int)($prog['total_units'] ?? 0);
                    $occ_units = $occupied_counts[$p_id] ?? 0;
                    $avail_units = max(0, $tot_units - $occ_units);
                    $pct_avail = $tot_units > 0 ? round(($avail_units / $tot_units) * 100) : 0;
                    $already_applied = isset($resident_apps[$p_id]);
                    $app_status = $already_applied ? $resident_apps[$p_id]['status'] : '';
                ?>
                <article class="housing-card" data-type="<?php echo htmlspecialchars($p_type); ?>" data-name="<?php echo htmlspecialchars(strtolower($prog['program_name'] . ' ' . $prog['location'])); ?>">
                    <div class="housing-card-banner <?php echo $is_insite ? 'insite' : 'relocation'; ?>">
                        <div class="card-banner-left">
                            <div class="banner-icon-circle">
                                <i class="fas fa-<?php echo $is_insite ? 'house-chimney-user' : 'hotel'; ?>"></i>
                            </div>
                            <div class="card-banner-meta">
                                <h4><?php echo htmlspecialchars($prog['program_code'] ?? ('HSG-' . str_pad($p_id, 3, '0', STR_PAD_LEFT))); ?></h4>
                                <span><?php echo $is_insite ? 'In-Site Shelter Project' : 'Relocation Community'; ?></span>
                            </div>
                        </div>
                        <span class="program-type-tag">
                            <?php echo $is_insite ? 'In-Site' : 'Relocation'; ?>
                        </span>
                    </div>

                    <div class="housing-card-body">
                        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--ref-text); letter-spacing: -0.01em;">
                            <?php echo htmlspecialchars($prog['program_name']); ?>
                        </h3>

                        <div class="program-location-bar">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($prog['location'] ?? 'Palayan City, Nueva Ecija'); ?></span>
                        </div>

                        <p class="program-description-text">
                            <?php echo htmlspecialchars($prog['description'] ?? 'Comprehensive socialized shelter program providing security of tenure, durable housing, and community services for eligible Palayan City residents.'); ?>
                        </p>

                        <!-- Slot Allocation Progress -->
                        <div class="slots-progress-box">
                            <div class="slots-label-row">
                                <span>Available Slots</span>
                                <span style="color: <?php echo $avail_units > 0 ? '#10B981' : '#EF4444'; ?>;">
                                    <?php echo number_format($avail_units); ?> of <?php echo number_format($tot_units); ?> units left
                                </span>
                            </div>
                            <div class="slots-track">
                                <div class="slots-fill" style="width: <?php echo min(100, max(5, $pct_avail)); ?>%;"></div>
                            </div>
                        </div>

                        <!-- Highlights -->
                        <ul class="requirements-mini-list">
                            <li><i class="fas fa-check-circle"></i> Verified Palayan City residency (Min. 3 years)</li>
                            <li><i class="fas fa-check-circle"></i> No prior government housing allocation</li>
                            <li><i class="fas fa-check-circle"></i> Valid government-issued ID &amp; Barangay Certificate</li>
                        </ul>
                    </div>

                    <div class="housing-card-footer">
                        <?php if ($already_applied): ?>
                            <a href="view-housing.php?id=<?php echo $resident_apps[$p_id]['beneficiary_id']; ?>" class="badge-applied-status" title="View tracking status">
                                <i class="fas fa-check-circle"></i> Applied (<?php echo ucfirst($app_status); ?>)
                            </a>
                        <?php else: ?>
                            <a href="apply-housing.php?program_id=<?php echo $p_id; ?>" class="btn-apply-program" title="Submit your housing application">
                                <i class="fas fa-pen-to-square"></i> Apply Now
                            </a>
                        <?php endif; ?>

                        <a href="housing-requirements.php?program_id=<?php echo $p_id; ?>" class="btn-program-guide" title="View complete documentary requirements">
                            <i class="fas fa-clipboard-list"></i> Requirements
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Search Modal Overlay -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>

    <script>
    function filterPrograms(type) {
        const buttons = document.querySelectorAll('.seg-filter-btn');
        buttons.forEach(b => b.classList.remove('active'));
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }

        const cards = document.querySelectorAll('#programsContainer .housing-card');
        cards.forEach(c => {
            if (type === 'all' || c.getAttribute('data-type') === type) {
                c.style.display = 'flex';
            } else {
                c.style.display = 'none';
            }
        });
    }

    function searchPrograms() {
        const q = (document.getElementById('programSearchInput').value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('#programsContainer .housing-card');
        cards.forEach(c => {
            const text = c.getAttribute('data-name') || '';
            if (!q || text.includes(q)) {
                c.style.display = 'flex';
            } else {
                c.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>

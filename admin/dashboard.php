<?php
require_once __DIR__ . '/../config/database.php';

if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    $features_path = __DIR__ . '/../config/features.php';
    if (file_exists($features_path)) {
        require_once $features_path;
    }
}
$is_pwd_senior_enabled = defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR;

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();


// ========== REPORTING PERIOD / MONTH FILTER ==========
$filter_month = isset($_GET['month']) ? sanitize($_GET['month']) : '';
if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = '';
}

// ========== COMPREHENSIVE STATISTICS ==========
// Combined into ONE query with PWD and Senior applications included
$stats_query = "SELECT
    (SELECT COUNT(*) FROM users)                                                       AS total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'staff')                                  AS total_staff,
    (SELECT COUNT(*) FROM users WHERE role = 'resident')                               AS total_residents,
    (SELECT COUNT(*) FROM appointments)                                                AS total_appointments,
    (SELECT COUNT(*) FROM aics_requests)                                               AS total_aics,
    (SELECT COUNT(*) FROM housing_beneficiaries)                                       AS total_housing,
    (SELECT COUNT(*) FROM aics_requests        WHERE status = 'submitted')             AS pending_aics,
    (SELECT COUNT(*) FROM housing_beneficiaries WHERE status = 'pending')              AS pending_housing,
    (SELECT COUNT(*) FROM verification_requests WHERE status = 'pending')              AS pending_verifications,
    (SELECT COUNT(*) FROM users                WHERE verification_status = 'verified') AS verified_residents,
    (SELECT COUNT(*) FROM pwd_applications)                                            AS total_pwd,
    (SELECT COUNT(*) FROM pwd_applications     WHERE status = 'submitted')             AS pending_pwd,
    (SELECT COUNT(*) FROM senior_applications)                                         AS total_senior,
    (SELECT COUNT(*) FROM senior_applications  WHERE status = 'submitted')             AS pending_senior";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// If filtered by specific month, fetch month-focused metrics
$month_stats = null;
if (!empty($filter_month)) {
    list($f_year, $f_month) = explode('-', $filter_month);
    $m_query = "SELECT
        (SELECT COUNT(*) FROM verification_requests WHERE YEAR(submitted_date) = :y1 AND MONTH(submitted_date) = :m1) AS m_verifications,
        (SELECT COUNT(*) FROM appointments WHERE YEAR(appointment_date) = :y2 AND MONTH(appointment_date) = :m2) AS m_appointments,
        (SELECT COUNT(*) FROM aics_requests WHERE YEAR(submission_date) = :y3 AND MONTH(submission_date) = :m3) AS m_aics,
        (SELECT COUNT(*) FROM housing_beneficiaries WHERE YEAR(application_date) = :y4 AND MONTH(application_date) = :m4) AS m_housing,
        (SELECT COUNT(*) FROM pwd_applications WHERE YEAR(created_at) = :y5 AND MONTH(created_at) = :m5) AS m_pwd,
        (SELECT COUNT(*) FROM senior_applications WHERE YEAR(created_at) = :y6 AND MONTH(created_at) = :m6) AS m_senior";
    $m_stmt = $db->prepare($m_query);
    $m_stmt->execute([
        ':y1' => $f_year, ':m1' => $f_month,
        ':y2' => $f_year, ':m2' => $f_month,
        ':y3' => $f_year, ':m3' => $f_month,
        ':y4' => $f_year, ':m4' => $f_month,
        ':y5' => $f_year, ':m5' => $f_month,
        ':y6' => $f_year, ':m6' => $f_month,
    ]);
    $month_stats = $m_stmt->fetch(PDO::FETCH_ASSOC);
}

// Recent Residency Verifications (Admin Approval Queue)
$verif_stmt = $db->prepare("SELECT v.*, u.first_name, u.last_name, u.email 
    FROM verification_requests v 
    JOIN users u ON v.user_id = u.user_id 
    ORDER BY v.submitted_date DESC LIMIT 5");
$verif_stmt->execute();
$recent_verifications = $verif_stmt->fetchAll(PDO::FETCH_ASSOC);

// User Accounts by Role
$roles_stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$roles_breakdown = $roles_stmt ? $roles_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$role_labels = [];
$role_values = [];
$role_colors = ['admin' => '#7F0303', 'staff' => '#2563EB', 'resident' => '#059669'];
$role_cls = [];
foreach ($roles_breakdown as $r) {
    $role_name = ($r['role'] === 'admin') ? 'Administrators' : (($r['role'] === 'staff') ? 'CSWDO Staff' : 'Residents');
    $role_labels[] = $role_name;
    $role_values[] = (int)$r['count'];
    $role_cls[] = $role_colors[$r['role']] ?? '#9CA3AF';
}
$js_role_labels = json_encode($role_labels);
$js_role_values = json_encode($role_values);
$js_role_colors = json_encode($role_cls);

// Verification Status Breakdown
$v_status_stmt = $db->query("SELECT status, COUNT(*) as count FROM verification_requests GROUP BY status");
$v_status_breakdown = $v_status_stmt ? $v_status_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$v_labels = [];
$v_values = [];
$v_colors_map = ['verified' => '#059669', 'pending' => '#F59E0B', 'rejected' => '#EF4444'];
$v_cls = [];
foreach ($v_status_breakdown as $vs) {
    $v_labels[] = ucfirst($vs['status']);
    $v_values[] = (int)$vs['count'];
    $v_cls[] = $v_colors_map[$vs['status']] ?? '#9CA3AF';
}
$js_verif_labels = json_encode($v_labels);
$js_verif_values = json_encode($v_values);
$js_verif_colors = json_encode($v_cls);

// Account Status Distribution
$u_status_stmt = $db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
$u_status_breakdown = $u_status_stmt ? $u_status_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$u_labels = [];
$u_values = [];
$u_colors_map = ['active' => '#059669', 'inactive' => '#6B7280'];
$u_cls = [];
foreach ($u_status_breakdown as $us) {
    $u_labels[] = ucfirst($us['status'] ?: 'Active');
    $u_values[] = (int)$us['count'];
    $u_cls[] = $u_colors_map[$us['status']] ?? '#9CA3AF';
}
$js_u_labels = json_encode($u_labels);
$js_u_values = json_encode($u_values);
$js_u_colors = json_encode($u_cls);

// Recent activities
$activity_stmt = $db->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10");
$activity_stmt->execute();
$recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Barangay geographical stats
$check_cols = $db->query("SHOW COLUMNS FROM users");
$user_cols = [];
while ($col = $check_cols->fetch(PDO::FETCH_ASSOC)) {
    $user_cols[] = $col['Field'];
}

$barangay_field = in_array('barangay', $user_cols)
    ? "u.barangay"
    : "COALESCE((SELECT barangay_name FROM verification_requests WHERE user_id = u.user_id LIMIT 1), 'Unknown')";

$barangay_stmt = $db->prepare("SELECT * FROM (
    SELECT $barangay_field as barangay, COUNT(DISTINCT a.aics_id) as aics_count, COUNT(DISTINCT h.beneficiary_id) as housing_count
    FROM users u
    LEFT JOIN aics_requests a ON u.user_id = a.user_id
    LEFT JOIN housing_beneficiaries h ON u.user_id = h.user_id
    GROUP BY $barangay_field
) as sub
WHERE barangay IS NOT NULL AND barangay != '' AND barangay != 'Unknown'
ORDER BY (aics_count + housing_count) DESC");
$barangay_stmt->execute();
$barangay_stats = $barangay_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// ─── Multi-Service Trend Engine (AICS, Appointments, Housing, PWD, Senior) ───
$trend_sources = [
    'aics' => [
        'table' => 'aics_requests',
        'col' => 'submission_date'
    ],
    'appts' => [
        'table' => 'appointments',
        'col' => 'appointment_date'
    ],
    'housing' => [
        'table' => 'housing_beneficiaries',
        'col' => 'application_date'
    ],
    'pwd' => [
        'table' => 'pwd_applications',
        'col' => 'created_at'
    ],
    'senior' => [
        'table' => 'senior_applications',
        'col' => 'created_at'
    ]
];

$trend_data_store = [
    'monthly' => [],
    'daily' => []
];

foreach ($trend_sources as $k => $src) {
    // 1. Monthly aggregation
    $m_stmt = $db->query("SELECT YEAR({$src['col']}) as y, MONTH({$src['col']}) as m, COUNT(*) as cnt 
                          FROM {$src['table']} 
                          WHERE {$src['col']} IS NOT NULL 
                          GROUP BY y, m");
    if ($m_stmt) {
        while ($r = $m_stmt->fetch(PDO::FETCH_ASSOC)) {
            $ym = sprintf('%04d-%02d', $r['y'], $r['m']);
            if (!isset($trend_data_store['monthly'][$ym])) {
                $trend_data_store['monthly'][$ym] = ['aics' => 0, 'appts' => 0, 'housing' => 0, 'pwd' => 0, 'senior' => 0];
            }
            $trend_data_store['monthly'][$ym][$k] = (int)$r['cnt'];
        }
    }

    // 2. Daily aggregation (for day-by-day month view)
    $d_stmt = $db->query("SELECT YEAR({$src['col']}) as y, MONTH({$src['col']}) as m, DAYOFMONTH({$src['col']}) as d, COUNT(*) as cnt 
                          FROM {$src['table']} 
                          WHERE {$src['col']} IS NOT NULL 
                          GROUP BY y, m, d");
    if ($d_stmt) {
        while ($r = $d_stmt->fetch(PDO::FETCH_ASSOC)) {
            $ym = sprintf('%04d-%02d', $r['y'], $r['m']);
            $day = (int)$r['d'];
            if (!isset($trend_data_store['daily'][$ym])) {
                $trend_data_store['daily'][$ym] = [];
            }
            if (!isset($trend_data_store['daily'][$ym][$day])) {
                $trend_data_store['daily'][$ym][$day] = ['aics' => 0, 'appts' => 0, 'housing' => 0, 'pwd' => 0, 'senior' => 0];
            }
            $trend_data_store['daily'][$ym][$day][$k] = (int)$r['cnt'];
        }
    }
}

// Available months for dropdown filter (past 12 rolling months)
$available_filter_months = [];
for ($i = 0; $i < 12; $i++) {
    $m_time = strtotime("first day of -$i month");
    $ym = date('Y-m', $m_time);
    $available_filter_months[$ym] = date('F Y', $m_time);
}

// ─── AICS category breakdown ────────────────────────────────────────────────
// JSON encoded arrays for JS charts
$js_trend_store = json_encode($trend_data_store);
$js_default_period = json_encode(!empty($filter_month) ? $filter_month : '6m');

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
$page_title = 'Dashboard';
$active_nav = 'dashboard';
require_once __DIR__ . '/includes/admin-head.php';
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- Subheader with Signature Bulgash Cursive Branding -->
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1>Dashboard</h1>
        <p>Overview of the One Palayan-Kalinga Hub</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <a href="cms.php" class="portal-hdr-pill" style="text-decoration: none; background: var(--ref-maroon); color: #fff; margin-right: 8px;">
            <i class="fas fa-desktop"></i>
            <span>Website CMS</span>
        </a>
        <span class="status-badge-pill verified">
            <i class="fas fa-shield-alt"></i>
            <span>Admin Control Center</span>
        </span>
    </div>
</div>

<!-- Dashboard Reporting Period / Month Filter Toolbar -->
<div class="dash-period-toolbar">
    <div class="period-toolbar-label">
        <i class="fas fa-calendar-alt"></i>
        <span>Dashboard Period:</span>
        <?php if (!empty($filter_month)): ?>
            <span class="trend-period-badge"><?php echo date('F Y', strtotime($filter_month . '-01')); ?></span>
        <?php endif; ?>
    </div>
    <div class="period-quick-links">
        <a href="dashboard.php" class="period-pill <?php echo empty($filter_month) ? 'active' : ''; ?>">All Time</a>
        <a href="dashboard.php?month=<?php echo date('Y-m'); ?>" class="period-pill <?php echo $filter_month === date('Y-m') ? 'active' : ''; ?>">This Month (<?php echo date('M Y'); ?>)</a>
        <a href="dashboard.php?month=<?php echo date('Y-m', strtotime('-1 month')); ?>" class="period-pill <?php echo $filter_month === date('Y-m', strtotime('-1 month')) ? 'active' : ''; ?>">Last Month (<?php echo date('M Y', strtotime('-1 month')); ?>)</a>
    </div>
    <form method="GET" action="dashboard.php" class="period-select-form">
        <select name="month" class="period-select-input" onchange="this.form.submit()">
            <option value="">Specific Month...</option>
            <?php foreach ($available_filter_months as $ym => $label): ?>
                <option value="<?php echo $ym; ?>" <?php echo $filter_month === $ym ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($filter_month)): ?>
            <a href="dashboard.php" class="period-clear-btn" title="Reset to All Time"><i class="fas fa-times-circle"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Minimalist Compact Status Grid (8 Core Pillars) -->
<div class="compact-stats-grid">
    <!-- Pending Verifications -->
    <a href="manage-verifications.php?status=pending" class="compact-stat-card" title="Review Pending Verifications">
        <div class="compact-stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value"><?php echo number_format($month_stats ? $month_stats['m_verifications'] : $stats['pending_verifications']); ?></div>
            <div class="compact-stat-label"><?php echo $month_stats ? 'New Verifications' : 'Pending Verification'; ?></div>
        </div>
        <span class="compact-stat-badge"><?php echo $month_stats ? 'Monthly' : 'Review'; ?></span>
    </a>

    <!-- Verified Residents -->
    <a href="manage-verifications.php?status=verified" class="compact-stat-card" title="View Verified Residents">
        <div class="compact-stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value"><?php echo number_format($stats['verified_residents']); ?></div>
            <div class="compact-stat-label">Verified Residents</div>
        </div>
        <span class="compact-stat-badge"><?php echo $stats['total_residents'] > 0 ? round(($stats['verified_residents'] / $stats['total_residents']) * 100) : 0; ?>%</span>
    </a>

    <!-- Staff Members -->
    <a href="manage-staff.php" class="compact-stat-card" title="Manage Staff Members">
        <div class="compact-stat-icon blue"><i class="fas fa-user-tie"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value"><?php echo number_format($stats['total_staff']); ?></div>
            <div class="compact-stat-label">Staff Members</div>
        </div>
        <span class="compact-stat-badge">Team</span>
    </a>

    <!-- Total Residents -->
    <a href="manage-residents.php" class="compact-stat-card" title="View Registered Residents">
        <div class="compact-stat-icon purple"><i class="fas fa-user-friends"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value"><?php echo number_format($stats['total_residents']); ?></div>
            <div class="compact-stat-label">Total Residents</div>
        </div>
        <span class="compact-stat-badge">All</span>
    </a>

    <!-- System Accounts -->
    <a href="manage-users.php" class="compact-stat-card" title="Manage System Accounts & Access">
        <div class="compact-stat-icon maroon"><i class="fas fa-users-cog"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value"><?php echo number_format($stats['total_users']); ?></div>
            <div class="compact-stat-label">System Accounts</div>
        </div>
        <span class="compact-stat-badge">All</span>
    </a>

    <!-- Website CMS -->
    <a href="cms.php" class="compact-stat-card" title="Dynamic Content Management System (CMS)">
        <div class="compact-stat-icon teal"><i class="fas fa-desktop"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value">CMS</div>
            <div class="compact-stat-label">Website Content</div>
        </div>
        <span class="compact-stat-badge">Manage</span>
    </a>

    <!-- Operations Calendar -->
    <a href="calendar.php" class="compact-stat-card" title="Operations Calendar & Schedules">
        <div class="compact-stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value">Schedule</div>
            <div class="compact-stat-label">Calendar Manager</div>
        </div>
        <span class="compact-stat-badge">Active</span>
    </a>

    <!-- System Audit Logs -->
    <a href="audit-logs.php" class="compact-stat-card" title="System Security & Audit Logs">
        <div class="compact-stat-icon purple"><i class="fas fa-history"></i></div>
        <div class="compact-stat-body">
            <div class="compact-stat-value">Security</div>
            <div class="compact-stat-label">System Audit Logs</div>
        </div>
        <span class="compact-stat-badge">Logs</span>
    </a>
</div>

            <!-- Charts Row 1 -->
            <div class="charts-grid">
                <!-- Monthly Trend Chart (AICS, Appointments, Housing) -->
                <div class="chart-card">
                    <div class="card-header trend-card-header">
                        <div class="chart-title-wrap">
                            <h3>
                                <i class="fas fa-chart-line" style="color: var(--maroon-light); margin-right: 8px;"></i>
                                Services & Applications Trend
                            </h3>
                            <span class="trend-period-badge" id="trendPeriodBadge">Last 6 Months</span>
                        </div>
                        <div class="trend-controls-wrap">
                            <!-- Month / Time Range Dropdown Filter -->
                            <div class="trend-filter-select-wrap">
                                <i class="fas fa-calendar-alt"></i>
                                <select id="trendTimeFilter" class="chart-filter-select" onchange="changeTrendPeriod(this.value)">
                                    <optgroup label="Trend Range">
                                        <option value="6m" <?php echo ($filter_month === '' || $filter_month === '6m') ? 'selected' : ''; ?>>Last 6 Months</option>
                                        <option value="3m">Last 3 Months</option>
                                        <option value="12m">Last 12 Months</option>
                                        <option value="ytd">Year <?php echo date('Y'); ?> (All)</option>
                                    </optgroup>
                                    <optgroup label="Filter by Month (Daily)">
                                        <?php foreach ($available_filter_months as $ym => $label): ?>
                                            <option value="<?php echo $ym; ?>" <?php echo $filter_month === $ym ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            <!-- Service Tabs -->
                            <div class="chart-tabs">
                                <button type="button" class="chart-tab active" data-mode="all" onclick="toggleTrendChart('all')">All</button>
                                <button type="button" class="chart-tab" data-mode="aics" onclick="toggleTrendChart('aics')">AICS</button>
                                <button type="button" class="chart-tab" data-mode="appts" onclick="toggleTrendChart('appts')">Appts</button>
                                <button type="button" class="chart-tab" data-mode="housing" onclick="toggleTrendChart('housing')">Housing</button>
                            </div>
                        </div>
                    </div>
                    <div class="canvas-wrap h280">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- System User Roles Distribution Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog" style="color: var(--maroon-light); margin-right: 8px;"></i> User Roles Distribution</h3>
                        <span class="badge">Total: <?php echo number_format($stats['total_users']); ?></span>
                    </div>
                    <div class="canvas-wrap h240">
                        <canvas id="rolesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="charts-row-2">
                <!-- Residency Verification Breakdown Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-alt" style="color: var(--maroon-light); margin-right: 8px;"></i> Verification Status</h3>
                        <span class="badge">Residency Requests</span>
                    </div>
                    <div class="canvas-wrap h240">
                        <canvas id="verifChart"></canvas>
                    </div>
                </div>

                <!-- Account Status Distribution Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-check" style="color: var(--maroon-light); margin-right: 8px;"></i> Account Activity Status</h3>
                        <span class="badge">Active vs Inactive</span>
                    </div>
                    <div class="canvas-wrap h240">
                        <canvas id="userStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Geographical Demand Heatmap by Barangay -->
            <div class="chart-card full-width" style="margin-top: 30px; margin-bottom: 30px; grid-column: 1 / -1;">
                <div class="card-header">
                    <h3><i class="fas fa-map-marked-alt" style="color: var(--maroon-light); margin-right: 8px;"></i>
                        Geographical Demand Heatmap (Palayan City by Barangay)</h3>
                    <span class="badge">AICS & Housing Crisis Distribution</span>
                </div>

                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
                    <!-- SVGMockup Map Container -->
                    <div
                        style="background: var(--bg-primary); border-radius: var(--radius-element); padding: 15px; text-align: center; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 250px;">
                        <span
                            style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; display: block;"><i
                                class="fas fa-map"></i> Palayan City Heatmap Visual</span>
                        <svg viewBox="0 0 400 240" style="width: 100%; max-height: 220px;">
                            <!-- Mockup contours of Palayan City Barangays -->
                            <g stroke="var(--bg-card)" stroke-width="2" fill="none">
                                <path d="M50,120 L120,40 L190,70 L150,140 Z" fill="#4CAF50" fill-opacity="0.6"
                                    title="Barangay Atate" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                                <path d="M120,40 L210,20 L270,90 L190,70 Z" fill="#FF9800" fill-opacity="0.6"
                                    title="Barangay Singalat" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                                <path d="M150,140 L190,70 L270,90 L230,180 L180,170 Z" fill="#F44336" fill-opacity="0.6"
                                    title="Barangay Caballero" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                                <path d="M270,90 L340,50 L380,120 L310,170 Z" fill="#4CAF50" fill-opacity="0.6"
                                    title="Barangay Ganaderia" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                                <path d="M230,180 L310,170 L280,230 L200,220 Z" fill="#FF9800" fill-opacity="0.6"
                                    title="Barangay Mapait" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                                <path d="M310,170 L380,120 L390,190 Z" fill="#4CAF50" fill-opacity="0.6"
                                    title="Barangay Marcos Village" style="transition: all 0.3s; cursor: pointer;"
                                    onmouseover="this.style.fillOpacity=0.9" onmouseout="this.style.fillOpacity=0.6" />
                            </g>
                            <!-- Legend -->
                            <g transform="translate(10, 200)" font-size="10" font-family="Inter"
                                fill="var(--text-secondary)">
                                <rect width="12" height="12" fill="#F44336" rx="2" />
                                <text x="18" y="10">High Demand (Caballero)</text>

                                <rect x="150" width="12" height="12" fill="#FF9800" rx="2" />
                                <text x="168" y="10">Moderate (Singalat/Mapait)</text>

                                <rect x="300" width="12" height="12" fill="#4CAF50" rx="2" />
                                <text x="318" y="10">Low (Atate/Others)</text>
                            </g>
                        </svg>
                    </div>

                    <!-- Barangay Stats List -->
                    <div
                        style="display: flex; flex-direction: column; gap: 10px; max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <span
                            style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);"><i
                                class="fas fa-list"></i> Barangay Demand Rankings</span>
                        <?php
                        $max_total = 1;
                        foreach ($barangay_stats as $bs) {
                            $total = (int) $bs['aics_count'] + (int) $bs['housing_count'];
                            if ($total > $max_total)
                                $max_total = $total;
                        }
                        if (count($barangay_stats) > 0):
                            foreach ($barangay_stats as $bs):
                                $aics_cnt = (int) $bs['aics_count'];
                                $housing_cnt = (int) $bs['housing_count'];
                                $total_cnt = $aics_cnt + $housing_cnt;
                                $pct = round(($total_cnt / $max_total) * 100);
                                $heat_color = $pct > 70 ? 'var(--color-danger)' : ($pct > 30 ? 'var(--color-warning)' : 'var(--color-success)');
                                ?>
                                <div
                                    style="background: var(--bg-card); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 4px;">
                                    <div
                                        style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600;">
                                        <span
                                            style="color: var(--text-primary);"><?php echo htmlspecialchars($bs['barangay']); ?></span>
                                        <span style="color: <?php echo $heat_color; ?>;"><?php echo $total_cnt; ?>
                                            requests</span>
                                    </div>
                                    <div style="display: flex; gap: 8px; font-size: 0.65rem; color: var(--text-muted);">
                                        <span>AICS: <?php echo $aics_cnt; ?></span>
                                        <span>•</span>
                                        <span>Housing: <?php echo $housing_cnt; ?></span>
                                    </div>
                                    <div
                                        style="width: 100%; height: 6px; background: var(--bg-primary); border-radius: 10px; overflow: hidden; margin-top: 2px;">
                                        <div
                                            style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $heat_color; ?>; border-radius: 10px;">
                                        </div>
                                    </div>
                                </div>
                            <?php
                            endforeach;
                        else:
                            ?>
                            <p style="font-size: 0.8rem; text-align: center; margin-top: 30px; color: var(--text-muted);">No
                                geographical data recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Administrative Queues & Activity Sections -->
            <div class="bottom-grid">
                <!-- Recent Residency Verification Requests (Core Admin Task) -->
                <div class="recent-card">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-alt" style="color: var(--maroon-light); margin-right: 8px;"></i> Recent Residency Verifications</h3>
                        <a href="manage-verifications.php">View All →</a>
                    </div>
                    <?php if (!empty($recent_verifications)): ?>
                        <?php foreach ($recent_verifications as $v): 
                            $v_status = $v['status'] ?? 'pending';
                            $v_badge_class = ($v_status === 'verified') ? 'completed' : (($v_status === 'rejected') ? 'cancelled' : 'pending');
                        ?>
                            <div class="activity-item">
                                <div class="activity-icon system"><i class="fas fa-id-card"></i></div>
                                <div class="activity-content">
                                    <div class="title"><?php echo htmlspecialchars($v['first_name'] . ' ' . $v['last_name']); ?></div>
                                    <div class="desc"><?php echo htmlspecialchars($v['id_type'] ?: 'Valid Government ID'); ?> &bull; <?php echo htmlspecialchars($v['barangay_name'] ?? 'Palayan City'); ?></div>
                                </div>
                                <span class="status-badge <?php echo $v_badge_class; ?>"><?php echo ($v_status === 'rejected') ? 'Disapproved' : ucfirst($v_status); ?></span>
                                <span class="activity-time"><?php echo !empty($v['submitted_date']) ? date('M d', strtotime($v['submitted_date'])) : '—'; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-data">No recent verification requests</p>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities (Audit Logs) -->
                <div class="recent-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock" style="color: var(--maroon-light); margin-right: 8px;"></i> Recent System Activities</h3>
                        <a href="audit-logs.php">View All →</a>
                    </div>
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon system"><i class="fas fa-history"></i></div>
                                <div class="activity-content">
                                    <div class="title">User #<?php echo $activity['user_id']; ?> •
                                        <?php echo ucfirst($activity['action']); ?></div>
                                    <div class="desc">
                                        <?php echo $activity['table_affected'] ? 'Table: ' . $activity['table_affected'] : 'System action'; ?>
                                    </div>
                                </div>
                                <span class="activity-time"><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-data">No recent activities</p>
                    <?php endif; ?>
                </div>
            </div>

    <script>
        // ========== INITIALIZE CHARTS ==========
        let trendChartInstance = null;
        let rolesChartInstance = null;
        let verifChartInstance = null;
        let userStatusChartInstance = null;

        function getThemeColors() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            return {
                text: isDark ? '#8AA0A9' : '#71818B',
                grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)',
                tooltipBg: isDark ? '#152227' : '#ffffff',
                tooltipColor: isDark ? '#E5EFF2' : '#192126',
                border: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)',
                cardBg: isDark ? '#152227' : '#ffffff'
            };
        }

        const trendStore = <?php echo $js_trend_store; ?>;
        const defaultTrendPeriod = <?php echo $js_default_period; ?>;
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const fullMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        let currentTrendMode = 'all';

        function getTrendPeriodData(period) {
            let labels = [];
            let badgeText = '';
            let aics = [], appts = [], housing = [], pwd = [], senior = [];

            if (period === '3m' || period === '6m' || period === '12m') {
                const count = period === '3m' ? 3 : (period === '6m' ? 6 : 12);
                badgeText = `Last ${count} Months`;
                const now = new Date();
                const ymList = [];
                for (let i = count - 1; i >= 0; i--) {
                    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                    const y = d.getFullYear();
                    const m = d.getMonth() + 1;
                    const ym = `${y}-${String(m).padStart(2, '0')}`;
                    ymList.push({ ym, label: monthNames[m - 1] + (count > 6 ? ` '${String(y).slice(-2)}` : '') });
                }
                labels = ymList.map(item => item.label);
                ymList.forEach(item => {
                    const mData = (trendStore.monthly && trendStore.monthly[item.ym]) || { aics: 0, appts: 0, housing: 0, pwd: 0, senior: 0 };
                    aics.push(mData.aics || 0);
                    appts.push(mData.appts || 0);
                    housing.push(mData.housing || 0);
                    pwd.push(mData.pwd || 0);
                    senior.push(mData.senior || 0);
                });
            } else if (period === 'ytd') {
                const currentYear = new Date().getFullYear();
                badgeText = `Year ${currentYear} (All Months)`;
                for (let m = 1; m <= 12; m++) {
                    labels.push(monthNames[m - 1]);
                    const ym = `${currentYear}-${String(m).padStart(2, '0')}`;
                    const mData = (trendStore.monthly && trendStore.monthly[ym]) || { aics: 0, appts: 0, housing: 0, pwd: 0, senior: 0 };
                    aics.push(mData.aics || 0);
                    appts.push(mData.appts || 0);
                    housing.push(mData.housing || 0);
                    pwd.push(mData.pwd || 0);
                    senior.push(mData.senior || 0);
                }
            } else if (/^\d{4}-\d{2}$/.test(period)) {
                const [yearStr, monthStr] = period.split('-');
                const year = parseInt(yearStr, 10);
                const month = parseInt(monthStr, 10);
                const daysInMonth = new Date(year, month, 0).getDate();
                badgeText = `${fullMonthNames[month - 1]} ${year} (Daily)`;

                const monthDaily = (trendStore.daily && trendStore.daily[period]) || {};
                for (let day = 1; day <= daysInMonth; day++) {
                    labels.push(monthNames[month - 1] + ' ' + day);
                    const dayData = monthDaily[day] || { aics: 0, appts: 0, housing: 0, pwd: 0, senior: 0 };
                    aics.push(dayData.aics || 0);
                    appts.push(dayData.appts || 0);
                    housing.push(dayData.housing || 0);
                    pwd.push(dayData.pwd || 0);
                    senior.push(dayData.senior || 0);
                }
            }

            return {
                labels,
                badgeText,
                datasets: { aics, appts, housing, pwd, senior }
            };
        }

        function initTrendChart() {
            const ctx = document.getElementById('trendChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getThemeColors();
            const initialPeriod = document.getElementById('trendTimeFilter')?.value || defaultTrendPeriod || '6m';
            const pData = getTrendPeriodData(initialPeriod);

            const badge = document.getElementById('trendPeriodBadge');
            if (badge) badge.textContent = pData.badgeText;

            trendChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: pData.labels,
                    datasets: [
                        {
                            label: 'AICS Applications',
                            data: pData.datasets.aics,
                            borderColor: '#7F0303',
                            backgroundColor: 'rgba(127, 3, 3, 0.08)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#7F0303',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1.5,
                            pointRadius: 3.5,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Appointments',
                            data: pData.datasets.appts,
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.08)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#F59E0B',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1.5,
                            pointRadius: 3.5,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Housing Applications',
                            data: pData.datasets.housing,
                            borderColor: '#00897B',
                            backgroundColor: 'rgba(0, 137, 123, 0.08)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#00897B',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1.5,
                            pointRadius: 3.5,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: colors.text,
                                font: { family: "'Plus Jakarta Sans', sans-serif", weight: 600, size: 11 },
                                usePointStyle: true,
                                boxWidth: 8,
                                padding: 14
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.tooltipColor,
                            bodyColor: colors.tooltipColor,
                            borderColor: colors.border,
                            borderWidth: 1,
                            padding: 10,
                            boxPadding: 4,
                            usePointStyle: true
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: colors.text,
                                font: { size: 11 },
                                maxRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: colors.grid },
                            ticks: {
                                color: colors.text,
                                precision: 0,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });

            applyTrendVisibility();
        }

        function initRolesChart() {
            const ctx = document.getElementById('rolesChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getThemeColors();
            rolesChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $js_role_labels; ?>,
                    datasets: [{
                        data: <?php echo $js_role_values; ?>,
                        backgroundColor: <?php echo $js_role_colors; ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: colors.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: 600 } }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.tooltipColor,
                            bodyColor: colors.tooltipColor,
                            borderColor: colors.border,
                            borderWidth: 1
                        }
                    }
                }
            });
        }

        function initVerifChart() {
            const ctx = document.getElementById('verifChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getThemeColors();
            verifChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $js_verif_labels; ?>,
                    datasets: [{
                        data: <?php echo $js_verif_values; ?>,
                        backgroundColor: <?php echo $js_verif_colors; ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: colors.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: 600 } }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.tooltipColor,
                            bodyColor: colors.tooltipColor,
                            borderColor: colors.border,
                            borderWidth: 1
                        }
                    }
                }
            });
        }

        function initUserStatusChart() {
            const ctx = document.getElementById('userStatusChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getThemeColors();
            userStatusChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $js_u_labels; ?>,
                    datasets: [{
                        label: 'Accounts',
                        data: <?php echo $js_u_values; ?>,
                        backgroundColor: <?php echo $js_u_colors; ?>,
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.tooltipColor,
                            bodyColor: colors.tooltipColor,
                            borderColor: colors.border,
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: colors.text }
                        },
                        y: {
                            grid: { color: colors.grid },
                            ticks: { color: colors.text, stepSize: 1 }
                        }
                    }
                }
            });
        }

        function changeTrendPeriod(period) {
            if (!trendChartInstance) return;
            const pData = getTrendPeriodData(period);

            const badge = document.getElementById('trendPeriodBadge');
            if (badge) badge.textContent = pData.badgeText;

            trendChartInstance.data.labels = pData.labels;
            trendChartInstance.data.datasets[0].data = pData.datasets.aics;
            trendChartInstance.data.datasets[1].data = pData.datasets.appts;
            trendChartInstance.data.datasets[2].data = pData.datasets.housing;

            applyTrendVisibility();
            trendChartInstance.update();
        }

        function toggleTrendChart(mode) {
            currentTrendMode = mode;
            const tabs = document.querySelectorAll('.trend-controls-wrap .chart-tab');
            tabs.forEach(tab => {
                if (tab.getAttribute('data-mode') === mode) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            applyTrendVisibility();
            if (trendChartInstance) {
                trendChartInstance.update();
            }
        }

        function applyTrendVisibility() {
            if (!trendChartInstance) return;
            const serviceMap = {
                'all':     [true, true, true],
                'aics':    [true, false, false],
                'appts':   [false, true, false],
                'housing': [false, false, true]
            };
            const visibility = serviceMap[currentTrendMode] || serviceMap['all'];
            visibility.forEach((visible, idx) => {
                if (trendChartInstance.data.datasets[idx]) {
                    trendChartInstance.setDatasetVisibility(idx, visible);
                }
            });
        }

        function updateChartsTheme() {
            const colors = getThemeColors();
            const charts = [trendChartInstance, statusChartInstance, categoryChartInstance, housingChartInstance];
            charts.forEach(chart => {
                if (!chart) return;
                if (chart.options.scales) {
                    Object.keys(chart.options.scales).forEach(scaleKey => {
                        const scale = chart.options.scales[scaleKey];
                        if (scale.grid) scale.grid.color = colors.grid;
                        if (scale.ticks) scale.ticks.color = colors.text;
                    });
                }
                if (chart.options.plugins?.legend?.labels) {
                    chart.options.plugins.legend.labels.color = colors.text;
                }
                if (chart.options.plugins?.tooltip) {
                    chart.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
                    chart.options.plugins.tooltip.titleColor = colors.tooltipColor;
                    chart.options.plugins.tooltip.bodyColor = colors.tooltipColor;
                    chart.options.plugins.tooltip.borderColor = colors.border;
                }
                chart.update();
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initTrendChart();
            initRolesChart();
            initVerifChart();
            initUserStatusChart();
        });
    </script>


<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>




<?php
// staff-dashboard.php - Unified Staff Operations Dashboard
// Faithfully mirrors Resident Portal UI/UX (dashboard.php)
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Administrator redirection: Admins manage operations via the Admin Control Center
if (isAdmin()) {
    redirect('admin/dashboard.php');
}

// Check if user is staff
if (!isStaff()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services - Get staff_id from staff table using user_id
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories for this staff
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_id, sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD', 'Verification')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if staff has specific service access
$has_aics_access = false;
$has_housing_access = false;
$has_senior_access = false;
$has_pwd_access = false;
$has_verif_access = false;

foreach ($assigned_categories as $category) {
    $cat_name = $category['category_name'];
    if ($cat_name == 'AICS') $has_aics_access = true;
    if ($cat_name == 'Housing') $has_housing_access = true;
    if ($cat_name == 'Senior Citizen') $has_senior_access = true;
    if ($cat_name == 'PWD') $has_pwd_access = true;
    if ($cat_name == 'Verification' || $cat_name == 'Residency Verification') $has_verif_access = true;
}

// If no specific assignments, grant default view access
if (empty($assigned_categories)) {
    $has_aics_access = true;
    $has_housing_access = true;
    $has_senior_access = true;
    $has_pwd_access = true;
    $has_verif_access = true;
}

// Get staff details
$staff_query = "SELECT s.*, u.email, u.contact_number, u.last_login, u.profile_picture 
                FROM staff s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$stats = [
    'pending_aics' => 0,
    'approved_aics' => 0,
    'rejected_aics' => 0,
    'pending_housing' => 0,
    'approved_housing' => 0,
    'pending_documents' => 0,
    'today_appointments' => 0,
    'pending_verifications' => 0
];

try {
    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status IN ('submitted', 'reviewing')";
    $stats['pending_aics'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'approved'";
    $stats['approved_aics'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $query = "SELECT COUNT(*) as count FROM aics_requests WHERE status = 'rejected'";
    $stats['rejected_aics'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

try {
    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status IN ('pending', 'qualified')";
    $stats['pending_housing'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $query = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE status = 'approved'";
    $stats['approved_housing'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

try {
    $query = "SELECT COUNT(*) as count FROM documents WHERE verification_status = 'pending'";
    $stats['pending_documents'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

try {
    $query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending', 'confirmed')";
    $stats['today_appointments'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

try {
    $query = "SELECT COUNT(*) as count FROM verification_requests WHERE status = 'pending'";
    $stats['pending_verifications'] = (int)($db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {}

// Recent AICS requests
$recent_aics = [];
try {
    $query = "SELECT a.*, u.first_name, u.last_name, u.profile_picture 
              FROM aics_requests a
              JOIN users u ON a.user_id = u.user_id
              WHERE a.status IN ('submitted', 'reviewing')
              ORDER BY a.submission_date DESC
              LIMIT 4";
    $recent_aics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Recent Housing applications
$recent_housing = [];
try {
    $tables_check = $db->query("SHOW TABLES LIKE 'housing_beneficiaries'");
    if ($tables_check->rowCount() > 0) {
        $query = "SELECT h.*, u.first_name, u.last_name, u.profile_picture 
                  FROM housing_beneficiaries h
                  JOIN users u ON h.user_id = u.user_id
                  WHERE h.status IN ('pending', 'qualified')
                  ORDER BY h.application_date DESC
                  LIMIT 4";
        $recent_housing = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}


// ================= CASEWORK STATS (AICS, Housing, Appointments, PWD, Senior) =================
$casework_stats = [
    'total_aics' => 0,
    'pending_aics' => 0,
    'total_housing' => 0,
    'pending_housing' => 0,
    'total_appts' => 0,
    'pending_appts' => 0,
    'total_pwd' => 0,
    'pending_pwd' => 0,
    'total_senior' => 0,
    'pending_senior' => 0
];

try {
    $row = $db->query("SELECT COUNT(*) as t, SUM(CASE WHEN status IN ('pending', 'under_review', 'submitted', 'reviewing') THEN 1 ELSE 0 END) as p FROM aics_requests")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $casework_stats['total_aics'] = (int)($row['t'] ?? 0);
        $casework_stats['pending_aics'] = (int)($row['p'] ?? 0);
    }
} catch (Exception $e) {}

try {
    $row = $db->query("SELECT COUNT(*) as t, SUM(CASE WHEN status IN ('pending', 'under_review', 'submitted', 'qualified') THEN 1 ELSE 0 END) as p FROM housing_beneficiaries")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $casework_stats['total_housing'] = (int)($row['t'] ?? 0);
        $casework_stats['pending_housing'] = (int)($row['p'] ?? 0);
    }
} catch (Exception $e) {}

try {
    $row = $db->query("SELECT COUNT(*) as t, SUM(CASE WHEN status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as p FROM appointments WHERE appointment_date >= CURDATE()")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $casework_stats['total_appts'] = (int)($row['t'] ?? 0);
        $casework_stats['pending_appts'] = (int)($row['p'] ?? 0);
    }
} catch (Exception $e) {}

try {
    $row = $db->query("SELECT COUNT(*) as t, SUM(CASE WHEN status IN ('pending', 'under_review', 'submitted', 'step_1_review') THEN 1 ELSE 0 END) as p FROM pwd_applications")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $casework_stats['total_pwd'] = (int)($row['t'] ?? 0);
        $casework_stats['pending_pwd'] = (int)($row['p'] ?? 0);
    }
} catch (Exception $e) {}

try {
    $row = $db->query("SELECT COUNT(*) as t, SUM(CASE WHEN status IN ('pending', 'under_review', 'submitted', 'step_1_review') THEN 1 ELSE 0 END) as p FROM senior_applications")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $casework_stats['total_senior'] = (int)($row['t'] ?? 0);
        $casework_stats['pending_senior'] = (int)($row['p'] ?? 0);
    }
} catch (Exception $e) {}

// ================= MULTI-SERVICE TREND ENGINE =================
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
    try {
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

        // 2. Daily aggregation
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
    } catch (Exception $e) {}
}

// Available filter months (past 12 rolling months)
$available_filter_months = [];
for ($i = 0; $i < 12; $i++) {
    $m_time = strtotime("first day of -$i month");
    $ym = date('Y-m', $m_time);
    $available_filter_months[$ym] = date('F Y', $m_time);
}

$js_trend_store = json_encode($trend_data_store);
$js_default_period = json_encode('6m');

// Staff Event Scheduling Handler (Part 2, Item 12)
$cal_success = '';
$cal_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_calendar_event'])) {
    $ev_title = sanitize($_POST['event_title'] ?? '');
    $ev_desc = sanitize($_POST['event_description'] ?? '');
    $ev_date = sanitize($_POST['event_date'] ?? '');
    $ev_start = sanitize($_POST['start_time'] ?? '08:00:00');
    $ev_end = sanitize($_POST['end_time'] ?? '17:00:00');

    if (!empty($ev_title) && !empty($ev_date)) {
        try {
            $ins = $db->prepare("INSERT INTO calendar_events (title, description, event_date, start_time, end_time) VALUES (:t, :d, :dt, :st, :et)");
            if ($ins->execute([
                ':t' => $ev_title,
                ':d' => $ev_desc,
                ':dt' => $ev_date,
                ':st' => $ev_start,
                ':et' => $ev_end
            ])) {
                $cal_success = "Service schedule event successfully added to the master calendar!";
            } else {
                $cal_error = "Failed to add event to the calendar.";
            }
        } catch (Exception $e) {
            $cal_error = "Database error: " . $e->getMessage();
        }
    } else {
        $cal_error = "Please provide both an event title and schedule date.";
    }
}

// Interactive Staff Calendar Engine (Part 2, Item 12)
$cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');
$cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');
if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1; $cal_year++; }

$prev_month = $cal_month - 1;
$prev_year = $cal_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $cal_month + 1;
$next_year = $cal_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$month_timestamp = mktime(0, 0, 0, $cal_month, 1, $cal_year);
$month_name = date('F Y', $month_timestamp);
$days_in_month = (int)date('t', $month_timestamp);
$first_day_of_week = (int)date('N', $month_timestamp); // 1 = Monday, 7 = Sunday

// Initialize day map for the entire month
$day_data_map = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    $day_data_map[$d] = [
        'day' => $d,
        'date' => sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d),
        'events' => [],
        'appointments' => [],
        'aics' => [],
        'housing' => []
    ];
}

try {
    // 1. Month Appointments
    $app_q = "SELECT a.appointment_id, a.reference_number, a.appointment_date, a.appointment_time, a.status, a.window_assignment,
                     s.service_name, u.first_name, u.last_name, u.contact_number 
              FROM appointments a 
              LEFT JOIN services s ON a.service_id = s.service_id 
              JOIN users u ON a.user_id = u.user_id 
              WHERE MONTH(a.appointment_date) = :m AND YEAR(a.appointment_date) = :y
              ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    $stmt = $db->prepare($app_q);
    $stmt->execute([':m' => $cal_month, ':y' => $cal_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)date('j', strtotime($row['appointment_date']));
        if (isset($day_data_map[$d])) {
            $day_data_map[$d]['appointments'][] = $row;
        }
    }

    // 2. Month Calendar Events / Service Announcements
    $ev_q = "SELECT * FROM calendar_events 
             WHERE MONTH(event_date) = :m AND YEAR(event_date) = :y 
             ORDER BY event_date ASC, start_time ASC";
    $stmt = $db->prepare($ev_q);
    $stmt->execute([':m' => $cal_month, ':y' => $cal_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)date('j', strtotime($row['event_date']));
        if (isset($day_data_map[$d])) {
            $day_data_map[$d]['events'][] = $row;
        }
    }

    // 3. Month AICS Requests
    $aics_q = "SELECT a.request_id, a.submission_date, a.status, a.amount_requested, c.category_name, u.first_name, u.last_name 
               FROM aics_requests a 
               JOIN users u ON a.user_id = u.user_id 
               LEFT JOIN aics_categories c ON a.category_id = c.category_id 
               WHERE MONTH(a.submission_date) = :m AND YEAR(a.submission_date) = :y";
    $stmt = $db->prepare($aics_q);
    $stmt->execute([':m' => $cal_month, ':y' => $cal_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)date('j', strtotime($row['submission_date']));
        if (isset($day_data_map[$d])) {
            $day_data_map[$d]['aics'][] = $row;
        }
    }

    // 4. Month Housing Applications
    $hsg_q = "SELECT h.beneficiary_id, h.application_date, h.status, p.program_name, u.first_name, u.last_name 
              FROM housing_beneficiaries h 
              JOIN users u ON h.user_id = u.user_id 
              JOIN housing_programs p ON h.program_id = p.program_id 
              WHERE MONTH(h.application_date) = :m AND YEAR(h.application_date) = :y";
    $stmt = $db->prepare($hsg_q);
    $stmt->execute([':m' => $cal_month, ':y' => $cal_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (int)date('j', strtotime($row['application_date']));
        if (isset($day_data_map[$d])) {
            $day_data_map[$d]['housing'][] = $row;
        }
    }
} catch (Exception $e) {}

// Today's Booked Appointments for Upcoming Feed
$today_appts = [];
try {
    $app_query = "SELECT a.*, s.service_name, u.first_name, u.last_name 
                  FROM appointments a 
                  LEFT JOIN services s ON a.service_id = s.service_id 
                  JOIN users u ON a.user_id = u.user_id 
                  WHERE a.appointment_date = CURDATE()
                  ORDER BY a.appointment_time ASC 
                  LIMIT 4";
    $today_appts = $db->query($app_query)->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: If no appointments today, show upcoming 4
    if (empty($today_appts)) {
        $up_query = "SELECT a.*, s.service_name, u.first_name, u.last_name 
                     FROM appointments a 
                     LEFT JOIN services s ON a.service_id = s.service_id 
                     JOIN users u ON a.user_id = u.user_id 
                     WHERE a.appointment_date >= CURDATE()
                     ORDER BY a.appointment_date ASC, a.appointment_time ASC 
                     LIMIT 4";
        $today_appts = $db->query($up_query)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Staff Initials
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name'] ?? 'Staff Member');
foreach ($name_parts as $part) {
    if (!empty($part)) $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2) ?: 'ST';

// Profile picture path
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = '';
if (!empty($profile_picture)) {
    if (file_exists(__DIR__ . '/' . $profile_picture)) {
        $avatar_url = $profile_picture;
    } elseif (file_exists(__DIR__ . '/../' . $profile_picture)) {
        $avatar_url = '../' . $profile_picture;
    }
}

// Dark mode
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
$page_title = 'Staff Dashboard - One Palayan-Kalinga Hub';
$portal_context = 'root';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600;1,700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo filemtime(__DIR__ . '/css/staff-portal.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
    /* ================= CASEWORK STAT CARDS (VERTICAL STACK IN LEFT COLUMN) ================= */
    .casework-stats-row {
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
        margin-bottom: 0 !important;
    }

    .column-left .compact-stat-card {
        padding: 12px 16px !important;
        gap: 12px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .column-left .compact-stat-icon {
        width: 42px !important;
        height: 42px !important;
        font-size: 1.1rem !important;
    }
    .column-left .compact-stat-value {
        font-size: 1.3rem !important;
    }
    .column-left .compact-stat-label {
        font-size: 0.68rem !important;
    }

    /* ================= 3 COLORED SERVICE DESK CARDS (IN RIGHT COLUMN) ================= */
    .colored-cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-bottom: 16px;
    }
    @media (max-width: 1200px) {
        .colored-cards-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 900px) {
        .colored-cards-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Compact Service Cards to pull dashboard content above fold */
    .colored-cards-grid .ref-service-card.service-card-minimal {
        min-height: 128px !important;
        padding: 16px 18px !important;
        border-radius: 20px !important;
    }
    .colored-cards-grid .service-card-top-tag {
        margin-bottom: 6px !important;
    }
    .colored-cards-grid .service-category-pill {
        padding: 3px 9px !important;
        font-size: 0.65rem !important;
    }
    .colored-cards-grid .service-card-minimal .service-card-title {
        font-size: 1.15rem !important;
        line-height: 1.2 !important;
    }
    .colored-cards-grid .service-card-bottom-minimal {
        margin-top: 12px !important;
    }
    .colored-cards-grid .service-card-action-text {
        font-size: 0.70rem !important;
    }
    .colored-cards-grid .service-card-arrow-btn {
        width: 34px !important;
        height: 34px !important;
    }
    .colored-cards-grid .service-card-giant-watermark {
        font-size: 130px !important;
        right: -30px !important;
        bottom: -30px !important;
    }

    .compact-stat-card {
        background: var(--ref-card, #FFFFFF) !important;
        border: 1px solid var(--ref-border, #E2E8F0) !important;
        border-radius: 20px !important;
        padding: 14px 16px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        box-shadow: var(--ref-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04)) !important;
        text-decoration: none !important;
        color: inherit !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
        cursor: pointer !important;
        position: relative !important;
        overflow: visible !important;
        box-sizing: border-box !important;
    }

    .compact-stat-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
        border-color: rgba(127, 3, 3, 0.25) !important;
    }

    .compact-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .compact-stat-icon.orange {
        background: rgba(245, 158, 11, 0.15);
        color: #D97706;
    }
    .compact-stat-icon.green {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }
    .compact-stat-icon.blue {
        background: rgba(59, 130, 246, 0.15);
        color: #2563EB;
    }
    .compact-stat-icon.purple {
        background: rgba(139, 92, 246, 0.15);
        color: #7C3AED;
    }
    .compact-stat-icon.maroon {
        background: rgba(127, 3, 3, 0.12);
        color: var(--ref-maroon, #7F0303);
    }
    .compact-stat-icon.teal {
        background: rgba(20, 184, 166, 0.15);
        color: #0D9488;
    }

    .compact-stat-body {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .compact-stat-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--ref-text, #1E293B);
        line-height: 1.1;
    }

    .compact-stat-label {
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--ref-muted, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.02em;
        white-space: normal;
        line-height: 1.2;
        margin-top: 3px;
    }

    .compact-stat-badge {
        font-size: 0.62rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 10px;
        background: var(--ref-canvas, #F1F5F9);
        color: var(--ref-muted, #64748B);
        white-space: nowrap;
        position: absolute;
        top: 9px;
        right: 10px;
        line-height: 1;
    }

    /* ================= TREND CHART CARD ================= */
    .trend-chart-card {
        background: var(--ref-card, #FFFFFF);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 20px;
        padding: 20px;
        box-shadow: var(--ref-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.04));
        margin-bottom: 22px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .trend-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--ref-border, #E2E8F0);
    }

    .chart-title-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .trend-period-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 12px;
        background: rgba(127, 3, 3, 0.08);
        color: var(--ref-maroon, #7F0303);
        border: 1px solid rgba(127, 3, 3, 0.15);
    }

    [data-theme="dark"] .trend-period-badge {
        background: rgba(229, 115, 115, 0.15);
        color: #ff8a80;
        border-color: rgba(229, 115, 115, 0.25);
    }

    .trend-controls-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .trend-filter-select-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--ref-canvas, #F1F5F9);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 0.72rem;
        color: var(--ref-muted, #64748B);
        transition: all 0.2s ease;
    }

    .trend-filter-select-wrap:focus-within {
        border-color: var(--ref-maroon, #7F0303);
        box-shadow: 0 0 0 2px rgba(127, 3, 3, 0.12);
    }

    .chart-filter-select {
        background: transparent;
        border: none;
        outline: none;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--ref-text, #1E293B);
        cursor: pointer;
        font-family: inherit;
        padding: 2px 4px;
    }

    .chart-filter-select option,
    .chart-filter-select optgroup {
        background: var(--ref-card, #FFFFFF);
        color: var(--ref-text, #1E293B);
    }

    .chart-tabs {
        display: flex;
        gap: 4px;
        background: var(--ref-canvas, #F1F5F9);
        padding: 3px;
        border-radius: 24px;
        border: 1px solid var(--ref-border, #E2E8F0);
        flex-wrap: wrap;
    }

    .chart-tab {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        background: none;
        color: var(--ref-muted, #64748B);
        transition: all 0.2s;
    }

    .chart-tab.active {
        background: var(--ref-maroon, #7F0303);
        color: #FFFFFF;
        box-shadow: 0 2px 8px rgba(127, 3, 3, 0.3);
    }
    
    /* ================= 3 SERVICES VERTICAL STACK (IN LEFT COLUMN) ================= */
    .services-vertical-stack {
        display: flex;
        flex-direction: column;
        gap: 14px;
        width: 100%;
        margin-top: 4px;
    }

    .ref-service-card.service-card-vertical {
        min-height: 120px !important;
        padding: 18px 20px !important;
        border-radius: 22px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        position: relative !important;
        overflow: hidden !important;
        cursor: pointer !important;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease !important;
    }

    .ref-service-card.service-card-vertical:hover {
        transform: translateY(-3px) !important;
    }

    .service-card-vertical .service-card-top-tag {
        margin-bottom: 6px !important;
        position: relative;
        z-index: 2;
    }

    .service-card-vertical .service-category-pill {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        padding: 3px 10px !important;
        font-size: 0.68rem !important;
        border-radius: 14px !important;
        font-weight: 700 !important;
        letter-spacing: 0.02em !important;
        text-transform: uppercase !important;
    }

    .service-card-vertical .service-card-title {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        font-size: 1.18rem !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
        letter-spacing: -0.015em !important;
        margin: 0 !important;
        position: relative;
        z-index: 2;
    }

    .service-card-vertical .service-card-bottom-minimal {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-top: 12px !important;
        position: relative;
        z-index: 2;
    }

    .service-card-vertical .service-card-action-text {
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        color: var(--ref-muted, #64748B) !important;
        letter-spacing: 0.03em !important;
        text-transform: uppercase !important;
        transition: color 0.2s !important;
    }

    .service-card-vertical:hover .service-card-action-text {
        color: #7F0303 !important;
    }

    [data-theme="dark"] .service-card-vertical:hover .service-card-action-text {
        color: #E8D5A8 !important;
    }

    .service-card-vertical .service-card-arrow-btn {
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        background: #FFFFFF !important;
        color: #1E293B !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.85rem !important;
        border: 1px solid rgba(0, 0, 0, 0.06) !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06) !important;
        transition: all 0.22s ease !important;
        text-decoration: none !important;
    }

    .service-card-vertical:hover .service-card-arrow-btn {
        background: var(--ref-maroon, #7F0303) !important;
        color: #FFFFFF !important;
        transform: translateX(3px) !important;
        box-shadow: 0 4px 14px rgba(127, 3, 3, 0.25) !important;
    }

    [data-theme="dark"] .service-card-vertical .service-card-arrow-btn {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #FFFFFF !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    [data-theme="dark"] .service-card-vertical:hover .service-card-arrow-btn {
        background: #F59E0B !important;
        color: #1E293B !important;
    }

    .service-card-vertical .service-card-giant-watermark {
        position: absolute !important;
        right: -25px !important;
        bottom: -28px !important;
        font-size: 130px !important;
        line-height: 1 !important;
        pointer-events: none !important;
        user-select: none !important;
        transition: all 0.35s ease !important;
        z-index: 1 !important;
        color: #64748B !important;
        opacity: 0.08 !important;
    }

    .service-card-vertical:hover .service-card-giant-watermark {
        transform: scale(1.08) rotate(-4deg) !important;
        opacity: 0.13 !important;
    }

    </style>
    <style>
    /* Interactive Staff Calendar Enhancements (Part 2, Item 12) */
    .btn-add-event-pill {
        background: var(--ref-maroon);
        color: #FFFFFF !important;
        border: none;
        border-radius: 50px;
        padding: 5px 12px;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.16s ease;
    }
    .btn-add-event-pill:hover {
        background: #991B1B;
        transform: translateY(-1px);
    }
    .btn-cal-nav {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--ref-canvas);
        border: 1px solid var(--ref-border);
        color: var(--ref-text);
        text-decoration: none;
        transition: all 0.15s;
    }
    .btn-cal-nav:hover {
        background: var(--ref-maroon);
        color: #FFFFFF;
        border-color: var(--ref-maroon);
    }
    .cal-day-cell {
        height: 38px !important;
        position: relative !important;
        cursor: pointer !important;
        flex-direction: column !important;
        justify-content: flex-start !important;
        padding-top: 4px !important;
        border: 1px solid transparent !important;
        transition: all 0.15s ease !important;
    }
    .cal-day-cell:hover {
        border-color: var(--ref-maroon) !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    }
    .cal-day-cell.empty-day {
        opacity: 0.15;
        cursor: default !important;
        pointer-events: none;
        background: transparent !important;
    }
    .cal-day-cell.today {
        border: 2px solid #D97706 !important;
        background: #FEF3C7 !important;
        color: #92400E !important;
        font-weight: 800 !important;
    }
    .cal-day-cell.has-events {
        background: #FFF1F2 !important;
        color: #991B1B !important;
    }
    .cal-day-cell.has-appts {
        background: #ECFDF5 !important;
        color: #065F46 !important;
    }
    .cal-day-cell.has-both {
        background: linear-gradient(135deg, #ECFDF5 50%, #FFF1F2 50%) !important;
        font-weight: 800 !important;
    }
    .cal-dots-wrap {
        display: flex;
        gap: 3px;
        align-items: center;
        justify-content: center;
        margin-top: 2px;
    }
    .cal-dot-pip {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        display: inline-block;
    }
    .dot-red { background: #DC2626; }
    .dot-green { background: #059669; }
    .dot-amber { background: #D97706; }
    .dot-blue { background: #2563EB; }

    .calendar-legend-bar {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        padding: 8px 12px;
        margin-top: 10px;
        background: var(--ref-canvas);
        border-radius: 10px;
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--ref-muted);
    }
    .cal-leg-item { display: inline-flex; align-items: center; gap: 5px; }

    /* Activity Details & Event Modal */
    .staff-cal-modal {
        position: fixed;
        z-index: 2500;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
    }
    .staff-cal-modal.active { display: flex; }
    .cal-modal-card {
        background: var(--ref-card);
        border-radius: 20px;
        width: 90%;
        max-width: 580px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 16px 40px rgba(0,0,0,0.22);
        border: 1px solid var(--ref-border);
        animation: calPopIn 0.18s ease-out;
    }
    @keyframes calPopIn { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .cal-modal-head {
        padding: 16px 22px;
        border-bottom: 1px solid var(--ref-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--ref-card-subtle);
    }
    .cal-modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--ref-text); display: flex; align-items: center; gap: 8px; }
    .cal-modal-tabs {
        display: flex;
        border-bottom: 1px solid var(--ref-border);
        background: var(--ref-canvas);
    }
    .cal-modal-tab-btn {
        flex: 1;
        padding: 10px 16px;
        font-size: 0.78rem;
        font-weight: 700;
        border: none;
        background: none;
        color: var(--ref-muted);
        cursor: pointer;
        transition: all 0.15s;
        border-bottom: 2px solid transparent;
    }
    .cal-modal-tab-btn.active {
        color: var(--ref-maroon);
        border-bottom-color: var(--ref-maroon);
        background: var(--ref-card);
    }
    .cal-modal-body { padding: 20px 22px; }
    .cal-activity-group { margin-bottom: 18px; }
    .cal-activity-group:last-child { margin-bottom: 0; }
    .cal-group-title {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .cal-act-card {
        background: var(--ref-canvas);
        border-radius: 12px;
        padding: 10px 14px;
        border: 1px solid var(--ref-border);
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }
    .cal-act-card:last-child { margin-bottom: 0; }
    .cal-form-group { margin-bottom: 14px; }
    .cal-form-group label { display: block; margin-bottom: 5px; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; color: var(--ref-muted); }
    .cal-form-group input, .cal-form-group textarea, .cal-form-group select {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--ref-border);
        border-radius: 10px;
        font-size: 0.82rem;
        background: var(--ref-canvas);
        color: var(--ref-text);
        box-sizing: border-box;
    }
    .cal-form-group input:focus, .cal-form-group textarea:focus { border-color: var(--ref-maroon); }

    /* 3 Colored Service Cards Minimalist UI (Matches Resident Dashboard) */
    .ref-service-card.service-card-minimal {
        min-height: 205px;
        padding: 26px 24px;
        border-radius: 26px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .ref-service-card.service-card-minimal:hover {
        transform: translateY(-4px);
    }
    .ref-service-card.card-yellow {
        background: #FEF7DF;
        border: 1px solid rgba(217, 119, 6, 0.22);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    }
    .ref-service-card.card-yellow:hover {
        box-shadow: 0 8px 24px rgba(217, 119, 6, 0.12);
        border-color: rgba(217, 119, 6, 0.35);
    }
    .ref-service-card.card-blue {
        background: #E7F1FE;
        border: 1px solid rgba(59, 130, 246, 0.22);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    }
    .ref-service-card.card-blue:hover {
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.12);
        border-color: rgba(59, 130, 246, 0.35);
    }
    .ref-service-card.card-pink {
        background: #FDE8E8;
        border: 1px solid rgba(239, 68, 68, 0.22);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    }
    .ref-service-card.card-pink:hover {
        box-shadow: 0 8px 24px rgba(239, 68, 68, 0.12);
        border-color: rgba(239, 68, 68, 0.35);
    }
    [data-theme="dark"] .ref-service-card.card-yellow {
        background: #252014;
        border-color: rgba(217, 119, 6, 0.4);
        border-top-color: #F59E0B;
    }
    [data-theme="dark"] .ref-service-card.card-blue {
        background: #142230;
        border-color: rgba(2, 132, 199, 0.4);
        border-top-color: #38BDF8;
    }
    [data-theme="dark"] .ref-service-card.card-pink {
        background: #27161b;
        border-color: rgba(225, 29, 72, 0.4);
        border-top-color: #FB7185;
    }
    .service-card-giant-watermark {
        position: absolute;
        right: -45px;
        bottom: -45px;
        font-size: 195px;
        line-height: 1;
        pointer-events: none;
        user-select: none;
        transition: all 0.35s ease;
        z-index: 1;
        color: #64748B !important;
        opacity: 0.08 !important;
    }
    .ref-service-card:hover .service-card-giant-watermark {
        transform: scale(1.08) rotate(-4deg);
        opacity: 0.12 !important;
    }
    .service-card-top-tag {
        position: relative;
        z-index: 2;
        margin-bottom: 12px;
    }
    .service-category-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .pill-yellow { background: rgba(217, 119, 6, 0.12); color: #B45309; }
    .pill-blue { background: rgba(2, 132, 199, 0.12); color: #0369A1; }
    .pill-pink { background: rgba(225, 29, 72, 0.12); color: #BE123C; }
    .service-card-minimal .service-card-title {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        color: #1E293B;
        margin: 0;
        line-height: 1.22;
        letter-spacing: -0.02em;
        position: relative;
        z-index: 2;
    }
    [data-theme="dark"] .service-card-minimal .service-card-title {
        color: #F8FAFC;
    }
    .service-card-bottom-minimal {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 24px;
        position: relative;
        z-index: 2;
    }
    .service-card-action-text {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--ref-muted, #64748B);
        letter-spacing: 0.02em;
        text-transform: uppercase;
        transition: color 0.2s;
    }
    .ref-service-card:hover .service-card-action-text {
        color: #7F0303;
    }
    [data-theme="dark"] .ref-service-card:hover .service-card-action-text {
        color: #E8D5A8;
    }
    .service-card-arrow-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #FFFFFF;
        color: #1E293B;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        border: 1px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        transition: all 0.22s ease;
        text-decoration: none;
    }
    .ref-service-card:hover .service-card-arrow-btn {
        background: var(--ref-maroon);
        color: #FFFFFF;
        transform: translateX(4px);
        box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25);
    }
    [data-theme="dark"] .service-card-arrow-btn {
        background: rgba(255, 255, 255, 0.08);
        color: #FFFFFF;
        border-color: rgba(255, 255, 255, 0.12);
    }
    [data-theme="dark"] .ref-service-card:hover .service-card-arrow-btn {
        background: #F59E0B;
        color: #1E293B;
    }

    /* Compact Action Pills (Resident Style) */
    .compact-action-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        background: var(--ref-canvas, #F1F5F9);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 12px;
        text-decoration: none;
        color: var(--ref-text, #1E293B);
        font-size: 0.78rem;
        font-weight: 700;
        transition: all 0.2s ease;
    }
    .compact-action-pill:hover {
        background: #FFFFFF;
        border-color: var(--ref-maroon, #7F0303);
        color: var(--ref-maroon, #7F0303);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .compact-action-pill i {
        color: var(--ref-maroon, #7F0303);
        font-size: 0.92rem;
    }
    [data-theme="dark"] .compact-action-pill {
        background: rgba(255, 255, 255, 0.04);
        border-color: rgba(255, 255, 255, 0.08);
        color: #F8FAFC;
    }
    [data-theme="dark"] .compact-action-pill:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: #F59E0B;
        color: #F59E0B;
    }
    [data-theme="dark"] .compact-action-pill i {
        color: #F59E0B;
    }
    </style>
    <script>
        (function() {
            var theme = '<?php echo $dark_mode ? 'dark' : 'light'; ?>';
            try {
                var stored = localStorage.getItem('theme');
                if (stored) theme = stored;
            } catch(e) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>

    <!-- Outer Dashboard Frame (Matches Resident Portal Frame) -->
    <div class="dashboard-container">

        <!-- 1. MINIMALIST 76PX DARK DOCK SIDEBAR -->
        <?php 
        $active_nav = 'dashboard';
        require_once __DIR__ . '/staff/includes/staff-sidebar.php'; 
        ?>

        <!-- 2. MAIN DASHBOARD CONTENT AREA -->
        <main class="dashboard-main">

            <!-- Unified Staff Portal Header Bar -->
            <?php require_once __DIR__ . '/staff/includes/staff-header.php'; ?>

            <!-- Subheader Bar (Back arrow, Title, Status Pills) -->
            <div class="dash-subheader">
                <div class="subheader-title-wrap">
                    <a href="index.php" class="btn-back-arrow" title="Return to City Portal Home">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1>Staff Operations &amp; Desk</h1>
                </div>

                <div class="subheader-status-wrap">
                    <span class="status-badge-pill verified">
                        <i class="fas fa-shield-alt"></i> Staff Officer Active
                    </span>
                    <span class="status-badge-pill">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F, Y'); ?>
                    </span>
                </div>
            </div>

            <!-- Main Content 2-Column Grid (390px 1fr) -->
            <div class="dash-content-grid">

                <!-- ================= LEFT COLUMN (390px) ================= -->
                <div class="column-left">

                    <!-- Card 1A: Staff Officer Profile Card -->
                    <section class="ref-card-profile">
                        <div class="profile-card-top">
                            <div class="profile-user-summary">
                                <div class="profile-avatar-circle">
                                    <?php if (!empty($avatar_url)): ?>
                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Staff Portrait">
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($initials); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-user-headings">
                                    <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                                    <p>City Social Welfare Officer &bull; Desk Duty</p>
                                </div>
                            </div>
                        </div>

                        <!-- Officer Quick Action Circles (Copied from Resident Dashboard) -->
                        <div class="profile-action-circles">
                            <a href="profile.php" class="action-circle-btn" title="Profile Overview">
                                <i class="fas fa-user"></i>
                            </a>
                            <a href="update-profile-info.php" class="action-circle-btn" title="Profile Completion &amp; Demographics">
                                <i class="fas fa-user-edit"></i>
                            </a>
                            <a href="verify-resident.php" class="action-circle-btn" title="Residency Verification">
                                <i class="fas fa-id-card"></i>
                            </a>
                        </div>

                        <!-- Bottom Slots Row (Shift Status & Today's Appointments Counter) -->
                        <div class="profile-slots-row">
                            <div class="slot-pill">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('h:i A'); ?> &bull; Active Duty</span>
                            </div>
                            <div class="meetings-counter-badge">
                                <span>Today's Visits</span>
                                <span class="counter-pill-black"><?php echo $stats['today_appointments']; ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- Casework Stat Cards (In Left Desk Column) -->
                    <div class="casework-stats-row">
                        <!-- Card 1: AICS Requests -->
                        <a href="staff/manage-aics.php" class="compact-stat-card" title="Manage AICS Assistance Requests">
                            <div class="compact-stat-icon maroon"><i class="fas fa-hand-holding-heart"></i></div>
                            <div class="compact-stat-body">
                                <div class="compact-stat-value"><?php echo number_format($casework_stats['total_aics']); ?></div>
                                <div class="compact-stat-label">AICS Requests</div>
                            </div>
                            <span class="compact-stat-badge"><?php echo $casework_stats['pending_aics'] > 0 ? $casework_stats['pending_aics'] . ' Pend' : 'All'; ?></span>
                        </a>

                        <!-- Card 2: Housing Applications -->
                        <a href="staff/manage-housing.php" class="compact-stat-card" title="Manage Housing Beneficiaries & Applications">
                            <div class="compact-stat-icon teal"><i class="fas fa-home"></i></div>
                            <div class="compact-stat-body">
                                <div class="compact-stat-value"><?php echo number_format($casework_stats['total_housing']); ?></div>
                                <div class="compact-stat-label">Housing Applications</div>
                            </div>
                            <span class="compact-stat-badge"><?php echo $casework_stats['pending_housing'] > 0 ? $casework_stats['pending_housing'] . ' Pend' : 'All'; ?></span>
                        </a>

                        <!-- Card 3: Appointments -->
                        <a href="staff/manage-appointments.php" class="compact-stat-card" title="Manage Intake Appointments & Queues">
                            <div class="compact-stat-icon green"><i class="fas fa-calendar-check"></i></div>
                            <div class="compact-stat-body">
                                <div class="compact-stat-value"><?php echo number_format($casework_stats['total_appts']); ?></div>
                                <div class="compact-stat-label">Appointments</div>
                            </div>
                            <span class="compact-stat-badge"><?php echo $casework_stats['pending_appts'] > 0 ? $casework_stats['pending_appts'] . ' Pend' : 'All'; ?></span>
                        </a>

                        <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                        <!-- Card 3: PWD Applications -->
                        <a href="staff/manage-pwd.php" class="compact-stat-card" title="Manage PWD Registry & ID Applications">
                            <div class="compact-stat-icon purple"><i class="fas fa-wheelchair"></i></div>
                            <div class="compact-stat-body">
                                <div class="compact-stat-value"><?php echo number_format($casework_stats['total_pwd']); ?></div>
                                <div class="compact-stat-label">PWD Applications</div>
                            </div>
                            <span class="compact-stat-badge"><?php echo $casework_stats['pending_pwd'] > 0 ? $casework_stats['pending_pwd'] . ' Pend' : 'All'; ?></span>
                        </a>

                        <!-- Card 4: Senior Citizens -->
                        <a href="staff/manage-senior.php" class="compact-stat-card" title="Manage OSCA Senior Citizen Applications">
                            <div class="compact-stat-icon blue"><i class="fas fa-person-walking-with-cane"></i></div>
                            <div class="compact-stat-body">
                                <div class="compact-stat-value"><?php echo number_format($casework_stats['total_senior']); ?></div>
                                <div class="compact-stat-label">Senior Citizens</div>
                            </div>
                            <span class="compact-stat-badge"><?php echo $casework_stats['pending_senior'] > 0 ? $casework_stats['pending_senior'] . ' Pend' : 'All'; ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ================= RIGHT COLUMN (1fr) ================= -->
                <div class="column-right">

                    <!-- Section Header: Active Desk Queues + Signature Cursive Title -->
                    <div class="ongoing-section-header">
                        <div class="ongoing-title-wrap">
                            <span class="ongoing-badge-pill">
                                <i class="fas fa-layer-group"></i> Active Desk Queues
                            </span>
                        </div>
                        <div class="dash-cursive-branding-inline">
                            <span class="dash-cursive-title">One Palayan - Kalinga Hub</span>
                        </div>
                        <div class="ongoing-right-wrap">
                            <span class="ongoing-subtitle">&bull; <?php echo date('Y'); ?> City Case Management</span>
                        </div>
                    </div>

                    <!-- 3 Colored Service Desk Cards Grid (In Right Column) -->
                    <div class="colored-cards-grid">

                        <!-- Card 1: Pastel Yellow (Housing Programs Desk) -->
                        <article class="ref-service-card card-yellow service-card-minimal" onclick="window.location.href='staff/manage-housing.php'">
                            <i class="fas fa-home service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-yellow"><i class="fas fa-building"></i> Shelter &amp; Lots</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">Housing Programs</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Manage Housing</span>
                                <a href="staff/manage-housing.php" class="service-card-arrow-btn" aria-label="Go to Housing Programs">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                        <!-- Card 2: Pastel Soft Blue (AICS Assistance Desk) -->
                        <article class="ref-service-card card-blue service-card-minimal" onclick="window.location.href='staff/manage-aics.php'">
                            <i class="fas fa-hand-holding-heart service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-blue"><i class="fas fa-hand-holding-usd"></i> Financial &amp; Crisis</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">AICS Assistance</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Process AICS</span>
                                <a href="staff/manage-aics.php" class="service-card-arrow-btn" aria-label="Go to AICS Assistance">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                        <!-- Card 3: Pastel Soft Pink (Appointment Desk & Queue) -->
                        <article class="ref-service-card card-pink service-card-minimal" onclick="window.location.href='staff/manage-appointments.php'">
                            <i class="fas fa-calendar-check service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-pink"><i class="fas fa-clock"></i> Consultations &amp; Queue</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">Appointment Desk</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Manage Appointments</span>
                                <a href="staff/manage-appointments.php" class="service-card-arrow-btn" aria-label="Go to Appointment Desk">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                    </div>

                    <!-- Services & Applications Trend Chart Card -->
                    <div class="trend-chart-card">
                        <div class="trend-card-header">
                            <div class="chart-title-wrap">
                                <h3 style="margin: 0; font-size: 0.98rem; font-weight: 700; color: var(--ref-text); display: flex; align-items: center;">
                                    <i class="fas fa-chart-line" style="color: var(--ref-maroon); margin-right: 8px;"></i>
                                    Services &amp; Applications Trend
                                </h3>
                                <span class="trend-period-badge" id="trendPeriodBadge">Last 6 Months</span>
                            </div>
                            <div class="trend-controls-wrap">
                                <!-- Time Range Filter -->
                                <div class="trend-filter-select-wrap">
                                    <i class="fas fa-calendar-alt"></i>
                                    <select id="trendTimeFilter" class="chart-filter-select" onchange="changeTrendPeriod(this.value)">
                                        <optgroup label="Trend Range">
                                            <option value="6m" selected>Last 6 Months</option>
                                            <option value="3m">Last 3 Months</option>
                                            <option value="12m">Last 12 Months</option>
                                            <option value="ytd">Year <?php echo date('Y'); ?> (All)</option>
                                        </optgroup>
                                        <optgroup label="Filter by Month (Daily)">
                                            <?php foreach ($available_filter_months as $ym => $label): ?>
                                                <option value="<?php echo $ym; ?>"><?php echo $label; ?></option>
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
                        <div class="canvas-wrap" style="position: relative; height: 260px; width: 100%;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                </div>

            </div>

        </main>

    </div>

    <!-- Staff Interactive Activity & Event Scheduling Modal (Part 2, Item 12) -->
    <div id="staffCalendarModal" class="staff-cal-modal">
        <div class="cal-modal-card">
            <div class="cal-modal-head">
                <h3 id="calModalTitle"><i class="fas fa-calendar-day" style="color:var(--ref-maroon);"></i> Activity Details</h3>
                <button type="button" onclick="closeStaffCalModal()" style="background:none; border:none; font-size:22px; cursor:pointer; color:var(--ref-muted);">&times;</button>
            </div>
            
            <div class="cal-modal-tabs">
                <button type="button" class="cal-modal-tab-btn active" id="tabBtnActivities" onclick="switchCalTab('activities')">
                    <i class="fas fa-list-check"></i> Activities &amp; Case Queue (<span id="calActivityCount">0</span>)
                </button>
                <button type="button" class="cal-modal-tab-btn" id="tabBtnAddEvent" onclick="switchCalTab('add_event')">
                    <i class="fas fa-calendar-plus"></i> + Schedule Service Event
                </button>
            </div>

            <div class="cal-modal-body">
                <!-- Tab Pane 1: Activity Queue Details -->
                <div id="calPaneActivities">
                    <div id="calActivitiesContent">
                        <!-- Dynamically filled by JS -->
                    </div>
                </div>

                <!-- Tab Pane 2: Add Service Event Form -->
                <div id="calPaneAddEvent" style="display:none;">
                    <form method="POST" id="calEventForm">
                        <input type="hidden" name="save_calendar_event" value="1">
                        
                        <div class="cal-form-group">
                            <label><i class="fas fa-heading"></i> Event / Announcement Title *</label>
                            <input type="text" name="event_title" id="form_event_title" placeholder="e.g. Senior Citizen Social Pension Payout" required>
                        </div>

                        <div class="cal-form-group">
                            <label><i class="fas fa-calendar-day"></i> Event Schedule Date *</label>
                            <input type="date" name="event_date" id="form_event_date" required>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="cal-form-group">
                                <label><i class="fas fa-clock"></i> Start Time</label>
                                <input type="time" name="start_time" value="08:00">
                            </div>
                            <div class="cal-form-group">
                                <label><i class="fas fa-clock"></i> End Time</label>
                                <input type="time" name="end_time" value="17:00">
                            </div>
                        </div>

                        <div class="cal-form-group">
                            <label><i class="fas fa-align-left"></i> Description &amp; Venue Instructions</label>
                            <textarea name="event_description" rows="3" placeholder="Provide instructions, requirements, or claiming details for residents..."></textarea>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                            <button type="button" onclick="closeStaffCalModal()" class="btn-cal-nav" style="width:auto; padding:0 14px; border-radius:50px;">
                                Cancel
                            </button>
                            <button type="submit" class="btn-add-event-pill" style="padding:8px 18px; font-size:0.8rem;">
                                <i class="fas fa-save"></i> Publish Service Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    let currentCalCell = null;

    function openStaffCalModal(dateStr, cellData) {
        currentCalCell = cellData || {};
        const modal = document.getElementById('staffCalendarModal');
        const titleEl = document.getElementById('calModalTitle');
        const contentEl = document.getElementById('calActivitiesContent');
        const countEl = document.getElementById('calActivityCount');
        
        // Format date string for title
        const dObj = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        titleEl.innerHTML = '<i class="fas fa-calendar-day" style="color:var(--ref-maroon);"></i> ' + dObj.toLocaleDateString('en-US', options);
        document.getElementById('form_event_date').value = dateStr;

        // Build activities list
        let html = '';
        let totalCount = 0;

        // 1. Service Events
        if (cellData && cellData.events && cellData.events.length > 0) {
            totalCount += cellData.events.length;
            html += '<div class="cal-activity-group">';
            html += '<div class="cal-group-title" style="color:#DC2626;"><i class="fas fa-bullhorn"></i> Service Notices &amp; Events (' + cellData.events.length + ')</div>';
            cellData.events.forEach(ev => {
                html += '<div class="cal-act-card" style="border-left:4px solid #DC2626;">';
                html += '  <div>';
                html += '    <strong style="color:var(--ref-text); font-size:0.84rem;">' + escapeHtml(ev.title) + '</strong>';
                if (ev.description) {
                    html += '    <div style="font-size:0.74rem; color:var(--ref-muted); margin-top:2px;">' + escapeHtml(ev.description) + '</div>';
                }
                html += '  </div>';
                html += '  <div style="font-size:0.72rem; color:var(--ref-muted); font-weight:700; white-space:nowrap;">';
                html += '    <i class="fas fa-clock"></i> ' + (ev.start_time || 'All Day');
                html += '  </div>';
                html += '</div>';
            });
            html += '</div>';
        }

        // 2. Appointments
        if (cellData && cellData.appointments && cellData.appointments.length > 0) {
            totalCount += cellData.appointments.length;
            html += '<div class="cal-activity-group">';
            html += '<div class="cal-group-title" style="color:#059669;"><i class="fas fa-calendar-check"></i> Citizen Appointments (' + cellData.appointments.length + ')</div>';
            cellData.appointments.forEach(ap => {
                const name = (ap.first_name || '') + ' ' + (ap.last_name || '');
                const srv = ap.service_name || 'CSWDO Welfare Desk';
                const ref = ap.reference_number || ('#' + ap.appointment_id);
                html += '<div class="cal-act-card" style="border-left:4px solid #059669;">';
                html += '  <div>';
                html += '    <strong style="color:var(--ref-text); font-size:0.84rem;">' + escapeHtml(name) + '</strong>';
                html += '    <div style="font-size:0.74rem; color:var(--ref-muted); margin-top:2px;">' + escapeHtml(srv) + ' &bull; <code>' + escapeHtml(ref) + '</code></div>';
                html += '  </div>';
                html += '  <div style="text-align:right;">';
                html += '    <span style="font-size:0.74rem; font-weight:700; color:var(--ref-text);"><i class="fas fa-clock"></i> ' + escapeHtml(ap.appointment_time || '') + '</span>';
                html += '    <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#059669; margin-top:2px;">' + escapeHtml(ap.status || '') + '</div>';
                html += '  </div>';
                html += '</div>';
            });
            html += '<div style="margin-top:8px; text-align:right;">';
            html += '  <a href="staff/manage-appointments.php?date=' + encodeURIComponent(dateStr) + '" style="font-size:0.74rem; font-weight:700; color:var(--ref-maroon); text-decoration:none;"><i class="fas fa-external-link-alt"></i> Open in Appointment Desk &rarr;</a>';
            html += '</div>';
            html += '</div>';
        }

        // 3. AICS Requests
        if (cellData && cellData.aics && cellData.aics.length > 0) {
            totalCount += cellData.aics.length;
            html += '<div class="cal-activity-group">';
            html += '<div class="cal-group-title" style="color:#D97706;"><i class="fas fa-hand-holding-heart"></i> AICS Requests Filed (' + cellData.aics.length + ')</div>';
            cellData.aics.forEach(ac => {
                const name = (ac.first_name || '') + ' ' + (ac.last_name || '');
                html += '<div class="cal-act-card" style="border-left:4px solid #D97706;">';
                html += '  <div>';
                html += '    <strong style="font-size:0.82rem;">' + escapeHtml(name) + '</strong>';
                html += '    <div style="font-size:0.72rem; color:var(--ref-muted);">' + escapeHtml(ac.category_name || 'Financial Relief') + '</div>';
                html += '  </div>';
                html += '  <span style="font-size:0.72rem; font-weight:700; color:#D97706;">' + escapeHtml(ac.status || '') + '</span>';
                html += '</div>';
            });
            html += '</div>';
        }

        // 4. Housing Applications
        if (cellData && cellData.housing && cellData.housing.length > 0) {
            totalCount += cellData.housing.length;
            html += '<div class="cal-activity-group">';
            html += '<div class="cal-group-title" style="color:#2563EB;"><i class="fas fa-home"></i> Housing Applications (' + cellData.housing.length + ')</div>';
            cellData.housing.forEach(hs => {
                const name = (hs.first_name || '') + ' ' + (hs.last_name || '');
                html += '<div class="cal-act-card" style="border-left:4px solid #2563EB;">';
                html += '  <div>';
                html += '    <strong style="font-size:0.82rem;">' + escapeHtml(name) + '</strong>';
                html += '    <div style="font-size:0.72rem; color:var(--ref-muted);">' + escapeHtml(hs.program_name || 'Housing Program') + '</div>';
                html += '  </div>';
                html += '  <span style="font-size:0.72rem; font-weight:700; color:#2563EB;">' + escapeHtml(hs.status || '') + '</span>';
                html += '</div>';
            });
            html += '</div>';
        }

        if (totalCount === 0) {
            html = '<div style="text-align:center; padding:32px 16px; color:var(--ref-muted);">';
            html += '  <i class="fas fa-calendar-check" style="font-size:36px; opacity:0.35; margin-bottom:10px; display:block;"></i>';
            html += '  <p style="margin:0 0 6px 0; font-weight:700; color:var(--ref-text);">No Scheduled Activities on this Date</p>';
            html += '  <small>You can schedule a service notice, payout event, or outreach session using the tab above.</small>';
            html += '</div>';
        }

        contentEl.innerHTML = html;
        countEl.textContent = totalCount;
        switchCalTab('activities');
        modal.classList.add('active');
    }

    function openAddEventModal(dateStr) {
        openStaffCalModal(dateStr, currentCalCell || {});
        switchCalTab('add_event');
    }

    function switchCalTab(tabName) {
        const btnAct = document.getElementById('tabBtnActivities');
        const btnAdd = document.getElementById('tabBtnAddEvent');
        const paneAct = document.getElementById('calPaneActivities');
        const paneAdd = document.getElementById('calPaneAddEvent');

        if (tabName === 'add_event') {
            btnAct.classList.remove('active');
            btnAdd.classList.add('active');
            paneAct.style.display = 'none';
            paneAdd.style.display = 'block';
            document.getElementById('form_event_title').focus();
        } else {
            btnAdd.classList.remove('active');
            btnAct.classList.add('active');
            paneAdd.style.display = 'none';
            paneAct.style.display = 'block';
        }
    }

    function closeStaffCalModal() {
        const modal = document.getElementById('staffCalendarModal');
        if (modal) modal.classList.remove('active');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    window.addEventListener('click', function(e) {
        const modal = document.getElementById('staffCalendarModal');
        if (e.target === modal) {
            closeStaffCalModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeStaffCalModal();
    });

    // ================= SERVICES & APPLICATIONS TREND CHART =================
    let trendChartInstance = null;

    function getTrendThemeColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            text: isDark ? '#8AA0A9' : '#71818B',
            grid: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)',
            tooltipBg: isDark ? '#152227' : '#ffffff',
            tooltipColor: isDark ? '#E5EFF2' : '#192126',
            border: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)'
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
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        const colors = getTrendThemeColors();
        const initialPeriod = document.getElementById('trendTimeFilter')?.value || defaultTrendPeriod || '6m';
        const pData = getTrendPeriodData(initialPeriod);

        const badge = document.getElementById('trendPeriodBadge');
        if (badge) badge.textContent = pData.badgeText;

        if (trendChartInstance) {
            trendChartInstance.destroy();
        }

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

    function updateTrendTheme() {
        if (!trendChartInstance) return;
        const colors = getTrendThemeColors();
        if (trendChartInstance.options.scales) {
            if (trendChartInstance.options.scales.x) {
                trendChartInstance.options.scales.x.ticks.color = colors.text;
            }
            if (trendChartInstance.options.scales.y) {
                trendChartInstance.options.scales.y.ticks.color = colors.text;
                trendChartInstance.options.scales.y.grid.color = colors.grid;
            }
        }
        if (trendChartInstance.options.plugins) {
            if (trendChartInstance.options.plugins.legend) {
                trendChartInstance.options.plugins.legend.labels.color = colors.text;
            }
            if (trendChartInstance.options.plugins.tooltip) {
                trendChartInstance.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
                trendChartInstance.options.plugins.tooltip.titleColor = colors.tooltipColor;
                trendChartInstance.options.plugins.tooltip.bodyColor = colors.tooltipColor;
                trendChartInstance.options.plugins.tooltip.borderColor = colors.border;
            }
        }
        trendChartInstance.update();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initTrendChart();
        
        // Theme switch observer
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.attributeName === 'data-theme') {
                    updateTrendTheme();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    });

    </script>

    <?php require_once __DIR__ . '/staff/includes/staff-footer.php'; ?>

</body>
</html>


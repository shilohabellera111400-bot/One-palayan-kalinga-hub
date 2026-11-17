<?php
// calendar.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$is_staff_or_admin = (isStaff() || isAdmin());

$database = new Database();
$db = $database->getConnection();

// Check resident verification only if resident
if (!$is_staff_or_admin) {
    $check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
    $verify_stmt = $db->prepare($check_verified);
    $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $verify_stmt->execute();
    $user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_verify || $user_verify['verification_status'] != 'verified') {
        redirect('verify-resident.php');
    }
}

$cal_msg = '';
$cal_err = '';

// Handle Create Event (Staff & Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event' && $is_staff_or_admin) {
    csrf_verify();
    $title = trim(sanitize($_POST['title'] ?? ''));
    $desc = trim(sanitize($_POST['description'] ?? ''));
    $e_date = trim($_POST['event_date'] ?? '');
    $s_time = !empty($_POST['start_time']) ? trim($_POST['start_time']) : null;
    $e_time = !empty($_POST['end_time']) ? trim($_POST['end_time']) : null;

    if (empty($title) || empty($e_date)) {
        $cal_err = 'Please provide both event title and date.';
    } else {
        $ins = $db->prepare("INSERT INTO calendar_events (title, description, event_date, start_time, end_time) VALUES (:t, :d, :ed, :st, :et)");
        if ($ins->execute([':t' => $title, ':d' => $desc, ':ed' => $e_date, ':st' => $s_time, ':et' => $e_time])) {
            $cal_msg = 'Event added to calendar successfully!';
            try {
                $log = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:uid, 'create_event', 'calendar_events', :nv)");
                $log->execute([':uid' => $_SESSION['user_id'], ':nv' => "Added event '$title' on $e_date"]);
            } catch (Exception $e) {}
        } else {
            $cal_err = 'Failed to create event.';
        }
    }
}

// Handle Edit Event (Staff & Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_event' && $is_staff_or_admin) {
    csrf_verify();
    $eid = (int)($_POST['event_id'] ?? 0);
    $title = trim(sanitize($_POST['title'] ?? ''));
    $desc = trim(sanitize($_POST['description'] ?? ''));
    $e_date = trim($_POST['event_date'] ?? '');
    $s_time = !empty($_POST['start_time']) ? trim($_POST['start_time']) : null;
    $e_time = !empty($_POST['end_time']) ? trim($_POST['end_time']) : null;

    if ($eid > 0 && !empty($title) && !empty($e_date)) {
        $upd = $db->prepare("UPDATE calendar_events SET title = :t, description = :d, event_date = :ed, start_time = :st, end_time = :et WHERE event_id = :id");
        if ($upd->execute([':t' => $title, ':d' => $desc, ':ed' => $e_date, ':st' => $s_time, ':et' => $e_time, ':id' => $eid])) {
            $cal_msg = 'Event updated successfully!';
            try {
                $log = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:uid, 'edit_event', 'calendar_events', :nv)");
                $log->execute([':uid' => $_SESSION['user_id'], ':nv' => "Updated event ID $eid: '$title' on $e_date"]);
            } catch (Exception $e) {}
        } else {
            $cal_err = 'Failed to update event.';
        }
    } else {
        $cal_err = 'Invalid event details.';
    }
}

// Handle Delete Event (Staff & Admin)
if (isset($_GET['delete_event']) && $is_staff_or_admin) {
    $del_id = (int)$_GET['delete_event'];
    if ($del_id > 0) {
        $del = $db->prepare("DELETE FROM calendar_events WHERE event_id = :id");
        if ($del->execute([':id' => $del_id])) {
            $cal_msg = 'Event removed from calendar.';
            try {
                $log = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, old_values) VALUES (:uid, 'delete_event', 'calendar_events', :ov)");
                $log->execute([':uid' => $_SESSION['user_id'], ':ov' => "Deleted event ID $del_id"]);
            } catch (Exception $e) {}
        }
    }
}

// Get month and year from GET parameters, default to current month/year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Fetch general calendar events for this month
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime($start_date));

$events_query = "SELECT * FROM calendar_events WHERE event_date BETWEEN :start_date AND :end_date ORDER BY start_time ASC";
$events_stmt = $db->prepare($events_query);
$events_stmt->bindParam(':start_date', $start_date);
$events_stmt->bindParam(':end_date', $end_date);
$events_stmt->execute();
$events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch announcements for this month
$announcements = [];
try {
    $ann_stmt = $db->prepare("SELECT announcement_id, title, category, content, DATE(created_at) as event_date, TIME(created_at) as start_time FROM announcements WHERE DATE(created_at) BETWEEN :start_date AND :end_date ORDER BY created_at DESC");
    $ann_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $announcements = $ann_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$event_map = [];
foreach ($events as $event) {
    $event['is_official_event'] = true;
    $event_map[$event['event_date']][] = $event;
}
$ann_map = [];
foreach ($announcements as $ann) {
    $ann_map[$ann['event_date']][] = $ann;
}

// Fetch blackout dates for this month
$blackout_dates = [];
try {
    $bo_stmt = $db->prepare("SELECT * FROM blackout_dates WHERE blackout_date BETWEEN :start_date AND :end_date ORDER BY blackout_date ASC");
    $bo_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $blackout_dates = $bo_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$blackout_map = [];
foreach ($blackout_dates as $bo) {
    $blackout_map[$bo['blackout_date']] = $bo;
}

// Fetch user's appointments for this month
$appts_query = "SELECT a.*, s.service_name 
                FROM appointments a
                JOIN services s ON a.service_id = s.service_id
                WHERE a.user_id = :user_id 
                AND a.appointment_date BETWEEN :start_date AND :end_date
                ORDER BY a.appointment_date ASC";
$appts_stmt = $db->prepare($appts_query);
$appts_stmt->bindParam(':user_id', $_SESSION['user_id']);
$appts_stmt->bindParam(':start_date', $start_date);
$appts_stmt->bindParam(':end_date', $end_date);
$appts_stmt->execute();
$appts = $appts_stmt->fetchAll(PDO::FETCH_ASSOC);

$appt_map = [];
foreach ($appts as $appt) {
    $appt_map[$appt['appointment_date']][] = $appt;
}

// Calendar Calculation
$first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
$number_days = date('t', $first_day_of_month);
$date_components = getdate($first_day_of_month);
$month_name = $date_components['month'];
$day_of_week = $date_components['wday']; // 0 (Sunday) to 6 (Saturday)

// Month names translation
$tagalog_months = [
    'January' => 'Enero',
    'February' => 'Pebrero',
    'March' => 'Marso',
    'April' => 'Abril',
    'May' => 'Mayo',
    'June' => 'Hunyo',
    'July' => 'Hulyo',
    'August' => 'Agosto',
    'September' => 'Setyembre',
    'October' => 'Oktubre',
    'November' => 'Nobyembre',
    'December' => 'Disyembre'
];
$display_month = $tagalog_months[$month_name] ?? $month_name;

// Day names translation
$tagalog_days = ['Lgo', 'Lns', 'Mrt', 'Miy', 'Huw', 'Biy', 'Sab'];

// Get user data for sidebar
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture
    : '';

// Check if dark mode is enabled
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get greeting based on time
$hour = date('H');
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
$greeting_emoji = $hour < 12 ? '🌅' : ($hour < 18 ? '☀️' : '🌙');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - One Palayan-Kalinga Hub</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <?php if ($is_staff_or_admin && isStaff()): ?>
        <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo filemtime(__DIR__ . '/css/staff-portal.css'); ?>">
    <?php endif; ?>
    <style>
        /* Avatar Constraints - Prevent image blowing out page layout */
        .portal-hdr-avatar {
            width: 38px !important;
            height: 38px !important;
            max-width: 38px !important;
            max-height: 38px !important;
            border-radius: 50% !important;
            overflow: hidden !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
        }
        .portal-hdr-avatar img {
            width: 100% !important;
            height: 100% !important;
            max-width: 38px !important;
            max-height: 38px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            display: block !important;
        }
        .sidebar-avatar-wrapper, .sidebar-avatar-large {
            overflow: hidden !important;
        }
        .sidebar-avatar-large img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
        }
        /* ========== CALENDAR GRID ========== */
        .calendar-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.32fr) minmax(330px, 0.88fr);
            gap: 20px;
            align-items: start;
        }

        .calendar-grid > .card,
        .calendar-grid > .right-panel {
            min-width: 0;
        }

        /* ========== CALENDAR ========== */
        .calendar-wrapper {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: var(--badge-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .calendar-nav h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .calendar-nav .nav-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .calendar-nav .nav-btn:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
        }

        .calendar-grid-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }

        .calendar-grid-header .day-header {
            padding: 8px 4px;
            text-align: center;
            font-weight: 700;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            background: var(--badge-bg);
            border-bottom: 2px solid var(--border-color);
        }

        .calendar-grid-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(64px, auto);
        }

        .calendar-grid-body .cell {
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 4px 5px;
            background: var(--bg-card);
            min-height: 64px;
            overflow: hidden;
            box-sizing: border-box;
            transition: background 0.2s ease;
        }

        .calendar-grid-body .cell:nth-child(7n) {
            border-right: none;
        }

        .calendar-grid-body .cell.empty {
            background: var(--bg-primary);
        }

        .calendar-grid-body .cell.today {
            background: rgba(127, 3, 3, 0.05);
            border-color: var(--maroon-light);
        }

        .calendar-grid-body .cell .date-num {
            font-weight: 700;
            font-size: 0.72rem;
            color: var(--text-secondary);
            margin-bottom: 3px;
            line-height: 1;
        }

        .calendar-grid-body .cell.today .date-num {
            color: var(--maroon);
        }

        .calendar-grid-body .cell .events {
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow: hidden;
        }

        .calendar-grid-body .cell .event-dot {
            font-size: 0.58rem;
            line-height: 1.25;
            padding: 2px 5px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            cursor: pointer;
            max-width: 100%;
            box-sizing: border-box;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .calendar-grid-body .cell .event-dot:hover {
            transform: translateY(-1px);
            filter: brightness(1.1);
        }

        .calendar-grid-body .cell .event-more-tag {
            font-size: 0.54rem;
            color: var(--text-muted);
            font-weight: 700;
            padding: 1px 3px;
            line-height: 1;
            display: inline-block;
            cursor: pointer;
        }

        .event-dot.appt {
            background: var(--sky);
        }

        .event-dot.event {
            background: var(--maroon);
        }

        /* ========== RIGHT PANEL ========== */
        .right-panel .card {
            margin-bottom: 24px;
        }

        .right-panel .card:last-child {
            margin-bottom: 0;
        }

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-element);
            border-left: 4px solid var(--border-color);
            background: var(--badge-bg);
            transition: all 0.3s ease;
        }

        .info-box:hover {
            transform: translateX(4px);
        }

        .info-box.blue {
            border-left-color: var(--sky);
        }

        .info-box.maroon {
            border-left-color: var(--maroon);
        }

        .info-box .info-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .info-box.blue .info-icon {
            background: rgba(66, 165, 245, 0.12);
            color: var(--sky);
        }

        .info-box.maroon .info-icon {
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon);
        }

        .info-box h4 {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .info-box p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .event-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .event-list::-webkit-scrollbar {
            width: 4px;
        }

        .event-list::-webkit-scrollbar-track {
            background: var(--badge-bg);
            border-radius: 4px;
        }

        .event-list::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        .event-item {
            padding: 10px 14px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .event-item:hover {
            border-color: var(--gold);
            transform: translateX(4px);
        }

        .event-item .event-type {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-item .event-type.appt {
            color: var(--sky);
        }

        .event-item .event-type.event {
            color: var(--maroon);
        }

        .event-item .event-date {
            font-size: 0.6rem;
            color: var(--text-muted);
        }

        .event-item h4 {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 2px;
        }

        .event-item p {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.3;
            display: block;
            margin-bottom: 12px;
        }

        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            transform: scale(0.97);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }

            .top-bar-left {
                width: 100%;
                justify-content: space-between;
            }

            .page-title h1 {
                font-size: 1.3rem;
            }

            .top-bar-actions {
                justify-content: stretch;
                flex-wrap: wrap;
                width: 100%;
            }

            .card {
                padding: 20px;
            }

            .calendar-grid-body .cell {
                min-height: 70px;
                padding: 4px 6px;
            }

            .calendar-grid-body .cell .event-dot {
                font-size: 0.5rem;
                padding: 1px 4px;
            }

            .calendar-nav h3 {
                font-size: 0.9rem;
            }

            .calendar-nav {
                padding: 12px 16px;
            }

            .hamburger-btn span {
                display: inline;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 14px;
            }

            .card {
                padding: 16px;
            }

            .calendar-grid-body .cell {
                min-height: 60px;
            }

            .calendar-grid-body .cell .date-num {
                font-size: 0.7rem;
            }

            .event-item {
                padding: 8px 12px;
            }

            .event-item h4 {
                font-size: 0.75rem;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .hamburger-btn {
                padding: 6px 10px;
                font-size: 1rem;
            }

            .hamburger-btn span {
                font-size: 0.65rem;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar based on role -->
        <?php if ($is_staff_or_admin): ?>
            <?php if (isStaff()): ?>
                <?php include __DIR__ . '/staff/includes/staff-sidebar.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header based on role -->
            <?php if (isStaff()): ?>
                <?php include __DIR__ . '/staff/includes/staff-header.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/portal-header.php'; ?>
            <?php endif; ?>

            <?php if (!empty($cal_msg)): ?>
                <div style="background: #D1FAE5; color: #065F46; padding: 12px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($cal_msg); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($cal_err)): ?>
                <div style="background: #FEE2E2; color: #991B1B; padding: 12px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($cal_err); ?>
                </div>
            <?php endif; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "Events & Schedules Calendar";
            $banner_subtitle = $is_staff_or_admin 
                ? "Manage official city schedules, temporary events, and monitor appointment traffic"
                : "View your scheduled intake appointments, official city schedules, and announcements";
            $banner_icon = "fa-calendar-alt";
            if ($is_staff_or_admin) {
                $banner_actions = [
                    ["label" => "Add Temporary Event", "url" => "javascript:openAddEventModal()", "icon" => "fa-plus-circle", "type" => "primary"],
                    ["label" => "Staff Dashboard", "url" => "staff-dashboard.php", "icon" => "fa-th-large", "type" => "secondary"]
                ];
            } else {
                $banner_actions = [
                    ["label" => "Book Appointment", "url" => "appointments.php", "icon" => "fa-calendar-plus", "type" => "primary"],
                    ["label" => "Citizen Dashboard", "url" => "dashboard.php", "icon" => "fa-th-large", "type" => "secondary"]
                ];
            }
            include __DIR__ . "/includes/portal-banner.php";
            ?>

            <!-- Calendar Grid -->
            <div class="calendar-grid">
                <!-- Left Panel: Calendar View -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-day"></i>
                        <h3>Calendar View</h3>
                    </div>

                    <div class="calendar-wrapper">
                        <!-- Navigation -->
                        <div class="calendar-nav">
                            <a href="calendar.php?month=<?php echo $month - 1; ?>&year=<?php echo $year; ?>"
                                class="nav-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <h3>
                                <?php echo htmlspecialchars($display_month) . " " . $year; ?>
                            </h3>
                            <a href="calendar.php?month=<?php echo $month + 1; ?>&year=<?php echo $year; ?>"
                                class="nav-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>

                        <!-- Day Headers -->
                        <div class="calendar-grid-header">
                            <?php foreach ($tagalog_days as $day): ?>
                                <div class="day-header">
                                    <?php echo $day; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Calendar Body -->
                        <div class="calendar-grid-body">
                            <?php
                            // Render Empty Cells until the first day of the week
                            for ($i = 0; $i < $day_of_week; $i++) {
                                echo '<div class="cell empty"></div>';
                            }

                            // Render Days of the Month
                            for ($day = 1; $day <= $number_days; $day++) {
                                $current_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                $is_today = ($current_date === date('Y-m-d'));

                                $cell_classes = 'cell';
                                if ($is_today)
                                    $cell_classes .= ' today';

                                echo '<div class="' . $cell_classes . '">';
                                echo '<div class="date-num">' . $day . '</div>';

                                echo '<div class="events">';
                                $day_items = [];
                                // Check for blackout date
                                if (isset($blackout_map[$current_date])) {
                                    $bo = $blackout_map[$current_date];
                                    $day_items[] = [
                                        'type' => 'blackout',
                                        'title' => $bo['reason'] ?: 'Walang Pasok',
                                        'icon' => '⛔',
                                        'full' => 'Blackout Date / Walang Pasok: ' . ($bo['reason'] ?: 'Office Closed')
                                    ];
                                    $cell_classes .= ' blackout-date';
                                }
                                // Check for user's appointments
                                if (isset($appt_map[$current_date])) {
                                    foreach ($appt_map[$current_date] as $a) {
                                        $day_items[] = [
                                            'type' => 'appt',
                                            'title' => $a['service_name'],
                                            'icon' => '📋',
                                            'full' => 'Appointment: ' . $a['service_name']
                                        ];
                                    }
                                }
                                // Check for general events
                                if (isset($event_map[$current_date])) {
                                    foreach ($event_map[$current_date] as $e) {
                                        $day_items[] = [
                                            'type' => 'event',
                                            'title' => $e['title'],
                                            'icon' => '📅',
                                            'full' => 'Event: ' . $e['title']
                                        ];
                                    }
                                }
                                // Check for announcements
                                if (isset($ann_map[$current_date])) {
                                    foreach ($ann_map[$current_date] as $an) {
                                        $day_items[] = [
                                            'type' => 'ann',
                                            'title' => $an['title'],
                                            'icon' => '📢',
                                            'full' => 'Announcement: ' . $an['title']
                                        ];
                                    }
                                }

                                $visible_items = array_slice($day_items, 0, 2);
                                $overflow_count = count($day_items) - count($visible_items);

                                foreach ($visible_items as $item) {
                                    $short_label = $item['title'];
                                    if (mb_strlen($short_label) > 13) {
                                        $short_label = mb_substr($short_label, 0, 12) . '…';
                                    }
                                    $extra_style = '';
                                    if ($item['type'] === 'ann') {
                                        $extra_style = ' style="background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;"';
                                    } elseif ($item['type'] === 'blackout') {
                                        $extra_style = ' style="background:#334155; color:#F8FAFC; border:1px solid #64748B;"';
                                    }
                                    echo '<span class="event-dot ' . $item['type'] . '"' . $extra_style . ' title="' . htmlspecialchars($item['full']) . '">' . $item['icon'] . ' ' . htmlspecialchars($short_label) . '</span>';
                                }

                                if ($overflow_count > 0) {
                                    echo '<span class="event-more-tag" title="' . $overflow_count . ' more item(s) on this date">+' . $overflow_count . ' more</span>';
                                }
                                echo '</div>';

                                echo '</div>';
                            }

                            // Render Empty Cells to complete grid rows if needed
                            $total_cells = $day_of_week + $number_days;
                            $remaining_cells = 7 - ($total_cells % 7);
                            if ($remaining_cells < 7) {
                                for ($i = 0; $i < $remaining_cells; $i++) {
                                    echo '<div class="cell empty"></div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Events & Appointments -->
                <div class="right-panel">
                    <!-- Info Cards -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list-ul"></i>
                            <h3>This Month's Overview</h3>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div class="info-box blue">
                                <div class="info-icon"><i class="fas fa-calendar-check"></i></div>
                                <div>
                                    <h4>Your Appointments</h4>
                                    <p>You have
                                        <?php echo count($appts); ?> appointment(s) this month
                                    </p>
                                </div>
                            </div>

                            <div class="info-box maroon">
                                <div class="info-icon"><i class="fas fa-bullhorn"></i></div>
                                <div>
                                    <h4>CSWDO Events</h4>
                                    <p>
                                        <?php echo count($events); ?> event(s) scheduled this month
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_staff_or_admin): ?>
                            <a href="javascript:openAddEventModal()" class="btn-primary" style="margin-top: 16px; width: 100%; justify-content: center;">
                                <i class="fas fa-plus-circle"></i> Add Calendar Event
                            </a>
                        <?php else: ?>
                            <a href="appointments.php" class="btn-primary" style="margin-top: 16px; width: 100%; justify-content: center;">
                                <i class="fas fa-calendar-plus"></i> Book New Appointment
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Event List -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clock"></i>
                            <h3>Upcoming Events & Schedules</h3>
                            <span
                                style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 20px;">
                                <?php echo count($appts) + count($events) + count($blackout_dates); ?> total
                            </span>
                        </div>

                        <?php if (empty($appts) && empty($events) && empty($blackout_dates)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No events, appointments, or holidays scheduled for this month.</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
                                <?php foreach ($blackout_dates as $bo): ?>
                                    <div class="event-item" style="border-left: 3px solid #64748B;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="event-type" style="background: #334155; color: #F8FAFC;">Walang Pasok / Blackout</span>
                                            <span class="event-date">
                                                <?php echo date('M d', strtotime($bo['blackout_date'])); ?>
                                            </span>
                                        </div>
                                        <h4><?php echo htmlspecialchars($bo['reason'] ?: 'CSWDO Holiday / Non-Working Day'); ?></h4>
                                        <p style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-ban"></i> Opisyal na non-working day sa Palayan City CSWDO</p>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($appts as $a): ?>
                                    <div class="event-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="event-type appt">Appointment</span>
                                            <span class="event-date">
                                                <?php echo date('M d', strtotime($a['appointment_date'])); ?>
                                            </span>
                                        </div>
                                        <h4>
                                            <?php echo htmlspecialchars($a['service_name']); ?>
                                        </h4>
                                        <?php if (!empty($a['appointment_time'])): ?>
                                            <p><i class="far fa-clock"></i>
                                                <?php echo date('h:i A', strtotime($a['appointment_time'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach ($events as $e): ?>
                                    <div class="event-item" style="position:relative;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="event-type event">City Schedule / Event</span>
                                            <span class="event-date">
                                                <?php echo date('M d', strtotime($e['event_date'])); ?>
                                                <?php if (!empty($e['start_time'])): ?> &bull; <?php echo date('h:i A', strtotime($e['start_time'])); ?><?php endif; ?>
                                            </span>
                                        </div>
                                        <h4>
                                            <?php echo htmlspecialchars($e['title']); ?>
                                        </h4>
                                        <?php if (!empty($e['description'])): ?>
                                            <p>
                                                <?php echo htmlspecialchars($e['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($is_staff_or_admin): ?>
                                            <div style="display: flex; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--border-color, #E2E8F0);">
                                                <button type="button" class="btn-cal-action edit" onclick='openEditEventModal(<?php echo json_encode($e); ?>)' style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="calendar.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&delete_event=<?php echo $e['event_id']; ?>" onclick="return confirm('Are you sure you want to delete this event?')" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach ($announcements as $an): ?>
                                    <div class="event-item" style="border-left: 3px solid #EF4444;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="event-type" style="background:#FEE2E2; color:#DC2626;">Announcement</span>
                                            <span class="event-date">
                                                <?php echo date('M d', strtotime($an['event_date'])); ?>
                                            </span>
                                        </div>
                                        <h4>
                                            <?php echo htmlspecialchars($an['title']); ?>
                                        </h4>
                                        <?php if (!empty($an['content'])): ?>
                                            <p>
                                                <?php echo htmlspecialchars(substr(strip_tags($an['content']), 0, 90)) . (strlen($an['content']) > 90 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

        // Close sidebar on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== THEME TOGGLE ==========
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        const currentTheme = localStorage.getItem('theme') ||
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;

            document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
        }

        setTheme(currentTheme);

        themeToggle.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);

            themeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                themeToggle.style.transform = '';
            }, 200);
        });

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Escape to close sidebar on mobile
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== CONSOLE WELCOME ==========
        console.log('📅 One Palayan-Kalinga Hub - Calendar');
        console.log('💡 Keyboard shortcuts: ESC (Close Menu)');
        console.log('📱 Mobile navigation: Click the hamburger menu (☰) to open sidebar');
        console.log('🌓 Toggle dark/light mode using the sun/moon button!');
    </script>

    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>

    <!-- Add Event Modal (Staff/Admin) -->
    <div id="addEventModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #FFFFFF; width: 100%; max-width: 520px; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3); border: 1px solid var(--border-color, #E2E8F0);">
            <div style="background: var(--maroon, #7F0303); color: #FFFFFF; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-calendar-plus"></i> Add Temporary Event / Schedule
                </h3>
                <button type="button" onclick="closeAddEventModal()" style="background: transparent; border: none; color: #FFFFFF; font-size: 1.2rem; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="calendar.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" style="padding: 24px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create_event">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Event Title *</label>
                    <input type="text" name="title" required placeholder="e.g., Senior Pension Distribution / Town Meeting" style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.88rem; box-sizing: border-box;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Date *</label>
                        <input type="date" name="event_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Start Time</label>
                        <input type="time" name="start_time" style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">End Time</label>
                        <input type="time" name="end_time" style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Description / Instructions</label>
                    <textarea name="description" rows="3" placeholder="Provide event instructions or details for citizens and staff..." style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.88rem; box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeAddEventModal()" style="padding: 10px 18px; border-radius: 10px; border: 1px solid #CBD5E1; background: #F8FAFC; color: #475569; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; border-radius: 10px; border: none; background: var(--maroon, #7F0303); color: #FFFFFF; font-weight: 700; cursor: pointer;">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Event Modal (Staff/Admin) -->
    <div id="editEventModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #FFFFFF; width: 100%; max-width: 520px; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.3); border: 1px solid var(--border-color, #E2E8F0);">
            <div style="background: #1E293B; color: #FFFFFF; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;">
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit"></i> Edit Event / Schedule
                </h3>
                <button type="button" onclick="closeEditEventModal()" style="background: transparent; border: none; color: #FFFFFF; font-size: 1.2rem; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="calendar.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" style="padding: 24px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="event_id" id="edit_event_id">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Event Title *</label>
                    <input type="text" name="title" id="edit_title" required style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.88rem; box-sizing: border-box;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Date *</label>
                        <input type="date" name="event_date" id="edit_event_date" required style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Start Time</label>
                        <input type="time" name="start_time" id="edit_start_time" style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">End Time</label>
                        <input type="time" name="end_time" id="edit_end_time" style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Description / Instructions</label>
                    <textarea name="description" id="edit_description" rows="3" style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 10px; font-size: 0.88rem; box-sizing: border-box; resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeEditEventModal()" style="padding: 10px 18px; border-radius: 10px; border: 1px solid #CBD5E1; background: #F8FAFC; color: #475569; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 10px 22px; border-radius: 10px; border: none; background: #1E293B; color: #FFFFFF; font-weight: 700; cursor: pointer;">Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddEventModal() {
            const m = document.getElementById('addEventModal');
            if (m) m.style.display = 'flex';
        }
        function closeAddEventModal() {
            const m = document.getElementById('addEventModal');
            if (m) m.style.display = 'none';
        }
        function openEditEventModal(data) {
            document.getElementById('edit_event_id').value = data.event_id || '';
            document.getElementById('edit_title').value = data.title || '';
            document.getElementById('edit_event_date').value = data.event_date || '';
            document.getElementById('edit_start_time').value = data.start_time || '';
            document.getElementById('edit_end_time').value = data.end_time || '';
            document.getElementById('edit_description').value = data.description || '';
            const m = document.getElementById('editEventModal');
            if (m) m.style.display = 'flex';
        }
        function closeEditEventModal() {
            const m = document.getElementById('editEventModal');
            if (m) m.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('add=1') || window.location.hash === '#addEvent') {
                openAddEventModal();
            }
        });
    </script>

</body>

</html>
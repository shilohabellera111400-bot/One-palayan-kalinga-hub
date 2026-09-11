<?php
// calendar.php
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

$events_query = "SELECT * FROM calendar_events WHERE event_date BETWEEN :start_date AND :end_date";
$events_stmt = $db->prepare($events_query);
$events_stmt->bindParam(':start_date', $start_date);
$events_stmt->bindParam(':end_date', $end_date);
$events_stmt->execute();
$events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

$event_map = [];
foreach ($events as $event) {
    $event_map[$event['event_date']][] = $event;
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
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        /* ========== CALENDAR GRID ========== */
        .calendar-grid {
            display: grid;
            grid-template-columns: 2fr 1.2fr;
            gap: 28px;
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
            padding: 16px 24px;
            background: var(--badge-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .calendar-nav h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .calendar-nav .nav-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 36px;
            height: 36px;
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
            padding: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            background: var(--badge-bg);
            border-bottom: 2px solid var(--border-color);
        }

        .calendar-grid-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(90px, auto);
        }

        .calendar-grid-body .cell {
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 6px 8px;
            background: var(--bg-card);
            min-height: 90px;
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
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .calendar-grid-body .cell.today .date-num {
            color: var(--maroon);
        }

        .calendar-grid-body .cell .events {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .calendar-grid-body .cell .event-dot {
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .calendar-grid-body .cell .event-dot:hover {
            transform: scale(1.02);
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
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-close {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .hamburger-btn {
                display: flex;
            }

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
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-calendar-alt"></i></div>
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
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </h4>
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
                        <h1><i class="fas fa-calendar-alt"></i> Calendar</h1>
                        <p>View your scheduled appointments and government events</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="my-appointments.php" class="top-icon" title="My Appointments">
                        <i class="fas fa-calendar-check"></i>
                    </a>
                </div>
            </div>

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
                                // Check for user's appointments
                                if (isset($appt_map[$current_date])) {
                                    foreach ($appt_map[$current_date] as $a) {
                                        echo '<span class="event-dot appt" title="Appointment: ' . htmlspecialchars($a['service_name']) . '">📋 ' . htmlspecialchars($a['service_name']) . '</span>';
                                    }
                                }
                                // Check for general events
                                if (isset($event_map[$current_date])) {
                                    foreach ($event_map[$current_date] as $e) {
                                        echo '<span class="event-dot event" title="Event: ' . htmlspecialchars($e['title']) . '">📢 ' . htmlspecialchars($e['title']) . '</span>';
                                    }
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

                        <a href="appointments.php" class="btn-primary" style="margin-top: 16px;">
                            <i class="fas fa-plus-circle"></i> Book New Appointment
                        </a>
                    </div>

                    <!-- Event List -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clock"></i>
                            <h3>Upcoming Events</h3>
                            <span
                                style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 20px;">
                                <?php echo count($appts) + count($events); ?> total
                            </span>
                        </div>

                        <?php if (empty($appts) && empty($events)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No events or appointments scheduled for this month.</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
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
                                    <div class="event-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="event-type event">CSWDO Event</span>
                                            <span class="event-date">
                                                <?php echo date('M d', strtotime($e['event_date'])); ?>
                                            </span>
                                        </div>
                                        <h4>
                                            <?php echo htmlspecialchars($e['title']); ?>
                                        </h4>
                                        <?php if (!empty($e['description'])): ?>
                                            <p>
                                                <?php echo htmlspecialchars(substr($e['description'], 0, 60)) . (strlen($e['description']) > 60 ? '...' : ''); ?>
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

        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

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
</body>

</html>
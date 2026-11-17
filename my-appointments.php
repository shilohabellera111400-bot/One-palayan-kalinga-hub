<?php
// my-appointments.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

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

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = sanitize($_POST['appointment_id']);
    
    $check_query = "SELECT * FROM appointments 
                    WHERE appointment_id = :id AND user_id = :user_id 
                    AND status IN ('pending', 'confirmed', 'for_review', 'rescheduled')";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $appointment_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $update_query = "UPDATE appointments SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = :user_id WHERE appointment_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $appointment_id);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            logAppointmentHistory($db, $appointment_id, 'cancelled', 'Appointment cancelled by resident.');
            notifyAppointmentUpdate($db, $appointment_id, 'cancelled', 'Your appointment has been cancelled successfully.');
            $success = "Appointment cancelled successfully.";
        } else {
            $error = "Failed to cancel appointment.";
        }
    } else {
        $error = "Appointment not found or cannot be cancelled.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$query = "SELECT a.*, s.service_name, s.category_id
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          WHERE a.user_id = :user_id";

$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter != 'all') {
    if ($status_filter == 'active_visit') {
        $query .= " AND a.status IN ('checked_in', 'in_progress')";
    } else {
        $query .= " AND a.status = :status";
        $params[':status'] = $status_filter;
    }
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND a.appointment_date BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}
if (!empty($search)) {
    $query .= " AND (a.appointment_number LIKE :search OR s.service_name LIKE :search OR a.purpose LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$all_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status IN ('pending', 'for_review') THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status IN ('checked_in', 'in_progress') THEN 1 ELSE 0 END) as active_visit,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status IN ('cancelled', 'rejected', 'no_show') THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcoming_query = "SELECT a.*, s.service_name 
                   FROM appointments a
                   JOIN services s ON a.service_id = s.service_id
                   WHERE a.user_id = :user_id 
                   AND a.appointment_date >= CURDATE()
                   AND a.status IN ('pending', 'confirmed')
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC
                   LIMIT 3";
$upcoming_stmt = $db->prepare($upcoming_query);
$upcoming_stmt->bindParam(':user_id', $_SESSION['user_id']);
$upcoming_stmt->execute();
$upcoming = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>My Appointments - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 14px 18px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid var(--midnight);
            border: 1px solid var(--border-color);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card.pending { border-left-color: var(--color-warning); }
        .stat-card.confirmed { border-left-color: var(--color-success); }
        .stat-card.completed { border-left-color: var(--color-info); }
        .stat-card.cancelled { border-left-color: var(--color-danger); }
        .stat-number {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ========== UPCOMING SECTION ========== */
        .upcoming-section {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .upcoming-section .upcoming-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .upcoming-section .upcoming-info h4 {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .upcoming-section .upcoming-info .badge {
            background: rgba(255,255,255,0.15);
            padding: 4px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .upcoming-section .upcoming-info .badge i {
            font-size: 0.7rem;
        }
        .upcoming-section .quick-actions {
            display: flex;
            gap: 10px;
        }
        .upcoming-section .quick-actions a {
            background: rgba(255,255,255,0.12);
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            color: white;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .upcoming-section .quick-actions a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ========== ACTION BAR ========== */
        .action-bar {
            background: var(--card-bg);
            padding: 18px 22px;
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .book-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .book-btn i {
            font-size: 0.85rem !important;
            display: inline-block !important;
            margin: 0 !important;
            opacity: 1 !important;
            color: inherit !important;
        }
        .book-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-section select,
        .filter-section input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-section select:focus,
        .filter-section input:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-section .sep {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .filter-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 18px;
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
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .appointments-table th {
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
        .appointments-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .appointments-table tr:hover {
            background: var(--badge-bg);
        }
        .appointments-table .service-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .appointments-table .purpose-text {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.confirmed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.completed {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
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
            padding: 50px 20px;
        }
        .empty-state > i {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.35;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 22px;
        }
        .empty-state .book-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 50px;
            background: var(--maroon);
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.22);
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .empty-state .book-btn i {
            font-size: 0.85rem !important;
            display: inline-block !important;
            margin-bottom: 0 !important;
            opacity: 1 !important;
            color: inherit !important;
        }
        .empty-state .book-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.32);
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
        .modal.show {
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
        .modal.show .modal-content {
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
        .modal-content .text-danger {
            color: var(--color-danger);
            font-size: 0.8rem;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .modal-actions .btn-cancel {
            padding: 10px 25px;
            font-size: 0.85rem;
        }
        .modal-actions .btn-view {
            padding: 10px 25px;
            font-size: 0.85rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .action-bar { flex-direction: column; align-items: stretch; gap: 12px; }
            .filter-section form { flex-direction: column !important; width: 100%; align-items: stretch !important; gap: 10px !important; }
            .filter-section select, .filter-section input { width: 100% !important; min-width: 0 !important; }
            .filter-section .sep { display: none; }
            .filter-section .filter-btn, .filter-section .clear-btn { width: 100%; justify-content: center; }
            .upcoming-section { flex-direction: column; text-align: left; align-items: flex-start; }
            .upcoming-section .upcoming-info { justify-content: flex-start; }
            .upcoming-section .quick-actions { width: 100%; }
            .upcoming-section .quick-actions a { display: flex; width: 100%; justify-content: center; }
            .table-container { padding: 12px 14px; overflow-x: auto; }
            .appointments-table th, .appointments-table td { padding: 10px 12px; font-size: 0.78rem; }
            .action-buttons { flex-wrap: wrap; gap: 6px; }
            .modal-content { padding: 20px 16px; width: calc(100vw - 28px); max-width: 480px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 8px; }
            .stat-number { font-size: 1.15rem; }
            .stat-label { font-size: 0.65rem; }
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
            $banner_title = 'My Appointments';
            $banner_subtitle = 'View existing visit appointments, schedules, and check-in status';
            $banner_icon = 'fa-calendar-check';
            $banner_actions = [
                ['label' => 'Book New Appointment', 'url' => 'appointments.php', 'icon' => 'fa-plus-circle', 'type' => 'primary'],
                ['label' => 'Calendar View', 'url' => 'calendar.php', 'icon' => 'fa-calendar-alt', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

        <!-- Upcoming Appointments Section -->
        <?php if(!empty($upcoming)): ?>
        <div class="upcoming-section">
            <div class="upcoming-info">
                <h4><i class="fas fa-calendar-check"></i> Upcoming Appointments</h4>
                <?php foreach($upcoming as $up): ?>
                <span class="badge">
                    <i class="fas fa-clock"></i>
                    <?php echo htmlspecialchars($up['service_name']); ?> - 
                    <?php echo date('M d, h:i A', strtotime($up['appointment_date'] . ' ' . $up['appointment_time'])); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="quick-actions">
                <a href="appointments.php"><i class="fas fa-plus"></i> Book New</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="action-bar">
            <a href="appointments.php" class="book-btn">
                <i class="fas fa-plus-circle"></i> Book New Appointment
            </a>
            <div class="filter-section">
                <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending (Review)</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="active_visit" <?php echo $status_filter == 'active_visit' ? 'selected' : ''; ?>>Checked-In / In Progress</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="rescheduled" <?php echo $status_filter == 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved</option>
                    </select>
                    <input type="text" name="search" placeholder="Search reference or service..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 7px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.8rem; background: var(--bg-card); color: var(--text-primary); min-width: 190px;">
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="Date From">
                    <span class="sep">to</span>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="Date To">
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    <?php if($status_filter != 'all' || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                    <a href="my-appointments.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
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

        <!-- View Switcher -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-layer-group" style="color: var(--maroon);"></i>
                <span>Appointments Overview (<?php echo count($all_appointments); ?>)</span>
            </div>
            <div style="display: inline-flex; background: var(--bg-card); padding: 4px; border-radius: 50px; border: 1px solid var(--border-color); gap: 4px; box-shadow: var(--shadow-sm);">
                <button type="button" id="btnTableView" onclick="switchAppointmentView('table')" style="padding: 6px 16px; border-radius: 50px; font-size: 0.78rem; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: var(--maroon); color: #FFFFFF; transition: all 0.2s;">
                    <i class="fas fa-table-list"></i> Table List
                </button>
                <button type="button" id="btnKanbanView" onclick="switchAppointmentView('kanban')" style="padding: 6px 16px; border-radius: 50px; font-size: 0.78rem; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: transparent; color: var(--text-muted); transition: all 0.2s;">
                    <i class="fas fa-columns"></i> Kanban Pipeline
                </button>
            </div>
        </div>

        <?php if(!empty($all_appointments)): ?>
        <!-- 1. Appointments Table View -->
        <div class="table-container" id="appointmentTableViewWrap">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Reference #</th>
                        <th>Service & Department</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_appointments as $app): ?>
                    <?php 
                        $refNum = !empty($app['reference_number']) ? $app['reference_number'] : ('PAL-SOC-' . date('Y', strtotime($app['created_at'])) . '-' . str_pad($app['appointment_id'], 6, '0', STR_PAD_LEFT));
                    ?>
                    <tr>
                        <td>
                            <div style="font-family: 'Courier Prime', monospace; font-weight: 700; color: var(--maroon); font-size: 0.88rem; letter-spacing: 0.04em;">
                                <?php echo htmlspecialchars($refNum); ?>
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;">
                                <i class="fas fa-calendar-day"></i> Booked <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="service-name"><?php echo htmlspecialchars($app['service_name']); ?></div>
                            <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">
                                <i class="fas fa-building" style="color: var(--maroon); font-size: 0.7rem;"></i> <?php echo htmlspecialchars($app['office_department'] ?: 'Social Welfare & Development'); ?>
                            </div>
                            <?php if(!empty($app['purpose'])): ?>
                            <div class="purpose-text" style="font-style: italic; margin-top: 3px;">
                                "<?php echo htmlspecialchars(substr($app['purpose'], 0, 45)) . (strlen($app['purpose']) > 45 ? '...' : ''); ?>"
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><strong><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></strong></div>
                            <div style="font-size: 0.74rem; color: var(--text-muted);"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($app['appointment_time'])); ?></div>
                        </td>
                        <td>
                            <?php echo getAppointmentBadge($app['status']); ?>
                        </td>
                        <td style="text-align: center;">
                            <div class="action-buttons" style="justify-content: center;">
                                <a href="appointment-slip.php?ref=<?php echo urlencode($refNum); ?>" target="_blank" class="btn-view" style="background: #FFF1F2; color: var(--maroon); border: 1px solid #FECDD3;" title="View & Print Official Appointment Slip">
                                    <i class="fas fa-print"></i> Slip
                                </a>
                                <button type="button" onclick="showQrModal('<?php echo htmlspecialchars($refNum); ?>', '<?php echo htmlspecialchars($app['qr_token'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($app['service_name'])); ?>')" class="btn-view" style="background: #F3F4F6; color: #1F2937; border: 1px solid #E5E7EB;" title="View Check-in QR Code">
                                    <i class="fas fa-qrcode"></i> QR
                                </button>
                                <a href="view-appointment.php?id=<?php echo $app['appointment_id']; ?>" class="btn-view" title="View Full Details">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                                <?php if(in_array($app['status'], ['pending','for_review','confirmed','rescheduled'])): ?>
                                <button onclick="showRescheduleModal(<?php echo $app['appointment_id']; ?>, '<?php echo htmlspecialchars(addslashes($app['service_name'])); ?>', '<?php echo $app['appointment_date']; ?>', '<?php echo $app['appointment_time']; ?>')" class="btn-reschedule">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </button>
                                <button onclick="showCancelModal(<?php echo $app['appointment_id']; ?>)" class="btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Appointments Kanban Pipeline View -->
        <?php
        $res_kanban_cols = [
            'pending' => ['title' => 'Pending Review', 'color' => '#D97706', 'icon' => 'fa-clock', 'items' => []],
            'confirmed' => ['title' => 'Confirmed & Slotted', 'color' => '#059669', 'icon' => 'fa-calendar-check', 'items' => []],
            'active' => ['title' => 'Active / In-Office', 'color' => '#2563EB', 'icon' => 'fa-user-clock', 'items' => []],
            'completed' => ['title' => 'Completed', 'color' => '#7C3AED', 'icon' => 'fa-circle-check', 'items' => []],
            'cancelled' => ['title' => 'Cancelled / Disapproved', 'color' => '#DC2626', 'icon' => 'fa-circle-xmark', 'items' => []]
        ];

        foreach ($all_appointments as $item) {
            $st = strtolower($item['status'] ?? 'pending');
            if (in_array($st, ['pending', 'for_review', 'needs_information'])) {
                $res_kanban_cols['pending']['items'][] = $item;
            } elseif (in_array($st, ['confirmed', 'rescheduled'])) {
                $res_kanban_cols['confirmed']['items'][] = $item;
            } elseif (in_array($st, ['checked_in', 'in_progress'])) {
                $res_kanban_cols['active']['items'][] = $item;
            } elseif ($st === 'completed') {
                $res_kanban_cols['completed']['items'][] = $item;
            } else {
                $res_kanban_cols['cancelled']['items'][] = $item;
            }
        }
        ?>
        <div id="appointmentKanbanViewWrap" style="display: none;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: flex-start;">
                <?php foreach ($res_kanban_cols as $kKey => $kCol): ?>
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;">
                    <div style="padding: 12px 16px; background: var(--bg-subtle, rgba(0,0,0,0.02)); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 0.82rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 7px;">
                            <i class="fas <?php echo $kCol['icon']; ?>" style="color: <?php echo $kCol['color']; ?>;"></i>
                            <?php echo $kCol['title']; ?>
                        </span>
                        <span style="font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 50px; background: rgba(0,0,0,0.06); color: <?php echo $kCol['color']; ?>;">
                            <?php echo count($kCol['items']); ?>
                        </span>
                    </div>

                    <div style="padding: 12px; display: flex; flex-direction: column; gap: 12px; min-height: 100px;">
                        <?php if (empty($kCol['items'])): ?>
                            <div style="padding: 24px 10px; text-align: center; color: var(--text-muted); font-size: 0.78rem; opacity: 0.6;">
                                <i class="fas fa-inbox" style="font-size: 1.4rem; display: block; margin-bottom: 4px;"></i>
                                No appointments
                            </div>
                        <?php else: ?>
                            <?php foreach ($kCol['items'] as $kApp): ?>
                            <?php 
                                $kRef = !empty($kApp['reference_number']) ? $kApp['reference_number'] : ('PAL-SOC-' . date('Y', strtotime($kApp['created_at'])) . '-' . str_pad($kApp['appointment_id'], 6, '0', STR_PAD_LEFT));
                            ?>
                            <div style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; box-shadow: var(--shadow-sm);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <span style="font-family: 'Courier Prime', monospace; font-size: 0.74rem; font-weight: 800; color: var(--maroon);"><?php echo htmlspecialchars($kRef); ?></span>
                                    <?php if (!empty($kApp['window_assignment'])): ?>
                                        <span style="font-size: 0.65rem; font-weight: 700; background: #E0E7FF; color: #3730A3; padding: 1px 6px; border-radius: 4px;"><?php echo htmlspecialchars($kApp['window_assignment']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($kApp['service_name']); ?>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 8px;">
                                    <i class="fas fa-calendar-day"></i> <?php echo date('M d, Y', strtotime($kApp['appointment_date'])); ?> • <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($kApp['appointment_time'])); ?>
                                </div>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <a href="appointment-slip.php?ref=<?php echo urlencode($kRef); ?>" target="_blank" style="padding: 3px 8px; font-size: 0.68rem; font-weight: 700; border-radius: 6px; background: #FFF1F2; color: var(--maroon); text-decoration: none; border: 1px solid #FECDD3;">
                                        <i class="fas fa-print"></i> Slip
                                    </a>
                                    <a href="view-appointment.php?id=<?php echo $kApp['appointment_id']; ?>" style="padding: 3px 8px; font-size: 0.68rem; font-weight: 700; border-radius: 6px; background: var(--bg-card); color: var(--text-primary); text-decoration: none; border: 1px solid var(--border-color);">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Appointments Found</h3>
            <p>You don't have any appointments scheduled yet.</p>
            <a href="appointments.php" class="book-btn"><i class="fas fa-plus"></i> Book Your First Appointment</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h3>
        <p>Are you sure you want to cancel this appointment?</p>
        <p class="text-danger"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="appointment_id" id="cancel_appointment_id">
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn-view">No, Keep It</button>
                <button type="submit" name="cancel_appointment" class="btn-cancel">Yes, Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content" style="max-width:480px;text-align:left;">
        <h3 style="justify-content:flex-start;"><i class="fas fa-calendar-edit" style="color:var(--maroon);"></i> Reschedule Appointment</h3>
        <p style="margin-bottom:4px;color:var(--text-muted);font-size:.85rem;">Service: <strong id="rescheduleSvcName"></strong></p>
        <p style="margin-bottom:16px;color:var(--text-muted);font-size:.85rem;">Current: <strong id="rescheduleCurrentDate"></strong></p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:var(--text-secondary);">New Date *</label>
                <input type="date" id="rescheduleNewDate" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                       style="width:100%;padding:9px 12px;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-primary);color:var(--text-primary);font-size:.9rem;">
            </div>
            <div>
                <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:var(--text-secondary);">New Time *</label>
                <select id="rescheduleNewTime" style="width:100%;padding:9px 12px;border:1.5px solid var(--border-color);border-radius:10px;background:var(--bg-primary);color:var(--text-primary);font-size:.9rem;">
                    <option value="">-- Select time --</option>
                    <option value="09:00:00">9:00 AM</option>
                    <option value="10:00:00">10:00 AM</option>
                    <option value="11:00:00">11:00 AM</option>
                    <option value="13:00:00">1:00 PM</option>
                    <option value="14:00:00">2:00 PM</option>
                    <option value="15:00:00">3:00 PM</option>
                    <option value="16:00:00">4:00 PM</option>
                </select>
            </div>
        </div>

        <div id="rescheduleSlots" style="margin-bottom:16px;min-height:40px;"></div>
        <div id="rescheduleFeedback" style="font-size:.85rem;margin-bottom:12px;display:none;padding:10px 14px;border-radius:8px;"></div>

        <div class="modal-actions" style="justify-content:flex-start;gap:10px;">
            <button type="button" onclick="closeRescheduleModal()" class="btn-view">Cancel</button>
            <button type="button" id="rescheduleSubmitBtn" onclick="submitReschedule()" class="btn-primary" style="padding:8px 20px;border:none;border-radius:50px;cursor:pointer;font-size:.85rem;font-weight:600;">
                <i class="fas fa-check"></i> Confirm Reschedule
            </button>
        </div>
    </div>
</div>

<!-- QR Modal -->
<div id="qrModal" class="modal">
    <div class="modal-content" style="max-width: 420px; text-align: center; border-radius: 16px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-qrcode" style="color: var(--maroon);"></i> Appointment QR Pass
            </h3>
            <button type="button" class="close-btn" onclick="closeQrModal()">&times;</button>
        </div>
        <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 14px; line-height: 1.4;">
            I-presenta ang QR Code na ito sa LGU Social Service staff o kiosk scanner pagdating sa City Hall.
        </p>
        <div style="background: #FFFFFF; padding: 18px; border-radius: 14px; border: 2px dashed #E2E8F0; display: inline-flex; justify-content: center; align-items: center; margin-bottom: 14px; box-shadow: var(--shadow-sm);">
            <div id="qrModalCanvas"></div>
        </div>
        <div style="margin-bottom: 16px;">
            <span style="font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 2px;">Reference Number</span>
            <div id="qrModalRef" style="font-family: 'Courier Prime', monospace; font-size: 1.15rem; font-weight: 800; color: var(--maroon); letter-spacing: 0.05em;"></div>
            <div id="qrModalService" style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; margin-top: 4px;"></div>
        </div>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <a id="qrModalSlipBtn" href="#" target="_blank" class="btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; text-decoration: none; font-size: 0.82rem; margin: 0; border-radius: 50px;">
                <i class="fas fa-print"></i> Open Official Slip
            </a>
            <button type="button" onclick="closeQrModal()" class="btn-view" style="padding: 8px 18px; font-size: 0.82rem;">Close</button>
        </div>
    </div>
</div>

<style>
.btn-reschedule {
    background: rgba(33, 150, 243, 0.12);
    color: #1565c0;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.7rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-weight: 600;
}
.btn-reschedule:hover {
    background: #1565c0;
    color: white;
    transform: translateY(-2px);
}
.btn-primary {
    background: var(--maroon);
    color: #fff;
}
.btn-primary:hover { background: var(--maroon-light); }
</style>

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
    document.getElementById('cancel_appointment_id').value = id;
    document.getElementById('cancelModal').classList.add('show');
}

function closeModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

// ========== RESCHEDULE MODAL FUNCTIONS ==========
let _rescheduleId = null;

function showRescheduleModal(id, svcName, currentDate, currentTime) {
    _rescheduleId = id;
    document.getElementById('rescheduleSvcName').textContent = svcName;
    const cDate = new Date(currentDate + 'T' + currentTime);
    document.getElementById('rescheduleCurrentDate').textContent =
        cDate.toLocaleDateString('en-PH', {month:'long', day:'numeric', year:'numeric'}) + ' at ' +
        cDate.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    document.getElementById('rescheduleNewDate').value = '';
    document.getElementById('rescheduleNewTime').value = '';
    document.getElementById('rescheduleSlots').innerHTML = '';
    hideFeedback();
    document.getElementById('rescheduleModal').classList.add('show');
}

function closeRescheduleModal() {
    document.getElementById('rescheduleModal').classList.remove('show');
    _rescheduleId = null;
}

function hideFeedback() {
    const fb = document.getElementById('rescheduleFeedback');
    fb.style.display = 'none';
    fb.textContent = '';
}

function showFeedback(msg, isError) {
    const fb = document.getElementById('rescheduleFeedback');
    fb.textContent = msg;
    fb.style.display = 'block';
    fb.style.background = isError ? '#ffebee' : '#e8f5e8';
    fb.style.color = isError ? '#c62828' : '#2e7d32';
    fb.style.border = isError ? '1px solid #ffcdd2' : '1px solid #c8e6c9';
}

// Load available slots when date changes inside reschedule modal
document.getElementById('rescheduleNewDate').addEventListener('change', function() {
    const date = this.value;
    if (!date) return;
    const slotsDiv = document.getElementById('rescheduleSlots');
    slotsDiv.innerHTML = '<em style="font-size:.82rem;color:var(--text-muted);">Loading slots...</em>';
    fetch(`get-available-slots.php?date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.is_blackout) {
                slotsDiv.innerHTML = `<div style="background:#fff3e0;color:#e65100;padding:8px 12px;border-radius:8px;font-size:.82rem;"><i class="fas fa-ban"></i> Holiday: ${data.reason}</div>`;
                return;
            }
            const avail = data.slots.filter(s => s.available);
            if (!avail.length) {
                slotsDiv.innerHTML = '<em style="font-size:.82rem;color:var(--color-danger);">No available slots on this date.</em>';
            } else {
                slotsDiv.innerHTML = '<em style="font-size:.82rem;color:var(--text-muted);">Available: ' +
                    avail.map(s => `<strong>${s.time}</strong> (${s.remaining} left)`).join(', ') + '</em>';
            }
        })
        .catch(() => { slotsDiv.innerHTML = ''; });
});

function submitReschedule() {
    const newDate = document.getElementById('rescheduleNewDate').value;
    const newTime = document.getElementById('rescheduleNewTime').value;

    if (!newDate || !newTime) {
        showFeedback('Pumili ng bagong petsa at oras bago i-confirm.', true);
        return;
    }
    if (!_rescheduleId) return;

    const btn = document.getElementById('rescheduleSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    hideFeedback();

    fetch('reschedule-appointment-ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: _rescheduleId, new_date: newDate, new_time: newTime})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirm Reschedule';
        if (data.success) {
            showFeedback(data.message, false);
            setTimeout(() => { window.location.reload(); }, 1800);
        } else {
            showFeedback(data.message || 'Nabigo ang reschedule. Subukang muli.', true);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirm Reschedule';
        showFeedback('Network error. Subukang muli.', true);
    });
}

// ========== QR MODAL FUNCTIONS ==========
let _qrInstance = null;
function showQrModal(refNumber, qrToken, serviceName) {
    document.getElementById('qrModalRef').textContent = refNumber;
    document.getElementById('qrModalService').textContent = serviceName;
    document.getElementById('qrModalSlipBtn').href = 'appointment-slip.php?ref=' + encodeURIComponent(refNumber);
    
    const qrContainer = document.getElementById('qrModalCanvas');
    qrContainer.innerHTML = '';
    
    const qrPayload = refNumber + '|' + (qrToken || '');
    try {
        _qrInstance = new QRCode(qrContainer, {
            text: qrPayload,
            width: 180,
            height: 180,
            colorDark: '#7B1113',
            colorLight: '#FFFFFF',
            correctLevel: QRCode.CorrectLevel.H
        });
    } catch(e) {
        qrContainer.innerHTML = '<div style="color:red; font-size:0.8rem;">Could not load QR code library</div>';
    }
    
    document.getElementById('qrModal').classList.add('show');
}

function closeQrModal() {
    document.getElementById('qrModal').classList.remove('show');
}

// Close modals on outside click
window.onclick = function(event) {
    const cancelModal     = document.getElementById('cancelModal');
    const rescheduleModal = document.getElementById('rescheduleModal');
    const qrModal         = document.getElementById('qrModal');
    if (event.target == cancelModal)     closeModal();
    if (event.target == rescheduleModal) closeRescheduleModal();
    if (event.target == qrModal)         closeQrModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
    }
});

// Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 500);
        });
    }, 5000);

    function switchAppointmentView(mode) {
        const tableWrap = document.getElementById('appointmentTableViewWrap');
        const kanbanWrap = document.getElementById('appointmentKanbanViewWrap');
        const btnTable = document.getElementById('btnTableView');
        const btnKanban = document.getElementById('btnKanbanView');

        if (!tableWrap || !kanbanWrap) return;

        if (mode === 'kanban') {
            tableWrap.style.display = 'none';
            kanbanWrap.style.display = 'block';
            btnTable.style.background = 'transparent';
            btnTable.style.color = 'var(--text-muted)';
            btnKanban.style.background = 'var(--maroon)';
            btnKanban.style.color = '#FFFFFF';
            try { localStorage.setItem('resident_appt_view', 'kanban'); } catch(e){}
        } else {
            tableWrap.style.display = 'block';
            kanbanWrap.style.display = 'none';
            btnTable.style.background = 'var(--maroon)';
            btnTable.style.color = '#FFFFFF';
            btnKanban.style.background = 'transparent';
            btnKanban.style.color = 'var(--text-muted)';
            try { localStorage.setItem('resident_appt_view', 'table'); } catch(e){}
        }
    }

    // Restore user preference
    try {
        if (localStorage.getItem('resident_appt_view') === 'kanban') {
            switchAppointmentView('kanban');
        }
    } catch(e){}
    </script>

    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

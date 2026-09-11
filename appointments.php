<?php
// appointments.php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is verified (only for residents)
if (isLoggedIn() && !isStaff() && !isAdmin()) {
    $check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
    $verify_stmt = $db->prepare($check_verified);
    $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $verify_stmt->execute();
    $user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_verify || $user_verify['verification_status'] != 'verified') {
        redirect('verify-resident.php');
    }
}

// Get user's appointments if logged in as RESIDENT only
$user_appointments = [];

if (isLoggedIn() && !isStaff() && !isAdmin()) {
    $query = "SELECT a.*, s.service_name, s.category_id 
              FROM appointments a
              JOIN services s ON a.service_id = s.service_id
              WHERE a.user_id = :user_id 
              ORDER BY a.appointment_date DESC, a.appointment_time DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get available services for booking
$services_query = "SELECT * FROM services WHERE is_active = 1 ORDER BY service_name";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+30 days'));

// Get initials for sidebar avatar
$initials = '';
if (isLoggedIn()) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    foreach($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

// Profile picture path
$profile_picture = '';
$avatar_url = '';
if (isLoggedIn()) {
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
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        .appointments-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
            align-items: start;
        }

        /* ========== STEPPER WIZARD HEADERS ========== */
        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            background: var(--bg-primary);
            padding: 10px;
            border-radius: 40px;
            border: 1px solid var(--border-color);
        }
        .wizard-step {
            flex: 1;
            text-align: center;
            padding: 8px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .wizard-step.active {
            background: var(--maroon);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        .wizard-step.completed {
            color: var(--color-success);
        }
        .wizard-step-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(0,0,0,0.06);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        .wizard-step.active .wizard-step-num {
            background: rgba(255,255,255,0.2);
        }

        /* ========== BOOKING WIZARD BOX ========== */
        .booking-section {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 35px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        .booking-section h2 {
            color: var(--text-primary);
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .booking-section h2 i { color: var(--maroon); }

        .step-container {
            display: none;
            animation: fadeIn 0.4s ease forwards;
        }
        .step-container.active {
            display: block;
        }

        /* STEP 1: Visual Service Card Grid */
        .services-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            padding: 5px;
        }
        .service-select-card {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: var(--radius-element);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .service-select-card:hover {
            transform: translateY(-3px);
            border-color: var(--maroon-light);
            box-shadow: var(--shadow-sm);
        }
        .service-select-card.selected {
            border-color: var(--maroon);
            background: rgba(127, 3, 3, 0.05);
        }
        [data-theme="dark"] .service-select-card.selected {
            border-color: var(--gold-light);
            background: rgba(232, 213, 168, 0.05);
        }
        .service-select-card i {
            font-size: 1.8rem;
            color: var(--maroon);
        }
        [data-theme="dark"] .service-select-card i {
            color: var(--gold-light);
        }
        .service-select-card h4 {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .service-select-card .duration {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Dynamic Requirements Alert */
        .dynamic-requirements-box {
            background: rgba(216, 186, 152, 0.12);
            border-left: 4px solid var(--tan);
            padding: 20px;
            border-radius: var(--radius-element);
            margin-bottom: 25px;
            display: none;
        }
        .dynamic-requirements-box h4 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dynamic-requirements-box h4 i { color: var(--maroon); }
        .dynamic-requirements-box ul {
            list-style: none;
            padding: 0;
        }
        .dynamic-requirements-box li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .dynamic-requirements-box li:before {
            content: "•";
            color: var(--maroon);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        /* STEP 2: Date & Segregated Time Picker */
        .calendar-picker-row {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .form-group input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-group input[type="date"]:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127,3,3,0.08);
        }

        .time-slots-container-block {
            display: none;
            margin-top: 20px;
        }
        .slots-section-header {
            font-size: 0.85rem;
            font-weight: 750;
            color: var(--text-primary);
            margin: 15px 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .slots-section-header.am-header { color: var(--color-warning); }
        .slots-section-header.pm-header { color: var(--color-info); }

        .time-slots-segregated {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .time-slot-btn {
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .time-slot-btn:hover:not(.disabled) {
            transform: translateY(-2px);
            border-color: var(--maroon-light);
        }
        .time-slot-btn.selected {
            background: var(--maroon);
            border-color: var(--maroon);
            color: white !important;
        }
        .time-slot-btn.selected .slot-badge {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        .time-slot-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: rgba(211,47,47,0.15);
            background: rgba(211,47,47,0.02);
        }
        .time-slot-btn .slot-time {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .time-slot-btn.selected .slot-time { color: white; }
        .time-slot-btn.disabled .slot-time { color: var(--color-danger); }
        
        .slot-badge {
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 700;
        }
        .slot-badge.badge-available { background: rgba(46,125,50,0.1); color: var(--color-success); }
        .slot-badge.badge-warning { background: rgba(237,108,2,0.1); color: var(--color-warning); }
        .slot-badge.badge-full { background: rgba(211,47,47,0.1); color: var(--color-danger); }

        /* STEP 3: Summary & Submit Confirmation */
        .summary-box {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
        }
        .summary-box h4 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.88rem;
        }
        .summary-row:last-child { margin-bottom: 0; }
        .summary-label { color: var(--text-muted); font-weight: 500; }
        .summary-value { color: var(--text-primary); font-weight: 700; }

        .confirmation-checklist {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .confirmation-checklist input {
            margin-top: 4px;
            cursor: pointer;
        }
        .confirmation-checklist label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
            line-height: 1.4;
            cursor: pointer;
        }

        /* Form Actions Buttons */
        .wizard-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 25px;
        }
        .btn-wizard-nav {
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-wizard-nav.btn-prev {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        .btn-wizard-nav.btn-prev:hover { background: var(--border-color); }
        .btn-wizard-nav.btn-next {
            background: var(--maroon);
            color: white;
            margin-left: auto;
        }
        .btn-wizard-nav.btn-next:hover:not(:disabled) {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .btn-wizard-nav.btn-next:disabled {
            background: var(--border-color);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* ========== MY APPOINTMENTS BOARD ========== */
        .my-appointments-section {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 35px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        .my-appointments-section h2 {
            color: var(--text-primary);
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .my-appointments-section h2 i { color: var(--maroon); }

        .appointment-card {
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            border-left: 5px solid var(--midnight);
            transition: all 0.3s ease;
            position: relative;
        }
        .appointment-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        .appointment-card.status-pending { border-left-color: var(--color-warning); }
        .appointment-card.status-confirmed { border-left-color: var(--color-success); }
        .appointment-card.status-completed { border-left-color: var(--color-info); }
        .appointment-card.status-cancelled { border-left-color: var(--color-danger); opacity: 0.8; }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .appointment-service {
            font-weight: 800;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .appointment-status {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .appointment-status.pending { background: rgba(237,108,2,0.1); color: var(--color-warning); }
        .appointment-status.confirmed { background: rgba(46,125,50,0.1); color: var(--color-success); }
        .appointment-status.completed { background: rgba(2,136,209,0.1); color: var(--color-info); }
        .appointment-status.cancelled { background: rgba(211,47,47,0.1); color: var(--color-danger); }

        .appointment-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            border-top: 1px dashed var(--border-color);
            padding-top: 12px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .detail-item i { color: var(--maroon); }
        [data-theme="dark"] .detail-item i { color: var(--gold-light); }

        .appointment-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn-small {
            padding: 6px 16px;
            border: none;
            border-radius: 30px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        .btn-view {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .btn-view:hover { border-color: var(--maroon); color: var(--maroon); }
        .btn-cancel {
            background: rgba(211,47,47,0.1);
            color: var(--color-danger);
        }
        .btn-cancel:hover { background: var(--color-danger); color: white; }

        /* ========== INFO CARDS AT THE BOTTOM ========== */
        .info-section {
            margin-top: 35px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .info-card {
            background: var(--bg-card);
            padding: 24px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .info-card i {
            font-size: 1.8rem;
            color: var(--maroon);
            margin-bottom: 12px;
        }
        [data-theme="dark"] .info-card i { color: var(--gold-light); }
        .info-card h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .info-card p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.5;
            font-weight: 500;
        }

        /* ========== CONFIRMATION MODALS ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 14, 23, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            padding: 30px;
            border-radius: var(--radius-card);
            max-width: 460px;
            width: 90%;
            transform: translateY(20px);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }
        .modal.show .modal-content {
            transform: translateY(0);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-header h2 i { color: var(--color-danger); }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: transform 0.3s;
        }
        .close-btn:hover { transform: rotate(90deg); color: var(--maroon); }

        .modal-body p {
            color: var(--text-secondary);
            font-size: 0.92rem;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .no-appointments {
            text-align: center;
            padding: 45px 20px;
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            color: var(--text-muted);
            border: 2px dashed var(--border-color);
        }
        .no-appointments i {
            font-size: 2.8rem;
            opacity: 0.25;
            margin-bottom: 15px;
            display: block;
            color: var(--text-primary);
        }
        .btn-login-prompt {
            display: inline-block;
            padding: 10px 28px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.88rem;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .btn-login-prompt:hover { background: var(--maroon-light); transform: translateY(-2px); }

        .view-all-link {
            text-align: center;
            margin-top: 20px;
        }
        .view-all-link a {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        [data-theme="dark"] .view-all-link a { color: var(--gold-light); }
        .view-all-link a:hover { text-decoration: underline; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== RESPONSIVE OVERRIDES ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); display: none; }
            .main-content { margin-left: 0; padding: 25px; }
            .appointments-grid { grid-template-columns: 1fr; }
            .info-section { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; text-align: center; }
            .top-bar-actions { justify-content: center; }
            .services-cards-grid { grid-template-columns: 1fr; }
            .time-slots-segregated { grid-template-columns: repeat(2, 1fr); }
            .wizard-steps { flex-direction: column; gap: 5px; border-radius: var(--radius-element); }
            .wizard-step { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Overlay -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <?php endif; ?>

        <!-- Sidebar Menu -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga</h2>
                    <span>Resident Portal</span>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if(!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <span>Resident</span>
                </div>
            </div>
            
            <?php include __DIR__ . '/includes/usersidenav.php'; ?>
            
            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Panel -->
        <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
            <!-- Top Bar Actions -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <?php endif; ?>
                    <div class="page-title">
                        <h1>Appointments Scheduling</h1>
                        <p>Schedule your resident support visit at CSWDO Palayan City.</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <div class="appointments-grid">
                <!-- 3-Step Wizard Block -->
                <div class="booking-section">
                    <div class="wizard-steps">
                        <div class="wizard-step active" id="stepHeader1">
                            <span class="wizard-step-num">1</span> Service
                        </div>
                        <div class="wizard-step" id="stepHeader2">
                            <span class="wizard-step-num">2</span> Date & Time
                        </div>
                        <div class="wizard-step" id="stepHeader3">
                            <span class="wizard-step-num">3</span> Confirm
                        </div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-right-to-bracket"></i>
                        <p>Access Restricted: Resident Authentication Required</p>
                        <a href="login.php" class="btn-login-prompt">Sign In Portal</a>
                    </div>
                    <?php elseif (isStaff() || isAdmin()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-user-tie"></i>
                        <p>Staff members cannot schedule appointments using the resident portal.</p>
                        <a href="staff-dashboard.php" class="btn-login-prompt">Staff Area Dashboard</a>
                    </div>
                    <?php else: ?>
                    <form id="bookingForm" onsubmit="return bookAppointment(event)">
                        <!-- Input triggers -->
                        <input type="hidden" id="service_id" name="service_id" required>
                        <input type="hidden" id="appointment_time" name="appointment_time" required>

                        <!-- STEP 1: SERVICE SELECT GRID -->
                        <div class="step-container active" id="step1">
                            <h2 style="margin-bottom: 15px;"><i class="fas fa-concierge-bell"></i> Choose service category</h2>
                            <div class="services-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
                                <!-- Card 1: Burial Assistance -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 7, 'AICS - Funeral/Burial Assistance', '1-3 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #7F0303, #4d0000); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-cross"></i>
                                        </div>
                                        <span class="card-badge urgent" style="position: absolute; top: 12px; right: 12px; background: #e53935; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;">Urgent</span>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">⚰️ Burial Assistance</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Financial support for funeral services and burial expenses of a deceased relative.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>

                                <!-- Card 2: Food & Transportation Assistance -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 10, 'AICS - Food Assistance', '1-2 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #2E7D32, #1B5E20); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-bus"></i>
                                        </div>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">🍲 Food & Transportation Assistance</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Help with groceries/food packs or fare/travel expenses during crisis situations.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>

                                <!-- Card 3: Medical & Financial Assistance -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 6, 'AICS - Medical Assistance', '3-5 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #0288D1, #01579B); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-hospital-user"></i>
                                        </div>
                                        <span class="card-badge popular" style="position: absolute; top: 12px; right: 12px; background: #f57c00; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;">Popular</span>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">🏥 Medical & Financial Assistance</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Financial assistance for hospital bills, doctor's prescriptions, or cash relief for emergency situations.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>

                                <!-- Card 4: Housing Assistance -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 4, 'Housing Application - Akap Village', '5-7 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #e65100, #bf360c); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-house-user"></i>
                                        </div>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">🏠 Housing Assistance</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Subsidized socialized housing programs including Akap Village and San Lorenzo NHA resettlement.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>

                                <!-- Card 5: Senior Citizen Services -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 2, 'Senior Citizen Pension', '3-5 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #5d4037, #3e2723); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-person-cane"></i>
                                        </div>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">👴 Senior Citizen Services</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Social pension registration, senior citizen ID application, and stipend benefits for elderly residents.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>

                                <!-- Card 6: PWD Services -->
                                <div class="category-card service-select-card" style="background: var(--bg-card); border-radius: var(--radius-card); border: 2px solid var(--border-color); overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: all 0.3s ease;" onclick="selectServiceCard(this, 3, 'PWD Registration and Benefits', '3-5 days')">
                                    <div class="card-image" style="background: linear-gradient(135deg, #4a148c, #311b92); padding: 24px; text-align: center; position: relative;">
                                        <div class="icon-wrapper" style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin: 0 auto;">
                                            <i class="fas fa-wheelchair"></i>
                                        </div>
                                    </div>
                                    <div class="card-content" style="padding: 20px; flex: 1; text-align: center;">
                                        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">♿ PWD Services</h3>
                                        <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">Persons with Disability ID registration, discount booklets, and special assistance privileges.</p>
                                    </div>
                                    <div class="select-indicator" style="padding: 10px; text-align: center; background: rgba(0,0,0,0.03); border-top: 1px solid var(--border-color); font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                                        <i class="fas fa-hand-pointer"></i> Click to select service
                                    </div>
                                </div>
                            </div>

                            <!-- Prerequisite Alert display -->
                            <div class="dynamic-requirements-box" id="requirementsAlert">
                                <h4><i class="fas fa-file-circle-exclamation"></i> Documents to prepare:</h4>
                                <ul id="requirementsList"></ul>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-next" id="btnNext1" disabled onclick="goToStep(2)">Next: Schedule <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- STEP 2: DATE AND SLOTS SELECTOR -->
                        <div class="step-container" id="step2">
                            <h2 style="margin-bottom: 15px;"><i class="fas fa-calendar-day"></i> Select appointment date & time</h2>
                            
                            <div class="calendar-picker-row">
                                <div class="form-group">
                                    <label for="appointment_date"><i class="fas fa-calendar-check"></i> Choose Date</label>
                                    <input type="date" id="appointment_date" name="appointment_date" min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" onchange="loadAvailableSlots()">
                                </div>
                            </div>

                            <!-- Time Slots Segmented -->
                            <div class="time-slots-container-block" id="slotsBlock">
                                <div class="slots-section-header am-header"><i class="fas fa-sun"></i> Morning Slots (AM)</div>
                                <div class="time-slots-segregated" id="amSlots"></div>

                                <div class="slots-section-header pm-header"><i class="fas fa-cloud-sun"></i> Afternoon Slots (PM)</div>
                                <div class="time-slots-segregated" id="pmSlots"></div>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-prev" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="button" class="btn-wizard-nav btn-next" id="btnNext2" disabled onclick="goToStep(3)">Next: Review <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- STEP 3: DETAILS AND CONFIRM -->
                        <div class="step-container" id="step3">
                            <h2 style="margin-bottom: 15px;"><i class="fas fa-clipboard-check"></i> Finalize appointment details</h2>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="purpose"><i class="fas fa-pencil-alt"></i> Notes/Purpose (Optional)</label>
                                <textarea id="purpose" name="purpose" rows="3" placeholder="Explain the main reason for your booking..."></textarea>
                            </div>

                            <!-- Review Summary Box -->
                            <div class="summary-box">
                                <h4>Booking Summary</h4>
                                <div class="summary-row">
                                    <span class="summary-label">Selected Service:</span>
                                    <span class="summary-value" id="summaryService">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Estimated processing:</span>
                                    <span class="summary-value" id="summaryDuration">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Date:</span>
                                    <span class="summary-value" id="summaryDate">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Time Slot:</span>
                                    <span class="summary-value" id="summaryTime">-</span>
                                </div>
                            </div>

                            <!-- Checklist Checkbox -->
                            <div class="confirmation-checklist">
                                <input type="checkbox" id="confirmDocsCheck" onchange="toggleBookingSubmit()">
                                <label for="confirmDocsCheck">I confirm that I have prepared and will bring all the required documents listed in Step 1 to my appointment.</label>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-prev" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="submit" class="btn-wizard-nav btn-next" id="bookBtn" disabled><i class="fas fa-calendar-check"></i> Confirm Booking</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- My Appointments Column List -->
                <div class="my-appointments-section">
                    <h2><i class="fas fa-calendar-check"></i> Active appointments</h2>
                    <p class="section-subtitle" style="margin-bottom: 20px;">Manage and monitor your upcoming appointments.</p>

                    <?php if (!isLoggedIn()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-lock"></i>
                        <p>Authenticate your account to view your scheduled visits.</p>
                    </div>
                    <?php elseif (isStaff() || isAdmin()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-list-check"></i>
                        <p>Manage appointments inside the CSWDO Admin Portal.</p>
                        <a href="staff/manage-appointments.php" class="btn-login-prompt">Staff Booking Portal</a>
                    </div>
                    <?php elseif (empty($user_appointments)): ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-xmark"></i>
                        <p>No active scheduled appointments recorded yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($user_appointments as $appointment): ?>
                        <div class="appointment-card status-<?php echo $appointment['status']; ?>">
                            <div class="appointment-header">
                                <span class="appointment-service"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                                <span class="appointment-status <?php echo $appointment['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $appointment['status'] == 'pending' ? 'clock' : 
                                            ($appointment['status'] == 'confirmed' ? 'circle-check' : 
                                            ($appointment['status'] == 'completed' ? 'check-double' : 'circle-xmark')); 
                                    ?>"></i>
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>

                            <div class="appointment-details">
                                <div class="detail-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <span><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                </div>
                            </div>

                            <div class="appointment-actions">
                                <a href="view-appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-small btn-view">
                                    <i class="fas fa-eye"></i> View details
                                </a>
                                <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                <button onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)" class="btn-small btn-cancel">
                                    <i class="fas fa-xmark"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="view-all-link">
                        <a href="my-appointments.php">View All Appointments History <i class="fas fa-arrow-right-long"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Information Cards -->
            <div class="info-section">
                <div class="info-card">
                    <i class="fas fa-clock"></i>
                    <h3>Office Hours</h3>
                    <p>Monday - Friday<br>8:00 AM - 5:00 PM</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-file-pdf"></i>
                    <h3>Verification</h3>
                    <p>Prepare digital or original photocopies of all listed service documents before your scheduled visit.</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-bell"></i>
                    <h3>Notifications</h3>
                    <p>Automated reminders will be delivered to your registered phone number 24 hours prior to booking.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal Dialog -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h2>
                <button class="close-btn" onclick="closeModal('cancelModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this appointment?</p>
                <p class="text-danger" style="font-weight: 700;"><i class="fas fa-triangle-exclamation"></i> This action is permanent and cannot be undone.</p>
            </div>
            <div class="modal-actions">
                <button onclick="closeModal('cancelModal')" class="btn-small btn-view" style="padding: 10px 20px;">Keep appointment</button>
                <button id="confirmCancelBtn" class="btn-small btn-cancel" style="padding: 10px 20px;">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <!-- Success Modal Dialog -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle" style="color: var(--color-success);"></i> Booked successfully</h2>
                <button class="close-btn" onclick="closeModal('successModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="successMessage">Your appointment has been successfully scheduled! You can review details inside your appointments menu.</p>
            </div>
            <div class="modal-actions" style="justify-content: center;">
                <button onclick="closeModal('successModal')" class="btn-wizard-nav btn-next" style="margin-left:0; padding: 10px 30px;">Continue</button>
            </div>
        </div>
    </div>

    <script>
    // ========== STATE VARIABLES ==========
    let currentStep = 1;
    let selectedServiceId = null;
    let selectedServiceName = '';
    let selectedServiceDuration = '';
    let currentAppointmentId = null;

    // ========== DYNAMIC SERVICE REQUIREMENTS DICTIONARY ==========
    const serviceRequirementsMap = {
        'medical assistance': [
            'Medical Abstract/Certificate (Original, issued within the last 3 months)',
            'Doctor Prescription or Treatment Protocol/Price Quotation',
            'Barangay Certificate of Indigency (Original)',
            'Valid Government ID of the patient & applicant'
        ],
        'cash relief assistance': [
            'Barangay Certificate of Indigency (Original)',
            'Valid Government ID of applicant',
            'Incident/Blotter Report or Social Worker Assessment Report'
        ],
        'educational assistance': [
            'Certificate of Enrollment or Certificate of Registration (latest semester)',
            'School ID photocopy (front and back)',
            'Barangay Certificate of Indigency of parent/guardian (Original)',
            'Tuition fee statement or Statement of Account'
        ],
        'burial assistance': [
            'Registered Death Certificate (photocopy)',
            'Funeral Contract / Funeral Service Invoice',
            'Barangay Certificate of Indigency of the claimant (Original)',
            'Valid Government ID of the claimant/applicant'
        ],
        'food assistance': [
            'Barangay Certificate of Indigency (Original)',
            'Valid Government ID of the applicant',
            'Social Case Study Report (from municipal CSWDO social worker, if applicable)'
        ],
        'funeral assistance': [
            'Registered Death Certificate (photocopy)',
            'Funeral Contract / Funeral Service Invoice',
            'Barangay Certificate of Indigency of the claimant (Original)',
            'Valid Government ID of the claimant/applicant'
        ],
        'material assistance': [
            'Barangay Certificate of Indigency (Original)',
            'Valid Government ID of the applicant',
            'Estimated cost quotation / materials description list'
        ],
        'transportation assistance': [
            'Police Clearance or Travel Authority/Referral (if applicable)',
            'Barangay Certificate of Indigency (Original)',
            'Valid ID of the traveler',
            'Referral Letter or Medical Abstract (if traveling for medical treatments)'
        ],
        'housing application': [
            'Letter of Intent addressed to CSWDO committee',
            'Barangay Clearance/Indigency (Original)',
            'Government Valid ID',
            'Photos of Current House condition',
            'Utility Proof of Billing'
        ],
        'housing program': [
            'Letter of Intent addressed to CSWDO committee',
            'Barangay Clearance/Indigency (Original)',
            'Government Valid ID',
            'Photos of Current House condition',
            'Utility Proof of Billing'
        ],
        'senior citizen pension': [
            'Proof of Age (Birth Certificate, Senior Citizen ID or Barangay Certification)',
            'Barangay Certificate of Indigency (Original)',
            'Valid Government ID',
            '1x1 or 2x2 ID Photo (2 copies)'
        ],
        'pwd registration and benefits': [
            'Medical Certificate specifying type of disability (issued by licensed physician)',
            'Barangay Certificate of Residency',
            '2 pcs 1x1 recent ID photos',
            'Valid Government ID or Birth Certificate of PWD'
        ]
    };

    // ========== STEP NAVIGATION WIZARD ==========
    function goToStep(step) {
        // Validation check
        if (step === 2 && !selectedServiceId) return;
        if (step === 3 && (!document.getElementById('appointment_date').value || !document.getElementById('appointment_time').value)) return;

        // Reset step state styling
        document.querySelectorAll('.step-container').forEach(cont => cont.classList.remove('active'));
        document.querySelectorAll('.wizard-step').forEach(hdr => hdr.classList.remove('active'));

        document.getElementById(`step${step}`).classList.add('active');
        document.getElementById(`stepHeader${step}`).classList.add('active');

        // Apply completion color state
        for (let i = 1; i < step; i++) {
            document.getElementById(`stepHeader${i}`).classList.add('completed');
        }
        for (let i = step; i <= 3; i++) {
            document.getElementById(`stepHeader${i}`).classList.remove('completed');
        }

        currentStep = step;

        // Compile Summary details if entering step 3
        if (step === 3) {
            document.getElementById('summaryService').textContent = selectedServiceName;
            document.getElementById('summaryDuration').textContent = selectedServiceDuration;
            
            const rawDate = document.getElementById('appointment_date').value;
            if (rawDate) {
                const dateObj = new Date(rawDate);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                document.getElementById('summaryDate').textContent = formattedDate;
            }

            const timeLabel = document.querySelector('.time-slot-btn.selected .slot-time')?.textContent || '-';
            document.getElementById('summaryTime').textContent = timeLabel;
            
            toggleBookingSubmit();
        }
    }

    // Show requirements helper function
    function showRequirements(categoryKey) {
        let matchedReqs = [];
        if (categoryKey === 'funeral') {
            matchedReqs = serviceRequirementsMap['burial assistance'] || serviceRequirementsMap['funeral assistance'] || [];
        } else if (categoryKey === 'food_transport') {
            matchedReqs = [...(serviceRequirementsMap['food assistance'] || []), ...(serviceRequirementsMap['transportation assistance'] || [])];
        } else if (categoryKey === 'medical_financial') {
            matchedReqs = [...(serviceRequirementsMap['medical assistance'] || []), ...(serviceRequirementsMap['cash relief assistance'] || [])];
        } else if (categoryKey === 'housing') {
            matchedReqs = serviceRequirementsMap['housing application'] || serviceRequirementsMap['housing program'] || [];
        } else if (categoryKey === 'senior') {
            matchedReqs = serviceRequirementsMap['senior citizen pension'] || [];
        } else if (categoryKey === 'pwd') {
            matchedReqs = serviceRequirementsMap['pwd registration and benefits'] || [];
        }

        const alertBox = document.getElementById('requirementsAlert');
        const listContainer = document.getElementById('requirementsList');
        if (alertBox && listContainer) {
            listContainer.innerHTML = '';
            if (matchedReqs.length > 0) {
                matchedReqs.forEach(req => {
                    const li = document.createElement('li');
                    li.textContent = req;
                    listContainer.appendChild(li);
                });
                alertBox.style.display = 'block';
            }
        }
    }

    // Select service card action
    function selectServiceCard(cardElement, serviceId, name, duration) {
        selectedServiceId = serviceId;
        selectedServiceName = name;
        selectedServiceDuration = duration;
        document.getElementById('service_id').value = serviceId;

        // Reset all card selections
        document.querySelectorAll('.service-select-card').forEach(card => {
            card.style.borderColor = 'var(--border-color)';
            card.style.boxShadow = 'none';
            card.style.background = 'var(--bg-card)';
            const indicator = card.querySelector('.select-indicator');
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-hand-pointer"></i> Click to select service';
                indicator.style.background = 'rgba(0,0,0,0.03)';
                indicator.style.color = 'var(--text-muted)';
            }
        });

        // Highlight selected card
        const card = cardElement.classList.contains('service-select-card') ? cardElement : cardElement.closest('.service-select-card');
        if (card) {
            card.style.borderColor = 'var(--maroon)';
            card.style.boxShadow = '0 8px 24px rgba(127, 3, 3, 0.18)';
            card.style.background = 'rgba(127, 3, 3, 0.03)';
            const indicator = card.querySelector('.select-indicator');
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-check-circle" style="color: #2E7D32;"></i> Selected';
                indicator.style.background = 'rgba(46, 125, 50, 0.12)';
                indicator.style.color = '#2E7D32';
            }
        }

        // Dynamic Document loading
        const normName = name.toLowerCase();
        let matchedReqs = [];
        
        // Find requirements matches
        for (let key in serviceRequirementsMap) {
            if (normName.indexOf(key) !== -1 || key.indexOf(normName) !== -1) {
                matchedReqs = serviceRequirementsMap[key];
                break;
            }
        }

        const alertBox = document.getElementById('requirementsAlert');
        const listContainer = document.getElementById('requirementsList');
        listContainer.innerHTML = '';

        if (matchedReqs && matchedReqs.length > 0) {
            matchedReqs.forEach(req => {
                const li = document.createElement('li');
                li.textContent = req;
                listContainer.appendChild(li);
            });
            alertBox.style.display = 'block';
        } else {
            // Default requirement list
            const defaults = serviceRequirementsMap['burial assistance'] || [];
            defaults.forEach(req => {
                const li = document.createElement('li');
                li.textContent = req;
                listContainer.appendChild(li);
            });
            alertBox.style.display = 'block';
        }

        // Enable next step button
        document.getElementById('btnNext1').removeAttribute('disabled');
    }

    // Grouping slots dynamically on API call
    function loadAvailableSlots() {
        const date = document.getElementById('appointment_date').value;
        const slotsBlock = document.getElementById('slotsBlock');
        const amSlotsDiv = document.getElementById('amSlots');
        const pmSlotsDiv = document.getElementById('pmSlots');
        const btnNext2 = document.getElementById('btnNext2');

        if (!date) {
            slotsBlock.style.display = 'none';
            btnNext2.setAttribute('disabled', 'true');
            return;
        }

        amSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">Loading slots...</div>';
        pmSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">Loading slots...</div>';
        slotsBlock.style.display = 'block';

        fetch(`get-available-slots.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                amSlotsDiv.innerHTML = '';
                pmSlotsDiv.innerHTML = '';

                if (data.slots && data.slots.length > 0) {
                    let hasAm = false;
                    let hasPm = false;

                    data.slots.forEach(slot => {
                        const slotButton = document.createElement('div');
                        slotButton.className = `time-slot-btn ${slot.available ? 'available' : 'disabled'}`;
                        
                        let badgeClass = 'badge-available';
                        let badgeText = `${slot.remaining} left`;

                        if (!slot.available || slot.remaining <= 0) {
                            badgeClass = 'badge-full';
                            badgeText = 'FULL';
                            slotButton.classList.add('disabled');
                        } else if (slot.remaining <= 2) {
                            badgeClass = 'badge-warning';
                        }

                        slotButton.innerHTML = `
                            <span class="slot-time">${slot.time}</span>
                            <span class="slot-badge ${badgeClass}">${badgeText}</span>
                        `;

                        if (slot.available) {
                            slotButton.onclick = () => selectTimeSlotBtn(slot.value, slotButton);
                        }

                        // AM/PM classification
                        const hour = parseInt(slot.value.split(':')[0]);
                        if (hour < 12) {
                            amSlotsDiv.appendChild(slotButton);
                            hasAm = true;
                        } else {
                            pmSlotsDiv.appendChild(slotButton);
                            hasPm = true;
                        }
                    });

                    if (!hasAm) amSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">No morning slots</div>';
                    if (!hasPm) pmSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;">No afternoon slots</div>';

                } else {
                    amSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.8rem;">Fully booked today</div>';
                    pmSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.8rem;">Fully booked today</div>';
                }
            })
            .catch(error => {
                console.error('Error loading time slots:', error);
                amSlotsDiv.innerHTML = '<div style="color:var(--color-danger);">Load failed</div>';
                pmSlotsDiv.innerHTML = '<div style="color:var(--color-danger);">Load failed</div>';
            });
    }

    // Highlight slots buttons
    function selectTimeSlotBtn(value, element) {
        document.getElementById('appointment_time').value = value;
        document.querySelectorAll('.time-slot-btn').forEach(btn => btn.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('btnNext2').removeAttribute('disabled');
    }

    // Toggle confirm submit
    function toggleBookingSubmit() {
        const checkbox = document.getElementById('confirmDocsCheck');
        const submitBtn = document.getElementById('bookBtn');
        if (checkbox.checked && selectedServiceId && document.getElementById('appointment_time').value) {
            submitBtn.removeAttribute('disabled');
        } else {
            submitBtn.setAttribute('disabled', 'true');
        }
    }

    // Submit Booking logic
    function bookAppointment(event) {
        event.preventDefault();
        const serviceId = document.getElementById('service_id').value;
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        const purpose = document.getElementById('purpose').value;
        const submitBtn = document.getElementById('bookBtn');

        if (!serviceId || !date || !time) {
            alert('Fill out all required scheduling parameters first');
            return false;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling visit...';

        fetch('book-appointment-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ service_id: serviceId, appointment_date: date, appointment_time: time, purpose: purpose })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('successMessage').textContent = data.message || 'Appointment booked successfully!';
                openModal('successModal');
                document.getElementById('bookingForm').reset();
                submitBtn.disabled = true;
                
                setTimeout(() => location.reload(), 2500);
            } else {
                alert(data.message || 'Booking submission failed');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Appointment';
            }
        })
        .catch(error => {
            console.error('Error booking:', error);
            alert('Network error occurred. Try booking again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Appointment';
        });

        return false;
    }

    // Cancel appointment trigger
    function cancelAppointment(id) {
        currentAppointmentId = id;
        openModal('cancelModal');
    }

    document.getElementById('confirmCancelBtn')?.addEventListener('click', function() {
        if (!currentAppointmentId) return;
        fetch('cancel-appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ appointment_id: currentAppointmentId })
        })
        .then(response => response.json())
        .then(data => {
            closeModal('cancelModal');
            if (data.success) {
                alert('Appointment cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel appointment');
            }
        })
        .catch(error => {
            console.error('Error cancelling:', error);
            alert('An error occurred');
        });
    });

    // Modal display scripts
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }

    // ========== MOBILE SIDEBAR TOGGLE ==========
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarClose = document.getElementById('sidebarClose');

    function openSidebar() {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSidebar() {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
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
    </script>
</body>
</html>

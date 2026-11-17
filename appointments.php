<?php
// appointments.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

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

// Get resident details
$resident_info = null;
if (isLoggedIn() && !isStaff() && !isAdmin()) {
    $r_stmt = $db->prepare("SELECT u.first_name, u.last_name, u.email, u.contact_number, r.barangay, r.street, r.birth_date, r.senior_citizen 
                            FROM users u 
                            LEFT JOIN residents r ON u.user_id = r.user_id 
                            WHERE u.user_id = :id");
    $r_stmt->execute([':id' => $_SESSION['user_id']]);
    $resident_info = $r_stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($resident_info['birth_date']) && $resident_info['birth_date'] !== '0000-00-00') {
        $bDate = new DateTime($resident_info['birth_date']);
        $age = (new DateTime())->diff($bDate)->y;
        if ($age >= 60) {
            $_SESSION['is_senior_citizen'] = true;
            if (empty($resident_info['senior_citizen'])) {
                $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")->execute([':uid' => $_SESSION['user_id']]);
                $resident_info['senior_citizen'] = 1;
            }
        }
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
              LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Separate upcoming vs history appointments
$upcoming_appointments = [];
$history_appointments = [];
$today_str = date('Y-m-d');

foreach ($user_appointments as $app) {
    $is_future = ($app['appointment_date'] >= $today_str);
    $is_active_status = ($app['status'] === 'pending' || $app['status'] === 'confirmed');
    
    if ($is_future && $is_active_status) {
        $upcoming_appointments[] = $app;
    } else {
        $history_appointments[] = $app;
    }
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
    <title>Appointments Scheduling - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7B1113;
            --maroon-light: #9A1515;
            --maroon-dark: #5A0202;
            --maroon-subtle: rgba(123, 17, 19, 0.08);
            --maroon-border: rgba(123, 17, 19, 0.2);
            
            --bg-page: #F8FAFC;
            --bg-card: #FFFFFF;
            --bg-muted: #F1F5F9;
            --bg-subtle: #F8FAFC;
            
            --text-primary: #0F172A;
            --text-secondary: #334155;
            --text-muted: #64748B;
            --text-light: #94A3B8;
            
            --border-color: #E2E8F0;
            --border-focus: #CBD5E1;
            
            --shadow-subtle: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
            
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --radius-full: 9999px;
        }

        [data-theme="dark"] {
            --bg-page: #0B1120;
            --bg-card: #131D33;
            --bg-muted: #1E293B;
            --bg-subtle: #182238;
            
            --text-primary: #F8FAFC;
            --text-secondary: #CBD5E1;
            --text-muted: #94A3B8;
            --text-light: #64748B;
            
            --border-color: #24334D;
            --border-focus: #3B527A;
            --maroon-subtle: rgba(220, 38, 38, 0.15);
            --maroon-border: rgba(220, 38, 38, 0.3);
            
            --shadow-subtle: 0 1px 3px 0 rgba(0, 0, 0, 0.3);
            --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 10px 20px -3px rgba(0, 0, 0, 0.5);
        }

        body {
            background-color: var(--ref-canvas, #E4EFE7) !important;
            color: var(--ref-text, var(--text-secondary));
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* ========== FULL-WIDTH LANDSCAPE APPOINTMENT DESK ========== */
        .appointments-grid {
            display: block;
            width: 100%;
        }

        /* ========== MODERN STEPPER PROGRESS BAR ========== */
        .stepper-container {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 12px 18px;
            margin-bottom: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .stepper-step {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            position: relative;
        }
        .step-indicator {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-full);
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .step-label-group {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .step-micro-tag {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        .step-main-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-secondary);
            white-space: nowrap;
            transition: color 0.2s;
        }
        .stepper-divider {
            flex: 1;
            height: 2px;
            background: var(--border-color);
            margin: 0 4px;
            max-width: 40px;
            transition: background 0.3s ease;
        }

        /* Stepper States */
        .stepper-step.active .step-indicator {
            background: var(--maroon);
            border-color: var(--maroon);
            color: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(123, 17, 19, 0.15);
        }
        .stepper-step.active .step-micro-tag {
            color: var(--maroon);
        }
        .stepper-step.active .step-main-title {
            color: var(--text-primary);
        }

        .stepper-step.completed .step-indicator {
            background: #DCFCE7;
            border-color: #86EFAC;
            color: #15803D;
        }
        [data-theme="dark"] .stepper-step.completed .step-indicator {
            background: rgba(21, 128, 61, 0.25);
            border-color: rgba(74, 222, 128, 0.4);
            color: #4ADE80;
        }
        .stepper-step.completed .step-micro-tag {
            color: #15803D;
        }
        .stepper-step.completed .step-main-title {
            color: var(--text-primary);
        }
        .stepper-divider.completed {
            background: #86EFAC;
        }

        /* ========== BOOKING WIZARD BOX ========== */
        .booking-section {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 28px 30px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-color);
        }
        .section-header-title {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.02em;
        }
        .section-subtitle-muted {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin-bottom: 22px;
        }

        .step-container {
            display: none;
            animation: fadeIn 0.3s ease forwards;
        }
        .step-container.active {
            display: block;
        }

        /* ========== SERVICE SELECTION CARDS (FULL WIDTH LANDSCAPE) ========== */
        .services-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        @media (max-width: 992px) {
            .services-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .services-cards-grid {
                grid-template-columns: 1fr;
            }
        }
        .service-card-item {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            position: relative;
            outline: none;
            text-align: left;
        }
        .service-card-item:hover {
            transform: translateY(-2px);
            border-color: #CBD5E1;
            box-shadow: var(--shadow-card);
        }
        .service-card-item.selected {
            border-color: var(--maroon);
            background: var(--maroon-subtle);
            box-shadow: 0 0 0 2px var(--maroon), 0 8px 20px -4px rgba(123, 17, 19, 0.12);
        }

        .service-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .service-icon-box {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        .service-card-item:hover .service-icon-box {
            transform: scale(1.05);
        }

        /* Themed Soft Pastel Icon Boxes */
        .service-icon-box.burial { background: #FEE2E2; color: #991B1B; }
        .service-icon-box.food { background: #DCFCE7; color: #166534; }
        .service-icon-box.medical { background: #E0F2FE; color: #0369A1; }
        .service-icon-box.housing { background: #FFEDD5; color: #C2410C; }
        .service-icon-box.senior { background: #FEF3C7; color: #B45309; }
        .service-icon-box.pwd { background: #F3E8FF; color: #7E22CE; }

        [data-theme="dark"] .service-icon-box.burial { background: rgba(153, 27, 27, 0.25); color: #FCA5A5; }
        [data-theme="dark"] .service-icon-box.food { background: rgba(22, 101, 52, 0.25); color: #86EFAC; }
        [data-theme="dark"] .service-icon-box.medical { background: rgba(3, 105, 161, 0.25); color: #7DD3FC; }
        [data-theme="dark"] .service-icon-box.housing { background: rgba(194, 65, 12, 0.25); color: #FDBA74; }
        [data-theme="dark"] .service-icon-box.senior { background: rgba(180, 83, 9, 0.25); color: #FCD34D; }
        [data-theme="dark"] .service-icon-box.pwd { background: rgba(126, 34, 206, 0.25); color: #D8B4FE; }

        .service-card-badges {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .service-pill-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--radius-full);
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .service-pill-badge.urgent {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid rgba(185, 28, 28, 0.2);
        }
        .service-pill-badge.popular {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid rgba(180, 83, 9, 0.2);
        }

        /* Radio Indicator Ring */
        .service-radio-ring {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            color: transparent;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .service-card-item.selected .service-radio-ring {
            border-color: var(--maroon);
            background: var(--maroon);
            color: #FFFFFF;
        }

        .service-card-body {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .service-card-title {
            font-size: 0.94rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .service-card-desc {
            font-size: 0.78rem;
            color: var(--text-secondary);
            line-height: 1.45;
            margin: 0;
        }

        .service-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.74rem;
            color: var(--text-muted);
            font-weight: 600;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }
        .service-duration-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .service-status-text {
            font-size: 0.72rem;
            color: var(--text-light);
            font-weight: 600;
            transition: color 0.2s;
        }
        .service-card-item.selected .service-status-text {
            color: var(--maroon);
            font-weight: 700;
        }

        /* Dynamic Requirements Box */
        .dynamic-requirements-box {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--maroon);
            padding: 18px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: none;
            box-shadow: var(--shadow-subtle);
        }
        .dynamic-requirements-box h4 {
            color: var(--text-primary);
            font-size: 0.9rem;
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
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dynamic-requirements-box li {
            padding-left: 18px;
            position: relative;
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .dynamic-requirements-box li:before {
            content: "✓";
            color: #15803D;
            position: absolute;
            left: 0;
            font-weight: bold;
            font-size: 0.78rem;
        }

        /* ========== STEP 2: DATE & TIME ========== */
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .form-control-modern {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-subtle);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.88rem;
            outline: none;
            transition: all 0.2s ease;
        }
        .form-control-modern:focus {
            border-color: var(--maroon);
            background: var(--bg-card);
            box-shadow: 0 0 0 3px rgba(123, 17, 19, 0.08);
        }

        .slots-section-header {
            font-size: 0.78rem;
            font-weight: 750;
            color: var(--text-muted);
            margin: 18px 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .time-slots-segregated {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
            margin-bottom: 18px;
        }
        .time-slot-btn {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 9px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
        }
        .time-slot-btn:hover:not(.disabled) {
            transform: translateY(-1px);
            border-color: var(--maroon);
            background: var(--bg-card);
        }
        .time-slot-btn.selected {
            background: var(--maroon) !important;
            border-color: var(--maroon) !important;
            color: #FFFFFF !important;
            box-shadow: var(--shadow-subtle);
        }
        .time-slot-btn.selected .slot-time { color: #FFFFFF !important; }
        .time-slot-btn.selected .slot-badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #FFFFFF !important;
        }
        .time-slot-btn.disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: var(--bg-muted);
            border-color: var(--border-color);
        }
        .time-slot-btn .slot-time {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .slot-badge {
            font-size: 0.65rem;
            padding: 2px 7px;
            border-radius: var(--radius-full);
            font-weight: 700;
        }
        .slot-badge.badge-available { background: #DCFCE7; color: #15803D; }
        .slot-badge.badge-warning { background: #FEF3C7; color: #B45309; }
        .slot-badge.badge-full { background: #FEE2E2; color: #B91C1C; }

        /* ========== STEP 3: CONFIRMATION ========== */
        .summary-box {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 22px;
        }
        .summary-box h4 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.84rem;
        }
        .summary-row:last-child { margin-bottom: 0; }
        .summary-label { color: var(--text-muted); font-weight: 500; }
        .summary-value { color: var(--text-primary); font-weight: 700; }

        .confirmation-checklist {
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-top: 14px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .confirmation-checklist input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--maroon);
            cursor: pointer;
        }
        .confirmation-checklist label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
            line-height: 1.45;
            cursor: pointer;
        }

        /* Wizard Navigation Buttons */
        .wizard-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        .btn-wizard-nav {
            padding: 10px 22px;
            border-radius: var(--radius-md);
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-wizard-nav.btn-prev {
            background: var(--bg-subtle);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-wizard-nav.btn-prev:hover {
            border-color: #CBD5E1;
            background: var(--bg-muted);
        }
        .btn-wizard-nav.btn-next {
            background: var(--maroon);
            color: #FFFFFF;
            border: 1px solid var(--maroon);
            margin-left: auto;
            box-shadow: var(--shadow-subtle);
        }
        .btn-wizard-nav.btn-next:hover:not(:disabled) {
            background: var(--maroon-light);
            border-color: var(--maroon-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-card);
        }
        .btn-wizard-nav.btn-next:disabled {
            background: var(--bg-muted);
            border-color: var(--border-color);
            color: var(--text-light);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* ========== APPOINTMENTS FEED (RIGHT COLUMN) ========== */
        .my-appointments-section {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 26px 28px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-color);
        }
        .feed-header-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .feed-filter-tabs {
            display: flex;
            gap: 6px;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            padding: 3px;
            border-radius: var(--radius-md);
        }
        .feed-tab-btn {
            background: transparent;
            border: none;
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .feed-tab-btn.active {
            background: var(--bg-card);
            color: var(--maroon);
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .feed-tab-count {
            font-size: 0.68rem;
            padding: 1px 6px;
            border-radius: var(--radius-full);
            background: var(--bg-muted);
            color: var(--text-secondary);
        }
        .feed-tab-btn.active .feed-tab-count {
            background: var(--maroon-subtle);
            color: var(--maroon);
        }

        /* Compact Appointment Card */
        .appointment-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            transition: all 0.2s ease;
            position: relative;
        }
        .appointment-card:hover {
            transform: translateY(-1px);
            border-color: #CBD5E1;
            box-shadow: var(--shadow-card);
        }

        .appointment-card.status-pending { border-left-color: #F59E0B; }
        .appointment-card.status-confirmed { border-left-color: #10B981; }
        .appointment-card.status-completed { border-left-color: #0284C7; }
        .appointment-card.status-cancelled { border-left-color: #EF4444; opacity: 0.85; }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .appointment-service {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.88rem;
            line-height: 1.3;
        }
        .appointment-status {
            padding: 3px 9px;
            border-radius: var(--radius-full);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .appointment-status.pending { background: #FEF3C7; color: #B45309; border: 1px solid rgba(180, 83, 9, 0.2); }
        .appointment-status.confirmed { background: #DCFCE7; color: #15803D; border: 1px solid rgba(21, 128, 61, 0.2); }
        .appointment-status.completed { background: #E0F2FE; color: #0369A1; border: 1px solid rgba(3, 105, 161, 0.2); }
        .appointment-status.cancelled { background: #FEE2E2; color: #B91C1C; border: 1px solid rgba(185, 28, 28, 0.2); }

        [data-theme="dark"] .appointment-status.pending { background: rgba(180, 83, 9, 0.2); color: #FCD34D; }
        [data-theme="dark"] .appointment-status.confirmed { background: rgba(21, 128, 61, 0.2); color: #4ADE80; }
        [data-theme="dark"] .appointment-status.completed { background: rgba(3, 105, 161, 0.2); color: #38BDF8; }
        [data-theme="dark"] .appointment-status.cancelled { background: rgba(185, 28, 28, 0.2); color: #F87171; }

        .appointment-details {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            font-size: 0.76rem;
            color: var(--text-muted);
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }
        .detail-item i { color: var(--maroon); }
        [data-theme="dark"] .detail-item i { color: #F87171; }

        .appointment-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }
        .btn-small {
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.74rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        .btn-view {
            background: var(--bg-subtle);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-view:hover {
            border-color: var(--maroon);
            color: var(--maroon);
            background: var(--bg-card);
        }
        .btn-cancel {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        .btn-cancel:hover {
            background: #DC2626;
            color: #FFFFFF;
        }
        [data-theme="dark"] .btn-cancel {
            background: rgba(220, 38, 38, 0.15);
            color: #F87171;
            border-color: rgba(220, 38, 38, 0.3);
        }
        [data-theme="dark"] .btn-cancel:hover {
            background: #DC2626;
            color: #FFFFFF;
        }

        .view-all-link {
            text-align: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }
        .view-all-link a {
            color: var(--maroon);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }
        .view-all-link a:hover {
            text-decoration: underline;
            gap: 8px;
        }

        /* Empty States */
        .empty-feed-state {
            text-align: center;
            padding: 36px 20px;
            color: var(--text-muted);
        }
        .empty-feed-state i {
            font-size: 2.4rem;
            opacity: 0.25;
            color: var(--maroon);
            margin-bottom: 10px;
            display: block;
        }
        .empty-feed-state h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .empty-feed-state p {
            font-size: 0.78rem;
            max-width: 260px;
            margin: 0 auto;
            line-height: 1.45;
        }

        /* ========== INFO CARDS AT THE BOTTOM ========== */
        .info-section {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }
        .info-card {
            background: var(--bg-card);
            padding: 22px 20px;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-color);
            transition: all 0.22s ease;
        }
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card);
        }
        .info-card i {
            font-size: 1.6rem;
            color: var(--maroon);
            margin-bottom: 10px;
            display: inline-block;
        }
        [data-theme="dark"] .info-card i { color: #F87171; }
        .info-card h3 {
            color: var(--text-primary);
            font-size: 0.94rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .info-card p {
            color: var(--text-muted);
            font-size: 0.78rem;
            line-height: 1.5;
            margin: 0;
        }

        /* ========== CONFIRMATION MODALS ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 20px;
        }
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            padding: 28px;
            border-radius: var(--radius-xl);
            max-width: 440px;
            width: 100%;
            transform: translateY(15px) scale(0.98);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-hover);
        }
        .modal.show .modal-content {
            transform: translateY(0) scale(1);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        .close-btn:hover { color: var(--text-primary); }

        .modal-body p {
            color: var(--text-secondary);
            font-size: 0.88rem;
            line-height: 1.55;
            margin-bottom: 8px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 22px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Breakpoints */
        @media (max-width: 1100px) {
            .appointments-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .services-cards-grid {
                grid-template-columns: 1fr;
            }
            .time-slots-segregated {
                grid-template-columns: repeat(2, 1fr);
            }
            .stepper-container {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .stepper-divider {
                display: none;
            }
            .info-section {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 480px) {
            .time-slots-segregated {
                grid-template-columns: 1fr !important;
            }
            .appointment-wizard-card,
            .my-appointments-section {
                padding: 18px 14px !important;
                border-radius: 18px !important;
            }
            .stepper-label {
                font-size: 0.72rem !important;
            }
            .btn-wizard-nav {
                padding: 10px 18px !important;
                font-size: 0.8rem !important;
            }
        }

        /* ========== SLIDING TOAST NOTIFICATIONS (Upper Right Corner) ========== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 440px;
            width: calc(100% - 48px);
            pointer-events: none;
        }

        .toast-sliding-card {
            pointer-events: auto;
            background: #FFFFFF;
            border-radius: 16px;
            padding: 16px 18px 16px 16px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.16), 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            border-left: 5px solid;
            position: relative;
            overflow: hidden;
            animation: toastSlideInRight 0.38s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transition: all 0.25s ease;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        [data-theme="dark"] .toast-sliding-card {
            background: #1E293B;
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
            color: #F8FAFC;
        }

        .toast-sliding-card.hide {
            animation: toastSlideOutRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes toastSlideInRight {
            from {
                opacity: 0;
                transform: translateX(80px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes toastSlideOutRight {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateX(80px) scale(0.95);
            }
        }

        .toast-card-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .toast-sliding-card.warning {
            border-left-color: #D97706;
        }
        .toast-sliding-card.warning .toast-card-icon {
            background: #FEF3C7;
            color: #D97706;
        }
        [data-theme="dark"] .toast-sliding-card.warning .toast-card-icon {
            background: rgba(217, 119, 6, 0.2);
            color: #FBBF24;
        }

        .toast-sliding-card.error {
            border-left-color: #DC2626;
        }
        .toast-sliding-card.error .toast-card-icon {
            background: #FEE2E2;
            color: #DC2626;
        }
        [data-theme="dark"] .toast-sliding-card.error .toast-card-icon {
            background: rgba(220, 38, 38, 0.2);
            color: #F87171;
        }

        .toast-sliding-card.success {
            border-left-color: #059669;
        }
        .toast-sliding-card.success .toast-card-icon {
            background: #D1FAE5;
            color: #059669;
        }
        [data-theme="dark"] .toast-sliding-card.success .toast-card-icon {
            background: rgba(5, 150, 105, 0.2);
            color: #34D399;
        }

        .toast-sliding-card.info {
            border-left-color: #7F0303;
        }
        .toast-sliding-card.info .toast-card-icon {
            background: rgba(127, 3, 3, 0.1);
            color: #7F0303;
        }
        [data-theme="dark"] .toast-sliding-card.info .toast-card-icon {
            background: rgba(239, 68, 68, 0.18);
            color: #FCA5A5;
        }

        .toast-card-content {
            flex: 1;
            min-width: 0;
        }

        .toast-card-title {
            font-weight: 700;
            font-size: 0.92rem;
            color: #0F172A;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        [data-theme="dark"] .toast-card-title {
            color: #F8FAFC;
        }

        .toast-card-message {
            font-size: 0.84rem;
            color: #475569;
            line-height: 1.5;
            word-break: break-word;
        }
        [data-theme="dark"] .toast-card-message {
            color: #CBD5E1;
        }

        .toast-card-close {
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.2s;
            flex-shrink: 0;
            margin-top: -3px;
            margin-right: -4px;
        }
        .toast-card-close:hover {
            color: #7F0303;
            background: rgba(0, 0, 0, 0.05);
        }
        [data-theme="dark"] .toast-card-close:hover {
            color: #F87171;
            background: rgba(255, 255, 255, 0.08);
        }

        .toast-card-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            transform-origin: left;
            animation: toastProgressBar linear forwards;
        }
        .toast-sliding-card.warning .toast-card-progress { background: #D97706; }
        .toast-sliding-card.error .toast-card-progress { background: #DC2626; }
        .toast-sliding-card.success .toast-card-progress { background: #059669; }
        .toast-sliding-card.info .toast-card-progress { background: #7F0303; }

        @keyframes toastProgressBar {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content Panel -->
        <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "Appointment Desk & Scheduling";
            $banner_subtitle = "Schedule a new resident support consultation at CSWDO Palayan City Hall";
            $banner_icon = "fa-calendar-plus";
            $banner_actions = [
                ["label" => "View My Appointments", "url" => "my-appointments.php", "icon" => "fa-list-check", "type" => "primary"],
                ["label" => "Calendar View", "url" => "calendar.php", "icon" => "fa-calendar-alt", "type" => "secondary"]
            ];
            include __DIR__ . "/includes/portal-banner.php";
            ?>

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

            <!-- 2-Column Balanced Responsive Grid -->
            <div class="appointments-grid">
                <!-- Left Column: 3-Step Wizard Block -->
                <div class="booking-section">
                    <!-- Modern Stepper Indicator -->
                    <div class="stepper-container">
                        <div class="stepper-step active" id="stepHeader1">
                            <span class="step-indicator" id="stepIcon1">1</span>
                            <div class="step-label-group">
                                <span class="step-micro-tag">Step 1</span>
                                <span class="step-main-title">Select Service</span>
                            </div>
                        </div>
                        <div class="stepper-divider" id="stepDiv1"></div>
                        <div class="stepper-step" id="stepHeader2">
                            <span class="step-indicator" id="stepIcon2">2</span>
                            <div class="step-label-group">
                                <span class="step-micro-tag">Step 2</span>
                                <span class="step-main-title">Date & Time</span>
                            </div>
                        </div>
                        <div class="stepper-divider" id="stepDiv2"></div>
                        <div class="stepper-step" id="stepHeader3">
                            <span class="step-indicator" id="stepIcon3">3</span>
                            <div class="step-label-group">
                                <span class="step-micro-tag">Step 3</span>
                                <span class="step-main-title">Confirmation</span>
                            </div>
                        </div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                    <div class="empty-feed-state">
                        <i class="fas fa-right-to-bracket"></i>
                        <h4>Resident Sign-in Required</h4>
                        <p>Please log in to your verified resident account to book an appointment.</p>
                        <a href="login.php" class="btn-wizard-nav btn-next" style="margin-top: 15px; display: inline-flex;">Sign In Portal</a>
                    </div>
                    <?php elseif (isStaff() || isAdmin()): ?>
                    <div class="empty-feed-state">
                        <i class="fas fa-user-tie"></i>
                        <h4>Staff Account Detected</h4>
                        <p>Staff and administrative users manage appointments via the staff portal.</p>
                        <a href="staff/manage-appointments.php" class="btn-wizard-nav btn-next" style="margin-top: 15px; display: inline-flex;">Staff Dashboard</a>
                    </div>
                    <?php else: ?>
                    <form id="bookingForm" enctype="multipart/form-data" onsubmit="return bookAppointment(event)">
                        <input type="hidden" id="service_id" name="service_id" required>
                        <input type="hidden" id="appointment_time" name="appointment_time" required>

                        <!-- STEP 1: SERVICE SELECTION CARDS -->
                        <div class="step-container active" id="step1">
                            <h2 class="section-header-title"><i class="fas fa-hand-holding-heart" style="color: var(--maroon);"></i> Choose Service Category</h2>
                            <p class="section-subtitle-muted">Select the specific assistance or citizen's charter service you need.</p>

                            <!-- Hidden Department Field (auto-set based on service selection) -->
                            <input type="hidden" id="office_department" name="office_department" value="Social Services / CSWDO">

                            <div class="services-cards-grid">
                                <!-- Card 1: Burial Assistance -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 7, 'AICS - Funeral/Burial Assistance', '1-3 days')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box burial">
                                            <i class="fas fa-cross"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <span class="service-pill-badge urgent">Priority</span>
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">Burial Assistance</h3>
                                        <p class="service-card-desc">Financial subsidy for funeral service contracts and burial expenses for a deceased family member.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-clock"></i> 1-3 days</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>

                                <!-- Card 2: Food & Transportation Assistance -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 10, 'AICS - Food Assistance', '1-2 days')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box food">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">Food & Transportation</h3>
                                        <p class="service-card-desc">Immediate food packs, relief subsidies, or transportation fare assistance during emergency situations.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-clock"></i> 1-2 days</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>

                                <!-- Card 3: Medical & Financial Assistance -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 6, 'AICS - Medical Assistance', '3-5 days')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box medical">
                                            <i class="fas fa-hospital-user"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <span class="service-pill-badge popular">Popular</span>
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">Medical & Financial Aid</h3>
                                        <p class="service-card-desc">Financial grants for hospital bills, doctor prescriptions, laboratory diagnostic tests, or emergency cash aid.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-clock"></i> 3-5 days</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>

                                <!-- Card 4: Housing Assistance -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 4, 'Housing Application - Akap Village', '5-7 days')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box housing">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">Housing Services</h3>
                                        <p class="service-card-desc">Subsidized housing programs including Akap Village shelter and San Lorenzo resettlement applications.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-clock"></i> 5-7 days</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>

                                <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                                <!-- Card 5: Senior Citizen Services -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 2, 'Provision of Identification Card and Purchase Booklet to Senior Citizens', 'Window 3 Express')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box senior">
                                            <i class="fas fa-person-cane"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <?php if (!empty($_SESSION['is_senior_citizen'])): ?>
                                            <span class="service-pill-badge urgent" style="background: rgba(46, 125, 50, 0.15); color: #2e7d32; border-color: rgba(46, 125, 50, 0.3); font-weight: 700;"><i class="fas fa-star"></i> Priority (60+)</span>
                                            <?php else: ?>
                                            <span class="service-pill-badge" style="background: rgba(2, 136, 209, 0.12); color: #0288D1; border-color: rgba(2, 136, 209, 0.25);">Window 3</span>
                                            <?php endif; ?>
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">Senior Citizen Services (OSCA)</h3>
                                        <p class="service-card-desc">Window 3 Express Priority Lane for OSCA ID issuance, purchase booklets (20% discount & VAT exemption), and senior social pensions.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-bolt"></i> Window 3 Express</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>

                                <!-- Card 6: PWD Services -->
                                <div class="service-card-item" role="radio" aria-checked="false" tabindex="0" onclick="selectServiceCard(this, 3, 'PWD Registration and Benefits', '3-5 days')">
                                    <div class="service-card-top">
                                        <div class="service-icon-box pwd">
                                            <i class="fas fa-wheelchair"></i>
                                        </div>
                                        <div class="service-card-badges">
                                            <span class="service-radio-ring"><i class="fas fa-check"></i></span>
                                        </div>
                                    </div>
                                    <div class="service-card-body">
                                        <h3 class="service-card-title">PWD Welfare Services</h3>
                                        <p class="service-card-desc">PWD identification card registration, assistive devices (wheelchairs, canes), and statutory welfare privileges.</p>
                                    </div>
                                    <div class="service-card-footer">
                                        <span class="service-duration-badge"><i class="fas fa-clock"></i> 3-5 days</span>
                                        <span class="service-status-text">Select</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Prerequisite Alert Box -->
                            <div class="dynamic-requirements-box" id="requirementsAlert">
                                <h4><i class="fas fa-file-circle-check"></i> Required Documents for this Service:</h4>
                                <ul id="requirementsList"></ul>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-next" id="btnNext1" disabled onclick="goToStep(2)">
                                    <span>Next: Schedule</span>
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 2: DATE AND SLOTS SELECTOR -->
                        <div class="step-container" id="step2">
                            <h2 class="section-header-title"><i class="fas fa-calendar-day" style="color: var(--maroon);"></i> Select Date & Time Slot</h2>
                            <p class="section-subtitle-muted">Choose your preferred visit date to check for available morning and afternoon appointment slots.</p>
                            
                            <div class="form-group" style="margin-bottom: 22px;">
                                <label for="appointment_date"><i class="fas fa-calendar-check"></i> Select Appointment Date</label>
                                <input type="date" id="appointment_date" name="appointment_date" class="form-control-modern" min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" onchange="loadAvailableSlots()">
                            </div>

                            <!-- Time Slots Segmented Block -->
                            <div class="time-slots-container-block" id="slotsBlock" style="display: none;">
                                <div class="slots-section-header"><i class="fas fa-sun" style="color: #D97706;"></i> Morning Sessions (AM)</div>
                                <div class="time-slots-segregated" id="amSlots"></div>

                                <div class="slots-section-header"><i class="fas fa-cloud-sun" style="color: #0284C7;"></i> Afternoon Sessions (PM)</div>
                                <div class="time-slots-segregated" id="pmSlots"></div>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-prev" onclick="goToStep(1)">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="btn-wizard-nav btn-next" id="btnNext2" disabled onclick="goToStep(3)">
                                    <span>Next: Confirmation</span>
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 3: DETAILS AND CONFIRM -->
                        <div class="step-container" id="step3">
                            <h2 class="section-header-title"><i class="fas fa-clipboard-check" style="color: var(--maroon);"></i> Review & Confirm Appointment</h2>
                            <p class="section-subtitle-muted">Double-check your client details, attach optional documents, and review your schedule before submission.</p>

                            <!-- Client Info Summary Box -->
                            <div class="summary-box" style="margin-bottom: 16px;">
                                <h4><i class="fas fa-user-circle" style="color: var(--maroon);"></i> Client Information</h4>
                                <div class="summary-row">
                                    <span class="summary-label">Full Name:</span>
                                    <span class="summary-value"><?php echo htmlspecialchars(trim(($resident_info['first_name'] ?? '') . ' ' . ($resident_info['last_name'] ?? '')) ?: $_SESSION['full_name']); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Email Address:</span>
                                    <span class="summary-value"><?php echo htmlspecialchars($resident_info['email'] ?? $_SESSION['email'] ?? 'Registered Resident'); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Contact Number:</span>
                                    <span class="summary-value"><?php echo htmlspecialchars($resident_info['contact_number'] ?? 'Not set'); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Barangay:</span>
                                    <span class="summary-value"><?php echo htmlspecialchars($resident_info['barangay'] ?? 'Palayan City Resident'); ?></span>
                                </div>
                            </div>

                            <!-- Review Summary Box -->
                            <div class="summary-box">
                                <h4><i class="fas fa-receipt" style="color: var(--maroon);"></i> Appointment Summary</h4>
                                <div class="summary-row">
                                    <span class="summary-label">Office / Department:</span>
                                    <span class="summary-value" id="summaryDept">City Social Welfare & Development Office</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Selected Service:</span>
                                    <span class="summary-value" id="summaryService">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Estimated Processing:</span>
                                    <span class="summary-value" id="summaryDuration">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Scheduled Date:</span>
                                    <span class="summary-value" id="summaryDate">-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Time Window:</span>
                                    <span class="summary-value" id="summaryTime">-</span>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 18px;">
                                <label for="purpose"><i class="fas fa-pencil-alt"></i> Purpose / Reason for Appointment <span class="required-star" style="color:#DC2626;">*</span></label>
                                <textarea id="purpose" name="purpose" rows="3" class="form-control-modern" style="height: auto; padding: 10px 14px;" placeholder="State your specific concern, assistance needed, or reason for scheduling this visit..." required oninput="toggleBookingSubmit()"></textarea>
                            </div>

                            <!-- Supporting Documents Upload -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="supporting_documents"><i class="fas fa-paperclip"></i> Upload Supporting Documents (Optional)</label>
                                <p style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 8px;">
                                    Upload scanned copies or photos of your Valid ID, Barangay Certificate, or medical/funeral contracts (PDF, JPG, PNG up to 10MB each).
                                </p>
                                <input type="file" id="supporting_documents" name="supporting_documents[]" multiple class="form-control-modern" style="padding-top: 8px;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            </div>

                            <!-- Document Confirmation Checklist -->
                            <div class="confirmation-checklist">
                                <input type="checkbox" id="confirmDocsCheck" onchange="toggleBookingSubmit()">
                                <label for="confirmDocsCheck">I confirm that all information provided is accurate and I understand that this appointment will initially be marked as <strong>PENDING</strong> for review by Palayan Social Service staff.</label>
                            </div>

                            <div class="wizard-actions">
                                <button type="button" class="btn-wizard-nav btn-prev" onclick="goToStep(2)">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back</span>
                                </button>
                                <button type="submit" class="btn-wizard-nav btn-next" id="bookBtn" disabled>
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Submit Request (Pending)</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Helpful Information Cards -->
            <div class="info-section">
                <div class="info-card">
                    <i class="fas fa-clock"></i>
                    <h3>Office Hours</h3>
                    <p>Monday - Friday<br>8:00 AM - 5:00 PM (Regular Working Days)</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-file-shield"></i>
                    <h3>Document Verification</h3>
                    <p>Bring the required original and photocopied documents listed in Step 1 to expedite your on-site processing.</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-bell"></i>
                    <h3>Status Notifications</h3>
                    <p>Automated reminders will be delivered to your verified phone number and in-app inbox prior to your booking.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal Dialog -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle" style="color: #DC2626;"></i> Cancel Appointment</h2>
                <button type="button" class="close-btn" onclick="closeModal('cancelModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this scheduled appointment?</p>
                <p style="color: #DC2626; font-weight: 700; font-size: 0.82rem;">
                    <i class="fas fa-circle-exclamation"></i> This action is permanent. If cancelled, your reserved time slot will be reopened to other residents.
                </p>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('cancelModal')" class="btn-wizard-nav btn-prev" style="padding: 8px 18px;">Keep Appointment</button>
                <button type="button" id="confirmCancelBtn" class="btn-small btn-cancel" style="padding: 8px 18px;">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <!-- Confirmation / Success Modal Dialog -->
    <div id="successModal" class="modal">
        <div class="modal-content" style="max-width: 520px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <div class="modal-body" style="padding: 16px 24px;">
                <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">Appointment Request Submitted!</h2>
                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 50px; background: #FEF3C7; color: #92400E; font-size: 0.78rem; font-weight: 700; margin-bottom: 16px;">
                    <i class="fas fa-clock"></i> PENDING • Waiting for Staff Review
                </div>
                
                <div style="background: var(--bg-subtle); border: 1.5px dashed var(--border-color); border-radius: 10px; padding: 14px; margin-bottom: 18px;">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 4px;">Appointment Reference Number</span>
                    <span id="confirmedRefPill" style="font-family: 'Courier Prime', monospace; font-size: 1.2rem; font-weight: 700; color: var(--maroon); letter-spacing: 0.05em;">PAL-SOC-2026-000000</span>
                </div>

                <p style="font-size: 0.84rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 12px;">
                    Ang inyong request ay naitala na sa ating sistema. Susuriin po ito ng Social Service Staff bago tuluyang aprubahan at kumpirmahin ang inyong schedule.
                </p>
                <div style="background: #EFF6FF; border-left: 4px solid #3B82F6; padding: 10px 14px; border-radius: 6px; font-size: 0.78rem; color: #1E40AF; text-align: left; margin-bottom: 20px;">
                    <strong>Paalala:</strong> Makakatanggap po kayo ng abiso sa inyong email at portal notifications kapag na-aprubahan na ang schedule upang makuha ang inyong <strong>Appointment Slip at QR Code</strong>.
                </div>

                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="my-appointments.php" class="btn-wizard-nav btn-next" style="margin-left:0; padding: 11px 22px;">
                        <i class="fas fa-calendar-check"></i> View in My Appointments
                    </a>
                    <button type="button" onclick="closeModal('successModal'); location.reload();" class="btn-wizard-nav btn-prev" style="padding: 11px 20px;">
                        <i class="fas fa-plus"></i> Book Another
                    </button>
                </div>
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
        if (step === 2 && !selectedServiceId) return;
        if (step === 3 && (!document.getElementById('appointment_date').value || !document.getElementById('appointment_time').value)) return;

        // Reset step visibility
        document.querySelectorAll('.step-container').forEach(cont => cont.classList.remove('active'));
        document.querySelectorAll('.stepper-step').forEach(hdr => hdr.classList.remove('active', 'completed'));
        document.querySelectorAll('.stepper-divider').forEach(div => div.classList.remove('completed'));

        // Show active step
        document.getElementById(`step${step}`).classList.add('active');
        document.getElementById(`stepHeader${step}`).classList.add('active');

        // Update indicators and dividers
        for (let i = 1; i <= 3; i++) {
            const iconEl = document.getElementById(`stepIcon${i}`);
            if (i < step) {
                document.getElementById(`stepHeader${i}`).classList.add('completed');
                iconEl.innerHTML = '<i class="fas fa-check"></i>';
                if (i === 1) document.getElementById('stepDiv1').classList.add('completed');
                if (i === 2) document.getElementById('stepDiv2').classList.add('completed');
            } else if (i === step) {
                iconEl.textContent = i;
            } else {
                iconEl.textContent = i;
            }
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

    // Select service card action
    function selectServiceCard(cardElement, serviceId, name, duration) {
        selectedServiceId = serviceId;
        selectedServiceName = name;
        selectedServiceDuration = duration;
        document.getElementById('service_id').value = serviceId;

        // Auto-assign department based on selected service
        let autoDept = 'Social Services / CSWDO';
        const lowerName = name.toLowerCase();
        if (lowerName.includes('aics') || lowerName.includes('burial') || lowerName.includes('funeral') || lowerName.includes('food') || lowerName.includes('medical')) {
            autoDept = 'AICS Division';
        } else if (lowerName.includes('housing') || lowerName.includes('akap') || lowerName.includes('resettlement')) {
            autoDept = 'Housing Division';
        } else if (lowerName.includes('senior')) {
            autoDept = 'Senior Citizens Affairs';
        } else if (lowerName.includes('pwd') || lowerName.includes('disability')) {
            autoDept = 'PWD Affairs';
        }
        const deptInput = document.getElementById('office_department');
        if (deptInput) deptInput.value = autoDept;
        const summaryDeptEl = document.getElementById('summaryDept');
        if (summaryDeptEl) summaryDeptEl.textContent = autoDept;

        // Reset all card selections
        document.querySelectorAll('.service-card-item').forEach(card => {
            card.classList.remove('selected');
            card.setAttribute('aria-checked', 'false');
            const statusText = card.querySelector('.service-status-text');
            if (statusText) statusText.textContent = 'Select';
        });

        // Highlight selected card
        const card = cardElement.classList.contains('service-card-item') ? cardElement : cardElement.closest('.service-card-item');
        if (card) {
            card.classList.add('selected');
            card.setAttribute('aria-checked', 'true');
            const statusText = card.querySelector('.service-status-text');
            if (statusText) statusText.textContent = 'Selected ✓';
        }

        // Dynamic Document Requirements loading
        const normName = name.toLowerCase();
        let matchedReqs = [];
        
        for (let key in serviceRequirementsMap) {
            if (normName.indexOf(key) !== -1 || key.indexOf(normName) !== -1) {
                matchedReqs = serviceRequirementsMap[key];
                break;
            }
        }

        const alertBox = document.getElementById('requirementsAlert');
        const listContainer = document.getElementById('requirementsList');
        if (alertBox && listContainer) {
            listContainer.innerHTML = '';
            const reqsToRender = (matchedReqs && matchedReqs.length > 0) 
                ? matchedReqs 
                : (serviceRequirementsMap['burial assistance'] || []);

            reqsToRender.forEach(req => {
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

        amSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;"><i class="fas fa-spinner fa-spin"></i> Loading slots...</div>';
        pmSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.8rem;"><i class="fas fa-spinner fa-spin"></i> Loading slots...</div>';
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

                        const hour = parseInt(slot.value.split(':')[0]);
                        if (hour < 12) {
                            amSlotsDiv.appendChild(slotButton);
                            hasAm = true;
                        } else {
                            pmSlotsDiv.appendChild(slotButton);
                            hasPm = true;
                        }
                    });

                    if (!hasAm) amSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.78rem;">No morning slots available</div>';
                    if (!hasPm) pmSlotsDiv.innerHTML = '<div style="color:var(--text-muted); font-size:0.78rem;">No afternoon slots available</div>';

                } else {
                    amSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.78rem;">Fully booked today</div>';
                    pmSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.78rem;">Fully booked today</div>';
                }
            })
            .catch(error => {
                console.error('Error loading time slots:', error);
                amSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.78rem;">Failed to load slots</div>';
                pmSlotsDiv.innerHTML = '<div style="color:var(--color-danger); font-size:0.78rem;">Failed to load slots</div>';
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

    // Department change handler
    function updateSelectedDepartment() {
        const deptEl = document.getElementById('office_department');
        if (!deptEl) return;
        const dept = deptEl.value;
        const summaryDeptEl = document.getElementById('summaryDept');
        if (summaryDeptEl) summaryDeptEl.textContent = dept;
    }

    // Submit Booking logic
    function bookAppointment(event) {
        event.preventDefault();
        const form = document.getElementById('bookingForm');
        const serviceId = document.getElementById('service_id').value;
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        const submitBtn = document.getElementById('bookBtn');

        if (!serviceId || !date || !time) {
            showNotification('warning', 'Incomplete Selection', 'Please select a service, date, and available time slot first.');
            return false;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Request...';

        const formData = new FormData(form);

        fetch('book-appointment-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (parseErr) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Server returned an unexpected response: ' + text.substring(0, 150));
                }
            });
        })
        .then(data => {
            if (data.success) {
                if (document.getElementById('confirmedRefPill')) {
                    document.getElementById('confirmedRefPill').textContent = data.reference_number || 'PAL-SOC-2026-PENDING';
                }
                openModal('successModal');
                form.reset();
            } else {
                const isDuplicate = data.message && data.message.includes('already have an active appointment');
                const isLimit = data.message && (data.message.includes('limit') || data.message.includes('puno na'));
                const notifType = (isDuplicate || isLimit) ? 'warning' : 'error';
                const notifTitle = isDuplicate ? 'Active Appointment Notice' : (isLimit ? 'Slot Limit Notice' : 'Booking Notice');
                showNotification(notifType, notifTitle, data.message || 'Booking submission failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Submit Appointment Request';
            }
        })
        .catch(error => {
            console.error('Error booking:', error);
            showNotification('error', 'Booking Error', error.message || 'A network error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Submit Appointment Request';
        });

        return false;
    }

    // Filter right-hand appointments feed (Upcoming vs History vs All)
    function filterAppointmentsFeed(type, tabBtn) {
        document.querySelectorAll('.feed-tab-btn').forEach(btn => btn.classList.remove('active'));
        tabBtn.classList.add('active');

        const cards = document.querySelectorAll('.appointment-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardType = card.getAttribute('data-type');
            if (type === 'all' || cardType === type) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const emptyMsg = document.getElementById('emptyFeedMessage');
        const emptyTitle = document.getElementById('emptyFeedTitle');
        const emptyDesc = document.getElementById('emptyFeedDesc');

        if (emptyMsg) {
            if (visibleCount === 0) {
                emptyMsg.style.display = 'block';
                if (type === 'upcoming') {
                    emptyTitle.textContent = 'No upcoming appointments';
                    emptyDesc.textContent = 'You have no active appointments scheduled. Select a service on the left to book a new appointment.';
                } else if (type === 'history') {
                    emptyTitle.textContent = 'No appointment history';
                    emptyDesc.textContent = 'You have no past, completed, or cancelled appointments recorded.';
                } else {
                    emptyTitle.textContent = 'No appointments found';
                    emptyDesc.textContent = 'You have no scheduled or historical visits recorded yet.';
                }
            } else {
                emptyMsg.style.display = 'none';
            }
        }
    }

    // Cancel appointment trigger
    function cancelAppointment(id) {
        currentAppointmentId = id;
        openModal('cancelModal');
    }

    document.getElementById('confirmCancelBtn')?.addEventListener('click', function() {
        if (!currentAppointmentId) return;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

        fetch('cancel-appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ appointment_id: currentAppointmentId })
        })
        .then(response => response.json())
        .then(data => {
            closeModal('cancelModal');
            if (data.success) {
                showNotification('success', 'Appointment Cancelled', 'Appointment cancelled successfully.');
                setTimeout(() => location.reload(), 1200);
            } else {
                showNotification('error', 'Cancellation Notice', data.message || 'Failed to cancel appointment');
                btn.disabled = false;
                btn.innerHTML = 'Yes, Cancel';
            }
        })
        .catch(error => {
            console.error('Error cancelling:', error);
            showNotification('error', 'Cancellation Error', 'An error occurred while cancelling.');
            btn.disabled = false;
            btn.innerHTML = 'Yes, Cancel';
        });
    });

    // ========== SLIDING CARD NOTIFICATION SYSTEM (Upper Right Corner) ==========
    function showNotification(type, title, message, duration = 7500) {
        if (window.showProcessToast) {
            window.showProcessToast(message, type === 'error' || type === 'warning', title);
            return;
        }

        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        // STRICTLY ONE SLIDING CARD: Remove any existing toast so only one sliding animation remains
        container.querySelectorAll('.toast-sliding-card').forEach(t => t.remove());

        const toast = document.createElement('div');
        toast.className = `toast-sliding-card ${type}`;

        const icons = {
            warning: 'fa-calendar-xmark',
            error: 'fa-circle-exclamation',
            success: 'fa-circle-check',
            info: 'fa-circle-info'
        };
        const iconClass = icons[type] || 'fa-bell';

        toast.innerHTML = `
            <div class="toast-card-icon">
                <i class="fas ${iconClass}"></i>
            </div>
            <div class="toast-card-content">
                <div class="toast-card-title">${title}</div>
                <div class="toast-card-message">${message}</div>
            </div>
            <button type="button" class="toast-card-close" aria-label="Close Notification" onclick="closeNotification(this)">
                <i class="fas fa-times"></i>
            </button>
            <div class="toast-card-progress" style="animation-duration: ${duration / 1000}s;"></div>
        `;

        container.appendChild(toast);

        const timer = setTimeout(() => {
            closeNotification(toast.querySelector('.toast-card-close'));
        }, duration);

        toast.dataset.timer = timer;
    }

    function closeNotification(btn) {
        if (!btn) return;
        const toast = btn.closest('.toast-sliding-card');
        if (toast) {
            if (toast.dataset.timer) {
                clearTimeout(Number(toast.dataset.timer));
            }
            toast.classList.add('hide');
            setTimeout(() => {
                toast.remove();
            }, 320);
        }
    }

    // Modernize all standard window.alert calls into upper-right sliding notifications
    window.alert = function(msg) {
        if (!msg) return;
        const str = String(msg);
        const isSuccess = str.toLowerCase().includes('success') || str.toLowerCase().includes('confirmed');
        const isError = str.toLowerCase().includes('error') || str.toLowerCase().includes('failed');
        const isWarning = str.toLowerCase().includes('already have an active appointment') || str.toLowerCase().includes('limit') || str.toLowerCase().includes('puno na');
        
        let type = 'info';
        let title = 'Notice';
        if (isSuccess) {
            type = 'success';
            title = 'Success';
        } else if (isWarning) {
            type = 'warning';
            title = 'Active Appointment Notice';
        } else if (isError) {
            type = 'error';
            title = 'Notice';
        }
        showNotification(type, title, str);
    };

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
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
        }
    });

    // Mobile sidebar toggle
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

    // Initial feed filter on page load (Default: upcoming if any exist, else all)
    document.addEventListener('DOMContentLoaded', function() {
        const upcomingCount = <?php echo count($upcoming_appointments); ?>;
        const defaultTab = upcomingCount > 0 ? 'upcoming' : 'all';
        const tabBtn = document.querySelector(`.feed-tab-btn[onclick*="'${defaultTab}'"]`);
        if (tabBtn) {
            filterAppointmentsFeed(defaultTab, tabBtn);
        }
    });

    // Theme toggle
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

    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>
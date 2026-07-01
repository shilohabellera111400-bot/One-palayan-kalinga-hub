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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            --bg-primary: #F8F9FC;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            --input-bg: #FFFFFF;
            --hero-bg: #0F414A;
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;
            --hero-bg: #0a0e17;
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ========== HERO SECTION ========== */
        .appointments-hero {
            background: var(--hero-bg);
            color: white;
            padding: 40px 0 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: background 0.3s ease;
        }
        .appointments-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.06;
            z-index: 0;
        }
        .appointments-hero .container { 
            position: relative; 
            z-index: 2; 
            max-width: 1280px; 
            margin: 0 auto; 
            padding: 0 24px; 
        }
        .appointments-hero h1 { 
            font-size: 2rem; 
            font-weight: 800; 
            margin-bottom: 12px; 
            letter-spacing: -0.5px;
        }
        .appointments-hero p { 
            font-size: 0.95rem; 
            max-width: 700px; 
            margin: 0 auto; 
            opacity: 0.85; 
        }

        /* ========== LAYOUT ========== */
        .dashboard-container { display: flex; min-height: 100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-info-sidebar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--maroon-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }
        .user-avatar-sidebar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info-sidebar h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .user-info-sidebar span {
            color: rgba(255,255,255,0.5);
            font-size: 0.65rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 14px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 0.95rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--divider);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }
        .main-content.full-width {
            margin-left: 0;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover { 
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: var(--color-success);
            border-left-color: var(--color-success);
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            border-left-color: var(--color-danger);
        }
        .alert i { font-size: 1.2rem; }

        /* ========== APPOINTMENTS GRID ========== */
        .appointments-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }

        /* ========== BOOKING SECTION ========== */
        .booking-section {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .booking-section .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .booking-section .section-header h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .booking-section .section-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }
        .section-subtitle {
            color: var(--text-muted);
            font-size: 0.82rem;
            margin-bottom: 20px;
        }

        .booking-form .form-group {
            margin-bottom: 18px;
        }
        .booking-form label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
        }
        .booking-form label i {
            color: var(--maroon-light);
            width: 20px;
            margin-right: 4px;
        }
        .booking-form select,
        .booking-form input,
        .booking-form textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        .booking-form select:focus,
        .booking-form input:focus,
        .booking-form textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        .time-slot {
            padding: 8px 12px;
            text-align: center;
            background: var(--badge-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .time-slot:hover:not(.disabled) {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
        }
        .time-slot.selected {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }
        .time-slot.disabled {
            background: rgba(244, 67, 54, 0.08);
            color: var(--color-danger);
            border-color: rgba(244, 67, 54, 0.15);
            cursor: not-allowed;
            opacity: 0.7;
        }
        .time-slot.available {
            background: rgba(76, 175, 80, 0.08);
            color: var(--color-success);
            border-color: rgba(76, 175, 80, 0.15);
        }

        .btn-book {
            width: 100%;
            padding: 12px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        .btn-book:hover:not(:disabled) {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-book:disabled {
            background: var(--badge-bg);
            cursor: not-allowed;
            color: var(--text-muted);
        }

        .service-info-box {
            margin: 12px 0;
            padding: 12px 16px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            border-left: 3px solid var(--maroon-light);
            display: none;
        }
        .service-info-box i {
            color: var(--maroon-light);
            margin-right: 6px;
        }
        .service-info-box small {
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        /* ========== MY APPOINTMENTS SECTION ========== */
        .my-appointments-section {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .my-appointments-section .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .my-appointments-section .section-header h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .my-appointments-section .section-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .appointments-list {
            margin-top: 16px;
        }
        .appointment-card {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 14px 18px;
            margin-bottom: 12px;
            border-left: 4px solid var(--midnight);
            transition: all 0.3s;
        }
        .appointment-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        .appointment-card.status-pending { border-left-color: var(--color-warning); }
        .appointment-card.status-confirmed { border-left-color: var(--color-success); }
        .appointment-card.status-completed { border-left-color: var(--color-info); }
        .appointment-card.status-cancelled { border-left-color: var(--color-danger); opacity: 0.7; }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .appointment-service {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .appointment-status {
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .appointment-status.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .appointment-status.confirmed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .appointment-status.completed {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .appointment-status.cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }

        .appointment-details {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        .detail-item i {
            color: var(--maroon-light);
            width: 14px;
            font-size: 0.7rem;
        }

        .appointment-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 5px 14px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            font-weight: 600;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }
        .btn-cancel {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
        }
        .btn-cancel:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-2px);
        }

        .no-appointments {
            text-align: center;
            padding: 40px 20px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            color: var(--text-muted);
        }
        .no-appointments i {
            font-size: 2.5rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 12px;
        }
        .no-appointments p {
            font-size: 0.85rem;
        }
        .no-appointments .hint {
            font-size: 0.75rem;
            margin-top: 8px;
        }
        .btn-login-prompt {
            display: inline-block;
            padding: 10px 24px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 12px;
        }
        .btn-login-prompt:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .view-all-link {
            text-align: center;
            margin-top: 16px;
        }
        .view-all-link a {
            color: var(--maroon-light);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
        }
        .view-all-link a:hover {
            text-decoration: underline;
        }

        /* ========== INFO SECTION ========== */
        .info-section {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .info-card {
            background: var(--card-bg);
            padding: 18px 20px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .info-card i {
            font-size: 1.6rem;
            color: var(--maroon-light);
            margin-bottom: 10px;
        }
        .info-card h3 {
            color: var(--text-primary);
            margin-bottom: 6px;
            font-size: 0.95rem;
            font-weight: 700;
        }
        .info-card p {
            color: var(--text-muted);
            font-size: 0.78rem;
            line-height: 1.5;
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
            max-width: 450px;
            width: 90%;
            transform: translateY(-20px);
            transition: transform 0.3s;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .modal.show .modal-content {
            transform: translateY(0);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .modal-header h2 {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-header h2 i {
            color: var(--maroon-light);
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .modal .text-danger {
            color: var(--color-danger);
            font-size: 0.8rem;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .appointments-grid { grid-template-columns: 1fr; }
            .info-section { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .form-row { grid-template-columns: 1fr; }
            .time-slots { grid-template-columns: repeat(3, 1fr); }
            .appointments-hero h1 { font-size: 1.5rem; }
            .appointments-hero p { font-size: 0.85rem; }
            .booking-section { padding: 18px 20px; }
            .my-appointments-section { padding: 18px 20px; }
            .info-section { grid-template-columns: 1fr; }
            .appointment-header { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .time-slots { grid-template-columns: repeat(2, 1fr); }
            .modal-content { padding: 20px; }
            .appointment-card { padding: 12px 14px; }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="appointments-hero">
        <div class="container">
            <h1>📅 Appointments</h1>
            <p>Schedule, manage, and track your appointments with CSWDO Palayan City. Choose a convenient time and service that fits your needs.</p>
        </div>
    </section>

    <div class="dashboard-container">
        <!-- Sidebar - ONLY SHOWN FOR LOGGED IN RESIDENTS -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Integrated Service Portal</span>
                </div>
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
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
                <li><a href="verify-resident.php"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
                <li><a href="appointments.php" class="active"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
                <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
                <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
                <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
                <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content <?php echo (!isLoggedIn() || isStaff() || isAdmin()) ? 'full-width' : ''; ?>">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-calendar-check" style="color: var(--maroon-light); margin-right: 10px;"></i> Appointments</h1>
                    <p>Book and manage your appointments with CSWDO</p>
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
                <!-- Booking Form Section -->
                <div class="booking-section">
                    <div class="section-header">
                        <i class="fas fa-calendar-plus"></i>
                        <h2>Book an Appointment</h2>
                    </div>
                    <p class="section-subtitle">Schedule your visit to CSWDO - it's quick and easy!</p>

                    <?php if (!isLoggedIn()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-sign-in-alt"></i>
                        <p>Please log in to book an appointment</p>
                        <a href="login.php" class="btn-login-prompt">Login to Continue</a>
                    </div>
                    <?php elseif (isStaff() || isAdmin()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-user-tie"></i>
                        <p>Staff members cannot book appointments as residents.</p>
                        <a href="staff-dashboard.php" class="btn-login-prompt">Go to Staff Dashboard</a>
                    </div>
                    <?php else: ?>
                    <form id="bookingForm" class="booking-form" onsubmit="return bookAppointment(event)">
                        <div class="form-group">
                            <label><i class="fas fa-concierge-bell"></i> Select Service <span style="color: var(--color-danger);">*</span></label>
                            <select id="service_id" name="service_id" required onchange="updateServiceInfo()">
                                <option value="">-- Choose a service --</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['service_id']; ?>" data-processing="<?php echo htmlspecialchars($service['processing_time']); ?>">
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Preferred Date <span style="color: var(--color-danger);">*</span></label>
                                <input type="date" id="appointment_date" name="appointment_date" 
                                       min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" 
                                       required onchange="loadAvailableSlots()">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Preferred Time <span style="color: var(--color-danger);">*</span></label>
                                <select id="appointment_time" name="appointment_time" required disabled>
                                    <option value="">-- Select date first --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-pencil-alt"></i> Purpose / Notes (Optional)</label>
                            <textarea id="purpose" name="purpose" rows="3" 
                                      placeholder="Tell us more about your appointment needs..."></textarea>
                        </div>

                        <div id="timeSlotsContainer" style="display: none;">
                            <label><i class="fas fa-clock"></i> Available Time Slots</label>
                            <div class="time-slots" id="timeSlots"></div>
                        </div>

                        <div class="service-info-box" id="serviceInfo">
                            <i class="fas fa-info-circle"></i>
                            <small>Processing time: <span id="processingTime"></span></small>
                        </div>

                        <button type="submit" class="btn-book" id="bookBtn" disabled>
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- My Appointments Section -->
                <div class="my-appointments-section">
                    <div class="section-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h2>My Appointments</h2>
                    </div>
                    <p class="section-subtitle">View and manage your scheduled appointments</p>

                    <?php if (!isLoggedIn()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-user-lock"></i>
                        <p>Please log in to view your appointments</p>
                        <a href="login.php" class="btn-login-prompt">Login to View</a>
                    </div>
                    <?php elseif (isStaff() || isAdmin()): ?>
                    <div class="no-appointments">
                        <i class="fas fa-user-tie"></i>
                        <p>Staff members do not have resident appointments.</p>
                        <a href="staff/manage-appointments.php" class="btn-login-prompt">Manage Appointments</a>
                    </div>
                    <?php elseif (empty($user_appointments)): ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-times"></i>
                        <p>You have no appointments scheduled yet</p>
                        <p class="hint">Book your first appointment using the form above!</p>
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
                                            ($appointment['status'] == 'confirmed' ? 'check-circle' : 
                                            ($appointment['status'] == 'completed' ? 'check-double' : 'times-circle')); 
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
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                <button onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)" class="btn-small btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="view-all-link">
                        <a href="my-appointments.php"><i class="fas fa-arrow-right"></i> View All Appointments</a>
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
                    <i class="fas fa-file-alt"></i>
                    <h3>Document Preparation</h3>
                    <p>Upload required documents before your appointment for faster processing</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-bell"></i>
                    <h3>Reminders</h3>
                    <p>You'll receive SMS and email reminders 24 hours before your appointment</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h2>
                <button class="close-btn" onclick="closeModal('cancelModal')">&times;</button>
            </div>
            <p>Are you sure you want to cancel this appointment?</p>
            <p class="text-danger"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
            <div class="modal-actions">
                <button onclick="closeModal('cancelModal')" class="btn-small btn-view">No, Keep It</button>
                <button id="confirmCancelBtn" class="btn-small btn-cancel">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-check-circle" style="color: var(--color-success);"></i> Success!</h2>
                <button class="close-btn" onclick="closeModal('successModal')">&times;</button>
            </div>
            <p id="successMessage">Your appointment has been booked successfully!</p>
            <div class="modal-actions" style="justify-content: center;">
                <button onclick="closeModal('successModal')" class="btn-book" style="width: auto; padding: 10px 30px;">OK</button>
            </div>
        </div>
    </div>

    <script>
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
    }

    setTheme(currentTheme);

    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });

    // ========== APPOINTMENT FUNCTIONS ==========
    let currentAppointmentId = null;

    function loadAvailableSlots() {
        const date = document.getElementById('appointment_date').value;
        const timeSelect = document.getElementById('appointment_time');
        const timeSlotsContainer = document.getElementById('timeSlotsContainer');
        const timeSlotsDiv = document.getElementById('timeSlots');
        const bookBtn = document.getElementById('bookBtn');

        if (!date) {
            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">-- Select date first --</option>';
            timeSlotsContainer.style.display = 'none';
            bookBtn.disabled = true;
            return;
        }

        timeSlotsDiv.innerHTML = '<div class="time-slot">Loading...</div>';
        timeSlotsContainer.style.display = 'block';

        fetch(`get-available-slots.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                timeSlotsDiv.innerHTML = '';
                if (data.slots && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const slotDiv = document.createElement('div');
                        slotDiv.className = `time-slot ${slot.available ? 'available' : 'disabled'}`;
                        slotDiv.textContent = slot.time;
                        if (slot.available) {
                            slotDiv.onclick = () => selectTimeSlot(slot.value, slot.time);
                        }
                        timeSlotsDiv.appendChild(slotDiv);
                    });
                    timeSelect.disabled = false;
                    timeSelect.innerHTML = '<option value="">-- Select time --</option>';
                    data.slots.forEach(slot => {
                        if (slot.available) {
                            const option = document.createElement('option');
                            option.value = slot.value;
                            option.textContent = slot.time;
                            timeSelect.appendChild(option);
                        }
                    });
                } else {
                    timeSlotsDiv.innerHTML = '<div class="time-slot disabled">No available slots</div>';
                    timeSelect.disabled = true;
                    timeSelect.innerHTML = '<option value="">-- No slots available --</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                timeSlotsDiv.innerHTML = '<div class="time-slot disabled">Error loading slots</div>';
            });
    }

    function selectTimeSlot(value, label) {
        document.getElementById('appointment_time').value = value;
        document.getElementById('bookBtn').disabled = false;
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
            if (slot.textContent === label) {
                slot.classList.add('selected');
            }
        });
    }

    function updateServiceInfo() {
        const select = document.getElementById('service_id');
        const selected = select.options[select.selectedIndex];
        const serviceInfo = document.getElementById('serviceInfo');
        const processingTime = document.getElementById('processingTime');
        if (selected.value) {
            const time = selected.getAttribute('data-processing') || 'Standard processing time';
            processingTime.textContent = time;
            serviceInfo.style.display = 'block';
        } else {
            serviceInfo.style.display = 'none';
        }
    }

    function bookAppointment(event) {
        event.preventDefault();
        const serviceId = document.getElementById('service_id').value;
        const date = document.getElementById('appointment_date').value;
        const time = document.getElementById('appointment_time').value;
        const purpose = document.getElementById('purpose').value;

        if (!serviceId || !date || !time) {
            alert('Please fill in all required fields');
            return false;
        }

        const bookBtn = document.getElementById('bookBtn');
        bookBtn.disabled = true;
        bookBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';

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
                document.getElementById('bookBtn').disabled = true;
                document.getElementById('timeSlotsContainer').style.display = 'none';
                document.getElementById('serviceInfo').style.display = 'none';
                setTimeout(() => location.reload(), 2000);
            } else {
                alert(data.message || 'Error booking appointment');
                bookBtn.disabled = false;
                bookBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Appointment';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            bookBtn.disabled = false;
            bookBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Appointment';
        });
        return false;
    }

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
                alert(data.message || 'Error cancelling appointment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    });

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
    </script>
</body>
</html>
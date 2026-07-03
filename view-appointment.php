<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$appointment_id) {
    redirect('my-appointments.php');
}

// Fetch appointment details for the logged-in user
$query = "SELECT a.*, s.service_name, s.description as service_description, s.duration_minutes,
          u.first_name, u.last_name, u.email, u.contact_number
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          JOIN users u ON a.user_id = u.user_id
          WHERE a.appointment_id = :id AND a.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $appointment_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('my-appointments.php?error=Appointment not found');
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get document attachments for this appointment
$doc_query = "SELECT * FROM documents WHERE appointment_id = :id ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':id', $appointment_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status color and icon
function getAppointmentStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => 'rgba(255, 152, 0, 0.12)'],
        'confirmed' => ['color' => '#2196F3', 'icon' => 'fa-check-circle', 'text' => 'Confirmed', 'bg' => 'rgba(33, 150, 243, 0.12)'],
        'completed' => ['color' => '#4CAF50', 'icon' => 'fa-check-double', 'text' => 'Completed', 'bg' => 'rgba(76, 175, 80, 0.12)'],
        'cancelled' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Cancelled', 'bg' => 'rgba(244, 67, 54, 0.12)']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => 'rgba(153, 153, 153, 0.12)'];
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            /* Light theme defaults */
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
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --search-bg: #FFFFFF;
            --stat-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
        }

        /* ========== DARK THEME ========== */
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
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --search-bg: #1a2332;
            --stat-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
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

        /* ========== LAYOUT ========== */
        .app-container { display: flex; min-height: 100vh; }

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
        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }
        .logo-image {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,0.10);
            padding: 4px;
            flex-shrink: 0;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .logo-text {
            flex: 1;
            min-width: 0;
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

        .sidebar-menu {
            list-style: none;
            padding: 16px 14px;
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
            font-size: 0.85rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 1rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 12px 0;
            padding-top: 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            padding-left: 14px;
        }

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
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }
        .sidebar-footer .avatar {
            width: 38px;
            height: 38px;
            background: var(--maroon-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.8rem;
        }
        .sidebar-footer .user-details h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-footer .user-details p {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
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
        .search-box {
            display: flex;
            align-items: center;
            background: var(--search-bg);
            border-radius: 50px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            gap: 10px;
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .search-box input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: transparent;
            min-width: 180px;
            color: var(--text-primary);
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { color: var(--text-muted); }

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

        /* ========== BREADCRUMB ========== */
        .breadcrumb {
            margin-bottom: 24px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--maroon-light);
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb i {
            margin: 0 6px;
        }

        /* ========== APPOINTMENT HEADER ========== */
        .appointment-header {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .appointment-title h1 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }
        .appointment-title .ref-number {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .appointment-title .ref-number strong {
            color: var(--text-primary);
        }
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
        }

        /* ========== INFO GRID ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .info-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .info-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .info-card .card-header h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
        }
        .info-card .card-header i {
            color: var(--maroon-light);
            font-size: 1.1rem;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--text-muted);
            flex-shrink: 0;
            font-size: 0.82rem;
        }
        .info-value {
            flex: 1;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .info-value strong {
            color: var(--text-primary);
        }

        /* ========== DOCUMENTS ========== */
        .documents-list {
            display: grid;
            gap: 10px;
        }
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        .document-item:hover {
            background: var(--bg-primary);
            border-color: var(--maroon-light);
        }
        .document-name {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .document-name i {
            font-size: 1.1rem;
            color: var(--maroon-light);
        }
        .btn-download {
            background: var(--maroon);
            color: white;
            padding: 6px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-download:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== NOTES ========== */
        .notes-section {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 16px 20px;
            border-left: 4px solid var(--maroon-light);
        }
        .notes-content {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .btn-secondary:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-print {
            background: #17a2b8;
            color: white;
        }
        .btn-print:hover {
            background: #138496;
        }
        .btn-cancel {
            background: #EF5350;
            color: white;
        }
        .btn-cancel:hover {
            background: #C62828;
        }
        .btn-cancel:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        .btn-success:hover {
            background: #388E3C;
        }

        /* ========== TOAST ========== */
        .toast-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            padding: 16px 24px;
            border-radius: var(--radius-element);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .toast-success { border-left-color: #4CAF50; }
        .toast-error { border-left-color: #EF5350; }
        .toast-success i { color: #4CAF50; }
        .toast-error i { color: #EF5350; }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-message.fade-out {
            animation: fadeOut 0.3s ease forwards;
        }
        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(100%); }
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.4;
            display: block;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .info-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .appointment-header { flex-direction: column; align-items: stretch; text-align: center; }
            .status-badge-large { align-self: center; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 4px; }
            .action-buttons { flex-direction: column; }
            .btn { justify-content: center; }
            .info-card { padding: 18px 20px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .appointment-header { padding: 16px 18px; }
            .info-card { padding: 14px 16px; }
        }
        @media print {
            .sidebar, .top-bar, .action-buttons, .breadcrumb, .theme-toggle { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
            .appointment-header { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <img src="../images/logo.png" alt="Palayan City Logo" class="logo-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-icon" style="display: none;"><i class="fas fa-crown"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Resident Portal</span>
                </div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li class="menu-divider">Services</li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php" class="active"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li class="menu-divider">Account</li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo $initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <p>Resident</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-calendar-check" style="color: var(--maroon-light); margin-right: 10px;"></i> Appointment Details</h1>
                <p>View your appointment information</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
            </div>
        </div>
        
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <a href="my-appointments.php">My Appointments</a>
            <i class="fas fa-chevron-right"></i>
            <span>Appointment #<?php echo $appointment_id; ?></span>
        </div>
        
        <?php $statusInfo = getAppointmentStatusInfo($appointment['status']); ?>
        
        <!-- Appointment Header -->
        <div class="appointment-header">
            <div class="appointment-title">
                <h1><?php echo htmlspecialchars($appointment['service_name']); ?></h1>
                <div class="ref-number">
                    <strong>Reference Number:</strong> #<?php echo str_pad($appointment_id, 6, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
            <div>
                <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>">
                    <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                    <?php echo $statusInfo['text']; ?>
                </span>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <!-- Service Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-concierge-bell"></i>
                    <h3>Service Information</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Service:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong></div>
                </div>
                <?php if(!empty($appointment['service_description'])): ?>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($appointment['service_description'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Duration:</div>
                    <div class="info-value"><?php echo $appointment['duration_minutes'] ?? '30'; ?> minutes</div>
                </div>
            </div>
            
            <!-- Appointment Details -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Appointment Details</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><strong><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong></div>
                </div>
                <?php if(!empty($appointment['purpose'])): ?>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($appointment['purpose'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Booked On:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['created_at'])); ?></div>
                </div>
            </div>
            
            <!-- Your Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Your Information</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Contact Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['contact_number']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></div>
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Status Information</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Current Status:</div>
                    <div class="info-value">
                        <span style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 14px; border-radius: 20px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                            <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                </div>
                <?php if($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                <div class="info-row">
                    <div class="info-label">Last Updated:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['updated_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Supporting Documents -->
        <div class="info-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <i class="fas fa-paperclip"></i>
                <h3>Supporting Documents</h3>
                <span style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 50px;"><?php echo count($documents); ?> file(s)</span>
            </div>
            <?php if(count($documents) > 0): ?>
                <div class="documents-list">
                    <?php foreach($documents as $doc): ?>
                    <div class="document-item">
                        <div class="document-name">
                            <i class="fas fa-file-<?php 
                                $ext = pathinfo($doc['document_path'] ?? $doc['file_name'] ?? '', PATHINFO_EXTENSION);
                                echo $ext == 'pdf' ? 'pdf' : ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' ? 'image' : 'alt');
                            ?>"></i>
                            <?php echo htmlspecialchars($doc['document_type'] ?? $doc['document_name'] ?? 'Document'); ?>
                            <span style="font-size: 0.65rem; color: var(--text-muted);">
                                <?php echo date('M d, Y', strtotime($doc['upload_date'] ?? 'now')); ?>
                            </span>
                        </div>
                        <a href="uploads/<?php echo $doc['document_path'] ?? $doc['file_path']; ?>" class="btn-download" target="_blank">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No documents attached to this appointment</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Staff Notes -->
        <?php if(!empty($appointment['notes'])): ?>
        <div class="info-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <i class="fas fa-sticky-note"></i>
                <h3>Staff Notes</h3>
            </div>
            <div class="notes-section">
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="my-appointments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Appointments
            </a>
            <?php if($appointment['status'] == 'pending'): ?>
            <button id="cancelAppointmentBtn" class="btn btn-cancel" data-id="<?php echo $appointment_id; ?>">
                <i class="fas fa-times-circle"></i> Cancel Appointment
            </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
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

// ========== TOAST NOTIFICATION ==========
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// ========== CANCEL APPOINTMENT ==========
document.addEventListener('DOMContentLoaded', function() {
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-id');
            
            if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                return;
            }
            
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
            fetch('cancel-appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ appointment_id: appointmentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> Cancel Appointment';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                cancelBtn.disabled = false;
                cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> Cancel Appointment';
            });
        });
    }
});

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'my-appointments.php?search=' + encodeURIComponent(query);
        }
    }
});
</script>
</body>
</html>
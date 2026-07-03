<?php
// dashboard.php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified (skip for staff and admin)
if (!isStaff() && !isAdmin()) {
    $check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
    $verify_stmt = $db->prepare($check_verified);
    $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $verify_stmt->execute();
    $user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_verify || $user_verify['verification_status'] != 'verified') {
        redirect('verify-resident.php');
    }
}

// Get user's appointments
$appointments_query = "SELECT a.*, s.service_name 
                      FROM appointments a 
                      JOIN services s ON a.service_id = s.service_id 
                      WHERE a.user_id = :user_id 
                      ORDER BY a.appointment_date DESC 
                      LIMIT 5";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':user_id', $_SESSION['user_id']);
$appointments_stmt->execute();

// Get user's AICS requests
$aics_query = "SELECT * FROM aics_requests 
               WHERE user_id = :user_id 
               ORDER BY submission_date DESC 
               LIMIT 5";
$aics_stmt = $db->prepare($aics_query);
$aics_stmt->bindParam(':user_id', $_SESSION['user_id']);
$aics_stmt->execute();

// Get notifications
$notif_query = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 5";
$notif_stmt = $db->prepare($notif_query);
$notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
$notif_stmt->execute();

// Mark notifications as read
$update_query = "UPDATE notifications SET is_read = TRUE 
                 WHERE user_id = :user_id AND is_read = FALSE";
$update_stmt = $db->prepare($update_query);
$update_stmt->bindParam(':user_id', $_SESSION['user_id']);
$update_stmt->execute();

// Get statistics counts
$count_query = "SELECT 
    (SELECT COUNT(*) FROM appointments WHERE user_id = :user_id) as total_appointments,
    (SELECT COUNT(*) FROM aics_requests WHERE user_id = :user_id) as total_aics,
    (SELECT COUNT(*) FROM housing_beneficiaries WHERE user_id = :user_id) as total_housing,
    (SELECT COUNT(*) FROM documents WHERE user_id = :user_id) as total_documents,
    (SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE) as unread_notifications";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':user_id', $_SESSION['user_id']);
$count_stmt->execute();
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

// Get user data
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = [
        'email' => '',
        'contact_number' => '',
        'address' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'profile_picture' => ''
    ];
}

// Get user's verification status
$verification_status = $user['verification_status'] ?? 'pending';

// Get initials for avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
    ? $profile_picture 
    : '';

// Check for dark mode preference
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
    <title>Dashboard - One Palayan-Kalinga Hub</title>
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
            --gold: #C9A86C;
            --gold-light: #E8D5A8;
            
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
            
            --stat-bg: #FFFFFF;
            --quick-bg: #EFE8DF;
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            --input-bg: #FFFFFF;
            --header-gradient-start: #0F414A;
            --header-gradient-end: #1a5a66;
            
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
            
            --stat-bg: #1a2332;
            --quick-bg: #1a2332;
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;
            --header-gradient-start: #0a0e17;
            --header-gradient-end: #1a2332;
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            min-height: 100vh;
            transition: background 0.4s ease, color 0.4s ease;
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
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.4s ease;
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
            animation: fadeInDown 0.6s ease;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 1.3rem;
            transition: transform 0.3s ease;
        }
        .logo-icon:hover {
            transform: rotate(-10deg) scale(1.05);
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
        .sidebar-close {
            display: none;
            background: none;
            border: none;
            color: var(--text-sidebar);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px;
            margin-left: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-close:hover {
            transform: rotate(90deg);
        }

        .user-info-sidebar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 14px;
            animation: fadeInDown 0.7s ease;
        }
        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--maroon-light), var(--maroon-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .user-avatar-sidebar:hover {
            transform: scale(1.05);
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .sidebar-menu li a i { 
            width: 20px; 
            font-size: 0.95rem; 
            color: rgba(255,255,255,0.4);
            transition: color 0.3s ease;
        }
        .sidebar-menu li a:hover { 
            background: var(--bg-sidebar-hover); 
            color: var(--text-sidebar-active);
            transform: translateX(4px);
        }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }
        .sidebar-menu li a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--gold);
            border-radius: 0 4px 4px 0;
        }
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 10px 0;
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
            animation: fadeInUp 0.8s ease;
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
            transition: all 0.3s ease;
        }
        .sidebar-footer a:hover { 
            background: rgba(255,255,255,0.10); 
            color: var(--text-sidebar-active);
            transform: translateX(4px);
        }

        /* ========== SIDEBAR OVERLAY (Mobile) ========== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* ========== MOBILE HAMBURGER ========== */
        .hamburger-btn {
            display: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 8px 12px;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 1.2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .hamburger-btn:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }
        .hamburger-btn span {
            font-size: 0.75rem;
            display: none;
        }
        @media (max-width: 768px) {
            .hamburger-btn span {
                display: inline;
            }
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.4s ease, margin-left 0.3s ease;
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .animate-fade-down {
            animation: fadeInDown 0.6s ease forwards;
        }
        .animate-fade-up {
            animation: fadeInUp 0.6s ease forwards;
        }
        .animate-fade-scale {
            animation: fadeInScale 0.6s ease forwards;
        }
        .animate-slide-right {
            animation: slideInRight 0.5s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
            animation: fadeInDown 0.5s ease;
        }
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .welcome-text h1 {
            font-size: 1.6rem;
            color: var(--text-primary);
            font-weight: 800;
            transition: color 0.3s ease;
            letter-spacing: -0.5px;
        }
        .welcome-text h1 .greeting-emoji {
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }
        .welcome-text p {
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .theme-toggle::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: var(--maroon);
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s ease;
        }
        .theme-toggle:hover::after {
            opacity: 0.1;
            transform: scale(1);
        }
        .theme-toggle:hover {
            color: var(--maroon);
            transform: rotate(30deg) scale(1.05);
            box-shadow: var(--shadow-md);
        }
        .notification-bell {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }
        .notification-bell:hover {
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px) scale(1.02);
        }
        .notification-bell .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--color-danger);
            color: white;
            font-size: 0.55rem;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            transform: translateY(-2px) scale(1.02);
        }

        /* ========== PROFILE HEADER ========== */
        .profile-header {
            background: linear-gradient(135deg, var(--header-gradient-start), var(--header-gradient-end));
            border-radius: var(--radius-card);
            padding: 32px 36px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            animation: fadeInScale 0.6s ease;
        }
        .profile-header::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        .profile-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.02);
            border-radius: 50%;
            pointer-events: none;
        }
        .profile-row {
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        .large-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
            border: 3px solid rgba(255,255,255,0.25);
            overflow: hidden;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: var(--maroon);
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }
        .large-avatar:hover {
            transform: scale(1.02);
        }
        .large-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-info h1 {
            color: white;
            font-size: 1.7rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .profile-info .details {
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }
        .profile-info .details i {
            width: 18px;
            color: var(--gold-light);
        }
        .profile-stats {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .profile-stat {
            background: rgba(255,255,255,0.08);
            padding: 6px 16px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .profile-stat:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }
        .profile-stat span {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .profile-stat small {
            color: rgba(255,255,255,0.6);
            font-size: 0.65rem;
        }
        .verification-badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-verified { background: var(--color-success); color: white; }
        .badge-pending { background: var(--color-warning); color: white; }
        .badge-rejected { background: var(--color-danger); color: white; }
        .edit-profile-btn {
            margin-left: auto;
            background: rgba(255,255,255,0.12);
            border: none;
            padding: 10px 22px;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            backdrop-filter: blur(10px);
        }
        .edit-profile-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px) scale(1.02);
            box-shadow: var(--shadow-sm);
        }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--stat-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease both;
        }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--maroon), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
            border-radius: var(--radius-card);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
        }
        .stat-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card:hover::before {
            opacity: 0.04;
        }
        .stat-card:hover::after {
            opacity: 1;
        }
        .stat-card:active {
            transform: scale(0.97);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }
        .stat-card:hover .stat-icon {
            transform: scale(1.05) rotate(-5deg);
        }
        .stat-icon.appointments { background: rgba(33, 150, 243, 0.12); color: var(--color-info); }
        .stat-icon.aics { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.housing { background: rgba(76, 175, 80, 0.12); color: var(--color-success); }
        .stat-icon.documents { background: rgba(255, 152, 0, 0.12); color: var(--color-warning); }
        .stat-info {
            position: relative;
            z-index: 1;
        }
        .stat-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }
        .stat-info p {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ========== QUICK ACTIONS ========== */
        .quick-section {
            background: var(--quick-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border-color);
            transition: background 0.4s ease, border-color 0.4s ease, box-shadow 0.3s ease;
            box-shadow: var(--shadow-sm);
            animation: fadeInUp 0.7s ease;
        }
        .quick-section:hover {
            box-shadow: var(--shadow-md);
        }
        .quick-section h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quick-section h3 i {
            color: var(--gold);
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .action-btn {
            background: var(--bg-card);
            padding: 10px 22px;
            border-radius: 50px;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .action-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--maroon);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .action-btn:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-3px) scale(1.02);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon);
        }
        .action-btn:hover::after {
            opacity: 0.05;
        }
        .action-btn:hover i { 
            color: white;
        }
        .action-btn i {
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }
        .action-btn span {
            position: relative;
            z-index: 1;
        }

        /* ========== TWO COLUMNS ========== */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.7s ease both;
        }
        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
            border-color: var(--maroon-light);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i {
            color: var(--gold);
        }
        .card-header a {
            color: var(--maroon-light);
            font-size: 0.7rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(127, 3, 3, 0.05);
        }
        .card-header a:hover {
            background: var(--maroon);
            color: white;
            transform: translateX(4px);
        }

        .item-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .list-item:last-child {
            border-bottom: none;
        }
        .list-item:hover {
            background: var(--badge-bg);
            margin: 0 -8px;
            padding: 10px 8px;
            border-radius: var(--radius-element);
            transform: translateX(4px);
        }
        .item-info h4 {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .item-info p {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .status-badge {
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }
        .status-badge:hover {
            transform: scale(1.05);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.confirmed {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.completed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }
        .status-badge.submitted {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.reviewing {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.released {
            background: rgba(0, 150, 136, 0.15);
            color: #009688;
        }
        .status-badge.qualified {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .amount-tag {
            font-weight: 700;
            color: var(--maroon-light);
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .list-item:hover .amount-tag {
            transform: scale(1.05);
            color: var(--maroon);
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
        }
        .empty-state i {
            font-size: 36px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .empty-state:hover i {
            opacity: 0.5;
            transform: scale(1.05) rotate(-5deg);
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.82rem;
        }
        .empty-state a {
            color: var(--maroon-light);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .empty-state a:hover {
            color: var(--maroon);
            text-decoration: underline;
        }

        /* ========== NOTIFICATIONS ========== */
        .notification-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.8s ease;
        }
        .notification-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }
        .notification-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .notification-card .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notification-card .card-header h3 i {
            color: var(--gold);
        }
        .notification-card .card-header a {
            color: var(--maroon-light);
            font-size: 0.7rem;
            text-decoration: none;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(127, 3, 3, 0.05);
            transition: all 0.3s ease;
        }
        .notification-card .card-header a:hover {
            background: var(--maroon);
            color: white;
            transform: translateX(4px);
        }
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .notif-item {
            padding: 12px 16px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            border-left: 3px solid var(--gold);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .notif-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--maroon), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: var(--radius-element);
        }
        .notif-item:hover {
            transform: translateX(6px);
            box-shadow: var(--shadow-sm);
        }
        .notif-item:hover::before {
            opacity: 0.03;
        }
        .notif-item h4 {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            position: relative;
            z-index: 1;
        }
        .notif-item p {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
            position: relative;
            z-index: 1;
        }
        .notif-item .time {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 4px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
            .profile-row { flex-direction: column; text-align: center; }
            .profile-stats { justify-content: center; }
            .edit-profile-btn { margin-left: 0; }
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
            .welcome-text h1 { font-size: 1.3rem; }
            .top-bar-actions { 
                justify-content: stretch; 
                flex-wrap: wrap;
                width: 100%;
            }
            .stats-grid { grid-template-columns: 1fr; }
            .profile-header { padding: 24px; }
            .large-avatar { width: 80px; height: 80px; font-size: 2rem; }
            .profile-info h1 { font-size: 1.3rem; }
            .profile-info .details { 
                font-size: 0.75rem; 
                gap: 10px; 
                flex-direction: column; 
                align-items: center; 
            }
            .action-buttons { flex-direction: column; }
            .action-btn { width: 100%; justify-content: center; }
            .dashboard-card { padding: 16px; }
            .notification-card { padding: 16px; }
            .stat-card { padding: 16px 18px; }
            .stat-icon { width: 44px; height: 44px; font-size: 1.1rem; }
            .hamburger-btn span { display: inline; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 14px; }
            .profile-header { padding: 16px; }
            .profile-stat { padding: 4px 12px; }
            .profile-stat span { font-size: 0.75rem; }
            .stat-card { padding: 14px 16px; }
            .stat-info h3 { font-size: 1.1rem; }
            .hamburger-btn { 
                padding: 6px 10px;
                font-size: 1rem;
            }
            .hamburger-btn span { font-size: 0.65rem; }
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
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
            <li><a href="verify-resident.php"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
            <li class="menu-divider">Services</li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li class="menu-divider"></li>
            <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
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
                <div class="welcome-text">
                    <h1>
                        <span class="greeting-emoji"><?php echo $greeting_emoji; ?></span>
                        Good <?php echo $greeting; ?>, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>!
                    </h1>
                    <p><?php echo date('l, F d, Y'); ?></p>
                </div>
            </div>
            <div class="top-bar-actions">
                <a href="notifications.php" class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <?php if(($counts['unread_notifications'] ?? 0) > 0): ?>
                        <span class="badge"><?php echo $counts['unread_notifications']; ?></span>
                    <?php endif; ?>
                </a>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="profile.php" class="top-icon"><i class="fas fa-user"></i></a>
            </div>
        </div>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-row">
                <div class="large-avatar">
                    <?php if(!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                    <div class="details">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></span>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['contact_number'] ?? 'Not provided'); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <i class="fas fa-calendar-alt" style="color: rgba(255,255,255,0.6);"></i>
                            <span><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                            <small>Member since</small>
                        </div>
                        <div class="profile-stat">
                            <span class="verification-badge badge-<?php echo $verification_status; ?>">
                                <i class="fas fa-<?php 
                                    echo $verification_status == 'verified' ? 'check-circle' : 
                                        ($verification_status == 'pending' ? 'clock' : 'times-circle'); 
                                ?>"></i>
                                <?php echo ucfirst($verification_status); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <a href="profile.php" class="edit-profile-btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='my-appointments.php'">
                <div class="stat-icon appointments"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $counts['total_appointments'] ?? 0; ?></h3>
                    <p>Appointments</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='aics-requests.php'">
                <div class="stat-icon aics"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-info">
                    <h3><?php echo $counts['total_aics'] ?? 0; ?></h3>
                    <p>AICS Requests</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='my-housing.php'">
                <div class="stat-icon housing"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3><?php echo $counts['total_housing'] ?? 0; ?></h3>
                    <p>Housing Apps</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='documents.php'">
                <div class="stat-icon documents"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $counts['total_documents'] ?? 0; ?></h3>
                    <p>Documents</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-section">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <a href="track-aics.php" class="action-btn"><i class="fas fa-chart-line"></i> <span>Track AICS</span></a>
                <a href="my-housing.php" class="action-btn"><i class="fas fa-home"></i> <span>My Housing Status</span></a>
                <a href="aics-requests.php" class="action-btn"><i class="fas fa-list"></i> <span>My AICS Requests</span></a>
                <a href="documents.php" class="action-btn"><i class="fas fa-upload"></i> <span>Upload Document</span></a>
                <a href="notifications.php" class="action-btn"><i class="fas fa-bell"></i> <span>Notifications</span></a>
            </div>
        </div>

        <!-- Two Columns: Appointments & AICS -->
        <div class="two-columns">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-week"></i> Recent Appointments</h3>
                    <a href="my-appointments.php">View all →</a>
                </div>
                <?php 
                $appointments_stmt->execute();
                if($appointments_stmt->rowCount() > 0): 
                ?>
                <div class="item-list">
                    <?php while($appointment = $appointments_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="list-item">
                        <div class="item-info">
                            <h4><?php echo htmlspecialchars($appointment['service_name']); ?></h4>
                            <p><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> • <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                        </div>
                        <span class="status-badge <?php echo strtolower($appointment['status']); ?>"><?php echo ucfirst($appointment['status']); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No appointments yet. <a href="appointments.php">Book one now</a></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-file-invoice"></i> AICS Requests</h3>
                    <a href="aics-requests.php">View all →</a>
                </div>
                <?php 
                $aics_stmt->execute();
                if($aics_stmt->rowCount() > 0): 
                ?>
                <div class="item-list">
                    <?php while($aics = $aics_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="list-item">
                        <div class="item-info">
                            <h4><?php echo ucfirst($aics['request_type']); ?></h4>
                            <p>Submitted: <?php echo date('M d, Y', strtotime($aics['submission_date'])); ?></p>
                        </div>
                        <span class="amount-tag">₱<?php echo number_format($aics['amount_requested'], 2); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No AICS requests yet. <a href="aics.php">Apply now</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <?php 
        $notif_stmt->execute();
        if($notif_stmt->rowCount() > 0): 
        ?>
        <div class="notification-card">
            <div class="card-header">
                <h3><i class="fas fa-bell"></i> Latest Notifications</h3>
                <a href="notifications.php">View all →</a>
            </div>
            <div class="notif-list">
                <?php 
                $notif_stmt->execute();
                $counter = 0;
                while($notif = $notif_stmt->fetch(PDO::FETCH_ASSOC)): 
                    if($counter >= 5) break;
                ?>
                <div class="notif-item">
                    <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                    <p><?php echo htmlspecialchars(substr($notif['message'], 0, 100)); ?>...</p>
                    <div class="time"><i class="far fa-clock"></i> <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></div>
                </div>
                <?php 
                $counter++;
                endwhile; 
                ?>
            </div>
        </div>
        <?php endif; ?>
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
document.addEventListener('keydown', function(e) {
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

themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
    
    themeToggle.style.transform = 'scale(0.9)';
    setTimeout(() => {
        themeToggle.style.transform = '';
    }, 200);
});

// ========== ANIMATED COUNTERS ==========
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-info h3');
    
    statNumbers.forEach((el, index) => {
        const target = parseInt(el.textContent);
        if (target > 0) {
            let current = 0;
            const increment = Math.ceil(target / 40);
            const duration = 800;
            const stepTime = Math.floor(duration / 40);
            
            const counter = setInterval(() => {
                current += increment;
                if (current >= target) {
                    el.textContent = target;
                    clearInterval(counter);
                } else {
                    el.textContent = current;
                }
            }, stepTime);
        }
    });
});

// ========== SMOOTH SCROLL ANIMATIONS ==========
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .dashboard-card, .notification-card');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }, observerOptions);
    
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});

// ========== STAT CARD CLICK FEEDBACK ==========
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(127, 3, 3, 0.3);
            width: 100px;
            height: 100px;
            margin-left: -50px;
            margin-top: -50px;
            top: ${e.offsetY}px;
            left: ${e.offsetX}px;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    });
});

const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ========== LIVE CLOCK UPDATE ==========
function updateClock() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    const dateStr = now.toLocaleDateString('en-US', options);
    const dateElement = document.querySelector('.welcome-text p');
    if (dateElement) {
        dateElement.textContent = dateStr;
    }
}

setInterval(updateClock, 1000);

// ========== KEYBOARD SHORTCUTS ==========
document.addEventListener('keydown', function(e) {
    // Ctrl + D for dashboard
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        window.location.href = 'dashboard.php';
    }
    // Ctrl + P for profile
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.location.href = 'profile.php';
    }
    // Ctrl + N for notifications
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'notifications.php';
    }
    // Escape to close sidebar on mobile
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

console.log('🚀 One Palayan-Kalinga Hub Dashboard loaded successfully!');
console.log('💡 Keyboard shortcuts: Ctrl+D (Dashboard), Ctrl+P (Profile), Ctrl+N (Notifications)');
console.log('📱 Mobile navigation: Click the hamburger menu (☰) to open sidebar');
</script>
</body>
</html>
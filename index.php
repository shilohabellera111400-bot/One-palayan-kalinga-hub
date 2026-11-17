<?php
// Include database configuration
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/cms-helper.php';

// Get real-time statistics for the dashboard
$database = new Database();
$db = $database->getConnection();

$stats_query = "SELECT 
    (SELECT COUNT(*) FROM appointments) as total_appointments,
    (SELECT COUNT(*) FROM aics_requests) as total_aics,
    (SELECT COUNT(*) FROM housing_beneficiaries WHERE status = 'approved') as total_housing_beneficiaries,
    (SELECT COUNT(*) FROM users WHERE role = 'resident') as total_residents,
    (SELECT AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 FROM appointments) as satisfaction_rate";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get latest appointments and AICS requests for logged-in users
$latest_appointments = [];
$latest_aics = [];

if (isLoggedIn()) {
    $appointments_query = "SELECT a.*, s.service_name 
                          FROM appointments a 
                          JOIN services s ON a.service_id = s.service_id 
                          WHERE a.user_id = :user_id 
                          ORDER BY a.appointment_date DESC 
                          LIMIT 3";
    $appointments_stmt = $db->prepare($appointments_query);
    $appointments_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $appointments_stmt->execute();
    $latest_appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $aics_query = "SELECT * FROM aics_requests 
                   WHERE user_id = :user_id 
                   ORDER BY submission_date DESC 
                   LIMIT 3";
    $aics_stmt = $db->prepare($aics_query);
    $aics_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $aics_stmt->execute();
    $latest_aics = $aics_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user data for navbar if logged in
$user_data = null;
if (isLoggedIn()) {
    $user_query = "SELECT * FROM users WHERE user_id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get initials for avatar
$initials = '';
if (isLoggedIn()) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    foreach($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(cms_get('site_title', 'One Palayan-Kalinga Hub')); ?> - <?php echo htmlspecialchars(cms_get('site_tagline', 'CSWDO Palayan City')); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --light-blue: #96C0CE;
            --alabaster: #EFE8DF;
            
            --bg-primary: #F8F9FC;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --radius-card: 24px;
            --radius-element: 16px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-card: #1a2332;
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --border-color: #2a3a4f;
            --alabaster: #1a2332;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            transition: background 0.4s ease, color 0.4s ease;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .section-shell {
            position: relative;
            overflow: hidden;
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes slowZoom {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        @keyframes bounceUp {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        @keyframes ripple {
            to { transform: scale(4); opacity: 0; }
        }

        .animate-fade-down { animation: fadeInDown 0.6s ease forwards; }
        .animate-fade-up { animation: fadeInUp 0.6s ease forwards; }
        .animate-fade-scale { animation: fadeInScale 0.6s ease forwards; }
        .animate-slide-right { animation: slideInRight 0.5s ease forwards; }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

        /* ========== SCROLL UP BUTTON ========== */
        .scroll-up-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 999;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(127,3,3,0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px) scale(0.8);
        }

        .scroll-up-btn.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .scroll-up-btn:hover {
            background: var(--maroon-light);
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 6px 25px rgba(127,3,3,0.4);
        }

        .scroll-up-btn i {
            font-size: 1.2rem;
            animation: bounceUp 1.5s infinite;
        }



        /* ========== HERO SECTION (Fills entire screen down to bottom) ========== */
        .hero {
            position: relative;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 130px 0 70px;
            overflow: hidden;
            background: var(--midnight);
            margin-top: 0;
            box-sizing: border-box;
        }
        .hero .container {
            width: 100%;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/palayan-bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.50;
            z-index: 0;
            animation: slowZoom 20s ease-in-out infinite alternate;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15,65,74,0.85) 0%, rgba(15,65,74,0.78) 100%);
            z-index: 0;
        }

        .hero .container { position: relative; z-index: 2; }
        .hero-wrapper { display: flex; align-items: center; gap: 50px; }
        .hero-content { flex: 1; }
        .hero-content .badge {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            padding: 4px 16px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--gold-light);
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
        }
        .hero-content h1 { 
            font-size: 1rem; 
            font-weight: 500; 
            margin-bottom: 10px; 
            color: var(--light-blue); 
            letter-spacing: 1px; 
            text-transform: uppercase;
        }
        .hero-content h2 { 
            font-size: 3.2rem; 
            font-weight: 800; 
            line-height: 1.1; 
            margin-bottom: 20px; 
            color: white; 
            max-width: 640px;
            letter-spacing: -1px;
        }
        .hero-content h2 span {
            color: var(--gold-light);
        }
        .hero-content p { 
            font-size: 1.05rem; 
            margin-bottom: 30px; 
            opacity: 0.9; 
            max-width: 560px; 
            color: rgba(255,255,255,0.92); 
            line-height: 1.7;
        }
        .hero-buttons { 
            display: flex; 
            gap: 16px; 
            margin-bottom: 30px; 
            flex-wrap: wrap; 
        }
        .btn-primary {
            display: inline-block;
            padding: 14px 32px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .btn-primary:hover { 
            background: var(--maroon-light); 
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(127,3,3,0.35);
        }
        .btn-primary:hover::after {
            opacity: 1;
        }
        .btn-secondary {
            display: inline-block;
            padding: 14px 32px;
            background: transparent;
            color: white;
            text-decoration: none;
            border: 2px solid var(--light-blue);
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-secondary:hover { 
            background: var(--light-blue); 
            color: var(--midnight); 
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(150,192,206,0.3);
        }
        .hero-image { flex: 1; text-align: center; }
        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 24px;
            box-shadow: 0 24px 50px rgba(0,0,0,0.28);
            animation: float 3.2s ease-in-out infinite;
            border: 3px solid rgba(255,255,255,0.2);
        }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        .status-card {
            margin-top: 30px;
            background: rgba(255,255,255,0.95);
            border-radius: 18px;
            padding: 16px 20px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--maroon);
            max-width: 390px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            user-select: none;
            backdrop-filter: blur(8px);
        }
        .status-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-lg);
            border-left-color: var(--gold);
        }
        .status-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dashed rgba(0,0,0,0.06);
        }
        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .live-dot {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: livePulse 2s infinite;
        }
        @keyframes livePulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        .live-time {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .status-card-content { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            transition: opacity 0.28s ease, transform 0.28s ease;
        }
        .status-card-content.status-fade-out {
            opacity: 0;
            transform: translateY(-5px);
        }
        .status-card-content.status-fade-in {
            opacity: 1;
            transform: translateY(0);
        }
        .status-icon {
            background: rgba(127,3,3,0.1);
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .status-icon i { color: var(--maroon); font-size: 1.4rem; transition: color 0.3s ease; }
        .status-icon.status-icon-released,
        .status-icon.status-icon-approved {
            background: rgba(16, 185, 129, 0.14);
        }
        .status-icon.status-icon-released i,
        .status-icon.status-icon-approved i {
            color: #059669;
        }
        .status-icon.status-icon-reviewing {
            background: rgba(59, 130, 246, 0.14);
        }
        .status-icon.status-icon-reviewing i {
            color: #2563eb;
        }
        .status-icon.status-icon-confirmed,
        .status-icon.status-icon-completed {
            background: rgba(127, 3, 3, 0.1);
        }
        .status-icon.status-icon-confirmed i,
        .status-icon.status-icon-completed i {
            color: var(--maroon);
        }
        .status-text {
            flex: 1;
            min-width: 0;
        }
        .status-text h4 { 
            color: var(--midnight); 
            margin-bottom: 4px; 
            font-size: 0.88rem; 
            font-weight: 700; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .status-text p { 
            color: var(--text-secondary); 
            margin: 0; 
            font-weight: 500; 
            font-size: 0.84rem; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            flex-wrap: wrap; 
        }
        .status-text strong { 
            color: var(--maroon); 
            font-weight: 700; 
            font-size: 0.85rem; 
        }
        .status-pill {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.4;
        }
        .status-pill.status-released {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pill.status-approved {
            background: #dcfce7;
            color: #15803d;
        }
        .status-pill.status-reviewing {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-pill.status-confirmed {
            background: #fef3c7;
            color: #92400e;
        }
        .status-pill.status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-pill.status-submitted {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: rgba(255,255,255,0.95);
            padding: 14px 20px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            min-width: 140px;
            border: 1px solid rgba(127,3,3,0.08);
            transition: all 0.3s ease;
            backdrop-filter: blur(8px);
        }
        .stat-box:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .stat-number { 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: var(--midnight); 
            display: block; 
            transition: color 0.3s ease, transform 0.3s ease;
        }
        .stat-number.stat-updated {
            animation: statPulse 0.7s ease;
        }
        @keyframes statPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); color: #059669; }
            100% { transform: scale(1); }
        }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; font-weight: 500; }

        /* Dark mode adaptions for status card & stats */
        [data-theme="dark"] .status-card,
        [data-theme="dark"] .stat-box {
            background: rgba(26, 35, 50, 0.95);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45);
        }
        [data-theme="dark"] .status-card-header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }
        [data-theme="dark"] .status-text h4 {
            color: #E8EDF5;
        }
        [data-theme="dark"] .status-text p {
            color: #B0C4DE;
        }
        [data-theme="dark"] .stat-number {
            color: #E8EDF5;
        }
        [data-theme="dark"] .status-pill.status-released {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }
        [data-theme="dark"] .status-pill.status-approved {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        [data-theme="dark"] .status-pill.status-reviewing {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }
        [data-theme="dark"] .status-pill.status-confirmed {
            background: rgba(245, 158, 11, 0.2);
            color: #fde68a;
        }

        /* ========== CSWDO STATS SECTION ========== */
        .cswdo-stats-section {
            padding: 84px 0;
            background: linear-gradient(180deg, #F8F9FC 0%, #FFFFFF 100%);
            position: relative;
        }
        .stats-grid-large {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            text-align: center;
        }
        .stat-card-large {
            background: #FFFFFF;
            padding: 38px 24px;
            border-radius: 24px;
            box-shadow: 0 8px 24px rgba(15, 65, 74, 0.06);
            border: 1.5px solid #E8ECF2;
            position: relative;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: fadeInUp 0.6s ease both;
        }
        .stat-card-large:nth-child(1) { border-top: 5px solid #7F0303; animation-delay: 0.1s; }
        .stat-card-large:nth-child(2) { border-top: 5px solid #0284C7; animation-delay: 0.2s; }
        .stat-card-large:nth-child(3) { border-top: 5px solid #D97706; animation-delay: 0.3s; }
        .stat-card-large:nth-child(4) { border-top: 5px solid #10B981; animation-delay: 0.4s; }

        .stat-card-large:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 36px rgba(15, 65, 74, 0.12);
        }
        .stat-card-large .stat-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            transition: all 0.3s ease;
        }
        .stat-card-large:nth-child(1) .stat-icon-wrap { background: rgba(127, 3, 3, 0.08); color: #7F0303; }
        .stat-card-large:nth-child(2) .stat-icon-wrap { background: rgba(2, 132, 199, 0.08); color: #0284C7; }
        .stat-card-large:nth-child(3) .stat-icon-wrap { background: rgba(217, 119, 6, 0.08); color: #D97706; }
        .stat-card-large:nth-child(4) .stat-icon-wrap { background: rgba(16, 185, 129, 0.08); color: #10B981; }

        .stat-card-large:hover .stat-icon-wrap {
            transform: scale(1.12) rotate(-5deg);
        }
        .stat-card-large .stat-number-large {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            color: #1E293B;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .stat-card-large .stat-label-large {
            font-size: 0.88rem;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ========== HOUSING PROGRAM SECTION (LANDSCAPE THEATER SHOWCASE) ========== */
        .housing-program-section {
            padding: 84px 0;
            background: var(--alabaster);
            transition: background 0.4s ease;
        }
        .housing-landscape-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .housing-header-landscape {
            text-align: center;
            max-width: 780px;
            margin: 0 auto 36px;
        }
        .housing-header-landscape .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .housing-header-landscape h2 {
            font-size: 2.35rem;  
            font-weight: 800;
            color: var(--maroon);
            margin-bottom: 14px;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .housing-header-landscape p {
            color: var(--text-muted);
            font-size: 1.02rem;
            line-height: 1.65;
            margin: 0 auto;
        }

        /* 16:9 Cinema Landscape Video Wrapper */
        .housing-video-theater {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            min-height: 420px;
            max-height: 600px;
            border-radius: 28px;
            overflow: hidden;
            background: #061518;
            box-shadow: 0 24px 60px rgba(15, 65, 74, 0.22), 0 4px 16px rgba(0, 0, 0, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.9);
            margin-bottom: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
        }
        .housing-video-theater:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 70px rgba(15, 65, 74, 0.28), 0 6px 20px rgba(0, 0, 0, 0.12);
        }
        .housing-video-element {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .housing-video-pill {
            position: absolute;
            top: 18px;
            left: 20px;
            background: rgba(15, 65, 74, 0.82);
            color: #FFFFFF;
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            z-index: 2;
            border: 1px solid rgba(255, 255, 255, 0.2);
            pointer-events: none;
        }
        .housing-video-pill span.live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 8px #10B981;
        }

        /* 3-Column Landscape Highlights Grid */
        .housing-features-landscape {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .housing-feature {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 22px 20px;
            border-radius: 20px;
            background: #FFFFFF;
            border: 1.5px solid rgba(15, 65, 74, 0.08);
            box-shadow: 0 4px 16px rgba(15, 65, 74, 0.04);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
        }
        .housing-feature:hover {
            background: #FFFFFF;
            transform: translateY(-5px);
            border-color: rgba(127, 3, 3, 0.3);
            box-shadow: 0 12px 28px rgba(127, 3, 3, 0.1);
        }
        .housing-feature i {
            font-size: 1.6rem;
            color: var(--maroon);
            background: rgba(127, 3, 3, 0.08);
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .housing-feature:hover i {
            transform: scale(1.1) rotate(-5deg);
        }
        .housing-feature h4 {
            color: var(--text-primary);
            margin-bottom: 6px;
            font-size: 1.05rem;
            font-weight: 700;
        }
        .housing-feature p {
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.55;
            margin: 0;
        }
        @media (max-width: 900px) {
            .housing-features-landscape {
                grid-template-columns: 1fr;
            }
            .housing-video-theater {
                min-height: 280px;
            }
            .housing-header-landscape h2 {
                font-size: 1.8rem;
            }
        }

        /* ========== TRANSPARENCY SECTION ========== */
        .transparency-section {
            padding: 84px 0;
            background: var(--bg-primary);
            transition: background 0.4s ease;
        }
        .transparency-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 50px;
        }
        .transparency-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        .transparency-header p {
            color: var(--text-muted);
        }
        .process-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 50px;
        }
        .process-step {
            text-align: center;
            position: relative;
            background: #FFFFFF;
            border: 1.5px solid #E8ECF2;
            border-radius: 22px;
            padding: 30px 22px;
            box-shadow: 0 6px 20px rgba(15, 65, 74, 0.05);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: fadeInUp 0.6s ease both;
        }
        .process-step:nth-child(1) { border-top: 4px solid #7F0303; animation-delay: 0.1s; }
        .process-step:nth-child(2) { border-top: 4px solid #0284C7; animation-delay: 0.2s; }
        .process-step:nth-child(3) { border-top: 4px solid #D97706; animation-delay: 0.3s; }
        .process-step:nth-child(4) { border-top: 4px solid #10B981; animation-delay: 0.4s; }

        .process-step:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 36px rgba(15, 65, 74, 0.12);
        }
        .process-step:not(:last-child):after {
            content: "→";
            position: absolute;
            right: -20px;
            top: 36px;
            font-size: 1.4rem;
            color: #7F0303;
            font-weight: 800;
            animation: float 2s infinite;
        }
        .step-number {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
            color: #FFFFFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 auto 18px;
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25);
            transition: all 0.3s ease;
        }
        .process-step:hover .step-number {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 4px 15px rgba(127,3,3,0.3);
        }
        .process-step h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .process-step p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .tracking-demo {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 30px;
            max-width: 520px;
            margin: 0 auto;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }
        .tracking-demo:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .tracking-demo i {
            font-size: 3rem;
            color: var(--maroon);
            margin-bottom: 15px;
        }
        .tracking-demo .status-updated {
            background: rgba(76, 175, 80, 0.12);
            color: var(--color-success);
            padding: 12px;
            border-radius: var(--radius-element);
            margin-top: 20px;
            font-weight: 600;
            border-left: 3px solid var(--color-success);
        }

        /* ========== NOTIFICATION SECTION ========== */
        .notification-section {
            padding: 84px 0;
            background: var(--bg-card);
            transition: background 0.4s ease;
        }
        .notification-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }
        .notification-text h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        .notification-text p {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        .notification-channels {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .notification-channel {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 18px 20px;
            background: #FFFFFF;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1.5px solid #E8ECF2;
            box-shadow: 0 4px 16px rgba(15, 65, 74, 0.04);
            cursor: pointer;
        }
        .notification-channel:hover {
            transform: translateX(6px) translateY(-3px);
            box-shadow: 0 12px 28px rgba(15, 65, 74, 0.08);
            border-color: rgba(127, 3, 3, 0.25);
        }
        .notification-channel i {
            font-size: 1.6rem;
            color: var(--maroon);
            background: rgba(127, 3, 3, 0.08);
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .notification-channel:hover i {
            transform: scale(1.1) rotate(-5deg);
        }
        .notification-channel h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        .notification-channel p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }
        .notification-channel small {
            color: var(--color-success);
            font-size: 0.7rem;
            display: inline-block;
            margin-top: 5px;
        }
        .notification-preview {
            background: linear-gradient(135deg, var(--midnight), var(--light-blue));
            border-radius: 28px;
            padding: 30px;
            color: white;
            box-shadow: 0 20px 44px rgba(15,65,74,0.16);
            transition: all 0.3s ease;
        }
        .notification-preview:hover {
            transform: scale(1.01);
        }
        .notification-preview h3 {
            margin-bottom: 20px;
        }
        .preview-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .preview-item:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        .preview-item p {
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .preview-item small {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        /* ========== DOCUMENT UPLOAD SECTION ========== */
        .document-upload-section {
            padding: 84px 0;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .document-upload-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        .document-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        .document-text h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
        }
        .document-text p {
            opacity: 0.9;
            margin-bottom: 25px;
        }
        .document-features {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .document-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 12px;
        }
        .document-feature:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .document-feature i {
            font-size: 1.2rem;
            color: var(--gold-light);
            transition: all 0.3s ease;
        }
        .document-feature:hover i {
            transform: scale(1.2);
        }
        .document-feature span {
            font-size: 0.9rem;
        }
        .secure-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 16px;
            border-radius: 40px;
            font-size: 0.7rem;
            margin-bottom: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .document-btn {
            display: inline-block;
            background: white;
            color: var(--maroon);
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 25px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .document-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .document-image {
            text-align: center;
        }
        .document-image i {
            font-size: 10rem;
            opacity: 0.3;
            transition: all 0.3s ease;
        }
        .document-image:hover i {
            transform: scale(1.05) rotate(-5deg);
            opacity: 0.5;
        }

        /* ========== SERVICES SECTION ========== */
        .services {
            padding: 84px 0;
            background: var(--bg-primary);
            transition: background 0.4s ease;
        }
        .section-title {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 50px;
            color: var(--text-primary);
        }
        .section-title span {
            display: block;
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 400;
            margin-top: 8px;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        .service-card {
            background: #FFFFFF;
            padding: 34px 28px;
            border-radius: 24px;
            box-shadow: 0 6px 20px rgba(15, 65, 74, 0.05);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1.5px solid #E8ECF2;
            animation: fadeInUp 0.6s ease both;
            position: relative;
            overflow: hidden;
        }
        .service-card:nth-child(1) { border-top: 5px solid #7F0303; animation-delay: 0.1s; }
        .service-card:nth-child(2) { border-top: 5px solid #0284C7; animation-delay: 0.2s; }
        .service-card:nth-child(3) { border-top: 5px solid #D97706; animation-delay: 0.3s; }
        .service-card:nth-child(4) { border-top: 5px solid #10B981; animation-delay: 0.4s; }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 36px rgba(15, 65, 74, 0.12);
        }
        .service-card h3 { 
            color: var(--text-primary); 
            margin-bottom: 15px; 
            font-size: 1.25rem; 
            font-weight: 700; 
        }
        .service-card p { 
            color: var(--text-muted); 
            margin-bottom: 20px; 
            line-height: 1.7; 
        }
        .learn-more { 
            color: var(--maroon); 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .learn-more:hover { 
            color: var(--maroon-light); 
            transform: translateX(5px);
        }

        /* ========== CTA SECTION ========== */
        .cta-section {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            bottom: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        .cta-section h2 { 
            font-size: 2.2rem; 
            font-weight: 800; 
            margin-bottom: 20px; 
        }
        .cta-section p { 
            font-size: 1rem; 
            max-width: 600px; 
            margin: 0 auto 40px; 
            opacity: 0.9; 
        }
        .cta-buttons { 
            display: flex; 
            gap: 20px; 
            justify-content: center; 
            flex-wrap: wrap; 
        }
        .cta-section .btn-primary { 
            background: var(--maroon);
            padding: 14px 36px;
        }
        .cta-section .btn-primary:hover { 
            background: var(--maroon-light); 
        }
        .cta-section .btn-secondary { 
            border-color: white; 
            color: white;
            padding: 14px 36px;
        }
        .cta-section .btn-secondary:hover { 
            background: white; 
            color: var(--midnight); 
        }

        /* ========== FOOTER ========== */
        .footer {
            background: var(--midnight);
            color: white;
            padding: 60px 0 20px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-section h3, .footer-section h4 { margin-bottom: 20px; font-weight: 700; }
        .footer-section h3 { color: var(--tan); font-size: 1.2rem; }
        .footer-section h4 { color: var(--light-blue); font-size: 1rem; }
        .footer-section p { color: rgba(255,255,255,0.7); margin-bottom: 15px; font-size: 0.85rem; }
        .footer-section ul { list-style: none; }
        .footer-section ul li { margin-bottom: 10px; }
        .footer-section a { 
            color: rgba(255,255,255,0.6); 
            text-decoration: none; 
            transition: all 0.3s ease; 
            font-size: 0.85rem;
        }
        .footer-section a:hover { 
            color: var(--tan); 
            transform: translateX(4px);
        }
        .contact-number, .email { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); font-size: 0.85rem; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.75rem; }

        /* ========== RESPONSIVE & MOBILE SMALL UI/UX ========== */
        .mobile-bottom-nav {
            display: none; /* Hidden on desktop */
        }

        @media (max-width: 968px) {
            .hero-wrapper { flex-direction: column-reverse; gap: 30px; }
            .hero-content h2 { font-size: 1.85rem; }
            .hero-buttons { flex-direction: column; }
            .hero-buttons a { width: 100%; text-align: center; }
            .stats-row { justify-content: center; }
            .stats-grid-large { grid-template-columns: repeat(2, 1fr); }
            .housing-content, .notification-content, .document-content { grid-template-columns: 1fr; }
            .process-steps { grid-template-columns: repeat(2, 1fr); }
            .process-step:not(:last-child):after { display: none; }
            .footer-grid { grid-template-columns: 1fr; gap: 24px; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .cta-buttons a { width: 85%; text-align: center; }
            .hamburger { display: flex; }
            .scroll-up-btn {
                bottom: 20px;
                right: 20px;
                width: 44px;
                height: 44px;
            }
        }

        @media (max-width: 768px) {
            /* General Small UI/UX Spacing */
            body {
                padding-bottom: 68px !important; /* Space for fixed bottom tab bar */
            }
            .container { 
                padding: 0 16px !important; 
            }

            /* Compact Hero Section */
            .hero { 
                padding: 85px 0 25px !important; 
            }
            .hero-wrapper { 
                gap: 20px !important; 
            }
            .hero-content .badge {
                font-size: 0.72rem !important;
                padding: 4px 10px !important;
                margin-bottom: 10px !important;
            }
            .hero-content h1 {
                font-size: 0.75rem !important;
                letter-spacing: 0.8px !important;
                margin-bottom: 6px !important;
            }
            .hero-content h2 {
                font-size: 1.45rem !important;
                line-height: 1.25 !important;
                margin-bottom: 10px !important;
            }
            .hero-content p {
                font-size: 0.85rem !important;
                line-height: 1.45 !important;
                margin-bottom: 16px !important;
            }
            .hero-buttons {
                flex-direction: column !important;
                gap: 8px !important;
                width: 100% !important;
            }
            .hero-buttons a {
                width: 100% !important;
                padding: 10px 16px !important;
                font-size: 0.86rem !important;
                border-radius: 12px !important;
                justify-content: center !important;
            }
            .hero-image {
                text-align: center !important;
            }
            .hero-image img {
                max-height: 200px !important;
                width: 100% !important;
                object-fit: cover !important;
                border-radius: 16px !important;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12) !important;
            }

            /* Compact 2x2 Stats Dashboard Grid */
            .cswdo-stats-section {
                padding: 12px 0 16px !important;
            }
            .stats-grid-large { 
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }
            .stat-card-large {
                padding: 14px 10px !important;
                border-radius: 14px !important;
                text-align: center !important;
            }
            .stat-icon-wrap {
                width: 36px !important;
                height: 36px !important;
                font-size: 1rem !important;
                margin: 0 auto 6px !important;
                border-radius: 10px !important;
            }
            .stat-number-large {
                font-size: 1.35rem !important;
                line-height: 1.1 !important;
                margin-bottom: 3px !important;
            }
            .stat-label-large {
                font-size: 0.72rem !important;
                line-height: 1.25 !important;
                font-weight: 600 !important;
            }

            /* Compact Housing Section */
            .housing-program-section {
                padding: 18px 0 !important;
            }
            .housing-landscape-wrap {
                padding: 16px 12px !important;
                border-radius: 18px !important;
            }
            .housing-header-landscape .badge {
                font-size: 0.7rem !important;
                padding: 4px 10px !important;
            }
            .housing-header-landscape h2 {
                font-size: 1.25rem !important;
                margin: 8px 0 !important;
            }
            .housing-header-landscape p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 12px !important;
            }
            .housing-video-theater {
                min-height: auto !important;
                border-radius: 12px !important;
                margin-bottom: 12px !important;
            }
            .housing-video-element {
                max-height: 190px !important;
                border-radius: 12px !important;
            }
            .housing-video-pill {
                font-size: 0.7rem !important;
                padding: 4px 10px !important;
            }
            .housing-features-landscape {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }
            .housing-feature {
                padding: 10px 12px !important;
                border-radius: 12px !important;
                gap: 10px !important;
            }
            .housing-feature i {
                font-size: 1.1rem !important;
            }
            .housing-feature h4 {
                font-size: 0.85rem !important;
                margin-bottom: 2px !important;
            }
            .housing-feature p {
                font-size: 0.75rem !important;
                line-height: 1.35 !important;
            }

            /* Compact Transparency & 2x2 Steps Grid */
            .transparency-section {
                padding: 18px 0 !important;
            }
            .transparency-header h2 {
                font-size: 1.25rem !important;
                margin-bottom: 6px !important;
            }
            .transparency-header p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 14px !important;
            }
            .process-steps { 
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
            }
            .process-step {
                padding: 12px 10px !important;
                border-radius: 12px !important;
                text-align: center !important;
            }
            .step-number {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.78rem !important;
                margin: 0 auto 6px !important;
            }
            .process-step h4 {
                font-size: 0.82rem !important;
                margin-bottom: 3px !important;
            }
            .process-step p {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
            }
            .tracking-demo {
                padding: 12px 14px !important;
                margin-top: 14px !important;
                border-radius: 12px !important;
                font-size: 0.8rem !important;
            }

            /* Compact Services Section */
            .services {
                padding: 20px 0 !important;
            }
            .section-title {
                font-size: 1.25rem !important;
                line-height: 1.3 !important;
                margin-bottom: 14px !important;
            }
            .services-grid { 
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
            .service-card {
                padding: 14px 14px !important;
                border-radius: 14px !important;
            }
            .service-card h3 {
                font-size: 0.95rem !important;
                margin-bottom: 4px !important;
            }
            .service-card p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 8px !important;
            }
            .service-card .learn-more {
                font-size: 0.8rem !important;
            }

            /* Compact Notifications & Document Sections */
            .notification-section, .document-upload-section {
                padding: 18px 0 !important;
            }
            .notification-text h2, .document-text h2 {
                font-size: 1.25rem !important;
                margin-bottom: 6px !important;
            }
            .notification-text p, .document-text p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 12px !important;
            }
            .notification-channels {
                gap: 8px !important;
            }
            .notification-channel {
                padding: 10px 12px !important;
                border-radius: 12px !important;
                gap: 10px !important;
            }
            .notification-channel i {
                font-size: 1.1rem !important;
            }
            .notification-channel h4 {
                font-size: 0.85rem !important;
                margin-bottom: 2px !important;
            }
            .notification-channel p {
                font-size: 0.75rem !important;
                line-height: 1.35 !important;
            }
            .notification-preview {
                padding: 14px !important;
                border-radius: 14px !important;
                margin-top: 14px !important;
            }
            .notification-preview h3 {
                font-size: 0.9rem !important;
                margin-bottom: 8px !important;
            }
            .preview-item {
                padding: 8px 0 !important;
                font-size: 0.78rem !important;
            }
            .secure-badge {
                font-size: 0.72rem !important;
                padding: 4px 10px !important;
                margin-bottom: 8px !important;
            }
            .document-features {
                gap: 6px !important;
            }
            .document-feature {
                font-size: 0.8rem !important;
                gap: 8px !important;
            }
            .document-btn {
                padding: 9px 16px !important;
                font-size: 0.82rem !important;
                border-radius: 10px !important;
                margin-top: 12px !important;
                display: inline-flex !important;
            }

            /* Compact CTA Banner */
            .cta-section {
                padding: 28px 16px !important;
                border-radius: 18px !important;
                margin: 16px 0 !important;
            }
            .cta-section h2 {
                font-size: 1.25rem !important;
                margin-bottom: 6px !important;
            }
            .cta-section p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 14px !important;
            }

            /* Reposition Floating Scroll Up Button */
            .scroll-up-btn {
                bottom: 18px !important;
                right: 14px !important;
                width: 38px !important;
                height: 38px !important;
                font-size: 0.85rem !important;
            }

            /* Bottom tabs disabled per user request */
            .mobile-bottom-nav {
                display: none !important;
                visibility: hidden !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar (Unified Header Design matching Services, About, Contact) -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-wrapper">
                <div class="hero-content animate-fade-down">
                    <div class="badge"><i class="fas fa-star"></i> <?php echo htmlspecialchars(cms_get('hero_badge', 'CSWDO Palayan City')); ?></div>
                    <h1><?php echo htmlspecialchars(cms_get('hero_headline', 'SERVING PALAYAN CITY WITH EXCELLENCE')); ?></h1>
                    <h2><?php echo htmlspecialchars(cms_get('hero_subheadline', 'Streamlined Social Services For Every Palayan Resident')); ?></h2>
                    <p><?php echo htmlspecialchars(cms_get('hero_description', 'Schedule appointments, track assistance requests, and access housing programs - all in one integrated platform designed for efficiency and transparency.')); ?></p>
                    
                    <div class="hero-buttons">
                        <a href="<?php echo htmlspecialchars(cms_get('hero_btn1_link', 'appointments.php')); ?>" class="btn-primary"><?php echo htmlspecialchars(cms_get('hero_btn1_text', 'Book Appointment Now')); ?> <i class="fas fa-arrow-right"></i></a>
                        <a href="<?php echo htmlspecialchars(cms_get('hero_btn2_link', 'track-aics.php')); ?>" class="btn-secondary"><?php echo htmlspecialchars(cms_get('hero_btn2_text', 'Track My Request')); ?></a>
                    </div>
                </div>

                <div class="hero-image animate-fade-scale delay-2">
                    <img src="<?php 
                        $hero_src = cms_get('hero_image', 'images/palayan_hero_cswdo_banner.jpg');
                        if ($hero_src === 'images/palayan-city.png' || empty($hero_src)) {
                            $hero_src = 'images/palayan_hero_cswdo_banner.jpg';
                        }
                        echo htmlspecialchars($hero_src) . '?v=' . time();
                    ?>" alt="<?php echo htmlspecialchars(cms_get('site_title', 'Palayan City')); ?>">
                </div>
            </div>
        </div>
    </section>

    <!-- CSWDO Stats Section -->
    <?php if (cms_bool('show_hero_stats', true)): ?>
    <section class="cswdo-stats-section">
        <div class="container">
            <div class="stats-grid-large">
                <div class="stat-card-large">
                    <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_appointments'] ?? 2847); ?>">0</div>
                    <div class="stat-label-large">Appointments Scheduled</div>
                </div>
                <div class="stat-card-large">
                    <div class="stat-icon-wrap"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_aics'] ?? 1523); ?>">0</div>
                    <div class="stat-label-large">AICS Requests Tracked</div>
                </div>
                <div class="stat-card-large">
                    <div class="stat-icon-wrap"><i class="fas fa-home"></i></div>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_housing_beneficiaries'] ?? 892); ?>">0</div>
                    <div class="stat-label-large">Housing Beneficiaries</div>
                </div>
                <div class="stat-card-large">
                    <div class="stat-icon-wrap"><i class="fas fa-smile"></i></div>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['satisfaction_rate'] ?? 98); ?>">0%</div>
                    <div class="stat-label-large">User Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Housing Program Section (Landscape Theater Layout) -->
    <?php if (cms_bool('show_housing_section', true)): ?>
    <section class="housing-program-section">
        <div class="container">
            <div class="housing-landscape-wrap">
                <!-- Section Header -->
                <div class="housing-header-landscape animate-fade-up">
                    <div class="badge">
                        <i class="fas fa-city"></i> <?php echo htmlspecialchars(str_ireplace(['&amp;', '&'], 'and', cms_get('housing_section_badge', 'Municipal Resettlement and In-Site Housing')), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <h2><?php echo htmlspecialchars(cms_get('housing_section_title', 'Comprehensive Beneficiary Management')); ?></h2>
                    <p><?php echo htmlspecialchars(cms_get('housing_section_desc', 'Streamlined housing application tracking and beneficiary data management for efficient allocation and transparent monitoring of housing programs in Palayan City.')); ?></p>
                </div>

                <!-- Landscape 16:9 Cinema Video Showcase -->
                <div class="housing-video-theater animate-fade-scale delay-1">
                    <div class="housing-video-pill">
                        <span class="live-dot"></span>
                        <i class="fas fa-play-circle"></i> Palayan City Township Housing Fair Video
                    </div>
                    <video class="housing-video-element" controls poster="<?php echo cms_image('housing_section_poster', 'images/cswdo-housing-assistance.jpg'); ?>" preload="none">
                        <source src="<?php echo htmlspecialchars(cms_get('housing_section_video', 'video/Palayan City Township Housing Fair.mp4')); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- 3-Column Landscape Features Grid -->
                <div class="housing-features-landscape animate-fade-up delay-2">
                    <div class="housing-feature">
                        <i class="fas fa-database"></i>
                        <div>
                            <h4>Beneficiary Database</h4>
                            <p>Centralized records of all housing program beneficiaries with complete documentation and eligibility information.</p>
                        </div>
                    </div>
                    <div class="housing-feature">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <h4>Eligibility Verification</h4>
                            <p>Automated validation of applicant eligibility based on program requirements and government guidelines.</p>
                        </div>
                    </div>
                    <div class="housing-feature">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <h4>Distribution Monitoring</h4>
                            <p>Real-time tracking of housing unit allocation, distribution progress, and beneficiary status updates.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Transparency Section -->
    <?php if (cms_bool('show_transparency_section', true)): ?>
    <section class="transparency-section">
        <div class="container">
            <div class="transparency-header animate-fade-down">
                <h2><?php echo htmlspecialchars(str_ireplace(['&amp;', '&'], 'and', cms_get('transparency_title', 'Transparency and Accountability'))); ?></h2>
                <p><?php echo htmlspecialchars(cms_get('transparency_desc', "Track your assistance request in real-time. From submission to release, you'll always know where your request stands.")); ?></p>
            </div>
            <div class="process-steps">
                <div class="process-step"><div class="step-number">01</div><h4><?php echo htmlspecialchars(cms_get('step1_title', 'Submit')); ?></h4><p><?php echo htmlspecialchars(cms_get('step1_desc', 'Fill out the online form and submit your AICS request with required documents.')); ?></p></div>
                <div class="process-step"><div class="step-number">02</div><h4><?php echo htmlspecialchars(cms_get('step2_title', 'Review')); ?></h4><p><?php echo htmlspecialchars(cms_get('step2_desc', 'CSWDO staff reviews your application and verifies submitted documents.')); ?></p></div>
                <div class="process-step"><div class="step-number">03</div><h4><?php echo htmlspecialchars(cms_get('step3_title', 'Approval')); ?></h4><p><?php echo htmlspecialchars(cms_get('step3_desc', 'Your request is evaluated and approved based on eligibility criteria.')); ?></p></div>
                <div class="process-step"><div class="step-number">04</div><h4><?php echo htmlspecialchars(cms_get('step4_title', 'Release')); ?></h4><p><?php echo htmlspecialchars(cms_get('step4_desc', 'Receive notification and claim your assistance at the CSWDO office.')); ?></p></div>
            </div>
            <div class="tracking-demo animate-fade-up">
                <i class="fas fa-chart-line"></i>
                <h4>Status Updated</h4>
                <div class="status-updated"><strong>Request #AICS-12345</strong> - Approved and ready for release</div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Stay Informed Section -->
    <?php if (cms_bool('show_notification_section', true)): ?>
    <section class="notification-section">
        <div class="container">
            <div class="notification-content">
                <div class="notification-text animate-fade-up">
                    <h2><?php echo htmlspecialchars(cms_get('notif_section_title', 'Stay Informed with Automated Updates')); ?></h2>
                    <p><?php echo htmlspecialchars(cms_get('notif_section_desc', 'Receive timely notifications through multiple channels to keep you updated on your appointments, requests, and application status at every step.')); ?></p>
                    <div class="notification-channels">
                        <div class="notification-channel">
                            <i class="fas fa-sms"></i>
                            <div><h4>SMS Notifications</h4><p>Receive instant text messages for appointment confirmations, reminders, and status updates directly to your mobile phone.</p><small><i class="fas fa-check-circle"></i> Active</small></div>
                        </div>
                        <div class="notification-channel">
                            <i class="fas fa-envelope"></i>
                            <div><h4>Email Alerts</h4><p>Get detailed email notifications with complete information about your requests, approvals, and important announcements.</p><small><i class="fas fa-check-circle"></i> Active</small></div>
                        </div>
                        <div class="notification-channel">
                            <i class="fas fa-bell"></i>
                            <div><h4>In-App Notifications</h4><p>Stay updated with real-time notifications within the platform. Never miss important updates about your applications.</p><small><i class="fas fa-check-circle"></i> Active</small></div>
                        </div>
                    </div>
                </div>
                <div class="notification-preview animate-fade-scale delay-2">
                    <h3><i class="fas fa-clock"></i> Recent Updates</h3>
                    <?php if(isLoggedIn() && !empty($latest_appointments)): ?>
                        <?php foreach($latest_appointments as $appointment): ?>
                        <div class="preview-item">
                            <p><strong>✓ Your appointment for <?php echo htmlspecialchars($appointment['service_name']); ?> is on <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong></p>
                            <small><?php echo time_ago(strtotime($appointment['created_at'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif(isLoggedIn() && !empty($latest_aics)): ?>
                        <?php foreach($latest_aics as $aics): ?>
                        <div class="preview-item">
                            <p><strong>✓ Your <?php echo ucfirst($aics['request_type']); ?> request #AICS-<?php echo str_pad($aics['aics_id'], 6, '0', STR_PAD_LEFT); ?> is <?php echo $aics['status']; ?></strong></p>
                            <small><?php echo time_ago(strtotime($aics['submission_date'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="preview-item">
                            <p><strong>✓ Your appointment is confirmed for Jan 15, 2025 at 10:00 AM</strong></p>
                            <small>Sample notification - Login to see your updates</small>
                        </div>
                        <div class="preview-item">
                            <p><strong>✓ Your AICS request #12345 has been approved and is ready for release</strong></p>
                            <small>Sample notification - Login to see your updates</small>
                        </div>
                        <div class="preview-item">
                            <p><strong>✓ New document required for your housing application</strong></p>
                            <small>Sample notification - Login to see your updates</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Document Upload Section -->
    <?php if (cms_bool('show_document_upload_section', true)): ?>
    <section class="document-upload-section">
        <div class="container">
            <div class="document-content">
                <div class="document-text animate-fade-up">
                    <div class="secure-badge"><i class="fas fa-lock"></i> <?php echo htmlspecialchars(cms_get('doc_upload_badge', 'Secure Upload • 256-bit encryption')); ?></div>
                    <h2><?php echo htmlspecialchars(cms_get('doc_upload_title', 'Upload Documents Before Your Visit')); ?></h2>
                    <p><?php echo htmlspecialchars(cms_get('doc_upload_desc', 'Our document pre-verification system allows you to upload required documents before your scheduled appointment, significantly reducing processing time and ensuring a smoother experience at the CSWDO office.')); ?></p>
                    <div class="document-features">
                        <div class="document-feature"><i class="fas fa-check-circle"></i><span>Faster processing and reduced waiting time</span></div>
                        <div class="document-feature"><i class="fas fa-cloud-upload-alt"></i><span>Secure cloud storage for all documents</span></div>
                        <div class="document-feature"><i class="fas fa-file-alt"></i><span>Reduced office visits and paperwork</span></div>
                        <div class="document-feature"><i class="fas fa-sync-alt"></i><span>Instant verification status updates</span></div>
                    </div>
                    <a href="services.php" class="document-btn">See Required Documents <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="document-image animate-fade-scale delay-2">
                    <i class="fas fa-upload"></i>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Services Section -->
    <?php if (cms_bool('show_services_section', true)): ?>
    <?php $homepage_services = cms_get_featured_services(4); ?>
    <section class="services">
        <div class="container">
            <h2 class="section-title animate-fade-down">Integrated Services<br><span>Designed for Efficiency</span></h2>
            <div class="services-grid">
                <?php if (!empty($homepage_services)): ?>
                    <?php foreach ($homepage_services as $h_svc): ?>
                    <div class="service-card">
                        <h3><i class="<?php echo htmlspecialchars($h_svc['icon'] ?: 'fas fa-hands-helping'); ?>"></i> <?php echo htmlspecialchars($h_svc['service_name']); ?></h3>
                        <p><?php echo htmlspecialchars($h_svc['short_desc'] ?: $h_svc['description']); ?></p>
                        <a href="<?php echo htmlspecialchars($h_svc['apply_url'] ?: 'services.php'); ?>" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="service-card"><h3>📅 Appointment Scheduling</h3><p>Book your social service appointments online with ease. Choose your preferred date and time, and receive instant confirmation with automated reminders.</p><a href="services.php?service=appointments" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                    <div class="service-card"><h3>📄 Document Pre-Verification</h3><p>Upload required documents before your appointment to reduce processing time. Our secure system verifies your documents in advance for faster service.</p><a href="services.php?service=documents" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                    <div class="service-card"><h3>🤝 Crisis Assistance (AICS)</h3><p>Medical, burial, educational, transportation, food, and emergency cash relief for individuals and families in crisis. Complete checklists and guidelines.</p><a href="services.php?cat=aics" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                    <div class="service-card"><h3>🏠 Socialized and In-Site Housing</h3><p>Explore Akap Village, San Lorenzo NHA, and In-Site Housing (on your own lot). Review full requirements, guidelines, and procedures freely.</p><a href="services.php?cat=housing" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 36px;">
                <a href="services.php" class="learn-more" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 1rem; color: var(--maroon);">
                    <i class="fas fa-th-large"></i> Explore All Available Public Services and Citizen's Charters <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <?php if (cms_bool('show_cta_section', true)): ?>
    <section class="cta-section">
        <div class="container">
            <h2 class="animate-fade-down"><?php echo htmlspecialchars(cms_get('cta_title', 'Ready to Get Started?')); ?></h2>
            <p class="animate-fade-up delay-1"><?php echo htmlspecialchars(cms_get('cta_desc', 'Create your account in under 2 minutes and start accessing social services online. Join thousands of Palayan City residents already using our platform.')); ?></p>
            <div class="cta-buttons animate-fade-scale delay-2">
                <a href="<?php echo htmlspecialchars(cms_get('cta_btn1_link', 'register.php')); ?>" class="btn-primary"><?php echo htmlspecialchars(cms_get('cta_btn1_text', 'Register as Resident')); ?> <i class="fas fa-arrow-right"></i></a>
                <?php if(!isLoggedIn()): ?>
                <a href="<?php echo htmlspecialchars(cms_get('cta_btn2_link', 'login.php')); ?>" class="btn-secondary"><?php echo htmlspecialchars(cms_get('cta_btn2_text', 'Sign In')); ?> <i class="fas fa-sign-in-alt"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Global Unified Green Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scroll Up Button -->
    <div class="scroll-up-btn" id="scrollUpBtn">
        <i class="fas fa-chevron-up"></i>
    </div>

    <script>
    // Helper function for time ago
    function time_ago(timestamp) {
        var seconds = Math.floor((new Date() - timestamp) / 1000);
        var intervals = [
            { label: 'year', seconds: 31536000 },
            { label: 'month', seconds: 2592000 },
            { label: 'day', seconds: 86400 },
            { label: 'hour', seconds: 3600 },
            { label: 'minute', seconds: 60 },
            { label: 'second', seconds: 1 }
        ];
        for (var i = 0; i < intervals.length; i++) {
            var interval = intervals[i];
            var count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return count + ' ' + interval.label + (count === 1 ? '' : 's') + ' ago';
            }
        }
        return 'just now';
    }

    // Ensure light theme for public homepage
    document.documentElement.setAttribute('data-theme', 'light');

    // ========== NAVBAR SCROLL EFFECT ==========
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Show/hide scroll button based on scroll position
        const scrollBtn = document.getElementById('scrollUpBtn');
        if (window.scrollY > 300) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // ========== SCROLL TO TOP ==========
    document.getElementById('scrollUpBtn').addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // ========== MOBILE MENU TOGGLE (DRAWER & BOTTOM NAV) ==========
    const mobileMenuBtn = document.getElementById('mobileMenuTabBtn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof openDrawer === 'function') {
                openDrawer();
            } else {
                const hb = document.getElementById('hamburger');
                if (hb) hb.click();
            }
        });
    }

    // ========== ANIMATED COUNTERS ==========
    document.addEventListener('DOMContentLoaded', function() {
        const statNumbers = document.querySelectorAll('.stat-number-large[data-count]');
        
        statNumbers.forEach((el) => {
            const target = el.getAttribute('data-count');
            const isPercentage = target.includes('%');
            const cleanTarget = parseInt(target.replace(/,/g, '').replace('%', ''));
            
            if (cleanTarget > 0) {
                let current = 0;
                const increment = Math.ceil(cleanTarget / 50);
                const duration = 1000;
                const stepTime = Math.floor(duration / 50);
                
                const counter = setInterval(() => {
                    current += increment;
                    if (current >= cleanTarget) {
                        el.textContent = isPercentage ? cleanTarget + '%' : cleanTarget.toLocaleString();
                        clearInterval(counter);
                    } else {
                        el.textContent = isPercentage ? current + '%' : current.toLocaleString();
                    }
                }, stepTime);
            }
        });
    });

    // ========== SMOOTH SCROLL ANIMATIONS ==========
    document.addEventListener('DOMContentLoaded', function() {
        const animateElements = document.querySelectorAll('.stat-card-large, .service-card, .process-step, .housing-feature, .notification-channel');
        
        const observerOptions = {
            threshold: 0.15,
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
        
        animateElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    });

    // ========== KEYBOARD SHORTCUTS ==========
    document.addEventListener('keydown', function(e) {
        // Ctrl + H for home
        if (e.ctrlKey && e.key === 'h') {
            e.preventDefault();
            window.location.href = 'index.php';
        }
        // Ctrl + L for login
        if (e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            window.location.href = 'login.php';
        }
        // Escape to close mobile menu
        if (e.key === 'Escape') {
            const navLinks = document.getElementById('navLinks');
            const hamburger = document.getElementById('hamburger');
            if (navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');
            }
        }
    });

    // ========== CONSOLE WELCOME ==========
    console.log('🏛️ One Palayan-Kalinga Hub');
    console.log('📍 CSWDO Palayan City');
    console.log('💡 Keyboard shortcuts: Ctrl+H (Home), Ctrl+L (Login)');
    console.log('🌓 Toggle dark/light mode using the sun/moon button in the navbar!');

    </script>

    <script src="js/main.js"></script>
</body>
</html>
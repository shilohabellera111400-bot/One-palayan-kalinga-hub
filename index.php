<?php
// Include database configuration
require_once __DIR__ . '/config/database.php';

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
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Palayan-Kalinga Hub - CSWDO Palayan City</title>
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

        /* ========== THEME TOGGLE ========== */
        .theme-toggle-nav {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
            margin-right: 8px;
        }
        .theme-toggle-nav:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(30deg) scale(1.05);
        }
        .navbar.scrolled .theme-toggle-nav {
            background: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        .navbar.scrolled .theme-toggle-nav:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        /* ========== NAVBAR STYLES ========== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--midnight);
            border-bottom: none;
        }

        .navbar.scrolled {
            background: var(--bg-card);
            box-shadow: var(--shadow-md);
            padding: 0.7rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-circle {
            width: 65px;
            height: 65px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .logo-circle:hover {
            transform: scale(1.05) rotate(-5deg);
        }

        .logo-circle img {
            width: 63px;
            height: auto;
        }

        .logo-text h2 {
            font-size: 1.3rem;
            font-weight: 800;
            background: linear-gradient(135deg, white 0%, var(--light-blue) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
        }

        .logo-text span {
            font-size: 0.7rem;
            font-weight: 500;
            background: var(--tan);
            padding: 2px 10px;
            border-radius: 40px;
            margin-left: 8px;
            color: #3b2a1f;
        }

        .navbar.scrolled .logo-text h2 {
            background: linear-gradient(135deg, var(--midnight) 0%, var(--light-blue) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: white;
        }

        .navbar.scrolled .nav-links a {
            color: var(--text-secondary);
        }

        .navbar.scrolled .nav-links a:hover {
            color: var(--maroon);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .profile-icon i { font-size: 1.1rem; }
        .profile-icon span { font-size: 0.85rem; }
        .profile-icon:hover { 
            background: rgba(255, 255, 255, 0.25); 
            transform: translateY(-2px) scale(1.02);
        }
        .logout-icon {
            background: rgba(255, 255, 255, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .logout-icon:hover { 
            background: rgba(255, 255, 255, 0.2); 
            transform: translateY(-2px) scale(1.05);
        }
        .navbar.scrolled .profile-icon { 
            background: var(--alabaster); 
            color: var(--midnight); 
        }
        .navbar.scrolled .profile-icon:hover { 
            background: var(--tan); 
        }
        .navbar.scrolled .logout-icon { 
            background: var(--bg-primary); 
            color: var(--maroon); 
        }
        .navbar.scrolled .logout-icon:hover { 
            background: var(--border-color); 
        }

        .login-btn {
            background: var(--maroon);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-btn:hover { 
            background: var(--maroon-light); 
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 15px rgba(127,3,3,0.3);
        }
        .login-btn::after { display: none; }
        .navbar.scrolled .login-btn { 
            background: var(--maroon); 
            color: white !important; 
        }
        .navbar.scrolled .login-btn:hover { 
            background: var(--maroon-light); 
        }

        .register-btn {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-right: 20px;
        }
        .register-btn:hover { 
            background: rgba(255, 255, 255, 0.25); 
            transform: translateY(-2px) scale(1.02);
        }
        .register-btn::after { display: none; }
        .navbar.scrolled .register-btn { 
            background: var(--alabaster); 
            color: var(--midnight) !important; 
        }
        .navbar.scrolled .register-btn:hover { 
            background: var(--tan); 
        }

        .hamburger {
            display: none;
            cursor: pointer;
            background: none;
            border: none;
            padding: 8px;
            flex-direction: column;
            gap: 5px;
        }
        .hamburger span {
            display: block;
            width: 25px;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }
        .navbar.scrolled .hamburger span { 
            background: var(--text-primary); 
        }

        /* ========== HERO SECTION ========== */
        .hero {
            position: relative;
            color: white;
            padding: 160px 0 90px;
            overflow: hidden;
            background: var(--midnight);
            margin-top: 0;
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
            padding: 18px 22px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--maroon);
            max-width: 380px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .status-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-lg);
        }
        .status-card-content { display: flex; align-items: center; gap: 15px; }
        .status-icon {
            background: rgba(127,3,3,0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-icon i { color: var(--maroon); font-size: 1.5rem; }
        .status-text h4 { color: var(--midnight); margin-bottom: 5px; font-size: 0.9rem; font-weight: 700; }
        .status-text p { color: var(--gray-600); margin: 0; font-weight: 500; font-size: 0.85rem; }
        .status-text strong { color: var(--maroon); }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-top: 20px;
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
        }
        .stat-label { color: var(--text-muted); font-size: 0.75rem; font-weight: 500; }

        /* ========== CSWDO STATS SECTION ========== */
        .cswdo-stats-section {
            padding: 84px 0;
            background: var(--bg-primary);
            transition: background 0.4s ease;
        }
        .stats-grid-large {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
        }
        .stat-card-large {
            background: var(--bg-card);
            padding: 36px 22px;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease both;
        }
        .stat-card-large:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-large:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-large:nth-child(3) { animation-delay: 0.3s; }
        .stat-card-large:nth-child(4) { animation-delay: 0.4s; }

        .stat-card-large::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(127,3,3,0.04), transparent 60%);
            pointer-events: none;
        }
        .stat-card-large:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card-large i {
            font-size: 3rem;
            color: var(--maroon);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .stat-card-large:hover i {
            transform: scale(1.1) rotate(-5deg);
        }
        .stat-card-large .stat-number-large {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }
        .stat-card-large .stat-label-large {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ========== HOUSING PROGRAM SECTION ========== */
        .housing-program-section {
            padding: 84px 0;
            background: var(--alabaster);
            transition: background 0.4s ease;
        }
        .housing-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }
        .housing-text h2 {
            font-size: 2.2rem;  
            font-weight: 800;
            color: var(--maroon);
            margin-bottom: 15px;
        }
        .housing-text p {
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.7;
        }
        .housing-features {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .housing-feature {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            padding: 12px 16px;
            border-radius: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .housing-feature:hover {
            background: rgba(255,255,255,0.5);
            transform: translateX(5px);
        }
        .housing-feature i {
            font-size: 1.8rem;
            color: var(--maroon);
            transition: all 0.3s ease;
        }
        .housing-feature:hover i {
            transform: scale(1.1) rotate(-5deg);
        }
        .housing-feature h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        .housing-feature p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }
        .housing-image {
            background: linear-gradient(135deg, var(--midnight), var(--light-blue));
            border-radius: 28px;
            padding: 42px;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 360px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 44px rgba(15,65,74,0.16);
            transition: all 0.4s ease;
        }
        .housing-image:hover {
            transform: scale(1.01);
            box-shadow: 0 24px 50px rgba(15,65,74,0.25);
        }
        .housing-image i {
            font-size: 6rem;
            color: rgba(255,255,255,0.3);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .housing-image:hover i {
            transform: scale(1.1);
            color: rgba(255,255,255,0.5);
        }
        .housing-image h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .housing-image p {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        .image-badge {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: var(--maroon);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 0.8rem;
            animation: pulse 2s infinite;
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
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px 18px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease both;
        }
        .process-step:nth-child(1) { animation-delay: 0.1s; }
        .process-step:nth-child(2) { animation-delay: 0.2s; }
        .process-step:nth-child(3) { animation-delay: 0.3s; }
        .process-step:nth-child(4) { animation-delay: 0.4s; }

        .process-step:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .process-step:not(:last-child):after {
            content: "→";
            position: absolute;
            right: -20px;
            top: 30px;
            font-size: 1.5rem;
            color: var(--maroon);
            font-weight: 700;
            animation: float 2s infinite;
        }
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
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
            gap: 15px;
            align-items: flex-start;
            padding: 16px 18px;
            background: var(--bg-primary);
            border-radius: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            cursor: pointer;
        }
        .notification-channel:hover {
            transform: translateX(8px) scale(1.01);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon-light);
        }
        .notification-channel i {
            font-size: 1.8rem;
            color: var(--maroon);
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
            background: var(--bg-card);
            padding: 30px;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease both;
        }
        .service-card:nth-child(1) { animation-delay: 0.1s; }
        .service-card:nth-child(2) { animation-delay: 0.2s; }
        .service-card:nth-child(3) { animation-delay: 0.3s; }
        .service-card:nth-child(4) { animation-delay: 0.4s; }

        .service-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 968px) {
            .hero-wrapper { flex-direction: column-reverse; gap: 40px; }
            .hero-content h2 { font-size: 2rem; }
            .hero-buttons { flex-direction: column; }
            .hero-buttons a { width: 100%; text-align: center; }
            .stats-row { justify-content: center; }
            .stats-grid-large { grid-template-columns: repeat(2, 1fr); }
            .housing-content, .notification-content, .document-content { grid-template-columns: 1fr; }
            .process-steps { grid-template-columns: repeat(2, 1fr); }
            .process-step:not(:last-child):after { display: none; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .cta-buttons a { width: 80%; text-align: center; }
            .nav-links { display: none; width: 100%; flex-direction: column; padding: 20px 0; gap: 15px; }
            .nav-links.active { display: flex; }
            .hamburger { display: flex; }
            .scroll-up-btn {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
            }
        }
        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1.5rem; }
            .navbar.scrolled { padding: 0.6rem 1.5rem; }
            .container { padding: 0 20px; }
            .services-grid { grid-template-columns: 1fr; }
            .hero { padding: 120px 0 60px; }
            .stats-grid-large { grid-template-columns: 1fr; }
            .process-steps { grid-template-columns: 1fr; }
            .scroll-up-btn {
                bottom: 15px;
                right: 15px;
                width: 40px;
                height: 40px;
            }
            .logo-circle {
                width: 38px;
                height: 38px;
            }
            .logo-circle img {
                width: 26px;
            }
            .logo-text h2 {
                font-size: 1rem;
            }
            .hero-content h2 {
                font-size: 1.8rem;
            }
        }
        @media (max-width: 480px) {
            .hero-content h2 {
                font-size: 1.5rem;
            }
            .stat-card-large {
                padding: 24px 16px;
            }
            .stat-card-large .stat-number-large {
                font-size: 2rem;
            }
            .notification-preview {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <div class="logo-area">
            <div class="logo-circle">
                <img src="images/logo.png" alt="One Palayan-Kalinga Hub Logo" onerror="this.src='https://placehold.co/32x32?text=PKH'">
            </div>
            <div class="logo-text">
                <h2>ONE PALAYAN-KALINGA HUB</h2>
                <span>CSWDO Palayan City</span>
            </div>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="services.php">Services</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            
            <?php if(isLoggedIn()): ?>
                <div class="user-menu">
                    <button class="theme-toggle-nav" id="themeToggleNav" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="profile-icon">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </a>
                </div>
            <?php else: ?>
                <button class="theme-toggle-nav" id="themeToggleNav" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            <?php endif; ?>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-wrapper">
                <div class="hero-content animate-fade-down">
                    <div class="badge"><i class="fas fa-star"></i> CSWDO Palayan City</div>
                    <h1>SERVING PALAYAN CITY WITH EXCELLENCE</h1>
                    <h2>Streamlined Social Services For Every <span>Palayan Resident</span></h2>
                    <p>Schedule appointments, track assistance requests, and access housing programs - all in one integrated platform designed for efficiency and transparency.</p>
                    
                    <div class="hero-buttons">
                        <a href="appointments.php" class="btn-primary">Book Appointment Now <i class="fas fa-arrow-right"></i></a>
                        <a href="track-aics.php" class="btn-secondary">Track My Request</a>
                    </div>

                    <div class="status-card">
                        <div class="status-card-content">
                            <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="status-text">
                                <h4>AICS Status Update</h4>
                                <p><strong>Request #12345</strong> Approved</p>
                            </div>
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-box"><span class="stat-number">847+</span><span class="stat-label">Appointments Served</span></div>
                        <div class="stat-box"><span class="stat-number">₱1.2M</span><span class="stat-label">AICS Released</span></div>
                    </div>
                </div>

                <div class="hero-image animate-fade-scale delay-2">
                    <img src="images/palayan-city.png" alt="Lungsod ng Palayan - Kabisera ng Nueva Ecija">
                </div>
            </div>
        </div>
    </section>

    <!-- CSWDO Stats Section -->
    <section class="cswdo-stats-section">
        <div class="container">
            <div class="stats-grid-large">
                <div class="stat-card-large">
                    <i class="fas fa-calendar-check"></i>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_appointments'] ?? 2847); ?>">0</div>
                    <div class="stat-label-large">Appointments Scheduled</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-hand-holding-heart"></i>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_aics'] ?? 1523); ?>">0</div>
                    <div class="stat-label-large">AICS Requests Tracked</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-home"></i>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['total_housing_beneficiaries'] ?? 892); ?>">0</div>
                    <div class="stat-label-large">Housing Beneficiaries</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-smile"></i>
                    <div class="stat-number-large" data-count="<?php echo number_format($stats['satisfaction_rate'] ?? 98); ?>">0%</div>
                    <div class="stat-label-large">User Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Housing Program Section -->
    <section class="housing-program-section">
        <div class="container">
            <div class="housing-content">
                <div class="housing-text animate-fade-up">
                    <h2>Comprehensive Beneficiary Management</h2>
                    <p>Streamlined housing application tracking and beneficiary data management for efficient allocation and transparent monitoring of housing programs in Palayan City.</p>
                    <div class="housing-features">
                        <div class="housing-feature">
                            <i class="fas fa-database"></i>
                            <div><h4>Beneficiary Database</h4><p>Centralized records of all housing program beneficiaries with complete documentation and eligibility information.</p></div>
                        </div>
                        <div class="housing-feature">
                            <i class="fas fa-check-circle"></i>
                            <div><h4>Eligibility Verification</h4><p>Automated validation of applicant eligibility based on program requirements and government guidelines.</p></div>
                        </div>
                        <div class="housing-feature">
                            <i class="fas fa-chart-line"></i>
                            <div><h4>Distribution Monitoring</h4><p>Real-time tracking of housing unit allocation, distribution progress, and beneficiary status updates.</p></div>
                        </div>
                    </div>
                </div>
                <div class="housing-image animate-fade-scale delay-2">
                    <i class="fas fa-home"></i>
                    <h3>Housing Programs</h3>
                    <p>AKAP Village • San Lorenzo Residences • Socialized Housing</p>
                    <div class="image-badge">Housing Program 2026</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Transparency Section -->
    <section class="transparency-section">
        <div class="container">
            <div class="transparency-header animate-fade-down">
                <h2>Transparency & Accountability</h2>
                <p>Track your assistance request in real-time. From submission to release, you'll always know where your request stands.</p>
            </div>
            <div class="process-steps">
                <div class="process-step"><div class="step-number">01</div><h4>Submit</h4><p>Fill out the online form and submit your AICS request with required documents.</p></div>
                <div class="process-step"><div class="step-number">02</div><h4>Review</h4><p>CSWDO staff reviews your application and verifies submitted documents.</p></div>
                <div class="process-step"><div class="step-number">03</div><h4>Approval</h4><p>Your request is evaluated and approved based on eligibility criteria.</p></div>
                <div class="process-step"><div class="step-number">04</div><h4>Release</h4><p>Receive notification and claim your assistance at the CSWDO office.</p></div>
            </div>
            <div class="tracking-demo animate-fade-up">
                <i class="fas fa-chart-line"></i>
                <h4>Status Updated</h4>
                <div class="status-updated"><strong>Request #AICS-12345</strong> - Approved and ready for release</div>
            </div>
        </div>
    </section>

    <!-- Stay Informed Section -->
    <section class="notification-section">
        <div class="container">
            <div class="notification-content">
                <div class="notification-text animate-fade-up">
                    <h2>Stay Informed with Automated Updates</h2>
                    <p>Receive timely notifications through multiple channels to keep you updated on your appointments, requests, and application status at every step.</p>
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

    <!-- Document Upload Section -->
    <section class="document-upload-section">
        <div class="container">
            <div class="document-content">
                <div class="document-text animate-fade-up">
                    <div class="secure-badge"><i class="fas fa-lock"></i> Secure Upload • 256-bit encryption</div>
                    <h2>Upload Documents Before Your Visit</h2>
                    <p>Our document pre-verification system allows you to upload required documents before your scheduled appointment, significantly reducing processing time and ensuring a smoother experience at the CSWDO office.</p>
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

    <!-- Services Section -->
    <section class="services">
        <div class="container">
            <h2 class="section-title animate-fade-down">Integrated Services<br><span>Designed for Efficiency</span></h2>
            <div class="services-grid">
                <div class="service-card"><h3>📅 Appointment Scheduling</h3><p>Book your social service appointments online with ease. Choose your preferred date and time, and receive instant confirmation with automated reminders.</p><a href="appointments.php" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                <div class="service-card"><h3>📄 Document Pre-Verification</h3><p>Upload required documents before your appointment to reduce processing time. Our secure system verifies your documents in advance for faster service.</p><a href="documents.php" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                <div class="service-card"><h3>🤝 AICS Tracking</h3><p>Monitor your Assistance to Individuals in Crisis Situation request status in real-time. Track every step from submission to approval and release.</p><a href="track-aics.php" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
                <div class="service-card"><h3>🏠 Housing Management</h3><p>Apply for housing programs and track your beneficiary status. Access centralized records and monitor allocation updates through our integrated system.</p><a href="housing.php" class="learn-more">Learn More <i class="fas fa-arrow-right"></i></a></div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="animate-fade-down">Ready to Get Started?</h2>
            <p class="animate-fade-up delay-1">Create your account in under 2 minutes and start accessing social services online. Join thousands of Palayan City residents already using our platform.</p>
            <div class="cta-buttons animate-fade-scale delay-2">
                <a href="register.php" class="btn-primary">Register as Resident <i class="fas fa-arrow-right"></i></a>
                <?php if(!function_exists('isStaff') || !isStaff()): ?>
                <a href="staff-login.php" class="btn-secondary">Staff Login</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section"><h3>ONE PALAYAN-KALINGA HUB</h3><p>CSWDO Palayan City — Integrated Social Services Platform</p><div><h4>SERVICES</h4><ul><li><a href="appointments.php">Appointment Booking</a></li><li><a href="aics.php">AICS Application</a></li><li><a href="housing.php">Housing Programs</a></li></ul></div></div>
                <div class="footer-section"><h4>RESOURCES</h4><ul><li><a href="user-guide.php">User Guide</a></li><li><a href="faqs.php">FAQs</a></li><li><a href="privacy-policy.php">Privacy Policy</a></li><li><a href="terms.php">Terms of Service</a></li></ul></div>
                <div class="footer-section"><h4>CONTACT US</h4><p>City Social Welfare and Development Office<br>Palayan City, Nueva Ecija, Philippines</p><p class="contact-number"><i class="fas fa-phone"></i> +63 (44) 123 4567</p><p class="email"><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2026 One Palayan-Kalinga Hub. All rights reserved. | A project of CSWDO Palayan City</p></div>
        </div>
    </footer>

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

    // ========== THEME TOGGLE ==========
    const themeToggleNav = document.getElementById('themeToggleNav');
    const html = document.documentElement;

    const currentTheme = localStorage.getItem('theme') || 
                        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    function setTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        const icon = themeToggleNav.querySelector('i');
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
    }

    setTheme(currentTheme);

    themeToggleNav.addEventListener('click', function() {
        const current = html.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        
        // Haptic feedback simulation
        this.style.transform = 'scale(0.85)';
        setTimeout(() => {
            this.style.transform = '';
        }, 200);
    });

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

    // ========== MOBILE MENU TOGGLE ==========
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            this.classList.toggle('active');
            navLinks.classList.toggle('active');
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
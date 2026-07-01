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
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Palayan-Kalinga Hub - CSWDO Palayan City</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== PROFESSIONAL COLOR PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-dark: #5a0202;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-100: #F1F3F8;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 16px 32px rgba(0, 0, 0, 0.08);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.5;
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(127,3,3,0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }

        .scroll-up-btn.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .scroll-up-btn:hover {
            background: var(--maroon-light);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(127,3,3,0.4);
        }

        .scroll-up-btn i {
            font-size: 1.2rem;
            animation: bounceUp 1.5s infinite;
        }

        @keyframes bounceUp {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
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
            transition: all 0.3s ease-in-out;
            background: var(--midnight);
            border-bottom: none;
        }

        .navbar.scrolled {
            background: var(--white);
            box-shadow: var(--shadow-md);
            padding: 0.7rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Logo with white circle background - from login page */
        .logo-circle {
            width: 65px;
            height: 65px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .logo-circle:hover {
            transform: scale(1.05);
        }

        .logo-circle img {
            width: 63px;
            height: auto;
        }

        .logo-text h2 {
            font-size: 1.3rem;
            font-weight: 700;
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
            transition: all 0.2s;
            position: relative;
        }

        .nav-links a:hover {
            color: white;
        }

        .navbar.scrolled .nav-links a {
            color: var(--gray-700);
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
            transition: all 0.3s;
        }
        .profile-icon i { font-size: 1.1rem; }
        .profile-icon span { font-size: 0.85rem; }
        .profile-icon:hover { background: rgba(255, 255, 255, 0.25); transform: translateY(-2px); }
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
            transition: all 0.3s;
        }
        .logout-icon:hover { background: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
        .navbar.scrolled .profile-icon { background: var(--alabaster); color: var(--midnight); }
        .navbar.scrolled .profile-icon:hover { background: var(--tan); }
        .navbar.scrolled .logout-icon { background: var(--gray-100); color: var(--maroon); }
        .navbar.scrolled .logout-icon:hover { background: var(--gray-200); }

        .login-btn {
            background: var(--maroon);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s;
        }
        .login-btn:hover { background: var(--maroon-light); transform: translateY(-2px); }
        .navbar.scrolled .login-btn { background: var(--maroon); color: white !important; }
        .navbar.scrolled .login-btn:hover { background: var(--maroon-light); }

        .register-btn {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s;
            margin-right: 20px;
        }
        .register-btn:hover { background: rgba(255, 255, 255, 0.25); transform: translateY(-2px); }
        .navbar.scrolled .register-btn { background: var(--alabaster); color: var(--midnight) !important; }
        .navbar.scrolled .register-btn:hover { background: var(--tan); }

        .hamburger {
            display: none;
            cursor: pointer;
            background: none;
            border: none;
            padding: 8px;
        }
        .hamburger span {
            display: block;
            width: 25px;
            height: 2px;
            margin: 5px 0;
            background: white;
            transition: all 0.3s;
        }
        .navbar.scrolled .hamburger span { background: var(--midnight); }

        /* ========== HERO SECTION ========== */
        .hero {
            position: relative;
            color: white;
            padding: 150px 0 90px;
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

        @keyframes slowZoom {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }

        .hero .container { position: relative; z-index: 2; }
        .hero-wrapper { display: flex; align-items: center; gap: 50px; }
        .hero-content { flex: 1; }
        .hero-content h1 { font-size: 1rem; font-weight: 500; margin-bottom: 10px; color: var(--light-blue); letter-spacing: 1px; }
        .hero-content h2 { font-size: 3rem; font-weight: 800; line-height: 1.15; margin-bottom: 20px; color: white; max-width: 640px; }
        .hero-content p { font-size: 1.02rem; margin-bottom: 30px; opacity: 0.9; max-width: 560px; color: rgba(255,255,255,0.92); }
        .hero-buttons { display: flex; gap: 16px; margin-bottom: 30px; flex-wrap: wrap; }
        .btn-primary {
            display: inline-block;
            padding: 14px 32px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-primary:hover { background: var(--maroon-light); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(127,3,3,0.3); }
        .btn-secondary {
            display: inline-block;
            padding: 14px 32px;
            background: transparent;
            color: white;
            text-decoration: none;
            border: 2px solid var(--light-blue);
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-secondary:hover { background: var(--light-blue); color: var(--midnight); transform: translateY(-3px); }
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
        }
        .stat-number { font-size: 1.5rem; font-weight: 800; color: var(--midnight); display: block; }
        .stat-label { color: var(--gray-600); font-size: 0.75rem; font-weight: 500; }

        /* ========== CSWDO STATS SECTION ========== */
        .cswdo-stats-section {
            padding: 84px 0;
            background: linear-gradient(180deg, var(--gray-50) 0%, #f7f9fb 100%);
        }
        .stats-grid-large {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
        }
        .stat-card-large {
            background: var(--white);
            padding: 36px 22px;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }
        .stat-card-large::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(127,3,3,0.04), transparent 60%);
            pointer-events: none;
        }
        .stat-card-large:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .stat-card-large i {
            font-size: 3rem;
            color: var(--maroon);
            margin-bottom: 20px;
        }
        .stat-card-large .stat-number-large {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--midnight);
            margin-bottom: 10px;
        }
        .stat-card-large .stat-label-large {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* ========== HOUSING PROGRAM SECTION ========== */
        .housing-program-section {
            padding: 84px 0;
            background: var(--alabaster);
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
            color:var(--maroon);
            margin-bottom: 15px;
        }
        .housing-text p {
            color: var(--gray-600);
            margin-bottom: 30px;
            line-height: 1.6;
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
        }
        .housing-feature i {
            font-size: 1.8rem;
            color: var(--maroon);
        }
        .housing-feature h4 {
            color: var(--midnight);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        .housing-feature p {
            color: var(--gray-600);
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
        }
        .housing-image i {
            font-size: 6rem;
            color: rgba(255,255,255,0.3);
            margin-bottom: 20px;
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
        }

        /* ========== TRANSPARENCY SECTION ========== */
        .transparency-section {
            padding: 84px 0;
            background: var(--gray-50);
        }
        .transparency-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 50px;
        }
        .transparency-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--midnight);
            margin-bottom: 15px;
        }
        .transparency-header p {
            color: var(--gray-600);
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
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 24px 18px;
            box-shadow: var(--shadow-sm);
        }
        .process-step:not(:last-child):after {
            content: "→";
            position: absolute;
            right: -20px;
            top: 30px;
            font-size: 1.5rem;
            color: var(--maroon);
        }
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--midnight);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
        }
        .process-step h4 {
            color: var(--midnight);
            margin-bottom: 10px;
        }
        .process-step p {
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .tracking-demo {
            background: var(--white);
            border-radius: 24px;
            padding: 30px;
            max-width: 520px;
            margin: 0 auto;
            text-align: center;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
        }
        .tracking-demo i {
            font-size: 3rem;
            color: var(--maroon);
            margin-bottom: 15px;
        }
        .tracking-demo .status-updated {
            background: #D4EDDA;
            color: #155724;
            padding: 10px;
            border-radius: var(--radius-element);
            margin-top: 20px;
        }

        /* ========== NOTIFICATION SECTION ========== */
        .notification-section {
            padding: 84px 0;
            background: var(--white);
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
            color: var(--midnight);
            margin-bottom: 15px;
        }
        .notification-text p {
            color: var(--gray-600);
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
            background: var(--gray-50);
            border-radius: 18px;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }
        .notification-channel:hover {
            transform: translateX(5px);
            background: var(--gray-100);
        }
        .notification-channel i {
            font-size: 1.8rem;
            color: var(--maroon);
        }
        .notification-channel h4 {
            color: var(--midnight);
            margin-bottom: 5px;
        }
        .notification-channel p {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin: 0;
        }
        .notification-channel small {
            color: #4CAF50;
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
        }
        .notification-preview h3 {
            margin-bottom: 20px;
        }
        .preview-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-bottom: 15px;
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
        }
        .document-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
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
        }
        .document-feature i {
            font-size: 1.2rem;
            color: var(--tan);
        }
        .document-feature span {
            font-size: 0.9rem;
        }
        .secure-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.7rem;
            margin-bottom: 20px;
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
            transition: all 0.3s;
        }
        .document-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .document-image {
            text-align: center;
        }
        .document-image i {
            font-size: 10rem;
            opacity: 0.3;
        }

        /* ========== SERVICES SECTION ========== */
        .services {
            padding: 84px 0;
            background: var(--gray-50);
        }
        .section-title {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 50px;
            color: var(--midnight);
        }
        .section-title span {
            display: block;
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 400;
            margin-top: 8px;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        .service-card {
            background: var(--white);
            padding: 30px;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }
        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--light-blue);
        }
        .service-card h3 { color: var(--midnight); margin-bottom: 15px; font-size: 1.25rem; font-weight: 700; }
        .service-card p { color: var(--gray-600); margin-bottom: 20px; line-height: 1.6; }
        .learn-more { color: var(--maroon); text-decoration: none; font-weight: 600; transition: color 0.3s; }
        .learn-more:hover { color: var(--maroon-light); }

        /* ========== CTA SECTION ========== */
        .cta-section {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .cta-section h2 { font-size: 2.2rem; font-weight: 800; margin-bottom: 20px; }
        .cta-section p { font-size: 1rem; max-width: 600px; margin: 0 auto 40px; opacity: 0.9; }
        .cta-buttons { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .cta-section .btn-primary { background: var(--maroon); }
        .cta-section .btn-primary:hover { background: var(--maroon-light); }
        .cta-section .btn-secondary { border-color: white; color: white; }
        .cta-section .btn-secondary:hover { background: white; color: var(--midnight); }

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
        .footer-section a { color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.3s; font-size: 0.85rem; }
        .footer-section a:hover { color: var(--tan); }
        .contact-number, .email { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); font-size: 0.85rem; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.75rem; }

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
            .hamburger { display: block; }
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
                    <a href="dashboard.php" class="profile-icon">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            <?php endif; ?>
        </div>
        <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-wrapper">
                <div class="hero-content">
                    <h1>SERVING PALAYAN CITY WITH EXCELLENCE</h1>
                    <h2>Streamlined Social Services For Every Palayan Resident</h2>
                    <p>Schedule appointments, track assistance requests, and access housing programs - all in one integrated platform designed for efficiency and transparency.</p>
                    
                    <div class="hero-buttons">
                        <a href="appointments.php" class="btn-primary">Book Appointment Now →</a>
                        <a href="track-aics.php" class="btn-secondary">Track My Request</a>
                    </div>

                    <div class="status-card">
                        <div class="status-card-content">
                            <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="status-text"><h4>AICS Status Update</h4><p><strong>Request #12345</strong> Approved</p></div>
                        </div>
                    </div>

                    <div class="stats-row">
                        <div class="stat-box"><span class="stat-number">847+</span><span class="stat-label">Appointments Served</span></div>
                        <div class="stat-box"><span class="stat-number">₱1.2M</span><span class="stat-label">AICS Released</span></div>
                    </div>
                </div>

                <div class="hero-image">
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
                    <div class="stat-number-large"><?php echo number_format($stats['total_appointments'] ?? 2847); ?></div>
                    <div class="stat-label-large">Appointments Scheduled</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-hand-holding-heart"></i>
                    <div class="stat-number-large"><?php echo number_format($stats['total_aics'] ?? 1523); ?></div>
                    <div class="stat-label-large">AICS Requests Tracked</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-home"></i>
                    <div class="stat-number-large"><?php echo number_format($stats['total_housing_beneficiaries'] ?? 892); ?></div>
                    <div class="stat-label-large">Housing Beneficiaries</div>
                </div>
                <div class="stat-card-large">
                    <i class="fas fa-smile"></i>
                    <div class="stat-number-large"><?php echo number_format($stats['satisfaction_rate'] ?? 98); ?>%</div>
                    <div class="stat-label-large">User Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Housing Program Section -->
    <section class="housing-program-section">
        <div class="container">
            <div class="housing-content">
                <div class="housing-text">
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
                <div class="housing-image">
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
            <div class="transparency-header">
                <h2>Transparency & Accountability</h2>
                <p>Track your assistance request in real-time. From submission to release, you'll always know where your request stands.</p>
            </div>
            <div class="process-steps">
                <div class="process-step"><div class="step-number">01</div><h4>Submit</h4><p>Fill out the online form and submit your AICS request with required documents.</p></div>
                <div class="process-step"><div class="step-number">02</div><h4>Review</h4><p>CSWDO staff reviews your application and verifies submitted documents.</p></div>
                <div class="process-step"><div class="step-number">03</div><h4>Approval</h4><p>Your request is evaluated and approved based on eligibility criteria.</p></div>
                <div class="process-step"><div class="step-number">04</div><h4>Release</h4><p>Receive notification and claim your assistance at the CSWDO office.</p></div>
            </div>
            <div class="tracking-demo">
                <i class="fas fa-chart-line"></i>
                <h4>Status Updated</h4>
                <div class="status-updated"><strong>Request #AICS-12345</strong> - Approved and ready for release</div>
            </div>
        </div>
    </section>

    <!-- Stay Informed Section - Shows dynamic content based on login status -->
    <section class="notification-section">
        <div class="container">
            <div class="notification-content">
                <div class="notification-text">
                    <h2>Stay Informed with Automated Updates</h2>
                    <p>Receive timely notifications through multiple channels to keep you updated on your appointments, requests, and application status at every step.</p>
                    <div class="notification-channels">
                        <div class="notification-channel">
                            <i class="fas fa-sms"></i>
                            <div><h4>SMS Notifications</h4><p>Receive instant text messages for appointment confirmations, reminders, and status updates directly to your mobile phone.</p><small>2 minutes ago</small></div>
                        </div>
                        <div class="notification-channel">
                            <i class="fas fa-envelope"></i>
                            <div><h4>Email Alerts</h4><p>Get detailed email notifications with complete information about your requests, approvals, and important announcements.</p><small>2 minutes ago</small></div>
                        </div>
                        <div class="notification-channel">
                            <i class="fas fa-bell"></i>
                            <div><h4>In-App Notifications</h4><p>Stay updated with real-time notifications within the platform. Never miss important updates about your applications.</p><small>2 minutes ago</small></div>
                        </div>
                    </div>
                </div>
                <div class="notification-preview">
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
                <div class="document-text">
                    <div class="secure-badge"><i class="fas fa-lock"></i> Secure Upload • 256-bit encryption</div>
                    <h2>Upload Documents Before Your Visit</h2>
                    <p>Our document pre-verification system allows you to upload required documents before your scheduled appointment, significantly reducing processing time and ensuring a smoother experience at the CSWDO office.</p>
                    <div class="document-features">
                        <div class="document-feature"><i class="fas fa-check-circle"></i><span>Faster processing and reduced waiting time</span></div>
                        <div class="document-feature"><i class="fas fa-cloud-upload-alt"></i><span>Secure cloud storage for all documents</span></div>
                        <div class="document-feature"><i class="fas fa-file-alt"></i><span>Reduced office visits and paperwork</span></div>
                        <div class="document-feature"><i class="fas fa-sync-alt"></i><span>Instant verification status updates</span></div>
                    </div>
                    <a href="services.php" class="document-btn">See Required Documents →</a>
                </div>
                <div class="document-image">
                    <i class="fas fa-upload"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services">
        <div class="container">
            <h2 class="section-title">Integrated Services<br><span>Designed for Efficiency</span></h2>
            <div class="services-grid">
                <div class="service-card"><h3>📅 Appointment Scheduling</h3><p>Book your social service appointments online with ease. Choose your preferred date and time, and receive instant confirmation with automated reminders.</p><a href="appointments.php" class="learn-more">Learn More →</a></div>
                <div class="service-card"><h3>📄 Document Pre-Verification</h3><p>Upload required documents before your appointment to reduce processing time. Our secure system verifies your documents in advance for faster service.</p><a href="documents.php" class="learn-more">Learn More →</a></div>
                <div class="service-card"><h3>🤝 AICS Tracking</h3><p>Monitor your Assistance to Individuals in Crisis Situation request status in real-time. Track every step from submission to approval and release.</p><a href="track-aics.php" class="learn-more">Learn More →</a></div>
                <div class="service-card"><h3>🏠 Housing Management</h3><p>Apply for housing programs and track your beneficiary status. Access centralized records and monitor allocation updates through our integrated system.</p><a href="housing.php" class="learn-more">Learn More →</a></div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Get Started?</h2>
            <p>Create your account in under 2 minutes and start accessing social services online. Join thousands of Palayan City residents already using our platform.</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn-primary">Register as Resident</a>
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

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
        
        // Show/hide scroll button based on scroll position
        const scrollBtn = document.getElementById('scrollUpBtn');
        if (window.scrollY > 300) {
            scrollBtn.classList.add('visible');
        } else {
            scrollBtn.classList.remove('visible');
        }
    });

    // Scroll to top when button is clicked
    document.getElementById('scrollUpBtn').addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // Mobile menu toggle
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');
    if (hamburger) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
    }
    </script>
    <script src="js/main.js"></script>
</body>
</html>
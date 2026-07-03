<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get user data if logged in
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
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - One Palayan-Kalinga Hub</title>
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
            50% { transform: translateY(-6px); }
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

        .nav-links a.active::after {
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
            margin-right: 4px;
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

        /* ========== SERVICES HERO ========== */
        .services-hero {
            background: var(--midnight);
            color: white;
            padding: 160px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .services-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.12;
            z-index: 0;
            animation: slowZoom 20s ease-in-out infinite alternate;
        }

        .services-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15,65,74,0.85), rgba(15,65,74,0.7));
            z-index: 0;
        }

        @keyframes slowZoom {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }

        .services-hero .container { position: relative; z-index: 2; }
        .services-hero .badge {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            padding: 4px 16px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--gold-light);
            margin-bottom: 16px;
            backdrop-filter: blur(10px);
        }
        .services-hero h1 { 
            font-size: 3.2rem; 
            font-weight: 800; 
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .services-hero h1 span {
            color: var(--gold-light);
        }
        .services-hero p { 
            font-size: 1.1rem; 
            max-width: 700px; 
            margin: 0 auto; 
            opacity: 0.9;
            line-height: 1.7;
        }

        /* ========== CATEGORIES SECTION ========== */
        .categories-section {
            padding: 80px 0;
            background: var(--bg-primary);
            transition: background 0.4s ease;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .section-header h2 i {
            color: var(--gold);
        }
        .section-header p {
            color: var(--text-muted);
            font-size: 1rem;
            margin-top: 8px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 30px;
        }

        .category-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease both;
        }
        .category-card:nth-child(1) { animation-delay: 0.05s; }
        .category-card:nth-child(2) { animation-delay: 0.1s; }
        .category-card:nth-child(3) { animation-delay: 0.15s; }
        .category-card:nth-child(4) { animation-delay: 0.2s; }
        .category-card:nth-child(5) { animation-delay: 0.25s; }
        .category-card:nth-child(6) { animation-delay: 0.3s; }

        .category-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .card-image-container {
            height: 220px;
            position: relative;
            overflow: hidden;
            background-size: cover;
            background-position: center;
        }

        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
        }
        .slide.active {
            opacity: 1;
        }
        .slide-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.75));
            color: white;
            padding: 30px 20px 15px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .slide-dots {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 5;
        }
        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            margin: 0 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .dot.active {
            background: white;
            transform: scale(1.2);
        }
        .dot:hover {
            background: white;
            transform: scale(1.1);
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(127,3,3,0.2), transparent);
            pointer-events: none;
        }

        .category-content {
            padding: 28px;
        }

        .category-icon {
            width: 60px;
            height: 60px;
            background: var(--alabaster);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .category-card:hover .category-icon {
            background: var(--gold-light);
            transform: scale(1.05) rotate(-5deg);
        }
        .category-icon i {
            font-size: 1.8rem;
            color: var(--maroon);
        }

        .category-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .category-card p {
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        .service-tag {
            background: var(--bg-primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: var(--maroon);
            font-weight: 600;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .category-card:hover .service-tag {
            background: var(--gold-light);
            border-color: var(--gold);
        }

        .category-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--maroon);
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .category-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.02);
            gap: 14px;
            box-shadow: 0 4px 15px rgba(127,3,3,0.3);
        }

        /* ========== QUICK LINKS ========== */
        .quick-links {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        .quick-links::before {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }

        .quick-links h3 {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .quick-links h3 i {
            color: var(--gold-light);
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .quick-link-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: var(--radius-element);
            text-align: center;
            text-decoration: none;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .quick-link-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0,0,0,0.2);
            border-color: var(--gold-light);
        }

        .quick-link-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--gold-light);
            transition: all 0.3s ease;
        }
        .quick-link-card:hover i {
            transform: scale(1.1) rotate(-5deg);
        }

        .quick-link-card h4 {
            margin-bottom: 8px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .quick-link-card p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* ========== FAQ SECTION ========== */
        .faq-section {
            padding: 80px 0;
            background: var(--bg-card);
            transition: background 0.4s ease;
        }

        .faq-section .section-header h2 {
            color: var(--text-primary);
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--gold);
        }

        .faq-question {
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .faq-question:hover {
            color: var(--maroon);
        }

        .faq-question i {
            transition: transform 0.4s ease;
            color: var(--gold);
            font-size: 1.1rem;
        }

        .faq-question.active i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 24px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.3s ease;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .faq-answer.show {
            padding: 0 24px 24px 24px;
            max-height: 300px;
        }

        /* ========== CONTACT CTA ========== */
        .contact-cta {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .contact-cta::before {
            content: '';
            position: absolute;
            top: -40%;
            left: -20%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .contact-cta h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .contact-cta p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .cta-button {
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .cta-button.primary {
            background: white;
            color: var(--maroon);
        }

        .cta-button.primary:hover {
            background: var(--alabaster);
            color: var(--maroon);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .cta-button.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .cta-button.secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-3px) scale(1.02);
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

        .footer-section h3, .footer-section h4 {
            margin-bottom: 20px;
            font-weight: 700;
        }
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
        .contact-number, .email { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            color: rgba(255,255,255,0.7); 
            font-size: 0.85rem; 
        }
        .footer-bottom { 
            text-align: center; 
            padding-top: 20px; 
            border-top: 1px solid rgba(255,255,255,0.1); 
            color: rgba(255,255,255,0.5); 
            font-size: 0.75rem; 
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 968px) {
            .services-hero h1 { font-size: 2rem; }
            .services-hero { padding: 120px 0 60px; }
            .categories-grid { grid-template-columns: 1fr; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                padding: 20px 0;
                gap: 15px;
            }
            .nav-links.active { display: flex; }
            .hamburger { display: flex; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
            .links-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1.5rem; }
            .navbar.scrolled { padding: 0.6rem 1.5rem; }
            .category-card { max-width: 100%; }
            .services-hero h1 { font-size: 1.6rem; }
            .section-header h2 { font-size: 1.6rem; }
            .contact-cta h2 { font-size: 1.6rem; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .cta-button { width: 80%; justify-content: center; }
            .links-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .services-hero h1 { font-size: 1.4rem; }
            .services-hero { padding: 100px 0 40px; }
            .category-content { padding: 20px; }
            .category-card h3 { font-size: 1.2rem; }
            .quick-link-card { padding: 18px; }
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
            <a href="services.php" class="active">Services</a>
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
    <section class="services-hero">
        <div class="container">
            <div class="badge"><i class="fas fa-star"></i> CSWDO Palayan City</div>
            <h1>Our <span>Services</span></h1>
            <p>Comprehensive social welfare services designed to support and empower every resident of Palayan City. Accessible, transparent, and efficient.</p>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="section-header animate-fade-down">
                <h2><i class="fas fa-th-large"></i> Service Categories</h2>
                <p>Choose the service that fits your needs</p>
            </div>
            <div class="categories-grid">
                <!-- Housing Programs Card -->
                <div class="category-card" onclick="window.location.href='housing.php'">
                    <div class="card-image-container" id="housingSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/san-lorenzo.png');"></div>
                            <div class="slide" style="background-image: url('images/housing-sanlorenzo1.png');"></div>
                            <div class="slide" style="background-image: url('images/housing-sanlorenzo2.png');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-home"></i> Affordable Housing for Every Family</div>
                            <div class="slide-dots" id="housingDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-home"></i></div>
                        <h3>Housing Programs</h3>
                        <p>Apply for housing programs and track your beneficiary status. Access affordable housing units including AKAP Village and San Lorenzo Residences designed for Palayan City residents.</p>
                        <div class="service-tags">
                            <span class="service-tag">AKAP Village</span>
                            <span class="service-tag">San Lorenzo</span>
                            <span class="service-tag">Socialized Housing</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='housing.php'">
                            Explore Housing <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- AICS Assistance Card -->
                <div class="category-card" onclick="window.location.href='aics.php'">
                    <div class="card-image-container" id="aicsSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/Aics1.png');"></div>
                            <div class="slide" style="background-image: url('images/aics2.png');"></div>
                            <div class="slide" style="background-image: url('images/aics3.png');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-hand-holding-heart"></i> Assistance in Times of Crisis</div>
                            <div class="slide-dots" id="aicsDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <h3>AICS Assistance</h3>
                        <p>Assistance to Individuals in Crisis Situation. Get medical, financial, burial, educational, transportation, food, and cash relief support for you and your family.</p>
                        <div class="service-tags">
                            <span class="service-tag">Medical</span>
                            <span class="service-tag">Financial</span>
                            <span class="service-tag">Educational</span>
                            <span class="service-tag">Burial</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='aics.php'">
                            View Assistance <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Appointment Services Card -->
                <div class="category-card" onclick="window.location.href='appointments.php'">
                    <div class="card-image-container" id="appointmentSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/ap1.png');"></div>
                            <div class="slide" style="background-image: url('images/ap2.png');"></div>
                            <div class="slide" style="background-image: url('images/ap3.png');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-calendar-check"></i> Schedule Your Visit Online</div>
                            <div class="slide-dots" id="appointmentDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-calendar-check"></i></div>
                        <h3>Appointment Services</h3>
                        <p>Schedule your social service appointments online. Choose your preferred date and time, receive instant confirmation, and get automated reminders via SMS and email.</p>
                        <div class="service-tags">
                            <span class="service-tag">Online Booking</span>
                            <span class="service-tag">SMS Reminders</span>
                            <span class="service-tag">Email Alerts</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='appointments.php'">
                            Book Appointment <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Document Services Card -->
                <div class="category-card" onclick="window.location.href='documents.php'">
                    <div class="card-image-container" id="documentSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/doc2.png');"></div>
                            <div class="slide" style="background-image: url('images/doc1.jpg');"></div>
                            <div class="slide" style="background-image: url('images/doc3.jpg');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-file-alt"></i> Secure Document Management</div>
                            <div class="slide-dots" id="documentDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-file-alt"></i></div>
                        <h3>Document Services</h3>
                        <p>Upload, verify, and manage your documents online. Reduce processing time with our secure pre-verification system before your appointment.</p>
                        <div class="service-tags">
                            <span class="service-tag">256-bit Encryption</span>
                            <span class="service-tag">Pre-verification</span>
                            <span class="service-tag">Cloud Storage</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='documents.php'">
                            Upload Documents <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Senior Citizen Services Card -->
                <div class="category-card" onclick="window.location.href='senior.php'">
                    <div class="card-image-container" id="seniorSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/senior2.png');"></div>
                            <div class="slide" style="background-image: url('images/senior1.png');"></div>
                            <div class="slide" style="background-image: url('images/senior4.png');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-user-clock"></i> Honoring Our Senior Citizens</div>
                            <div class="slide-dots" id="seniorDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-user-clock"></i></div>
                        <h3>Senior Citizen Services</h3>
                        <p>Specialized services for senior citizens including social pension, ID application, benefits assistance, and health programs.</p>
                        <div class="service-tags">
                            <span class="service-tag">Social Pension</span>
                            <span class="service-tag">Senior ID</span>
                            <span class="service-tag">Health Programs</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='senior.php'">
                            View Services <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- PWD Services Card -->
                <div class="category-card" onclick="window.location.href='pwd.php'">
                    <div class="card-image-container" id="pwdSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/PWD1.png');"></div>
                            <div class="slide" style="background-image: url('images/PWD2.png');"></div>
                            <div class="slide" style="background-image: url('images/PWD3.png');"></div>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-wheelchair"></i> Empowering Persons with Disabilities</div>
                            <div class="slide-dots" id="pwdDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="category-icon"><i class="fas fa-wheelchair"></i></div>
                        <h3>PWD Services</h3>
                        <p>Support and assistance for Persons with Disabilities including PWD ID application, benefits, and special programs.</p>
                        <div class="service-tags">
                            <span class="service-tag">PWD ID</span>
                            <span class="service-tag">Benefits</span>
                            <span class="service-tag">Assistive Devices</span>
                        </div>
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='pwd.php'">
                            View Services <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Links -->
    <section class="quick-links">
        <div class="container">
            <h3><i class="fas fa-bolt"></i> Frequently Accessed Services</h3>
            <div class="links-grid">
                <a href="track-aics.php" class="quick-link-card animate-fade-up delay-1">
                    <i class="fas fa-search"></i>
                    <h4>Track AICS</h4>
                    <p>Check your application status</p>
                </a>
                <a href="my-appointments.php" class="quick-link-card animate-fade-up delay-2">
                    <i class="fas fa-calendar-alt"></i>
                    <h4>View Appointments</h4>
                    <p>See your scheduled appointments</p>
                </a>
                <a href="my-housing.php" class="quick-link-card animate-fade-up delay-3">
                    <i class="fas fa-home"></i>
                    <h4>My Housing Status</h4>
                    <p>Track your housing application</p>
                </a>
                <a href="documents.php" class="quick-link-card animate-fade-up delay-4">
                    <i class="fas fa-upload"></i>
                    <h4>Upload Documents</h4>
                    <p>Submit requirements online</p>
                </a>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-header animate-fade-down">
                <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
                <p>Find answers to the most common questions about our services</p>
            </div>
            <div class="faq-container">
                <div class="faq-item animate-fade-up delay-1">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What is AICS and who can avail?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">AICS (Assistance to Individuals in Crisis Situation) is a program that provides medical, financial, burial, and other forms of assistance to individuals and families in crisis. It is available to all residents of Palayan City who are in urgent need of assistance.</div>
                </div>
                <div class="faq-item animate-fade-up delay-2">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How do I schedule an appointment?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">You can schedule an appointment by creating an account and clicking on "Book Appointment" in the navigation menu. Choose your preferred service, date, and time. You'll receive a confirmation via email and SMS.</div>
                </div>
                <div class="faq-item animate-fade-up delay-3">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What documents do I need to prepare?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">Required documents vary per service. Generally, you'll need a valid ID, Barangay Certificate, and service-specific documents (medical certificate for AICS, proof of income for housing, etc.).</div>
                </div>
                <div class="faq-item animate-fade-up delay-4">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How can I track my application?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">You can track your AICS application by clicking on "Track AICS" in the navigation menu and entering your tracking number. You'll also receive notifications when your application status changes.</div>
                </div>
                <div class="faq-item animate-fade-up delay-5">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What are the office hours?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">CSWDO Palayan City is open Monday to Friday, 8:00 AM to 5:00 PM. Appointments are available during these hours. For emergencies, please contact us directly.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact CTA -->
    <section class="contact-cta">
        <div class="container">
            <h2>Need Assistance?</h2>
            <p>Our team is ready to help you with any questions or concerns about our services.</p>
            <div class="cta-buttons">
                <a href="contact.php" class="cta-button primary"><i class="fas fa-envelope"></i> Contact Us</a>
                <a href="faqs.php" class="cta-button secondary"><i class="fas fa-question-circle"></i> View All FAQs</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3>ONE PALAYAN-KALINGA HUB</h3>
                    <p>CSWDO Palayan City — Integrated Social Services Platform</p>
                    <div>
                        <h4>SERVICES</h4>
                        <ul>
                            <li><a href="appointments.php">Appointment Booking</a></li>
                            <li><a href="aics.php">AICS Application</a></li>
                            <li><a href="housing.php">Housing Programs</a></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>RESOURCES</h4>
                    <ul>
                        <li><a href="user-guide.php">User Guide</a></li>
                        <li><a href="faqs.php">FAQs</a></li>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>CONTACT US</h4>
                    <p>City Social Welfare and Development Office<br>Palayan City, Nueva Ecija, Philippines</p>
                    <p class="contact-number"><i class="fas fa-phone"></i> +63 (44) 123 4567</p>
                    <p class="email"><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 One Palayan-Kalinga Hub. All rights reserved. | A project of CSWDO Palayan City</p>
            </div>
        </div>
    </footer>

    <script>
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
        
        // Add transition effect
        document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
    }

    setTheme(currentTheme);

    themeToggleNav.addEventListener('click', function() {
        const current = html.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        
        // Haptic feedback
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

    // Close mobile menu on link click
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            hamburger.classList.remove('active');
        });
    });

    // ========== FAQ TOGGLE ==========
    function toggleFAQ(element) {
        element.classList.toggle('active');
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
    }

    // ========== SLIDESHOW ==========
    function initSlideshow(containerId, dotsId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const slides = container.querySelectorAll('.slide');
        const dotsContainer = document.getElementById(dotsId);
        
        if (slides.length === 0 || !dotsContainer) return;
        
        let currentIndex = 0;
        let interval;
        
        // Clear dots container
        dotsContainer.innerHTML = '';
        
        // Create dots
        for (let i = 0; i < slides.length; i++) {
            const dot = document.createElement('span');
            dot.className = 'dot';
            if (i === 0) dot.classList.add('active');
            dot.onclick = (function(index) {
                return function() {
                    showSlide(index);
                    resetInterval();
                };
            })(i);
            dotsContainer.appendChild(dot);
        }
        
        const dots = dotsContainer.querySelectorAll('.dot');
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            currentIndex = index;
            if (currentIndex >= slides.length) currentIndex = 0;
            if (currentIndex < 0) currentIndex = slides.length - 1;
            
            slides[currentIndex].classList.add('active');
            if (dots[currentIndex]) dots[currentIndex].classList.add('active');
        }
        
        function nextSlide() {
            showSlide(currentIndex + 1);
        }
        
        function resetInterval() {
            if (interval) clearInterval(interval);
            interval = setInterval(nextSlide, 5000);
        }
        
        // Pause on hover
        container.addEventListener('mouseenter', () => {
            if (interval) clearInterval(interval);
        });
        
        container.addEventListener('mouseleave', () => {
            resetInterval();
        });
        
        resetInterval();
    }

    // ========== SMOOTH SCROLL ANIMATIONS ==========
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.category-card, .quick-link-card, .faq-item');
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
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

    // ========== KEYBOARD SHORTCUTS ==========
    document.addEventListener('keydown', function(e) {
        // Ctrl + H for home
        if (e.ctrlKey && e.key === 'h') {
            e.preventDefault();
            window.location.href = 'index.php';
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

    // Initialize all slideshows
    document.addEventListener('DOMContentLoaded', function() {
        initSlideshow('housingSlideshow', 'housingDots');
        initSlideshow('aicsSlideshow', 'aicsDots');
        initSlideshow('appointmentSlideshow', 'appointmentDots');
        initSlideshow('documentSlideshow', 'documentDots');
        initSlideshow('seniorSlideshow', 'seniorDots');
        initSlideshow('pwdSlideshow', 'pwdDots');
    });

    // ========== CONSOLE WELCOME ==========
    console.log('🏛️ One Palayan-Kalinga Hub - Services Page');
    console.log('💡 Keyboard shortcuts: Ctrl+H (Home), ESC (Close Menu)');
    console.log('🌓 Toggle dark/light mode using the sun/moon button in the navbar!');
    </script>
</body>
</html>
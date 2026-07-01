<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - One Palayan-Kalinga Hub</title>
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
            --midnight-light: #1a6a78;
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

        .navbar.scrolled .logo-icon {
            background: var(--maroon);
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

        /* Services Hero */
        .services-hero {
            background: var(--midnight);
            color: white;
            padding: 140px 0 80px;
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
        }

        .services-hero .container { position: relative; z-index: 2; }
        .services-hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 20px; }
        .services-hero p { font-size: 1.1rem; max-width: 800px; margin: 0 auto; opacity: 0.9; }

        /* Category Cards Grid */
        .categories-section {
            padding: 80px 0;
            background: var(--gray-50);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 30px;
        }

        .category-card {
            background: var(--white);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid var(--gray-200);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        /* Card Image / Slideshow Container */
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
            transition: opacity 0.5s ease-in-out;
        }
        .slide.active {
            opacity: 1;
        }
        .slide-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 30px 20px 15px;
            font-size: 0.9rem;
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
            background: rgba(255,255,255,0.5);
            margin: 0 4px;
            cursor: pointer;
        }
        .dot.active {
            background: white;
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
        }

        .category-icon i {
            font-size: 1.8rem;
            color: var(--maroon);
        }

        .category-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--midnight);
            margin-bottom: 12px;
        }

        .category-card p {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .category-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--midnight);
            color: white;
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .category-btn:hover {
            background: var(--midnight-light);
            transform: translateY(-2px);
            gap: 14px;
        }

        /* Service Tags */
        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        .service-tag {
            background: var(--gray-50);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: var(--maroon);
            font-weight: 500;
        }

        /* Quick Links */
        .quick-links {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            padding: 60px 0;
        }

        .quick-links h3 {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .quick-link-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: var(--radius-element);
            text-align: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .quick-link-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .quick-link-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--light-blue);
        }

        .quick-link-card h4 {
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .quick-link-card p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* FAQ Section */
        .faq-section {
            padding: 80px 0;
            background: var(--white);
        }

        .faq-section h3 {
            text-align: center;
            color: var(--maroon);
            margin-bottom: 50px;
            font-size: 2rem;
            font-weight: 800;
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--alabaster);
            border-radius: var(--radius-element);
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }

        .faq-item:hover { box-shadow: var(--shadow-md); }

        .faq-question {
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--midnight);
            font-size: 1rem;
        }

        .faq-question i {
            transition: transform 0.3s;
            color: var(--maroon);
        }

        .faq-question.active i { transform: rotate(180deg); }

        .faq-answer {
            padding: 0 24px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .faq-answer.show {
            padding: 0 24px 24px 24px;
            max-height: 300px;
        }

        /* Contact CTA */
        .contact-cta {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .contact-cta h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .contact-cta p {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-button {
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .cta-button.primary {
            background: white;
            color: var(--maroon);
        }

        .cta-button.primary:hover {
            background: var(--alabaster);
            color: var(--maroon);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .cta-button.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .cta-button.secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-3px);
        }

        /* Footer */
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
        .footer-section a { color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.3s; font-size: 0.85rem; }
        .footer-section a:hover { color: var(--tan); }
        .contact-number, .email { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); font-size: 0.85rem; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.75rem; }

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
            .hamburger { display: block; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1.5rem; }
            .navbar.scrolled { padding: 0.6rem 1.5rem; }
            .category-card { max-width: 100%; }
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
    <section class="services-hero">
        <div class="container">
            <h1>Our Services</h1>
            <p>Comprehensive social welfare services designed to support and empower every resident of Palayan City. Accessible, transparent, and efficient.</p>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="categories-grid">
                <!-- Housing Programs Card - Routes to housing.php -->
                <div class="category-card" onclick="window.location.href='housing.php'">
                    <div class="card-image-container" id="housingSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/san-lorenzo.png');"></div>
                            <div class="slide" style="background-image: url('images/housing-sanlorenzo1.png');"></div>
                            <div class="slide" style="background-image: url('images/housing-sanlorenzo2.png');"></div>
                            <div class="slide-caption">Affordable Housing for Every Family</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='housing.php'">Explore Housing →</button>
                    </div>
                </div>

                <!-- AICS Assistance Card - Routes to aics.php -->
                <div class="category-card" onclick="window.location.href='aics.php'">
                    <div class="card-image-container" id="aicsSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/Aics1.png');"></div>
                            <div class="slide" style="background-image: url('images/aics2.png');"></div>
                            <div class="slide" style="background-image: url('images/aics3.png');"></div>
                            <div class="slide-caption">Assistance in Times of Crisis</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='aics.php'">View Assistance Types →</button>
                    </div>
                </div>

                <!-- Appointment Services Card - Routes to appointments.php -->
                <div class="category-card" onclick="window.location.href='appointments.php'">
                    <div class="card-image-container" id="appointmentSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/ap1.png');"></div>
                            <div class="slide" style="background-image: url('images/ap2.png');"></div>
                            <div class="slide" style="background-image: url('images/ap3.png');"></div>
                            <div class="slide-caption">Schedule Your Visit Online</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='appointments.php'">Book Appointment →</button>
                    </div>
                </div>

                <!-- Document Services Card - Routes to documents.php -->
                <div class="category-card" onclick="window.location.href='documents.php'">
                    <div class="card-image-container" id="documentSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/doc2.png');"></div>
                            <div class="slide" style="background-image: url('images/doc1.jpg');"></div>
                            <div class="slide" style="background-image: url('images/doc3.jpg');"></div>
                            <div class="slide-caption">Secure Document Management</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='documents.php'">Upload Documents →</button>
                    </div>
                </div>

                <!-- Senior Citizen Services Card - Routes to senior.php -->
                <div class="category-card" onclick="window.location.href='senior.php'">
                    <div class="card-image-container" id="seniorSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/senior2.png');"></div>
                            <div class="slide" style="background-image: url('images/senior1.png');"></div>
                            <div class="slide" style="background-image: url('images/senior4.png');"></div>
                            <div class="slide-caption">Honoring Our Senior Citizens</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='senior.php'">View Services →</button>
                    </div>
                </div>

                <!-- PWD Services Card - Routes to pwd.php -->
                <div class="category-card" onclick="window.location.href='pwd.php'">
                    <div class="card-image-container" id="pwdSlideshow">
                        <div class="slideshow-container">
                            <div class="slide active" style="background-image: url('images/PWD1.png');"></div>
                            <div class="slide" style="background-image: url('images/PWD2.png');"></div>
                            <div class="slide" style="background-image: url('images/PWD3.png');"></div>
                            <div class="slide-caption">Empowering Persons with Disabilities</div>
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
                        <button class="category-btn" onclick="event.stopPropagation(); window.location.href='pwd.php'">View Services →</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Links -->
    <section class="quick-links">
        <div class="container">
            <h3>Frequently Accessed Services</h3>
            <div class="links-grid">
                <a href="track-aics.php" class="quick-link-card"><i class="fas fa-search"></i><h4>Track AICS</h4><p>Check your application status</p></a>
                <a href="my-appointments.php" class="quick-link-card"><i class="fas fa-calendar-alt"></i><h4>View Appointments</h4><p>See your scheduled appointments</p></a>
                <a href="my-housing.php" class="quick-link-card"><i class="fas fa-home"></i><h4>My Housing Status</h4><p>Track your housing application</p></a>
                <a href="documents.php" class="quick-link-card"><i class="fas fa-upload"></i><h4>Upload Documents</h4><p>Submit requirements online</p></a>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h3>Frequently Asked Questions</h3>
            <div class="faq-container">
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>What is AICS and who can avail?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">AICS (Assistance to Individuals in Crisis Situation) is a program that provides medical, financial, burial, and other forms of assistance to individuals and families in crisis. It is available to all residents of Palayan City who are in urgent need of assistance.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>How do I schedule an appointment?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">You can schedule an appointment by creating an account and clicking on "Book Appointment" in the navigation menu. Choose your preferred service, date, and time. You'll receive a confirmation via email and SMS.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>What documents do I need to prepare?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Required documents vary per service. Generally, you'll need a valid ID, Barangay Certificate, and service-specific documents (medical certificate for AICS, proof of income for housing, etc.).</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>How can I track my application?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">You can track your AICS application by clicking on "Track AICS" in the navigation menu and entering your tracking number. You'll also receive notifications when your application status changes.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>What are the office hours?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">CSWDO Palayan City is open Monday to Friday, 8:00 AM to 5:00 PM. Appointments are available during these hours. For emergencies, please contact us directly.</div></div>
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
                <div class="footer-section"><h3>ONE PALAYAN-KALINGA HUB</h3><p>CSWDO Palayan City — Integrated Social Services Platform</p><div><h4>SERVICES</h4><ul><li><a href="appointments.php">Appointment Booking</a></li><li><a href="aics.php">AICS Application</a></li><li><a href="housing.php">Housing Programs</a></li></ul></div></div>
                <div class="footer-section"><h4>RESOURCES</h4><ul><li><a href="user-guide.php">User Guide</a></li><li><a href="faqs.php">FAQs</a></li><li><a href="privacy-policy.php">Privacy Policy</a></li><li><a href="terms.php">Terms of Service</a></li></ul></div>
                <div class="footer-section"><h4>CONTACT US</h4><p>City Social Welfare and Development Office<br>Palayan City, Nueva Ecija, Philippines</p><p class="contact-number"><i class="fas fa-phone"></i> +63 (44) 123 4567</p><p class="email"><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2026 One Palayan-Kalinga Hub. All rights reserved. | A project of CSWDO Palayan City</p></div>
        </div>
    </footer>

    <script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
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

    // FAQ Toggle
    function toggleFAQ(element) {
        element.classList.toggle('active');
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
    }

    // Generic slideshow initializer
    function initSlideshow(containerId, dotsId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const slides = container.querySelectorAll('.slide');
        const dotsContainer = document.getElementById(dotsId);
        
        if (slides.length === 0 || !dotsContainer) return;
        
        let currentIndex = 0;
        let interval;
        
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
            interval = setInterval(nextSlide, 4000);
        }
        
        resetInterval();
    }

    // Initialize all slideshows
    document.addEventListener('DOMContentLoaded', function() {
        initSlideshow('housingSlideshow', 'housingDots');
        initSlideshow('aicsSlideshow', 'aicsDots');
        initSlideshow('appointmentSlideshow', 'appointmentDots');
        initSlideshow('documentSlideshow', 'documentDots');
        initSlideshow('seniorSlideshow', 'seniorDots');
        initSlideshow('pwdSlideshow', 'pwdDots');
    });
    </script>
</body>
</html>
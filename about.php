<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - One Palayan-Kalinga Hub</title>
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

        /* ========== NAVBAR STYLES - SAME AS INDEX.PHP ========== */
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

        /* User Menu */
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
        .profile-icon i {
            font-size: 1.1rem;
        }
        .profile-icon span {
            font-size: 0.85rem;
        }
        .profile-icon:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
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
            transition: all 0.3s;
        }
        .logout-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .navbar.scrolled .profile-icon {
            background: var(--alabaster);
            color: var(--midnight);
        }
        .navbar.scrolled .profile-icon:hover {
            background: var(--tan);
        }
        .navbar.scrolled .logout-icon {
            background: var(--gray-100);
            color: var(--maroon);
        }
        .navbar.scrolled .logout-icon:hover {
            background: var(--gray-200);
        }

        .login-btn {
            background: var(--maroon);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s;
        }
        .login-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

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
            transition: all 0.3s;
        }
        .register-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

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
        }

        .hamburger span {
            display: block;
            width: 25px;
            height: 2px;
            margin: 5px 0;
            background: white;
            transition: all 0.3s;
        }

        .navbar.scrolled .hamburger span {
            background: var(--midnight);
        }

        /* About Hero */
        .about-hero {
            background: var(--midnight);
            color: white;
            padding: 140px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '🏛️';
            font-size: 15rem;
            position: absolute;
            right: -50px;
            bottom: -50px;
            opacity: 0.06;
            transform: rotate(15deg);
            pointer-events: none;
        }

        .about-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
        }

        .about-hero p {
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
            position: relative;
        }

        /* Mission Vision Section */
        .mission-vision-section {
            padding: 80px 0;
            background: var(--gray-50);
        }

        .mv-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .mission-card, .vision-card {
            background: var(--white);
            padding: 40px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .mission-card:hover, .vision-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .mission-card {
            border-bottom: 4px solid var(--maroon);
        }

        .vision-card {
            border-bottom: 4px solid var(--midnight);
        }

        .mv-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .mission-card .mv-icon {
            color: var(--maroon);
        }

        .vision-card .mv-icon {
            color: var(--midnight);
        }

        .mission-card h2, .vision-card h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 15px;
        }

        .mission-card h2 {
            color: var(--maroon);
        }

        .vision-card h2 {
            color: var(--midnight);
        }

        .mission-card h2::after, .vision-card h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
        }

        .mission-card h2::after {
            background: var(--maroon);
        }

        .vision-card h2::after {
            background: var(--midnight);
        }

        .mv-content {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--gray-600);
        }

        .mv-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .mv-list li {
            padding: 10px 0;
            padding-left: 35px;
            position: relative;
            font-size: 1rem;
        }

        .mv-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--maroon);
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* History Section */
        .history-section {
            padding: 80px 0;
            background: var(--alabaster);
        }

        .history-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .history-icon {
            font-size: 4rem;
            color: var(--maroon);
            margin-bottom: 20px;
        }

        .history-content h2 {
            color: var(--midnight);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .history-text {
            color: var(--gray-600);
            line-height: 1.8;
            font-size: 1rem;
        }

        .history-text p {
            margin-bottom: 20px;
        }

        /* Organizational Chart Section */
        .org-chart-section {
            padding: 80px 0;
            background: var(--gray-50);
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            color: var(--midnight);
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .section-header p {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .org-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .dept-head {
            text-align: center;
            margin-bottom: 50px;
        }

        .head-card {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 35px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-lg);
            display: inline-block;
            min-width: 350px;
        }

        .head-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .head-name {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .head-position {
            font-size: 1rem;
            opacity: 0.9;
        }

        .org-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .org-card {
            background: var white;
            border-radius: var(--radius-element);
            padding: 30px 25px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
        }

        .org-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--light-blue);
        }

        .org-avatar {
            width: 80px;
            height: 80px;
            background: var(--midnight);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 2rem;
        }

        .org-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--midnight);
            margin-bottom: 5px;
        }

        .org-position {
            color: var(--maroon);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        /* Values Section */
        .values-section {
            padding: 80px 0;
            background: var(--alabaster);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .value-card {
            background: var(--gray-50);
            padding: 35px 25px;
            border-radius: var(--radius-element);
            text-align: center;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .value-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
            border-color: var(--light-blue);
        }

        .value-icon {
            width: 70px;
            height: 70px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
        }

        .value-card h3 {
            color: var(--midnight);
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .value-card p {
            color: var(--gray-600);
            line-height: 1.6;
            font-size: 0.9rem;
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

        .footer-section h3,
        .footer-section h4 {
            margin-bottom: 20px;
            font-weight: 700;
        }

        .footer-section h3 {
            color: var(--tan);
            font-size: 1.2rem;
        }

        .footer-section h4 {
            color: var(--light-blue);
            font-size: 1rem;
        }

        .footer-section p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.85rem;
        }

        .footer-section a:hover {
            color: var(--tan);
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

        @media (max-width: 968px) {
            .about-hero h1 { font-size: 2rem; }
            .about-hero { padding: 120px 0 60px; }
            .mv-container { grid-template-columns: 1fr; gap: 30px; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                padding: 20px 0;
                gap: 15px;
            }
            .nav-links.active { display: flex; }
            .hamburger { display: block; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1.5rem; }
            .navbar.scrolled { padding: 0.6rem 1.5rem; }
            .head-card { min-width: auto; width: 100%; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
            .values-grid { grid-template-columns: 1fr; }
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
            <a href="about.php" class="active">About</a>
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
        <button class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="container">
            <h1>About CSWDO Palayan City</h1>
            <p>Committed to serving the vulnerable and socially dysfunctional sectors of society through care, protection, rehabilitation, development, and integration.</p>
        </div>
    </section>

    <!-- Mission and Vision Section -->
    <section class="mission-vision-section">
        <div class="container">
            <div class="mv-container">
                <div class="mission-card">
                    <div class="mv-icon"><i class="fas fa-bullseye"></i></div>
                    <h2>MISSION</h2>
                    <div class="mv-content">
                        <p>Care, Protect, Rehabilitate, Develop and Integrate into the mainstream the vulnerable and socially dysfunctional sectors of society</p>
                    </div>
                </div>

                <div class="vision-card">
                    <div class="mv-icon"><i class="fas fa-eye"></i></div>
                    <h2>VISION</h2>
                    <div class="mv-content">
                        <p>Empowered Individuals, Families and Communities:</p>
                        <ul class="mv-list">
                            <li>With improved quality of Life performing their expected roles</li>
                            <li>Able to mobilize resources to meet their basic needs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- History Section -->
    <section class="history-section">
        <div class="container">
            <div class="history-content">
                <div class="history-icon"><i class="fas fa-landmark"></i></div>
                <h2>Our History</h2>
                <div class="history-text">
                    <p>The City Social Welfare and Development Office (CSWDO) of Palayan City has been at the forefront of social services, dedicated to improving the quality of life for every resident. Through the years, we have evolved to meet the changing needs of our community, always with the goal of empowering individuals, families, and communities.</p>
                    <p>Today, we continue this legacy through the One Palayan-Kalinga Hub, a digital transformation initiative that brings our services closer to the people we serve.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Organizational Chart Section -->
    <section class="org-chart-section">
        <div class="container">
            <div class="section-header">
                <h2>Organizational Chart</h2>
                <p>Meet the dedicated team behind CSWDO Palayan City</p>
            </div>

            <div class="org-container">
                <div class="dept-head">
                    <div class="head-card">
                        <div class="head-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="head-name">MARIA TERESA M. RAMOS, RSW</div>
                        <div class="head-position">City Social Welfare and Development Officer</div>
                    </div>
                </div>

                <div class="org-grid">
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user"></i></div>
                        <div class="org-name">JOCELYN M. AGULTO, RSW</div>
                        <div class="org-position">City Social Welfare Officer IV</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user"></i></div>
                        <div class="org-name">MA. VICTORIA A. VENTURA, RSW</div>
                        <div class="org-position">Social Welfare Officer III</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user"></i></div>
                        <div class="org-name">JONALYN C. CONCEPCION</div>
                        <div class="org-position">Administrative Officer V</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user"></i></div>
                        <div class="org-name">JOHN DEXTER A. VILLAMIN</div>
                        <div class="org-position">Project Development Officer II</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values Section -->
    <section class="values-section">
        <div class="container">
            <div class="section-header">
                <h2>Our Core Values</h2>
                <p>Guiding principles that shape our service delivery</p>
            </div>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-heart"></i></div>
                    <h3>Malasakit</h3>
                    <p>Genuine care and compassion for every individual we serve.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <h3>Integridad</h3>
                    <p>Honesty and transparency in all our transactions.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-users"></i></div>
                    <h3>Pakikipagkapwa</h3>
                    <p>Building strong relationships within the community.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Kahusayan</h3>
                    <p>Continuous improvement and excellence in service delivery.</p>
                </div>
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
                    <p>City Social Welfare and Development Office<br>
                    Palayan City, Nueva Ecija, Philippines</p>
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
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
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
</body>
</html>
<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Requirements - One Palayan-Kalinga Hub</title>
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
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.5;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

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

        .logo-icon {
            width: 44px;
            height: 44px;
            background: var(--maroon);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            transition: all 0.3s;
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

        /* Requirements Hero */
        .requirements-hero {
            background: var(--midnight);
            color: white;
            padding: 140px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .requirements-hero h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 20px; }
        .requirements-hero p { font-size: 1.1rem; max-width: 800px; margin: 0 auto; opacity: 0.9; }

        .requirements-content { padding: 60px 0; background: var(--gray-50); }
        .requirements-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

        .language-toggle {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .lang-btn {
            padding: 8px 16px;
            border: 2px solid var(--maroon);
            background: transparent;
            color: var(--maroon);
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .lang-btn.active { background: var(--maroon); color: white; }
        .lang-btn:hover { background: var(--maroon); color: white; }
        .print-btn {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 8px 16px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .print-btn:hover { background: var(--gray-200); opacity: 0.8; }

        .office-info { text-align: center; margin-bottom: 30px; }
        .office-info h3 { color: var(--midnight); font-size: 1.5rem; font-weight: 800; margin-bottom: 5px; }
        .office-info .city { color: var(--maroon); font-size: 1rem; font-weight: 500; }

        .header-with-icon {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--maroon);
        }
        .header-with-icon i { font-size: 2rem; color: var(--maroon); }
        .header-with-icon h2 { color: var(--midnight); font-size: 1.8rem; font-weight: 700; margin: 0; }

        .requirements-list { list-style: none; padding: 0; margin: 30px 0; }
        .requirements-list li {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            background: var(--gray-50);
            border-radius: var(--radius-element);
            border-left: 4px solid var(--maroon);
            transition: all 0.3s;
        }
        .requirements-list li:hover { transform: translateX(5px); box-shadow: var(--shadow-sm); }
        .requirements-list li .number {
            background: var(--maroon);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        .requirements-list li .content { flex: 1; }
        .requirements-list li .content strong {
            color: var(--midnight);
            display: block;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        .requirements-list li .content .english {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-style: italic;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed var(--gray-200);
        }

        .additional-info {
            margin-top: 40px;
            padding: 25px;
            background: rgba(216,186,152,0.15);
            border-radius: var(--radius-element);
            border-left: 4px solid var(--tan);
        }
        .additional-info h4 { color: var(--midnight); margin-bottom: 15px; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .additional-info p { color: var(--gray-600); margin-bottom: 10px; line-height: 1.6; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; }
        .additional-info i { color: var(--maroon); }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .btn-primary, .btn-secondary {
            padding: 12px 28px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary { background: var(--maroon); color: white; }
        .btn-primary:hover { background: var(--maroon-light); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(127,3,3,0.3); }
        .btn-secondary { background: var(--gray-200); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-200); opacity: 0.8; transform: translateY(-2px); }

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

        .filipino-only { display: none; }
        .show-filipino .filipino-only { display: inline; }
        .show-filipino .english-only { display: none; }

        @media (max-width: 968px) {
            .requirements-hero { padding: 120px 0 60px; }
            .requirements-hero h1 { font-size: 2rem; }
            .requirements-container { padding: 20px; margin: 0 15px; }
            .header-with-icon h2 { font-size: 1.3rem; }
            .requirements-list li { flex-direction: column; }
            .requirements-list li .number { margin-bottom: 10px; }
            .action-buttons { flex-direction: column; }
            .action-buttons a { justify-content: center; }
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
            .language-toggle { justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- Navbar - Same as index.php -->
    <nav class="navbar" id="navbar">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="logo-text"><h2>ONE PALAYAN-KALINGA HUB</h2><span>CSWDO Palayan City</span></div>
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
    <section class="requirements-hero">
        <div class="container">
            <h1>Housing Program Requirements</h1>
            <p>Mga kinakailangang dokumento para sa housing program / Required documents for housing application</p>
        </div>
    </section>

    <!-- Requirements Content -->
    <section class="requirements-content">
        <div class="container">
            <div class="requirements-container" id="requirementsContainer">
                <!-- Language Toggle -->
                <div class="language-toggle">
                    <button class="lang-btn active" onclick="setLanguage('filipino')">🇵🇭 Filipino</button>
                    <button class="lang-btn" onclick="setLanguage('english')">🇬🇧 English</button>
                    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / I-print</button>
                </div>

                <!-- Office Header -->
                <div class="office-info">
                    <h3>CITY SOCIAL WELFARE AND DEVELOPMENT OFFICE</h3>
                    <div class="city">PALAYAN CITY</div>
                </div>

                <!-- Title with Icon -->
                <div class="header-with-icon">
                    <i class="fas fa-file-alt"></i>
                    <h2 class="filipino-only">MGA KINAKAILANGAN</h2>
                    <h2 class="english-only">REQUIREMENTS</h2>
                </div>

                <!-- Requirements List -->
                <ul class="requirements-list">
                    <li><span class="number">1</span><div class="content"><strong class="filipino-only">KASULATAN NG LAYUNIN (LETTER OF INTENT) O APLIKASYON NA NAGLALAMAN NG MGA PANGALAN NG LAHAT NG KASAPI O INDIBIDWAL</strong><strong class="english-only">LETTER OF INTENT OR APPLICATION CONTAINING NAMES OF ALL HOUSEHOLD MEMBERS OR INDIVIDUALS</strong><div class="english english-only">Formal letter requesting housing assistance</div></div></li>
                    <li><span class="number">2</span><div class="content"><strong class="filipino-only">BARANGAY CLEARANCE / CERTIFICATE OF INDIGENCY</strong><strong class="english-only">BARANGAY CLEARANCE / CERTIFICATE OF INDIGENCY</strong><div class="english english-only">Certificate from your barangay proving residency and financial status</div></div></li>
                    <li><span class="number">3</span><div class="content"><strong class="filipino-only">KATIBAYAN NG PAGKAKAKILANLAN</strong><strong class="english-only">PROOF OF IDENTITY</strong><div class="filipino-only" style="color: var(--gray-600); font-size: 0.85rem; margin-top: 5px;">(Tulad ng VOTER'S CERTIFICATION, SENIOR CITIZEN ID, PHILSYS ID, PWD ID, SOLOPARENT, 4P'S ID O ANUMANG GOVERNMENT ISSUED ID) AT KATUNAYAN NG PAGIGING RESIDENTE NG LUNGSOD NG PALAYAN</div><div class="english english-only">(Such as VOTER'S CERTIFICATION, SENIOR CITIZEN ID, PHILSYS ID, PWD ID, SOLOPARENT ID, 4P'S ID OR ANY GOVERNMENT ISSUED ID) AND PROOF OF RESIDENCY IN PALAYAN CITY</div></div></li>
                    <li><span class="number">4</span><div class="content"><strong class="filipino-only">KASULATAN NG KAPAHINTULUTAN NG MAGULANG O KAMAG-ANAK NA MAY PAG-AARI SA LUPA SA PAGPAPATAYO NG BAHAY</strong><strong class="english-only">WRITTEN AFFIDAVIT OF CONSENT FROM PARENTS OR RELATIVES WHO OWN THE LAND FOR HOUSE CONSTRUCTION</strong><div class="english english-only">If building on relative's land, you need their written permission</div></div></li>
                    <li><span class="number">5</span><div class="content"><strong class="filipino-only">LARAWAN NG SARILING BAHAY O BAHAY NG MGA MAGULANG</strong><strong class="english-only">PHOTO OF YOUR OWN HOUSE OR PARENTS' HOUSE</strong><div class="english english-only">Current photos of your living situation</div></div></li>
                    <li><span class="number">6</span><div class="content"><strong class="filipino-only">FAMILY INTAKE NA MAY KASAMANG ASSESSMENT NG SOCIAL WORKER</strong><strong class="english-only">FAMILY INTAKE WITH SOCIAL WORKER'S ASSESSMENT</strong><div class="english english-only">To be completed during interview with social worker</div></div></li>
                    <li><span class="number">7</span><div class="content"><strong class="filipino-only">PROOF OF BILLING</strong><strong class="english-only">PROOF OF BILLING</strong><div class="english english-only">Recent utility bills showing your current address</div></div></li>
                    <li><span class="number">8</span><div class="content"><strong class="filipino-only">TAX DECLARATION</strong><strong class="english-only">TAX DECLARATION</strong><div class="english english-only">If applicable, proof of property tax payments</div></div></li>
                    <li><span class="number">9</span><div class="content"><strong class="filipino-only">IBA PANG MGA KINAKAILANGANG DOKUMENTO NA MAAARING HINGIN PARA SA PAG-APRUBA</strong><strong class="english-only">OTHER REQUIRED DOCUMENTS THAT MAY BE REQUESTED FOR APPROVAL</strong><div class="english english-only">Additional documents as needed based on your specific situation</div></div></li>
                </ul>

                <!-- Additional Information -->
                <div class="additional-info">
                    <h4><i class="fas fa-info-circle"></i> Paalala / Reminders</h4>
                    <p><i class="fas fa-check-circle"></i> Siguraduhing kumpleto ang mga dokumento bago mag-apply / Ensure all documents are complete before applying</p>
                    <p><i class="fas fa-check-circle"></i> Magdala ng photocopy at original ng bawat dokumento / Bring photocopy and original of each document</p>
                    <p><i class="fas fa-check-circle"></i> Ang mga dokumento ay dapat nasa maayos na kondisyon / Documents should be in good condition</p>
                    <p><i class="fas fa-check-circle"></i> Maaaring humingi ng karagdagang dokumento kung kinakailangan / Additional documents may be requested if necessary</p>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if (isLoggedIn()): ?>
                        <a href="apply-housing.php" class="btn-primary"><i class="fas fa-paper-plane"></i> <span class="filipino-only">Mag-apply Na</span><span class="english-only">Apply Now</span></a>
                    <?php else: ?>
                        <a href="login.php" class="btn-primary"><i class="fas fa-sign-in-alt"></i> <span class="filipino-only">Mag-login para Mag-apply</span><span class="english-only">Login to Apply</span></a>
                    <?php endif; ?>
                    <a href="housing.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> <span class="filipino-only">Bumalik sa Housing Programs</span><span class="english-only">Back to Housing Programs</span></a>
                </div>
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

    function setLanguage(lang) {
        const container = document.getElementById('requirementsContainer');
        const langButtons = document.querySelectorAll('.lang-btn');
        if (lang === 'filipino') {
            container.classList.add('show-filipino');
            langButtons[0].classList.add('active');
            langButtons[1].classList.remove('active');
        } else {
            container.classList.remove('show-filipino');
            langButtons[1].classList.add('active');
            langButtons[0].classList.remove('active');
        }
        localStorage.setItem('preferredLanguage', lang);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const savedLang = localStorage.getItem('preferredLanguage') || 'filipino';
        setLanguage(savedLang);
    });

    window.onbeforeprint = function() { document.getElementById('requirementsContainer').classList.remove('show-filipino'); };
    window.onafterprint = function() { const savedLang = localStorage.getItem('preferredLanguage') || 'filipino'; setLanguage(savedLang); };
    </script>
</body>
</html>
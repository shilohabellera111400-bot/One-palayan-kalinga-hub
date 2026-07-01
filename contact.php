<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $mailer = new Mailer();
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .header { background: #003366; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { text-align: center; padding: 20px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Contact Form Message</h2>
                    </div>
                    <div class='content'>
                        <p><strong>Name:</strong> $name</p>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Subject:</strong> $subject</p>
                        <p><strong>Message:</strong></p>
                        <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    <div class='footer'>
                        <p>Sent from One Palayan-Kalinga Hub</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $result = $mailer->send('cswdo@palayancity.gov.ph', "Contact Form: $subject", $body);
            
            if ($result['success']) {
                $success = "Thank you for your message! We will get back to you soon.";
            } else {
                $error = "Failed to send message. Please try again later.";
            }
        } catch (Exception $e) {
            $error = "Failed to send message. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - One Palayan-Kalinga Hub</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.5;
        }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

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

        /* Hero Section */
        .page-header {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 140px 0 60px;
            text-align: center;
        }
        .page-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; }
        .page-header p { font-size: 1rem; max-width: 600px; margin: 0 auto; opacity: 0.9; }

        /* Contact Layout */
        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 40px;
            padding: 60px 0;
        }

        /* Contact Info Cards */
        .info-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .info-section h2 {
            color: var(--midnight);
            font-size: 1.5rem;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--maroon);
            padding-bottom: 12px;
        }
        .contact-info-item {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: var(--gray-50);
            border-radius: var(--radius-element);
            transition: all 0.3s;
        }
        .contact-info-item:hover { transform: translateX(5px); background: var(--gray-100); }
        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--maroon);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }
        .contact-details h3 {
            color: var(--midnight);
            font-size: 1rem;
            margin-bottom: 5px;
        }
        .contact-details p {
            color: var(--gray-600);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Office Hours */
        .office-hours {
            margin-top: 30px;
            padding: 20px;
            background: var(--alabaster);
            border-radius: var(--radius-element);
        }
        .office-hours h3 {
            color: var(--maroon);
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hours-list { list-style: none; }
        .hours-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--gray-200);
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .hours-list li:last-child { border-bottom: none; }

        /* Map */
        .map-container {
            margin-top: 30px;
            border-radius: var(--radius-element);
            overflow: hidden;
        }
        .map-container iframe {
            width: 100%;
            height: 250px;
            border: none;
        }

        /* Contact Form */
        .form-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .form-section h2 {
            color: var(--midnight);
            font-size: 1.5rem;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--maroon);
            padding-bottom: 12px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .form-group label i { color: var(--maroon); margin-right: 5px; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-element);
            font-size: 0.9rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--midnight);
            box-shadow: 0 0 0 3px rgba(15,65,74,0.1);
        }
        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover { background: var(--maroon-light); transform: translateY(-2px); }

        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
        }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .social-link {
            width: 40px;
            height: 40px;
            background: var(--gray-200);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon);
            transition: all 0.3s;
            text-decoration: none;
        }
        .social-link:hover { background: var(--maroon); color: white; transform: translateY(-3px); }

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

        @media (max-width: 992px) {
            .contact-layout { grid-template-columns: 1fr; gap: 30px; }
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
            .page-header { padding: 120px 0 40px; }
            .page-header h1 { font-size: 1.8rem; }
            .contact-info-item { flex-direction: column; text-align: center; }
            .contact-icon { margin: 0 auto; }
            .hours-list li { flex-direction: column; text-align: center; gap: 5px; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
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
            <a href="contact.php" class="active">Contact</a>
            
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

    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
            <p>We're here to help! Reach out to us for any questions or concerns.</p>
        </div>
    </div>
    
    <div class="container">
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="contact-layout">
            <!-- Contact Information -->
            <div class="info-section">
                <h2><i class="fas fa-address-card"></i> Contact Information</h2>
                
                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="contact-details">
                        <h3>Office Address</h3>
                        <p>City Social Welfare and Development Office<br>Palayan City, Nueva Ecija, Philippines 3132</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                    <div class="contact-details">
                        <h3>Phone Numbers</h3>
                        <p>📞 (044) 123-4567<br>📞 8962-2813 / 8951-7433</p>
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div class="contact-details">
                        <h3>Email Addresses</h3>
                        <p>📧 cswdo@palayancity.gov.ph<br>📧 info@palayancity.gov.ph</p>
                    </div>
                </div>
                
                <div class="office-hours">
                    <h3><i class="fas fa-clock"></i> Office Hours</h3>
                    <ul class="hours-list">
                        <li><span>Monday - Friday:</span><span>8:00 AM - 5:00 PM</span></li>
                        <li><span>Saturday - Sunday:</span><span>Closed</span></li>
                        <li><span>Lunch Break:</span><span>12:00 NN - 1:00 PM</span></li>
                        <li><span>Holidays:</span><span>Closed</span></li>
                    </ul>
                </div>
                
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-viber"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-facebook-messenger"></i></a>
                </div>
                
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d123456!2d121.0!3d15.5!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTXCsDMwJzAwLjAiTiAxMjHCsDAwJzAwLjAiRQ!5e0!3m2!1sen!2sph!4v1234567890" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="form-section">
                <h2><i class="fas fa-paper-plane"></i> Send us a Message</h2>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Your Name *</label>
                        <input type="text" name="name" required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" required placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Subject *</label>
                        <input type="text" name="subject" required placeholder="What is this regarding?">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Message *</label>
                        <textarea name="message" rows="6" required placeholder="Please provide details about your inquiry..."></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: var(--gray-50); border-radius: var(--radius-element);">
                    <p style="font-size: 0.8rem; color: var(--gray-600);">
                        <i class="fas fa-clock"></i> We typically respond within 1-2 business days.
                        For urgent concerns, please call our office directly.
                    </p>
                </div>
            </div>
        </div>
    </div>

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

        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
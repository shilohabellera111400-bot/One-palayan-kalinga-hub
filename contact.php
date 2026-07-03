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
                    .header { background: #0F414A; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; background: #f9f9f9; border: 1px solid #eee; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 0.8rem; }
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
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== MODERN PROFESSIONAL PALETTE & VARIABLES ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-dark: #5a0202;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            
            /* Light mode default tokens */
            --bg-primary: #F8F9FC;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #374151;
            --text-muted: #6B7A8A;
            --border-color: #E4E7EF;
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 12px 30px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.08);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        /* Dark mode overrides */
        body.dark-mode, [data-theme="dark"] body {
            --bg-primary: #0D1117;
            --bg-secondary: #161B22;
            --bg-card: #161B22;
            --text-primary: #F0F6FC;
            --text-secondary: #C9D1D9;
            --text-muted: #8B949E;
            --border-color: #30363D;
            --white: #161B22;
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.4);
            --alabaster: #21262D;
            --midnight: #0F414A;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header Fix for Floating Navbar */
        .header-spacer {
            height: 90px;
        }
        @media (max-width: 768px) {
            .header-spacer { height: 75px; }
        }

        /* Hero Header */
        .page-header {
            background: linear-gradient(135deg, var(--midnight) 0%, #155d6a 50%, var(--maroon-dark) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            border-bottom: 5px solid var(--maroon);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '✉️';
            font-size: 16rem;
            position: absolute;
            left: -20px;
            bottom: -50px;
            opacity: 0.05;
            transform: rotate(-15deg);
            pointer-events: none;
        }

        .page-header h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 850;
            margin-bottom: 16px;
            letter-spacing: -0.03em;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .page-header p {
            font-size: clamp(1rem, 2vw, 1.15rem);
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.95;
        }

        /* Contact Layout */
        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 40px;
            padding: 60px 0;
        }

        /* Contact Info Cards */
        .info-section {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .info-section h2 {
            color: var(--text-primary);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--maroon);
            padding-bottom: 12px;
        }

        .contact-info-item {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .contact-info-item:hover {
            transform: translateX(6px);
            box-shadow: var(--shadow-sm);
            border-color: var(--maroon);
        }

        .contact-icon {
            width: 54px;
            height: 54px;
            background: var(--maroon);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.2);
        }

        body.dark-mode .contact-icon {
            background: #ff6b6b;
            color: #0D1117;
            box-shadow: 0 4px 10px rgba(255, 107, 107, 0.2);
        }

        .contact-details h3 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .contact-details p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Office Hours */
        .office-hours {
            margin-top: 30px;
            padding: 24px;
            background: var(--alabaster);
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
        }

        .office-hours h3 {
            color: var(--maroon);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .office-hours h3 {
            color: #ff6b6b;
        }

        .hours-list {
            list-style: none;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .hours-list li:last-child {
            border-bottom: none;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        .social-link {
            width: 44px;
            height: 44px;
            background: var(--bg-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            text-decoration: none;
            font-size: 1.1rem;
        }

        body.dark-mode .social-link {
            color: #ff6b6b;
        }

        .social-link:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-4px);
            border-color: var(--maroon);
        }

        body.dark-mode .social-link:hover {
            background: #ff6b6b;
            color: #0D1117;
            border-color: #ff6b6b;
        }

        /* Map styling */
        .map-container {
            margin-top: 30px;
            border-radius: var(--radius-element);
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .map-container iframe {
            width: 100%;
            height: 250px;
            border: none;
        }

        /* Contact Form Section */
        .form-section {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .form-section h2 {
            color: var(--text-primary);
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--maroon);
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group label i {
            color: var(--maroon);
            margin-right: 6px;
        }

        body.dark-mode .form-group label i {
            color: #ff6b6b;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: var(--radius-element);
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: inherit;
            outline: none;
        }

        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--midnight);
            box-shadow: 0 0 0 4px rgba(15, 65, 74, 0.15);
            background: var(--bg-card);
        }

        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 40px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 6px 20px rgba(127, 3, 3, 0.25);
        }

        .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(127, 3, 3, 0.35);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Alert Banners */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        body.dark-mode .alert-success {
            background: rgba(15, 81, 50, 0.2);
            color: #75b798;
            border-color: rgba(117, 183, 152, 0.25);
        }

        .alert-error {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        body.dark-mode .alert-error {
            background: rgba(132, 32, 41, 0.2);
            color: #ea868f;
            border-color: rgba(234, 134, 143, 0.25);
        }

        /* Responsive Layouts */
        @media (max-width: 992px) {
            .contact-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0;
            }
            .contact-info-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .hours-list li {
                flex-direction: column;
                align-items: center;
                gap: 4px;
            }
        }
    </style>
</head>
<body>

    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Header Spacer for Sticky Navbar -->
    <div class="header-spacer"></div>

    <!-- Hero Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
            <p>We're here to help! Reach out to us for any questions, concerns, or support inquiries.</p>
        </div>
    </div>
    
    <div class="container" style="padding-top: 40px;">
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="contact-layout">
            <!-- Contact Information -->
            <div class="info-section">
                <h2><i class="fas fa-address-card"></i> Contact Info</h2>
                
                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="contact-details">
                        <h3>Office Address</h3>
                        <p>City Social Welfare and Development Office<br>Palayan City Hall, Nueva Ecija, Philippines 3132</p>
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
                    <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link" title="Viber"><i class="fab fa-viber"></i></a>
                    <a href="#" class="social-link" title="Messenger"><i class="fab fa-facebook-messenger"></i></a>
                </div>
                
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3838.747942698715!2d121.09641737593237!3d15.541062085065403!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33b18f8ab6230f89%3A0xe54e60b1338dfa0c!2sPalayan%20City%20Hall!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="form-section">
                <h2><i class="fas fa-paper-plane"></i> Send a Message</h2>
                
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
                
                <div style="margin-top: 24px; padding: 20px; background: var(--alabaster); border: 1px solid var(--border-color); border-radius: var(--radius-element);">
                    <p style="font-size: 0.88rem; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-info-circle" style="color: var(--midnight);"></i>
                        We typically respond within 1-2 business days. For immediate assistance, please call us directly.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Auto-fade alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s, transform 0.5s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
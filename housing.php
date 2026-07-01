<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all housing programs
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active' ORDER BY program_name";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute();
$programs = $programs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get housing statistics
$stats_query = "SELECT 
    COUNT(*) as total_units,
    SUM(CASE WHEN status = 'active' THEN total_units ELSE 0 END) as available_units,
    (SELECT COUNT(*) FROM housing_beneficiaries WHERE status = 'approved') as approved_beneficiaries,
    (SELECT COUNT(*) FROM housing_beneficiaries WHERE status = 'pending') as pending_applications
    FROM housing_programs";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get recent housing beneficiaries
$beneficiaries_query = "SELECT h.*, u.first_name, u.last_name, p.program_name 
                        FROM housing_beneficiaries h
                        JOIN users u ON h.user_id = u.user_id
                        JOIN housing_programs p ON h.program_id = p.program_id
                        WHERE h.status = 'approved'
                        ORDER BY h.approval_date DESC
                        LIMIT 6";
$beneficiaries_stmt = $db->prepare($beneficiaries_query);
$beneficiaries_stmt->execute();
$beneficiaries = $beneficiaries_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Programs - One Palayan-Kalinga Hub</title>
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
            --midnight-light: #1a6a78;
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

        /* Housing Hero */
        .housing-hero {
            background: var(--midnight);
            color: white;
            padding: 140px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .housing-hero::before {
            content: '🏠';
            font-size: 15rem;
            position: absolute;
            right: -50px;
            bottom: -50px;
            opacity: 0.06;
            transform: rotate(15deg);
            z-index: 0;
        }

        .housing-hero .container {
            position: relative;
            z-index: 2;
        }

        .housing-hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .housing-hero p {
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Stats Section */
        .stats-section {
            padding: 60px 0;
            background: var(--white);
            margin-top: -40px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .stat-item {
            text-align: center;
            padding: 30px 20px;
            background: var(--white);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .stat-item:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--maroon);
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--midnight);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Quick Links Bar */
        .quick-links-bar {
            background: var(--white);
            padding: 20px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .quick-links-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .quick-link-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            background: var(--gray-50);
            border-radius: 40px;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .quick-link-item:hover {
            background: var(--midnight);
            color: white;
            transform: translateY(-3px);
            border-color: var(--midnight);
        }

        .quick-link-item i {
            color: var(--maroon);
            font-size: 1rem;
        }

        .quick-link-item:hover i {
            color: white;
        }

        /* Requirements Banner */
        .requirements-banner {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
        }

        .requirements-banner h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .requirements-banner p {
            font-size: 1rem;
            max-width: 700px;
            margin: 0 auto 25px;
            opacity: 0.9;
        }

        .banner-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-light {
            background: var(--white);
            color: var(--maroon);
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-light:hover {
            background: var(--alabaster);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-light {
            background: transparent;
            color: white;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            border: 2px solid white;
            transition: all 0.3s;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--maroon);
            transform: translateY(-3px);
        }

        /* Programs Section */
        .programs-section {
            padding: 60px 0;
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
            max-width: 700px;
            margin: 0 auto;
        }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .program-card {
            background: var(--white);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-200);
        }

        .program-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--light-blue);
        }

        .program-image {
            height: 160px;
            background: linear-gradient(135deg, var(--midnight), var(--midnight-light));
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .program-image i {
            font-size: 4rem;
            color: rgba(255,255,255,0.2);
        }

        .program-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--maroon);
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .program-content {
            padding: 25px;
            flex: 1;
        }

        .program-content h3 {
            color: var(--midnight);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .program-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        .program-location i {
            color: var(--maroon);
        }

        .program-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .program-details {
            background: var(--gray-50);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--gray-200);
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray-600);
            font-weight: 500;
        }

        .detail-value {
            color: var(--midnight);
            font-weight: 700;
        }

        .program-requirements h4 {
            color: var(--midnight);
            margin-bottom: 10px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
        }

        .requirements-list li {
            padding: 5px 0;
            padding-left: 25px;
            position: relative;
            color: var(--gray-600);
            font-size: 0.85rem;
        }

        .requirements-list li:before {
            content: "✓";
            color: var(--maroon);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .program-footer {
            padding: 20px 25px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 10px;
        }

        .btn-apply {
            flex: 2;
            padding: 12px;
            background: var(--midnight);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            text-align: center;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .btn-apply:hover {
            background: var(--midnight-light);
            transform: translateY(-2px);
        }

        .btn-details {
            flex: 1;
            padding: 12px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            text-align: center;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-details:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* Success Stories */
        .stories-section {
            padding: 60px 0;
            background: var(--white);
        }

        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .story-card {
            background: var(--gray-50);
            border-radius: var(--radius-card);
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .story-avatar {
            width: 80px;
            height: 80px;
            background: var(--midnight);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }

        .story-card h4 {
            color: var(--midnight);
            margin-bottom: 5px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .story-location {
            color: var(--maroon);
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .story-quote {
            color: var(--gray-600);
            font-style: italic;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .story-quote i {
            color: var(--maroon);
            font-size: 0.8rem;
            margin: 0 5px;
        }

        /* Process Section */
        .process-section {
            padding: 60px 0;
            background:  var(--alabaster);
        }

        .process-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .process-step {
            text-align: center;
            position: relative;
        }

        .process-step:not(:last-child):after {
            content: "→";
            position: absolute;
            right: -20px;
            top: 30px;
            font-size: 1.8rem;
            color: var(--midnight);
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
        }

        .process-step h4 {
            color: var(--midnight);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .process-step p {
            color: var(--gray-600);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* FAQ Section */
        .faq-section {
            padding: 60px 0;
            background: var(--white);
        }

        .faq-container {
            max-width: 800px;
            margin: 40px auto 0;
        }

        .faq-item {
            background: var(--gray-50);
            border-radius: var(--radius-element);
            margin-bottom: 15px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--midnight);
        }

        .faq-question i {
            transition: transform 0.3s;
            color: var(--maroon);
        }

        .faq-question.active i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .faq-answer.show {
            padding: 0 20px 20px 20px;
            max-height: 200px;
        }

        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--midnight) 0%, var(--midnight-light) 100%);
            color: white;
            text-align: center;
        }

        .cta-section h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta-section p {
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-btn {
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .cta-btn.primary {
            background: var(--maroon);
            color: white;
        }

        .cta-btn.primary:hover {
            background: var(--maroon-light);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(127,3,3,0.3);
        }

        .cta-btn.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .cta-btn.secondary:hover {
            background: white;
            color: var(--midnight);
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

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: var(--radius-card);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: transform 0.3s;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-200);
        }

        .modal-header h2 {
            color: var(--midnight);
            margin: 0;
            font-size: 1.3rem;
        }

        .modal-header i {
            color: var(--maroon);
            margin-right: 8px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-600);
        }

        .close-btn:hover {
            color: var(--maroon);
        }

        @media (max-width: 968px) {
            .housing-hero h1 { font-size: 2rem; }
            .housing-hero { padding: 120px 0 60px; }
            .stats-container { grid-template-columns: repeat(2, 1fr); }
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
            .stats-container { grid-template-columns: 1fr; }
            .quick-links-container { flex-direction: column; align-items: center; }
            .quick-link-item { width: 80%; justify-content: center; }
            .banner-buttons { flex-direction: column; align-items: center; }
            .programs-grid { grid-template-columns: 1fr; }
            .program-footer { flex-wrap: wrap; }
            .btn-apply, .btn-details { flex: 1 1 100%; }
            .process-steps { grid-template-columns: 1fr; }
            .process-step:not(:last-child):after { display: none; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .stories-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
        }
    </style>
</head>
<body>
    <!-- Navbar - Same as other pages -->
    <nav class="navbar" id="navbar">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-hand-holding-heart"></i>
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
        <button class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <!-- Hero Section -->
    <section class="housing-hero">
        <div class="container">
            <h1>Housing Programs</h1>
            <p>Providing safe, affordable, and quality housing for every Palayan resident. Your dream home starts here.</p>
        </div>
    </section>

    <!-- Quick Links Bar -->
    <div class="quick-links-bar">
        <div class="container">
            <div class="quick-links-container">
                <a href="#programs" class="quick-link-item">
                    <i class="fas fa-home"></i>
                    <span>Available Programs</span>
                </a>
                <a href="housing-requirements.php" class="quick-link-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Requirements</span>
                </a>
                <a href="#process" class="quick-link-item">
                    <i class="fas fa-list-ol"></i>
                    <span>How to Apply</span>
                </a>
                <a href="#faq" class="quick-link-item">
                    <i class="fas fa-question-circle"></i>
                    <span>FAQs</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-container">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_units'] ?? 0); ?></div>
                    <div class="stat-label">Total Units</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['available_units'] ?? 0); ?></div>
                    <div class="stat-label">Available Units</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['approved_beneficiaries'] ?? 0); ?></div>
                    <div class="stat-label">Beneficiaries</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['pending_applications'] ?? 0); ?></div>
                    <div class="stat-label">Pending Apps</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements Banner -->
    <section class="requirements-banner">
        <div class="container">
            <h2><i class="fas fa-file-alt"></i> Housing Program Requirements</h2>
            <p>Before applying, make sure you have all the necessary documents ready. View the complete list of requirements in Filipino and English.</p>
            <div class="banner-buttons">
                <a href="housing-requirements.php" class="btn-light">
                    <i class="fas fa-eye"></i> View Requirements
                </a>
                <a href="housing-requirements.php#print" class="btn-outline-light">
                    <i class="fas fa-print"></i> Printable Version
                </a>
            </div>
        </div>
    </section>

    <!-- Housing Programs Section -->
    <section id="programs" class="programs-section">
        <div class="container">
            <div class="section-header">
                <h2>Available Housing Programs</h2>
                <p>Choose from our various housing programs designed to meet different needs and qualifications</p>
            </div>

            <div class="programs-grid">
                <?php foreach ($programs as $program): ?>
                <div class="program-card">
                    <div class="program-image">
                        <i class="fas fa-home"></i>
                        <span class="program-badge"><?php echo htmlspecialchars($program['program_code']); ?></span>
                    </div>
                    <div class="program-content">
                        <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                        <div class="program-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($program['location']); ?>
                        </div>
                        <p class="program-description"><?php echo htmlspecialchars($program['description']); ?></p>
                        
                        <div class="program-details">
                            <div class="detail-row">
                                <span class="detail-label">Total Units:</span>
                                <span class="detail-value"><?php echo $program['total_units']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value">
                                    <span style="color: <?php echo $program['status'] == 'active' ? '#4CAF50' : '#f44336'; ?>">
                                        <?php echo ucfirst($program['status']); ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($program['requirements'])): ?>
                        <div class="program-requirements">
                            <h4>Key Requirements:</h4>
                            <ul class="requirements-list">
                                <?php 
                                $reqs = explode("\n", $program['requirements']);
                                $display_reqs = array_slice($reqs, 0, 3);
                                foreach ($display_reqs as $req): 
                                    if (trim($req)):
                                ?>
                                <li><?php echo htmlspecialchars(trim($req)); ?></li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                <?php if (count($reqs) > 3): ?>
                                <li><em>+<?php echo count($reqs) - 3; ?> more</em></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="program-footer">
                        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                            <a href="apply-housing.php?program_id=<?php echo $program['program_id']; ?>" class="btn-apply">
                                <i class="fas fa-paper-plane"></i> Apply Now
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn-apply">
                                <i class="fas fa-sign-in-alt"></i> Login to Apply
                            </a>
                        <?php endif; ?>
                        <button class="btn-details" onclick="showProgramDetails(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Success Stories Section -->
    <?php if (!empty($beneficiaries)): ?>
    <section class="stories-section">
        <div class="container">
            <div class="section-header">
                <h2>Success Stories</h2>
                <p>Meet some of our housing beneficiaries who now have a place to call home</p>
            </div>

            <div class="stories-grid">
                <?php foreach ($beneficiaries as $beneficiary): ?>
                <div class="story-card">
                    <div class="story-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($beneficiary['first_name'] . ' ' . $beneficiary['last_name']); ?></h4>
                    <div class="story-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($beneficiary['program_name']); ?>
                    </div>
                    <div class="story-quote">
                        <i class="fas fa-quote-left"></i>
                        Thank you CSWDO for helping us find a home. My family now has a safe place to live.
                        <i class="fas fa-quote-right"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Application Process Section -->
    <section id="process" class="process-section">
        <div class="container">
            <div class="section-header">
                <h2>How to Apply</h2>
                <p>Simple steps to start your housing application</p>
            </div>

            <div class="process-steps">
                <div class="process-step">
                    <div class="step-number">1</div>
                    <h4>Check Eligibility</h4>
                    <p>Review program requirements and ensure you meet the qualifications</p>
                </div>
                <div class="process-step">
                    <div class="step-number">2</div>
                    <h4>Prepare Documents</h4>
                    <p>Gather all required documents listed in the program details</p>
                </div>
                <div class="process-step">
                    <div class="step-number">3</div>
                    <h4>Submit Application</h4>
                    <p>Fill out the online form and upload your documents</p>
                </div>
                <div class="process-step">
                    <div class="step-number">4</div>
                    <h4>Wait for Evaluation</h4>
                    <p>Your application will be reviewed and you'll be notified of the status</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq-section">
        <div class="container">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <p>Find answers to common questions about housing programs</p>
            </div>

            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Who can apply for housing programs?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Housing programs are open to all qualified residents of Palayan City. Eligibility criteria may vary per program and typically include factors such as income level, family size, and current housing situation.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What documents are required?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Common requirements include: Valid ID, Barangay Certificate of Residency, Proof of Income, Birth Certificates of family members, and Current housing situation documentation. 
                        <a href="housing-requirements.php" style="color: var(--maroon); text-decoration: none; font-weight: 600;">View complete requirements →</a>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How long is the application process?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        The application process typically takes 7-10 working days from submission to initial evaluation. This includes document verification, interview scheduling, and final approval.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I apply for multiple programs?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes, you can apply for multiple housing programs, but you can only be approved for one. We recommend applying for the program that best matches your needs and qualifications.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How are beneficiaries selected?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Beneficiaries are selected based on a points system considering factors such as income level, family size, current housing conditions, and urgency of need. The selection process is transparent and documented.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Find Your New Home?</h2>
            <p>Start your application today and take the first step towards owning a home in Palayan City.</p>
            <div class="cta-buttons">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <a href="apply-housing.php" class="cta-btn primary">
                        <i class="fas fa-paper-plane"></i> Apply Now
                    </a>
                <?php else: ?>
                    <a href="register.php" class="cta-btn primary">
                        <i class="fas fa-user-plus"></i> Register to Apply
                    </a>
                <?php endif; ?>
                <a href="housing-requirements.php" class="cta-btn secondary">
                    <i class="fas fa-file-alt"></i> View Requirements
                </a>
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

    <!-- Program Details Modal -->
    <div id="programModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-home"></i> <span id="modalProgramName"></span></h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

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

    function toggleFAQ(element) {
        element.classList.toggle('active');
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
    }

    function showProgramDetails(program) {
        const modal = document.getElementById('programModal');
        const modalName = document.getElementById('modalProgramName');
        const modalContent = document.getElementById('modalContent');

        modalName.textContent = program.program_name;

        let requirementsHtml = '';
        if (program.requirements) {
            const reqs = program.requirements.split('\n');
            requirementsHtml = '<h4 style="color: var(--midnight); margin: 20px 0 10px;">Complete Requirements:</h4><ul style="list-style: none; padding: 0;">';
            reqs.forEach(req => {
                if (req.trim()) {
                    requirementsHtml += `<li style="padding: 8px 0; padding-left: 25px; position: relative; color: var(--gray-600);"><span style="position: absolute; left: 0; color: var(--maroon);">✓</span> ${req.trim()}</li>`;
                }
            });
            requirementsHtml += '</ul>';
        }

        modalContent.innerHTML = `
            <div style="margin-bottom: 20px;">
                <p><i class="fas fa-map-marker-alt" style="color: var(--maroon);"></i> <strong>Location:</strong> ${program.location}</p>
                <p><i class="fas fa-building" style="color: var(--maroon);"></i> <strong>Total Units:</strong> ${program.total_units}</p>
                <p><i class="fas fa-info-circle" style="color: var(--maroon);"></i> <strong>Status:</strong> <span style="color: ${program.status == 'active' ? '#4CAF50' : '#f44336'}">${program.status}</span></p>
            </div>
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--midnight); margin-bottom: 10px;">Description:</h4>
                <p style="line-height: 1.6; color: var(--gray-600);">${program.description}</p>
            </div>
            ${requirementsHtml}
            <div style="margin-top: 25px; text-align: center;">
                <a href="housing-requirements.php" class="btn-requirements" style="display: inline-block; padding: 12px 30px; margin-right: 10px; text-decoration: none;">View All Requirements</a>
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <a href="apply-housing.php?program_id=${program.program_id}" class="btn-apply" style="display: inline-block; padding: 12px 30px; text-decoration: none;">Apply Now</a>
                <?php else: ?>
                    <a href="login.php" class="btn-apply" style="display: inline-block; padding: 12px 30px; text-decoration: none;">Login to Apply</a>
                <?php endif; ?>
            </div>
        `;

        modal.classList.add('show');
    }

    function closeModal() {
        document.getElementById('programModal').classList.remove('show');
    }

    window.onclick = function(event) {
        const modal = document.getElementById('programModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Animate stats numbers
    document.addEventListener('DOMContentLoaded', function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const target = parseInt(stat.textContent);
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    stat.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current).toLocaleString();
                }
            }, 20);
        });
    });
    </script>
</body>
</html>
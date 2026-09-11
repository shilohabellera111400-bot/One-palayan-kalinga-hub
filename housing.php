<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

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
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Programs - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE & GLOBAL THEMING ========== */
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
            --alabaster: #E3E7EB;
            
            --bg-primary: #E8ECF2;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #D5DBE1;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 10px 30px rgba(15, 65, 74, 0.09), 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 16px 40px rgba(15, 65, 74, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --radius-card: 24px;
            --radius-element: 16px;
            
            --color-success: #2e7d32;
            --color-warning: #ed6c02;
            --color-danger: #d32f2f;
            --color-info: #0288d1;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-card: #1a2332;
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --border-color: #2a3a4f;
            --alabaster: #161e2a;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.5);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.15);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
            
            --color-success: #4caf50;
            --color-warning: #ff9800;
            --color-danger: #f44336;
            --color-info: #2196f3;
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

        /* ========== HERO SECTION ========== */
        .housing-hero {
            background: var(--midnight);
            color: white;
            padding: 170px 0 95px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .housing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: 0;
            animation: slowZoom 20s ease-in-out infinite alternate;
        }

        .housing-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.88), rgba(15, 65, 74, 0.75));
            z-index: 0;
        }

        @keyframes slowZoom {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }

        .housing-hero .container {
            position: relative;
            z-index: 2;
            animation: fadeInDown 0.6s ease forwards;
        }

        .housing-hero h1 {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .housing-hero h1 span {
            color: var(--gold-light);
        }

        .housing-hero p {
            font-size: 1.2rem;
            max-width: 750px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.7;
            color: var(--alabaster);
        }

        /* ========== QUICK LINKS PILL BAR ========== */
        .quick-links-bar {
            background: var(--bg-card);
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 76px;
            z-index: 99;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .quick-links-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .quick-link-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: var(--bg-primary);
            border-radius: 40px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
        }

        .quick-link-item:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.2);
        }

        .quick-link-item i {
            color: var(--maroon);
            transition: color 0.3s;
        }

        .quick-link-item:hover i {
            color: white;
        }

        /* ========== STATISTICS CARDS ========== */
        .stats-section {
            padding: 60px 0;
            background: var(--bg-primary);
            position: relative;
            z-index: 10;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .stat-item {
            text-align: center;
            padding: 30px 20px;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease both;
        }

        .stat-item:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .stat-item:nth-child(1) { animation-delay: 0.05s; }
        .stat-item:nth-child(2) { animation-delay: 0.1s; }
        .stat-item:nth-child(3) { animation-delay: 0.15s; }
        .stat-item:nth-child(4) { animation-delay: 0.2s; }

        .stat-item .stat-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin: 0 auto 15px;
            transition: transform 0.3s ease;
        }

        [data-theme="dark"] .stat-item .stat-icon-wrapper {
            background: rgba(232, 213, 168, 0.1);
            color: var(--gold-light);
        }

        .stat-item:hover .stat-icon-wrapper {
            transform: scale(1.08) rotate(-5deg);
        }

        .stat-number {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 5px;
            line-height: 1.1;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== REQUIREMENTS BANNER ========== */
        .requirements-banner {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 45px 0;
            text-align: center;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .requirements-banner::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .requirements-banner h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 12px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .requirements-banner p {
            font-size: 1.05rem;
            max-width: 700px;
            margin: 0 auto 28px;
            opacity: 0.9;
        }

        .banner-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .btn-light {
            background: var(--white);
            color: var(--maroon);
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-light:hover {
            background: var(--alabaster);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .btn-outline-light {
            background: transparent;
            color: white;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid white;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--maroon);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        /* ========== PROGRAMS SECTION ========== */
        .programs-section {
            padding: 80px 0;
            background: var(--bg-primary);
        }

        .section-header {
            text-align: center;
            margin-bottom: 55px;
            animation: fadeInDown 0.6s ease both;
        }

        .section-header h2 {
            color: var(--text-primary);
            font-size: 2.4rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 1.05rem;
            max-width: 650px;
            margin: 0 auto;
        }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 30px;
        }

        .program-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease both;
        }

        .program-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .program-image {
            height: 180px;
            background: linear-gradient(135deg, var(--midnight) 0%, var(--midnight-light) 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .program-image::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('images/palayan-bg.png');
            background-size: cover;
            background-position: center;
            opacity: 0.12;
        }

        .program-image i {
            font-size: 4.5rem;
            color: rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 2;
            transition: transform 0.4s ease;
        }

        .program-card:hover .program-image i {
            transform: scale(1.08) rotate(-3deg);
        }

        .program-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--maroon);
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.3);
            z-index: 3;
        }

        .program-content {
            padding: 30px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .program-content h3 {
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .program-location {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            margin-bottom: 16px;
            font-size: 0.88rem;
            font-weight: 500;
        }

        .program-location i {
            color: var(--maroon);
        }

        [data-theme="dark"] .program-location i {
            color: var(--gold-light);
        }

        .program-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.92rem;
            flex-grow: 1;
        }

        .program-details {
            background: var(--bg-primary);
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--border-color);
            font-size: 0.88rem;
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 700;
        }

        .program-requirements h4 {
            color: var(--text-primary);
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
            padding-left: 24px;
            position: relative;
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 500;
        }

        .requirements-list li:before {
            content: "✓";
            color: var(--color-success);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .program-footer {
            padding: 20px 30px 28px;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
        }

        .btn-apply {
            flex: 2;
            padding: 12px;
            background: var(--midnight);
            color: white;
            border: none;
            border-radius: 40px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(15, 65, 74, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-apply:hover {
            background: var(--maroon);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25);
        }

        .btn-details {
            flex: 1;
            padding: 12px;
            background: var(--bg-card);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 40px;
            text-align: center;
            font-weight: 700;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            border: 1.5px solid var(--border-color);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-details:hover {
            border-color: var(--maroon);
            color: var(--maroon);
            transform: translateY(-2px);
        }

        [data-theme="dark"] .btn-details:hover {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        /* ========== SUCCESS STORIES ========== */
        .stories-section {
            padding: 80px 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 45px;
        }

        .story-card {
            background: var(--bg-primary);
            border-radius: var(--radius-card);
            padding: 35px 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .story-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon-light);
        }

        .story-avatar {
            width: 76px;
            height: 76px;
            background: var(--midnight);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            box-shadow: var(--shadow-sm);
        }

        [data-theme="dark"] .story-avatar {
            background: var(--maroon);
        }

        .story-card h4 {
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .story-location {
            color: var(--maroon);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        [data-theme="dark"] .story-location {
            color: var(--gold-light);
        }

        .story-quote {
            color: var(--text-secondary);
            font-style: italic;
            line-height: 1.7;
            font-size: 0.92rem;
        }

        .story-quote i {
            color: var(--maroon);
            opacity: 0.3;
            margin: 0 4px;
        }

        [data-theme="dark"] .story-quote i {
            color: var(--gold-light);
        }

        /* ========== PROCESS SECTION ========== */
        .process-section {
            padding: 80px 0;
            background: var(--bg-primary);
        }

        .process-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 45px;
        }

        .process-step {
            text-align: center;
            position: relative;
        }

        .process-step:not(:last-child):after {
            content: "→";
            position: absolute;
            right: -20px;
            top: 25px;
            font-size: 1.8rem;
            color: var(--text-muted);
            opacity: 0.5;
        }

        .process-step-number {
            width: 60px;
            height: 60px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 auto 20px;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.25);
        }

        [data-theme="dark"] .process-step-number {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 10px rgba(232, 213, 168, 0.25);
        }

        .process-step h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .process-step p {
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.6;
            font-weight: 500;
        }

        /* ========== FAQ SECTION ========== */
        .faq-section {
            padding: 80px 0;
            background: var(--bg-card);
        }

        .faq-container {
            max-width: 800px;
            margin: 45px auto 0;
        }

        .faq-item {
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            margin-bottom: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .faq-question {
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .faq-question i {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--maroon);
        }

        [data-theme="dark"] .faq-question i {
            color: var(--gold-light);
        }

        .faq-question.active {
            background: var(--alabaster);
        }

        [data-theme="dark"] .faq-question.active {
            background: var(--border-color);
        }

        .faq-question.active i {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 0.92rem;
            padding: 0 24px;
        }

        .faq-answer.show {
            padding: 8px 24px 24px 24px;
            max-height: 250px;
        }

        /* ========== CTA SECTION ========== */
        .cta-section {
            padding: 85px 0;
            background: linear-gradient(135deg, var(--midnight) 0%, var(--midnight-light) 100%);
            color: white;
            text-align: center;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .cta-section h2 {
            font-size: 2.4rem;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .cta-section p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 32px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-btn {
            padding: 14px 36px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .cta-btn.primary {
            background: var(--maroon);
            color: white;
            box-shadow: 0 4px 15px rgba(127, 3, 3, 0.3);
        }

        .cta-btn.primary:hover {
            background: var(--maroon-light);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(127, 3, 3, 0.45);
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

        /* ========== DETAILS MODAL ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 14, 23, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--bg-card);
            padding: 35px;
            border-radius: var(--radius-card);
            max-width: 640px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            transform: scale(0.95) translateY(20px);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .modal.show .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .modal-header h2 {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h2 i {
            color: var(--maroon);
        }

        [data-theme="dark"] .modal-header h2 i {
            color: var(--gold-light);
        }

        .close-btn {
            background: var(--bg-primary);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: var(--color-danger);
            color: white;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== RESPONSIVE OVERRIDES ========== */
        @media (max-width: 992px) {
            .housing-hero h1 { font-size: 2.5rem; }
            .stats-container { grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .process-steps { grid-template-columns: repeat(2, 1fr); gap: 30px; }
            .process-step:nth-child(2):after { display: none; }
            .process-step:not(:last-child):after { right: -15px; }
        }

        @media (max-width: 768px) {
            .housing-hero { padding: 130px 0 60px; }
            .housing-hero h1 { font-size: 2rem; }
            .stats-container { grid-template-columns: 1fr; }
            .quick-links-container { flex-direction: column; align-items: stretch; gap: 10px; }
            .quick-link-item { justify-content: center; }
            .banner-buttons { flex-direction: column; align-items: stretch; }
            .programs-grid { grid-template-columns: 1fr; }
            .program-footer { flex-wrap: wrap; }
            .btn-apply, .btn-details { flex: 1 1 100%; }
            .process-steps { grid-template-columns: 1fr; gap: 20px; }
            .process-step:not(:last-child):after { display: none; }
            .cta-buttons { flex-direction: column; align-items: stretch; }
            .stories-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Navbar Include -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="housing-hero">
        <div class="container">
            <h1>CSWDO <span>Housing Programs</span></h1>
            <p>Providing secure, high-quality, and affordable resettlement opportunities for low-income and qualified families in Palayan City. Invest in your family's future today.</p>
        </div>
    </section>

    <!-- Quick Links Floating Pill Bar -->
    <div class="quick-links-bar">
        <div class="container">
            <div class="quick-links-container">
                <a href="#programs" class="quick-link-item">
                    <i class="fas fa-house"></i>
                    <span>Available Programs</span>
                </a>
                <a href="housing-requirements.php" class="quick-link-item">
                    <i class="fas fa-file-shield"></i>
                    <span>Requirements Guide</span>
                </a>
                <a href="#process" class="quick-link-item">
                    <i class="fas fa-bars-staggered"></i>
                    <span>How to Apply</span>
                </a>
                <a href="#faq" class="quick-link-item">
                    <i class="fas fa-circle-question"></i>
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
                    <div class="stat-icon-wrapper"><i class="fas fa-city"></i></div>
                    <div class="stat-number" data-target="<?php echo $stats['total_units'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Subdivisions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper"><i class="fas fa-circle-check"></i></div>
                    <div class="stat-number" data-target="<?php echo $stats['available_units'] ?? 0; ?>">0</div>
                    <div class="stat-label">Active Sites</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper"><i class="fas fa-users-viewfinder"></i></div>
                    <div class="stat-number" data-target="<?php echo $stats['approved_beneficiaries'] ?? 0; ?>">0</div>
                    <div class="stat-label">Families Relocated</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-wrapper"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-number" data-target="<?php echo $stats['pending_applications'] ?? 0; ?>">0</div>
                    <div class="stat-label">Pending Reviews</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements Banner -->
    <section class="requirements-banner">
        <div class="container">
            <h2><i class="fas fa-clipboard-list"></i> Application Requirements Checklist</h2>
            <p>Ensure you have all the necessary legal and civil documentation prepared before submitting your housing application.</p>
            <div class="banner-buttons">
                <a href="housing-requirements.php" class="btn-light">
                    <i class="fas fa-circle-info"></i> View Requirements Guide
                </a>
                <a href="housing-requirements.php?print=1" class="btn-outline-light">
                    <i class="fas fa-print"></i> Printable Checklist
                </a>
            </div>
        </div>
    </section>

    <!-- Available Housing Programs Grid -->
    <section id="programs" class="programs-section">
        <div class="container">
            <div class="section-header">
                <h2>Available Programs & Communities</h2>
                <p>Browse active housing and subdivision development projects managed by the CSWDO Palayan City.</p>
            </div>

            <div class="programs-grid">
                <?php foreach ($programs as $program): ?>
                <div class="program-card">
                    <div class="program-image">
                        <i class="fas fa-hotel"></i>
                        <span class="program-badge"><?php echo htmlspecialchars($program['program_code']); ?></span>
                    </div>
                    <div class="program-content">
                        <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                        <div class="program-location">
                            <i class="fas fa-map-location-dot"></i>
                            <?php echo htmlspecialchars($program['location']); ?>
                        </div>
                        <p class="program-description"><?php echo htmlspecialchars($program['description']); ?></p>
                        
                        <div class="program-details">
                            <div class="detail-row">
                                <span class="detail-label">Total Allocation:</span>
                                <span class="detail-value"><?php echo $program['total_units']; ?> Units</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Program Status:</span>
                                <span class="detail-value">
                                    <span style="color: <?php echo $program['status'] == 'active' ? 'var(--color-success)' : 'var(--color-danger)'; ?>">
                                        <?php echo ucfirst($program['status']); ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($program['requirements'])): ?>
                        <div class="program-requirements">
                            <h4>Prerequisites:</h4>
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
                                <li><em>+<?php echo count($reqs) - 3; ?> more requirements</em></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="program-footer">
                        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                            <a href="apply-housing.php?program_id=<?php echo $program['program_id']; ?>" class="btn-apply">
                                <i class="fas fa-paper-plane"></i> Apply Online
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn-apply">
                                <i class="fas fa-right-to-bracket"></i> Login to Apply
                            </a>
                        <?php endif; ?>
                        <button class="btn-details" onclick="showProgramDetails(<?php echo htmlspecialchars(json_encode($program)); ?>)">
                            <i class="fas fa-circle-info"></i> Full Details
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
                <h2>Families Welcomed Home</h2>
                <p>Read experiences from residents relocated under CSWDO housing initiatives.</p>
            </div>

            <div class="stories-grid">
                <?php foreach ($beneficiaries as $beneficiary): ?>
                <div class="story-card">
                    <div class="story-avatar">
                        <i class="fas fa-house-user"></i>
                    </div>
                    <h4><?php echo htmlspecialchars($beneficiary['first_name'] . ' ' . $beneficiary['last_name']); ?></h4>
                    <div class="story-location">
                        <i class="fas fa-location-dot"></i>
                        Resettled in <?php echo htmlspecialchars($beneficiary['program_name']); ?>
                    </div>
                    <div class="story-quote">
                        <i class="fas fa-quote-left"></i>
                        Lubos kaming nagpapasalamat sa CSWDO Palayan City sa pagkakaroon namin ng ligtas at sariling tahanan. Isang malaking tulong ito para sa kinabukasan ng aking mga anak.
                        <i class="fas fa-quote-right"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Application Process Steps Grid -->
    <section id="process" class="process-section">
        <div class="container">
            <div class="section-header">
                <h2>How the Application Works</h2>
                <p>Follow these structured steps to file your residential housing request.</p>
            </div>

            <div class="process-steps">
                <div class="process-step">
                    <div class="process-step-number">1</div>
                    <h4>Verify Eligibility</h4>
                    <p>Review standard requirements and confirm your qualifications and household status.</p>
                </div>
                <div class="process-step">
                    <div class="process-step-number">2</div>
                    <h4>Prepare Documents</h4>
                    <p>Assemble clearances, identity IDs, billing histories, and intent letters in digital formats.</p>
                </div>
                <div class="process-step">
                    <div class="process-step-number">3</div>
                    <h4>Submit Application</h4>
                    <p>File details through our secure resident portal and upload legal files.</p>
                </div>
                <div class="process-step">
                    <div class="process-step-number">4</div>
                    <h4>Social Assessment</h4>
                    <p>Complete interview evaluations conducted by social workers to finalize site selection.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq-section">
        <div class="container">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <p>Have questions? Review the general guidelines for Palayan City housing services.</p>
            </div>

            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Who qualifies for low-income housing resettlement?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Applications are open to registered indigent families, residents residing in disaster-prone hazard zones, and individuals meeting household income assessments defined by municipal social guidelines.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What are the key civil document requirements?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Core prerequisites consist of: Letter of Intent, Barangay Clearance or Indigency Cert, Valid Identity Card, House conditions photos, Utility billing proofs, and land permission letters if building on relative properties.
                        <a href="housing-requirements.php" style="color: var(--maroon); text-decoration: none; font-weight: 700; display: block; margin-top: 10px;">View Full Checklist Requirements →</a>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How long does the evaluation process take?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Once filed, evaluations, background checks, and household visits are finalized within 7 to 14 business days. The applicant receives direct SMS notification updates inside the portal.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I request relocation for multiple properties?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        While multiple applications are allowed, approvals are restricted to one resettlement lot or housing project per eligible household unit to ensure equitable allocation.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Secure Your Community Home?</h2>
            <p>Take the first step today. Register, verify your residency, and submit your application online.</p>
            <div class="cta-buttons">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <a href="apply-housing.php" class="cta-btn primary">
                        <i class="fas fa-paper-plane"></i> Apply for Housing
                    </a>
                <?php else: ?>
                    <a href="register.php" class="cta-btn primary">
                        <i class="fas fa-user-plus"></i> Register Portal Account
                    </a>
                <?php endif; ?>
                <a href="housing-requirements.php" class="cta-btn secondary">
                    <i class="fas fa-file-shield"></i> Read Requirements
                </a>
            </div>
        </div>
    </section>

    <!-- Footer Include -->
    <?php include 'includes/footer.php'; ?>

    <!-- Program Details Modal Dialog -->
    <div id="programModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-hotel"></i> <span id="modalProgramName"></span></h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
    // Toggle Accordion FAQs
    function toggleFAQ(element) {
        element.classList.toggle('active');
        const answer = element.nextElementSibling;
        answer.classList.toggle('show');
    }

    // Modal display scripts
    function showProgramDetails(program) {
        const modal = document.getElementById('programModal');
        const modalName = document.getElementById('modalProgramName');
        const modalContent = document.getElementById('modalContent');

        modalName.textContent = program.program_name;

        let requirementsHtml = '';
        if (program.requirements) {
            const reqs = program.requirements.split('\n');
            requirementsHtml = '<h4 style="color: var(--text-primary); margin: 20px 0 10px; font-weight:700;">Prerequisites Checklist:</h4><ul style="list-style: none; padding: 0;">';
            reqs.forEach(req => {
                if (req.trim()) {
                    requirementsHtml += `<li style="padding: 6px 0; padding-left: 25px; position: relative; color: var(--text-secondary);"><span style="position: absolute; left: 0; color: var(--color-success); font-weight:bold;">✓</span> ${req.trim()}</li>`;
                }
            });
            requirementsHtml += '</ul>';
        }

        modalContent.innerHTML = `
            <div style="margin-bottom: 20px; display: grid; gap: 10px; font-size:0.95rem; border-bottom: 1px solid var(--border-color); padding-bottom:15px;">
                <p><i class="fas fa-location-dot" style="color: var(--maroon); margin-right: 6px;"></i> <strong>Location:</strong> ${program.location}</p>
                <p><i class="fas fa-city" style="color: var(--maroon); margin-right: 6px;"></i> <strong>Allocated Units:</strong> ${program.total_units} Units</p>
                <p><i class="fas fa-circle-info" style="color: var(--maroon); margin-right: 6px;"></i> <strong>Site Status:</strong> <span style="color: ${program.status == 'active' ? 'var(--color-success)' : 'var(--color-danger)'}; font-weight:700;">${program.status.toUpperCase()}</span></p>
            </div>
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--text-primary); margin-bottom: 8px; font-weight:700;">Community Description:</h4>
                <p style="line-height: 1.6; color: var(--text-secondary); font-size:0.92rem;">${program.description}</p>
            </div>
            ${requirementsHtml}
            <div style="margin-top: 30px; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="housing-requirements.php" class="btn-details" style="flex:unset; padding: 10px 20px;">Requirements Guide</a>
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <a href="apply-housing.php?program_id=${program.program_id}" class="btn-apply" style="flex:unset; padding: 10px 25px;">Apply Online</a>
                <?php else: ?>
                    <a href="login.php" class="btn-apply" style="flex:unset; padding: 10px 25px;">Login to Apply</a>
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

    // Smooth Scroll to internal anchors
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                e.preventDefault();
                document.querySelector(targetId)?.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Statistical counter entry animations
    document.addEventListener('DOMContentLoaded', function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        
        // Simple IntersectionObserver for stats counter
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const stat = entry.target;
                    const target = parseInt(stat.getAttribute('data-target') || 0);
                    let current = 0;
                    const duration = 1000; // ms
                    const stepTime = 20;
                    const increment = target / (duration / stepTime);
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            stat.textContent = target.toLocaleString();
                            clearInterval(timer);
                        } else {
                            stat.textContent = Math.floor(current).toLocaleString();
                        }
                    }, stepTime);
                    obs.unobserve(stat);
                }
            });
        }, { threshold: 0.1 });
        
        statNumbers.forEach(stat => observer.observe(stat));
    });
    </script>
</body>
</html>

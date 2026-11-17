<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

$success = '';
$error = '';

// Pre-fill user information if logged in
$prefill_name = '';
$prefill_email = '';
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    try {
        $db = (new Database())->getConnection();
        $user_stmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = :uid LIMIT 1");
        $user_stmt->execute([':uid' => $_SESSION['user_id']]);
        if ($u = $user_stmt->fetch(PDO::FETCH_ASSOC)) {
            $prefill_name = trim($u['first_name'] . ' ' . $u['last_name']);
            $prefill_email = $u['email'];
        }
    } catch (Exception $e) {
        // Fallback gracefully
    }
}

// Calculate live CSWDO Office Status based on Asia/Manila time
$manila_day = (int)date('N'); // 1 = Monday, 7 = Sunday
$manila_hour = (int)date('G');
$manila_min = (int)date('i');
$time_in_hours = $manila_hour + ($manila_min / 60);

$is_weekday = ($manila_day >= 1 && $manila_day <= 5);
$is_open_hours = ($time_in_hours >= 8.0 && $time_in_hours < 17.0);
$is_lunch_break = ($time_in_hours >= 12.0 && $time_in_hours < 13.0);

if ($is_weekday && $is_open_hours && !$is_lunch_break) {
    $office_status_class = 'status-open';
    $office_status_label = 'Open Now';
    $office_status_detail = 'Closes at 5:00 PM';
} elseif ($is_weekday && $is_lunch_break) {
    $office_status_class = 'status-lunch';
    $office_status_label = 'Lunch Break';
    $office_status_detail = 'Resumes at 1:00 PM';
} else {
    $office_status_class = 'status-closed';
    $office_status_label = 'Closed Now';
    if ($is_weekday && $manila_hour < 8) {
        $office_status_detail = 'Opens today at 8:00 AM';
    } elseif ($is_weekday && $manila_hour >= 17) {
        $office_status_detail = ($manila_day == 5) ? 'Opens Monday 8:00 AM' : 'Opens tomorrow 8:00 AM';
    } else {
        $office_status_detail = 'Opens Monday at 8:00 AM';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    csrf_verify();
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

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
                    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1F2937; line-height: 1.6; }
                    .container { max-width: 600px; margin: 20px auto; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #E5E7EB; }
                    .header { background: linear-gradient(135deg, #0F414A 0%, #7F0303 100%); color: white; padding: 26px 24px; text-align: center; }
                    .header h2 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
                    .header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
                    .content { padding: 28px 24px; background: #FFFFFF; }
                    .field-box { margin-bottom: 16px; padding: 12px 16px; background: #F9FAFB; border-radius: 8px; border-left: 4px solid #7F0303; }
                    .field-label { font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 700; margin-bottom: 4px; }
                    .field-value { font-size: 14px; color: #111827; font-weight: 600; }
                    .message-box { padding: 16px; background: #F3F4F6; border-radius: 8px; font-size: 14px; color: #1F2937; white-space: pre-wrap; margin-top: 10px; line-height: 1.6; }
                    .footer { text-align: center; padding: 18px 24px; background: #F9FAFB; color: #9CA3AF; font-size: 12px; border-top: 1px solid #E5E7EB; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>One Palayan-Kalinga Hub</h2>
                        <p>Citizen Contact & Support Desk</p>
                    </div>
                    <div class='content'>
                        <div class='field-box'>
                            <div class='field-label'>Sender Name</div>
                            <div class='field-value'>" . htmlspecialchars($name) . "</div>
                        </div>
                        <div class='field-box'>
                            <div class='field-label'>Email Address</div>
                            <div class='field-value'>" . htmlspecialchars($email) . "</div>
                        </div>
                        <div class='field-box'>
                            <div class='field-label'>Inquiry Subject</div>
                            <div class='field-value'>" . htmlspecialchars($subject) . "</div>
                        </div>
                        <div class='field-box'>
                            <div class='field-label'>Message Content</div>
                            <div class='message-box'>" . nl2br(htmlspecialchars($message)) . "</div>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " City Social Welfare and Development Office (CSWDO) &bull; Palayan City</p>
                    </div>
                </div>
            </body>
            </html>
            ";

            $result = $mailer->send('monaaronmarcelo0924@gmail.com', "Contact Form: $subject", $body);

            if ($result['success']) {
                $success = "Thank you for reaching out! Your message has been sent to the CSWDO team. We typically respond within 1-2 business days.";
            } else {
                $error = "Failed to send message: " . ($result['message'] ?? 'Please try again later or reach out via our official phone hotlines.');
            }
        } catch (Exception $e) {
            $error = "Failed to send message. Please reach out directly through our hotlines or Facebook page.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us &bull; One Palayan-Kalinga Hub - CSWDO</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ========== PALAYAN BRAND COLOR TOKENS ========== */
        :root {
            --palayan-maroon: #7F0303;
            --palayan-maroon-dark: #580202;
            --palayan-maroon-light: #9B1A1A;
            --palayan-gold: #D4AF37;
            --palayan-gold-light: #F3E5AB;
            --palayan-teal: #0F414A;
            --palayan-teal-dark: #0A2B31;
            --palayan-teal-light: #165B67;
            --palayan-fb-blue: #1877F2;
            --palayan-fb-hover: #0c63d4;

            /* Light mode surface tokens */
            --bg-canvas: #F4F7F6;
            --bg-card: #FFFFFF;
            --bg-card-subtle: #F8FAFC;
            --bg-card-hover: #F1F5F9;
            --border-color: #E2E8F0;
            --border-highlight: #CBD5E1;
            --text-heading: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;
            --badge-bg: rgba(127, 3, 3, 0.08);

            --shadow-subtle: 0 4px 16px rgba(15, 23, 42, 0.04);
            --shadow-card: 0 10px 30px -5px rgba(15, 23, 42, 0.06), 0 4px 12px rgba(15, 23, 42, 0.03);
            --shadow-elevated: 0 20px 40px -10px rgba(15, 23, 42, 0.1);
            --radius-card: 22px;
            --radius-inner: 16px;
        }

        /* Dark mode overrides */
        body.dark-mode,
        [data-theme="dark"] body {
            --bg-canvas: #0B1114;
            --bg-card: #131C21;
            --bg-card-subtle: #19242B;
            --bg-card-hover: #1F2E36;
            --border-color: #273742;
            --border-highlight: #3B4F5E;
            --text-heading: #F8FAFC;
            --text-body: #CBD5E1;
            --text-muted: #8FA2B0;
            --badge-bg: rgba(239, 68, 68, 0.12);

            --shadow-subtle: 0 4px 16px rgba(0, 0, 0, 0.25);
            --shadow-card: 0 12px 32px rgba(0, 0, 0, 0.35);
            --shadow-elevated: 0 24px 50px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-canvas);
            color: var(--text-body);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .header-spacer {
            display: none !important;
        }

        /* ========== HERO HEADER (Extends behind floating oval navbar) ========== */
        .page-header {
            background: linear-gradient(135deg, var(--palayan-teal-dark) 0%, var(--palayan-teal) 50%, var(--palayan-maroon) 100%);
            color: #FFFFFF;
            padding: 135px 0 65px;
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid var(--palayan-gold);
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 15% 30%, rgba(212, 175, 55, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 70%, rgba(127, 3, 3, 0.25) 0%, transparent 50%),
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 40px 40px, 40px 40px;
            pointer-events: none;
        }

        .header-inner {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 820px;
            margin: 0 auto;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 18px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--palayan-gold-light);
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: clamp(2.1rem, 4.2vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.18;
            margin-bottom: 16px;
            color: #FFFFFF;
            text-shadow: 0 3px 12px rgba(0, 0, 0, 0.25);
        }

        .page-header p {
            font-size: clamp(1rem, 1.8vw, 1.15rem);
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.65;
            margin-bottom: 30px;
        }

        .header-highlights {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .highlight-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 50px;
            font-size: 0.84rem;
            font-weight: 600;
            color: #FFFFFF;
            backdrop-filter: blur(6px);
        }

        .highlight-chip i {
            color: var(--palayan-gold);
            font-size: 0.9rem;
        }

        
        /* ========== 3-CONTAINER CONTACT LAYOUT ========== */
        .contact-3containers-wrap {
            display: flex;
            flex-direction: column;
            gap: 36px;
            padding: 36px 0 70px;
        }

        .contact-top-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .contact-top-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Container 1: Landscape Contact Information */
        .info-landscape-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 640px) {
            .info-landscape-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-landscape-grid .contact-info-item {
            margin-bottom: 0;
            height: 100%;
        }

        .info-landscape-grid .office-hours-card {
            margin-top: 0;
            grid-column: 1 / -1;
        }

        /* Container 3: Social Media & Map (Underneath both) */
        .bottom-social-map-container {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 38px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
        }

        .bottom-social-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 960px) {
            .bottom-social-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ========== MAIN LAYOUT ========== */
        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 36px;
            padding: 48px 0 70px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .contact-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        /* ========== CARDS & SURFACES ========== */
        .info-section,
        .form-section {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 38px;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .section-header {
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .section-header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .section-header-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--badge-bg);
            color: var(--palayan-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        body.dark-mode .section-header-icon {
            color: #FF8A8A;
        }

        .section-header h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
            margin: 0;
        }

        .section-header p {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin: 3px 0 0 0;
        }

        /* ========== CONTACT INFO ITEMS ========== */
        .contact-card-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 28px;
        }

        .contact-info-item {
            display: flex;
            gap: 16px;
            padding: 20px;
            background: var(--bg-card-subtle);
            border-radius: var(--radius-inner);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
        }

        .contact-info-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
            border-color: var(--palayan-maroon-light);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--palayan-maroon) 0%, var(--palayan-maroon-light) 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(127, 3, 3, 0.25);
        }

        .contact-details {
            flex: 1;
            min-width: 0;
        }

        .contact-details h3 {
            color: var(--text-heading);
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-details p {
            color: var(--text-body);
            font-size: 0.92rem;
            line-height: 1.55;
            margin: 0;
        }

        .contact-details a {
            color: var(--text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .contact-details a:hover {
            color: var(--palayan-maroon);
            text-decoration: underline;
        }

        body.dark-mode .contact-details a:hover {
            color: #FF8A8A;
        }

        .item-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-mini-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 50px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-mini-action:hover {
            color: var(--palayan-maroon);
            border-color: var(--palayan-maroon);
            background: var(--badge-bg);
        }

        body.dark-mode .btn-mini-action:hover {
            color: #FF8A8A;
            border-color: #FF8A8A;
        }

        /* ========== OFFICE HOURS WITH LIVE STATUS ========== */
        .office-hours-card {
            background: var(--bg-card-subtle);
            border-radius: var(--radius-inner);
            border: 1px solid var(--border-color);
            padding: 22px;
            margin-bottom: 28px;
        }

        .hours-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .hours-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-heading);
        }

        .hours-title i {
            color: var(--palayan-maroon);
        }

        body.dark-mode .hours-title i {
            color: #FF8A8A;
        }

        /* Live Status Indicator */
        .office-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .office-status-badge.status-open {
            background: rgba(34, 197, 94, 0.12);
            color: #16A34A;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .office-status-badge.status-lunch {
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .office-status-badge.status-closed {
            background: rgba(100, 116, 139, 0.12);
            color: #64748B;
            border: 1px solid rgba(100, 116, 139, 0.25);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16A34A;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
            animation: pulseStatus 1.8s infinite;
        }

        .pulse-dot.lunch {
            background: #D97706;
            box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7);
        }

        .pulse-dot.closed {
            background: #94A3B8;
            animation: none;
        }

        @keyframes pulseStatus {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }

        .hours-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            color: var(--text-body);
            padding: 6px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .hours-list li:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .hours-list .day {
            font-weight: 600;
        }

        .hours-list .time {
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ========== FOLLOW US ON SOCIAL MEDIA SECTION ========== */
        .social-showcase-card {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-inner);
            overflow: hidden;
            margin-bottom: 28px;
            box-shadow: var(--shadow-subtle);
            transition: all 0.25s ease;
        }

        .social-showcase-card:hover {
            border-color: var(--palayan-fb-blue);
            box-shadow: 0 12px 30px rgba(24, 119, 242, 0.12);
        }

        .social-card-head {
            padding: 18px 22px;
            background: linear-gradient(135deg, rgba(24, 119, 242, 0.08) 0%, rgba(15, 65, 74, 0.05) 100%);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .social-head-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .social-fb-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--palayan-fb-blue);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(24, 119, 242, 0.35);
        }

        .social-card-head h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
        }

        .social-card-head p {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin: 2px 0 0 0;
        }

        .official-tag {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--palayan-fb-blue);
            background: rgba(24, 119, 242, 0.1);
            padding: 4px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Real Photo Showcase Container */
        .cswdo-photo-wrapper {
            position: relative;
            width: 100%;
            height: 250px;
            overflow: hidden;
            background: #0F172A;
        }

        .cswdo-team-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 22%;
            display: block;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .social-showcase-card:hover .cswdo-team-photo {
            transform: scale(1.04);
        }

        .photo-gradient-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(15, 23, 42, 0.88) 0%, rgba(15, 23, 42, 0.3) 50%, transparent 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 18px 20px;
            color: #FFFFFF;
            pointer-events: none;
        }

        .photo-tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
            align-self: flex-start;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .photo-caption-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #FFFFFF;
            line-height: 1.3;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }

        .photo-caption-sub {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.85);
            margin-top: 3px;
        }

        /* Social Channel Detail & Actions */
        .social-body-details {
            padding: 20px 22px;
        }

        .social-page-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .page-avatar-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--palayan-fb-blue);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.28);
        }

        .page-name-info h4 {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-name-info .handle {
            font-size: 0.78rem;
            color: var(--palayan-fb-blue);
            font-weight: 700;
        }

        .social-desc {
            font-size: 0.84rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .social-cta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width: 480px) {
            .social-cta-row {
                grid-template-columns: 1fr;
            }
        }

        .btn-social-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.22s ease;
            cursor: pointer;
            text-align: center;
        }

        .btn-social-cta.btn-fb-main {
            background: var(--palayan-fb-blue);
            color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(24, 119, 242, 0.3);
        }

        .btn-social-cta.btn-fb-main:hover {
            background: var(--palayan-fb-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(24, 119, 242, 0.4);
            color: #FFFFFF;
        }

        .btn-social-cta.btn-messenger {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            color: var(--text-heading);
        }

        .btn-social-cta.btn-messenger i {
            color: #0084FF;
        }

        .btn-social-cta.btn-messenger:hover {
            border-color: #0084FF;
            background: rgba(0, 132, 255, 0.06);
            transform: translateY(-2px);
            color: #0084FF;
        }

        /* ========== MAP CARD ========== */
        .map-card {
            border-radius: var(--radius-inner);
            overflow: hidden;
            border: 1px solid var(--border-color);
            background: var(--bg-card-subtle);
        }

        .map-header {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
        }

        .map-header span {
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .map-header span i {
            color: var(--palayan-maroon);
        }

        body.dark-mode .map-header span i {
            color: #FF8A8A;
        }

        .map-frame-wrap {
            position: relative;
            width: 100%;
            height: 240px;
        }

        .map-frame-wrap iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        /* ========== FORM SECTION STYLING ========== */
        .form-section h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
            margin: 0;
        }

        /* Quick Inquiry Topic Selector Pills */
        .topic-selector-box {
            margin-bottom: 24px;
        }

        .topic-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .topic-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .topic-pill {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 600;
            background: var(--bg-card-subtle);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .topic-pill:hover {
            border-color: var(--palayan-maroon);
            background: var(--badge-bg);
            color: var(--palayan-maroon);
            transform: translateY(-1px);
        }

        .topic-pill.active {
            background: var(--palayan-maroon);
            color: #FFFFFF;
            border-color: var(--palayan-maroon);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--text-heading);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .form-group label span.label-title {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .form-group label span.label-title i {
            color: var(--palayan-maroon);
            font-size: 0.88rem;
        }

        body.dark-mode .form-group label span.label-title i {
            color: #FF8A8A;
        }

        .char-counter {
            font-size: 0.74rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .input-control-wrap {
            position: relative;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card-subtle);
            color: var(--text-heading);
            border-radius: 12px;
            font-size: 0.92rem;
            transition: all 0.22s ease;
            font-family: inherit;
            outline: none;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--palayan-maroon);
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.12);
            background: var(--bg-card);
        }

        body.dark-mode .form-group input:focus,
        body.dark-mode .form-group textarea:focus {
            border-color: #FF8A8A;
            box-shadow: 0 0 0 4px rgba(255, 138, 138, 0.15);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--palayan-maroon) 0%, var(--palayan-maroon-light) 100%);
            color: #FFFFFF;
            padding: 15px 32px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            width: 100%;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 22px rgba(127, 3, 3, 0.28);
            margin-top: 6px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(127, 3, 3, 0.38);
            background: linear-gradient(135deg, var(--palayan-maroon-light) 0%, #B91C1C 100%);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Advisory Information Box */
        .advisory-box {
            margin-top: 24px;
            padding: 18px 20px;
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-inner);
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .advisory-icon {
            color: var(--palayan-teal);
            font-size: 1.25rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        body.dark-mode .advisory-icon {
            color: #5EEAD4;
        }

        .advisory-content h5 {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 3px 0;
        }

        .advisory-content p {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 22px;
            border-radius: 14px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 0.92rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: #E8F5E9;
            color: #1B5E20;
            border: 1px solid #A5D6A7;
        }

        body.dark-mode .alert-success {
            background: rgba(27, 94, 32, 0.25);
            color: #81C784;
            border-color: rgba(129, 199, 132, 0.35);
        }

        .alert-error {
            background: #FFEBEE;
            color: #B71C1C;
            border: 1px solid #FFCDD2;
        }

        body.dark-mode .alert-error {
            background: rgba(183, 28, 28, 0.25);
            color: #E57373;
            border-color: rgba(229, 115, 115, 0.35);
        }

        /* Toast notifications */
        .copy-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #1E293B;
            color: #FFFFFF;
            padding: 10px 22px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 99999;
        }

        .copy-toast.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
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
            <div class="header-inner">
                <div class="header-badge">
                    <i class="fas fa-hand-holding-heart"></i> Citizen Support & Public Assistance
                </div>
                <h1>Get in Touch with CSWDO</h1>
                <p>We are dedicated to providing responsive, compassionate social welfare services. Reach out to the City Social Welfare and Development Office through online messaging, direct telephone hotlines, or our official Facebook page.</p>
                <div class="header-highlights">
                    <div class="highlight-chip">
                        <i class="fas fa-building"></i> In front of Palayan City National High School
                    </div>
                    <div class="highlight-chip">
                        <i class="fas fa-calendar-check"></i> Mon &ndash; Fri &bull; 8:00 AM &ndash; 5:00 PM
                    </div>
                    <div class="highlight-chip">
                        <i class="fab fa-facebook-square"></i> Official Page: @PalayanCSWDO
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="padding-top: 36px;">
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle fa-lg"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle fa-lg"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="contact-3containers-wrap">
        <!-- TOP ROW: Container 1 (Contact Information Landscape) & Container 2 (Send a Message) -->
        <div class="contact-top-grid">
            <!-- CONTAINER 1: Contact Information (Landscape Orientation) -->
            <div class="info-section info-landscape-card">
                <div class="section-header">
                    <div class="section-header-title">
                        <div class="section-header-icon">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <div>
                            <h2>Contact Information</h2>
                            <p>Official communication channels &amp; office hours</p>
                        </div>
                    </div>
                </div>

                <!-- Landscape Grid -->
                <div class="info-landscape-grid">
                    <!-- Address Item -->
                    <div class="contact-info-item">
                        <div class="contact-icon"><i class="fas fa-building-columns"></i></div>
                        <div class="contact-details">
                            <h3>CSWDO Building Address</h3>
                            <p>City Social Welfare and Development Office (CSWDO)<br>City Social Welfare Building (In front of Palayan City National High School)<br>Nueva Ecija&ndash;Aurora Road, Palayan City, 3132</p>
                            <div class="item-actions">
                                <button type="button" class="btn-mini-action" onclick="copyText('City Social Welfare and Development Office, City Social Welfare Building (In front of Palayan City National High School), Nueva Ecija-Aurora Road, Palayan City, 3132', 'Address copied to clipboard!')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <a href="https://www.google.com/maps/place/City+Social+Welfare+And+Development+Office/@15.5619884,121.0995218,18.15z/data=!4m6!3m5!1s0x339732936cf4bb71:0x5941cfaa54e2364f!8m2!3d15.5624774!4d121.100356!16s%2Fg%2F11tg8q265w" target="_blank" rel="noopener noreferrer" class="btn-mini-action">
                                    <i class="fas fa-directions"></i> Directions
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Phone Item -->
                    <div class="contact-info-item">
                        <div class="contact-icon"><i class="fas fa-phone-volume"></i></div>
                        <div class="contact-details">
                            <h3>Public Hotlines &amp; Trunklines</h3>
                            <p>
                                <a href="tel:0441234567"><i class="fas fa-phone-alt" style="font-size: 0.78rem;"></i> (044) 123-4567</a><br>
                                <a href="tel:89622813">8962-2813</a> &bull; <a href="tel:89517433">8951-7433</a>
                            </p>
                            <div class="item-actions">
                                <button type="button" class="btn-mini-action" onclick="copyText('(044) 123-4567', 'Phone number copied!')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <a href="tel:0441234567" class="btn-mini-action">
                                    <i class="fas fa-phone"></i> Call Direct
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Email Item -->
                    <div class="contact-info-item" style="grid-column: 1 / -1;">
                        <div class="contact-icon"><i class="fas fa-envelope-open-text"></i></div>
                        <div class="contact-details">
                            <h3>Official Email Inquiries</h3>
                            <p>
                                <a href="mailto:cswdo@palayancity.gov.ph">cswdo@palayancity.gov.ph</a> &bull;
                                <a href="mailto:info@palayancity.gov.ph">info@palayancity.gov.ph</a>
                            </p>
                            <div class="item-actions">
                                <button type="button" class="btn-mini-action" onclick="copyText('cswdo@palayancity.gov.ph', 'Email copied to clipboard!')">
                                    <i class="fas fa-copy"></i> Copy Email
                                </button>
                                <a href="mailto:cswdo@palayancity.gov.ph" class="btn-mini-action">
                                    <i class="fas fa-paper-plane"></i> Write Email
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Office Hours Card -->
                    <div class="office-hours-card" style="grid-column: 1 / -1;">
                        <div class="hours-top-bar">
                            <div class="hours-title">
                                <i class="fas fa-clock"></i>
                                <span>Operating Hours</span>
                            </div>
                            <div class="office-status-badge <?php echo $office_status_class; ?>" title="<?php echo $office_status_detail; ?>">
                                <span class="pulse-dot <?php echo ($office_status_class == 'status-lunch' ? 'lunch' : ($office_status_class == 'status-closed' ? 'closed' : '')); ?>"></span>
                                <span><?php echo $office_status_label; ?></span>
                            </div>
                        </div>
                        <ul class="hours-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px;">
                            <li>
                                <span class="day">Monday &ndash; Friday</span>
                                <span class="time">8:00 AM &ndash; 5:00 PM</span>
                            </li>
                            <li>
                                <span class="day">Lunch Break</span>
                                <span class="time">12:00 NN &ndash; 1:00 PM</span>
                            </li>
                            <li>
                                <span class="day">Saturday &ndash; Sunday</span>
                                <span class="time">Closed (Weekend)</span>
                            </li>
                            <li>
                                <span class="day">Official Holidays</span>
                                <span class="time">Closed</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- CONTAINER 2: Send a Message Form -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-header-title">
                        <div class="section-header-icon" style="background: rgba(15, 65, 74, 0.1); color: var(--palayan-teal);">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div>
                            <h2>Send a Message</h2>
                            <p>Direct inquiry to the City Social Welfare &amp; Development Office</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Topic Selector Pills -->
                <div class="topic-selector-box">
                    <span class="topic-label"><i class="fas fa-lightbulb" style="color: var(--palayan-gold);"></i> Choose an Inquiry Topic (Quick Fill):</span>
                    <div class="topic-pills">
                        <button type="button" class="topic-pill" data-topic="AICS Financial / Medical Assistance Inquiry">
                            <i class="fas fa-hand-holding-heart"></i> AICS Assistance
                        </button>
                        <button type="button" class="topic-pill" data-topic="Housing Program Application Inquiry">
                            <i class="fas fa-home"></i> Housing Program
                        </button>
                        <button type="button" class="topic-pill" data-topic="Senior Citizen / PWD Identification &amp; Services">
                            <i class="fas fa-id-card"></i> Senior / PWD
                        </button>
                        <button type="button" class="topic-pill" data-topic="Appointment Schedule Question">
                            <i class="fas fa-calendar-alt"></i> Appointment
                        </button>
                        <button type="button" class="topic-pill" data-topic="General Public Assistance Inquiry">
                            <i class="fas fa-comment-dots"></i> General Inquiry
                        </button>
                    </div>
                </div>

                <form method="POST" action="" id="contactForm">
                    <?php echo csrf_field(); ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nameInput">
                                <span class="label-title"><i class="fas fa-user"></i> Your Full Name *</span>
                            </label>
                            <input type="text" name="name" id="nameInput" required placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars($prefill_name); ?>">
                        </div>

                        <div class="form-group">
                            <label for="emailInput">
                                <span class="label-title"><i class="fas fa-envelope"></i> Email Address *</span>
                            </label>
                            <input type="email" name="email" id="emailInput" required placeholder="juan@example.com" value="<?php echo htmlspecialchars($prefill_email); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subjectInput">
                            <span class="label-title"><i class="fas fa-tag"></i> Subject *</span>
                        </label>
                        <input type="text" name="subject" id="subjectInput" required placeholder="e.g., Medical Assistance Requirements or General Inquiry">
                    </div>

                    <div class="form-group">
                        <label for="messageInput">
                            <span class="label-title"><i class="fas fa-comment-alt"></i> Your Message *</span>
                            <span class="char-counter" id="charCounter">0 / 1000</span>
                        </label>
                        <textarea name="message" id="messageInput" rows="6" maxlength="1000" required placeholder="Please provide specific details regarding your inquiry, application, or feedback so we can assist you promptly..."></textarea>
                    </div>

                    <button type="submit" name="send_message" id="submitBtn" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Send Message to CSWDO
                    </button>
                </form>

                <!-- Advisory Box -->
                <div class="advisory-box">
                    <div class="advisory-icon">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div class="advisory-content">
                        <h5>Official Public Assistance Guarantee</h5>
                        <p>
                            Inquiries received via this portal are monitored by the CSWDO intake staff during official working days (Monday&ndash;Friday, 8AM&ndash;5PM). For urgent crisis relief or emergency burial/medical needs, please proceed directly to the <strong>CSWDO Building (in front of Palayan City National High School), Windows 1, 2, or 3</strong>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTAINER 3: Begins with Follow Us on Social Media, Occupies Underneath Contact Info & Send Message -->
        <div class="bottom-social-map-container">
            <div class="bottom-social-grid">
                <!-- Left: Social Media Card -->
                <div class="social-showcase-card" style="margin-top: 0; box-shadow: none; border: none; padding: 0;">
                    <div class="social-card-head">
                        <div class="social-head-title">
                            <div class="social-fb-badge">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <div>
                                <h3>Follow Us on Social Media</h3>
                                <p>Stay connected with CSWDO Palayan City</p>
                            </div>
                        </div>
                        <span class="official-tag">
                            <i class="fas fa-check-circle"></i> Official LGU Channel
                        </span>
                    </div>

                    <!-- Authentic Team & Office Photo -->
                    <div class="cswdo-photo-wrapper">
                        <img src="images/cswdo-palayan-team.jpg" alt="City Social Welfare and Development Office Team &amp; Facility - Palayan City" class="cswdo-team-photo">
                        <div class="photo-gradient-overlay">
                            <span class="photo-tag-pill"><i class="fas fa-users"></i> Palayan CSWDO Staff &amp; Facility</span>
                            <div class="photo-caption-title">City Social Welfare &amp; Development Office</div>
                            <div class="photo-caption-sub">Serving the people of Palayan City with compassion &amp; dedication</div>
                        </div>
                    </div>

                    <div class="social-body-details">
                        <div class="social-page-profile">
                            <div class="page-avatar-wrap">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <div class="page-name-info">
                                <h4>CSWDO Palayan City <i class="fas fa-check-circle" style="color: #1877F2; font-size: 0.88rem;" title="Verified Official Page"></i></h4>
                                <span class="handle">@PalayanCSWDO</span>
                            </div>
                        </div>
                        <p class="social-desc">
                            Follow our official Facebook page for real-time announcements, emergency relief schedules, AICS payout schedules, Senior Citizen &amp; PWD program announcements, and community activities.
                        </p>
                        <div class="social-cta-row">
                            <a href="https://www.facebook.com/PalayanCSWDO" target="_blank" rel="noopener noreferrer" class="btn-social-cta btn-fb-main" title="Open CSWDO Palayan City Facebook Page in new tab">
                                <i class="fab fa-facebook"></i> Follow on Facebook <i class="fas fa-external-link-alt" style="font-size: 0.72rem; margin-left: 2px;"></i>
                            </a>
                            <a href="https://m.me/PalayanCSWDO" target="_blank" rel="noopener noreferrer" class="btn-social-cta btn-messenger" title="Send a Facebook message to CSWDO Palayan City">
                                <i class="fab fa-facebook-messenger"></i> Chat on Messenger
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right: Embedded Location Map -->
                <div class="map-card" style="margin-top: 0; box-shadow: none; border: none; padding: 0;">
                    <div class="map-header">
                        <span><i class="fas fa-map-marked-alt"></i> City Social Welfare And Development Office</span>
                        <a href="https://www.google.com/maps/place/City+Social+Welfare+And+Development+Office/@15.5619884,121.0995218,18.15z/data=!4m6!3m5!1s0x339732936cf4bb71:0x5941cfaa54e2364f!8m2!3d15.5624774!4d121.100356!16s%2Fg%2F11tg8q265w" target="_blank" rel="noopener noreferrer" class="btn-mini-action" title="Open City Social Welfare And Development Office on Google Maps">
                            <i class="fas fa-external-link-alt"></i> Open in Maps
                        </a>
                    </div>
                    <div class="map-frame-wrap" style="height: 380px;">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3837.7533814631375!2d121.0981673!3d15.5624774!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x339732936cf4bb71%3A0x5941cfaa54e2364f!2sCity%20Social%20Welfare%20And%20Development%20Office!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph"
                            allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="City Social Welfare And Development Office Location Map"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Copy Toast Notification -->
    <div class="copy-toast" id="copyToast">
        <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
        <span id="copyToastText">Copied to clipboard!</span>
    </div>


    <script>
        // Character counter for message textarea
        const messageInput = document.getElementById('messageInput');
        const charCounter = document.getElementById('charCounter');
        if (messageInput && charCounter) {
            messageInput.addEventListener('input', function() {
                const len = this.value.length;
                charCounter.textContent = `${len} / 1000`;
                if (len >= 950) {
                    charCounter.style.color = '#EF4444';
                } else {
                    charCounter.style.color = '';
                }
            });
        }

        // Quick Topic Pills
        const topicPills = document.querySelectorAll('.topic-pill');
        const subjectInput = document.getElementById('subjectInput');
        topicPills.forEach(pill => {
            pill.addEventListener('click', function() {
                topicPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                if (subjectInput) {
                    subjectInput.value = this.getAttribute('data-topic');
                    subjectInput.focus();
                }
            });
        });

        // Copy Text Helper
        function copyText(text, successMsg) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(successMsg || 'Copied to clipboard!');
                }).catch(() => {
                    fallbackCopy(text, successMsg);
                });
            } else {
                fallbackCopy(text, successMsg);
            }
        }

        function fallbackCopy(text, successMsg) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showToast(successMsg || 'Copied to clipboard!');
            } catch (err) {
                showToast('Failed to copy text.');
            }
            document.body.removeChild(ta);
        }

        function showToast(msg) {
            const toast = document.getElementById('copyToast');
            const toastText = document.getElementById('copyToastText');
            if (!toast || !toastText) return;
            toastText.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Auto-fade alerts after 6 seconds
        setTimeout(function () {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 6000);

        // Form submit feedback
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        if (contactForm && submitBtn) {
            contactForm.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending message...';
            });
        }
    </script>
</body>

</html>

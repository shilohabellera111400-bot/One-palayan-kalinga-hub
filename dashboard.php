<?php
// dashboard.php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified (skip for staff and admin)
if (!isStaff() && !isAdmin()) {
    $check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
    $verify_stmt = $db->prepare($check_verified);
    $verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $verify_stmt->execute();
    $user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_verify || $user_verify['verification_status'] != 'verified') {
        // Allow unverified users to stay on dashboard but show prompt
    }
}

// Redirect staff and admin to their respective dashboards
if (isStaff()) {
    redirect('staff/staff-dashboard.php');
} elseif (isAdmin()) {
    redirect('admin/index.php');
}

// Fetch user data
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's appointments
$app_query = "SELECT a.*, s.service_name 
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              WHERE a.user_id = :user_id 
              ORDER BY a.appointment_date DESC, a.appointment_time DESC 
              LIMIT 5";
$app_stmt = $db->prepare($app_query);
$app_stmt->bindParam(':user_id', $_SESSION['user_id']);
$app_stmt->execute();

// Fetch notifications
$notif_query = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT 5";
$notif_stmt = $db->prepare($notif_query);
$notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
$notif_stmt->execute();

// Fetch summary counts
$counts_query = "SELECT 
    (SELECT COUNT(*) FROM appointments WHERE user_id = :u1 AND status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM appointments WHERE user_id = :u2 AND status = 'confirmed' AND appointment_date >= CURDATE()) as upcoming_appointments,
    (SELECT COUNT(*) FROM aics_requests WHERE user_id = :u3) as total_aics,
    (SELECT COUNT(*) FROM notifications WHERE user_id = :u4 AND is_read = 0) as unread_notifications";
$counts_stmt = $db->prepare($counts_query);
$counts_stmt->bindParam(':u1', $_SESSION['user_id']);
$counts_stmt->bindParam(':u2', $_SESSION['user_id']);
$counts_stmt->bindParam(':u3', $_SESSION['user_id']);
$counts_stmt->bindParam(':u4', $_SESSION['user_id']);
$counts_stmt->execute();
$counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch announcements for the animated vertical ticker
$dash_ann_query = "SELECT a.*, u.first_name, u.last_name 
                   FROM announcements a 
                   LEFT JOIN users u ON a.created_by = u.user_id 
                   ORDER BY a.created_at DESC 
                   LIMIT 6";
$dash_ann_stmt = $db->prepare($dash_ann_query);
$dash_ann_stmt->execute();
$dash_announcements = $dash_ann_stmt->fetchAll(PDO::FETCH_ASSOC);

// If database has fewer than 4 announcements, supplement with official CSWDO advisories
if (count($dash_announcements) < 4) {
    $fallback_announcements = [
        [
            'announcement_id' => 901,
            'title' => 'CSWDO AICS Aid & Medical Assistance Payout Schedule',
            'content' => 'Scheduled assessment and claiming of AICS medical, food, and financial grants for qualified residents at Palayan City Hall Ground Floor.',
            'category' => 'aics',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'announcement_id' => 902,
            'title' => 'Socialized Housing Relocation & Resettlement Orientation',
            'content' => 'All pending applicants for the Palayan City Socialized Resettlement Project are invited to attend the document verification and briefing.',
            'category' => 'housing',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ],
        [
            'announcement_id' => 903,
            'title' => 'Digital Appointment Desk & Priority Lane Guidelines',
            'content' => 'Citizens are advised to book appointments online before visiting the CSWDO to ensure fast queueing and instant QR verification.',
            'category' => 'appointment',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ],
        [
            'announcement_id' => 904,
            'title' => 'Barangay Outreach & Citizen Social Welfare Helpdesk',
            'content' => 'Mobile social caseworkers will visit local barangay halls this week for on-site indigency profiling and welfare assistance verification.',
            'category' => 'general',
            'created_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
        ]
    ];
    foreach ($fallback_announcements as $fallback) {
        if (count($dash_announcements) >= 4) break;
        $dash_announcements[] = $fallback;
    }
}


// Verification Status
$verification_status = $user['verification_status'] ?? 'pending';

// Profile Completion calculation
$profile_fields = ['full_name', 'email', 'contact_number', 'address', 'profile_picture'];
$filled_fields = 0;
foreach ($profile_fields as $f) {
    if (!empty($user[$f])) $filled_fields++;
}
$profile_percent = round(($filled_fields / count($profile_fields)) * 100);

// Initials for avatar fallback
$initials = '';
$names = explode(' ', trim($_SESSION['full_name'] ?? 'User'));
foreach ($names as $n) {
    if (!empty($n)) $initials .= strtoupper($n[0]);
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'U';

// Profile picture path
$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = (!empty($profile_picture) && file_exists($profile_picture)) ? $profile_picture : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get greeting based on time
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
$greeting_emoji = $hour < 12 ? '🌅' : ($hour < 18 ? '☀️' : '🌙');

// Check residents table details
$has_profile_details = false;
$user_is_senior = false;
try {
    $res_info_query = "SELECT birth_date, civil_status, senior_citizen FROM residents WHERE user_id = :user_id";
    $res_info_stmt = $db->prepare($res_info_query);
    $res_info_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $res_info_stmt->execute();
    $resident_record = $res_info_stmt->fetch(PDO::FETCH_ASSOC);
    $has_profile_details = !empty($resident_record['birth_date']) && !empty($resident_record['civil_status']);
    if (!empty($resident_record['birth_date']) && $resident_record['birth_date'] !== '0000-00-00') {
        $b = new DateTime($resident_record['birth_date']);
        $now = new DateTime();
        if ($now->diff($b)->y >= 60) {
            $user_is_senior = true;
        }
    }
} catch (Exception $e) {
    // Graceful fallback
}

// Guided Steps Logic
$guided_step = 1;
$guided_url = 'verify-resident.php';
$guided_badge = 'Step 1 of 2 • Identity Verification';
$guided_title = 'Verify Your Palayan Residency 🏛️';
$guided_desc = 'Upload your valid government ID and proof of residency to verify your account with CSWDO and unlock all city services.';
$guided_btn = 'Step 1: Verify Residency';

if ($verification_status !== 'verified') {
    $guided_step = 1;
    $guided_url = 'verify-resident.php';
    $guided_badge = 'Step 1 of 2 • Identity Verification';
    $guided_title = 'Verify Your Palayan Residency 🏛️';
    $guided_desc = 'Upload your valid government ID and proof of residency to verify your account with CSWDO and unlock all city services.';
    $guided_btn = 'Step 1: Verify Residency';
} elseif (!$has_profile_details) {
    $guided_step = 2;
    $guided_url = 'update-profile-info.php?guided=1';
    $guided_badge = 'Step 2 of 2 • Complete Your Profile';
    $guided_title = 'Complete Personal Information ✍️';
    $guided_desc = 'Fill in your birth date, civil status, and family background to enable faster processing for AICS, Housing, and Social Programs.';
    $guided_btn = 'Step 2: Complete Profile';
} else {
    $guided_step = 3;
    $guided_url = 'profile.php';
    $guided_badge = 'Profile Complete • 100%';
    $guided_title = 'Account Fully Verified 🎉';
    $guided_desc = 'You have unrestricted access to all CSWDO Online Services, Appointments, AICS Financial Assistance, and Senior/PWD benefits.';
    $guided_btn = 'View Profile';
}

// Include interactive resident calendar helper
if (file_exists(__DIR__ . '/includes/resident-calendar-helper.php')) {
    require_once __DIR__ . '/includes/resident-calendar-helper.php';
}
$cal_req_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');
$cal_req_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');
if ($cal_req_month < 1) { $cal_req_month = 12; $cal_req_year--; }
elseif ($cal_req_month > 12) { $cal_req_month = 1; $cal_req_year++; }

$resident_cal = function_exists('getResidentCalendarData')
    ? getResidentCalendarData($db, $_SESSION['user_id'], $cal_req_month, $cal_req_year)
    : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Portal Dashboard - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600;1,700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Bulgash';
            src: url('fonts/Bulgash.otf') format('opentype'),
                 url('../fonts/Bulgash.otf') format('opentype'),
                 url('./fonts/Bulgash.otf') format('opentype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Brigtha';
            src: url('fonts/Brigtha.ttf') format('truetype'),
                 url('../fonts/Brigtha.ttf') format('truetype'),
                 url('./fonts/Brigtha.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        /* ========== DASHBOARD SYSTEM STYLES ========== */
        :root {
            --ref-canvas: #E4EFE7;
            --ref-card: #FFFFFF;
            --ref-pill-dark: #191D1E;
            --ref-text: #192126;
            --ref-muted: #71818B;
            --ref-border: rgba(0, 0, 0, 0.05);
            --ref-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            
            /* Pastel Card Colors */
            --card-yellow-bg: #FEF7DF;
            --card-yellow-border: rgba(245, 158, 11, 0.22);
            --card-blue-bg: #E7F1FE;
            --card-blue-border: rgba(59, 130, 246, 0.22);
            --card-pink-bg: #FDE8E8;
            --card-pink-border: rgba(239, 68, 68, 0.22);

            --accent-gold: #E5B338;
            --accent-green: #10B981;
            --maroon: #7F0303;
            --maroon-light: #9A1515;
        }

        [data-theme="dark"] {
            --ref-canvas: #0C1417;
            --ref-card: #152227;
            --ref-pill-dark: #080D0E;
            --ref-text: #E5EFF2;
            --ref-muted: #8AA0A9;
            --ref-border: rgba(255, 255, 255, 0.08);
            --ref-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
            
            --card-yellow-bg: #292415;
            --card-yellow-border: rgba(245, 158, 11, 0.3);
            --card-blue-bg: #142435;
            --card-blue-border: rgba(59, 130, 246, 0.3);
            --card-pink-bg: #2C181C;
            --card-pink-border: rgba(239, 68, 68, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        body {
            background-color: var(--ref-canvas);
            font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
            color: var(--ref-text);
            min-height: 100vh;
            padding: 20px 24px 60px;
            transition: background-color 0.35s ease, color 0.35s ease;
            overflow-x: hidden;
        }

        /* Outer Dashboard Container Frame */
        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            display: flex;
            gap: 20px;
            align-items: stretch;
            position: relative;
        }

        /* Main Dashboard Canvas */
        .dashboard-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Subheader Bar */
        .dash-subheader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2px 4px 6px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .subheader-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-back-arrow {
            color: var(--ref-text);
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 700;
            transition: transform 0.2s ease;
        }

        .btn-back-arrow:hover {
            transform: translateX(-3px);
            color: #7F0303;
        }

        .subheader-title-wrap h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--ref-text);
            letter-spacing: -0.02em;
        }

        .subheader-status-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 20px;
            background: var(--ref-card);
            border: 1px solid var(--ref-border);
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-muted);
            box-shadow: var(--ref-shadow);
        }

        .status-badge-pill.verified {
            color: #059669;
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.25);
        }

        .status-badge-pill.pending {
            color: #D97706;
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.25);
        }

        .btn-round-mini {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--ref-card);
            border: 1px solid var(--ref-border);
            color: var(--ref-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--ref-shadow);
            text-decoration: none;
        }

        .btn-round-mini:hover {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
            transform: translateY(-2px);
        }

        /* 2-Column Content Grid (390px 1fr) */
        .dash-content-grid {
            display: grid;
            grid-template-columns: 390px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* Column Left */
        .column-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Card 1A: Profile Summary Card */
        .ref-card-profile {
            background: var(--ref-card);
            border-radius: 28px;
            padding: 24px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
            position: relative;
        }

        .profile-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .profile-user-summary {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .profile-avatar-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            overflow: hidden;
            background: #FDE68A;
            border: 2.5px solid #E5B338;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
            color: #7F0303;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }

        .profile-avatar-circle img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
        }

        .profile-user-headings h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--ref-text);
            margin-bottom: 4px;
        }

        .profile-user-headings p {
            font-size: 0.78rem;
            color: var(--ref-muted);
            font-weight: 500;
        }

        .profile-action-circles {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .action-circle-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(45, 74, 62, 0.08);
            border: 1px solid rgba(45, 74, 62, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2D4A3E;
            text-decoration: none;
            font-size: 1.05rem;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .action-circle-btn:hover {
            background: #7F0303;
            color: #ffffff;
            border-color: #7F0303;
            transform: translateY(-3px) scale(1.08);
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.3);
        }

        [data-theme="dark"] .action-circle-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #D4AF37;
            border-color: rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .action-circle-btn:hover {
            background: #7F0303;
            color: #ffffff;
            border-color: #7F0303;
        }

        .profile-slots-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid var(--ref-border);
        }

        .slot-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 20px;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--ref-text);
        }

        .meetings-counter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-text);
        }

        .counter-pill-black {
            background: var(--ref-pill-dark);
            color: #ffffff;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 12px;
        }

        [data-theme="dark"] .counter-pill-black {
            background: #ffffff;
            color: #191D1E;
        }

        
        /* Card 1B: Announcements Ticker Card */
        .ref-card-announcements {
            background: var(--ref-card);
            border-radius: 28px;
            padding: 22px 24px 20px 24px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .ann-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ann-head-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ann-head-icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(127, 3, 3, 0.1);
            color: #7F0303;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .ann-head-left h4 {
            font-size: 0.96rem;
            font-weight: 800;
            color: var(--ref-text);
            margin: 0;
            letter-spacing: -0.01em;
        }

        .ann-head-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ann-live-pill {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #059669;
            background: rgba(16, 185, 129, 0.12);
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ann-live-pill .pulsing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10B981;
            animation: annPulse 1.8s infinite;
        }

        @keyframes annPulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Viewport Stage */
        .ann-ticker-stage {
            position: relative;
            width: 100%;
            height: 270px;
            overflow: hidden;
            border-radius: 22px;
        }

        .ann-ticker-item {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transform: translateY(100%);
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 18px 20px 14px 20px;
            background: linear-gradient(145deg, #FFFFFF 0%, rgba(228, 239, 231, 0.45) 100%);
            border: 1px solid var(--ref-border);
            border-left: 4.5px solid #7F0303;
            border-radius: 22px;
            box-sizing: border-box;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            transition: transform 0.8s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.8s ease, box-shadow 0.25s ease;
        }

        [data-theme="dark"] .ann-ticker-item {
            background: linear-gradient(145deg, #1A242B 0%, #151E24 100%);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .ann-ticker-item.border-cat-aics { border-left-color: #7F0303; }
        .ann-ticker-item.border-cat-housing { border-left-color: #0E7490; }
        .ann-ticker-item.border-cat-appointment { border-left-color: #D97706; }
        .ann-ticker-item.border-cat-general { border-left-color: #4F46E5; }

        .ann-ticker-item:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-color: rgba(127, 3, 3, 0.35);
        }

        /* Active: Stopped right in the position where the first announcement was */
        .ann-ticker-item.is-active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
            z-index: 2;
        }

        /* Leaving: Vanishes fading going UP */
        .ann-ticker-item.is-leaving {
            opacity: 0;
            transform: translateY(-90%);
            pointer-events: none;
            z-index: 1;
        }

        /* Entering: Prepared from below */
        .ann-ticker-item.is-entering {
            opacity: 0;
            transform: translateY(90%);
            pointer-events: none;
        }

        /* Mini Banner Photo Strip if announcement has photo */
        .ann-item-photo-strip {
            width: 100%;
            height: 70px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 10px;
            position: relative;
            background-size: cover;
            background-position: center;
            border: 1px solid rgba(0,0,0,0.06);
            display: flex;
            align-items: flex-end;
            padding: 6px 10px;
            box-sizing: border-box;
        }
        .ann-item-photo-strip::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.65) 100%);
        }
        .ann-media-badge {
            position: relative;
            z-index: 2;
            color: #FFFFFF;
            font-size: 0.64rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            padding: 2px 7px;
            border-radius: 6px;
        }

        /* Content inside announcement item */
        .ann-ticker-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .ann-cat-tag {
            font-size: 0.67rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ann-cat-tag.cat-aics {
            background: rgba(127, 3, 3, 0.12);
            color: #7F0303;
            border: 1px solid rgba(127, 3, 3, 0.2);
        }
        .ann-cat-tag.cat-housing {
            background: rgba(14, 116, 144, 0.12);
            color: #0E7490;
            border: 1px solid rgba(14, 116, 144, 0.2);
        }
        .ann-cat-tag.cat-appointment {
            background: rgba(217, 119, 6, 0.12);
            color: #D97706;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }
        .ann-cat-tag.cat-general {
            background: rgba(79, 70, 229, 0.12);
            color: #4F46E5;
            border: 1px solid rgba(79, 70, 229, 0.2);
        }

        .ann-date-tag {
            font-size: 0.72rem;
            color: var(--ref-muted);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .ann-ticker-title {
            font-size: 1.02rem;
            font-weight: 850;
            color: var(--ref-text);
            line-height: 1.32;
            margin: 0 0 6px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            letter-spacing: -0.015em;
        }

        .ann-ticker-desc {
            font-size: 0.78rem;
            color: var(--ref-muted);
            line-height: 1.5;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }

        .ann-ticker-bottom-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            margin-top: 10px;
            font-size: 0.72rem;
        }

        [data-theme="dark"] .ann-ticker-bottom-row {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .ann-source-tag {
            color: var(--ref-muted);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ann-read-btn {
            background: rgba(127, 3, 3, 0.08);
            color: #7F0303;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .ann-read-btn {
            background: rgba(248, 113, 113, 0.15);
            color: #FCA5A5;
        }

        .ann-ticker-item:hover .ann-read-btn {
            background: #7F0303;
            color: #FFFFFF;
            transform: translateX(2px);
        }

        /* Controls footer */
        .ann-ticker-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 4px;
        }

        .ann-dots-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ann-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--ref-border);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .ann-dot.active {
            width: 20px;
            border-radius: 8px;
            background: #7F0303;
        }

        .ann-arrows-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ann-arrow-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            color: var(--ref-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ann-arrow-btn:hover {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
        }

        .ann-counter-txt {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--ref-muted);
            letter-spacing: 0.04em;
            margin-right: 4px;
        }

        /* Card 1B: Detailed Information Card (Preserved) */
        .ref-card-details {
            background: var(--ref-card);
            border-radius: 28px;
            padding: 24px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
        }

        .card-details-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .card-details-header h4 {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--ref-text);
        }

        .online-pill-tag {
            font-size: 0.68rem;
            font-weight: 700;
            color: #059669;
            background: rgba(16, 185, 129, 0.12);
            padding: 4px 10px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .online-pill-tag::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10B981;
        }

        .details-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 16px;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            transition: background 0.2s ease;
        }

        .detail-item-row:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .detail-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .detail-radio-icon {
            color: #7F0303;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .detail-text-wrap {
            min-width: 0;
        }

        .detail-label {
            font-size: 0.68rem;
            color: var(--ref-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }

        .detail-value {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--ref-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .detail-action-icon {
            color: var(--ref-muted);
            font-size: 0.82rem;
            padding: 6px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .detail-action-icon:hover {
            color: #7F0303;
        }

        /* ================= RIGHT COLUMN STYLES ================= */
        .column-right {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 0;
        }

        .ongoing-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 6px 2px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ongoing-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            background: var(--ref-card);
            border: 1px solid var(--ref-border);
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-muted);
            box-shadow: var(--ref-shadow);
        }

        .dash-cursive-title {
            font-family: 'Bulgash', 'Playfair Display', cursive, serif !important;
            font-size: 2.35rem !important;
            font-weight: normal !important;
            color: #7F0303 !important;
            letter-spacing: 0.5px !important;
            line-height: 1.15 !important;
            margin: 0 !important;
            user-select: none !important;
        }

        [data-theme="dark"] .dash-cursive-title {
            color: #F87171 !important;
        }

        .ongoing-active-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-muted);
        }

        .active-dot-live {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: 0.7; }
        }

        /* 3 Colored Service Cards Minimalist UI */
        .colored-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .ref-service-card.service-card-minimal {
            min-height: 205px;
            padding: 24px 22px;
            border-radius: 26px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .ref-service-card.service-card-minimal:hover {
            transform: translateY(-4px);
        }

        .ref-service-card.card-yellow {
            background: #FEF7DF;
            border: 1px solid rgba(217, 119, 6, 0.22);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
        }
        .ref-service-card.card-yellow:hover {
            box-shadow: 0 8px 24px rgba(217, 119, 6, 0.14);
            border-color: rgba(217, 119, 6, 0.35);
        }

        .ref-service-card.card-blue {
            background: #E7F1FE;
            border: 1px solid rgba(59, 130, 246, 0.22);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
        }
        .ref-service-card.card-blue:hover {
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.14);
            border-color: rgba(59, 130, 246, 0.35);
        }

        .ref-service-card.card-pink {
            background: #FDE8E8;
            border: 1px solid rgba(239, 68, 68, 0.22);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
        }
        .ref-service-card.card-pink:hover {
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.14);
            border-color: rgba(239, 68, 68, 0.35);
        }

        [data-theme="dark"] .ref-service-card.card-yellow {
            background: #252014;
            border-color: rgba(217, 119, 6, 0.4);
            border-top-color: #F59E0B;
        }
        [data-theme="dark"] .ref-service-card.card-blue {
            background: #142230;
            border-color: rgba(2, 132, 199, 0.4);
            border-top-color: #38BDF8;
        }
        [data-theme="dark"] .ref-service-card.card-pink {
            background: #27161b;
            border-color: rgba(225, 29, 72, 0.4);
            border-top-color: #FB7185;
        }

        .service-card-giant-watermark {
            position: absolute;
            right: -45px;
            bottom: -45px;
            font-size: 195px;
            line-height: 1;
            pointer-events: none;
            user-select: none;
            transition: all 0.35s ease;
            z-index: 1;
            color: #64748B !important;
            opacity: 0.08 !important;
        }

        .ref-service-card:hover .service-card-giant-watermark {
            transform: scale(1.08) rotate(-4deg);
            opacity: 0.12 !important;
        }

        .service-card-top-tag {
            position: relative;
            z-index: 2;
            margin-bottom: 12px;
        }

        .service-category-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .pill-yellow { background: rgba(217, 119, 6, 0.12); color: #B45309; }
        .pill-blue { background: rgba(2, 132, 199, 0.12); color: #0369A1; }
        .pill-pink { background: rgba(225, 29, 72, 0.12); color: #BE123C; }

        .service-card-minimal .service-card-title {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #1E293B;
            margin: 0;
            line-height: 1.22;
            letter-spacing: -0.02em;
            position: relative;
            z-index: 2;
        }

        [data-theme="dark"] .service-card-minimal .service-card-title {
            color: #F8FAFC;
        }

        .service-card-bottom-minimal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            position: relative;
            z-index: 2;
        }

        .service-card-action-text {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-muted, #64748B);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            transition: color 0.2s;
        }

        .ref-service-card:hover .service-card-action-text {
            color: #7F0303;
        }

        [data-theme="dark"] .ref-service-card:hover .service-card-action-text {
            color: #E8D5A8;
        }

        .service-card-arrow-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #FFFFFF;
            color: #1E293B;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.22s ease;
            text-decoration: none;
        }

        .ref-service-card:hover .service-card-arrow-btn {
            background: #7F0303;
            color: #FFFFFF;
            transform: translateX(4px);
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25);
        }

        /* Bottom Split Section (Calendar Schedule + Recent Requests Activity) */
        .bottom-split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .ref-card-calendar, .ref-card-inbox {
            background: var(--ref-card);
            border-radius: 28px;
            padding: 24px;
            border: 1px solid var(--ref-border);
            box-shadow: var(--ref-shadow);
        }

        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .calendar-header h4 {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--ref-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .calendar-header h4 i {
            color: #7F0303;
        }

        .calendar-month-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--ref-canvas);
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid var(--ref-border);
        }

        .calendar-month-selector span {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--ref-text);
        }

        .btn-cal-nav {
            background: none;
            border: none;
            color: var(--ref-muted);
            cursor: pointer;
            padding: 2px 4px;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: color 0.15s;
        }

        .btn-cal-nav:hover {
            color: #7F0303;
        }

        .mini-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-bottom: 18px;
            text-align: center;
        }

        .cal-day-header {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--ref-muted);
            padding-bottom: 4px;
            text-transform: uppercase;
        }

        .calendar-upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-top: 1px solid var(--ref-border);
            padding-top: 14px;
        }

        .upcoming-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
        }

        .upcoming-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--ref-text);
        }

        .upcoming-badge-time {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--ref-muted);
        }

        /* Inbox Card Styles */
        .inbox-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .inbox-header h4 {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--ref-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .inbox-header h4 i {
            color: #7F0303;
        }

        .inbox-items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .inbox-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 16px;
            background: var(--ref-canvas);
            border: 1px solid var(--ref-border);
            transition: all 0.2s ease;
        }

        .inbox-item-row.highlighted {
            background: #FEF7DF;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .inbox-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .inbox-avatar-mini {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(127, 3, 3, 0.1);
            color: #7F0303;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .inbox-content-mini {
            min-width: 0;
        }

        .inbox-content-mini h5 {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--ref-text);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .inbox-content-mini p {
            font-size: 0.72rem;
            color: var(--ref-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .inbox-action-arrow {
            color: var(--ref-muted);
            font-size: 0.82rem;
            text-decoration: none;
            padding: 4px;
            transition: transform 0.2s;
        }

        .inbox-item-row:hover .inbox-action-arrow {
            transform: translateX(3px);
            color: #7F0303;
        }

        /* Responsive Breakpoints & Mobile Optimization */
        @media (max-width: 1200px) {
            .dash-content-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .colored-cards-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 14px;
            }
        }

        @media (max-width: 992px) {
            .dash-content-grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }
            .colored-cards-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
                gap: 14px;
            }
            .bottom-split-grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }
            .dash-subheader {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .subheader-status-wrap {
                width: 100%;
                justify-content: space-between;
            }
            .ongoing-section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Mobile Responsive Breakpoints (Mobile Mode Only) */
        @media (max-width: 768px) {
            body {
                padding: 12px 12px 28px !important;
            }

            /* Hide Bottom Tabs on Dashboard per user request */
            .mobile-bottom-nav {
                display: none !important;
            }

            /* Hide Subheader Bar so Dashboard begins with Services */
            .dash-subheader {
                display: none !important;
            }

            /* Reorder Columns: Services first, other cards below */
            .dash-content-grid {
                display: flex !important;
                flex-direction: column !important;
                gap: 14px !important;
            }

            .column-right {
                order: 1 !important;
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 14px !important;
            }

            .column-left {
                order: 2 !important;
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 14px !important;
            }

            /* Remove the Profile Card (name and pic) on mobile */
            .ref-card-profile {
                display: none !important;
            }

            /* Ongoing Services Header on Mobile */
            .ongoing-section-header {
                padding: 2px 2px 4px !important;
                gap: 6px !important;
            }

            .ongoing-badge-pill {
                font-size: 0.72rem !important;
                padding: 4px 10px !important;
            }

            .dash-cursive-title {
                font-size: 1.65rem !important;
                line-height: 1.2 !important;
                margin: 4px 0 !important;
            }

            .ongoing-active-indicator {
                font-size: 0.72rem !important;
            }

            /* 3 Colored Service Cards on Mobile */
            .colored-cards-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .ref-service-card.service-card-minimal {
                min-height: auto !important;
                padding: 16px 16px 14px !important;
                border-radius: 20px !important;
            }

            .service-card-minimal .service-card-title {
                font-size: 1.15rem !important;
            }

            .service-category-pill {
                font-size: 0.65rem !important;
                padding: 3px 9px !important;
            }

            .service-card-bottom-minimal {
                margin-top: 16px !important;
            }

            .service-card-action-text {
                font-size: 0.72rem !important;
            }

            .service-card-arrow-btn {
                width: 36px !important;
                height: 36px !important;
                font-size: 0.85rem !important;
            }

            .bottom-split-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            /* Make Calendar Small and Compact on Mobile */
            .ref-card-calendar {
                padding: 12px 14px !important;
                border-radius: 18px !important;
            }

            .calendar-header {
                margin-bottom: 8px !important;
            }

            .calendar-header h4 {
                font-size: 0.82rem !important;
            }

            .calendar-month-selector {
                padding: 2px 8px !important;
                border-radius: 14px !important;
            }

            .calendar-month-selector span {
                font-size: 0.68rem !important;
            }

            .btn-cal-nav {
                font-size: 0.65rem !important;
                padding: 1px 3px !important;
            }

            .mini-calendar-grid {
                gap: 3px !important;
                margin-bottom: 8px !important;
            }

            .cal-day-header {
                font-size: 0.58rem !important;
                padding-bottom: 2px !important;
            }

            .cal-day-cell {
                height: 24px !important;
                font-size: 0.65rem !important;
                border-radius: 5px !important;
                line-height: 24px !important;
            }

            .calendar-legend {
                gap: 6px !important;
                font-size: 0.58rem !important;
                margin: 4px 0 8px !important;
            }

            .calendar-upcoming-list {
                padding-top: 8px !important;
                gap: 6px !important;
            }

            .upcoming-item {
                padding: 5px 8px !important;
                border-radius: 8px !important;
                font-size: 0.68rem !important;
            }

            .upcoming-item-left {
                font-size: 0.68rem !important;
                gap: 6px !important;
            }

            .upcoming-badge-time {
                font-size: 0.62rem !important;
            }

            /* Announcement Card & Inbox on Mobile */
            .ref-card-announcements,
            .ref-card-inbox {
                padding: 14px 14px !important;
                border-radius: 18px !important;
            }

            .ann-ticker-stage {
                height: 220px !important;
            }

            .ann-ticker-title {
                font-size: 0.88rem !important;
            }

            .ann-ticker-desc {
                font-size: 0.72rem !important;
            }
        }
    </style>
    
    <!-- Interactive Calendar Hover Styles -->
    <style>
/* Calendar Day Indicators & Hover Popover */
.cal-day-cell {
    position: relative;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--ref-text);
    background: var(--ref-canvas);
    transition: all 0.2s ease;
    cursor: default;
}

.cal-day-cell.empty {
    background: transparent;
    cursor: default;
}

.cal-day-cell.is-today {
    border: 2.5px solid #7F0303 !important;
    font-weight: 900 !important;
    position: relative;
    box-sizing: border-box;
}

.cal-day-cell.is-today.has-event-yellow {
    border: 2.5px solid #7F0303 !important;
    box-shadow: 0 0 0 1px #7F0303, 0 3px 8px rgba(229, 179, 56, 0.45) !important;
}

.cal-day-cell.is-today.has-event-red {
    border: 2.5px solid #7F0303 !important;
    box-shadow: 0 0 0 1px #7F0303, 0 3px 8px rgba(248, 113, 113, 0.5) !important;
}

.cal-day-cell.is-today.has-event-dual {
    border: 2.5px solid #7F0303 !important;
    box-shadow: 0 0 0 1px #7F0303, 0 3px 8px rgba(239, 68, 68, 0.45) !important;
}

[data-theme="dark"] .cal-day-cell.is-today {
    border: 2.5px solid #F87171 !important;
}

.cal-day-cell.is-blackout {
    background: rgba(71, 85, 105, 0.12) !important;
    color: #475569 !important;
    text-decoration: line-through;
    opacity: 0.85;
}
.cal-day-cell.is-blackout::after {
    content: '';
    position: absolute;
    bottom: 3px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #475569;
}

[data-theme="dark"] .cal-day-cell.is-today.has-event-yellow,
[data-theme="dark"] .cal-day-cell.is-today.has-event-red,
[data-theme="dark"] .cal-day-cell.is-today.has-event-dual {
    border: 2.5px solid #F87171 !important;
    box-shadow: 0 0 0 1px #F87171, 0 3px 8px rgba(0, 0, 0, 0.5) !important;
}

/* Yellow Indicator: Personal Appointments & Applications */
.cal-day-cell.has-event-yellow {
    background: #E5B338 !important;
    color: #191D1E !important;
    font-weight: 800 !important;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(229, 179, 56, 0.45);
}

/* Red Indicator: City Announcements & Official Schedules */
.cal-day-cell.has-event-red {
    background: #F87171 !important;
    color: #FFFFFF !important;
    font-weight: 800 !important;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(248, 113, 113, 0.55);
}

/* Dual Indicator: Both Personal and Announcement/Event */
.cal-day-cell.has-event-dual {
    background: linear-gradient(135deg, #E5B338 50%, #F87171 50%) !important;
    color: #FFFFFF !important;
    font-weight: 800 !important;
    text-shadow: 0 1px 2px rgba(0,0,0,0.6);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
}

.cal-day-cell.has-event-yellow:hover,
.cal-day-cell.has-event-red:hover,
.cal-day-cell.has-event-dual:hover {
    transform: translateY(-2px) scale(1.08);
    z-index: 50;
}

/* Floating Hover Popover */
.cal-hover-popover {
    display: none;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    width: 240px;
    background: #FFFFFF;
    border: 1px solid var(--ref-border, #E2E8F0);
    border-radius: 14px;
    padding: 12px 14px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18), 0 2px 8px rgba(0, 0, 0, 0.08);
    z-index: 1000;
    pointer-events: none;
    text-align: left;
}

.cal-hover-popover::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: #FFFFFF transparent transparent transparent;
}

.cal-day-cell:hover .cal-hover-popover,
.cal-day-cell:focus .cal-hover-popover {
    display: block;
    animation: popoverFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes popoverFadeIn {
    from { opacity: 0; transform: translateX(-50%) translateY(4px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

@media (max-width: 600px) {
    .cal-hover-popover {
        width: min(220px, 80vw) !important;
    }
    .cal-day-cell:nth-child(7n+1) .cal-hover-popover,
    .cal-day-cell:nth-child(7n+2) .cal-hover-popover {
        left: 0 !important;
        transform: none !important;
    }
    .cal-day-cell:nth-child(7n+1) .cal-hover-popover::after,
    .cal-day-cell:nth-child(7n+2) .cal-hover-popover::after {
        left: 18px !important;
    }
    .cal-day-cell:nth-child(7n) .cal-hover-popover,
    .cal-day-cell:nth-child(7n+6) .cal-hover-popover {
        left: auto !important;
        right: 0 !important;
        transform: none !important;
    }
    .cal-day-cell:nth-child(7n) .cal-hover-popover::after,
    .cal-day-cell:nth-child(7n+6) .cal-hover-popover::after {
        left: auto !important;
        right: 18px !important;
        transform: none !important;
    }
}

.popover-date-head {
    font-size: 0.72rem;
    font-weight: 800;
    color: #0F414A;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding-bottom: 6px;
    margin-bottom: 6px;
    border-bottom: 1px solid #EDF2F7;
    display: flex;
    align-items: center;
    gap: 6px;
}

.popover-item {
    padding: 5px 0;
}
.popover-item:not(:last-child) {
    border-bottom: 1px dashed #EDF2F7;
    margin-bottom: 4px;
}

.popover-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
    margin-bottom: 3px;
    text-transform: uppercase;
}
.popover-tag.tag-yellow {
    background: #FEF3C7;
    color: #92400E;
}
.popover-tag.tag-red {
    background: #FEE2E2;
    color: #991B1B;
}

.popover-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #1E293B;
    line-height: 1.25;
}

.popover-desc {
    font-size: 0.67rem;
    color: #64748B;
    margin-top: 2px;
    line-height: 1.25;
}

.calendar-legend {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 10px;
    margin-bottom: 14px;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--ref-muted, #64748B);
    flex-wrap: wrap;
}
.legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}
.legend-dot.dot-yellow {
    background: #E5B338;
}
.legend-dot.dot-red {
    background: #F87171;
}

@media (max-width: 768px) {
    /* Full Responsive Width Containment on Mobile */
    body {
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
    }
    .dashboard-container {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        gap: 0 !important;
    }
    .dashboard-main,
    .dash-content-grid,
    .column-right,
    .column-left {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
        box-sizing: border-box !important;
    }

    /* Bottom Split Grid: Stack Vertically & Full Width */
    .bottom-split-grid {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        gap: 12px !important;
        box-sizing: border-box !important;
    }

    /* Calendar & Inbox Cards Fit Viewport */
    .ref-card-calendar,
    .ref-card-inbox,
    .ref-card-announcements {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        padding: 12px 10px !important;
        border-radius: 18px !important;
    }

    /* 7-Day Mini Calendar Grid (Never overflows, 7 equal columns) */
    .mini-calendar-grid {
        display: grid !important;
        grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
        gap: 2px !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-bottom: 8px !important;
        box-sizing: border-box !important;
    }
    .cal-day-header {
        font-size: 0.58rem !important;
        padding-bottom: 2px !important;
        text-align: center !important;
        min-width: 0 !important;
    }
    .cal-day-cell {
        height: 24px !important;
        min-height: 24px !important;
        max-height: 24px !important;
        font-size: 0.65rem !important;
        border-radius: 5px !important;
        line-height: 24px !important;
        padding: 0 !important;
        min-width: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Calendar Legend (Wraps cleanly, never pushes card width) */
    .calendar-legend,
    .cal-calendar-legend {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 4px 6px !important;
        justify-content: flex-start !important;
        width: 100% !important;
        box-sizing: border-box !important;
        margin: 6px 0 8px !important;
        font-size: 0.58rem !important;
    }
    .legend-pill {
        display: inline-flex !important;
        align-items: center !important;
        gap: 3px !important;
        font-size: 0.58rem !important;
        white-space: normal !important;
        line-height: 1.2 !important;
    }
    .legend-dot {
        width: 7px !important;
        height: 7px !important;
        flex-shrink: 0 !important;
    }

    /* Upcoming Booked Visits List */
    .calendar-upcoming-list {
        width: 100% !important;
        box-sizing: border-box !important;
        padding-top: 8px !important;
        gap: 6px !important;
    }
    .upcoming-item {
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 5px 8px !important;
        border-radius: 8px !important;
        font-size: 0.68rem !important;
    }
    .upcoming-item-left {
        font-size: 0.68rem !important;
        gap: 6px !important;
        min-width: 0 !important;
    }

    /* Inbox & Updates Cards Responsive Fit */
    .inbox-items-list {
        width: 100% !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 6px !important;
    }
    .inbox-item-row {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        padding: 8px 10px !important;
        gap: 8px !important;
        min-width: 0 !important;
        border-radius: 12px !important;
    }
    .inbox-item-left {
        min-width: 0 !important;
        flex: 1 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        overflow: hidden !important;
    }
    .inbox-avatar-mini {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.75rem !important;
        flex-shrink: 0 !important;
    }
    .inbox-content-mini {
        min-width: 0 !important;
        flex: 1 !important;
        overflow: hidden !important;
    }
    .inbox-content-mini h5 {
        font-size: 0.75rem !important;
        margin-bottom: 2px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 100% !important;
    }
    .inbox-content-mini p {
        font-size: 0.68rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 100% !important;
    }
    .inbox-action-arrow {
        font-size: 0.75rem !important;
        padding: 2px !important;
        flex-shrink: 0 !important;
    }
}
    </style>
</head>

<body>
    <!-- Outer Dashboard Frame -->
    <div class="dashboard-container">

        <!-- Unified Minimalist Dock Sidebar -->
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <!-- Main Dashboard Canvas -->
        <main class="dashboard-main">

            <!-- Unified Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Subheader Bar (Back arrow, Title, Status Pills) -->
            <div class="dash-subheader">
                <div class="subheader-title-wrap">
                    <a href="index.php" class="btn-back-arrow" title="Return to City Portal Home">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1>Citizen Services &amp; Portal</h1>
                </div>

                <div class="subheader-status-wrap">
                    <span class="status-badge-pill <?php echo $verification_status == 'verified' ? 'verified' : 'pending'; ?>">
                        <i class="fas <?php echo $verification_status == 'verified' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                        <?php echo $verification_status == 'verified' ? 'Verified Resident' : 'Pending Verification'; ?>
                    </span>
                    <span class="status-badge-pill">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F, Y'); ?>
                    </span>
                </div>
            </div>

            <!-- Main Content 2-Column Grid (390px 1fr) -->
            <div class="dash-content-grid">

                <!-- ================= LEFT COLUMN (390px) ================= -->
                <div class="column-left">

                    <!-- Card 1A: Profile Summary Card -->
                    <section class="ref-card-profile">
                        <div class="profile-card-top">
                            <div class="profile-user-summary">
                                <div class="profile-avatar-circle">
                                    <?php if (!empty($avatar_url)): ?>
                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Resident Portrait">
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($initials); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-user-headings">
                                    <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                                    <p>Palayan Resident &bull; <?php echo $verification_status == 'verified' ? 'Verified Citizen' : 'Registered'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Resident Quick Action Circles -->
                        <div class="profile-action-circles">
                            <a href="profile.php" class="action-circle-btn" title="Profile Overview">
                                <i class="fas fa-user"></i>
                            </a>
                            <a href="update-profile-info.php" class="action-circle-btn" title="Profile Completion &amp; Demographics">
                                <i class="fas fa-user-edit"></i>
                            </a>
                            <a href="verify-resident.php" class="action-circle-btn" title="Residency Verification">
                                <i class="fas fa-id-card"></i>
                            </a>
                        </div>

                        <!-- Bottom Slots Row (Clock & Portal Status) -->
                        <div class="profile-slots-row" style="justify-content: center; flex-wrap: wrap; gap: 8px;">
                            <?php if ($verification_status === 'verified'): ?>
                            <div class="slot-pill" onclick="openWelcomeProfileModal()" style="cursor: pointer; background: rgba(5, 150, 105, 0.1); border-color: rgba(5, 150, 105, 0.25); color: #059669;" title="Click to view Step-by-Step Profile Guide">
                                <i class="fas fa-shield-check"></i>
                                <span>Verified &bull; Onboarding Guide</span>
                            </div>
                            <?php endif; ?>
                            <div class="slot-pill">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('h:i A'); ?> &bull; Active Portal</span>
                            </div>
                        </div>
                    </section>

                    <!-- Card 1B: City Announcements & Bulletins (Animated Vertical Ticker) -->
                    <section class="ref-card-details ref-card-announcements">
                        <div class="ann-card-head">
                            <div class="ann-head-left">
                                <span class="ann-head-icon-circle"><i class="fas fa-bullhorn"></i></span>
                                <h4>City Bulletins</h4>
                            </div>
                            <div class="ann-head-right">
                                <a href="announcements.php" class="btn-round-mini" title="View All Announcements" style="width: 28px; height: 28px; font-size: 0.72rem;">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Vertical Ticker Viewport Stage -->
                        <div class="ann-ticker-stage" id="annTickerStage">
                            <?php foreach ($dash_announcements as $idx => $ann): 
                                $cat = strtolower($ann['category'] ?? 'general');
                                $cat_label = 'City Notice';
                                $cat_icon = 'fa-info-circle';
                                if ($cat === 'aics') {
                                    $cat_label = 'AICS Aid';
                                    $cat_icon = 'fa-hand-holding-usd';
                                } elseif ($cat === 'housing') {
                                    $cat_label = 'Housing';
                                    $cat_icon = 'fa-home';
                                } elseif ($cat === 'appointment') {
                                    $cat_label = 'Appointment';
                                    $cat_icon = 'fa-calendar-check';
                                }
                                $ann_date = !empty($ann['created_at']) ? date('M d, Y', strtotime($ann['created_at'])) : date('M d, Y');
                                $ann_title = $ann['title'] ?? 'Official City Notice';
                                $ann_desc = trim(strip_tags($ann['content'] ?? ''));
                                if (strlen($ann_desc) < 20) {
                                    $ann_desc .= ' — Official public welfare bulletin issued by CSWDO Palayan City.';
                                }
                                $is_active = ($idx === 0) ? 'is-active' : '';

                                $has_img = (!empty($ann['image_path']) && file_exists(__DIR__ . '/' . ltrim($ann['image_path'], '/')));
                                $img_web = $has_img ? htmlspecialchars(ltrim($ann['image_path'], '/')) : '';
                            ?>
                            <a href="announcements.php" class="ann-ticker-item border-cat-<?php echo $cat; ?> <?php echo $is_active; ?>" data-ann-idx="<?php echo $idx; ?>">
                                <div>
                                    <?php if ($has_img): ?>
                                        <div class="ann-item-photo-strip" style="background-image: url('<?php echo $img_web; ?>');"></div>
                                    <?php endif; ?>

                                    <div class="ann-ticker-top-row">
                                        <span class="ann-cat-tag cat-<?php echo $cat; ?>">
                                            <i class="fas <?php echo $cat_icon; ?>"></i> <?php echo $cat_label; ?>
                                        </span>
                                        <span class="ann-date-tag">
                                            <i class="far fa-calendar-alt"></i> <?php echo $ann_date; ?>
                                        </span>
                                    </div>
                                    <h5 class="ann-ticker-title"><?php echo htmlspecialchars($ann_title); ?></h5>
                                    <p class="ann-ticker-desc"><?php echo htmlspecialchars($ann_desc); ?></p>
                                </div>
                                <div class="ann-ticker-bottom-row">
                                    <span class="ann-source-tag">
                                        <i class="fas fa-check-circle" style="color: #059669;"></i> CSWDO Official
                                    </span>
                                    <span class="ann-read-btn">
                                        View Notice <i class="fas fa-arrow-right"></i>
                                    </span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Bottom Controls (Dots & Manual Arrows) -->
                        <div class="ann-ticker-controls">
                            <div class="ann-dots-wrap" id="annDotsWrap">
                                <?php foreach ($dash_announcements as $idx => $ann): ?>
                                    <span class="ann-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-slide-target="<?php echo $idx; ?>" onclick="goToAnnouncement(<?php echo $idx; ?>)"></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="ann-arrows-wrap">
                                <span class="ann-counter-txt">
                                    <span id="annCounterNumber">01</span> / <?php echo str_pad((string)count($dash_announcements), 2, '0', STR_PAD_LEFT); ?>
                                </span>
                                <button type="button" class="ann-arrow-btn" onclick="prevAnnouncement()" title="Previous Announcement">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button type="button" class="ann-arrow-btn" onclick="nextAnnouncement()" title="Next Announcement">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- ================= RIGHT COLUMN (1fr) ================= -->
                <div class="column-right">

                    <!-- Top Section: Ongoing Programs & Services Header -->
                    <div class="ongoing-section-header">
                        <div class="ongoing-badge-pill">
                            <i class="fas fa-layer-group"></i>
                            <span>Ongoing Services</span>
                        </div>

                        <!-- Brand Title (One Palayan - Kalinga Hub) -->
                        <h2 class="dash-branding-heading dash-cursive-title">One Palayan - Kalinga Hub</h2>

                        <!-- Clean Active Programs Indicator -->
                        <div class="ongoing-active-indicator">
                            <span class="active-dot-live"></span>
                            <span>2026 Active Programs</span>
                        </div>
                    </div>

                    <!-- 3 Colored Cards Grid -->
                    <div class="colored-cards-grid">

                        <!-- Card 1: Pastel Yellow (Housing Programs) -->
                        <article class="ref-service-card card-yellow service-card-minimal" onclick="window.location.href='housing-programs.php'">
                            <i class="fas fa-home service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-yellow"><i class="fas fa-building"></i> Shelter &amp; Lots</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">Housing Programs</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Explore Housing</span>
                                <a href="housing-programs.php" class="service-card-arrow-btn" aria-label="Go to Housing Programs">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                        <!-- Card 2: Pastel Soft Blue (AICS Assistance) -->
                        <article class="ref-service-card card-blue service-card-minimal" onclick="window.location.href='aics.php'">
                            <i class="fas fa-hand-holding-heart service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-blue"><i class="fas fa-hand-holding-usd"></i> Financial &amp; Crisis</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">AICS Assistance</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Apply Assistance</span>
                                <a href="aics.php" class="service-card-arrow-btn" aria-label="Go to AICS Assistance">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                        <!-- Card 3: Pastel Soft Pink (Appointment Desk) -->
                        <article class="ref-service-card card-pink service-card-minimal" onclick="window.location.href='appointments.php'">
                            <i class="fas fa-calendar-check service-card-giant-watermark"></i>
                            <div class="service-card-top-tag">
                                <span class="service-category-pill pill-pink"><i class="fas fa-clock"></i> Consultations</span>
                            </div>
                            <div class="service-card-center">
                                <h4 class="service-card-title">Appointment Desk</h4>
                            </div>
                            <div class="service-card-bottom-minimal">
                                <span class="service-card-action-text">Book Appointment</span>
                                <a href="appointments.php" class="service-card-arrow-btn" aria-label="Go to Appointment Desk">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>

                    </div>

                    <!-- Bottom Split Section (Calendar Schedule + Recent Requests Activity) -->
                    <div class="bottom-split-grid">

                        <!-- Left Card: Interactive Calendar -->
                        <section class="ref-card-calendar">
                            <?php
                            $c_disp_m = $resident_cal['month'] ?? (int)date('n');
                            $c_disp_y = $resident_cal['year'] ?? (int)date('Y');
                            $c_disp_name = $resident_cal['month_name'] ?? date('F');
                            $prev_m = ($c_disp_m === 1) ? 12 : ($c_disp_m - 1);
                            $prev_y = ($c_disp_m === 1) ? ($c_disp_y - 1) : $c_disp_y;
                            $next_m = ($c_disp_m === 12) ? 1 : ($c_disp_m + 1);
                            $next_y = ($c_disp_m === 12) ? ($c_disp_y + 1) : $c_disp_y;
                            ?>
                            <div class="calendar-header">
                                <h4><i class="fas fa-calendar-alt"></i> Calendar</h4>
                                <div class="calendar-month-selector">
                                    <a href="?cal_month=<?php echo $prev_m; ?>&cal_year=<?php echo $prev_y; ?>" class="btn-cal-nav" title="Previous Month"><i class="fas fa-chevron-left"></i></a>
                                    <span><?php echo htmlspecialchars($c_disp_name . ' ' . $c_disp_y); ?></span>
                                    <a href="?cal_month=<?php echo $next_m; ?>&cal_year=<?php echo $next_y; ?>" class="btn-cal-nav" title="Next Month"><i class="fas fa-chevron-right"></i></a>
                                </div>
                            </div>

                            <!-- Mini Calendar Grid -->
                            <div class="mini-calendar-grid">
                                <div class="cal-day-header">M</div>
                                <div class="cal-day-header">T</div>
                                <div class="cal-day-header">W</div>
                                <div class="cal-day-header">T</div>
                                <div class="cal-day-header">F</div>
                                <div class="cal-day-header">S</div>
                                <div class="cal-day-header">S</div>

                                <?php
                                $first_dow = $resident_cal['first_dow'] ?? 1; // 1 = Monday, 7 = Sunday
                                $days_in_m = $resident_cal['days_in_month'] ?? (int)date('t');
                                $today_str = date('Y-m-d');
                                $activities = $resident_cal['activities'] ?? [];

                                // Preceding empty cells
                                for ($empty = 1; $empty < $first_dow; $empty++):
                                ?>
                                    <div class="cal-day-cell empty"></div>
                                <?php endfor; ?>

                                <?php
                                for ($d = 1; $d <= $days_in_m; $d++):
                                    $d_str = sprintf('%04d-%02d-%02d', $c_disp_y, $c_disp_m, $d);
                                    $is_today = ($d_str === $today_str);
                                    $day_data = $activities[$d_str] ?? null;

                                    $has_yellow = !empty($day_data['has_yellow']);
                                    $has_red = !empty($day_data['has_red']);
                                    $has_blackout = !empty($day_data['has_blackout']);
                                    $has_events = ($has_yellow || $has_red || $has_blackout);

                                    $cell_classes = ['cal-day-cell'];
                                    if ($is_today) $cell_classes[] = 'is-today';
                                    if ($has_blackout) $cell_classes[] = 'is-blackout';
                                    if ($has_yellow && $has_red) {
                                        $cell_classes[] = 'has-event-dual';
                                    } elseif ($has_yellow) {
                                        $cell_classes[] = 'has-event-yellow';
                                    } elseif ($has_red) {
                                        $cell_classes[] = 'has-event-red';
                                    }
                                ?>
                                    <div class="<?php echo implode(' ', $cell_classes); ?>" tabindex="0" <?php echo $has_blackout ? 'title="Blackout Date / Walang Pasok"' : ''; ?>>
                                        <?php echo $d; ?>
                                        <?php if ($has_events && !empty($day_data)): ?>
                                            <div class="cal-hover-popover">
                                                <div class="popover-date-head">
                                                    <i class="fas fa-calendar-day"></i> <?php echo date('D, M j, Y', strtotime($d_str)); ?>
                                                </div>
                                                <?php if (!empty($day_data['personal'])): ?>
                                                    <?php foreach ($day_data['personal'] as $p): ?>
                                                        <div class="popover-item">
                                                            <span class="popover-tag tag-yellow"><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($p['time']); ?></span>
                                                            <div class="popover-title"><?php echo htmlspecialchars($p['title']); ?></div>
                                                            <div class="popover-desc"><?php echo htmlspecialchars($p['ref']); ?> &bull; <strong><?php echo ucfirst(htmlspecialchars($p['status'])); ?></strong></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php if (!empty($day_data['announcements'])): ?>
                                                    <?php foreach ($day_data['announcements'] as $a): ?>
                                                        <div class="popover-item">
                                                            <span class="popover-tag tag-red"><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($a['category'] ?? 'Announcement'); ?></span>
                                                            <div class="popover-title"><?php echo htmlspecialchars($a['title']); ?></div>
                                                            <?php if (!empty($a['details'])): ?>
                                                                <div class="popover-desc"><?php echo htmlspecialchars($a['details']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Color Indicator Legend -->
                            <div class="calendar-legend">
                                <span class="legend-pill"><span class="legend-dot dot-today" style="border: 2px solid #7F0303; background: #FFF; width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span> Today (Maroon Border)</span>
                                <span class="legend-pill"><span class="legend-dot dot-yellow"></span> Appointments / Applied</span>
                                <span class="legend-pill"><span class="legend-dot dot-red"></span> Announcements / Events</span>
                                <span class="legend-pill"><span class="legend-dot" style="background: #475569; width: 8px; height: 8px; border-radius: 50%; display: inline-block;"></span> Walang Pasok (Blackout)</span>
                            </div>

                            <div class="calendar-upcoming-list">
                                <div style="font-size: 0.72rem; font-weight: 800; color: var(--ref-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                                    Upcoming Booked Visits
                                </div>
                                <?php
                                $app_stmt->execute();
                                $counter = 0;
                                if ($app_stmt->rowCount() > 0):
                                    while ($app = $app_stmt->fetch(PDO::FETCH_ASSOC)):
                                        if ($counter >= 2) break;
                                ?>
                                    <div class="upcoming-item">
                                        <div class="upcoming-item-left">
                                            <i class="fas fa-calendar-check" style="color: #059669;"></i>
                                            <span><?php echo htmlspecialchars($app['service_name']); ?></span>
                                        </div>
                                        <span class="upcoming-badge-time">
                                            <?php echo date('M d', strtotime($app['appointment_date'])); ?> &bull; <?php echo date('h:i A', strtotime($app['appointment_time'])); ?>
                                        </span>
                                    </div>
                                <?php
                                        $counter++;
                                    endwhile;
                                else:
                                ?>
                                    <div class="upcoming-item" style="color: var(--ref-muted); font-size: 0.74rem;">
                                        <span>No booked visits yet. <a href="appointments.php" style="color: #7F0303; font-weight: 700;">Book now</a></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Right Card: Inbox & Notifications -->
                        <section class="ref-card-inbox">
                            <div class="inbox-header">
                                <h4><i class="fas fa-inbox"></i> Inbox &amp; Updates</h4>
                                <a href="notifications.php" class="btn-round-mini" title="All Notifications"><i class="fas fa-ellipsis-h"></i></a>
                            </div>

                            <div class="inbox-items-list">
                                <?php
                                $notif_stmt->execute();
                                $has_notifs = false;
                                if ($notif_stmt->rowCount() > 0):
                                    $has_notifs = true;
                                    $n_idx = 0;
                                    while ($notif = $notif_stmt->fetch(PDO::FETCH_ASSOC)):
                                        if ($n_idx >= 3) break;
                                ?>
                                    <div class="inbox-item-row <?php echo $n_idx === 1 ? 'highlighted' : ''; ?>">
                                        <div class="inbox-item-left">
                                            <div class="inbox-avatar-mini">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                            <div class="inbox-content-mini">
                                                <h5><?php echo htmlspecialchars($notif['title']); ?></h5>
                                                <p><?php echo htmlspecialchars(substr($notif['message'], 0, 48)); ?>...</p>
                                            </div>
                                        </div>
                                        <a href="notifications.php" class="inbox-action-arrow">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                <?php
                                        $n_idx++;
                                    endwhile;
                                endif;

                                if (!$has_notifs):
                                ?>
                                    <div class="inbox-item-row">
                                        <div class="inbox-item-left">
                                            <div class="inbox-avatar-mini"><i class="fas fa-check"></i></div>
                                            <div class="inbox-content-mini">
                                                <h5>All Caught Up</h5>
                                                <p>No new notifications at this time.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                    </div>

                </div>

            </div>

        </main>

    </div>

    <!-- Integrated Search Modal Overlay -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>

    <!-- Verified Resident Welcome & Profile Completion Guide Modal -->
    <?php if ($verification_status === 'verified' && !isStaff() && !isAdmin()): ?>
        <?php include __DIR__ . '/includes/welcome-profile-modal.php'; ?>
    <?php endif; ?>

    <!-- Animated Vertical Announcements Ticker Controller -->
    <script>
    (function() {
        const items = document.querySelectorAll('.ann-ticker-item');
        const dots = document.querySelectorAll('.ann-dot');
        const counterEl = document.getElementById('annCounterNumber');
        const stage = document.getElementById('annTickerStage');
        const total = items.length;

        if (total <= 0) return;

        let currentIdx = 0;
        let isTransitioning = false;
        let tickerInterval = null;

        function goTo(targetIdx) {
            if (isTransitioning || targetIdx === currentIdx || targetIdx < 0 || targetIdx >= total) return;
            isTransitioning = true;

            const currentItem = items[currentIdx];
            const nextItem = items[targetIdx];

            // 1. Current item vanishes fading going UP
            currentItem.classList.remove('is-active');
            currentItem.classList.add('is-leaving');

            // 2. Next item prepared from below
            nextItem.classList.remove('is-leaving');
            nextItem.classList.add('is-entering');
            void nextItem.offsetHeight; // trigger reflow

            // 3. Next item smoothly scrolls up and stops at the resting place
            nextItem.classList.remove('is-entering');
            nextItem.classList.add('is-active');

            // 4. Update dots & counter
            if (dots[currentIdx]) dots[currentIdx].classList.remove('active');
            if (dots[targetIdx]) dots[targetIdx].classList.add('active');
            if (counterEl) counterEl.textContent = String(targetIdx + 1).padStart(2, '0');

            currentIdx = targetIdx;

            setTimeout(() => {
                currentItem.classList.remove('is-leaving');
                isTransitioning = false;
            }, 820);
        }

        window.nextAnnouncement = function() {
            const next = (currentIdx + 1) % total;
            goTo(next);
        };

        window.prevAnnouncement = function() {
            const prev = (currentIdx - 1 + total) % total;
            goTo(prev);
        };

        window.goToAnnouncement = function(idx) {
            goTo(idx);
        };

        function startTimer() {
            if (total <= 1) return;
            clearInterval(tickerInterval);
            tickerInterval = setInterval(window.nextAnnouncement, 2800);
        }

        function stopTimer() {
            clearInterval(tickerInterval);
        }

        if (stage) {
            stage.addEventListener('mouseenter', stopTimer);
            stage.addEventListener('mouseleave', startTimer);
        }

        startTimer();
    })();
    </script>

</body>
</html>

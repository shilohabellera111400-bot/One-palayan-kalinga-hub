<?php
require_once __DIR__ . '/config/database.php';

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

$tracking_number = isset($_GET['tracking']) ? htmlspecialchars(trim($_GET['tracking'])) : '';
$request = null;
$error = '';

if ($tracking_number) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!preg_match('/^AICS-\d{6}$/', $tracking_number)) {
        $error = "Invalid tracking number format. Please use format: AICS-000001";
    } else {
        $id = intval(str_replace('AICS-', '', $tracking_number));
        
        $query = "SELECT a.aics_id, a.user_id, a.request_type, a.amount_requested, a.amount_approved, 
                         a.status, a.submission_date, a.review_date, a.approval_date, a.release_date, 
                         a.rejection_reason, a.purpose,
                         u.first_name, u.last_name 
                  FROM aics_requests a
                  JOIN users u ON a.user_id = u.user_id
                  WHERE a.aics_id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "No request found with tracking number: " . htmlspecialchars($tracking_number);
        }
    }
}

// Get recent stats (last 30 days)
$database = new Database();
$db = $database->getConnection();
$stats_query = "SELECT 
    COUNT(*) as total_tracked,
    COUNT(DISTINCT user_id) as unique_users
    FROM aics_requests 
    WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Timeline Stepper Progress calculation
$status_timeline = 0;
if ($request) {
    if ($request['status'] == 'submitted') {
        $status_timeline = 0;
    } elseif ($request['status'] == 'reviewing') {
        $status_timeline = 33.33;
    } elseif ($request['status'] == 'approved' || $request['status'] == 'rejected') {
        $status_timeline = 66.66;
    } elseif ($request['status'] == 'released') {
        $status_timeline = 100;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track AICS Request - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== CORE VARIABLE OVERRIDES AND THEMING ========== */
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
            
            --bg-primary: #EFF1F4;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #D5DBE1;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 30px rgba(15, 65, 74, 0.09), 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 16px 40px rgba(15, 65, 74, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --radius-card: 24px;
            --radius-element: 16px;
            
            --color-success: #2e7d32;
            --color-warning: #ed6c02;
            --color-danger: #d32f2f;
            --color-info: #0288d1;
            
            --stepper-track-bg: #E4E7E9;
            --stepper-track-active: #2e7d32;
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
            
            --stepper-track-bg: #2d3b4e;
            --stepper-track-active: #4caf50;
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

        /* ========== HERO & HEADER ========== */
        .tracking-page {
            padding: 130px 0 80px;
            min-height: 85vh;
        }

        .tracking-header {
            text-align: center;
            margin-bottom: 45px;
            animation: fadeInDown 0.6s ease forwards;
        }

        .tracking-header h1 {
            color: var(--text-primary);
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--text-primary) 30%, var(--maroon) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        [data-theme="dark"] .tracking-header h1 {
            background: linear-gradient(135deg, var(--text-primary) 30%, var(--gold-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .tracking-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ========== STATS CARDS ========== */
        .stats-row {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.1s;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 24px 30px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            min-width: 220px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .stat-icon {
            background: rgba(232, 213, 168, 0.1);
            color: var(--gold-light);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.08) rotate(-5deg);
        }

        .stat-details {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== SEARCH CARD ========== */
        .search-container {
            max-width: 720px;
            margin: 0 auto 40px;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.2s;
        }

        .search-card {
            background: var(--bg-card);
            padding: 35px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .search-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--maroon), var(--gold));
        }

        .search-box-wrapper {
            display: flex;
            gap: 15px;
            position: relative;
        }

        .search-input-group {
            position: relative;
            flex: 1;
        }

        .search-input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        .search-input-group input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-primary);
            background: var(--bg-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input-group input:focus {
            outline: none;
            border-color: var(--maroon-light);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.08);
        }

        .search-input-group input:focus + i {
            color: var(--maroon-light);
        }

        .btn-track {
            padding: 16px 36px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: var(--radius-element);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.2);
            white-space: nowrap;
        }

        .btn-track:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.35);
        }

        .btn-track:active {
            transform: translateY(0);
        }

        .search-hint {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 18px 24px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow-sm);
            animation: fadeInScale 0.4s ease forwards;
        }

        .alert-error {
            background: rgba(211, 47, 47, 0.08);
            color: var(--color-danger);
            border: 1px solid rgba(211, 47, 47, 0.15);
        }

        .alert-error i {
            font-size: 1.3rem;
        }

        /* ========== RESULTS AREA ========== */
        .result-container {
            margin-top: 30px;
            animation: fadeInUp 0.6s ease forwards;
        }

        .tracking-result {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 20px;
        }

        .result-header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .result-header-title i {
            font-size: 1.8rem;
            color: var(--maroon);
        }

        [data-theme="dark"] .result-header-title i {
            color: var(--gold-light);
        }

        .result-header-title h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .result-badge {
            background: var(--bg-primary);
            padding: 10px 24px;
            border-radius: 50px;
            font-family: 'Inter', monospace;
            font-size: 1.05rem;
            color: var(--text-primary);
            font-weight: 700;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ========== STEPPER TIMELINE ========== */
        .stepper-timeline-wrapper {
            position: relative;
            padding: 20px 0;
            margin: 50px 0;
        }

        .stepper-timeline-progress {
            position: absolute;
            top: 38px;
            left: 5%;
            right: 5%;
            height: 5px;
            background: var(--stepper-track-bg);
            border-radius: 10px;
            z-index: 1;
        }

        .stepper-timeline-bar {
            height: 100%;
            background: var(--stepper-track-active);
            width: 0%;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(46, 125, 50, 0.4);
        }

        [data-theme="dark"] .stepper-timeline-bar {
            box-shadow: 0 0 12px rgba(76, 175, 80, 0.4);
        }

        .stepper-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 2;
            list-style: none;
        }

        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-icon-container {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 4px solid var(--stepper-track-bg);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Completed Step Styles */
        .step-item.completed .step-icon-container {
            background: var(--color-success);
            border-color: var(--color-success);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(46, 125, 50, 0.3);
        }

        /* Active Step Styles */
        .step-item.active .step-icon-container {
            background: var(--color-warning);
            border-color: var(--color-warning);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 8px 24px rgba(237, 108, 2, 0.35);
            animation: pulse-orange 2s infinite;
        }

        /* Rejected/Danger Active Step Styles */
        .step-item.active.rejected-step .step-icon-container {
            background: var(--color-danger);
            border-color: var(--color-danger);
            color: white;
            box-shadow: 0 8px 24px rgba(211, 47, 47, 0.35);
            animation: pulse-red 2s infinite;
        }

        .step-content h4 {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .step-content p {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
        }

        @keyframes pulse-orange {
            0% { box-shadow: 0 0 0 0 rgba(237, 108, 2, 0.5); }
            70% { box-shadow: 0 0 0 12px rgba(237, 108, 2, 0); }
            100% { box-shadow: 0 0 0 0 rgba(237, 108, 2, 0); }
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(211, 47, 47, 0.5); }
            70% { box-shadow: 0 0 0 12px rgba(211, 47, 47, 0); }
            100% { box-shadow: 0 0 0 0 rgba(211, 47, 47, 0); }
        }

        /* ========== DETAILS GRID ========== */
        .details-section {
            margin-top: 50px;
            background: var(--alabaster);
            border-radius: var(--radius-element);
            padding: 30px;
            border: 1px solid var(--border-color);
        }

        .details-section h3 {
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px 30px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 500;
        }

        .detail-value strong {
            font-weight: 700;
        }

        .detail-value .amount-approved {
            color: var(--color-success);
            font-weight: 800;
            font-size: 1.2rem;
        }

        /* Badge design */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .badge-submitted { background: rgba(255, 193, 7, 0.15); color: #b78103; }
        .badge-reviewing { background: rgba(33, 150, 243, 0.15); color: #0b69a3; }
        .badge-approved { background: rgba(76, 175, 80, 0.15); color: #2e7d32; }
        .badge-released { background: rgba(0, 150, 136, 0.15); color: #00796b; }
        .badge-rejected { background: rgba(244, 67, 54, 0.15); color: #c62828; }

        .rejection-reason-box {
            grid-column: span 2;
            background: rgba(244, 67, 54, 0.08);
            border-left: 4px solid var(--color-danger);
            padding: 16px 20px;
            border-radius: 8px;
            color: var(--color-danger);
            font-weight: 500;
            margin-top: 10px;
        }

        .rejection-reason-box i {
            margin-right: 8px;
        }

        .btn-view-full {
            margin-top: 30px;
            text-align: right;
        }

        .btn-outline-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border: 2px solid var(--maroon);
            color: var(--maroon);
            background: transparent;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .btn-outline-action {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        .btn-outline-action:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 3, 3, 0.2);
        }

        [data-theme="dark"] .btn-outline-action:hover {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 15px rgba(232, 213, 168, 0.2);
        }

        /* ========== GUIDES ========== */
        .info-section {
            margin-top: 70px;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.3s;
        }

        .info-section h3 {
            text-align: center;
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .step-card {
            background: var(--bg-card);
            padding: 30px;
            border-radius: var(--radius-card);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .step-number {
            width: 44px;
            height: 44px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.25);
        }

        [data-theme="dark"] .step-number {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 10px rgba(232, 213, 168, 0.25);
        }

        .step-card h4 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .step-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ========== ASSISTANCE HELP ========== */
        .need-help {
            text-align: center;
            margin-top: 50px;
            padding: 40px;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .need-help i.headset-icon {
            font-size: 2.5rem;
            color: var(--maroon);
            margin-bottom: 15px;
            display: block;
        }

        [data-theme="dark"] .need-help i.headset-icon {
            color: var(--gold-light);
        }

        .need-help h4 {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .need-help p {
            color: var(--text-muted);
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .contact-info-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            flex-wrap: wrap;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .contact-info-row span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-info-row i {
            color: var(--maroon);
        }

        [data-theme="dark"] .contact-info-row i {
            color: var(--gold-light);
        }

        .btn-login-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 30px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.2);
        }

        .btn-login-cta:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.35);
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* ========== RESPONSIVE OVERRIDES ========== */
        @media (max-width: 992px) {
            .tracking-page { padding: 110px 0 60px; }
            .tracking-header h1 { font-size: 2.2rem; }
            .stats-row { flex-direction: column; align-items: center; gap: 15px; }
            .stat-card { width: 100%; max-width: 400px; }
            
            .stepper-timeline-progress { display: none; }
            .stepper-timeline { flex-direction: column; gap: 30px; align-items: flex-start; padding-left: 20px; }
            .step-item { display: flex; text-align: left; gap: 20px; width: 100%; }
            .step-icon-container { margin: 0; flex-shrink: 0; width: 64px; height: 64px; font-size: 1.3rem; }
            .step-content { display: flex; flex-direction: column; justify-content: center; }
            
            .details-grid { grid-template-columns: 1fr; }
            .rejection-reason-box { grid-column: span 1; }
            .steps-grid { grid-template-columns: 1fr; gap: 20px; }
        }

        @media (max-width: 600px) {
            .search-box-wrapper { flex-direction: column; }
            .btn-track { width: 100%; justify-content: center; }
            .tracking-result { padding: 25px; }
            .result-header { flex-direction: column; align-items: flex-start; }
            .badge-status { margin-top: 5px; }
        }
    </style>
</head>
<body>
    <!-- Navbar Include -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="tracking-page">
        <div class="container">
            <!-- Header section -->
            <div class="tracking-header">
                <h1>Track Your AICS Request</h1>
                <p>Enter your tracking number below to verify the real-time processing status of your crisis intervention request.</p>
            </div>

            <!-- Stats section -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($stats['total_tracked'] ?? 0); ?></div>
                        <div class="stat-label">Requests This Month</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
                        <div class="stat-label">Beneficiaries Assisted</div>
                    </div>
                </div>
            </div>
            
            <!-- Search bar card -->
            <div class="search-container">
                <div class="search-card">
                    <form method="GET" action="" id="trackingForm">
                        <div class="search-box-wrapper">
                            <div class="search-input-group">
                                <input type="text" name="tracking" id="trackingInput" placeholder="Enter Tracking Number (e.g., AICS-000001)" value="<?php echo htmlspecialchars($tracking_number); ?>" pattern="AICS-\d{6}" title="Please enter in format: AICS-000001" required autocomplete="off">
                                <i class="fas fa-search"></i>
                            </div>
                            <button type="submit" class="btn-track"><i class="fas fa-magnifying-glass"></i> Track Request</button>
                        </div>
                        <span class="search-hint">Format: AICS-000001 (AICS followed by 6 digits)</span>
                    </form>
                </div>
            </div>
            
            <!-- Error feedback -->
            <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <div>
                    <strong>Unable to Track:</strong> <?php echo $error; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Results Block -->
            <?php if($tracking_number && $request): ?>
            <div class="result-container" id="resultContainer">
                <div class="tracking-result">
                    <div class="result-header">
                        <div class="result-header-title">
                            <i class="fas fa-circle-nodes"></i>
                            <h2>Assistance Request Progress</h2>
                        </div>
                        <span class="result-badge">
                            <i class="fas fa-hashtag"></i>
                            AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?>
                        </span>
                    </div>
                    
                    <!-- Stepper Timeline -->
                    <div class="stepper-timeline-wrapper">
                        <div class="stepper-timeline-progress">
                            <div class="stepper-timeline-bar" style="width: <?php echo $status_timeline; ?>%;"></div>
                        </div>
                        <ul class="stepper-timeline">
                            <!-- Step 1: Submitted -->
                            <li class="step-item <?php echo in_array($request['status'], ['submitted', 'reviewing', 'approved', 'released', 'rejected']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'submitted' ? 'active' : ''; ?>">
                                <div class="step-icon-container">
                                    <i class="fas fa-file-arrow-up"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Submitted</h4>
                                    <p><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></p>
                                </div>
                            </li>
                            <!-- Step 2: Under Review -->
                            <li class="step-item <?php echo in_array($request['status'], ['reviewing', 'approved', 'released', 'rejected']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'reviewing' ? 'active' : ''; ?>">
                                <div class="step-icon-container">
                                    <i class="fas fa-magnifying-glass-chart"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Under Review</h4>
                                    <p><?php echo $request['review_date'] ? date('M d, Y', strtotime($request['review_date'])) : 'Pending Review'; ?></p>
                                </div>
                            </li>
                            
                            <!-- Step 3: Approval / Rejection Decision -->
                            <?php if ($request['status'] == 'rejected'): ?>
                            <li class="step-item active rejected-step">
                                <div class="step-icon-container">
                                    <i class="fas fa-circle-xmark"></i>
                                </div>
                                <div class="step-content">
                                    <h4 style="color: var(--color-danger);">Rejected</h4>
                                    <p><?php echo $request['review_date'] ? date('M d, Y', strtotime($request['review_date'])) : 'Rejected'; ?></p>
                                </div>
                            </li>
                            <?php else: ?>
                            <li class="step-item <?php echo in_array($request['status'], ['approved', 'released']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'approved' ? 'active' : ''; ?>">
                                <div class="step-icon-container">
                                    <i class="fas fa-file-shield"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Approved</h4>
                                    <p><?php echo $request['approval_date'] ? date('M d, Y', strtotime($request['approval_date'])) : 'Pending Approval'; ?></p>
                                </div>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Step 4: Released -->
                            <li class="step-item <?php echo $request['status'] == 'released' ? 'completed active' : ''; ?>">
                                <div class="step-icon-container">
                                    <i class="fas fa-hand-holding-dollar"></i>
                                </div>
                                <div class="step-content">
                                    <h4>Released</h4>
                                    <p><?php echo $request['release_date'] ? date('M d, Y', strtotime($request['release_date'])) : 'Pending Release'; ?></p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Request Details Section -->
                    <div class="details-section">
                        <h3><i class="fas fa-circle-info"></i> Request Details</h3>
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Applicant Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Assistance Type</span>
                                <span class="detail-value"><?php echo ucfirst($request['request_type'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Amount Requested</span>
                                <span class="detail-value"><strong>₱<?php echo number_format($request['amount_requested'] ?? 0, 2); ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Approved Benefit</span>
                                <span class="detail-value">
                                    <?php if(isset($request['amount_approved']) && $request['amount_approved'] > 0): ?>
                                        <span class="amount-approved">₱<?php echo number_format($request['amount_approved'], 2); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Not yet approved / ---</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item" style="grid-column: span 2;">
                                <span class="detail-label">Purpose</span>
                                <span class="detail-value"><?php echo nl2br(htmlspecialchars($request['purpose'] ?? 'Not specified')); ?></span>
                            </div>
                            <div class="detail-item" style="grid-column: span 2; border-bottom: none; padding-bottom: 0;">
                                <span class="detail-label">Status</span>
                                <div>
                                    <span class="badge-status badge-<?php echo $request['status']; ?>">
                                        <i class="fas fa-<?php echo $request['status'] == 'submitted' ? 'clock' : ($request['status'] == 'reviewing' ? 'magnifying-glass' : ($request['status'] == 'approved' ? 'check-circle' : ($request['status'] == 'released' ? 'hand-holding-dollar' : 'circle-xmark'))); ?>"></i>
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if(isset($request['rejection_reason']) && $request['rejection_reason'] && $request['status'] == 'rejected'): ?>
                            <div class="rejection-reason-box">
                                <i class="fas fa-triangle-exclamation"></i>
                                <strong>Reason for Rejection:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Direct View details logic if logged in -->
                    <?php if(function_exists('isLoggedIn') && isLoggedIn() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $request['user_id']): ?>
                    <div class="btn-view-full">
                        <a href="view-aics.php?id=<?php echo $request['aics_id']; ?>" class="btn-outline-action">
                            <i class="fas fa-eye"></i> View Full Application Details
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Steppers Guide Section -->
            <div class="info-section">
                <h3><i class="fas fa-circle-question"></i> How to Track Your Request</h3>
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Get Tracking Code</h4>
                        <p>Retrieve the unique AICS tracking number sent via your SMS/email notification or dashboard.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Enter in Search Bar</h4>
                        <p>Input the code (e.g. AICS-000001) in the tracking search box above and submit request.</p>
                    </div>
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Monitor Status</h4>
                        <p>View real-time progress steps showing application logs and CSWDO approval approvals.</p>
                    </div>
                </div>
            </div>

            <!-- Support / Assistance Widget -->
            <div class="need-help">
                <i class="fas fa-headset headset-icon"></i>
                <h4>Need Assistance?</h4>
                <p>If you've lost your tracking code or encounter issues with tracking, please get in touch with our support desk.</p>
                <div class="contact-info-row">
                    <span><i class="fas fa-phone"></i> (044) 123-4567</span>
                    <span><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</span>
                </div>
                <?php if(!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                <a href="login.php" class="btn-login-cta"><i class="fas fa-right-to-bracket"></i> Login to View Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer Include -->
    <?php include 'includes/footer.php'; ?>

    <script>
    // Live input helper - dynamically formats input as AICS-XXXXXX
    document.getElementById('trackingInput')?.addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase();
        value = value.replace(/[^AICS0-9]/g, '');
        if (value.startsWith('AICS') && value.length > 4) {
            value = 'AICS-' + value.substring(4);
        }
        e.target.value = value;
    });

    // Form validation check
    document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
        const val = document.getElementById('trackingInput').value;
        if (!/^AICS-\d{6}$/.test(val)) {
            e.preventDefault();
            alert('Please enter a valid tracking number in the format AICS-000001');
        }
    });

    // Smooth scroll to results
    <?php if($request): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('resultContainer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    <?php endif; ?>
    </script>
</body>
</html>
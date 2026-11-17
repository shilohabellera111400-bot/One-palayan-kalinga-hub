<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/cms-helper.php';

// Group database AICS requirements by request_type
$db_aics_all = cms_get_aics_requirements();
$aics_grouped_reqs = [];
foreach ($db_aics_all as $r_item) {
    $aics_grouped_reqs[$r_item['request_type']][] = $r_item;
}

$is_resident_portal = (isLoggedIn() && !isStaff() && !isAdmin());

$initials = '';
$avatar_url = '';
if (isLoggedIn()) {
    $name_parts = explode(' ', $_SESSION['full_name'] ?? 'User');
    foreach ($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
    
    if (class_exists('Database')) {
        try {
            $db = (new Database())->getConnection();
            $u_stmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = :uid LIMIT 1");
            $u_stmt->execute([':uid' => $_SESSION['user_id']]);
            $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
            if ($u_row && !empty($u_row['profile_picture']) && file_exists($u_row['profile_picture'])) {
                $avatar_url = $u_row['profile_picture'];
            }
        } catch (Exception $e) {}
    }
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AICS Requirements & Citizen's Charter - CSWDO Palayan City</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if ($is_resident_portal): ?>
        <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <?php else: ?>
        <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php endif; ?>

    <style>
        /* ========== GLOBAL THEME TOKENS & RESET ========== */
        :root {
            --cswdo-maroon: #7F0303;
            --cswdo-maroon-dark: #5A0202;
            --cswdo-maroon-light: #9A1515;
            --cswdo-gold: #C9A86C;
            --cswdo-gold-light: #E8D5A8;
            --cswdo-gold-glow: #F3BA38;
            
            --canvas-bg: var(--ref-canvas, #E4EFE7);
            --card-surface: var(--ref-card, #FFFFFF);
            --text-heading: var(--ref-text, #191D1E);
            --text-body: #374151;
            --text-muted: #6B7280;
            --border-subtle: rgba(0, 0, 0, 0.08);
            --border-highlight: rgba(127, 3, 3, 0.15);
            
            --shadow-subtle: 0 4px 20px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 10px 30px rgba(0, 0, 0, 0.06), 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-hover: 0 16px 40px rgba(127, 3, 3, 0.12);
            
            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-pill: 9999px;
            
            --accent-green: #10B981;
            --accent-green-bg: #ECFDF5;
            --accent-blue: #0284C7;
            --accent-blue-bg: #E0F2FE;
            --accent-amber: #D97706;
            --accent-amber-bg: #FEF3C7;
            --accent-purple: #7C3AED;
            --accent-purple-bg: #F5F3FF;
        }

        [data-theme="dark"] {
            --canvas-bg: #0C1417;
            --card-surface: #152227;
            --text-heading: #F0F6FC;
            --text-body: #CBD5E1;
            --text-muted: #8B949E;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(255, 138, 128, 0.2);
            
            --shadow-subtle: 0 4px 20px rgba(0, 0, 0, 0.35);
            --shadow-card: 0 12px 36px rgba(0, 0, 0, 0.5);
            --shadow-hover: 0 16px 48px rgba(0, 0, 0, 0.65);
            
            --accent-green-bg: rgba(16, 185, 129, 0.15);
            --accent-blue-bg: rgba(2, 132, 199, 0.15);
            --accent-amber-bg: rgba(217, 119, 6, 0.15);
            --accent-purple-bg: rgba(124, 58, 237, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--canvas-bg) !important;
            color: var(--text-body);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        <?php if ($is_resident_portal): ?>
        body {
            padding: 16px 20px 48px !important;
        }
        <?php else: ?>
        .public-wrapper {
            max-width: 1240px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }
        <?php endif; ?>

        /* ========== HERO HEADER ========== */
        .aics-req-hero {
            position: relative;
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 50%, #5A0202 100%);
            border-radius: var(--radius-xl);
            padding: 36px 40px;
            color: #FFFFFF;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(127, 3, 3, 0.28);
            border: 1px solid rgba(255, 255, 255, 0.14);
            margin-bottom: 28px;
        }

        .aics-req-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(201, 168, 108, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .aics-req-hero::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 20%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-top-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: var(--radius-pill);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #FFF;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero-badge.gold {
            background: rgba(201, 168, 108, 0.28);
            color: #FFE599;
            border-color: rgba(201, 168, 108, 0.45);
        }

        .hero-title-group h1 {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.25;
            margin: 0 0 8px;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }

        .hero-title-group p {
            font-size: 0.98rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            max-width: 780px;
            line-height: 1.5;
        }

        /* Hero Metrics Strip */
        .hero-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .metric-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0, 0, 0, 0.18);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .metric-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            color: var(--cswdo-gold-glow);
            flex-shrink: 0;
        }

        .metric-info .metric-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 600;
        }

        .metric-info .metric-val {
            font-size: 0.95rem;
            font-weight: 800;
            color: #FFFFFF;
        }

        /* Top Action Buttons */
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: var(--radius-pill);
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
        }

        .btn-hero-primary {
            background: #FFFFFF;
            color: var(--cswdo-maroon) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        }

        .btn-hero-primary:hover {
            background: #FFF8E7;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
        }

        .btn-hero-glass {
            background: rgba(255, 255, 255, 0.15);
            color: #FFFFFF !important;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(6px);
        }

        .btn-hero-glass:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* ========== MANDATE & QUICK ADVISORY CARDS ========== */
        .advisory-row {
            display: grid;
            grid-template-columns: 2fr 1.2fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .advisory-card {
            background: var(--card-surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-subtle);
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }

        .advisory-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.12), rgba(201, 168, 108, 0.2));
            color: var(--cswdo-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        [data-theme="dark"] .advisory-icon-wrap {
            background: rgba(255, 138, 128, 0.15);
            color: #FF8A80;
        }

        .advisory-body h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 6px;
        }

        .advisory-body p {
            font-size: 0.88rem;
            line-height: 1.55;
            color: var(--text-body);
            margin: 0 0 10px;
        }

        .window-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .window-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            background: rgba(0, 0, 0, 0.04);
            color: var(--text-heading);
            border: 1px solid var(--border-subtle);
        }

        [data-theme="dark"] .window-pill {
            background: rgba(255, 255, 255, 0.05);
        }

        .window-pill i {
            color: var(--cswdo-maroon);
        }

        [data-theme="dark"] .window-pill i {
            color: #FF8A80;
        }

        /* Fee Exemption Callout */
        .fee-card {
            background: linear-gradient(135deg, var(--accent-green-bg) 0%, var(--card-surface) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            box-shadow: var(--shadow-subtle);
        }

        .fee-badge-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: #10B981;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        .fee-info h4 {
            font-size: 1.05rem;
            font-weight: 800;
            color: #065F46;
            margin: 0 0 4px;
        }

        [data-theme="dark"] .fee-info h4 {
            color: #34D399;
        }

        .fee-info p {
            font-size: 0.85rem;
            color: var(--text-body);
            margin: 0;
            line-height: 1.45;
        }

        /* ========== INTERACTIVE CONTROLS BAR ========== */
        .controls-card {
            background: var(--card-surface);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-subtle);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .cat-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: var(--radius-pill);
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        [data-theme="dark"] .cat-tab-btn {
            background: rgba(255, 255, 255, 0.05);
            color: #94A3B8;
        }

        .cat-tab-btn:hover {
            color: var(--text-heading);
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        [data-theme="dark"] .cat-tab-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #FFFFFF;
        }

        .cat-tab-btn.active {
            background: var(--cswdo-maroon) !important;
            color: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.28);
            border-color: var(--cswdo-maroon);
        }

        .search-box-wrap {
            position: relative;
            min-width: 260px;
            flex: 1 1 260px;
            max-width: 360px;
        }

        .search-box-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .search-input {
            width: 100%;
            padding: 8px 14px 8px 38px;
            border-radius: var(--radius-pill);
            border: 1px solid var(--border-subtle);
            background: var(--canvas-bg);
            color: var(--text-heading);
            font-size: 0.84rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: var(--cswdo-maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
            background: var(--card-surface);
        }

        /* ========== INTERACTIVE PREPARATION SELF-CHECK TOOL ========== */
        .self-checker-card {
            background: var(--card-surface);
            border-radius: var(--radius-xl);
            padding: 24px 28px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-card);
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .self-checker-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--cswdo-maroon) 0%, var(--cswdo-gold) 100%);
        }

        .checker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .checker-title-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checker-badge-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(127, 3, 3, 0.1);
            color: var(--cswdo-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        [data-theme="dark"] .checker-badge-icon {
            background: rgba(255, 138, 128, 0.15);
            color: #FF8A80;
        }

        .checker-title-area h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
        }

        .checker-title-area p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 2px 0 0;
        }

        .checker-actions-top {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-checker-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            font-size: 0.78rem;
            font-weight: 700;
            background: rgba(0, 0, 0, 0.04);
            color: var(--text-heading);
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .btn-checker-action {
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
        }

        .btn-checker-action:hover {
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        /* Progress Bar */
        .checker-progress-container {
            background: rgba(0, 0, 0, 0.04);
            border-radius: var(--radius-pill);
            height: 10px;
            overflow: hidden;
            margin-bottom: 12px;
            position: relative;
        }

        [data-theme="dark"] .checker-progress-container {
            background: rgba(255, 255, 255, 0.08);
        }

        .checker-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #10B981 0%, #059669 100%);
            border-radius: var(--radius-pill);
            transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .checker-status-text {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        .checker-status-text strong {
            color: #10B981;
            font-weight: 800;
        }

        /* Checklist Quick Grid */
        .checker-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }

        .chk-item-pill {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            background: var(--canvas-bg);
            border: 1px solid var(--border-subtle);
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }

        .chk-item-pill:hover {
            border-color: var(--cswdo-maroon);
            background: rgba(127, 3, 3, 0.02);
        }

        [data-theme="dark"] .chk-item-pill:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 138, 128, 0.3);
        }

        .chk-item-pill input[type="checkbox"] {
            margin-top: 3px;
            accent-color: #10B981;
            width: 17px;
            height: 17px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .chk-item-pill span {
            font-size: 0.83rem;
            line-height: 1.4;
            color: var(--text-heading);
            font-weight: 600;
        }

        .chk-item-pill.checked {
            background: var(--accent-green-bg);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .chk-item-pill.checked span {
            color: #065F46;
            text-decoration: line-through;
            opacity: 0.8;
        }

        [data-theme="dark"] .chk-item-pill.checked span {
            color: #A7F3D0;
        }

        /* ========== REQUIREMENTS CARDS GRID ========== */
        .section-header-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-header-title h2 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .section-header-title h2 i {
            color: var(--cswdo-maroon);
        }

        [data-theme="dark"] .section-header-title h2 i {
            color: #FF8A80;
        }

        .requirements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 22px;
            margin-bottom: 36px;
        }

        .req-card {
            background: var(--card-surface);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .req-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: var(--border-highlight);
        }

        .req-card-header {
            padding: 20px 22px 18px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            position: relative;
        }

        .req-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #FFFFFF;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .icon-medical { background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%); }
        .icon-burial  { background: linear-gradient(135deg, #475569 0%, #1E293B 100%); }
        .icon-food    { background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%); }
        .icon-edu     { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }
        .icon-calamity{ background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); }
        .icon-general { background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%); }

        .req-card-title-wrap {
            flex: 1;
            min-width: 0;
        }

        .req-card-category {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .req-card-title {
            font-size: 1.12rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            line-height: 1.3;
        }

        .req-card-body {
            padding: 20px 22px;
            flex: 1;
        }

        .doc-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .doc-item {
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--border-subtle);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .doc-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .doc-bullet {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            margin-top: 2px;
            flex-shrink: 0;
        }

        [data-theme="dark"] .doc-bullet {
            background: rgba(16, 185, 129, 0.25);
            color: #34D399;
        }

        .doc-detail {
            flex: 1;
        }

        .doc-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.4;
            margin-bottom: 3px;
        }

        .doc-source-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.73rem;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.03);
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-subtle);
            margin-top: 3px;
        }

        [data-theme="dark"] .doc-source-tag {
            background: rgba(255, 255, 255, 0.04);
        }

        .doc-source-tag i {
            color: var(--cswdo-gold);
            font-size: 0.7rem;
        }

        .req-card-footer {
            padding: 14px 22px;
            background: rgba(0, 0, 0, 0.02);
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        [data-theme="dark"] .req-card-footer {
            background: rgba(0, 0, 0, 0.15);
        }

        .card-tip {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-tip i {
            color: var(--cswdo-maroon);
        }

        [data-theme="dark"] .card-tip i {
            color: #FF8A80;
        }

        .card-action-link {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--cswdo-maroon);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: gap 0.2s ease;
        }

        [data-theme="dark"] .card-action-link {
            color: #FF8A80;
        }

        .card-action-link:hover {
            gap: 7px;
            text-decoration: underline;
        }

        /* ========== CITIZEN'S CHARTER STEP-BY-STEP WORKFLOW ========== */
        .process-section {
            background: var(--card-surface);
            border-radius: var(--radius-xl);
            padding: 30px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-card);
            margin-bottom: 32px;
        }

        .process-flow-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 22px;
            margin-bottom: 24px;
        }

        .flow-step-card {
            background: var(--canvas-bg);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        .flow-step-card:hover {
            transform: translateY(-3px);
            border-color: var(--cswdo-maroon);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        .step-badge-number {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: var(--cswdo-maroon);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            margin-bottom: 12px;
            box-shadow: 0 3px 10px rgba(127, 3, 3, 0.25);
        }

        .step-name {
            font-size: 0.98rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 6px;
            line-height: 1.35;
        }

        .step-desc {
            font-size: 0.83rem;
            color: var(--text-body);
            line-height: 1.45;
            margin: 0 0 14px;
            flex: 1;
        }

        .step-meta-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-top: 10px;
            border-top: 1px dashed var(--border-subtle);
            font-size: 0.74rem;
        }

        .step-time-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 700;
            color: #0284C7;
            background: var(--accent-blue-bg);
            padding: 3px 8px;
            border-radius: var(--radius-pill);
        }

        .step-officer {
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Toggle Button for Table */
        .charter-table-toggle-btn {
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-subtle);
            color: var(--text-heading);
            padding: 10px 18px;
            border-radius: var(--radius-pill);
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .charter-table-toggle-btn {
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
        }

        .charter-table-toggle-btn:hover {
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        /* Full Citizen's Charter Regulatory Matrix Table */
        .charter-table-wrapper {
            margin-top: 20px;
            overflow-x: auto;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-subtle);
            display: none;
        }

        .charter-table-wrapper.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
            min-width: 780px;
            background: var(--card-surface);
        }

        .modern-table th {
            background: var(--cswdo-maroon);
            color: #FFFFFF;
            padding: 14px 16px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .modern-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-body);
            vertical-align: top;
            line-height: 1.45;
        }

        .modern-table tr:hover td {
            background: rgba(0, 0, 0, 0.015);
        }

        [data-theme="dark"] .modern-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .modern-table tr.table-total-row {
            background: rgba(127, 3, 3, 0.06);
            font-weight: 800;
        }

        [data-theme="dark"] .modern-table tr.table-total-row {
            background: rgba(255, 138, 128, 0.08);
        }

        .modern-table tr.table-total-row td {
            color: var(--cswdo-maroon);
            border-top: 2px solid var(--cswdo-maroon);
        }

        [data-theme="dark"] .modern-table tr.table-total-row td {
            color: #FF8A80;
            border-top-color: #FF8A80;
        }

        /* ========== GUIDELINES & PROXY FILING CARDS ========== */
        .guidelines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .guide-box {
            background: var(--card-surface);
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-subtle);
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .guide-box-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .g-icon-amber { background: var(--accent-amber-bg); color: var(--accent-amber); }
        .g-icon-blue  { background: var(--accent-blue-bg); color: var(--accent-blue); }
        .g-icon-purple{ background: var(--accent-purple-bg); color: var(--accent-purple); }

        .guide-box-content h4 {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 4px;
        }

        .guide-box-content p {
            font-size: 0.83rem;
            color: var(--text-body);
            margin: 0;
            line-height: 1.45;
        }

        /* ========== FAQ ACCORDION ========== */
        .faq-section {
            background: var(--card-surface);
            border-radius: var(--radius-xl);
            padding: 30px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-card);
            margin-bottom: 32px;
        }

        .faq-accordion {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .faq-item {
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .faq-question {
            padding: 16px 20px;
            background: var(--canvas-bg);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--text-heading);
            user-select: none;
        }

        .faq-question i.faq-chevron {
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: transform 0.25s ease;
        }

        .faq-item.open .faq-question i.faq-chevron {
            transform: rotate(180deg);
            color: var(--cswdo-maroon);
        }

        [data-theme="dark"] .faq-item.open .faq-question i.faq-chevron {
            color: #FF8A80;
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: var(--card-surface);
            font-size: 0.86rem;
            color: var(--text-body);
            line-height: 1.55;
        }

        .faq-item.open .faq-answer {
            padding: 16px 20px 18px;
            max-height: 250px;
        }

        /* ========== ACTION DOCK & CONTACT FOOTER ========== */
        .action-dock {
            background: var(--card-surface);
            border-radius: var(--radius-xl);
            padding: 26px 32px;
            border: 1px solid var(--border-subtle);
            box-shadow: var(--shadow-card);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .dock-left h3 {
            font-size: 1.18rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 4px;
        }

        .dock-left p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
        }

        .dock-buttons {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-dock {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: var(--radius-pill);
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
        }

        .btn-dock-primary {
            background: var(--cswdo-maroon);
            color: #FFFFFF !important;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3);
        }

        .btn-dock-primary:hover {
            background: var(--cswdo-maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(127, 3, 3, 0.38);
        }

        .btn-dock-secondary {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-heading) !important;
            border: 1px solid var(--border-subtle);
        }

        [data-theme="dark"] .btn-dock-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: #FFFFFF !important;
        }

        .btn-dock-secondary:hover {
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        [data-theme="dark"] .btn-dock-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Help Strip */
        .help-strip {
            text-align: center;
            padding: 16px;
            font-size: 0.84rem;
            color: var(--text-muted);
        }

        .help-strip a {
            color: var(--cswdo-maroon);
            text-decoration: none;
            font-weight: 700;
        }

        [data-theme="dark"] .help-strip a {
            color: #FF8A80;
        }

        /* ========== PRINT STYLESHEET ========== */
        @media print {
            body {
                background: #FFF !important;
                color: #000 !important;
                padding: 0 !important;
            }
            .app-container, .main-content, .public-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .cswdo-sidebar, .portal-header, .portal-page-subheader,
            .navbar, .footer, .controls-card, .self-checker-card,
            .hero-actions, .charter-table-toggle-btn, .action-dock,
            .help-strip, .req-card-footer, .faq-section {
                display: none !important;
            }
            .aics-req-hero {
                background: #FFF !important;
                color: #000 !important;
                border: 2px solid #000 !important;
                box-shadow: none !important;
                padding: 15px !important;
            }
            .aics-req-hero h1, .aics-req-hero p, .metric-info .metric-val {
                color: #000 !important;
            }
            .hero-badge {
                border-color: #000 !important;
                color: #000 !important;
                background: transparent !important;
            }
            .metric-item {
                background: #F0F0F0 !important;
                border: 1px solid #CCC !important;
            }
            .metric-info .metric-label {
                color: #444 !important;
            }
            .req-card {
                break-inside: avoid;
                border: 1px solid #CCC !important;
                box-shadow: none !important;
                margin-bottom: 15px !important;
            }
            .charter-table-wrapper {
                display: block !important;
            }
            .modern-table th {
                background: #333 !important;
                color: #FFF !important;
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .advisory-row {
                grid-template-columns: 1fr;
            }
            .aics-req-hero {
                padding: 28px 24px;
            }
        }

        @media (max-width: 640px) {
            .hero-title-group h1 {
                font-size: 1.45rem;
            }
            .hero-metrics-grid {
                grid-template-columns: 1fr 1fr;
            }
            .requirements-grid {
                grid-template-columns: 1fr;
            }
            .action-dock {
                flex-direction: column;
                align-items: stretch;
            }
            .dock-buttons {
                flex-direction: column;
            }
            .btn-dock {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php if ($is_resident_portal): ?>
    <div class="app-container">
        <!-- Resident Portal Sidebar -->
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner with Signature Branding -->
            <?php
            $banner_title = "AICS Documentary Requirements";
            $banner_subtitle = "Official Citizen's Charter guidelines, service standards, and preparation checklist";
            $banner_icon = "fa-clipboard-check";
            $banner_actions = [
                ["label" => "Print Guide", "url" => "javascript:window.print();", "icon" => "fa-print", "type" => "secondary"],
                ["label" => "Back to AICS", "url" => "aics.php", "icon" => "fa-arrow-left", "type" => "primary"]
            ];
            include __DIR__ . "/includes/portal-banner.php";
            ?>

            <div class="portal-inner-content">
<?php else: ?>
    <!-- Public Visitor Navigation -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="public-wrapper">
<?php endif; ?>

        <!-- ========== 1. HERO BANNER ========== -->
        <section class="aics-req-hero">
            <div class="hero-top-badges">
                <span class="hero-badge gold"><i class="fas fa-certificate"></i> Citizen's Charter Compliant</span>
                <span class="hero-badge"><i class="fas fa-shield-heart"></i> Social Safety Net Program</span>
                <span class="hero-badge"><i class="fas fa-landmark"></i> Republic Act No. 11032</span>
            </div>

            <div class="hero-title-group">
                <h1>Assistance to Individuals in Crisis Situations (AICS)</h1>
                <p>
                    Comprehensive documentary requirements guide, processing procedures, and citizen guidelines for emergency medical, burial, transportation, and educational crisis intervention.
                </p>
            </div>

            <!-- Quick Metrics Grid -->
            <div class="hero-metrics-grid">
                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-tags"></i></div>
                    <div class="metric-info">
                        <div class="metric-label">Total Processing Fee</div>
                        <div class="metric-val">100% Free (₱0.00)</div>
                    </div>
                </div>

                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-stopwatch"></i></div>
                    <div class="metric-info">
                        <div class="metric-label">Standard Turnaround</div>
                        <div class="metric-val">~1h 43m Full Flow</div>
                    </div>
                </div>

                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-door-open"></i></div>
                    <div class="metric-info">
                        <div class="metric-label">CSWDO Service Lanes</div>
                        <div class="metric-val">Windows 1, 2, and 3</div>
                    </div>
                </div>

                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-users"></i></div>
                    <div class="metric-info">
                        <div class="metric-label">Eligible Beneficiaries</div>
                        <div class="metric-val">Palayan Residents</div>
                    </div>
                </div>
            </div>

            <!-- Top Actions -->
            <div class="hero-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="aics.php" class="btn-hero btn-hero-primary">
                        <i class="fas fa-paper-plane"></i> Apply for AICS Online
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-hero btn-hero-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Apply
                    </a>
                <?php endif; ?>
                <a href="print-forms.php?form=intake" target="_blank" class="btn-hero btn-hero-glass">
                    <i class="fas fa-file-pdf"></i> Download Intake Sheet
                </a>
                <a href="appointments.php" class="btn-hero btn-hero-glass">
                    <i class="fas fa-calendar-check"></i> Book CSWDO Slot
                </a>
                <button type="button" class="btn-hero btn-hero-glass" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Complete Checklist
                </button>
            </div>
        </section>

        <!-- ========== 2. STATUTORY ADVISORY & FEE CLARIFICATION ========== -->
        <section class="advisory-row">
            <div class="advisory-card">
                <div class="advisory-icon-wrap">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="advisory-body">
                    <h3>Purpose of the AICS Program</h3>
                    <p>
                        AICS serves as a primary social safety net and stop-gap measure to provide immediate psychosocial and direct financial intervention to vulnerable individuals and families facing unforeseen life events or crises. Grants are determined based on an objective assessment by licensed City Social Welfare Officers.
                    </p>
                    <div class="window-pills">
                        <span class="window-pill"><i class="fas fa-wheelchair"></i> Window 1: PWDs &amp; Special Needs</span>
                        <span class="window-pill"><i class="fas fa-user-friends"></i> Window 2: General Crisis Intervention</span>
                        <span class="window-pill"><i class="fas fa-person-cane"></i> Window 3: OSCA Senior Citizens</span>
                    </div>
                </div>
            </div>

            <div class="fee-card">
                <div class="fee-badge-icon">
                    <i class="fas fa-hand-holding-dollar"></i>
                </div>
                <div class="fee-info">
                    <h4>No Fees or Charges</h4>
                    <p>
                        All services, social case studies, voucher preparations, and document releases provided under the AICS program are <strong>completely free of charge</strong>. Never pay any fee or unauthorized facilitator.
                    </p>
                </div>
            </div>
        </section>

        <!-- ========== 3. CONTROLS BAR: CATEGORY FILTER TABS & SEARCH ========== -->
        <section class="controls-card">
            <div class="category-tabs" id="categoryTabs">
                <button type="button" class="cat-tab-btn active" data-category="all">
                    <i class="fas fa-th-large"></i> All Requirements
                </button>
                <button type="button" class="cat-tab-btn" data-category="medical">
                    <i class="fas fa-hospital-user"></i> Medical &amp; Hospital
                </button>
                <button type="button" class="cat-tab-btn" data-category="burial">
                    <i class="fas fa-cross"></i> Burial &amp; Funeral
                </button>
                <button type="button" class="cat-tab-btn" data-category="food">
                    <i class="fas fa-bus"></i> Transportation &amp; Food
                </button>
                <button type="button" class="cat-tab-btn" data-category="educational">
                    <i class="fas fa-graduation-cap"></i> Educational Aid
                </button>
                <button type="button" class="cat-tab-btn" data-category="calamity">
                    <i class="fas fa-fire"></i> Fire &amp; Calamity
                </button>
            </div>

            <div class="search-box-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="docSearchInput" class="search-input" placeholder="Search requirements e.g. death cert, indigent...">
            </div>
        </section>

        <!-- ========== 4. INTERACTIVE DOCUMENT PREPARATION CHECKER ========== -->
        <section class="self-checker-card">
            <div class="checker-header">
                <div class="checker-title-area">
                    <div class="checker-badge-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div>
                        <h3>Citizen Document Preparation Checklist</h3>
                        <p>Check off the documents you have prepared so far before heading to City Hall or submitting online.</p>
                    </div>
                </div>
                <div class="checker-actions-top">
                    <button type="button" class="btn-checker-action" onclick="resetDocumentChecklist()">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                    <button type="button" class="btn-checker-action" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Checklist
                    </button>
                </div>
            </div>

            <!-- Live Progress Bar -->
            <div class="checker-progress-container">
                <div class="checker-progress-bar" id="checkerProgressBar"></div>
            </div>

            <div class="checker-status-text">
                <span id="checkerCountLabel">0 of 6 Core Documents Ready</span>
                <span id="checkerMotivation">Gather your original copies and photocopies.</span>
            </div>

            <!-- Dynamic Checklist Items -->
            <div class="checker-items-grid" id="checkerItemsContainer">
                <label class="chk-item-pill" data-check-id="chk_id_valid">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Photocopy of Valid Government-issued Photo ID (Client &amp; Beneficiary)</span>
                </label>
                <label class="chk-item-pill" data-check-id="chk_indigency">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Original Barangay Certificate of Residency &amp; Indigency</span>
                </label>
                <label class="chk-item-pill" data-check-id="chk_letter">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Written Request Letter addressed to City Mayor / CSWDO</span>
                </label>
                <label class="chk-item-pill" data-check-id="chk_med_doc">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Medical Certificate / Abstract (if applying for Medical Aid)</span>
                </label>
                <label class="chk-item-pill" data-check-id="chk_bills">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Hospital Statement of Account / Doctor's Prescription with estimate</span>
                </label>
                <label class="chk-item-pill" data-check-id="chk_death">
                    <input type="checkbox" onchange="updateChecklistProgress()">
                    <span>Registered Death Certificate &amp; Funeral Contract (if Burial Aid)</span>
                </label>
            </div>
        </section>

        <!-- ========== 5. DETAILED REQUIREMENTS CARDS GRID ========== -->
        <div class="section-header-title">
            <h2><i class="fas fa-folder-open"></i> Documentary Requirements by Category</h2>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                Showing complete official guidelines per assistance type
            </span>
        </div>

        <div class="requirements-grid" id="requirementsGrid">
            <!-- 1. GENERAL REQUIREMENTS CARD -->
            <div class="req-card" data-category="general medical burial food educational calamity">
                <div class="req-card-header">
                    <div class="req-card-icon icon-general">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Universal Baseline</div>
                        <h4 class="req-card-title">General Requirements</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Barangay Certificate of Residency &amp; Indigency</div>
                                <span class="doc-source-tag"><i class="fas fa-building"></i> Secure from: Barangay Hall of residence</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Photocopy of Valid Government-issued Photo ID</div>
                                <span class="doc-source-tag"><i class="fas fa-user"></i> Secure from: Applicant &amp; Beneficiary (COMELEC, PhilHealth, UMID, Driver's License, OSCA, PWD ID)</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Formal Written Request Letter</div>
                                <span class="doc-source-tag"><i class="fas fa-pen"></i> Addressed to the City Mayor or CSWDO Department Head stating purpose of assistance</span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-info-circle"></i> Required for all categories</span>
                    <a href="print-forms.php?form=intake" target="_blank" class="card-action-link">
                        Intake Sheet <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 2. MEDICAL / HOSPITALIZATION CARD -->
            <div class="req-card" data-category="medical">
                <div class="req-card-header">
                    <div class="req-card-icon icon-medical">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Healthcare Support</div>
                        <h4 class="req-card-title">Medical &amp; Hospitalization</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <?php if (!empty($aics_grouped_reqs['medical'])): ?>
                            <?php foreach ($aics_grouped_reqs['medical'] as $m_doc): ?>
                            <li class="doc-item">
                                <div class="doc-bullet"><i class="fas fa-check"></i></div>
                                <div class="doc-detail">
                                    <div class="doc-name"><?php echo htmlspecialchars($m_doc['document_name']); ?> <?php if (!$m_doc['is_required']): ?><span style="font-size:0.75rem; color:#2196F3; font-weight:600;">(Conditional)</span><?php endif; ?></div>
                                    <span class="doc-source-tag"><i class="fas fa-file-medical"></i> <?php echo htmlspecialchars($m_doc['description'] ?: 'Attending physician / hospital'); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Medical Abstract or Medical Certificate</div>
                                <span class="doc-source-tag"><i class="fas fa-user-doctor"></i> Secure from: Attending Physician with PRC license # &amp; diagnosis</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Original Doctor's Prescription with Cost Estimate</div>
                                <span class="doc-source-tag"><i class="fas fa-prescription-bottle-medical"></i> Dated within 3 months; for medicines or medical supplies</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Hospital Statement of Account / Unpaid Bill</div>
                                <span class="doc-source-tag"><i class="fas fa-hospital"></i> Secure from: Hospital Billing Section where patient is admitted</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Laboratory / Diagnostic Request with Quotation</div>
                                <span class="doc-source-tag"><i class="fas fa-flask"></i> For CT Scan, MRI, Dialysis, Chemotherapy, or special lab work</span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-clock"></i> 1-2 Days Processing</span>
                    <a href="aics.php" class="card-action-link">
                        Apply Medical <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 3. BURIAL & FUNERAL ASSISTANCE -->
            <div class="req-card" data-category="burial">
                <div class="req-card-header">
                    <div class="req-card-icon icon-burial">
                        <i class="fas fa-cross"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Bereavement Support</div>
                        <h4 class="req-card-title">Burial &amp; Funeral Assistance</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <?php if (!empty($aics_grouped_reqs['funeral'])): ?>
                            <?php foreach ($aics_grouped_reqs['funeral'] as $f_doc): ?>
                            <li class="doc-item">
                                <div class="doc-bullet"><i class="fas fa-check"></i></div>
                                <div class="doc-detail">
                                    <div class="doc-name"><?php echo htmlspecialchars($f_doc['document_name']); ?> <?php if (!$f_doc['is_required']): ?><span style="font-size:0.75rem; color:#2196F3; font-weight:600;">(Conditional)</span><?php endif; ?></div>
                                    <span class="doc-source-tag"><i class="fas fa-file-contract"></i> <?php echo htmlspecialchars($f_doc['description'] ?: 'Official issuing authority'); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Certified True Copy of Registered Death Certificate</div>
                                <span class="doc-source-tag"><i class="fas fa-landmark"></i> Secure from: City Civil Registrar's Office (CCRO)</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Original Unpaid Funeral Contract</div>
                                <span class="doc-source-tag"><i class="fas fa-file-invoice"></i> Secure from: Funeral Parlor where services were acquired</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Proof of Relationship with the Deceased</div>
                                <span class="doc-source-tag"><i class="fas fa-people-arrows"></i> Marriage Certificate (for spouse) or Birth Certificate (for children/parents)</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Barangay Indigency indicating Deceased Resident</div>
                                <span class="doc-source-tag"><i class="fas fa-building"></i> Secure from: Barangay Hall where the deceased resided</span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-heart"></i> Expedited Priority</span>
                    <a href="aics.php" class="card-action-link">
                        Apply Burial <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 4. FOOD & TRANSPORTATION ASSISTANCE -->
            <div class="req-card" data-category="food">
                <div class="req-card-header">
                    <div class="req-card-icon icon-food">
                        <i class="fas fa-bus-simple"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Stranded &amp; Crisis Travel</div>
                        <h4 class="req-card-title">Transportation &amp; Food Relief</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Incident or Referral Report</div>
                                <span class="doc-source-tag"><i class="fas fa-file-lines"></i> Police blotter, Barangay blotter, or CSWDO social intake statement</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Proof of Destination or Fare Quotation</div>
                                <span class="doc-source-tag"><i class="fas fa-ticket"></i> Bus terminal quotation, ticket invoice, or travel itinerary</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Valid Government Photo ID or Clearance</div>
                                <span class="doc-source-tag"><i class="fas fa-user-check"></i> Proving identity and hometown address</span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-bolt"></i> Same-day Intake</span>
                    <a href="aics.php" class="card-action-link">
                        Apply Transport <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 5. EDUCATIONAL ASSISTANCE -->
            <div class="req-card" data-category="educational">
                <div class="req-card-header">
                    <div class="req-card-icon icon-edu">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Student Crisis Aid</div>
                        <h4 class="req-card-title">Educational Assistance</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Certificate of Enrollment or Registration Form</div>
                                <span class="doc-source-tag"><i class="fas fa-school"></i> Signed by School Registrar for the current academic term</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Statement of Account or Assessment of Fees</div>
                                <span class="doc-source-tag"><i class="fas fa-receipt"></i> Official school billing statement with unpaid balance</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Photocopy of Valid School ID of Student</div>
                                <span class="doc-source-tag"><i class="fas fa-id-badge"></i> Current school year ID card</span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-book-open"></i> Qualified Students</span>
                    <a href="aics.php" class="card-action-link">
                        Apply Education <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- 6. FIRE & CALAMITY ASSISTANCE -->
            <div class="req-card" data-category="calamity">
                <div class="req-card-header">
                    <div class="req-card-icon icon-calamity">
                        <i class="fas fa-house-chimney-crack"></i>
                    </div>
                    <div class="req-card-title-wrap">
                        <div class="req-card-category">Emergency Relief</div>
                        <h4 class="req-card-title">Fire &amp; Calamity Support</h4>
                    </div>
                </div>
                <div class="req-card-body">
                    <ul class="doc-list">
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Bureau of Fire Protection (BFP) Certificate</div>
                                <span class="doc-source-tag"><i class="fas fa-fire-extinguisher"></i> For fire victims, issued by local BFP station</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Barangay Calamity / Disaster Incident Report</div>
                                <span class="doc-source-tag"><i class="fas fa-building"></i> Confirming property damage and affected household members</span>
                            </div>
                        </li>
                        <li class="doc-item">
                            <div class="doc-bullet"><i class="fas fa-check"></i></div>
                            <div class="doc-detail">
                                <div class="doc-name">Proof of Residency &amp; Household Composition</div>
                                <span class="doc-source-tag"><i class="fas fa-users-viewfinder"></i> Verified by Barangay Chairman or CSWDO field validator</span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="req-card-footer">
                    <span class="card-tip"><i class="fas fa-triangle-exclamation"></i> Rapid Assessment</span>
                    <a href="aics.php" class="card-action-link">
                        Apply Relief <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- ========== 6. STEP-BY-STEP PROCESS ROADMAP ========== -->
        <section class="process-section">
            <div class="section-header-title">
                <div>
                    <h2><i class="fas fa-route"></i> Official Citizen's Charter Step-by-Step Flow</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 4px 0 0;">
                        Standard client journey from registration to voucher release at the CSWDO Office
                    </p>
                </div>
                <button type="button" class="charter-table-toggle-btn" id="toggleTableBtn" onclick="toggleCharterTable()">
                    <i class="fas fa-table-list"></i> <span id="toggleTableLabel">Show Full Regulatory Table</span>
                </button>
            </div>

            <!-- Visual Flow Stepper Cards -->
            <div class="process-flow-grid">
                <div class="flow-step-card">
                    <div class="step-badge-number">1</div>
                    <h4 class="step-name">Logbook Registration</h4>
                    <p class="step-desc">Register your name, address, gender, contact number, and purpose in the client logbook at Window 1, 2, or 3.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 1 Minute</span>
                        <span class="step-officer">CSWDO Aide</span>
                    </div>
                </div>

                <div class="flow-step-card">
                    <div class="step-badge-number">2</div>
                    <h4 class="step-name">Document Submission</h4>
                    <p class="step-desc">Submit all documentary requirements for verification, screening, and completeness checking.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 2 Minutes</span>
                        <span class="step-officer">Social Welfare Officer</span>
                    </div>
                </div>

                <div class="flow-step-card">
                    <div class="step-badge-number">3</div>
                    <h4 class="step-name">Intake &amp; Assessment</h4>
                    <p class="step-desc">Personal interview with a licensed Social Worker to evaluate the crisis severity and psychosocial needs.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 15 Minutes</span>
                        <span class="step-officer">Social Worker</span>
                    </div>
                </div>

                <div class="flow-step-card">
                    <div class="step-badge-number">4</div>
                    <h4 class="step-name">Voucher &amp; Case Study</h4>
                    <p class="step-desc">Preparation of Social Case Study Report, Justification, Certificate of Eligibility, and disbursement voucher.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 1 Hour</span>
                        <span class="step-officer">Case Worker</span>
                    </div>
                </div>

                <div class="flow-step-card">
                    <div class="step-badge-number">5</div>
                    <h4 class="step-name">Supervisory Approval</h4>
                    <p class="step-desc">Administrative review and authorization by the Department Head and city executive officials.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 15 Minutes</span>
                        <span class="step-officer">CSWDO Head</span>
                    </div>
                </div>

                <div class="flow-step-card">
                    <div class="step-badge-number">6</div>
                    <h4 class="step-name">Assistance Release</h4>
                    <p class="step-desc">Client is notified via SMS/call for issuance of check, cash voucher, or guarantee letter.</p>
                    <div class="step-meta-strip">
                        <span class="step-time-pill"><i class="fas fa-clock"></i> 10 Minutes</span>
                        <span class="step-officer">Disbursing Officer</span>
                    </div>
                </div>
            </div>

            <!-- Full Regulatory Table (Collapsible) -->
            <div class="charter-table-wrapper" id="charterTableWrapper">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">Client Steps</th>
                            <th style="width: 32%;">Agency Action</th>
                            <th style="width: 12%;">Fees to Pay</th>
                            <th style="width: 14%;">Processing Time</th>
                            <th style="width: 14%;">Person Responsible</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>1. Register in Logbook</strong><br><small style="color: var(--text-muted);">Logbook at Window 1, 2, or 3</small></td>
                            <td>Assist the client during their registration and queue numbering.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>1 Minute</td>
                            <td>CSWDO Intake Staff</td>
                        </tr>
                        <tr>
                            <td><strong>2. Submit Required Documents</strong><br><small style="color: var(--text-muted);">Window 1, 2, or 3 for initial check</small></td>
                            <td>Receive &amp; review required documents and check for completeness.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>2 Minutes</td>
                            <td>Social Welfare Officer</td>
                        </tr>
                        <tr>
                            <td><strong>3. Intake Interview</strong><br><small style="color: var(--text-muted);">Face-to-face consultation</small></td>
                            <td>Conduct assessment interview to determine crisis intervention scope.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>15 Minutes</td>
                            <td>Social Worker</td>
                        </tr>
                        <tr>
                            <td><strong>4. Await Case Evaluation</strong><br><small style="color: var(--text-muted);">Client informed via SMS notification</small></td>
                            <td>Preparation of Social Case Study Report, Certificate of Eligibility, and voucher.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>1 Hour</td>
                            <td>Social Welfare Officer</td>
                        </tr>
                        <tr>
                            <td>—</td>
                            <td>4.1 Administrative review and approval.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>15 Minutes</td>
                            <td>CSWDO Head</td>
                        </tr>
                        <tr>
                            <td>—</td>
                            <td>4.2 Submission to City Budget &amp; Accounting Office.</td>
                            <td><strong style="color: #10B981;">NONE</strong></td>
                            <td>10 Minutes</td>
                            <td>CSWDO Aide / Liaison</td>
                        </tr>
                        <tr class="table-total-row">
                            <td>TOTAL STANDARD DURATION</td>
                            <td>Approved Citizen's Charter Process Flow</td>
                            <td>₱0.00 (FREE)</td>
                            <td>1 HOUR &amp; 43 MINUTES</td>
                            <td>CSWDO Team</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ========== 7. SPECIAL INSTRUCTIONS & GUIDELINES ========== -->
        <section class="guidelines-grid">
            <div class="guide-box">
                <div class="guide-box-icon g-icon-amber">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="guide-box-content">
                    <h4>Proxy / Representative Filing</h4>
                    <p>
                        If the patient or beneficiary is bedridden, hospitalized, or deceased, an immediate family member may file. Bring a signed <strong>Authorization Letter</strong> and valid IDs for both parties.
                    </p>
                </div>
            </div>

            <div class="guide-box">
                <div class="guide-box-icon g-icon-blue">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="guide-box-content">
                    <h4>Barangay Certificate Validity</h4>
                    <p>
                        The Barangay Certificate of Indigency or Low Income must be issued by the Barangay of actual residence and should be dated within the last <strong>3 to 6 months</strong>.
                    </p>
                </div>
            </div>

            <div class="guide-box">
                <div class="guide-box-icon g-icon-purple">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="guide-box-content">
                    <h4>Receipts &amp; Estimates</h4>
                    <p>
                        Hospital bills, promissory notes, and prescriptions must be original or certified copies bearing official hospital stamps and physician license/PTR numbers.
                    </p>
                </div>
            </div>
        </section>

        <!-- ========== 8. FREQUENTLY ASKED QUESTIONS (FAQ) ========== -->
        <section class="faq-section">
            <div class="section-header-title" style="margin-bottom: 6px;">
                <h2><i class="fas fa-circle-question"></i> Frequently Asked Questions</h2>
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                    Quick answers to common resident inquiries
                </span>
            </div>

            <div class="faq-accordion" id="faqAccordion">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Can I apply for AICS online through this portal?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </div>
                    <div class="faq-answer">
                        Yes! Verified residents of Palayan City can submit applications, upload documentary requirements, and track status directly online through the One Palayan-Kalinga Hub without waiting in long physical queues.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>How soon can we receive medical assistance after submitting documents?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </div>
                    <div class="faq-answer">
                        Standard in-person applications take approximately 1 hour and 43 minutes for complete assessment and voucher endorsement. Once approved by the City Budget Office, disbursements or Guarantee Letters (GL) are scheduled immediately.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>What if the patient is confined in a private hospital outside Palayan City?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </div>
                    <div class="faq-answer">
                        Palayan City bonafide residents confined in public or accredited private hospitals outside Palayan are eligible, provided they submit the official hospital bill, medical abstract, and Barangay Certificate of Residency.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>What is the deadline for filing burial assistance?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </div>
                    <div class="faq-answer">
                        Burial assistance applications should ideally be filed within 3 months from the registered date of death, accompanied by the Certified True Copy of Death Certificate and the Funeral Contract.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Are Senior Citizens and Persons with Disabilities given priority lanes?</span>
                        <i class="fas fa-chevron-down faq-chevron"></i>
                    </div>
                    <div class="faq-answer">
                        Yes. Window 1 is dedicated specifically to Persons with Disabilities (PWDs), and Window 3 is designated for Senior Citizens (OSCA) with priority seating and express assistance lanes.
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== 9. ACTION DOCK / CTAS ========== -->
        <section class="action-dock">
            <div class="dock-left">
                <h3>Ready to Submit Your Application?</h3>
                <p>Have all your documentary requirements ready? Start your online request or visit CSWDO Ground Floor.</p>
            </div>
            <div class="dock-buttons">
                <?php if (isLoggedIn()): ?>
                    <a href="aics.php" class="btn-dock btn-dock-primary">
                        <i class="fas fa-paper-plane"></i> Apply for AICS Now
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-dock btn-dock-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Apply
                    </a>
                <?php endif; ?>
                <a href="track-aics.php" class="btn-dock btn-dock-secondary">
                    <i class="fas fa-magnifying-glass"></i> Track Status
                </a>
                <a href="appointments.php" class="btn-dock btn-dock-secondary">
                    <i class="fas fa-calendar-check"></i> Book Appointment
                </a>
            </div>
        </section>

        <!-- Help Strip -->
        <div class="help-strip">
            <i class="fas fa-building"></i> City Social Welfare and Development Office (CSWDO) &bull; Ground Floor, Palayan City Hall &bull; Mon - Fri: 8:00 AM - 5:00 PM &bull; Need help? <a href="contact.php">Contact CSWDO Support</a>
        </div>

<?php if ($is_resident_portal): ?>
            </div> <!-- Closes portal-inner-content -->
        </div> <!-- Closes main-content -->
    </div> <!-- Closes app-container -->
<?php else: ?>
    </div> <!-- Closes public-wrapper -->
<?php endif; ?>

<!-- Interactive JavaScript -->
<script>
(function() {
    // 1. Category Tab Filtering
    const tabButtons = document.querySelectorAll('.cat-tab-btn');
    const reqCards   = document.querySelectorAll('.req-card');
    const searchInput= document.getElementById('docSearchInput');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterRequirements();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterRequirements();
        });
    }

    function filterRequirements() {
        const activeTab = document.querySelector('.cat-tab-btn.active');
        const selectedCategory = activeTab ? activeTab.getAttribute('data-category') : 'all';
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

        reqCards.forEach(card => {
            const cardCategories = card.getAttribute('data-category') || '';
            const cardText = card.innerText.toLowerCase();

            const matchesCategory = (selectedCategory === 'all' || cardCategories.includes(selectedCategory));
            const matchesQuery = (!query || cardText.includes(query));

            if (matchesCategory && matchesQuery) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // 2. Interactive Document Preparation Self-Checker with LocalStorage
    const STORAGE_KEY = 'cswdo_aics_checklist_state';

    function loadSavedChecklist() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            const items = document.querySelectorAll('.chk-item-pill');
            items.forEach(item => {
                const id = item.getAttribute('data-check-id');
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (saved[id]) {
                    checkbox.checked = true;
                    item.classList.add('checked');
                } else {
                    checkbox.checked = false;
                    item.classList.remove('checked');
                }
            });
            updateChecklistUI();
        } catch (e) {}
    }

    window.updateChecklistProgress = function() {
        const items = document.querySelectorAll('.chk-item-pill');
        const savedState = {};

        items.forEach(item => {
            const id = item.getAttribute('data-check-id');
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox.checked) {
                item.classList.add('checked');
                savedState[id] = true;
            } else {
                item.classList.remove('checked');
            }
        });

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(savedState));
        } catch (e) {}

        updateChecklistUI();
    };

    function updateChecklistUI() {
        const items = document.querySelectorAll('.chk-item-pill');
        const total = items.length;
        let checkedCount = 0;

        items.forEach(item => {
            if (item.querySelector('input[type="checkbox"]').checked) {
                checkedCount++;
            }
        });

        const pct = total > 0 ? Math.round((checkedCount / total) * 100) : 0;
        const progressBar = document.getElementById('checkerProgressBar');
        const countLabel  = document.getElementById('checkerCountLabel');
        const motivation  = document.getElementById('checkerMotivation');

        if (progressBar) progressBar.style.width = pct + '%';
        if (countLabel)  countLabel.textContent = `${checkedCount} of ${total} Core Documents Ready (${pct}%)`;

        if (motivation) {
            if (pct === 0) {
                motivation.textContent = "Start checking off documents you have prepared.";
                motivation.style.color = "var(--text-muted)";
            } else if (pct < 50) {
                motivation.textContent = "Good start! Don't forget to prepare 2 photocopies each.";
                motivation.style.color = "var(--accent-blue)";
            } else if (pct < 100) {
                motivation.textContent = "Almost there! Make sure signatures and dates are visible.";
                motivation.style.color = "var(--accent-amber)";
            } else {
                motivation.textContent = "All set! You are 100% prepared to submit your AICS request.";
                motivation.style.color = "#10B981";
            }
        }
    }

    window.resetDocumentChecklist = function() {
        localStorage.removeItem(STORAGE_KEY);
        const items = document.querySelectorAll('.chk-item-pill');
        items.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            checkbox.checked = false;
            item.classList.remove('checked');
        });
        updateChecklistUI();
    };

    // 3. Citizen's Charter Table Toggle
    window.toggleCharterTable = function() {
        const tableWrap = document.getElementById('charterTableWrapper');
        const label     = document.getElementById('toggleTableLabel');
        if (!tableWrap) return;

        if (tableWrap.classList.contains('active')) {
            tableWrap.classList.remove('active');
            if (label) label.textContent = 'Show Full Regulatory Table';
        } else {
            tableWrap.classList.add('active');
            if (label) label.textContent = 'Hide Regulatory Table';
        }
    };

    // 4. FAQ Accordion
    window.toggleFaq = function(element) {
        const parentItem = element.closest('.faq-item');
        if (!parentItem) return;

        const isOpen = parentItem.classList.contains('open');
        // Close siblings for clean accordion behavior
        document.querySelectorAll('.faq-item').forEach(item => {
            item.classList.remove('open');
        });

        if (!isOpen) {
            parentItem.classList.add('open');
        }
    };

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        loadSavedChecklist();
    });

})();
</script>

<?php if ($is_resident_portal): ?>
    <!-- Resident Portal Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>

</body>
</html>

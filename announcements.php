<?php
// announcements.php - CSWDO Resident Announcements & Bulletins Hub
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Mark announcements as viewed for header notification badge
setcookie('last_viewed_announcements', (string)time(), time() + (86400 * 365), '/');
$_COOKIE['last_viewed_announcements'] = (string)time();

$database = new Database();
$db = $database->getConnection();

// Fetch current user data for sidebar
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name'] ?? 'User');
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = (!empty($profile_picture) && file_exists(__DIR__ . '/' . $profile_picture))
    ? $profile_picture
    : '';

// Fetch all announcements with author information
$query = "SELECT a.*, u.first_name, u.last_name, u.role 
          FROM announcements a 
          LEFT JOIN users u ON a.created_by = u.user_id 
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter announcements with valid images for the photo slideshow/carousel
$slideshow_items = [];
foreach ($announcements as $ann) {
    if (!empty($ann['image_path']) && file_exists(__DIR__ . '/' . $ann['image_path'])) {
        $slideshow_items[] = $ann;
    }
}

// Dark mode check
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements &amp; Bulletins - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <link rel="stylesheet" href="css/portal-motion.css?v=<?php echo filemtime(__DIR__ . '/css/portal-motion.css'); ?>">
    <style>
        /* ==========================================================================
           MODERN ANNOUNCEMENTS HUB STYLING (PALETTE: PASTEL GREEN, MAROON, KHAKI)
           ========================================================================== */
        :root {
            --ann-green-subtle: rgba(167, 215, 197, 0.22);
            --ann-maroon-subtle: rgba(127, 3, 3, 0.12);
            --ann-khaki-subtle: rgba(216, 186, 152, 0.25);
            --ann-accent-gold: #C9A86C;
        }

        .announcements-wrapper {
            max-width: 1360px;
            margin: 0 auto;
            padding-bottom: 60px;
        }

        /* Subheader Ribbon */
        .announcements-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 22px 28px;
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.95), rgba(9, 39, 33, 0.98));
            border-radius: 20px;
            color: #FFFFFF;
            box-shadow: 0 10px 30px rgba(15, 65, 74, 0.2);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(246, 216, 168, 0.25);
        }

        .announcements-hero-header::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(201, 168, 108, 0.25), transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-left h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #FFFFFF;
            letter-spacing: -0.02em;
        }

        .hero-left h1 i {
            color: #F6D8A8;
            font-size: 1.35rem;
        }

        .hero-left p {
            margin: 0;
            font-size: 0.88rem;
            color: rgba(255, 255, 255, 0.82);
            max-width: 600px;
            line-height: 1.5;
        }

        .hero-stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 18px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #FFFFFF;
        }

        .hero-stats-badge i {
            color: #A7D7C5;
        }

        /* ==========================================================================
           SLIDESHOW / CAROUSEL HERO (FEATURED ANNOUNCEMENTS WITH IMAGES)
           ========================================================================== */
        .featured-carousel-card {
            position: relative;
            background: var(--ref-card, #FFFFFF);
            border-radius: 22px;
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            box-shadow: 0 8px 28px rgba(15, 65, 74, 0.08);
            margin-bottom: 32px;
            overflow: hidden;
        }

        .carousel-badge-corner {
            position: absolute;
            top: 20px;
            left: 24px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(15, 65, 74, 0.88);
            backdrop-filter: blur(8px);
            border-radius: 999px;
            color: #F6D8A8;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 1px solid rgba(246, 216, 168, 0.35);
        }

        .carousel-container {
            position: relative;
            width: 100%;
            height: 420px;
            overflow: hidden;
        }

        .carousel-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(1.02);
            display: flex;
            align-items: stretch;
            background: linear-gradient(135deg, #0d383e 0%, #082420 100%);
        }

        .carousel-slide.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            z-index: 2;
        }

        /* CSWDO Palayan Logo Subtle Watermark (Req 4) */
        .slide-watermark-logo {
            position: absolute;
            right: 4%;
            top: 50%;
            transform: translateY(-50%);
            width: 320px;
            height: 320px;
            object-fit: contain;
            opacity: 0.08;
            filter: grayscale(100%) brightness(1.2);
            pointer-events: none;
            z-index: 1;
            transition: transform 6s ease, opacity 0.5s ease;
        }

        .carousel-slide.active .slide-watermark-logo {
            transform: translateY(-50%) scale(1.05);
            opacity: 0.11;
        }

        .slide-scrim {
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, 
                rgba(8, 36, 32, 0.95) 0%, 
                rgba(8, 36, 32, 0.85) 55%, 
                rgba(8, 36, 32, 0.4) 100%);
            z-index: 2;
        }

        .slide-content-wrap {
            position: relative;
            z-index: 3;
            max-width: 650px;
            padding: 56px 40px 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: #FFFFFF;
            height: 100%;
        }

        .slide-category-pill {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }

        .cat-pill-general { background: #2196F3; color: #FFFFFF; }
        .cat-pill-aics { background: #7F0303; color: #FFFFFF; }
        .cat-pill-housing { background: #009688; color: #FFFFFF; }
        .cat-pill-appointment { background: #FF9800; color: #FFFFFF; }

        .slide-title {
            font-size: 1.85rem;
            font-weight: 800;
            line-height: 1.25;
            color: #FFFFFF;
            margin: 0 0 12px 0;
            letter-spacing: -0.02em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .slide-text-excerpt {
            font-size: 0.92rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.88);
            margin: 0 0 22px 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-shadow: 0 1px 4px rgba(0,0,0,0.5);
        }

        .slide-meta-row {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .slide-meta-row span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .slide-meta-row i {
            color: #F6D8A8;
        }

        .slide-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-slide-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 24px;
            background: linear-gradient(135deg, #7F0303, #9A1515);
            color: #FFFFFF;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 16px rgba(127, 3, 3, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-slide-action:hover {
            background: linear-gradient(135deg, #9A1515, #B31E1E);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 3, 3, 0.45);
            color: #FFFFFF;
        }

        /* Carousel Navigation Controls */
        .carousel-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(15, 65, 74, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(6px);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 0.95rem;
        }

        .carousel-nav-btn:hover {
            background: #7F0303;
            transform: translateY(-50%) scale(1.1);
            color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        }

        .carousel-nav-btn.prev { left: 24px; }
        .carousel-nav-btn.next { right: 24px; }

        /* Carousel Indicators Bottom Bar */
        .carousel-indicators-bar {
            position: absolute;
            bottom: 20px;
            right: 28px;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(5, 26, 22, 0.65);
            padding: 6px 14px;
            border-radius: 999px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            width: 24px;
            background: #F6D8A8;
        }

        .carousel-counter-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.85);
            margin-left: 6px;
            letter-spacing: 0.05em;
        }

        .carousel-pause-toggle {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            cursor: pointer;
            padding: 2px 4px;
            margin-left: 4px;
            transition: color 0.2s;
        }

        .carousel-pause-toggle:hover {
            color: #FFFFFF;
        }

        /* ==========================================================================
           FILTER AND SEARCH TOOLBAR
           ========================================================================== */
        .announcements-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 12px 18px;
            background: var(--ref-card, #FFFFFF);
            border-radius: 18px;
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            box-shadow: var(--shadow-sm);
        }

        .filter-pills-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: var(--ref-canvas, #E4EFE7);
            color: var(--ref-text, #191D1E);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .filter-pill-btn:hover {
            background: rgba(127, 3, 3, 0.08);
            border-color: rgba(127, 3, 3, 0.2);
        }

        .filter-pill-btn.active {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
            box-shadow: 0 2px 8px rgba(127, 3, 3, 0.3);
        }

        .filter-pill-btn .pill-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.68rem;
            line-height: 1;
        }

        .filter-pill-btn:not(.active) .pill-badge {
            background: rgba(0, 0, 0, 0.08);
            color: var(--ref-text, #191D1E);
        }

        .search-box-wrap {
            position: relative;
            min-width: 260px;
            flex-grow: 1;
            max-width: 360px;
        }

        .search-box-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ref-muted, #71818B);
            font-size: 0.85rem;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 9px 36px 9px 38px;
            background: var(--ref-canvas, #E4EFE7);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            border-radius: 999px;
            font-size: 0.82rem;
            color: var(--ref-text, #191D1E);
            outline: none;
            transition: all 0.2s ease;
        }

        .search-box-wrap input:focus {
            border-color: #7F0303;
            background: var(--ref-card, #FFFFFF);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
        }

        .search-clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--ref-muted, #71818B);
            cursor: pointer;
            font-size: 0.8rem;
            display: none;
        }

        /* ==========================================================================
           ANNOUNCEMENT CARDS GRID
           ========================================================================== */
        .announcements-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 26px;
        }

        .ann-card {
            background: var(--ref-card, #FFFFFF);
            border-radius: 20px;
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .ann-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(15, 65, 74, 0.12);
            border-color: rgba(201, 168, 108, 0.4);
        }

        /* Card with Image */
        .ann-card-img-wrap {
            position: relative;
            height: 195px;
            width: 100%;
            overflow: hidden;
            background: #092721;
        }

        .ann-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.5s ease;
        }

        .ann-card:hover .ann-card-img-wrap img {
            transform: scale(1.06);
        }

        .card-img-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 2;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }

        /* Card without Image - High-end Typographic Design */
        .ann-card-no-img-header {
            position: relative;
            padding: 20px 22px 14px 22px;
            border-top: 5px solid #7F0303;
            background: linear-gradient(180deg, rgba(167, 215, 197, 0.15) 0%, transparent 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ann-card-no-img-header.cat-general { border-top-color: #2196F3; }
        .ann-card-no-img-header.cat-aics { border-top-color: #7F0303; }
        .ann-card-no-img-header.cat-housing { border-top-color: #009688; }
        .ann-card-no-img-header.cat-appointment { border-top-color: #FF9800; }

        .typographic-icon-tag {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            background: var(--ref-canvas, #E4EFE7);
            color: #7F0303;
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
        }

        .ann-card-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .ann-card-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.75rem;
            color: var(--ref-muted, #71818B);
            margin-bottom: 10px;
        }

        .ann-card-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ann-card-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--ref-text, #191D1E);
            line-height: 1.35;
            margin: 0 0 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ann-card-text {
            font-size: 0.85rem;
            color: var(--ref-muted, #71818B);
            line-height: 1.6;
            margin: 0 0 20px 0;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ann-card-footer {
            border-top: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            padding-top: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .ann-author-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--ref-muted, #71818B);
        }

        .ann-author-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0F414A, #1A5A66);
            color: #FFFFFF;
            font-size: 0.65rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .btn-view-details {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #7F0303;
            background: rgba(127, 3, 3, 0.08);
            border: 1px solid rgba(127, 3, 3, 0.18);
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .btn-view-details:hover {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
            transform: translateX(2px);
        }

        /* Empty State */
        .announcements-empty-state {
            grid-column: 1 / -1;
            background: var(--ref-card, #FFFFFF);
            border-radius: 20px;
            border: 1px dashed var(--ref-border, rgba(0,0,0,0.15));
            padding: 60px 24px;
            text-align: center;
        }

        .announcements-empty-state i {
            font-size: 3.5rem;
            color: var(--ref-muted, #71818B);
            opacity: 0.4;
            margin-bottom: 16px;
        }

        .announcements-empty-state h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--ref-text, #191D1E);
            margin: 0 0 8px 0;
        }

        .announcements-empty-state p {
            font-size: 0.88rem;
            color: var(--ref-muted, #71818B);
            margin: 0;
        }

        /* ==========================================================================
           FULL DETAILS INTERACTIVE MODAL
           ========================================================================== */
        .ann-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(5, 26, 22, 0.75);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ann-modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .ann-modal-container {
            background: var(--ref-card, #FFFFFF);
            border-radius: 24px;
            width: 100%;
            max-width: 760px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.1));
            transform: scale(0.95) translateY(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ann-modal-backdrop.active .ann-modal-container {
            transform: scale(1) translateY(0);
        }

        .ann-modal-header {
            padding: 20px 26px;
            border-bottom: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: var(--ref-canvas, #E4EFE7);
        }

        .modal-header-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--ref-card, #FFFFFF);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.1));
            color: var(--ref-text, #191D1E);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .btn-modal-close:hover {
            background: #7F0303;
            color: #FFFFFF;
            border-color: #7F0303;
            transform: rotate(90deg);
        }

        .ann-modal-scroll {
            padding: 26px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .modal-banner-img {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 22px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .modal-ann-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--ref-text, #191D1E);
            line-height: 1.3;
            margin: 0 0 14px 0;
            letter-spacing: -0.01em;
        }

        .modal-meta-row {
            display: flex;
            align-items: center;
            gap: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: var(--ref-muted, #71818B);
            flex-wrap: wrap;
        }

        .modal-meta-row span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .modal-meta-row i {
            color: #7F0303;
        }

        .modal-ann-body {
            font-size: 0.95rem;
            line-height: 1.75;
            color: var(--ref-text, #191D1E);
            margin-bottom: 24px;
            white-space: pre-line;
        }

        .modal-ann-notice-box {
            background: rgba(167, 215, 197, 0.25);
            border-left: 4px solid #0F414A;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.84rem;
            color: var(--ref-text, #191D1E);
        }

        .modal-ann-notice-box i {
            color: #0F414A;
            font-size: 1.1rem;
            margin-top: 2px;
        }

        .ann-modal-footer {
            padding: 16px 26px;
            border-top: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            background: var(--ref-canvas, #E4EFE7);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-modal-action-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            background: #7F0303;
            color: #FFFFFF;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-modal-action-link:hover {
            background: #9A1515;
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .btn-modal-dismiss {
            padding: 9px 20px;
            border-radius: 999px;
            background: var(--ref-card, #FFFFFF);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.12));
            color: var(--ref-text, #191D1E);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-dismiss:hover {
            background: rgba(0,0,0,0.05);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .carousel-container {
                height: 380px;
            }
            .slide-content-wrap {
                padding: 40px 24px 36px 24px;
            }
            .slide-title {
                font-size: 1.45rem;
            }
            .carousel-nav-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .announcements-hero-header {
                padding: 18px 20px;
            }
            .carousel-container {
                height: 360px;
            }
            .slide-title {
                font-size: 1.3rem;
            }
            .slide-text-excerpt {
                display: none;
            }
            .announcements-grid-container {
                grid-template-columns: 1fr;
            }
            .announcements-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .filter-categories-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 6px;
            }
            .search-box-wrap {
                max-width: 100%;
            }
            .official-spotlight-banner {
                grid-template-columns: 1fr !important;
                gap: 16px;
                padding: 18px 16px;
            }
            .ann-modal-container {
                max-width: 95vw;
                border-radius: 20px;
            }
            .ann-modal-header,
            .ann-modal-body-scroll,
            .ann-modal-footer {
                padding: 16px 18px;
            }
        }
    
/* Official Spotlight Announcement Banner */
.official-spotlight-banner {
    background: linear-gradient(135deg, #09231A 0%, #0F3B2E 50%, #081B15 100%);
    border-radius: 24px;
    border: 1.5px solid rgba(201, 168, 108, 0.45);
    padding: 24px;
    margin-bottom: 28px;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 28px;
    align-items: center;
    box-shadow: 0 14px 40px rgba(8, 27, 21, 0.25);
    position: relative;
    overflow: hidden;
}

.spotlight-banner-media {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    border: 2px solid rgba(201, 168, 108, 0.5);
    background: #000;
}

.spotlight-banner-img {
    width: 100%;
    height: auto;
    display: block;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.spotlight-banner-media:hover .spotlight-banner-img {
    transform: scale(1.03);
}

.spotlight-banner-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
    color: #FFFFFF;
}

.spotlight-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.spotlight-badge .badge-pulse {
    background: rgba(220, 38, 38, 0.25);
    border: 1px solid rgba(239, 68, 68, 0.6);
    color: #FCA5A5;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.spotlight-badge .badge-pulse i {
    font-size: 0.55rem;
    color: #EF4444;
    animation: pulseDot 1.4s infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.25); }
}

.spotlight-badge .badge-cat {
    background: rgba(201, 168, 108, 0.2);
    border: 1px solid rgba(201, 168, 108, 0.45);
    color: #E8D5A8;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.spotlight-title {
    font-size: clamp(1.3rem, 2.2vw, 1.75rem);
    font-weight: 800;
    color: #FFFFFF;
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.01em;
}

.spotlight-desc {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.55;
    margin: 0;
}

.spotlight-meta {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: #D8BA98;
    padding-top: 4px;
}

.spotlight-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.spotlight-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.btn-spotlight-primary {
    background: #7F0303;
    color: #FFFFFF !important;
    border: none;
    border-radius: 50px;
    padding: 10px 22px;
    font-size: 0.84rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(127, 3, 3, 0.35);
    transition: all 0.2s ease;
}

.btn-spotlight-primary:hover {
    background: #9A0404;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(127, 3, 3, 0.45);
}

.btn-spotlight-secondary {
    background: rgba(255, 255, 255, 0.12);
    color: #FFFFFF !important;
    border: 1.5px solid rgba(255, 255, 255, 0.3);
    border-radius: 50px;
    padding: 9px 20px;
    font-size: 0.84rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
}

.btn-spotlight-secondary:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #FFFFFF !important;
    transform: translateY(-2px);
}

/* Card Background using Dark Green Abstract Texture */
.ann-card {
    background: linear-gradient(180deg, rgba(12, 38, 30, 0.88) 0%, rgba(7, 24, 19, 0.94) 100%), url('images/announcements/announcement_card_bg.jpg') center/cover no-repeat !important;
    border-radius: 20px !important;
    border: 1.5px solid rgba(201, 168, 108, 0.35) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18) !important;
    color: #FFFFFF !important;
}

.ann-card:hover {
    transform: translateY(-6px) !important;
    border-color: rgba(229, 179, 56, 0.7) !important;
    box-shadow: 0 16px 36px rgba(12, 38, 30, 0.35) !important;
}

.ann-card .ann-card-title {
    color: #FFFFFF !important;
}

.ann-card .ann-card-text {
    color: rgba(255, 255, 255, 0.82) !important;
}

.ann-card .ann-card-meta span {
    color: #E8D5A8 !important;
}

.ann-card .ann-card-author-name {
    color: rgba(255, 255, 255, 0.9) !important;
}

.ann-card .ann-card-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
}

.ann-card .btn-read-more {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.ann-card .btn-read-more:hover {
    background: #FFFFFF !important;
    color: #0F414A !important;
}

@media (max-width: 900px) {
    .official-spotlight-banner {
        grid-template-columns: 1fr;
        gap: 20px;
        padding: 18px;
    }
    .spotlight-banner-media {
        max-width: 380px;
        margin: 0 auto;
    }
}

</style>
</head>

<body>
    <div class="app-container">
        <!-- Unified Resident Portal Sidebar -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Unified Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <div class="announcements-wrapper">
                <!-- Universal Top Hero Banner -->
                <?php
                $banner_title = 'CSWDO Announcements & Updates';
                $banner_subtitle = 'Official notices, claiming schedules, financial relief, and welfare bulletins for Palayan City';
                $banner_icon = 'fa-bullhorn';
                $banner_actions = [
                    ['label' => count($announcements) . ' Active Bulletins', 'url' => '#announcementsGrid', 'icon' => 'fa-newspaper', 'type' => 'secondary']
                ];
                include __DIR__ . '/includes/portal-banner.php';
                ?>

                <!-- ========================================================
                     OFFICIAL SPOTLIGHT ANNOUNCEMENT BANNER
                     ======================================================== -->
                <section class="official-spotlight-banner">
                    <div class="spotlight-banner-media">
                        <img src="images/announcements/announcement_main_banner.jpg" alt="Official CSWDO Announcement - New Date" class="spotlight-banner-img">
                    </div>
                    <div class="spotlight-banner-content">
                        <div class="spotlight-badge">
                            <span class="badge-pulse"><i class="fas fa-circle"></i> Live Official Advisory</span>
                            <span class="badge-cat"><i class="fas fa-bullhorn"></i> CSWDO Broadcast</span>
                        </div>
                        <h2 class="spotlight-title">Schedule Adjustment &amp; New Date Announcement</h2>
                        <p class="spotlight-desc">City Social Welfare and Development Office (CSWDO) - Palayan City official public advisory regarding updated service schedules, beneficiary aid distributions, and office appointments.</p>
                        <div class="spotlight-meta">
                            <span><i class="far fa-calendar-check"></i> Effective Immediately</span>
                            <span><i class="fas fa-building-columns"></i> CSWDO Operations</span>
                            <span><i class="fas fa-users"></i> All Palayan Residents</span>
                        </div>
                        <div class="spotlight-actions">
                            <a href="#announcementsGrid" class="btn-spotlight-primary">
                                <i class="fas fa-newspaper"></i> View All Announcements Below
                            </a>
                            <a href="aics.php" class="btn-spotlight-secondary">
                                <i class="fas fa-hand-holding-heart"></i> Explore AICS Programs
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Announcements Navigation Anchor -->
                <div id="bulletinsAnchor"></div>

                <!-- ========================================================
                     INTERACTIVE FILTER & SEARCH TOOLBAR
                     ======================================================== -->
                <div class="announcements-toolbar">
                    <div class="filter-pills-group" id="categoryFilterGroup">
                        <button type="button" class="filter-pill-btn active" data-category="all">
                            <span>All Bulletins</span>
                            <span class="pill-badge"><?php echo count($announcements); ?></span>
                        </button>
                        <button type="button" class="filter-pill-btn" data-category="aics">
                            <i class="fas fa-hand-holding-heart" style="color: #7F0303;"></i>
                            <span>AICS Assistance</span>
                        </button>
                        <button type="button" class="filter-pill-btn" data-category="housing">
                            <i class="fas fa-house-chimney" style="color: #009688;"></i>
                            <span>Housing</span>
                        </button>
                        <button type="button" class="filter-pill-btn" data-category="appointment">
                            <i class="fas fa-calendar-check" style="color: #FF9800;"></i>
                            <span>Appointments</span>
                        </button>
                        <button type="button" class="filter-pill-btn" data-category="general">
                            <i class="fas fa-info-circle" style="color: #2196F3;"></i>
                            <span>General</span>
                        </button>
                    </div>

                    <div class="search-box-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="announcementSearchInput" placeholder="Search announcements by keyword..." autocomplete="off">
                        <button type="button" class="search-clear-btn" id="searchClearBtn" title="Clear search"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <!-- ========================================================
                     ANNOUNCEMENT CARDS GRID (WITH & WITHOUT IMAGES)
                     ======================================================== -->
                <div class="announcements-grid-container" id="announcementsGrid">
                    <?php if (empty($announcements)): ?>
                        <div class="announcements-empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h3>No Announcements Available</h3>
                            <p>There are no announcements published at the moment. Please check back soon for updates from CSWDO.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $item): 
                            $has_image = !empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path']);
                            $cat_key = strtolower($item['category'] ?? 'general');
                            $cat_label = 'General Notice';
                            $cat_icon = 'fa-info-circle';
                            if ($cat_key === 'aics') {
                                $cat_label = 'AICS Assistance';
                                $cat_icon = 'fa-hand-holding-heart';
                            } elseif ($cat_key === 'housing') {
                                $cat_label = 'Housing Program';
                                $cat_icon = 'fa-house-chimney';
                            } elseif ($cat_key === 'appointment') {
                                $cat_label = 'Appointments';
                                $cat_icon = 'fa-calendar-check';
                            }

                            $author_name = (!empty($item['first_name']) && !empty($item['last_name']))
                                ? $item['first_name'] . ' ' . $item['last_name']
                                : 'CSWDO Administrator';

                            $author_initials = '';
                            $name_parts = explode(' ', $author_name);
                            foreach ($name_parts as $part) {
                                $author_initials .= strtoupper(substr($part, 0, 1));
                            }
                            $author_initials = substr($author_initials, 0, 2);
                        ?>
                            <article class="ann-card" 
                                     data-category="<?php echo htmlspecialchars($cat_key); ?>"
                                     data-title="<?php echo htmlspecialchars(strtolower($item['title'])); ?>"
                                     data-content="<?php echo htmlspecialchars(strtolower($item['content'])); ?>">
                                
                                <?php if ($has_image): ?>
                                    <!-- Card WITH Uploaded Image -->
                                    <div class="ann-card-img-wrap">
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             loading="lazy">
                                        <span class="card-img-badge cat-pill-<?php echo $cat_key; ?>">
                                            <?php echo htmlspecialchars($cat_label); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <!-- Card WITHOUT Image (Clean Typographic Header - NO broken image placeholder) -->
                                    <div class="ann-card-no-img-header cat-<?php echo $cat_key; ?>">
                                        <span class="slide-category-pill cat-pill-<?php echo $cat_key; ?>" style="margin: 0;">
                                            <?php echo htmlspecialchars($cat_label); ?>
                                        </span>
                                        <div class="typographic-icon-tag">
                                            <i class="fas <?php echo $cat_icon; ?>"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="ann-card-body">
                                    <div class="ann-card-meta">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                                        <span><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($item['created_at'])); ?></span>
                                    </div>

                                    <h3 class="ann-card-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <p class="ann-card-text"><?php echo htmlspecialchars($item['content']); ?></p>

                                    <div class="ann-card-footer">
                                        <div class="ann-author-info">
                                            <div class="ann-author-avatar"><?php echo htmlspecialchars($author_initials); ?></div>
                                            <span><?php echo htmlspecialchars($author_name); ?></span>
                                        </div>
                                        <button type="button" class="btn-view-details" 
                                                onclick="openAnnouncementModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)">
                                            View Details <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         DEDICATED FULL DETAILS MODAL
         ======================================================== -->
    <div class="ann-modal-backdrop" id="announcementModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="ann-modal-container">
            <div class="ann-modal-header">
                <div class="modal-header-meta">
                    <span id="modalCategoryBadge" class="slide-category-pill cat-pill-general">General Notice</span>
                    <span style="font-size: 0.76rem; color: var(--ref-muted, #71818B);"><i class="fas fa-city"></i> CSWDO Official Notice</span>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeAnnouncementModal()" aria-label="Close Announcement Details">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="ann-modal-scroll">
                <img id="modalBannerImage" src="" alt="Announcement media" class="modal-banner-img" style="display: none;">

                <h2 id="modalTitle" class="modal-ann-title">Announcement Title</h2>

                <div class="modal-meta-row">
                    <span><i class="far fa-calendar-alt"></i> Published: <strong id="modalDate">---</strong></span>
                    <span><i class="far fa-clock"></i> Time: <strong id="modalTime">---</strong></span>
                    <span><i class="fas fa-user-shield"></i> Author: <strong id="modalAuthor">CSWDO Staff</strong></span>
                </div>

                <div class="modal-ann-notice-box" id="modalNoticeBox" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Action Notice:</strong> <span id="modalNoticeText"></span>
                    </div>
                </div>

                <div id="modalContent" class="modal-ann-body">
                    Announcement content goes here.
                </div>
            </div>

            <div class="ann-modal-footer">
                <div id="modalActionContainer">
                    <!-- Dynamic Service Link (AICS, Housing, etc.) -->
                </div>
                <button type="button" class="btn-modal-dismiss" onclick="closeAnnouncementModal()">
                    Close Bulletin
                </button>
            </div>
        </div>
    </div>

    <!-- Search Modal Included -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>

    <script>
        // ==========================================================================
        // CAROUSEL LOGIC WITH AUTOPLAY, PAUSE ON HOVER, AND PREV/NEXT
        // ==========================================================================
        const carousel = document.getElementById('announcementsCarousel');
        const slides = carousel ? carousel.querySelectorAll('.carousel-slide') : [];
        const dots = carousel ? carousel.querySelectorAll('.carousel-dot') : [];
        const prevBtn = document.getElementById('carouselPrevBtn');
        const nextBtn = document.getElementById('carouselNextBtn');
        const counterLabel = document.getElementById('carouselCounter');
        const pauseToggle = document.getElementById('carouselPauseToggle');

        let currentSlideIdx = 0;
        let slideInterval = null;
        let isPaused = false;
        const totalSlides = slides.length;

        function showSlide(index) {
            if (totalSlides === 0) return;
            currentSlideIdx = (index + totalSlides) % totalSlides;

            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === currentSlideIdx);
            });

            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === currentSlideIdx);
            });

            if (counterLabel) {
                counterLabel.textContent = `${currentSlideIdx + 1} / ${totalSlides}`;
            }
        }

        function nextSlide() {
            showSlide(currentSlideIdx + 1);
        }

        function prevSlide() {
            showSlide(currentSlideIdx - 1);
        }

        function startAutoplay() {
            if (totalSlides <= 1) return;
            stopAutoplay();
            slideInterval = setInterval(() => {
                if (!isPaused) {
                    nextSlide();
                }
            }, 6000);
        }

        function stopAutoplay() {
            if (slideInterval) {
                clearInterval(slideInterval);
                slideInterval = null;
            }
        }

        if (carousel && totalSlides > 1) {
            if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); startAutoplay(); });
            if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); startAutoplay(); });

            dots.forEach((dot, idx) => {
                dot.addEventListener('click', () => {
                    showSlide(idx);
                    startAutoplay();
                });
            });

            // Pause on hover
            carousel.addEventListener('mouseenter', () => { isPaused = true; });
            carousel.addEventListener('mouseleave', () => { 
                const pauseIcon = pauseToggle ? pauseToggle.querySelector('i') : null;
                if (!pauseIcon || pauseIcon.classList.contains('fa-pause')) {
                    isPaused = false; 
                }
            });

            // Pause/Play Toggle Button
            if (pauseToggle) {
                pauseToggle.addEventListener('click', () => {
                    const icon = pauseToggle.querySelector('i');
                    if (isPaused) {
                        isPaused = false;
                        if (icon) icon.className = 'fas fa-pause';
                        pauseToggle.title = 'Pause Slideshow';
                    } else {
                        isPaused = true;
                        if (icon) icon.className = 'fas fa-play';
                        pauseToggle.title = 'Play Slideshow';
                    }
                });
            }

            startAutoplay();
        }

        // ==========================================================================
        // FILTER & SEARCH LOGIC
        // ==========================================================================
        const filterBtns = document.querySelectorAll('#categoryFilterGroup .filter-pill-btn');
        const searchInput = document.getElementById('announcementSearchInput');
        const searchClearBtn = document.getElementById('searchClearBtn');
        const cards = document.querySelectorAll('#announcementsGrid .ann-card');

        let activeCategory = 'all';
        let searchQuery = '';

        function filterAnnouncements() {
            let visibleCount = 0;

            cards.forEach(card => {
                const cardCat = card.getAttribute('data-category') || 'general';
                const cardTitle = card.getAttribute('data-title') || '';
                const cardContent = card.getAttribute('data-content') || '';

                const matchesCat = (activeCategory === 'all' || cardCat === activeCategory);
                const matchesSearch = (!searchQuery || cardTitle.includes(searchQuery) || cardContent.includes(searchQuery));

                if (matchesCat && matchesSearch) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            let noResultMsg = document.getElementById('filterNoResultState');
            if (visibleCount === 0 && cards.length > 0) {
                if (!noResultMsg) {
                    noResultMsg = document.createElement('div');
                    noResultMsg.id = 'filterNoResultState';
                    noResultMsg.className = 'announcements-empty-state';
                    noResultMsg.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>No Matching Bulletins</h3>
                        <p>No announcements match your selected filter or search term. Try adjusting your search query.</p>
                    `;
                    document.getElementById('announcementsGrid').appendChild(noResultMsg);
                }
                noResultMsg.style.display = 'block';
            } else if (noResultMsg) {
                noResultMsg.style.display = 'none';
            }
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeCategory = this.getAttribute('data-category');
                filterAnnouncements();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                searchQuery = this.value.trim().toLowerCase();
                if (searchClearBtn) {
                    searchClearBtn.style.display = searchQuery ? 'block' : 'none';
                }
                filterAnnouncements();
            });
        }

        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', function() {
                if (searchInput) {
                    searchInput.value = '';
                    searchQuery = '';
                    this.style.display = 'none';
                    searchInput.focus();
                    filterAnnouncements();
                }
            });
        }

        // ==========================================================================
        // DEDICATED ANNOUNCEMENT DETAILS MODAL
        // ==========================================================================
        const modalBackdrop = document.getElementById('announcementModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalDate = document.getElementById('modalDate');
        const modalTime = document.getElementById('modalTime');
        const modalAuthor = document.getElementById('modalAuthor');
        const modalContent = document.getElementById('modalContent');
        const modalCategoryBadge = document.getElementById('modalCategoryBadge');
        const modalBannerImage = document.getElementById('modalBannerImage');
        const modalNoticeBox = document.getElementById('modalNoticeBox');
        const modalNoticeText = document.getElementById('modalNoticeText');
        const modalActionContainer = document.getElementById('modalActionContainer');

        function openAnnouncementModal(data) {
            if (!data) return;

            const title = data.title || 'Official Announcement';
            const content = data.content || '';
            const category = (data.category || 'general').toLowerCase();
            const createdAt = data.created_at ? new Date(data.created_at.replace(/-/g, '/')) : new Date();
            const author = (data.first_name && data.last_name) ? `${data.first_name} ${data.last_name}` : 'CSWDO Administrator';
            const imgPath = data.image_path || '';

            modalTitle.textContent = title;
            modalContent.textContent = content;
            modalAuthor.textContent = author;

            modalDate.textContent = createdAt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            modalTime.textContent = createdAt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

            // Category Styling
            let catLabel = 'General Notice';
            modalCategoryBadge.className = 'slide-category-pill';
            if (category === 'aics') {
                catLabel = 'AICS Assistance';
                modalCategoryBadge.classList.add('cat-pill-aics');
            } else if (category === 'housing') {
                catLabel = 'Housing Program';
                modalCategoryBadge.classList.add('cat-pill-housing');
            } else if (category === 'appointment') {
                catLabel = 'Appointments';
                modalCategoryBadge.classList.add('cat-pill-appointment');
            } else {
                modalCategoryBadge.classList.add('cat-pill-general');
            }
            modalCategoryBadge.textContent = catLabel;

            // Image handling (Clean, NO broken image)
            if (imgPath) {
                modalBannerImage.src = imgPath;
                modalBannerImage.style.display = 'block';
            } else {
                modalBannerImage.src = '';
                modalBannerImage.style.display = 'none';
            }

            // Contextual Action Notice Box
            if (category === 'aics') {
                modalNoticeBox.style.display = 'flex';
                modalNoticeText.textContent = 'This update is related to the Assistance to Individuals in Crisis Situations (AICS). Please prepare the required documents if applying for relief.';
                modalActionContainer.innerHTML = `
                    <a href="apply-aics.php" class="btn-modal-action-link">
                        <i class="fas fa-hand-holding-heart"></i> Apply for AICS Assistance
                    </a>
                `;
            } else if (category === 'housing') {
                modalNoticeBox.style.display = 'flex';
                modalNoticeText.textContent = 'This update concerns city socialized housing programs. Qualified beneficiaries may verify their application status online.';
                modalActionContainer.innerHTML = `
                    <a href="housing-programs.php" class="btn-modal-action-link">
                        <i class="fas fa-house-chimney"></i> View Housing Programs
                    </a>
                `;
            } else {
                modalNoticeBox.style.display = 'none';
                modalActionContainer.innerHTML = '';
            }

            // Open Modal
            modalBackdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAnnouncementModal() {
            modalBackdrop.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close on backdrop click
        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAnnouncementModal();
                }
            });
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalBackdrop.classList.contains('active')) {
                closeAnnouncementModal();
            }
        });
    </script>
</body>
</html>

<?php
// includes/portal-header.php - Unified Resident Portal Header
if (!isset($portal_current_page)) {
    $portal_current_page = basename($_SERVER['PHP_SELF']);
}

// Ensure counts and user data are available
if (!isset($db)) {
    if (class_exists('Database')) {
        $database = new Database();
        $db = $database->getConnection();
    }
}

if (!isset($counts['unread_notifications']) && isset($db) && isset($_SESSION['user_id'])) {
    try {
        $notif_count_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
        $notif_count_stmt->bindParam(':uid', $_SESSION['user_id']);
        $notif_count_stmt->execute();
        $counts['unread_notifications'] = (int)$notif_count_stmt->fetchColumn();
    } catch (Exception $e) {
        $counts['unread_notifications'] = 0;
    }
}

// Fetch latest unread notification for resident bubble alert (Item 2)
$latest_notif = null;
if (isset($db) && isset($_SESSION['user_id'])) {
    try {
        $ln_stmt = $db->prepare("SELECT notification_id, title, message, created_at, is_read, type 
                                 FROM notifications 
                                 WHERE user_id = :uid AND (is_read = 0 OR is_read = FALSE)
                                 ORDER BY created_at DESC 
                                 LIMIT 1");
        $ln_stmt->execute([':uid' => $_SESSION['user_id']]);
        $latest_notif = $ln_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Unread announcements check (Item 4)
if (!isset($counts['unread_announcements']) && isset($db)) {
    try {
        $last_ann_ts = isset($_COOKIE['last_viewed_announcements']) ? (int)$_COOKIE['last_viewed_announcements'] : 0;
        if ($portal_current_page === 'announcements.php') {
            $counts['unread_announcements'] = 0;
        } else if ($last_ann_ts > 0) {
            $ann_c_stmt = $db->prepare("SELECT COUNT(*) FROM announcements WHERE UNIX_TIMESTAMP(created_at) > :last_view");
            $ann_c_stmt->execute([':last_view' => $last_ann_ts]);
            $counts['unread_announcements'] = (int)$ann_c_stmt->fetchColumn();
        } else {
            // First time or no cookie: count announcements within last 14 days
            $ann_c_stmt = $db->query("SELECT COUNT(*) FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
            $counts['unread_announcements'] = (int)$ann_c_stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $counts['unread_announcements'] = 0;
    }
}

if (empty($avatar_url)) {
    $avatar_url = '';
    $raw_pic = '';

    if (isset($user['profile_picture']) && !empty($user['profile_picture'])) {
        $raw_pic = $user['profile_picture'];
    } elseif (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
        $raw_pic = $_SESSION['profile_picture'];
    } elseif (isset($_SESSION['user_id']) && isset($db)) {
        try {
            $u_stmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = :uid LIMIT 1");
            $u_stmt->execute([':uid' => $_SESSION['user_id']]);
            $raw_pic = (string)$u_stmt->fetchColumn();
            if (!empty($raw_pic)) {
                $_SESSION['profile_picture'] = $raw_pic;
            }
        } catch (Exception $e) {}
    }

    if (!empty($raw_pic)) {
        $clean_pic = ltrim($raw_pic, '/\\');
        $root_dir = dirname(__DIR__);
        if (file_exists($root_dir . '/' . $clean_pic) || file_exists($clean_pic)) {
            $avatar_url = $clean_pic;
        } else {
            $avatar_url = $clean_pic;
        }
    }
}

if (!isset($initials)) {
    $initials = '';
    $name_parts = explode(' ', $_SESSION['full_name'] ?? 'User');
    foreach ($name_parts as $p) {
        $initials .= strtoupper(substr($p, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
include_once __DIR__ . '/pwa-meta.php';
?>
<style>
/* ========== UNIFIED PORTAL HEADER STYLES ========== */
.portal-header-bar {
    background: var(--ref-card, #FFFFFF);
    border-radius: 26px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.06));
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    margin-bottom: 20px;
    width: 100%;
}

[data-theme="dark"] .portal-header-bar {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}

/* Left Area: Red Menu / Home Buttons */
.portal-header-left {
    display: flex;
    align-items: center;
    justify-content: center;
}

.portal-menu-btn-red {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: #7F0303;
    border: none;
    color: #FFFFFF;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.35);
    margin-top: 3px;
    transition: all 0.22s ease;
    text-decoration: none;
    flex-shrink: 0;
}

.portal-menu-btn-red:hover {
    background: #9A1515;
    transform: translateY(1px) scale(1.03);
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.45);
    color: #FFFFFF;
}

.portal-menu-toggle-btn {
    display: none;
}

/* Center Nav Pills (Dashboard | Announcement | Documents | Appointments) */
.portal-header-center-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--ref-canvas, #E4EFE7);
    padding: 5px 8px;
    border-radius: 30px;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.05));
}

[data-theme="dark"] .portal-header-center-nav {
    background: #0D1619;
    border-color: rgba(255, 255, 255, 0.06);
}

.portal-hdr-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 24px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--ref-muted, #71818B);
    transition: all 0.22s ease;
}

.portal-hdr-pill:hover {
    color: #7F0303;
    background: rgba(127, 3, 3, 0.08);
}

[data-theme="dark"] .portal-hdr-pill:hover {
    color: #FCA5A5;
    background: rgba(220, 38, 38, 0.15);
}

.portal-hdr-pill.active {
    background: #7F0303;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.3);
}

[data-theme="dark"] .portal-hdr-pill.active {
    background: #9A1515;
    color: #FFFFFF;
}

/* Right Controls Area (Search | Bell | Theme | Profile) */
.portal-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.portal-hdr-ctrl {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--ref-canvas, #E4EFE7);
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.05));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--ref-text, #192126);
    cursor: pointer;
    font-size: 0.92rem;
    text-decoration: none;
    position: relative;
    transition: all 0.2s ease;
}

[data-theme="dark"] .portal-hdr-ctrl {
    background: #0D1619;
    color: #E5EFF2;
    border-color: rgba(255, 255, 255, 0.06);
}

.portal-hdr-ctrl:hover {
    transform: scale(1.06);
    color: #7F0303;
}

[data-theme="dark"] .portal-hdr-ctrl:hover {
    color: #F87171;
}

.portal-notif-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background: #DC2626;
    color: #FFFFFF;
    font-size: 0.62rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--ref-card, #FFFFFF);
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.4);
}

.portal-pill-badge-red {
    background: #DC2626;
    color: #FFFFFF;
    font-size: 0.62rem;
    font-weight: 800;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    margin-left: 4px;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.35);
}

/* Profile Avatar & Dropdown Menu */
.portal-avatar-dropdown-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.portal-hdr-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    overflow: hidden;
    background: #FDE68A;
    border: 2px solid #E5B338;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #7F0303;
    font-weight: 800;
    font-size: 0.9rem;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease;
    flex-shrink: 0;
    cursor: pointer;
    padding: 0;
}

.portal-hdr-avatar:hover {
    transform: scale(1.06);
}

.portal-hdr-avatar img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}

.portal-avatar-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 220px;
    background: var(--ref-card, #FFFFFF);
    border: 1.5px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    border-radius: 18px;
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.16);
    padding: 8px;
    z-index: 100060;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(0.96);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-avatar-menu.open {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) scale(1) !important;
}

[data-theme="dark"] .portal-avatar-menu {
    background: #1A242B;
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.55);
}

.pam-user-header {
    padding: 8px 10px 6px;
}

.pam-user-name {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--ref-text, #192126);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pam-user-role {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--ref-muted, #71818B);
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 2px;
}

.pam-divider {
    height: 1px;
    background: var(--ref-border, rgba(0, 0, 0, 0.06));
    margin: 5px 0;
}

[data-theme="dark"] .pam-divider {
    background: rgba(255, 255, 255, 0.08);
}

.pam-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ref-text, #192126);
    text-decoration: none;
    transition: all 0.18s ease;
}

.pam-item:hover {
    background: rgba(127, 3, 3, 0.08);
    color: #7F0303;
}

[data-theme="dark"] .pam-item {
    color: #E5EFF2;
}

[data-theme="dark"] .pam-item:hover {
    background: rgba(248, 113, 113, 0.15);
    color: #FCA5A5;
}

.pam-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #7F0303;
    flex-shrink: 0;
}

[data-theme="dark"] .pam-icon {
    color: #E5B338;
}

.pam-logout {
    color: #DC2626 !important;
}

.pam-logout .pam-icon {
    color: #DC2626 !important;
}

.pam-logout:hover {
    background: rgba(220, 38, 38, 0.1) !important;
    color: #B91C1C !important;
}

@media (max-width: 992px) {
    .portal-header-left {
        gap: 8px;
        justify-content: flex-start;
    }
    .portal-menu-toggle-btn {
        display: inline-flex !important;
    }
}

@media (max-width: 860px) {
    .portal-header-center-nav {
        display: none !important;
    }
}

@media (max-width: 600px) {
    .portal-header-bar {
        padding: 8px 12px;
        border-radius: 18px;
        margin-bottom: 14px;
        gap: 8px;
    }
    .portal-menu-btn-red {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        font-size: 0.95rem;
    }
    .portal-header-right {
        gap: 6px;
    }
    .portal-hdr-ctrl {
        width: 36px;
        height: 36px;
        font-size: 0.85rem;
    }
    .portal-hdr-avatar {
        width: 36px !important;
        height: 36px !important;
        font-size: 0.82rem !important;
    }
    .portal-notif-speech-bubble {
        right: -10px !important;
        min-width: min(280px, calc(100vw - 32px)) !important;
        max-width: calc(100vw - 28px) !important;
        padding: 8px 10px !important;
    }
    .portal-notif-speech-bubble .bubble-arrow-up {
        right: 18px !important;
    }
}

@media (max-width: 420px) {
    .portal-home-nav-btn {
        display: none !important;
    }
}

/* ========== NOTIFICATION SPEECH BUBBLE (Popup under Bell) ========== */
.portal-notif-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.portal-notif-speech-bubble {
    position: absolute;
    top: calc(100% + 14px);
    right: -8px;
    z-index: 100050;
    min-width: 290px;
    max-width: 360px;
    background: #FFFFFF;
    border: 1.5px solid rgba(127, 3, 3, 0.22);
    border-radius: 14px;
    padding: 10px 14px;
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.18), 0 3px 8px rgba(0, 0, 0, 0.08);
    pointer-events: auto;
    animation: bubblePopIn 0.38s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    transform-origin: top right;
}

[data-theme="dark"] .portal-notif-speech-bubble {
    background: #1E293B;
    border-color: rgba(248, 113, 113, 0.3);
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.5);
    color: #F8FAFC;
}

/* Upward pointing arrow centered with bell */
.portal-notif-speech-bubble .bubble-arrow-up {
    position: absolute;
    top: -9px;
    right: 20px;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 9px solid #FFFFFF;
    filter: drop-shadow(0 -2px 1px rgba(127, 3, 3, 0.15));
}

[data-theme="dark"] .portal-notif-speech-bubble .bubble-arrow-up {
    border-bottom-color: #1E293B;
    filter: drop-shadow(0 -2px 1px rgba(0, 0, 0, 0.3));
}

.bubble-content-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bubble-icon-pill {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(127, 3, 3, 0.1);
    color: #7F0303;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}
[data-theme="dark"] .bubble-icon-pill {
    background: rgba(248, 113, 113, 0.18);
    color: #F87171;
}

.bubble-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.bubble-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #0F172A;
    line-height: 1.25;
}
[data-theme="dark"] .bubble-title {
    color: #F8FAFC;
}

.bubble-desc {
    font-size: 0.74rem;
    color: #475569;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
[data-theme="dark"] .bubble-desc {
    color: #94A3B8;
}

.bubble-close-btn {
    background: none;
    border: none;
    color: #94A3B8;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 3px;
    border-radius: 4px;
    transition: color 0.2s;
    flex-shrink: 0;
}
.bubble-close-btn:hover {
    color: #7F0303;
}
[data-theme="dark"] .bubble-close-btn:hover {
    color: #F87171;
}

@keyframes bubblePopIn {
    0% {
        opacity: 0;
        transform: translateY(-8px) scale(0.9);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes bubbleFadeOut {
    0% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-8px) scale(0.9);
    }
}

.portal-notif-speech-bubble.fade-out {
    animation: bubbleFadeOut 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
<link rel="stylesheet" href="css/portal-motion.css?v=<?php echo file_exists(__DIR__ . '/../css/portal-motion.css') ? filemtime(__DIR__ . '/../css/portal-motion.css') : time(); ?>">

<!-- Unified Top Loading Bar for Page Transitions -->
<div class="portal-top-loader" id="portalTopLoader"></div>

<!-- Unified Portal Header Bar -->
<header class="portal-header-bar" role="banner">
    <!-- Left: Navigation Toggle (on mobile) & Home Button -->
    <div class="portal-header-left">
        <button type="button" class="portal-menu-btn-red portal-menu-toggle-btn" id="portalMenuToggleBtn" aria-label="Open Navigation Menu" title="Open Menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="index.php" class="portal-menu-btn-red portal-home-nav-btn" id="portalHomeBtn" aria-label="Home" title="Home">
            <i class="fas fa-home"></i>
        </a>
    </div>

    <!-- Center: Dedicated Navigation (Dashboard | Announcement | AICS Requests | Appointments) -->
    <nav class="portal-header-center-nav" aria-label="Portal Navigation">
        <div class="portal-header-sliding-pill"></div>
        <a href="dashboard.php" class="portal-hdr-pill <?php echo $portal_current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="announcements.php" id="residentAnnLink" class="portal-hdr-pill ann-hdr-link <?php echo in_array($portal_current_page, ['announcements.php', 'announcement.php'], true) ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcement</span>
            <?php if (!empty($counts['unread_announcements']) && !in_array($portal_current_page, ['announcements.php', 'announcement.php'], true)): ?>
                <span class="portal-pill-badge-red" id="residentAnnBadge"><?php echo $counts['unread_announcements']; ?></span>
            <?php endif; ?>
        </a>
        <a href="aics-requests.php" class="portal-hdr-pill <?php echo (in_array($portal_current_page, ['aics-requests.php', 'my-aics.php', 'track-aics.php'], true) || ($portal_current_page === 'track-request.php' && (empty($_GET['type']) || $_GET['type'] !== 'housing'))) ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding-heart"></i>
            <span>AICS Requests</span>
        </a>
        <a href="my-appointments.php" class="portal-hdr-pill <?php echo in_array($portal_current_page, ['my-appointments.php', 'view-appointment.php'], true) ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>My Appointments</span>
        </a>
    </nav>

    <!-- Right: Search | Notification Bell 🔔 | Night/Day Theme | Simple Avatar -->
    <div class="portal-header-right">
        <!-- Integrated Dashboard Search -->
        <button type="button" class="portal-hdr-ctrl" id="openPortalSearchBtn" title="Search Services &amp; Records" aria-label="Search">
            <i class="fas fa-search"></i>
        </button>

        <!-- Notification Bell 🔔 with Speech Bubble Popup -->
        <div class="portal-notif-wrapper">
            <a href="notifications.php" class="portal-hdr-ctrl" id="portalNotifBell" title="Notifications" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (($counts['unread_notifications'] ?? 0) > 0): ?>
                    <span class="portal-notif-dot"><?php echo $counts['unread_notifications']; ?></span>
                <?php endif; ?>
            </a>

            <?php if (!empty($latest_notif)): ?>
                <!-- Speech Bubble Popup with Arrow on Top (Exact match to requested UI) -->
                <div class="portal-notif-speech-bubble" id="portalNotifBubble" data-notif-id="<?php echo (int)$latest_notif['notification_id']; ?>" style="display: none;" role="status" aria-live="polite">
                    <div class="bubble-arrow-up"></div>
                    <div class="bubble-content-wrap">
                        <span class="bubble-icon-pill">
                            <i class="fas fa-bell"></i>
                        </span>
                        <div class="bubble-text" onclick="window.location.href='notifications.php'" style="cursor: pointer;">
                            <span class="bubble-title"><?php echo htmlspecialchars($latest_notif['title'] ?? 'Notification'); ?></span>
                            <span class="bubble-desc"><?php echo htmlspecialchars(mb_strimwidth($latest_notif['message'] ?? '', 0, 75, '...')); ?></span>
                        </div>
                        <button type="button" class="bubble-close-btn" aria-label="Dismiss notification bubble" onclick="dismissNotifBubble(event)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Night/Day Mode Toggle -->
        <button type="button" class="portal-hdr-ctrl" id="themeToggle" title="Toggle Night/Day Mode" aria-label="Toggle Theme">
            <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
        </button>

        <!-- Interactive Profile Avatar Dropdown -->
        <div class="portal-avatar-dropdown-wrap" id="portalAvatarDropdownWrap">
            <button type="button" class="portal-hdr-avatar" id="portalAvatarBtn" aria-label="Open Profile Menu" aria-expanded="false" title="Account Menu (<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>)">
                <?php if (!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Resident Avatar" onerror="this.onerror=null;this.parentElement.innerHTML='<span><?php echo htmlspecialchars($initials); ?></span>';">
                <?php else: ?>
                    <span><?php echo htmlspecialchars($initials); ?></span>
                <?php endif; ?>
            </button>
            <div class="portal-avatar-menu" id="portalAvatarMenu" role="menu">
                <div class="pam-user-header">
                    <div class="pam-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Citizen'); ?></div>
                    <div class="pam-user-role"><i class="fas fa-shield-alt" style="color: #059669;"></i> Palayan Resident</div>
                </div>
                <div class="pam-divider"></div>
                <a href="profile.php" class="pam-item" role="menuitem">
                    <span class="pam-icon"><i class="fas fa-user"></i></span>
                    <span class="pam-text">Profile Overview</span>
                </a>
                <a href="update-profile-info.php" class="pam-item" role="menuitem">
                    <span class="pam-icon"><i class="fas fa-user-edit"></i></span>
                    <span class="pam-text">Profile Completion</span>
                </a>
                <a href="verify-resident.php" class="pam-item" role="menuitem">
                    <span class="pam-icon"><i class="fas fa-id-card"></i></span>
                    <span class="pam-text">Residency Verification</span>
                </a>
                <div class="pam-divider"></div>
                <a href="logout.php" class="pam-item pam-logout" role="menuitem">
                    <span class="pam-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span class="pam-text">Sign Out</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
// Authority Theme Toggle Logic (Centralized across all resident pages)
(function() {
    function initPortalTheme() {
        const origToggle = document.getElementById('themeToggle');
        if (!origToggle) return;

        // Clone node to strip any duplicate/competing click listeners from page-specific scripts
        const themeToggle = origToggle.cloneNode(true);
        origToggle.parentNode.replaceChild(themeToggle, origToggle);

        const html = document.documentElement;
        const savedTheme = localStorage.getItem('theme');
        const cookieMatch = document.cookie.match(/dark_mode=(enabled|disabled)/);
        const cookieTheme = cookieMatch ? (cookieMatch[1] === 'enabled' ? 'dark' : 'light') : null;
        const currentTheme = savedTheme || cookieTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
        }

        applyTheme(currentTheme);

        themeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            const cur = html.getAttribute('data-theme') || 'light';
            const target = cur === 'dark' ? 'light' : 'dark';
            applyTheme(target);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalTheme);
    } else {
        initPortalTheme();
    }
    // Immediate badge vanish on clicking Announcement
    const resAnnLink = document.getElementById('residentAnnLink') || document.querySelector('.ann-hdr-link');
    if (resAnnLink) {
        resAnnLink.addEventListener('click', function() {
            const badge = document.getElementById('residentAnnBadge') || this.querySelector('.portal-pill-badge-red');
            if (badge) badge.style.display = 'none';
            document.cookie = 'last_viewed_announcements=' + Math.floor(Date.now() / 1000) + '; path=/; max-age=31536000';
            try { sessionStorage.setItem('ann_badge_cleared', '1'); } catch(e){}
        });
    }
    if (sessionStorage.getItem('ann_badge_cleared') === '1' || window.location.pathname.includes('announcements.php')) {
        const badge = document.getElementById('residentAnnBadge');
        if (badge) badge.style.display = 'none';
    }

    // Notification speech bubble: Show once per notification across session, vanishes after 3.5s
    const bubble = document.getElementById('portalNotifBubble');
    if (bubble) {
        const notifId = bubble.getAttribute('data-notif-id');
        const sessionKey = 'palayan_notif_seen_' + notifId;

        // If user already saw this notification bubble in the current session, do not pop up again
        if (sessionStorage.getItem(sessionKey)) {
            bubble.remove();
        } else {
            // Display and mark as seen in this session
            bubble.style.display = 'block';
            sessionStorage.setItem(sessionKey, '1');

            let vanishTimer = setTimeout(function() {
                bubble.classList.add('fade-out');
                setTimeout(function() {
                    if (bubble.parentNode) bubble.remove();
                }, 400);
            }, 3500);

            // Pause timer if user hovers to read, resume when mouse leaves
            bubble.addEventListener('mouseenter', function() {
                clearTimeout(vanishTimer);
            });
            bubble.addEventListener('mouseleave', function() {
                vanishTimer = setTimeout(function() {
                    bubble.classList.add('fade-out');
                    setTimeout(function() {
                        if (bubble.parentNode) bubble.remove();
                    }, 400);
                }, 1500);
            });

            window.dismissNotifBubble = function(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                clearTimeout(vanishTimer);
                bubble.classList.add('fade-out');
                setTimeout(function() {
                    if (bubble.parentNode) bubble.remove();
                }, 400);
            };
        }
    }

    // Avatar Dropdown Toggle Logic
    const avatarBtn = document.getElementById('portalAvatarBtn');
    const avatarMenu = document.getElementById('portalAvatarMenu');
    if (avatarBtn && avatarMenu) {
        avatarBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = avatarMenu.classList.contains('open');
            avatarMenu.classList.toggle('open', !isOpen);
            avatarBtn.setAttribute('aria-expanded', !isOpen);
        });

        document.addEventListener('click', function(e) {
            if (!avatarBtn.contains(e.target) && !avatarMenu.contains(e.target)) {
                avatarMenu.classList.remove('open');
                avatarBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

})();
</script>
<script src="js/portal-motion.js?v=<?php echo file_exists(__DIR__ . '/../js/portal-motion.js') ? filemtime(__DIR__ . '/../js/portal-motion.js') : time(); ?>"></script>
<?php require_once __DIR__ . '/verification-ad-reminder.php'; ?>
<?php include_once __DIR__ . '/process-toast.php'; ?>

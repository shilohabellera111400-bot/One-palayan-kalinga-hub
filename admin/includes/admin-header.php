<?php
// admin/includes/admin-header.php - Unified Admin Header Bar
// Faithfully mirrors Resident & Staff Header Bar (white pill, red button, center pills, right controls)
$cur_file = basename($_SERVER['PHP_SELF'] ?? '');
if (!isset($active_nav)) {
    $active_nav = '';
    if ($cur_file === 'dashboard.php') $active_nav = 'dashboard';
    elseif (in_array($cur_file, ['manage-verifications.php', 'view-verification.php'])) $active_nav = 'verifications';
    elseif ($cur_file === 'announcements.php') $active_nav = 'announcements';
    elseif ($cur_file === 'audit-logs.php') $active_nav = 'audit-logs';
}
if (!isset($dark_mode)) {
    $dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
}
$admin_full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');

// Header counts for announcements and notifications
$admin_unread_announcements = 0;
$admin_unread_notifs = 0;
$admin_pending_verifications = 0;
if (isset($db)) {
    try {
        $last_ann = $_COOKIE['last_viewed_announcement'] ?? '1970-01-01 00:00:00';
        $ann_st = $db->prepare("SELECT COUNT(*) FROM announcements WHERE is_active = 1 AND created_at > :ts");
        $ann_st->execute([':ts' => $last_ann]);
        $admin_unread_announcements = (int)$ann_st->fetchColumn();
    } catch(Throwable $e) {}

    try {
        if (!empty($_SESSION['user_id'])) {
            $notif_st = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
            $notif_st->execute([':uid' => $_SESSION['user_id']]);
            $admin_unread_notifs = (int)$notif_st->fetchColumn();
        }
    } catch(Throwable $e) {}

    try {
        $verif_st = $db->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'");
        $admin_pending_verifications = (int)$verif_st->fetchColumn();
    } catch(Throwable $e) {}
}
?>
<!-- Main Content Area Starts -->
<main class="main-content" role="main">
    <!-- Unified Portal Header Bar -->
    <header class="portal-header-bar admin-header-bar" role="banner">
        <!-- Left: Quick Home / Mobile Drawer Toggle Button -->
        <div class="portal-header-left">
            <a href="../index.php" class="portal-menu-btn-red" id="portalHomeBtn" aria-label="Portal Home" title="Return to Portal Home">
                <i class="fas fa-home"></i>
            </a>
            <button type="button" class="portal-menu-btn-red admin-mobile-toggle" id="portalMenuToggleBtn" style="display: none;" aria-label="Toggle Navigation" title="Toggle Navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Center: Quick Navigation Pills (Dashboard, Verifications, Residents, Announcements) -->
        <nav class="portal-header-center-nav" aria-label="Admin Navigation">
            <a href="dashboard.php" class="portal-hdr-pill <?php echo ($active_nav === 'dashboard' || $cur_file === 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-verifications.php" class="portal-hdr-pill <?php echo ($active_nav === 'verifications' || in_array($cur_file, ['manage-verifications.php', 'view-verification.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Verifications</span>
                <?php if ($admin_pending_verifications > 0): ?>
                    <span class="portal-pill-badge" id="hdrVerifBadge" style="background:#DC2626; color:#ffffff;"><?php echo $admin_pending_verifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage-residents.php" class="portal-hdr-pill <?php echo ($active_nav === 'residents' || $cur_file === 'manage-residents.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-friends"></i>
                <span>Residents</span>
            </a>
            <a href="announcements.php" id="portalHdrAnnLink" class="portal-hdr-pill <?php echo ($active_nav === 'announcements' || $cur_file === 'announcements.php') ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </nav>

        <!-- Right: Search | Notifications | Settings | Theme Toggle | Professional Admin Shield Badge -->
        <div class="portal-header-right">
            <!-- Search Trigger -->
            <div class="portal-search-wrap" id="adminSearchWrap" title="Search records (Ctrl+K)">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search records..." id="adminSearchTrigger" readonly>
                <span class="search-kbd">Ctrl+K</span>
            </div>

            <!-- Notifications Bell 🔔 -->
            <a href="../notifications.php" class="portal-hdr-ctrl" title="Notifications & Alerts" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($admin_unread_notifs > 0): ?>
                    <span class="portal-notif-dot"><?php echo $admin_unread_notifs; ?></span>
                <?php endif; ?>
            </a>

            <!-- Settings -->
            <a href="settings.php" class="portal-hdr-ctrl <?php echo ($cur_file === 'settings.php') ? 'active' : ''; ?>" title="System Settings" aria-label="System Settings">
                <i class="fas fa-cog"></i>
            </a>

            <!-- Theme Toggle -->
            <button type="button" class="portal-hdr-ctrl" id="themeToggle" title="Toggle Dark/Light Mode" aria-label="Toggle Theme">
                <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
            </button>

            <!-- Professional Admin Shield Logo Badge (Clickable to Edit Profile) -->
            <a href="profile.php" class="portal-hdr-admin-badge <?php echo ($cur_file === 'profile.php') ? 'active' : ''; ?>" title="<?php echo $admin_full_name; ?> - Edit Profile" aria-label="Administrator Profile">
                <i class="fas fa-user-shield"></i>
            </a>
        </div>
    </header>

<script>
// Immediate announcement badge dismissal on click
(function() {
    var annLink = document.getElementById('portalHdrAnnLink');
    if (annLink) {
        annLink.addEventListener('click', function() {
            var badge = document.getElementById('portalHdrAnnBadge');
            if (badge) badge.style.display = 'none';
            try {
                document.cookie = 'last_viewed_announcement=' + encodeURIComponent(new Date().toISOString().slice(0, 19).replace('T', ' ')) + '; path=/; max-age=31536000';
            } catch(e) {}
        });
    }
})();
</script>

<?php include __DIR__ . '/../../includes/process-toast.php'; ?>

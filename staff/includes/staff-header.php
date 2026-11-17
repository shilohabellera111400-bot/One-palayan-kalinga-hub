<?php
// staff/includes/staff-header.php - Unified Staff Portal Header Bar
// Eliminates duplicate module navigation per Part 2, Item 4; provides functional dark mode, notifications, and search per Item 8.
if (!isset($dark_mode)) {
    $dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
}
$is_root_staff = ($portal_context ?? '') === 'root' || basename(dirname($_SERVER['PHP_SELF'])) !== 'staff';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

$s_base = $is_root_staff ? 'staff/' : '';
$s_dash = $is_root_staff ? 'staff-dashboard.php' : '../staff-dashboard.php';
$s_home = $is_root_staff ? 'index.php' : '../index.php';
$s_notif = $is_root_staff ? 'notifications.php' : '../notifications.php';
$s_profile = $is_root_staff ? 'profile.php' : '../profile.php';
$s_announcements = $s_base . 'announcements.php';

$staff_display_name = htmlspecialchars($_SESSION['full_name'] ?? 'Staff Member');

// Avatar initials
$s_initials = '';
$name_parts = explode(' ', $_SESSION['full_name'] ?? 'S');
foreach ($name_parts as $p) {
    if (!empty($p)) $s_initials .= strtoupper(substr($p, 0, 1));
}
$s_initials = substr($s_initials, 0, 2) ?: 'ST';

// Notification count & recent notifications list
$unread_notifs = 0;
$staff_recent_notifs = [];
if (isset($db) && isset($_SESSION['user_id'])) {
    try {
        $n_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = FALSE");
        $n_stmt->execute([':uid' => $_SESSION['user_id']]);
        $unread_notifs = (int)$n_stmt->fetchColumn();

        $sn_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
        $sn_stmt->execute([':uid' => $_SESSION['user_id']]);
        $staff_recent_notifs = $sn_stmt->fetchAll(PDO::FETCH_ASSOC);

        // If staff user has no personal notifications, show latest desk alerts
        if (empty($staff_recent_notifs)) {
            $desk_stmt = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
            $staff_recent_notifs = $desk_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {
        $unread_notifs = 0;
        $staff_recent_notifs = [];
    }
}

// Avatar URL - Reliable database lookup for all staff subpages
if (!isset($avatar_url) || empty($avatar_url)) {
    $avatar_url = '';
    $raw_pic = '';
    if (isset($staff_info['profile_picture']) && !empty($staff_info['profile_picture'])) {
        $raw_pic = $staff_info['profile_picture'];
    } elseif (isset($db) && isset($_SESSION['user_id'])) {
        try {
            $pic_q = $db->prepare("SELECT profile_picture FROM users WHERE user_id = :uid");
            $pic_q->execute([':uid' => $_SESSION['user_id']]);
            $raw_pic = (string)$pic_q->fetchColumn();
        } catch(Exception $e) {}
    }

    if (!empty($raw_pic)) {
        if ($is_root_staff) {
            if (file_exists(__DIR__ . '/../../' . $raw_pic)) {
                $avatar_url = $raw_pic;
            } elseif (file_exists(__DIR__ . '/../' . $raw_pic)) {
                $avatar_url = $raw_pic;
            } else {
                $avatar_url = $raw_pic;
            }
        } else {
            if (file_exists(__DIR__ . '/../../' . $raw_pic)) {
                $avatar_url = '../' . $raw_pic;
            } else {
                $avatar_url = '../' . $raw_pic;
            }
        }
    }
}

// Check unread announcements for staff badge
$unread_announcements = 0;
if (isset($db)) {
    try {
        $last_staff_ann_ts = isset($_COOKIE['last_viewed_staff_announcements']) ? (int)$_COOKIE['last_viewed_staff_announcements'] : 0;
        if ($current_page === 'announcements.php') {
            $unread_announcements = 0;
        } elseif ($last_staff_ann_ts > 0) {
            $ann_c_stmt = $db->prepare("SELECT COUNT(*) FROM announcements WHERE UNIX_TIMESTAMP(created_at) > :last_view");
            $ann_c_stmt->execute([':last_view' => $last_staff_ann_ts]);
            $unread_announcements = (int)$ann_c_stmt->fetchColumn();
        } else {
            $unread_announcements = (int)$db->query("SELECT COUNT(*) FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        }
    } catch(Exception $e) {
        $unread_announcements = 0;
    }
}
?>

<style>
/* Header Dropdowns (Notification & Search) - Part 2, Item 8 */
.header-dropdown-wrap {
    position: relative;
    display: inline-block;
}

.staff-notif-dropdown,
.staff-search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border);
    border-radius: 18px;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.16);
    z-index: 2500;
    display: none;
    animation: fadeInDropdown 0.2s ease-out;
}

[data-theme="dark"] .staff-notif-dropdown,
[data-theme="dark"] .staff-search-dropdown {
    background: #152227;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.55);
}

.staff-notif-dropdown.active,
.staff-search-dropdown.active {
    display: flex;
    flex-direction: column;
}

.staff-notif-dropdown {
    width: 320px;
    max-height: 420px;
    overflow: hidden;
}

.notif-dropdown-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--ref-border);
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--ref-text);
}

.badge-unread-count {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 50px;
    background: #FEE2E2;
    color: #DC2626;
}

.notif-dropdown-list {
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    max-height: 280px;
}

.notif-dropdown-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--ref-border);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s ease;
}

.notif-dropdown-item:hover {
    background: var(--ref-canvas);
}

.notif-dropdown-item.is-unread {
    background: rgba(127, 3, 3, 0.05);
    border-left: 3px solid #DC2626;
}

.notif-item-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(127, 3, 3, 0.1);
    color: var(--ref-maroon);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.notif-item-body h5 {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ref-text);
    margin: 0 0 2px;
    line-height: 1.2;
}

.notif-item-body p {
    font-size: 0.7rem;
    color: var(--ref-muted);
    margin: 0;
    line-height: 1.3;
}

.notif-item-time {
    font-size: 0.64rem;
    color: var(--ref-muted);
    margin-top: 3px;
    display: block;
}

.notif-dropdown-footer {
    padding: 10px;
    text-align: center;
    border-top: 1px solid var(--ref-border);
    background: var(--ref-canvas);
}

.notif-dropdown-footer a {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ref-maroon);
    text-decoration: none;
}

/* Search Dropdown */
.staff-search-dropdown {
    width: 320px;
    padding: 14px;
}

.staff-search-form {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--ref-canvas);
    border: 1.5px solid var(--ref-border);
    border-radius: 50px;
    padding: 4px 6px 4px 14px;
    transition: border-color 0.2s ease;
}

.staff-search-form:focus-within {
    border-color: var(--ref-maroon);
}

.staff-search-form input {
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.8rem;
    color: var(--ref-text);
    flex: 1;
}

.btn-search-go {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: none;
    background: var(--ref-maroon);
    color: #FFFFFF;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: transform 0.15s;
}

.btn-search-go:hover {
    transform: scale(1.08);
}

.search-quick-tags {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--ref-border);
}

.quick-tag-label {
    font-size: 0.68rem;
    color: var(--ref-muted);
    font-weight: 700;
    width: 100%;
}

.quick-tag-chip {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-text);
    text-decoration: none;
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    padding: 4px 10px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}

.quick-tag-chip:hover {
    border-color: var(--ref-maroon);
    color: var(--ref-maroon);
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
    margin-left: 5px;
}

.hdr-unread-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #DC2626;
    display: inline-block;
    margin-left: 4px;
}
</style>

<!-- Unified Staff Portal Header Bar -->
<header class="portal-header-bar staff-header-bar" role="banner">
    <!-- Left: Red Home Button -->
    <div class="portal-header-left">
        <a href="<?php echo $s_home; ?>" class="portal-menu-btn-red" id="portalHomeBtn" aria-label="Home" title="Return to Portal Home">
            <i class="fas fa-home"></i>
        </a>
    </div>

    <!-- Center: Frequently Accessed Staff Navigation (Duplicate modules removed per Part 2, Item 4) -->
    <nav class="portal-header-center-nav" aria-label="Staff Navigation">
        <!-- 1. Dashboard -->
        <a href="<?php echo $s_dash; ?>" class="portal-hdr-pill <?php echo ($active_nav === 'dashboard' || $current_page === 'staff-dashboard.php') ? 'active' : ''; ?>" title="Operational Dashboard">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>

        <!-- 2. Announcements -->
        <a href="<?php echo $s_announcements; ?>" id="staffAnnLink" class="portal-hdr-pill ann-staff-hdr-link <?php echo in_array($current_page, ['announcements.php', 'announcement.php']) ? 'active' : ''; ?>" title="City Service Bulletins">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>

        <!-- 3. Live Desk Scanner (High Priority Desk Action) -->
        <a href="<?php echo $s_base; ?>qr-scanner.php" class="portal-hdr-pill <?php echo ($current_page === 'qr-scanner.php' || $active_nav === 'scanner') ? 'active' : ''; ?>" title="Live Intake QR Scanner">
            <i class="fas fa-qrcode"></i>
            <span>Live Scanner</span>
        </a>
    </nav>

    <!-- Right: Search | Notification Bell 🔔 | Night/Day Theme | Simple Avatar -->
    <div class="portal-header-right">
        <!-- Quick Search Dropdown Trigger -->
        <div class="header-dropdown-wrap">
            <button type="button" class="portal-hdr-ctrl" id="openStaffSearchBtn" title="Search Desk Records &amp; Residents" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>
            <div class="staff-search-dropdown" id="staffSearchDropdown">
                <form action="<?php echo $s_base; ?>manage-residents.php" method="GET" class="staff-search-form" id="staffHeaderSearchForm">
                    <i class="fas fa-search" style="color: var(--ref-muted); font-size: 0.8rem;"></i>
                    <input type="text" name="search" id="staffHeaderSearchInput" placeholder="Search resident, reference #..." autocomplete="off">
                    <button type="submit" class="btn-search-go" title="Execute Search"><i class="fas fa-arrow-right"></i></button>
                </form>
                <div class="search-quick-tags">
                    <span class="quick-tag-label">Direct Modules:</span>
                    <a href="<?php echo $s_base; ?>manage-residents.php" class="quick-tag-chip"><i class="fas fa-users"></i> Residents</a>
                    <a href="<?php echo $s_base; ?>manage-aics.php" class="quick-tag-chip"><i class="fas fa-hand-holding-heart"></i> AICS</a>
                    <a href="<?php echo $s_base; ?>manage-housing.php" class="quick-tag-chip"><i class="fas fa-home"></i> Housing</a>
                    <a href="<?php echo $s_base; ?>manage-appointments.php" class="quick-tag-chip"><i class="fas fa-calendar-check"></i> Appointments</a>
                </div>
            </div>
        </div>

        <!-- Notification Bell Dropdown 🔔 -->
        <div class="header-dropdown-wrap">
            <button type="button" class="portal-hdr-ctrl" id="staffNotifBellBtn" title="Desk Notifications" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread_notifs > 0): ?>
                    <span class="portal-notif-dot"><?php echo $unread_notifs; ?></span>
                <?php endif; ?>
            </button>
            <div class="staff-notif-dropdown" id="staffNotifMenu">
                <div class="notif-dropdown-header">
                    <span>Notifications</span>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="badge-unread-count"><?php echo $unread_notifs; ?> unread</span>
                    <?php endif; ?>
                </div>
                <div class="notif-dropdown-list">
                    <?php if (!empty($staff_recent_notifs)): ?>
                        <?php foreach ($staff_recent_notifs as $sn): 
                            $is_un = empty($sn['is_read']);
                        ?>
                        <a href="<?php echo $s_notif; ?>?mark_one=<?php echo $sn['notification_id']; ?>" class="notif-dropdown-item <?php echo $is_un ? 'is-unread' : ''; ?>">
                            <div class="notif-item-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notif-item-body">
                                <h5><?php echo htmlspecialchars($sn['title']); ?></h5>
                                <p><?php echo htmlspecialchars(substr($sn['message'], 0, 75)); ?><?php echo strlen($sn['message']) > 75 ? '...' : ''; ?></p>
                                <span class="notif-item-time"><?php echo date('M d, h:i A', strtotime($sn['created_at'])); ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 16px; text-align: center; color: var(--ref-muted); font-size: 0.78rem;">
                            <i class="fas fa-check-circle" style="color: #10B981; font-size: 1.1rem; margin-bottom: 4px; display: block;"></i>
                            No new notifications
                        </div>
                    <?php endif; ?>
                </div>
                <div class="notif-dropdown-footer">
                    <a href="<?php echo $s_notif; ?>">View All Notifications &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Night/Day Mode Toggle -->
        <button type="button" class="portal-hdr-ctrl" id="themeToggle" title="Toggle Night/Day Mode" aria-label="Toggle Theme">
            <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
        </button>

        <!-- Staff Profile Avatar (Direct to profile) -->
        <a href="<?php echo $s_profile; ?>" class="portal-hdr-avatar" title="Staff Profile (<?php echo $staff_display_name; ?>)">
            <?php if (!empty($avatar_url)): ?>
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Staff Avatar">
            <?php else: ?>
                <span><?php echo htmlspecialchars($s_initials); ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<script>
// Unified Portal Theme Toggle & Header Controls (Part 2, Item 8)
(function() {
    function initHeaderControls() {
        // Theme Toggle with Cookie + LocalStorage persistence
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const html = document.documentElement;
            function applyTheme(theme) {
                html.setAttribute('data-theme', theme);
                if (document.body) document.body.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                const icon = themeToggle.querySelector('i');
                if (icon) {
                    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
                document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000; SameSite=Lax`;
            }

            // Sync immediately with active theme
            const initialTheme = localStorage.getItem('theme') || html.getAttribute('data-theme') || 'light';
            applyTheme(initialTheme);

            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const cur = html.getAttribute('data-theme') || 'light';
                const target = cur === 'dark' ? 'light' : 'dark';
                applyTheme(target);
            });
        }

        // Search Dropdown Toggle
        const searchBtn = document.getElementById('openStaffSearchBtn');
        const searchDropdown = document.getElementById('staffSearchDropdown');
        const searchInput = document.getElementById('staffHeaderSearchInput');
        if (searchBtn && searchDropdown) {
            searchBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const wasActive = searchDropdown.classList.contains('active');
                closeAllHeaderDropdowns();
                if (!wasActive) {
                    searchDropdown.classList.add('active');
                    if (searchInput) searchInput.focus();
                }
            });
        }

        // Notification Dropdown Toggle
        const notifBtn = document.getElementById('staffNotifBellBtn');
        const notifMenu = document.getElementById('staffNotifMenu');
        if (notifBtn && notifMenu) {
            notifBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const wasActive = notifMenu.classList.contains('active');
                closeAllHeaderDropdowns();
                if (!wasActive) {
                    notifMenu.classList.add('active');
                }
            });
        }

        function closeAllHeaderDropdowns() {
            if (searchDropdown) searchDropdown.classList.remove('active');
            if (notifMenu) notifMenu.classList.remove('active');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.header-dropdown-wrap')) {
                closeAllHeaderDropdowns();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllHeaderDropdowns();
            }
        });

        // Responsive mobile menu button check
        const mobileBtn = document.getElementById('portalMenuToggleBtn');
        if (mobileBtn && window.innerWidth <= 820) {
            mobileBtn.style.display = 'inline-flex';
        }
        window.addEventListener('resize', function() {
            if (mobileBtn) {
                mobileBtn.style.display = (window.innerWidth <= 820) ? 'inline-flex' : 'none';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHeaderControls);
    } else {
        initHeaderControls();
    }
})();
</script>
<?php
// Universal sliding card notification for Staff Portal
$toast_file = __DIR__ . '/../../includes/process-toast.php';
if (file_exists($toast_file)) {
    include_once $toast_file;
}
?>

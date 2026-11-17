<?php
// admin/includes/admin-sidebar.php - Responsive Dual-Mode Sidebar for Admin Portal
// Minimal (76px, dark dock) on Admin Dashboard; Expanded (240px, rich maroon gradient) on other admin pages.
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$is_dashboard = ($current_page === 'dashboard.php');

$logo_v = @filemtime(__DIR__ . '/../../images/logo.png') ?: time();

// Active state detection
if (!isset($active_sidebar)) {
    $active_sidebar = '';
    if ($current_page === 'dashboard.php') $active_sidebar = 'dashboard';
    elseif (in_array($current_page, ['manage-staff.php', 'create-staff.php'])) $active_sidebar = 'staff';
    elseif (in_array($current_page, ['manage-residents.php', 'export-residents.php'])) $active_sidebar = 'residents';
    elseif ($current_page === 'manage-staff-services.php') $active_sidebar = 'staff-services';
    elseif ($current_page === 'manage-users.php') $active_sidebar = 'users';
    elseif (in_array($current_page, ['manage-verifications.php', 'view-verification.php'])) $active_sidebar = 'verifications';
    elseif ($current_page === 'calendar.php') $active_sidebar = 'calendar';
    elseif ($current_page === 'manage-blackout.php') $active_sidebar = 'blackout';
    elseif ($current_page === 'announcements.php') $active_sidebar = 'announcements';
    elseif ($current_page === 'cms.php') $active_sidebar = 'cms';
    elseif ($current_page === 'audit-logs.php') $active_sidebar = 'audit';
    elseif ($current_page === 'settings.php' || $current_page === 'backup.php') $active_sidebar = 'settings';
}

// Pending verification count for admin notification badges
$sidebar_pending_verif = 0;
try {
    $db_conn = $pdo ?? ($db ?? null);
    if (!$db_conn && class_exists('Database')) {
        $sb_db = new Database();
        $db_conn = $sb_db->getConnection();
    }
    if ($db_conn) {
        $st_v = $db_conn->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'");
        $sidebar_pending_verif = (int)$st_v->fetchColumn();
    }
} catch (Exception $e) {}
?>

<style>
/* Sidebar Brand Logo Fitting to White Background */
.portal-brand-logo-ring {
    border-radius: 50% !important;
    background: #FFFFFF !important;
    padding: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
}

.portal-sidebar-expanded .portal-brand-logo-ring {
    width: 68px !important;
    height: 68px !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
    margin: 0 auto 10px !important;
    border: 2px solid rgba(255, 255, 255, 0.8) !important;
}

.portal-sidebar-minimal .portal-brand-logo-ring {
    width: 48px !important;
    height: 48px !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
    border: 2px solid rgba(255, 255, 255, 0.8) !important;
}

.portal-brand-logo-ring img {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important;
    display: block !important;
    transform: scale(1.05);
}

/* User Profile Section in Expanded Sidebar (Part 1, 2, 3 - Item 4) */
.sidebar-user-expanded {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 10px 8px 12px;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14);
    width: 100%;
    box-sizing: border-box;
    flex-shrink: 0;
}

.portal-sidebar-minimal .sidebar-user-expanded {
    display: none !important;
}

/* Broken Line Big Border for Profile Circle */
.sidebar-avatar-wrapper {
    position: relative;
    width: 84px;
    height: 84px;
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sidebar-avatar-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 3.5px dashed #F6D8A8;
    box-shadow: 0 0 14px rgba(246, 216, 168, 0.45);
    animation: avatarRingRotate 25s linear infinite;
    pointer-events: none;
    z-index: 1;
}

[data-theme="dark"] .sidebar-avatar-ring {
    border-color: #E5B338;
    box-shadow: 0 0 14px rgba(229, 179, 56, 0.35);
}

@keyframes avatarRingRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.sidebar-avatar-large {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FEF7DF;
    color: #7F0303;
    font-size: 1.45rem;
    font-weight: 800;
    border: 2px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22);
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.sidebar-avatar-large img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
}

.sidebar-user-info {
    text-align: center;
    width: 100%;
}

.sidebar-user-name {
    font-size: 0.86rem;
    font-weight: 700;
    color: #FFFFFF;
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 0.68rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.78);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
</style>

<!-- Mobile Drawer Overlay -->
<div class="portal-sidebar-overlay" id="portalSidebarOverlay"></div>

<!-- Admin Sidebar (Minimalist 76px on Dashboard, Expanded 240px on Subpages) -->
<aside class="portal-sidebar-wrap admin-sidebar <?php echo $is_dashboard ? 'portal-sidebar-minimal' : 'portal-sidebar-expanded'; ?>" id="adminSidebar" aria-label="Admin Navigation">

    <!-- Branding Header -->
    <div class="portal-sidebar-brand">
        <a href="profile.php" class="portal-brand-logo-ring" title="Administrator Profile">
            <img src="../images/logo.png?v=<?php echo $logo_v; ?>" alt="Palayan City Seal" onerror="this.src='../images/logo.png';">
        </a>
        <?php if (!$is_dashboard): ?>
            <div class="portal-brand-text-expanded">ONE PALAYAN-KALINGA HUB</div>
            <div class="portal-brand-subtext-expanded">System Administration</div>
        <?php endif; ?>
    </div>

    <!-- User Profile Header removed per request for a clean, unified sidebar -->

    <!-- Navigation Stack -->
    <nav class="portal-sidebar-nav">

        <!-- 1. Dashboard -->
        <div class="portal-nav-item">
            <a href="dashboard.php" class="portal-nav-btn <?php echo ($active_sidebar === 'dashboard') ? 'active' : ''; ?>" aria-label="Dashboard">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-th-large"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Dashboard</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Dashboard</span><?php endif; ?>
            </a>
        </div>

        <?php if ($is_dashboard): ?>
            <!-- MINIMALIST DASHBOARD DOCK (76px) WITH FLYOUTS -->

            <!-- Personnel & Users Flyout -->
            <div class="portal-nav-item">
                <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo in_array($active_sidebar, ['staff', 'residents', 'staff-services', 'users']) ? 'active' : ''; ?>" data-flyout="flyoutAdminPersonnel" aria-label="Personnel &amp; Users" role="button" aria-haspopup="true">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="portal-tooltip">Personnel &amp; Users</span>
                </a>

                <div class="portal-side-flyout" id="flyoutAdminPersonnel" role="menu">
                    <div class="flyout-header-tag"><i class="fas fa-users"></i> Personnel &amp; Users</div>
                    <a href="manage-staff.php" class="flyout-link-item <?php echo ($active_sidebar === 'staff') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-user-tie"></i> Manage Staff
                    </a>
                    <a href="manage-residents.php" class="flyout-link-item <?php echo ($active_sidebar === 'residents') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-user-friends"></i> Resident Directory
                    </a>
                    <a href="manage-staff-services.php" class="flyout-link-item <?php echo ($active_sidebar === 'staff-services') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-user-tag"></i> Staff Services
                    </a>
                    <a href="manage-users.php" class="flyout-link-item <?php echo ($active_sidebar === 'users') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-id-badge"></i> System Users
                    </a>
                </div>
            </div>

            <!-- Verifications Direct -->
            <div class="portal-nav-item">
                <a href="manage-verifications.php" class="portal-nav-btn <?php echo ($active_sidebar === 'verifications') ? 'active' : ''; ?>" aria-label="Residency Verifications" style="position: relative;">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <?php if ($sidebar_pending_verif > 0): ?>
                        <span class="portal-badge-cnt" style="position: absolute; top: 8px; right: 8px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; background: #dc2626; color: #fff; font-size: 10px; font-weight: 700; line-height: 18px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo $sidebar_pending_verif; ?></span>
                    <?php endif; ?>
                    <span class="portal-tooltip">Residency Verifications <?php echo $sidebar_pending_verif > 0 ? "($sidebar_pending_verif pending)" : ''; ?></span>
                </a>
            </div>

            <!-- Operations & Schedule Flyout -->
            <div class="portal-nav-item">
                <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo in_array($active_sidebar, ['calendar', 'blackout']) ? 'active' : ''; ?>" data-flyout="flyoutAdminSchedule" aria-label="Operations &amp; Schedule" role="button" aria-haspopup="true">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span class="portal-tooltip">Operations &amp; Schedule</span>
                </a>

                <div class="portal-side-flyout" id="flyoutAdminSchedule" role="menu">
                    <div class="flyout-header-tag"><i class="fas fa-calendar-alt"></i> Operations &amp; Schedule</div>
                    <a href="calendar.php" class="flyout-link-item <?php echo ($active_sidebar === 'calendar') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-calendar-check"></i> Calendar Manager
                    </a>
                    <a href="manage-blackout.php" class="flyout-link-item <?php echo ($active_sidebar === 'blackout') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-calendar-times"></i> Blackout Dates
                    </a>
                </div>
            </div>

            <!-- Website CMS Direct -->
            <div class="portal-nav-item">
                <a href="cms.php" class="portal-nav-btn <?php echo ($active_sidebar === 'cms') ? 'active' : ''; ?>" aria-label="Website CMS">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <span class="portal-tooltip">Website CMS</span>
                </a>
            </div>

            <!-- Audit Logs Direct -->
            <div class="portal-nav-item">
                <a href="audit-logs.php" class="portal-nav-btn <?php echo ($active_sidebar === 'audit') ? 'active' : ''; ?>" aria-label="System Audit Logs">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-history"></i>
                    </div>
                    <span class="portal-tooltip">System Audit Logs</span>
                </a>
            </div>

            <!-- System & Governance Flyout -->
            <div class="portal-nav-item">
                <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo in_array($active_sidebar, ['settings', 'audit']) ? 'active' : ''; ?>" data-flyout="flyoutAdminSystem" aria-label="System &amp; Settings" role="button" aria-haspopup="true">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <span class="portal-tooltip">System &amp; Settings</span>
                </a>

                <div class="portal-side-flyout" id="flyoutAdminSystem" role="menu">
                    <div class="flyout-header-tag"><i class="fas fa-cogs"></i> System &amp; Security</div>
                    <a href="audit-logs.php" class="flyout-link-item <?php echo ($active_sidebar === 'audit') ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-history"></i> Audit Logs
                    </a>
                    <a href="settings.php" class="flyout-link-item <?php echo ($active_sidebar === 'settings' && !isset($_GET['tab'])) ? 'active' : ''; ?>" role="menuitem">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                    <a href="settings.php?tab=backup" class="flyout-link-item" role="menuitem">
                        <i class="fas fa-database"></i> Database Backup
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- EXPANDED SUBPAGE SIDEBAR (240px MAROON GRADIENT) -->

            <!-- Section 1: Personnel & Access -->
            <div class="portal-sidebar-divider">
                <span>Personnel &amp; Users</span>
            </div>

            <div class="portal-nav-item">
                <a href="manage-staff.php" class="portal-nav-btn <?php echo ($active_sidebar === 'staff') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-user-tie"></i>
                        <span class="portal-nav-label">Manage Staff</span>
                    </div>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="manage-residents.php" class="portal-nav-btn <?php echo ($active_sidebar === 'residents') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-user-friends"></i>
                        <span class="portal-nav-label">Resident Directory</span>
                    </div>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="manage-staff-services.php" class="portal-nav-btn <?php echo ($active_sidebar === 'staff-services') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-user-tag"></i>
                        <span class="portal-nav-label">Staff Services</span>
                    </div>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="manage-users.php" class="portal-nav-btn <?php echo ($active_sidebar === 'users') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-id-badge"></i>
                        <span class="portal-nav-label">System Users</span>
                    </div>
                </a>
            </div>

            <!-- Section 2: Operations & Schedules -->
            <div class="portal-sidebar-divider">
                <span>Operations &amp; Schedules</span>
            </div>

            <div class="portal-nav-item">
                <a href="manage-verifications.php" class="portal-nav-btn <?php echo ($active_sidebar === 'verifications') ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-shield-alt"></i>
                        <span class="portal-nav-label">Verifications</span>
                    </div>
                    <?php if ($sidebar_pending_verif > 0): ?>
                        <span class="portal-nav-badge" style="background: #dc2626; color: #ffffff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; box-shadow: 0 2px 6px rgba(220,38,38,0.4);"><?php echo $sidebar_pending_verif; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="calendar.php" class="portal-nav-btn <?php echo ($active_sidebar === 'calendar') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="portal-nav-label">Calendar Manager</span>
                    </div>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="manage-blackout.php" class="portal-nav-btn <?php echo ($active_sidebar === 'blackout') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-calendar-times"></i>
                        <span class="portal-nav-label">Blackout Dates</span>
                    </div>
                </a>
            </div>

            <!-- Section 3: Content & Portal -->
            <div class="portal-sidebar-divider">
                <span>Content &amp; Portal</span>
            </div>

            <div class="portal-nav-item">
                <a href="cms.php" class="portal-nav-btn <?php echo ($active_sidebar === 'cms') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-desktop"></i>
                        <span class="portal-nav-label">Website CMS</span>
                    </div>
                </a>
            </div>

            <!-- Section 4: System & Security -->
            <div class="portal-sidebar-divider">
                <span>System &amp; Security</span>
            </div>

            <div class="portal-nav-item">
                <a href="audit-logs.php" class="portal-nav-btn <?php echo ($active_sidebar === 'audit') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-history"></i>
                        <span class="portal-nav-label">Audit Logs</span>
                    </div>
                </a>
            </div>

            <div class="portal-nav-item">
                <a href="settings.php" class="portal-nav-btn <?php echo ($active_sidebar === 'settings') ? 'active' : ''; ?>">
                    <div class="portal-nav-btn-left">
                        <i class="fas fa-cogs"></i>
                        <span class="portal-nav-label">Settings &amp; Backup</span>
                    </div>
                </a>
            </div>

        <?php endif; ?>

    </nav>

    <!-- Sidebar Footer / Logout -->
    <div class="portal-sidebar-footer">
        <div class="portal-nav-item">
            <a href="../logout.php" class="portal-nav-btn portal-logout-btn" aria-label="Sign Out" title="Sign Out">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Sign Out</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Sign Out</span><?php endif; ?>
            </a>
        </div>
    </div>

</aside>

<script>
// Interactive Sidebar Flyout & Mobile Drawer Script with Safe Hover Bridge
(function() {
    function initAdminPortalSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('portalSidebarOverlay');
        const navItems = document.querySelectorAll('.portal-nav-item');
        const flyouts = document.querySelectorAll('.portal-side-flyout');
        const closeTimers = {};

        // 1. Service icon triggers: Clicking icon toggles flyout
        const triggers = sidebar ? sidebar.querySelectorAll('.portal-flyout-trigger') : [];
        triggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const targetId = this.getAttribute('data-flyout');
                const targetFlyout = document.getElementById(targetId);
                if (targetFlyout) {
                    const isOpen = targetFlyout.classList.contains('open');
                    flyouts.forEach(f => f.classList.remove('open'));
                    if (!isOpen) {
                        targetFlyout.classList.add('open');
                    }
                }
            });
        });

        // 2. Safe Hover for Flyout Menus with 300ms debounce
        navItems.forEach((item, idx) => {
            const trigger = item.querySelector('.portal-flyout-trigger');
            const flyout = item.querySelector('.portal-side-flyout');
            if (!trigger || !flyout) return;

            item.addEventListener('mouseenter', function() {
                if (closeTimers[idx]) {
                    clearTimeout(closeTimers[idx]);
                    delete closeTimers[idx];
                }
                flyouts.forEach(f => {
                    if (f !== flyout) f.classList.remove('open');
                });
                flyout.classList.add('open');
            });

            item.addEventListener('mouseleave', function() {
                closeTimers[idx] = setTimeout(() => {
                    flyout.classList.remove('open');
                    delete closeTimers[idx];
                }, 300);
            });
        });

        // 3. Document click to close flyouts
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.portal-side-flyout') && !e.target.closest('.portal-flyout-trigger')) {
                flyouts.forEach(f => f.classList.remove('open'));
            }
        });

        // 4. Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                flyouts.forEach(f => f.classList.remove('open'));
                if (sidebar) sidebar.classList.remove('mobile-open');
                if (overlay) overlay.classList.remove('active');
            }
        });

        // 5. Mobile drawer toggle listener
        const menuBtn = document.getElementById('portalMenuToggleBtn') || document.getElementById('adminMobileToggle');
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('mobile-open');
                if (overlay) overlay.classList.toggle('active');
            });
        }
        if (overlay && sidebar) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminPortalSidebar);
    } else {
        initAdminPortalSidebar();
    }
})();
</script>

<?php
// This file contains the navigation bar that's used across the site
include_once __DIR__ . '/pwa-meta.php';
?>
<nav class="navbar" id="mainNavbar">
    <div class="nav-container">
        <!-- Hamburger (3 lines) on mobile placed on the left side before the logo -->
        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Brand -->
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>" class="nav-brand">
            <div class="brand-logo-circle">
                <?php
                if (!function_exists('cms_get')) {
                    require_once __DIR__ . '/cms-helper.php';
                }
                $nav_logo_src = cms_image('site_logo', 'images/logo.png');
                ?>
                <img src="<?php echo $nav_logo_src; ?>" alt="<?php echo htmlspecialchars(cms_get('site_title', 'One Palayan-Kalinga Hub')); ?> Logo" onerror="this.src='<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../images/logo.png' : 'images/logo.png'; ?>'">
            </div>
            <div class="brand-text">
                <span class="brand-title"><?php echo htmlspecialchars(cms_get('site_title', 'ONE PALAYAN-KALINGA HUB')); ?></span>
                <span class="brand-sub"><?php echo htmlspecialchars(cms_get('site_tagline', 'CSWDO Palayan City')); ?></span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <ul class="nav-links" id="navLinks">
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Home</a></li>
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../services.php' : 'services.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'class="active"' : ''; ?>>Services</a></li>
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../about.php' : 'about.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'class="active"' : ''; ?>>About</a></li>
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../contact.php' : 'contact.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'class="active"' : ''; ?>>Contact Us</a></li>
        </ul>

        <!-- Right Actions -->
        <div class="nav-actions">
            <!-- Dark Mode Toggle -->
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <?php if(!isLoggedIn()): ?>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../login.php' : 'login.php'; ?>" class="nav-btn nav-btn-outline">Login</a>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../register.php' : 'register.php'; ?>" class="nav-btn nav-btn-solid">Register</a>
            <?php else: ?>
                <!-- Notification Bell -->
                <?php if(!isStaff() && !isAdmin()): ?>
                <a href="notifications.php" class="notif-bell" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php
                    // Show unread count if available
                    if (isset($unread_count) && $unread_count > 0):
                    ?>
                    <span class="notif-count"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- User Dropdown / Logged In Navigation -->
                <?php 
                $is_nav_home = (basename($_SERVER['PHP_SELF']) === 'index.php');
                $user_dash_link = (isStaff() || isAdmin()) 
                    ? ((isAdmin() && strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : (isAdmin() ? 'admin/dashboard.php' : 'staff/staff-dashboard.php')) 
                    : 'dashboard.php';
                
                // Get user avatar picture if available
                $nav_profile_pic = '';
                if (isset($_SESSION['user_id'])) {
                    if (!empty($_SESSION['profile_picture']) && file_exists($_SESSION['profile_picture'])) {
                        $nav_profile_pic = $_SESSION['profile_picture'];
                    } elseif (class_exists('Database')) {
                        try {
                            $nav_db = (new Database())->getConnection();
                            $av_stmt = $nav_db->prepare("SELECT profile_picture FROM users WHERE user_id = :uid LIMIT 1");
                            $av_stmt->execute([':uid' => $_SESSION['user_id']]);
                            $av_res = $av_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($av_res && !empty($av_res['profile_picture']) && file_exists($av_res['profile_picture'])) {
                                $nav_profile_pic = $av_res['profile_picture'];
                                $_SESSION['profile_picture'] = $nav_profile_pic;
                            }
                        } catch (Exception $e) {}
                    }
                }
                ?>

                <?php if ($is_nav_home): ?>
                    <!-- Logged-in Home Page (index.php): Avatar with back-to-dashboard arrow badge (No public/user dropdown menu per Requirement 1) -->
                    <a href="<?php echo $user_dash_link; ?>" class="nav-home-dashboard-btn" title="Back to Dashboard" aria-label="Back to Dashboard">
                        <div class="nav-home-avatar-wrap">
                            <?php if (!empty($nav_profile_pic)): ?>
                                <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" class="nav-home-avatar-img">
                            <?php else: ?>
                                <div class="nav-home-avatar-initials">
                                    <?php echo substr($initials, 0, 2); ?>
                                </div>
                            <?php endif; ?>
                            <span class="nav-home-arrow-badge" title="Back to Dashboard">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                        <span class="nav-home-dash-label">Dashboard</span>
                    </a>
                <?php else: ?>
                    <!-- Normal User Dropdown on other pages using navbar.php -->
                    <div class="nav-user-dropdown" id="userDropdown">
                        <button class="user-pill" id="userPillBtn" aria-expanded="false">
                            <div class="user-avatar-sm">
                                <?php if (!empty($nav_profile_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <?php echo substr($initials, 0, 2); ?>
                                <?php endif; ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'User')[0]); ?></span>
                            <i class="fas fa-chevron-down chevron-icon"></i>
                        </button>

                        <div class="user-dropdown-menu" id="userDropdownMenu">
                            <div class="dropdown-header">
                                <div class="user-avatar-lg">
                                    <?php if (!empty($nav_profile_pic)): ?>
                                        <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo substr($initials, 0, 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
                                    <p class="dropdown-role"><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></p>
                                </div>
                            </div>
                            <div class="dropdown-body">
                                <?php if(!isStaff() && !isAdmin()): ?>
                                     <a href="dashboard.php"       class="dropdown-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                                     <a href="profile.php"         class="dropdown-item"><i class="fas fa-user-circle"></i> Profile</a>
                                     <a href="my-appointments.php" class="dropdown-item"><i class="fas fa-calendar-check"></i> My Appointments</a>
                                     <a href="documents.php"       class="dropdown-item"><i class="fas fa-file-alt"></i> My Documents</a>
                                     <a href="my-housing.php"      class="dropdown-item"><i class="fas fa-home"></i> My Housing</a>
                                     <a href="my-aics.php"         class="dropdown-item"><i class="fas fa-hand-holding-heart"></i> My AICS</a>
                                     <a href="calendar.php"        class="dropdown-item"><i class="fas fa-calendar-alt"></i> Event Calendar</a>
                                     <a href="print-forms.php"     class="dropdown-item"><i class="fas fa-print"></i> Printable Forms</a>
                                     <a href="feedback.php"        class="dropdown-item"><i class="fas fa-comments"></i> Rate Us / Feedback</a>
                                     <a href="settings.php"        class="dropdown-item"><i class="fas fa-cog"></i> Settings</a>
                                     <div class="dropdown-divider"></div>
                                <?php endif; ?>
                                <?php if(isStaff() || isAdmin()): ?>
                                    <?php
                                    if (isAdmin()) {
                                        $dash_target = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : 'admin/dashboard.php';
                                        $dash_label = 'Admin Dashboard';
                                    } else {
                                        $dash_target = (strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../staff-dashboard.php' : 'staff-dashboard.php';
                                        $dash_label = 'Staff Dashboard';
                                    }
                                    ?>
                                    <a href="<?php echo $dash_target; ?>" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i>
                                        <?php echo $dash_label; ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php endif; ?>
                                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../logout.php' : 'logout.php'; ?>"
                                   class="dropdown-item dropdown-item-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if(isLoggedIn()): ?>
                <!-- Mobile Home Button beside 3 lines (Mobile only) -->
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>" class="mobile-nav-home-btn" aria-label="Home" title="Home">
                    <i class="fas fa-home"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════
     SIDE DRAWER BACKDROP
═══════════════════════════════════════════════════ -->
<div class="drawer-backdrop" id="drawerBackdrop" aria-hidden="true"></div>

<!-- ═══════════════════════════════════════════════════
     SIDE NAVIGATION DRAWER
═══════════════════════════════════════════════════ -->
<aside class="side-drawer" id="sideDrawer" role="navigation" aria-label="Mobile navigation">

    <!-- Drawer Header -->
    <div class="drawer-header">
        <?php
        $drawer_rel_prefix = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../' : '';
        $drawer_logo_src = function_exists('cms_image') ? cms_image('site_logo', $drawer_rel_prefix . 'images/logo.png') : ($drawer_rel_prefix . 'images/logo.png');
        ?>
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>" class="drawer-brand" id="drawerBrandLink">
            <div class="drawer-logo-circle">
                <img src="<?php echo htmlspecialchars($drawer_logo_src); ?>?v=<?php echo @filemtime(__DIR__ . '/../images/logo.png') ?: time(); ?>" alt="Logo" onerror="this.src='<?php echo $drawer_rel_prefix; ?>images/logo.png'">
            </div>
            <div class="drawer-brand-text">
                <span class="drawer-brand-title">ONE PALAYAN</span>
                <span class="drawer-brand-sub">KALINGA HUB</span>
            </div>
        </a>
        <button class="drawer-close" id="drawerClose" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- User Info (logged in) -->
    <?php if(isLoggedIn()): ?>
    <div class="drawer-user-card">
        <div class="drawer-avatar">
            <?php if (!empty($nav_profile_pic)): ?>
                <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <?php echo substr($initials, 0, 2); ?>
            <?php endif; ?>
        </div>
        <div class="drawer-user-info">
            <p class="drawer-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
            <p class="drawer-user-role">
                <?php
                if(isAdmin()) echo 'Administrator';
                elseif(isStaff()) echo 'Staff Member';
                else echo 'Resident';
                ?>
            </p>
        </div>
    </div>
    <?php if ($is_nav_home): ?>
    <div style="padding: 10px 16px 4px;">
        <a href="<?php echo $user_dash_link; ?>" class="drawer-link drawer-link--active" style="background: #7F0303; color: #FFFFFF; border-radius: 12px; justify-content: center; font-weight: 700; gap: 8px;">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Drawer Navigation -->
    <nav class="drawer-nav">

        <!-- ── MAIN PAGES ── -->
        <div class="drawer-section-label">Main</div>
        <ul class="drawer-links">
            <?php if(isLoggedIn()): ?>
            <li>
                <a href="<?php echo $user_dash_link; ?>"
                   class="drawer-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == 'staff-dashboard.php') ? 'drawer-link--active' : ''; ?>"
                   style="background: linear-gradient(135deg, rgba(127,3,3,0.08), rgba(15,65,74,0.08)); font-weight: 700;">
                    <span class="drawer-link-icon" style="color: var(--maroon);"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-home"></i></span>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../services.php' : 'services.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'drawer-link--active' : ''; ?>" onclick="if(typeof closeDrawer==='function')closeDrawer();">
                    <span class="drawer-link-icon"><i class="fas fa-concierge-bell"></i></span>
                    <span>Services</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../about.php' : 'about.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-info-circle"></i></span>
                    <span>About</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../contact.php' : 'contact.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-envelope"></i></span>
                    <span>Contact Us</span>
                </a>
            </li>
        </ul>

        <!-- ── RESIDENT: MY ACCOUNT ── -->
        <?php if(isLoggedIn() && !isStaff() && !isAdmin()): ?>
        <div class="drawer-section-label">My Account</div>
        <ul class="drawer-links">
            <li>
                <a href="dashboard.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="profile.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-user-circle"></i></span>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="my-appointments.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-appointments.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-calendar-check"></i></span>
                    <span>My Appointments</span>
                </a>
            </li>
            <li>
                <a href="documents.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-file-alt"></i></span>
                    <span>My Documents</span>
                </a>
            </li>
            <li>
                <a href="my-housing.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-housing.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-home"></i></span>
                    <span>My Housing</span>
                </a>
            </li>
            <li>
                <a href="my-aics.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-aics.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-hand-holding-heart"></i></span>
                    <span>My AICS</span>
                </a>
            </li>
            <li>
                <a href="notifications.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-bell"></i></span>
                    <span>Notifications</span>
                    <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="drawer-badge"><?php echo min($unread_count, 99); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <div class="drawer-section-label">Apply</div>
        <ul class="drawer-links">
            <li>
                <a href="apply-aics.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'apply-aics.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-hands-helping"></i></span>
                    <span>Apply for AICS</span>
                </a>
            </li>
            <li>
                <a href="apply-housing.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'apply-housing.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-building"></i></span>
                    <span>Apply for Housing</span>
                </a>
            </li>
            <li>
                <a href="book-appointment.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'book-appointment.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-calendar-plus"></i></span>
                    <span>Book Appointment</span>
                </a>
            </li>
            <li>
                <a href="calendar.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span>Event Calendar</span>
                </a>
            </li>
            <li>
                <a href="print-forms.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'print-forms.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-print"></i></span>
                    <span>Printable Forms</span>
                </a>
            </li>
            <li>
                <a href="feedback.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-comments"></i></span>
                    <span>Feedback & Rate Us</span>
                </a>
            </li>
        </ul>

        <div class="drawer-section-label">Settings</div>
        <ul class="drawer-links">
            <li>
                <a href="settings.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-cog"></i></span>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="change-password.php"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'change-password.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-key"></i></span>
                    <span>Change Password</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- ── STAFF / ADMIN SECTION ── -->
        <?php if(isLoggedIn() && (isStaff() || isAdmin())): ?>
        <div class="drawer-section-label">Management</div>
        <ul class="drawer-links">
            <li>
                <?php
                if (isAdmin()) {
                    $drawer_dash_target = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : 'admin/dashboard.php';
                    $drawer_dash_active = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false && basename($_SERVER['PHP_SELF']) == 'dashboard.php');
                } else {
                    $drawer_dash_target = (strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../staff-dashboard.php' : 'staff-dashboard.php';
                    $drawer_dash_active = (basename($_SERVER['PHP_SELF']) == 'staff-dashboard.php');
                }
                ?>
                <a href="<?php echo $drawer_dash_target; ?>"
                   class="drawer-link <?php echo $drawer_dash_active ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span><?php echo isAdmin() ? 'Admin Dashboard' : 'Staff Dashboard'; ?></span>
                </a>
            </li>
            <?php if(isAdmin()): ?>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'users.php' : 'admin/users.php'; ?>"
                   class="drawer-link">
                    <span class="drawer-link-icon"><i class="fas fa-users"></i></span>
                    <span>Manage Users</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../manage-aics.php' : 'manage-aics.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-aics.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-hand-holding-heart"></i></span>
                    <span>Manage AICS</span>
                </a>
            </li>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../manage-appointments.php' : 'manage-appointments.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-appointments.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-calendar-check"></i></span>
                    <span>Manage Appointments</span>
                </a>
            </li>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../manage-housing.php' : 'manage-housing.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-housing.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-building"></i></span>
                    <span>Manage Housing</span>
                </a>
            </li>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../reports.php' : 'reports.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false || strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../verify-resident.php' : 'verify-resident.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'verify-resident.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-user-check"></i></span>
                    <span>Verify Residents</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- ── GUEST: AUTH ACTIONS ── -->
        <?php if(!isLoggedIn()): ?>
        <div class="drawer-section-label">Account</div>
        <ul class="drawer-links">
            <li>
                <a href="login.php" class="drawer-link drawer-link--login">
                    <span class="drawer-link-icon"><i class="fas fa-sign-in-alt"></i></span>
                    <span>Log In</span>
                </a>
            </li>
            <li>
                <a href="register.php" class="drawer-link drawer-link--register">
                    <span class="drawer-link-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Create Account</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- ── LOGOUT (logged-in users) ── -->
        <?php if(isLoggedIn()): ?>
        <div class="drawer-divider"></div>
        <ul class="drawer-links">
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../logout.php' : 'logout.php'; ?>"
                   class="drawer-link drawer-link--danger">
                    <span class="drawer-link-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

    </nav>

    <!-- Drawer Footer -->
    <div class="drawer-footer">
        <p>ONE PALAYAN-KALINGA HUB</p>
        <p>CSWDO Palayan City &copy; <?php echo date('Y'); ?></p>
    </div>
</aside>

<!-- Unified Sticky Mobile Bottom Tab Bar (Enabled for index/public pages; hidden on dashboard) -->
<?php include_once __DIR__ . '/mobile-bottom-nav.php'; ?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../css/global.css' : 'css/global.css'; ?>">

<style>
/* =====================================================================
   Unified Navbar — One Palayan-Kalinga Hub
   ===================================================================== */

:root {
    --maroon: #7F0303;
    --maroon-light: #9a1515;
    --midnight: #0F414A;
    --gold: #C9A86C;
    --tan: #D8BA98;
    --light-blue: #96C0CE;
    --bg-card: #FFFFFF;
    --text-primary: #263238;
    --text-secondary: #3A4A5A;
    --text-muted: #6B7A8A;
    --border-color: #C8D0DC;
    --bg-primary: #E4EFE7;
}

[data-theme="dark"] {
    --bg-card: #1a2332;
    --text-primary: #E8EDF5;
    --text-secondary: #B0C4DE;
    --text-muted: #6B85A0;
    --border-color: #2a3a4f;
    --bg-primary: #0C1417;
}

/* ─── Base navbar: Floating Oval Header Design (Enlarged slightly, across all pages) ─── */
.navbar {
    position: fixed;
    top: 18px;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 36px);
    max-width: 1260px;
    z-index: 1000;
    padding: 8px 24px 8px 18px;
    background: rgba(15, 65, 74, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.24);
    transition: background 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.navbar.scrolled {
    top: 12px;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.14);
}

/* ─── Inner container ─── */
.nav-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 0;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}

.navbar.scrolled .nav-container {
    height: 54px;
}

/* Remove Night Mode button on public header per user request */
.theme-toggle {
    display: none !important;
}

/* ─── Brand ─── */
.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    flex-shrink: 0;
}

.brand-logo-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.18);
    transition: transform 0.3s ease;
    overflow: hidden;
    flex-shrink: 0;
    padding: 0;
}

.brand-logo-circle:hover {
    transform: scale(1.05) rotate(-5deg);
}

.brand-logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.brand-text {
    display: flex;
    flex-direction: column;
}

.brand-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: white;
    letter-spacing: -0.2px;
    line-height: 1.2;
    transition: color 0.3s;
}

.brand-sub {
    font-size: 0.65rem;
    background: var(--tan);
    color: #3b2a1f;
    padding: 1px 8px;
    border-radius: 20px;
    font-weight: 600;
    width: fit-content;
    margin-top: 2px;
}

.navbar.scrolled .brand-title {
    color: var(--midnight) !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    background-clip: unset !important;
}

/* ─── Desktop nav links ─── */
.nav-links {
    display: flex;
    align-items: center;
    gap: 12px;
    list-style: none;
    margin: 0;
    padding: 0;
    flex: 1;
    justify-content: center;
}

.nav-links li a {
    text-decoration: none;
    color: rgba(255,255,255,0.82);
    font-weight: 500;
    font-size: 0.88rem;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.25s ease;
    position: relative;
    white-space: nowrap;
}

.nav-links li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 12px;
    right: 12px;
    height: 2px;
    background: var(--gold);
    border-radius: 2px;
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.nav-links li a:hover {
    color: white;
    background: rgba(255,255,255,0.08);
}

.nav-links li a:hover::after,
.nav-links li a.active::after {
    transform: scaleX(1);
}

.nav-links li a.active {
    color: white;
    font-weight: 600;
}

.navbar.scrolled .nav-links li a {
    color: var(--text-secondary);
}

.navbar.scrolled .nav-links li a:hover {
    color: var(--maroon);
    background: rgba(127,3,3,0.06);
}

.navbar.scrolled .nav-links li a.active {
    color: var(--maroon);
}

.navbar.scrolled .nav-links li a::after {
    background: var(--maroon);
}

/* ─── Nav Actions (right side) ─── */
.nav-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
}

/* Theme toggle */
.theme-toggle {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: white;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.theme-toggle:hover {
    background: rgba(255,255,255,0.2);
    transform: rotate(20deg) scale(1.05);
}

.navbar.scrolled .theme-toggle {
    background: var(--bg-primary);
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.navbar.scrolled .theme-toggle:hover {
    background: var(--maroon);
    border-color: var(--maroon);
    color: white;
}

/* Notification bell */
.notif-bell {
    position: relative;
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.notif-bell:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

.navbar.scrolled .notif-bell {
    background: var(--bg-primary);
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.notif-count {
    position: absolute;
    top: -4px;
    right: -4px;
    background: var(--maroon);
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--midnight);
}

.navbar.scrolled .notif-count {
    border-color: var(--bg-card);
}

/* Nav Buttons */
.nav-btn {
    padding: 6px 15px;
    border-radius: 40px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.nav-btn-outline {
    background: transparent;
    border: 1.5px solid rgba(255, 255, 255, 0.45);
    color: #FFFFFF !important;
    padding: 0.44rem 1.15rem;
    border-radius: 40px;
    font-weight: 700;
    font-size: 0.84rem;
}

.nav-btn-outline:hover {
    background: rgba(255, 255, 255, 0.16);
    border-color: #FFFFFF;
    color: #FFFFFF !important;
    transform: translateY(-1px);
}

.navbar.scrolled .nav-btn-outline {
    border-color: rgba(0, 0, 0, 0.18);
    color: var(--text-primary) !important;
}

.navbar.scrolled .nav-btn-outline:hover {
    border-color: var(--maroon);
    color: var(--maroon) !important;
    background: rgba(127, 3, 3, 0.06);
}

.nav-btn-solid {
    background: transparent; /* copies color bg of login per user request */
    color: #FFFFFF !important; /* white when at top / scrolling up */
    border: 1.5px solid rgba(255, 255, 255, 0.85);
    padding: 0.44rem 1.25rem;
    border-radius: 40px;
    font-weight: 700;
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.nav-btn-solid:hover {
    background: rgba(255, 255, 255, 0.20);
    border-color: #FFFFFF;
    color: #FFFFFF !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(255, 255, 255, 0.25);
}

.navbar.scrolled .nav-btn-solid {
    background: var(--maroon);
    border-color: var(--maroon);
    color: #FFFFFF !important;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
}

.navbar.scrolled .nav-btn-solid:hover {
    background: var(--maroon-light);
    border-color: var(--maroon-light);
    color: #FFFFFF !important;
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.35);
}

/* ─── Logged-In Home Button: Profile Avatar + Back-to-Dashboard Arrow (Req 1) ─── */
.nav-home-dashboard-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 4px 14px 4px 5px;
    background: rgba(255, 255, 255, 0.14);
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    border-radius: 9999px;
    text-decoration: none;
    color: #FFFFFF;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
}

.nav-home-dashboard-btn:hover {
    background: #FFFFFF;
    border-color: #FFFFFF;
    color: #7F0303;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.navbar.scrolled .nav-home-dashboard-btn {
    background: var(--bg-primary, #FFFFFF);
    border-color: var(--border-color, #E2E8F0);
    color: var(--text-primary, #192126);
}

.navbar.scrolled .nav-home-dashboard-btn:hover {
    background: #7F0303;
    border-color: #7F0303;
    color: #FFFFFF;
}

.nav-home-avatar-wrap {
    position: relative;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
}

.nav-home-avatar-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #E5B338;
    display: block;
}

.nav-home-avatar-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%);
    color: #FFFFFF;
    font-size: 0.76rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #E5B338;
}

.nav-home-arrow-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #E5B338;
    color: #192126;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.58rem;
    border: 1.5px solid #FFFFFF;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.nav-home-dashboard-btn:hover .nav-home-arrow-badge {
    transform: scale(1.15) translateX(1px);
    background: #7F0303;
    color: #FFFFFF;
}

.nav-home-dash-label {
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.2px;
}

[data-theme="dark"] .nav-home-dashboard-btn {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
    color: #FFFFFF;
}

[data-theme="dark"] .navbar.scrolled .nav-home-dashboard-btn {
    background: #1A2332;
    border-color: rgba(255, 255, 255, 0.12);
    color: #E5EFF2;
}

[data-theme="dark"] .navbar.scrolled .nav-home-dashboard-btn:hover {
    background: #8B1A1A;
    border-color: #8B1A1A;
    color: #FFFFFF;
}

/* ─── User Dropdown ─── */
.nav-user-dropdown {
    position: relative;
}

.user-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 40px;
    padding: 5px 14px 5px 6px;
    cursor: pointer;
    color: white;
    font-size: 0.83rem;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.user-pill:hover {
    background: rgba(255,255,255,0.18);
}

.navbar.scrolled .user-pill {
    background: var(--bg-primary);
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.navbar.scrolled .user-pill:hover {
    border-color: var(--maroon);
    color: var(--maroon);
}

.user-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--maroon), var(--midnight));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 700;
    flex-shrink: 0;
}

.chevron-icon {
    font-size: 0.65rem;
    transition: transform 0.3s ease;
}

.user-pill[aria-expanded="true"] .chevron-icon {
    transform: rotate(180deg);
}

/* Dropdown panel */
.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    min-width: 240px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(0.97);
    transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    z-index: 1001;
    overflow: hidden;
}

.user-dropdown-menu.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.dropdown-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-primary);
}

.user-avatar-lg {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--maroon), var(--midnight));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    flex-shrink: 0;
}

.dropdown-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.dropdown-role {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 2px 0 0;
    text-transform: capitalize;
}

.dropdown-body {
    padding: 8px 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 18px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.dropdown-item i {
    width: 18px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.dropdown-item:hover {
    background: var(--bg-primary);
    color: var(--maroon);
    padding-left: 22px;
}

.dropdown-item:hover i {
    color: var(--maroon);
}

.dropdown-item-danger {
    color: #c62828;
}

.dropdown-item-danger i { color: #c62828; }

.dropdown-item-danger:hover {
    background: rgba(239,83,80,0.08);
    color: #b71c1c;
}

.dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 6px 0;
}

/* ─── Mobile Home Button (Hidden on Desktop) ─── */
.mobile-nav-home-btn {
    display: none;
}

/* ─── Hamburger ─── */
.hamburger {
    display: none;
    flex-direction: column;
    gap: 5px;
    cursor: pointer;
    background: none;
    border: none;
    padding: 6px;
    z-index: 1100;
}

.hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: white;
    border-radius: 2px;
    transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
}

.navbar.scrolled .hamburger span { background: var(--text-primary); }

.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
.hamburger.active span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

/* ═══════════════════════════════════════════════════
   DRAWER BACKDROP
═══════════════════════════════════════════════════ */
.drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 1099;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.35s ease, visibility 0.35s ease;
}

.drawer-backdrop.open {
    opacity: 1;
    visibility: visible;
}

/* ═══════════════════════════════════════════════════
   SIDE DRAWER
═══════════════════════════════════════════════════ */
.side-drawer {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 310px;
    max-width: 88vw;
    background: var(--bg-card);
    z-index: 1100;
    display: flex;
    flex-direction: column;
    transform: translateX(-100%);
    transition: transform 0.38s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 40px rgba(0,0,0,0.18);
    overflow: hidden;
    /* Only visible on mobile */
    display: none;
}

.side-drawer.open {
    transform: translateX(0);
}

/* ── Drawer Header ── */
.drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: 68px;
    background: var(--midnight);
    flex-shrink: 0;
}

.drawer-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.drawer-logo-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    padding: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.drawer-logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.drawer-brand-text {
    display: flex;
    flex-direction: column;
}

.drawer-brand-title {
    font-size: 0.78rem;
    font-weight: 800;
    color: white;
    line-height: 1.2;
    letter-spacing: 0.3px;
}

.drawer-brand-sub {
    font-size: 0.6rem;
    color: var(--tan);
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-top: 1px;
}

.drawer-close {
    width: 34px;
    height: 34px;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    color: white;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.drawer-close:hover {
    background: rgba(255,255,255,0.18);
    transform: rotate(90deg);
}

/* ── User Card ── */
.drawer-user-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    background: linear-gradient(135deg, rgba(15,65,74,0.12), rgba(127,3,3,0.08));
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.drawer-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--maroon), var(--midnight));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(127,3,3,0.25);
}

.drawer-user-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.3;
}

.drawer-user-role {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 2px 0 0;
    text-transform: capitalize;
}

/* ── Drawer Nav (scrollable) ── */
.drawer-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 8px 0 16px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) transparent;
}

.drawer-nav::-webkit-scrollbar {
    width: 4px;
}
.drawer-nav::-webkit-scrollbar-track { background: transparent; }
.drawer-nav::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

/* Section labels */
.drawer-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--text-muted);
    padding: 14px 20px 6px;
}

/* Drawer links list */
.drawer-links {
    list-style: none;
    padding: 0 10px;
    margin: 0 0 4px;
}

.drawer-link {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 10px 12px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s ease;
    position: relative;
    border-left: 3px solid transparent;
}

.drawer-link:hover {
    background: linear-gradient(90deg, rgba(127,3,3,0.07), transparent);
    color: var(--maroon);
    border-left-color: var(--maroon);
    padding-left: 15px;
}

.drawer-link--active {
    background: linear-gradient(90deg, rgba(127,3,3,0.1), rgba(127,3,3,0.03));
    color: var(--maroon);
    border-left-color: var(--maroon);
    font-weight: 600;
}

.drawer-link-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--bg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    color: var(--text-muted);
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.drawer-link:hover .drawer-link-icon,
.drawer-link--active .drawer-link-icon {
    background: rgba(127,3,3,0.12);
    color: var(--maroon);
}

/* Login / Register special styles */
.drawer-link--login {
    color: var(--midnight);
    border: 1.5px solid var(--border-color);
    margin-bottom: 6px;
}

.drawer-link--login:hover {
    border-color: var(--midnight);
    background: rgba(15,65,74,0.07);
    color: var(--midnight);
}

.drawer-link--register {
    background: var(--maroon);
    color: white;
    border-left-color: transparent;
}

.drawer-link--register:hover {
    background: var(--maroon-light);
    color: white;
    border-left-color: transparent;
    box-shadow: 0 4px 14px rgba(127,3,3,0.3);
    transform: translateY(-1px);
}

.drawer-link--register .drawer-link-icon {
    background: rgba(255,255,255,0.18);
    color: white;
}

.drawer-link--register:hover .drawer-link-icon {
    background: rgba(255,255,255,0.25);
    color: white;
}

/* Danger / Logout */
.drawer-link--danger {
    color: #c62828;
}

.drawer-link--danger:hover {
    background: rgba(239,83,80,0.07);
    color: #b71c1c;
    border-left-color: #ef5350;
}

.drawer-link--danger .drawer-link-icon { color: #c62828; }
.drawer-link--danger:hover .drawer-link-icon {
    background: rgba(239,83,80,0.12);
    color: #b71c1c;
}

/* Notification badge in drawer */
.drawer-badge {
    margin-left: auto;
    background: var(--maroon);
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
}

/* Drawer divider */
.drawer-divider {
    height: 1px;
    background: var(--border-color);
    margin: 10px 18px;
}

/* ── Drawer Footer ── */
.drawer-footer {
    padding: 12px 18px;
    border-top: 1px solid var(--border-color);
    flex-shrink: 0;
    background: var(--bg-primary);
}

.drawer-footer p {
    font-size: 0.65rem;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.6;
    text-align: center;
}

/* ─── Responsive breakpoints ─── */
@media (max-width: 1024px) {
    .nav-links { gap: 4px; }
    .nav-links li a { font-size: 0.82rem; padding: 6px 8px; }
}

@media (max-width: 768px) {
    .navbar {
        padding: 5px 12px 5px 8px !important;
    }
    .navbar.scrolled {
        padding: 4px 12px 4px 8px !important;
    }

    .nav-container {
        height: 56px !important;
        padding: 0 2px !important;
        gap: 6px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
    }
    .navbar.scrolled .nav-container {
        height: 52px !important;
    }

    /* Improved Hamburger Button placed on the Left side before Logo */
    .hamburger {
        display: inline-flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 4px !important;
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        margin-left: 0 !important;
        border-radius: 10px !important;
        background: rgba(255, 255, 255, 0.15) !important;
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        padding: 0 !important;
        cursor: pointer !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12) !important;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
        flex-shrink: 0 !important;
        order: 1 !important;
    }
    .hamburger:active {
        transform: scale(0.92) !important;
        background: rgba(255, 255, 255, 0.28) !important;
    }
    .hamburger span {
        display: block !important;
        width: 19px !important;
        height: 2.2px !important;
        background: #FFFFFF !important;
        border-radius: 3px !important;
        transition: all 0.25s ease !important;
    }
    .navbar.scrolled .hamburger {
        background: rgba(15, 65, 74, 0.08) !important;
        border-color: rgba(15, 65, 74, 0.15) !important;
    }
    .navbar.scrolled .hamburger span {
        background: var(--midnight, #0F414A) !important;
    }
    [data-theme="dark"] .navbar.scrolled .hamburger {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.18) !important;
    }
    [data-theme="dark"] .navbar.scrolled .hamburger span {
        background: #FFFFFF !important;
    }

    /* Brand Section beside Hamburger */
    .nav-brand {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        flex: 1 !important;
        min-width: 0 !important;
        text-decoration: none !important;
        order: 2 !important;
    }
    .brand-logo-circle {
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;
        flex-shrink: 0 !important;
    }
    .brand-logo-circle img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }
    .brand-text {
        flex: 1 !important;
        min-width: 0 !important;
        overflow: visible !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
    }
    .brand-title {
        font-size: 0.82rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.2px !important;
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
        color: #FFFFFF !important;
        line-height: 1.2 !important;
    }
    .navbar.scrolled .brand-title {
        color: var(--midnight, #0F414A) !important;
    }
    [data-theme="dark"] .navbar.scrolled .brand-title {
        color: #FFFFFF !important;
    }
    .brand-sub {
        font-size: 0.62rem !important;
        opacity: 0.8 !important;
        white-space: nowrap !important;
        overflow: visible !important;
        line-height: 1.1 !important;
    }

    /* Hide Right Actions (Theme toggle, etc.) on Mobile Header so System Name is 100% Complete & Visible */
    .nav-actions {
        display: none !important;
    }
    .theme-toggle {
        display: none !important;
    }
    .mobile-nav-home-btn {
        display: none !important;
    }

    .nav-links  { display: none; }

    .nav-btn-outline,
    .nav-btn-solid { display: none; }

    .notif-bell { display: none !important; } /* Hidden on mobile per user request */

    /* Show the side drawer on mobile */
    .side-drawer { display: flex; }

    /* Hide desktop user pill on small */
    .nav-user-dropdown { display: none; }
}

@media (max-width: 480px) {
    .navbar { padding: 5px 10px 5px 6px !important; }
    .nav-container { padding: 0 2px !important; gap: 6px !important; }
    .brand-sub { display: none; }
}

/* ─── Dark mode drawer tweaks ─── */
[data-theme="dark"] .drawer-header {
    background: #0a1a22;
}

[data-theme="dark"] .drawer-user-card {
    background: linear-gradient(135deg, rgba(15,65,74,0.25), rgba(127,3,3,0.15));
}

[data-theme="dark"] .drawer-link--login {
    color: var(--text-secondary);
}

[data-theme="dark"] .drawer-footer {
    background: #0d1a24;
}
</style>

<script>
(function() {
    // ─── Scroll Effect ───────────────────────────────────────────────
    const navbar = document.getElementById('mainNavbar');

    function updateNavbarScroll() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
    window.addEventListener('scroll', updateNavbarScroll, { passive: true });
    updateNavbarScroll(); // run once on load

    // ─── Side Drawer ─────────────────────────────────────────────────
    const hamburger     = document.getElementById('hamburger');
    const sideDrawer    = document.getElementById('sideDrawer');
    const drawerBackdrop = document.getElementById('drawerBackdrop');
    const drawerClose   = document.getElementById('drawerClose');

    function openDrawer() {
        if (sideDrawer) sideDrawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('open');
        if (hamburger) {
            hamburger.classList.add('active');
            hamburger.setAttribute('aria-expanded', 'true');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (sideDrawer) sideDrawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('open');
        if (hamburger) {
            hamburger.classList.remove('active');
            hamburger.setAttribute('aria-expanded', 'false');
        }
        document.body.style.overflow = '';
    }

    window.openDrawer = openDrawer;
    window.closeDrawer = closeDrawer;

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            if (sideDrawer.classList.contains('open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    // Close on backdrop click
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }

    // Close button inside drawer
    if (drawerClose) {
        drawerClose.addEventListener('click', closeDrawer);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sideDrawer.classList.contains('open')) {
            closeDrawer();
        }
    });

    // Close on drawer link click (navigating away)
    document.querySelectorAll('.drawer-link').forEach(function (link) {
        link.addEventListener('click', function () {
            closeDrawer();
        });
    });

    // ─── User Dropdown ───────────────────────────────────────────────
    const userPillBtn      = document.getElementById('userPillBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');

    if (userPillBtn && userDropdownMenu) {
        userPillBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = userDropdownMenu.classList.contains('open');
            userDropdownMenu.classList.toggle('open');
            userPillBtn.setAttribute('aria-expanded', !isOpen);
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!userPillBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.remove('open');
                userPillBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ─── Dark Mode Toggle ────────────────────────────────────────────
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon   = document.getElementById('themeIcon');

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (document.body) {
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        }
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        document.cookie = 'dark_mode=' + (theme === 'dark' ? 'enabled' : 'disabled') + '; path=/; max-age=' + (365*24*3600);
    }

    // Read saved preference
    const savedTheme = document.cookie.match(/dark_mode=([^;]+)/)?.[1];
    if (savedTheme === 'enabled') {
        applyTheme('dark');
    } else {
        applyTheme('light');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme');
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // Double-ensure body class matches on DOM load to prevent any timing issues
    document.addEventListener('DOMContentLoaded', function () {
        const current = document.documentElement.getAttribute('data-theme');
        if (current === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    });

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // ─── Real-time Notification Badges ──────────────────────────────
    <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
    function checkNotifications() {
        const isSubDir = window.location.pathname.includes('/admin/') || window.location.pathname.includes('/staff/');
        const ajaxUrl = isSubDir ? '../get-unread-notifications-ajax.php' : 'get-unread-notifications-ajax.php';
        
        fetch(ajaxUrl)
            .then(response => {
                if (response.ok) return response.json();
                throw new Error('Network response not ok');
            })
            .then(data => {
                if (data && data.hasOwnProperty('unread_count')) {
                    const count = data.unread_count;
                    const bell = document.querySelector('.notif-bell');
                    if (bell) {
                        let badge = bell.querySelector('.notif-count');
                        if (count > 0) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'notif-count';
                                bell.appendChild(badge);
                            }
                            badge.textContent = Math.min(count, 99);
                        } else if (badge) {
                            badge.remove();
                        }
                    }
                }
            })
            .catch(err => console.error('Notification check failed:', err));
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        checkNotifications();
        setInterval(checkNotifications, 60000);
    });
    <?php endif; ?>
})();
</script>

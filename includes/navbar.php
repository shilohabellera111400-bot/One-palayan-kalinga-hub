<?php
// This file contains the navigation bar that's used across the site
?>
<nav class="navbar" id="mainNavbar">
    <div class="nav-container">
        <!-- Brand -->
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>" class="nav-brand">
            <div class="brand-logo-circle">
                <?php
                $logo_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../images/logo.png' : 'images/logo.png';
                ?>
                <img src="<?php echo $logo_path; ?>" alt="One Palayan-Kalinga Hub Logo">
            </div>
            <div class="brand-text">
                <span class="brand-title">ONE PALAYAN-KALINGA HUB</span>
                <span class="brand-sub">CSWDO Palayan City</span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <ul class="nav-links" id="navLinks">
            <!-- Public links -->
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Home</a></li>
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../services.php' : 'services.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'class="active"' : ''; ?>>Services</a></li>
            <li><a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../announcements.php' : 'announcements.php'; ?>"
                   <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'class="active"' : ''; ?>>Announcements</a></li>

            <?php if(isLoggedIn() && !isStaff() && !isAdmin()): ?>
                <li><a href="appointments.php" <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'class="active"' : ''; ?>>Appointments</a></li>
                <li><a href="track-aics.php"   <?php echo basename($_SERVER['PHP_SELF']) == 'track-aics.php'   ? 'class="active"' : ''; ?>>Track AICS</a></li>
                <li><a href="housing.php"       <?php echo basename($_SERVER['PHP_SELF']) == 'housing.php'       ? 'class="active"' : ''; ?>>Housing</a></li>
            <?php endif; ?>

            <?php if(!isLoggedIn()): ?>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="track-aics.php">Track AICS</a></li>
                <li><a href="housing.php">Housing</a></li>
            <?php endif; ?>
        </ul>

        <!-- Right Actions -->
        <div class="nav-actions">
            <!-- Dark Mode Toggle -->
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <?php if(!isLoggedIn()): ?>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../login.php' : 'login.php'; ?>" class="nav-btn nav-btn-outline">Log In</a>
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

                <!-- User Dropdown -->
                <div class="nav-user-dropdown" id="userDropdown">
                    <button class="user-pill" id="userPillBtn" aria-expanded="false">
                        <div class="user-avatar-sm">
                            <?php
                            $name_parts = explode(' ', $_SESSION['full_name'] ?? 'User');
                            $initials = '';
                            foreach($name_parts as $part) { $initials .= strtoupper(substr($part, 0, 1)); }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <span><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'User')[0]); ?></span>
                        <i class="fas fa-chevron-down chevron-icon"></i>
                    </button>

                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar-lg">
                                <?php echo substr($initials, 0, 2); ?>
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
                                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : 'staff-dashboard.php'; ?>" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <?php echo isAdmin() ? 'Admin Dashboard' : 'Staff Dashboard'; ?>
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

            <!-- Hamburger -->
            <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
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
        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>" class="drawer-brand" id="drawerBrandLink">
            <div class="drawer-logo-circle">
                <img src="<?php echo $logo_path; ?>" alt="Logo">
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
            <?php echo substr($initials, 0, 2); ?>
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
    <?php endif; ?>

    <!-- Drawer Navigation -->
    <nav class="drawer-nav">

        <!-- ── MAIN PAGES ── -->
        <div class="drawer-section-label">Main</div>
        <ul class="drawer-links">
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../index.php' : 'index.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-home"></i></span>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../services.php' : 'services.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-concierge-bell"></i></span>
                    <span>Services</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../appointments.php' : 'appointments.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span>Appointments</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../track-aics.php' : 'track-aics.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'track-aics.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-search"></i></span>
                    <span>Track AICS</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../housing.php' : 'housing.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'housing.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-house-user"></i></span>
                    <span>Housing</span>
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
                    <span>Contact</span>
                </a>
            </li>
            <li>
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../faqs.php' : 'faqs.php'; ?>"
                   class="drawer-link <?php echo basename($_SERVER['PHP_SELF']) == 'faqs.php' ? 'drawer-link--active' : ''; ?>">
                    <span class="drawer-link-icon"><i class="fas fa-question-circle"></i></span>
                    <span>FAQs</span>
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
                <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'dashboard.php' : 'staff-dashboard.php'; ?>"
                   class="drawer-link <?php echo (basename($_SERVER['PHP_SELF']) == 'staff-dashboard.php' || basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'drawer-link--active' : ''; ?>">
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
            <li>
                <a href="staff-login.php" class="drawer-link">
                    <span class="drawer-link-icon"><i class="fas fa-id-badge"></i></span>
                    <span>Staff Login</span>
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

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../css/global.css' : 'css/global.css'; ?>">

<!-- Floating Chat Widget -->
<button class="chat-fab" id="chatFab" aria-label="Open Chat Help" title="Chat with support">
    <i class="fas fa-comments"></i>
    <span class="chat-badge-dot"></span>
</button>

<div class="chat-panel" id="chatPanel">
    <div class="chat-panel-header">
        <div class="chat-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div>
            <h4>Kalinga Bot</h4>
            <p>Online • Quick Support</p>
        </div>
        <button class="chat-close" id="chatCloseBtn" aria-label="Close Chat">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="chat-body">
        <div class="chat-message">
            <strong>Magandang araw!</strong>
            Ano po ang maitutulong ko sa inyo ngayon? Pumili sa mga madalas na katanungan sa ibaba o sumulat ng mensahe.
        </div>
        <div class="chat-quick-btns">
            <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../services.php' : 'services.php'; ?>" class="chat-quick-btn">
                <i class="fas fa-concierge-bell"></i> Paano mag-apply sa AICS?
            </a>
            <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../appointments.php' : 'appointments.php'; ?>" class="chat-quick-btn">
                <i class="fas fa-calendar-alt"></i> Paano magpa-appointment?
            </a>
            <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../housing.php' : 'housing.php'; ?>" class="chat-quick-btn">
                <i class="fas fa-home"></i> Ano ang mga programang pabahay?
            </a>
            <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../contact.php' : 'contact.php'; ?>" class="chat-quick-btn">
                <i class="fas fa-envelope"></i> Direktang makipag-ugnayan sa CSWDO
            </a>
        </div>
    </div>
    <div class="chat-footer">
        <form id="chatForm" onsubmit="event.preventDefault(); handleChatSend();">
            <div class="chat-input-row">
                <input type="text" id="chatInput" placeholder="Sumulat ng mensahe..." autocomplete="off">
                <button type="submit" class="chat-send" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>
</div>

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
    --bg-primary: #E8ECF2;
}

[data-theme="dark"] {
    --bg-card: #1a2332;
    --text-primary: #E8EDF5;
    --text-secondary: #B0C4DE;
    --text-muted: #6B85A0;
    --border-color: #2a3a4f;
    --bg-primary: #0a0e17;
}

/* ─── Base navbar ─── */
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    padding: 0;
    background: var(--midnight);
    border-bottom: none;
    transition: background 0.4s cubic-bezier(0.4,0,0.2,1),
                box-shadow 0.4s cubic-bezier(0.4,0,0.2,1),
                padding 0.4s cubic-bezier(0.4,0,0.2,1);
}

.navbar.scrolled {
    background: var(--bg-card);
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    border-bottom: 1px solid var(--border-color);
}

/* ─── Inner container ─── */
.nav-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    transition: height 0.3s ease;
}

.navbar.scrolled .nav-container {
    height: 66px;
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
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
    overflow: hidden;
    flex-shrink: 0;
}

.brand-logo-circle:hover {
    transform: scale(1.05) rotate(-5deg);
}

.brand-logo-circle img {
    width: 46px;
    height: auto;
    object-fit: contain;
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
    background: linear-gradient(135deg, var(--midnight), var(--light-blue));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* ─── Desktop nav links ─── */
.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
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
    gap: 10px;
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
    padding: 7px 18px;
    border-radius: 40px;
    font-size: 0.83rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-btn-outline {
    background: transparent;
    border: 1.5px solid rgba(255,255,255,0.35);
    color: white;
}

.nav-btn-outline:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.6);
}

.nav-btn-solid {
    background: var(--maroon);
    color: white;
    border: 1.5px solid transparent;
}

.nav-btn-solid:hover {
    background: var(--maroon-light);
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(127,3,3,0.35);
}

.navbar.scrolled .nav-btn-outline {
    border-color: var(--border-color);
    color: var(--text-secondary);
}

.navbar.scrolled .nav-btn-outline:hover {
    border-color: var(--maroon);
    color: var(--maroon);
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
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.drawer-logo-circle img {
    width: 34px;
    height: auto;
    object-fit: contain;
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
    .hamburger { display: flex; }
    .nav-links  { display: none; }

    .nav-btn-outline,
    .nav-btn-solid { display: none; }

    .notif-bell { display: flex; }

    .nav-container { height: 64px; }
    .navbar.scrolled .nav-container { height: 58px; }

    .brand-title { font-size: 0.88rem; }
    .brand-logo-circle { width: 44px; height: 44px; }
    .brand-logo-circle img { width: 38px; }

    /* Show the side drawer on mobile */
    .side-drawer { display: flex; }

    /* Hide desktop user pill & notif bell on very small */
    .nav-user-dropdown { display: none; }
}

@media (max-width: 480px) {
    .nav-container { padding: 0 16px; }
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
        sideDrawer.classList.add('open');
        drawerBackdrop.classList.add('open');
        hamburger.classList.add('active');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        sideDrawer.classList.remove('open');
        drawerBackdrop.classList.remove('open');
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

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
    // ─── Chat Widget ─────────────────────────────────────────────────
    const chatFab = document.getElementById('chatFab');
    const chatPanel = document.getElementById('chatPanel');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatBody = document.querySelector('.chat-body');

    if (chatFab && chatPanel) {
        chatFab.addEventListener('click', function() {
            chatPanel.classList.toggle('open');
        });
    }
    if (chatCloseBtn) {
        chatCloseBtn.addEventListener('click', function() {
            chatPanel.classList.remove('open');
        });
    }

    window.handleChatSend = function() {
        const text = chatInput.value.trim();
        if (!text) return;

        // User message
        const userMsg = document.createElement('div');
        userMsg.className = 'chat-message';
        userMsg.style.borderRadius = '14px 14px 4px 14px';
        userMsg.style.background = 'rgba(127,3,3,0.06)';
        userMsg.style.borderLeft = '3px solid var(--maroon)';
        userMsg.style.marginLeft = '20px';
        userMsg.innerHTML = `<strong>Kayo</strong> ${escapeHtml(text)}`;
        chatBody.appendChild(userMsg);
        chatInput.value = '';
        chatBody.scrollTop = chatBody.scrollHeight;

        // Typing indicator
        const typingMsg = document.createElement('div');
        typingMsg.className = 'chat-message typing-indicator';
        typingMsg.innerHTML = `<strong>Kalinga Boi</strong> <i class="fas fa-circle-notch fa-spin"></i> Nagsusulat...`;
        chatBody.appendChild(typingMsg);
        chatBody.scrollTop = chatBody.scrollHeight;

        // Smart Response matching
        setTimeout(function() {
            typingMsg.remove();
            
            const botMsg = document.createElement('div');
            botMsg.className = 'chat-message';
            
            const msgLower = text.toLowerCase();
            let response = '';

            const isSubDir = window.location.pathname.includes('/admin/') || window.location.pathname.includes('/staff/');
            const baseDir = isSubDir ? '../' : '';

            if (msgLower.includes('aics') || msgLower.includes('tulong') || msgLower.includes('finan') || msgLower.includes('financial') || msgLower.includes('cash')) {
                response = `<strong>Kalinga Boi</strong> Ang <strong>AICS</strong> (Assistance to Individuals in Crisis Situation) ay nagbibigay ng tulong pinansyal, medikal, libing, pagkain, at iba pa. <br><br>👉 Maaari kayong mag-apply online sa pamamagitan ng pagpunta sa <strong><a href="${baseDir}apply-aics.php">Apply for AICS</a></strong>.<br><br><strong>Pangunahing Requirements:</strong><br>1. Valid ID<br>2. Certificate of Indigency mula sa Barangay<br>3. Request Letter`;
            } else if (msgLower.includes('medikal') || msgLower.includes('medical') || msgLower.includes('gamot') || msgLower.includes('ospital')) {
                response = `<strong>Kalinga Boi</strong> Para sa <strong>Medical Assistance (AICS)</strong>, kailangan po ang mga sumusunod:<br><br>1. Barangay Certificate of Indigency ng Pasyente<br>2. Valid ID ng Pasyente at ng Naglalakad<br>3. Clinical Abstract / Medical Certificate<br>4. Prescription ng Gamot o Hospital Bill/Quotation<br><br>Maaari ninyo itong aplayan dito: <strong><a href="${baseDir}aics-medical.php">Medical Assistance Form</a></strong>.`;
            } else if (msgLower.includes('libing') || msgLower.includes('funeral') || msgLower.includes('burial') || msgLower.includes('patay')) {
                response = `<strong>Kalinga Boi</strong> Para sa <strong>Funeral/Burial Assistance</strong>, kailangan po ang mga sumusunod:<br><br>1. Death Certificate ng pumanaw (certified copy)<br>2. Funeral Contract at Statement of Account<br>3. Barangay Certificate of Indigency ng Claimant<br>4. Valid ID ng Claimant at Pumanaw<br><br>Maaari ninyo itong aplayan dito: <strong><a href="${baseDir}aics-funeral.php">Funeral Assistance Form</a></strong>.`;
            } else if (msgLower.includes('appointment') || msgLower.includes('schedule') || msgLower.includes('mag-book') || msgLower.includes('oras') || msgLower.includes('time')) {
                response = `<strong>Kalinga Boi</strong> Upang makipag-ugnayan nang personal sa CSWDO, maaari po kayong mag-book ng inyong appointment online sa pamamagitan ng pagpunta sa <strong><a href="${baseDir}book-appointment.php">Book Appointment</a></strong>.<br><br>Matatanggap ninyo ang inyong slot at schedule details sa inyong notifications kapag na-approve ng staff.`;
            } else if (msgLower.includes('pabahay') || msgLower.includes('housing') || msgLower.includes('bahay')) {
                response = `<strong>Kalinga Boi</strong> Nag-aalok po ang Palayan City ng iba't ibang <strong>Housing Programs</strong> para sa mga kwalipikadong residente. <br><br>👉 Maaari po kayong mag-apply o magbasa ng requirements sa <strong><a href="${baseDir}apply-housing.php">Apply for Housing</a></strong>. Kakailanganin ninyo ang Barangay Clearance, Larawan ng inyong kasalukuyang tinitirhan, at Valid ID.`;
            } else if (msgLower.includes('salamat') || msgLower.includes('thank') || msgLower.includes('ty') || msgLower.includes('salamat po')) {
                response = `<strong>Kalinga Boi</strong> Walang anuman po! Narito ako palagi upang magbigay-gabay. Kung may iba pa kayong katanungan, huwag mag-atubiling magtanong. Kalinga sa bawat Palayeño! 🏛️❤️`;
            } else {
                response = `<strong>Kalinga Boi</strong> Salamat sa inyong mensahe. Upang matulungan kayo, maaari ninyong piliin ang mga quick link sa itaas, o makipag-ugnayan sa amin sa:<br><br>📞 <strong>Hotline:</strong> (044) 123-4567<br>✉️ <strong>Email:</strong> cswdo@palayancity.gov.ph<br>🏛️ <strong>Address:</strong> CSWDO Office, Palayan City Hall.`;
            }
            
            botMsg.innerHTML = response;
            chatBody.appendChild(botMsg);
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 800);
    };

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

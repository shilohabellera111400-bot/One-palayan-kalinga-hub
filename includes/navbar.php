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
            <button class="hamburger" id="hamburger" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="mobile-menu" id="mobileMenu">
        <ul class="mobile-links">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="services.php"><i class="fas fa-concierge-bell"></i> Services</a></li>
            <?php if(isLoggedIn() && !isStaff() && !isAdmin()): ?>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="track-aics.php"><i class="fas fa-search"></i> Track AICS</a></li>
                <li><a href="housing.php"><i class="fas fa-house-user"></i> Housing</a></li>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="documents.php"><i class="fas fa-file-alt"></i> My Documents</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li class="mobile-divider"></li>
                <li><a href="logout.php" class="mobile-logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php elseif(!isLoggedIn()): ?>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="track-aics.php"><i class="fas fa-search"></i> Track AICS</a></li>
                <li><a href="housing.php"><i class="fas fa-house-user"></i> Housing</a></li>
                <li class="mobile-divider"></li>
                <li><a href="login.php" class="mobile-login"><i class="fas fa-sign-in-alt"></i> Log In</a></li>
                <li><a href="register.php" class="mobile-register"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php else: ?>
                <li><a href="staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Staff Dashboard</a></li>
                <li class="mobile-divider"></li>
                <li><a href="logout.php" class="mobile-logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =====================================================================
   Unified Navbar — One Palayan-Kalinga Hub
   Matches the premium dark/light design of index.php
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
    --border-color: #E8ECF2;
    --bg-primary: #F8F9FC;
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
}

.hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.navbar.scrolled .hamburger span { background: var(--text-primary); }

.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
.hamburger.active span:nth-child(2) { opacity: 0; }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

/* ─── Mobile Menu ─── */
.mobile-menu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4,0,0.2,1);
    background: var(--midnight);
    border-top: 1px solid rgba(255,255,255,0.08);
}

.navbar.scrolled .mobile-menu {
    background: var(--bg-card);
    border-top-color: var(--border-color);
}

.mobile-menu.open {
    max-height: 520px;
}

.mobile-links {
    list-style: none;
    padding: 12px 16px;
    margin: 0;
}

.mobile-links li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    color: rgba(255,255,255,0.82);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.navbar.scrolled .mobile-links li a {
    color: var(--text-secondary);
}

.mobile-links li a:hover {
    background: rgba(255,255,255,0.08);
    color: white;
}

.navbar.scrolled .mobile-links li a:hover {
    background: var(--bg-primary);
    color: var(--maroon);
}

.mobile-links li a i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
}

.mobile-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 8px 0;
}

.navbar.scrolled .mobile-divider {
    background: var(--border-color);
}

.mobile-logout {
    color: #ff8a8a !important;
}

.mobile-login {
    background: rgba(127,3,3,0.2) !important;
    color: #ff9999 !important;
}

.mobile-register {
    background: var(--maroon) !important;
    color: white !important;
    margin-top: 4px;
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
}

@media (max-width: 480px) {
    .nav-container { padding: 0 16px; }
    .brand-sub { display: none; }
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

    // ─── Hamburger / Mobile Menu ─────────────────────────────────────
    const hamburger   = document.getElementById('hamburger');
    const mobileMenu  = document.getElementById('mobileMenu');

    if (hamburger) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('open');
        });
    }

    // Close mobile menu on link click
    document.querySelectorAll('.mobile-links a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('open');
        });
    });

    // ─── User Dropdown ───────────────────────────────────────────────
    const userPillBtn     = document.getElementById('userPillBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');

    if (userPillBtn && userDropdownMenu) {
        userPillBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = userDropdownMenu.classList.contains('open');
            userDropdownMenu.classList.toggle('open');
            userPillBtn.setAttribute('aria-expanded', !isOpen);
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
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
        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // Double-ensure body class matches on DOM load to prevent any timing issues
    document.addEventListener('DOMContentLoaded', function() {
        const current = document.documentElement.getAttribute('data-theme');
        if (current === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }
    });
})();
</script>
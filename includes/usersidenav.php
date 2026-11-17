<?php
// includes/usersidenav.php - Minimalist Icon-based Navigation with Side Flyouts
$portal_current_page = basename($_SERVER['PHP_SELF']);

$is_dash_active = ($portal_current_page === 'dashboard.php');
$is_announcements_active = ($portal_current_page === 'announcements.php');
$is_documents_active = ($portal_current_page === 'documents.php');
$is_appointments_active = in_array($portal_current_page, ['appointments.php', 'my-appointments.php', 'book-appointment.php', 'view-appointment.php'], true);
$is_aics_active = in_array($portal_current_page, ['aics.php', 'aics-requests.php', 'apply-aics.php', 'view-aics.php', 'my-aics.php'], true);
$is_housing_active = in_array($portal_current_page, ['housing-applications.php', 'my-housing.php', 'view-housing.php', 'housing.php'], true);
$is_help_active = in_array($portal_current_page, ['contact.php', 'feedback.php'], true);
?>
<style>
/* User-info sidebar completely removed from sidebar per Req 9 */
.user-info-sidebar {
    display: none !important;
}

/* Minimalist Icon Menu */
.sidebar-menu {
    list-style: none;
    padding: 10px 4px !important;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.sidebar-menu li {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center;
    margin: 0 !important;
}

.sidebar-menu li a.portal-icon-nav,
.sidebar-menu li button.portal-icon-nav {
    width: 44px;
    height: 44px;
    border-radius: 16px;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s ease;
    position: relative;
    padding: 0 !important;
}

.sidebar-menu li a.portal-icon-nav:hover,
.sidebar-menu li button.portal-icon-nav:hover {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.12);
    transform: translateY(-2px);
}

.sidebar-menu li a.portal-icon-nav.active,
.sidebar-menu li button.portal-icon-nav.active {
    color: #FFFFFF;
    background: #7F0303;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.45);
}

.sidebar-menu li a.portal-icon-nav.active::after,
.sidebar-menu li button.portal-icon-nav.active::after {
    content: '';
    position: absolute;
    right: -4px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 18px;
    background: #E5B338;
    border-radius: 4px;
}

/* Tooltip */
.sidebar-menu li .nav-tooltip {
    position: absolute;
    left: calc(100% + 12px);
    top: 50%;
    transform: translateY(-50%) translateX(-6px);
    background: #111416;
    color: #FFFFFF;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    z-index: 1200;
}

.sidebar-menu li:hover .nav-tooltip {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}

.flyout-dot-indicator {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #E5B338;
}

/* Side Flyout Popup (Req 2) */
.side-popup-menu {
    position: absolute;
    left: calc(100% + 12px);
    top: 50%;
    transform: translateY(-50%) scale(0.92);
    width: 210px;
    background: #FFFFFF;
    border-radius: 18px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
    padding: 8px;
    opacity: 0;
    pointer-events: none;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1300;
}

[data-theme="dark"] .side-popup-menu {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.1);
}

.side-popup-menu.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(-50%) scale(1);
}

.side-popup-menu::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 50%;
    transform: translateY(-50%) rotate(45deg);
    width: 12px;
    height: 12px;
    background: #FFFFFF;
    border-left: 1px solid rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .side-popup-menu::before {
    background: #152227;
    border-left-color: rgba(255, 255, 255, 0.1);
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.side-popup-title {
    font-size: 0.65rem;
    font-weight: 800;
    color: #71818B;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 6px 10px 6px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    margin-bottom: 4px;
}

.side-popup-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #192126;
    text-decoration: none;
    transition: all 0.18s ease;
}

[data-theme="dark"] .side-popup-link {
    color: #E5EFF2;
}

.side-popup-link i {
    color: #7F0303;
    width: 16px;
    text-align: center;
}

[data-theme="dark"] .side-popup-link i {
    color: #F87171;
}

.side-popup-link:hover {
    background: rgba(127, 3, 3, 0.08);
    color: #7F0303;
    transform: translateX(2px);
}

[data-theme="dark"] .side-popup-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #FFFFFF;
}

.side-popup-link.active {
    background: rgba(127, 3, 3, 0.12);
    color: #7F0303;
    font-weight: 700;
}
</style>

<ul class="sidebar-menu">
    <!-- 1. Dashboard -->
    <li>
        <a href="dashboard.php" class="portal-icon-nav <?php echo $is_dash_active ? 'active' : ''; ?>" aria-label="Dashboard">
            <i class="fas fa-th-large"></i>
            <span class="nav-tooltip">Dashboard</span>
        </a>
    </li>

    <!-- 2. Appointment Desk with Side Flyout -->
    <li>
        <button type="button" class="portal-icon-nav sidenav-flyout-trigger <?php echo $is_appointments_active ? 'active' : ''; ?>" data-flyout="sideFlyoutApp" aria-label="Appointments Services">
            <i class="fas fa-calendar-plus"></i>
            <span class="flyout-dot-indicator"></span>
            <span class="nav-tooltip">Appointment Desk</span>
        </button>
        <div class="side-popup-menu" id="sideFlyoutApp">
            <div class="side-popup-title">Appointment Desk</div>
            <a href="appointments.php" class="side-popup-link <?php echo $portal_current_page === 'appointments.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i>
                <span>Book Appointment</span>
            </a>
            <a href="my-appointments.php" class="side-popup-link <?php echo $portal_current_page === 'my-appointments.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>My Appointments</span>
            </a>
        </div>
    </li>

    <!-- 3. Announcements -->
    <li>
        <a href="announcements.php" class="portal-icon-nav <?php echo $is_announcements_active ? 'active' : ''; ?>" aria-label="Announcements">
            <i class="fas fa-bullhorn"></i>
            <span class="nav-tooltip">Announcements</span>
        </a>
    </li>

    <!-- 4. Documents -->
    <li>
        <a href="documents.php" class="portal-icon-nav <?php echo $is_documents_active ? 'active' : ''; ?>" aria-label="My Documents">
            <i class="fas fa-folder"></i>
            <span class="nav-tooltip">Documents</span>
        </a>
    </li>

    <!-- 5. AICS Assistance with Side Flyout -->
    <li>
        <button type="button" class="portal-icon-nav sidenav-flyout-trigger <?php echo $is_aics_active ? 'active' : ''; ?>" data-flyout="sideFlyoutAics" aria-label="AICS Assistance">
            <i class="fas fa-hand-holding-heart"></i>
            <span class="flyout-dot-indicator"></span>
            <span class="nav-tooltip">AICS Assistance</span>
        </button>
        <div class="side-popup-menu" id="sideFlyoutAics">
            <div class="side-popup-title">CSWDO Welfare Aid</div>
            <a href="aics.php" class="side-popup-link <?php echo $portal_current_page === 'aics.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-medical"></i>
                <span>Apply for AICS</span>
            </a>
            <a href="aics-requests.php" class="side-popup-link <?php echo $portal_current_page === 'aics-requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i>
                <span>AICS Requests</span>
            </a>
            <a href="track-aics.php" class="side-popup-link <?php echo $portal_current_page === 'track-aics.php' ? 'active' : ''; ?>">
                <i class="fas fa-search-location"></i>
                <span>Track Status</span>
            </a>
        </div>
    </li>

    <!-- 6. Housing Programs with Side Flyout -->
    <li>
        <button type="button" class="portal-icon-nav sidenav-flyout-trigger <?php echo $is_housing_active ? 'active' : ''; ?>" data-flyout="sideFlyoutHousing" aria-label="Housing Programs">
            <i class="fas fa-home"></i>
            <span class="flyout-dot-indicator"></span>
            <span class="nav-tooltip">Housing Programs</span>
        </button>
        <div class="side-popup-menu" id="sideFlyoutHousing">
            <div class="side-popup-title">Housing Services</div>
            <a href="housing-applications.php" class="side-popup-link <?php echo $portal_current_page === 'housing-applications.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i>
                <span>Housing Applications</span>
            </a>
            <a href="my-housing.php" class="side-popup-link <?php echo $portal_current_page === 'my-housing.php' ? 'active' : ''; ?>">
                <i class="fas fa-city"></i>
                <span>My Housing Status</span>
            </a>
        </div>
    </li>

    <!-- 7. Helpdesk & Hotlines -->
    <li>
        <a href="contact.php" class="portal-icon-nav <?php echo $is_help_active ? 'active' : ''; ?>" aria-label="CSWDO Helpdesk">
            <i class="fas fa-headset"></i>
            <span class="nav-tooltip">CSWDO Helpdesk</span>
        </a>
    </li>

    <!-- 8. Logout -->
    <li>
        <a href="logout.php" class="portal-icon-nav" style="color: #EF4444;" aria-label="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-tooltip">Logout</span>
        </a>
    </li>
</ul>

<script>
(function() {
    function initSidenavFlyouts() {
        const triggers = document.querySelectorAll('.sidenav-flyout-trigger');
        const popups = document.querySelectorAll('.side-popup-menu');

        triggers.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const targetId = this.getAttribute('data-flyout');
                const targetPopup = document.getElementById(targetId);

                popups.forEach(p => {
                    if (p !== targetPopup) p.classList.remove('open');
                });

                if (targetPopup) {
                    targetPopup.classList.toggle('open');
                }
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.side-popup-menu') && !e.target.closest('.sidenav-flyout-trigger')) {
                popups.forEach(p => p.classList.remove('open'));
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                popups.forEach(p => p.classList.remove('open'));
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidenavFlyouts);
    } else {
        initSidenavFlyouts();
    }
})();
</script>

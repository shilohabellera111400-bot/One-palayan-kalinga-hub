<?php
/**
 * includes/mobile-bottom-nav.php
 * Unified Sticky Bottom Navigation Bar for Mobile
 * Shown on public/landing pages (index.php, services.php, etc.)
 * Strictly HIDDEN when user is on dashboard.php or detail/print views per user request.
 */
$mbn_current_page = basename($_SERVER['PHP_SELF'] ?? '');
$mbn_prefix = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/staff/') !== false) ? '../' : '';

// Strictly show bottom navigation tabs ONLY on index.php per user request
if ($mbn_current_page !== 'index.php' && $mbn_current_page !== '') {
    return;
}
?>
<!-- Unified Sticky Mobile Bottom Tab Bar -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <a href="<?php echo $mbn_prefix; ?>index.php" class="mobile-tab-item <?php echo ($mbn_current_page === 'index.php' || $mbn_current_page === '') ? 'active' : ''; ?>" aria-label="Home">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="<?php echo $mbn_prefix; ?>services.php" class="mobile-tab-item <?php echo ($mbn_current_page === 'services.php') ? 'active' : ''; ?>" aria-label="Services">
        <i class="fas fa-hand-holding-heart"></i>
        <span>Services</span>
    </a>
    <a href="<?php echo $mbn_prefix; ?>appointments.php" class="mobile-tab-item mobile-tab-featured <?php echo ($mbn_current_page === 'appointments.php' || $mbn_current_page === 'book-appointment.php') ? 'active' : ''; ?>" aria-label="Book Appointment" title="Book Appointment">
        <div class="mobile-tab-badge-btn">
            <i class="fas fa-calendar-plus"></i>
        </div>
        <span>Book</span>
    </a>
    <a href="<?php echo $mbn_prefix; ?>track-aics.php" class="mobile-tab-item <?php echo ($mbn_current_page === 'track-aics.php') ? 'active' : ''; ?>" aria-label="Track Requests">
        <i class="fas fa-search-location"></i>
        <span>Track</span>
    </a>
    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <a href="<?php echo $mbn_prefix; ?>dashboard.php" class="mobile-tab-item" aria-label="Dashboard">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    <?php else: ?>
        <a href="<?php echo $mbn_prefix; ?>login.php" class="mobile-tab-item <?php echo ($mbn_current_page === 'login.php') ? 'active' : ''; ?>" aria-label="Login">
            <i class="fas fa-sign-in-alt"></i>
            <span>Login</span>
        </a>
    <?php endif; ?>
</nav>

<style>
.mobile-bottom-nav {
    display: none; /* Never on desktop */
}

@media (max-width: 768px) {
    body:not(.is-dashboard-page) {
        padding-bottom: 66px !important;
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 58px !important;
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border-top: 1px solid var(--border-color, #E2E8F0) !important;
        box-shadow: 0 -4px 18px rgba(0, 0, 0, 0.08) !important;
        z-index: 99990 !important;
        align-items: center !important;
        justify-content: space-around !important;
        padding: 2px 4px calc(2px + env(safe-area-inset-bottom, 0)) !important;
    }

    [data-theme="dark"] .mobile-bottom-nav {
        background: rgba(21, 34, 48, 0.98) !important;
        border-top-color: rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 -4px 22px rgba(0, 0, 0, 0.5) !important;
    }

    .mobile-tab-item {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 1 !important;
        min-width: 0 !important;
        height: 100% !important;
        color: var(--text-muted, #64748B) !important;
        text-decoration: none !important;
        background: transparent !important;
        border: none !important;
        outline: none !important;
        cursor: pointer !important;
        font-family: inherit !important;
        padding: 3px 0 !important;
        transition: all 0.2s ease !important;
        -webkit-tap-highlight-color: transparent !important;
    }

    .mobile-tab-item i {
        font-size: 1.05rem !important;
        margin-bottom: 2px !important;
        transition: transform 0.2s ease, color 0.2s ease !important;
    }

    .mobile-tab-item span {
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.2px !important;
        line-height: 1.1 !important;
        white-space: nowrap !important;
    }

    .mobile-tab-item:active i {
        transform: scale(0.9) !important;
    }

    .mobile-tab-item.active {
        color: var(--maroon, #7F0303) !important;
    }

    [data-theme="dark"] .mobile-tab-item.active {
        color: var(--gold-light, #E8D5A8) !important;
    }

    .mobile-tab-item.active i {
        transform: translateY(-2px) !important;
    }

    /* Center Highlighted Tab: Book Appointment */
    .mobile-tab-featured {
        position: relative !important;
        top: -8px !important;
    }

    .mobile-tab-badge-btn {
        width: 42px !important;
        height: 42px !important;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #7F0303 0%, #9a1515 60%, #C9A86C 100%) !important;
        color: #ffffff !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 5px 14px rgba(127, 3, 3, 0.35) !important;
        border: 2.5px solid var(--bg-card, #FFFFFF) !important;
        transition: transform 0.2s ease !important;
    }

    .mobile-tab-featured i {
        font-size: 1.05rem !important;
        color: #ffffff !important;
        margin-bottom: 0 !important;
    }

    .mobile-tab-featured:active .mobile-tab-badge-btn {
        transform: scale(0.92) !important;
    }

    .mobile-tab-featured span {
        margin-top: 2px !important;
        color: var(--maroon, #7F0303) !important;
        font-weight: 700 !important;
    }

    [data-theme="dark"] .mobile-tab-featured span {
        color: var(--gold-light, #E8D5A8) !important;
    }
}
</style>

<?php
// includes/portal-sidebar.php - Responsive Sidebar for Resident Portal
// Minimal (76px, logo-only) on Dashboard; Expanded (240px with labels) on other pages.
$portal_current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = ($portal_current_page === 'dashboard.php');

// Active state detection
$is_dashboard_active = ($portal_current_page === 'dashboard.php');
$is_track_housing = ($portal_current_page === 'track-request.php' && isset($_GET['type']) && $_GET['type'] === 'housing');
$is_appointment_active = in_array($portal_current_page, ['appointments.php', 'appointment.php', 'book-appointment.php', 'my-appointments.php', 'view-appointment.php'], true);
$is_aics_active = in_array($portal_current_page, ['aics.php', 'aics-requests.php', 'apply-aics.php', 'view-aics.php', 'my-aics.php', 'track-aics.php', 'aics-requirements.php', 'aics-medical.php', 'aics-funeral.php', 'aics-educational.php', 'aics-food.php', 'aics-transportation.php', 'aics-cash-relief.php'], true) || ($portal_current_page === 'track-request.php' && !$is_track_housing);
$is_housing_active = in_array($portal_current_page, ['housing-applications.php', 'my-housing.php', 'view-housing.php', 'housing.php', 'housing-programs.php', 'apply-housing.php', 'housing-requirements.php'], true) || $is_track_housing;
$is_senior_active = in_array($portal_current_page, ['senior.php', 'senior-services.php'], true);
$is_pwd_active = in_array($portal_current_page, ['pwd.php', 'pwd-services.php'], true);
$is_calendar_active = in_array($portal_current_page, ['calendar.php'], true);
$is_announcements_active = in_array($portal_current_page, ['announcements.php', 'announcement.php'], true);
$is_print_active = in_array($portal_current_page, ['print-forms.php', 'print-claim-stub.php'], true);
$is_documents_active = in_array($portal_current_page, ['documents.php', 'my-documents.php', 'upload-document.php', 'verify-documents.php'], true);
$is_help_active = in_array($portal_current_page, ['contact.php', 'feedback.php'], true);
$is_settings_active = in_array($portal_current_page, ['settings.php', 'change-password.php'], true);

// Senior Citizen auto-detection check for sidebar (Strictly 60+ years old)
if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('isResident') && isResident()) {
    try {
        if (!isset($db) || !$db) {
            $database = new Database();
            $db_temp = $database->getConnection();
        } else {
            $db_temp = $db;
        }
        if ($db_temp) {
            $st = $db_temp->prepare("SELECT birth_date, senior_citizen FROM residents WHERE user_id = :uid LIMIT 1");
            $st->execute([':uid' => $_SESSION['user_id']]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            $sc = false;
            if ($r && !empty($r['birth_date']) && $r['birth_date'] !== '0000-00-00') {
                $b = new DateTime($r['birth_date']);
                $now = new DateTime();
                $age = $now->diff($b)->y;
                if ($age >= 60) {
                    $sc = true;
                    if (($r['senior_citizen'] ?? 0) != 1) {
                        $db_temp->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")->execute([':uid' => $_SESSION['user_id']]);
                    }
                } else {
                    $sc = false;
                    if (($r['senior_citizen'] ?? 0) == 1) {
                        $db_temp->prepare("UPDATE residents SET senior_citizen = 0 WHERE user_id = :uid")->execute([':uid' => $_SESSION['user_id']]);
                    }
                }
            }
            $_SESSION['is_senior_citizen'] = $sc;
        }
    } catch (Exception $e) {}
}
$user_is_senior = !empty($_SESSION['is_senior_citizen']);
?>
<style>
/* Global Layout Guarantee - Eliminate any dead margin/gap between sidebar and content */
html, body {
    background-color: var(--ref-canvas, #E4EFE7) !important;
    background: var(--ref-canvas, #E4EFE7) !important;
}
[data-theme="dark"], [data-theme="dark"] body {
    background-color: #0C1417 !important;
    background: #0C1417 !important;
}
.app-container,
.dashboard-container {
    display: flex !important;
    flex-direction: row !important;
    gap: 16px !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
}
.app-container > .main-content,
.dashboard-container > .dashboard-main,
.main-content,
.dashboard-main {
    flex: 1 1 0% !important;
    min-width: 0 !important;
    width: auto !important;
    margin: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    margin-top: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
}

/* ========== SIDEBAR BASE STYLES ========== */
.portal-sidebar-wrap {
    border-radius: 28px;
    z-index: 1050;
    flex-shrink: 0;
    transition: background-color 0.25s ease, border-color 0.25s ease;
    user-select: none;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

/* 1. Minimal Version (Dashboard Only - Black / Very Dark, Visually Centered Vertically on Desktop) */
@media (min-width: 993px) {
    .portal-sidebar-wrap.portal-sidebar-minimal {
        width: 76px;
        padding: 20px 8px 22px;
        align-items: center;
        background: #181B1D;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.22);
        position: sticky;
        top: 24px;
        margin-top: 16px;
        height: fit-content;
        max-height: calc(100vh - 48px);
        overflow: visible;
    }
}

/* Animation: Gentle Content Fade In upon page load */
@keyframes portalSidebarContentFadeIn {
    0% {
        opacity: 0.45;
        transform: translateY(2px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.portal-sidebar-nav {
    animation: portalSidebarContentFadeIn 0.26s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

/* Sidebar Navigating state: gentle fade out of current content while sliding */
.portal-sidebar-wrap.portal-sidebar-navigating .portal-sidebar-nav {
    opacity: 0.4 !important;
    transform: translateX(4px);
    transition: opacity 0.2s ease, transform 0.2s ease !important;
}

.portal-sidebar-wrap.portal-sidebar-navigating .portal-side-flyout {
    opacity: 0.35 !important;
    pointer-events: none !important;
    transition: opacity 0.2s ease !important;
}

.portal-sidebar-wrap.portal-sidebar-navigating .portal-sidebar-brand {
    opacity: 0.55 !important;
    transition: opacity 0.2s ease !important;
}

/* Clean Sidebar Transitions - No expanding or layout shifts (Item 3) */
.portal-sidebar-wrap.portal-sidebar-navigating .portal-sidebar-nav {
    opacity: 0.85;
}

@media (min-width: 993px) {
    [data-theme="dark"] .portal-sidebar-wrap.portal-sidebar-minimal {
        background: #0D1316;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .portal-sidebar-wrap.portal-sidebar-minimal .portal-sidebar-brand {
        display: flex;
        justify-content: center;
        align-items: center;
        padding-bottom: 12px;
        margin-bottom: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        width: 100%;
    }

    .portal-sidebar-wrap.portal-sidebar-minimal .portal-brand-logo-ring {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #FFFFFF;
        padding: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        cursor: default;
        flex-shrink: 0;
    }

    .portal-sidebar-wrap.portal-sidebar-minimal .portal-brand-logo-ring img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
        transform: scale(1.05);
    }

    .portal-sidebar-wrap.portal-sidebar-minimal .portal-brand-text-expanded,
    .portal-sidebar-wrap.portal-sidebar-minimal .portal-brand-subtext-expanded {
        display: none !important;
    }
}

/* 2. Expanded Version (Other Pages - Rich Maroon Gradient, Compact Vertical Fit & Scrollable) */
.portal-sidebar-wrap.portal-sidebar-expanded {
    width: 240px;
    min-width: 240px;
    max-width: 240px;
    height: calc(100vh - 36px);
    max-height: calc(100vh - 36px);
    overflow: hidden !important;
    padding: 16px 10px 14px;
    align-items: stretch;
    background: linear-gradient(180deg, #8A0B0B 0%, #760404 55%, #580101 100%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 12px 36px rgba(118, 4, 4, 0.28);
    position: sticky;
    top: 18px;
    display: flex;
    flex-direction: column;
}

[data-theme="dark"] .portal-sidebar-wrap.portal-sidebar-expanded {
    background: linear-gradient(180deg, #640404 0%, #500202 55%, #3B0101 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.55);
}

.portal-sidebar-expanded .portal-sidebar-brand,
.portal-sidebar-expanded .sidebar-user-expanded {
    flex-shrink: 0 !important;
}

.portal-sidebar-expanded .portal-sidebar-nav {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
    padding-right: 2px;
    min-height: 0;
}

.portal-sidebar-expanded .portal-sidebar-footer {
    flex-shrink: 0 !important;
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.portal-sidebar-expanded .portal-sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.portal-sidebar-expanded .portal-sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 4px;
}

.portal-sidebar-wrap.portal-sidebar-expanded .portal-sidebar-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding-bottom: 12px;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    width: 100%;
}

.portal-sidebar-wrap.portal-sidebar-expanded .portal-brand-logo-ring {
    width: 86px;
    height: 86px;
    border-radius: 50%;
    background: #FFFFFF;
    padding: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 22px rgba(0, 0, 0, 0.35);
    border: 3.5px solid rgba(255, 255, 255, 0.65);
    margin: 0 auto 12px;
    cursor: default;
    transition: transform 0.25s ease;
}

.portal-sidebar-wrap.portal-sidebar-expanded .portal-brand-logo-ring img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
    transform: scale(1.05);
}

.portal-brand-text-expanded {
    color: #FFFFFF;
    font-size: 0.88rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    line-height: 1.2;
    text-transform: uppercase;
}

.portal-brand-subtext-expanded {
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.70rem;
    letter-spacing: 0.3px;
    margin-top: 4px;
    font-weight: 600;
}

/* User Profile Section in Expanded Sidebar (Part 1, Item 4) */
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

/* Broken Line Big Border for Profile Circle (Resident & Staff) - Enlarged */
.sidebar-avatar-wrapper {
    position: relative;
    width: 106px;
    height: 106px;
    margin: 4px auto 12px;
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
    box-shadow: 0 0 16px rgba(246, 216, 168, 0.45);
    animation: avatarRingRotate 25s linear infinite;
    pointer-events: none;
    z-index: 1;
}

[data-theme="dark"] .sidebar-avatar-ring {
    border-color: #E5B338;
    box-shadow: 0 0 16px rgba(229, 179, 56, 0.35);
}

@keyframes avatarRingRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.sidebar-avatar-large {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FEF7DF;
    color: #7F0303;
    font-size: 1.85rem;
    font-weight: 800;
    border: 2.5px solid rgba(255, 255, 255, 0.95);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.32);
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
    font-size: 0.96rem;
    font-weight: 800;
    color: #FFFFFF;
    margin: 0 0 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.2px;
}

/* Fix verified resident highlights: sleek rounded pill badge, no harsh block */
.sidebar-user-role {
    font-size: 0.70rem !important;
    font-weight: 700 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    padding: 3px 12px !important;
    border-radius: 999px !important;
    letter-spacing: 0.25px !important;
    text-decoration: none !important;
    box-shadow: none !important;
    background: transparent !important;
    transition: all 0.25s ease !important;
    margin: 0 auto !important;
    max-width: fit-content !important;
    line-height: 1.4 !important;
}

.sidebar-user-role.status-verified,
.sidebar-user-role.sidebar-status-verified {
    color: #34D399 !important;
    background: rgba(52, 211, 153, 0.16) !important;
    border: 1px solid rgba(52, 211, 153, 0.38) !important;
}
.sidebar-user-role.status-verified:hover,
.sidebar-user-role.sidebar-status-verified:hover {
    background: rgba(52, 211, 153, 0.26) !important;
    color: #6EE7B7 !important;
    border-color: rgba(52, 211, 153, 0.5) !important;
    transform: translateY(-1px);
}

.sidebar-user-role.status-pending,
.sidebar-user-role.sidebar-status-pending {
    color: #FBBF24 !important;
    background: rgba(251, 191, 36, 0.16) !important;
    border: 1px solid rgba(251, 191, 36, 0.38) !important;
}

.sidebar-user-role.status-rejected,
.sidebar-user-role.sidebar-status-rejected {
    color: #F87171 !important;
    background: rgba(248, 113, 113, 0.16) !important;
    border: 1px solid rgba(248, 113, 113, 0.38) !important;
}

.sidebar-user-role.status-unverified,
.sidebar-user-role.sidebar-status-unverified {
    color: rgba(255, 255, 255, 0.85) !important;
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

/* Nav Icons Stack */
.portal-sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    flex: 1;
}

.portal-sidebar-minimal .portal-sidebar-nav {
    align-items: center;
    gap: 8px;
    flex: 1;
}

.portal-nav-item {
    position: relative;
    width: 100%;
    display: flex;
}

.portal-sidebar-minimal .portal-nav-item {
    justify-content: center;
}

/* Nav Button in Minimal Sidebar */
.portal-sidebar-minimal .portal-nav-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.18s ease, color 0.18s ease;
    position: relative;
}

.portal-sidebar-minimal .portal-nav-btn:hover {
    background: rgba(255, 255, 255, 0.14);
    color: #FFFFFF;
}

.portal-sidebar-minimal .portal-nav-btn.active {
    background: #7F0303;
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.45);
}

/* Nav Button in Expanded Sidebar */
.portal-sidebar-expanded .portal-nav-btn {
    width: 100%;
    padding: 8px 12px;
    border-radius: 12px;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.83rem;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.18s ease, color 0.18s ease;
    position: relative;
    box-sizing: border-box;
}

.portal-sidebar-expanded .portal-nav-btn-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.portal-sidebar-expanded .portal-nav-btn-left i {
    width: 20px;
    font-size: 0.95rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.85);
    transition: color 0.18s ease;
}

.portal-sidebar-expanded .portal-nav-label {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.83rem;
}

.portal-tag-senior-pill {
    font-size: 0.62rem;
    font-weight: 800;
    background: #e8f5e9;
    color: #1b5e20;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    line-height: 1.3;
    border: 1px solid rgba(46, 125, 50, 0.2);
}
[data-theme="dark"] .portal-tag-senior-pill {
    background: rgba(46, 125, 50, 0.28);
    color: #a5d6a7;
    border-color: rgba(165, 214, 167, 0.2);
}

.portal-sidebar-expanded .portal-nav-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #FFFFFF;
}

.portal-sidebar-expanded .portal-nav-btn:hover .portal-nav-btn-left i {
    color: #FFFFFF;
}

/* Active Button in Expanded Maroon Sidebar: Subtle Translucent Style */
.portal-sidebar-expanded .portal-nav-btn.active {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    border-left: 3.5px solid #F6D8A8 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.portal-sidebar-expanded .portal-nav-btn.active .portal-nav-btn-left i,
.portal-sidebar-expanded .portal-nav-btn.active .portal-nav-label {
    color: #FFFFFF !important;
    font-weight: 700;
}

.portal-sidebar-expanded .portal-nav-btn.active .portal-chevron-indicator {
    color: rgba(255, 255, 255, 0.85) !important;
    opacity: 0.95;
}

[data-theme="dark"] .portal-sidebar-expanded .portal-nav-btn.active {
    background: rgba(255, 255, 255, 0.14) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    border-left: 3.5px solid #E5B338 !important;
}

[data-theme="dark"] .portal-sidebar-expanded .portal-nav-btn.active .portal-nav-btn-left i,
[data-theme="dark"] .portal-sidebar-expanded .portal-nav-btn.active .portal-nav-label {
    color: #FFFFFF !important;
}

.portal-chevron-indicator {
    font-size: 0.68rem;
    opacity: 0.6;
    transition: opacity 0.18s ease;
}

.portal-nav-btn:hover .portal-chevron-indicator {
    opacity: 1;
}

/* Section Divider */
.portal-sidebar-divider {
    width: 100%;
    display: flex;
    align-items: center;
    margin: 6px 0 3px;
    padding: 6px 8px 2px;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.portal-sidebar-divider span {
    font-size: 0.64rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255, 255, 255, 0.5);
}

.portal-sidebar-minimal .portal-sidebar-divider {
    justify-content: center;
    padding: 0;
    margin: 6px 0;
    width: 36px;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.portal-sidebar-minimal .portal-sidebar-divider span {
    display: none;
}

/* Minimal Sidebar Tooltip */
.portal-sidebar-minimal .portal-tooltip {
    position: absolute;
    left: calc(100% + 14px);
    top: 50%;
    transform: translateY(-50%);
    background: #111827;
    color: #FFFFFF;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.18s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    z-index: 1200;
}

.portal-sidebar-minimal .portal-tooltip::before {
    content: '';
    position: absolute;
    top: 50%;
    left: -4px;
    transform: translateY(-50%) rotate(45deg);
    width: 8px;
    height: 8px;
    background: #111827;
}

.portal-sidebar-minimal .portal-nav-item:hover .portal-tooltip {
    opacity: 1;
    visibility: visible;
}

/* ========== SIDE-ORIENTED POPUP / FLYOUT MENU ========== */
.portal-side-flyout {
    position: fixed !important;
    background: var(--ref-card, #FFFFFF);
    border-radius: 18px;
    padding: 10px;
    min-width: 230px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateX(-4px);
    transition: opacity 0.16s ease, transform 0.16s ease;
    z-index: 999999;
}

/* Safe Hover Bridge: invisible hit-box extending towards the sidebar to prevent closing on diagonal mouse moves */
.portal-side-flyout::after {
    content: '';
    position: absolute;
    top: -12px;
    bottom: -12px;
    left: -20px;
    width: 24px;
    background: transparent;
    pointer-events: auto;
}

[data-theme="dark"] .portal-side-flyout {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
}

.portal-side-flyout::before {
    content: '';
    position: absolute;
    top: var(--flyout-arrow-top, 24px);
    left: -6px;
    width: 12px;
    height: 12px;
    background: var(--ref-card, #FFFFFF);
    border-left: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    border-bottom: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    transform: translateY(-50%) rotate(45deg);
    z-index: 1;
}

[data-theme="dark"] .portal-side-flyout::before {
    background: #152227;
    border-left-color: rgba(255, 255, 255, 0.08);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.portal-side-flyout.open {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
    transform: translateX(0) !important;
}

.flyout-header-tag {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ref-muted, #71818B);
    padding: 4px 8px 6px;
    border-bottom: 1px solid var(--ref-border, rgba(0, 0, 0, 0.06));
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.flyout-link-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ref-text, #192126) !important;
    text-decoration: none;
    transition: background-color 0.18s ease, color 0.18s ease;
    opacity: 1 !important;
    visibility: visible !important;
}

.flyout-link-item i {
    width: 18px;
    font-size: 0.88rem;
    color: #7F0303 !important;
    text-align: center;
    flex-shrink: 0;
}

[data-theme="dark"] .flyout-link-item {
    color: #E8EDF5 !important;
}

[data-theme="dark"] .flyout-link-item i {
    color: #F87171 !important;
}

.flyout-link-item:hover {
    background: rgba(127, 3, 3, 0.08) !important;
    color: #7F0303 !important;
}

[data-theme="dark"] .flyout-link-item:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #FFFFFF !important;
}

.flyout-link-item.active {
    background: rgba(127, 3, 3, 0.12) !important;
    color: #7F0303 !important;
    font-weight: 700 !important;
}

[data-theme="dark"] .flyout-link-item.active {
    background: rgba(127, 3, 3, 0.35) !important;
    color: #FFFFFF !important;
}

/* Sidebar Footer / Logout */
.portal-sidebar-footer {
    margin-top: auto;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.portal-sidebar-minimal .portal-sidebar-footer {
    align-items: center;
}

/* Minimalist Sidebar Red Logout Icon */
.portal-sidebar-minimal .portal-sidebar-footer .portal-logout-btn i {
    color: #EF4444 !important;
    transition: transform 0.18s ease, color 0.18s ease;
}

.portal-sidebar-minimal .portal-sidebar-footer .portal-logout-btn:hover {
    background: rgba(239, 68, 68, 0.16) !important;
}

.portal-sidebar-minimal .portal-sidebar-footer .portal-logout-btn:hover i {
    color: #DC2626 !important;
    transform: scale(1.1);
}

.portal-sidebar-expanded .portal-sidebar-footer .portal-logout-btn {
    color: #FFB3B3;
}

.portal-sidebar-expanded .portal-sidebar-footer .portal-logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #FFFFFF;
}

.portal-sidebar-expanded .portal-sidebar-footer .portal-logout-btn i {
    color: #FF9B9B;
}

/* Mobile responsive drawer overlay & sidebar styling */
.portal-sidebar-overlay {
    display: none !important;
}

.portal-sidebar-close-btn {
    display: none;
}

body.portal-sidebar-locked {
    overflow: hidden !important;
    touch-action: none !important;
}

@keyframes portalAccordionDown {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (min-width: 993px) {
    .portal-sidebar-minimal .portal-nav-dashboard-dock {
        display: none !important;
    }
    .portal-sidebar-minimal .portal-nav-label,
    .portal-sidebar-minimal .portal-chevron-indicator,
    .portal-sidebar-minimal .portal-brand-text-expanded,
    .portal-sidebar-minimal .portal-brand-subtext-expanded {
        display: none !important;
    }
}

@media (max-width: 992px) {
    .portal-sidebar-wrap,
    .portal-sidebar-minimal,
    .portal-sidebar-expanded {
        background: linear-gradient(180deg, #8A0B0B 0%, #760404 55%, #580101 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
    }
    .portal-sidebar-minimal .portal-sidebar-divider span {
        display: block !important;
    }
    .portal-sidebar-minimal .portal-sidebar-divider {
        width: 100% !important;
    }
    .portal-sidebar-minimal .portal-sidebar-brand {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        text-align: center !important;
        padding-bottom: 12px !important;
        margin-bottom: 10px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        width: 100% !important;
    }
    .portal-sidebar-minimal .portal-brand-logo-ring {
        width: 86px !important;
        height: 86px !important;
        margin-bottom: 8px !important;
    }
    .portal-sidebar-minimal .portal-brand-text-expanded {
        color: #FFFFFF !important;
        font-size: 0.88rem !important;
        font-weight: 800 !important;
        letter-spacing: 0.8px !important;
        line-height: 1.2 !important;
        display: block !important;
    }
    .portal-sidebar-minimal .portal-brand-subtext-expanded {
        color: rgba(255, 255, 255, 0.65) !important;
        font-size: 0.68rem !important;
        font-weight: 500 !important;
        letter-spacing: 0.4px !important;
        text-transform: uppercase !important;
        margin-top: 2px !important;
        display: block !important;
    }
    .portal-sidebar-minimal .portal-nav-label {
        display: inline-block !important;
    }
    .portal-sidebar-minimal .portal-chevron-indicator {
        display: inline-block !important;
        margin-left: auto !important;
    }
    .portal-sidebar-minimal .portal-sidebar-footer .portal-logout-btn i {
        color: #FFFFFF !important;
    }
    .portal-sidebar-minimal .portal-sidebar-footer {
        width: 100% !important;
        align-items: stretch !important;
    }
    .portal-sidebar-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.6) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
        z-index: 100090 !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transition: opacity 0.28s ease !important;
        display: block !important;
    }
    .portal-sidebar-overlay.active {
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    .portal-sidebar-wrap {
        position: fixed !important;
        left: -320px !important;
        top: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        max-height: 100vh !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
        border-radius: 0 24px 24px 0 !important;
        z-index: 100100 !important;
        display: flex !important;
        flex-direction: column !important;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5) !important;
        transition: left 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        width: min(290px, 84vw) !important;
        padding: 20px 14px 24px !important;
    }
    .portal-sidebar-wrap.mobile-open {
        left: 0 !important;
    }
    .portal-sidebar-minimal,
    .portal-sidebar-expanded {
        width: min(290px, 84vw) !important;
        padding: 20px 14px 24px !important;
        align-items: stretch !important;
    }
    .portal-sidebar-minimal .portal-brand-text-expanded,
    .portal-sidebar-minimal .portal-brand-subtext-expanded {
        display: block !important;
    }
    .portal-sidebar-minimal .portal-nav-item {
        justify-content: flex-start !important;
        flex-direction: column !important;
    }
    .portal-sidebar-expanded .portal-nav-item {
        flex-direction: column !important;
    }
    .portal-sidebar-minimal .portal-nav-btn {
        width: 100% !important;
        justify-content: flex-start !important;
        padding: 10px 14px !important;
    }
    .portal-sidebar-minimal .portal-nav-label {
        display: inline-block !important;
    }
    .portal-sidebar-minimal .portal-chevron-indicator {
        display: inline-block !important;
        margin-left: auto !important;
    }
    .portal-sidebar-minimal .portal-tooltip {
        display: none !important;
    }
    .portal-sidebar-close-btn {
        display: flex !important;
        position: absolute;
        top: 14px;
        right: 14px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #FFFFFF;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 100;
    }
    .portal-sidebar-close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }
    /* Mobile Accordion Flyouts */
    .portal-side-flyout {
        position: static !important;
        transform: none !important;
        box-shadow: none !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        background: rgba(0, 0, 0, 0.22) !important;
        border-radius: 14px !important;
        margin: 4px 0 8px 8px !important;
        padding: 8px !important;
        width: calc(100% - 8px) !important;
        min-width: 0 !important;
        display: none !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    }
    .portal-side-flyout::before,
    .portal-side-flyout::after {
        display: none !important;
    }
    .portal-side-flyout.open {
        display: block !important;
        animation: portalAccordionDown 0.24s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
    }
    .portal-side-flyout .flyout-link-item {
        color: #FFFFFF !important;
        padding: 6px 10px !important;
        border-radius: 8px !important;
        font-size: 0.72rem !important;
        gap: 8px !important;
    }
    .portal-side-flyout .flyout-link-item:hover,
    .portal-side-flyout .flyout-link-item.active {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #FBBF24 !important;
    }
    .portal-side-flyout .flyout-link-item i {
        color: #FBBF24 !important;
        font-size: 0.75rem !important;
    }
    .portal-side-flyout .flyout-header-tag {
        color: rgba(255, 255, 255, 0.6) !important;
        border-bottom-color: rgba(255, 255, 255, 0.1) !important;
        padding: 3px 6px 4px !important;
        font-size: 0.64rem !important;
    }
    .portal-chevron-indicator {
        transition: transform 0.2s ease;
    }
    .portal-nav-item.accordion-expanded .portal-chevron-indicator {
        transform: rotate(90deg);
    }
}
</style>

<!-- Mobile Drawer Overlay -->
<div class="portal-sidebar-overlay" id="portalSidebarOverlay"></div>

<!-- Resident Portal Sidebar (Minimal on Dashboard, Expanded on Other Pages) -->
<aside class="portal-sidebar-wrap <?php echo $is_dashboard ? 'portal-sidebar-minimal' : 'portal-sidebar-expanded'; ?>" id="portalSidebar" aria-label="Portal Navigation">

    <!-- Branding & Close Button -->
    <div class="portal-sidebar-brand" style="position: relative;">
        <button type="button" class="portal-sidebar-close-btn" id="portalSidebarCloseBtn" aria-label="Close Navigation Menu" title="Close Menu">
            <i class="fas fa-times"></i>
        </button>
        <div class="portal-brand-logo-ring" title="Palayan City Integrated Portal">
            <img src="images/logo.png?v=<?php echo @filemtime(__DIR__ . '/../images/logo.png') ?: time(); ?>" alt="Palayan City Seal">
        </div>
        <div class="portal-brand-text-expanded">ONE PALAYAN-KALINGA HUB</div>
        <div class="portal-brand-subtext-expanded">Integrated Service Portal</div>
    </div>

    <!-- Navigation Stack (Order: Dashboard -> Appointments -> AICS -> Housing -> Senior Citizen -> PWD -> (Divider) -> Calendar -> Printable Forms -> Documents -> Helpdesk -> Settings) -->
    <nav class="portal-sidebar-nav">

        <!-- 0. Dashboard (First Option per user request) -->
        <div class="portal-nav-item <?php if ($is_dashboard): ?>portal-nav-dashboard-dock<?php endif; ?>">
            <a href="dashboard.php" class="portal-nav-btn <?php echo $is_dashboard_active ? 'active' : ''; ?>" aria-label="Dashboard">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="portal-nav-label">Dashboard</span>
                </div>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">Dashboard</span>
                <?php endif; ?>
            </a>
        </div>

        <!-- 1. Appointment Desk (Side Flyout) -->
        <div class="portal-nav-item">
            <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo $is_appointment_active ? 'active' : ''; ?>" data-flyout="flyoutAppointments" aria-label="Appointment Desk" role="button" aria-haspopup="true">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-calendar-plus"></i>
                    <span class="portal-nav-label">Appointment Desk</span>
                </div>
                <span class="portal-chevron-indicator" data-flyout-toggle="flyoutAppointments" title="More Options"><i class="fas fa-chevron-right"></i></span>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">Appointment Desk</span>
                <?php endif; ?>
            </a>

            <!-- Appointments Flyout -->
            <div class="portal-side-flyout" id="flyoutAppointments" role="menu">
                <div class="flyout-header-tag"><i class="fas fa-calendar-check"></i> Appointment Desk</div>
                <a href="appointments.php" class="flyout-link-item <?php echo in_array($portal_current_page, ['appointments.php', 'appointment.php', 'book-appointment.php'], true) ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="my-appointments.php" class="flyout-link-item <?php echo in_array($portal_current_page, ['my-appointments.php', 'view-appointment.php'], true) ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-calendar-alt"></i> My Appointments
                </a>
            </div>
        </div>

        <!-- 1. AICS Services (Side Flyout) -->
        <div class="portal-nav-item">
            <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo $is_aics_active ? 'active' : ''; ?>" data-flyout="flyoutAics" aria-label="AICS Services" role="button" aria-haspopup="true">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-hand-holding-heart"></i>
                    <span class="portal-nav-label">AICS</span>
                </div>
                <span class="portal-chevron-indicator" data-flyout-toggle="flyoutAics" title="More Options"><i class="fas fa-chevron-right"></i></span>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">AICS Assistance</span>
                <?php endif; ?>
            </a>

            <!-- AICS Flyout (Apply for Assistance Removed per Requirement 6) -->
            <div class="portal-side-flyout" id="flyoutAics" role="menu">
                <div class="flyout-header-tag"><i class="fas fa-hand-holding-heart"></i> AICS Services</div>
                <a href="aics.php" class="flyout-link-item <?php echo ($portal_current_page === 'aics.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-hand-holding-heart"></i> AICS Assistance
                </a>
                <a href="aics-requests.php" class="flyout-link-item <?php echo ($portal_current_page === 'aics-requests.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-list-check"></i> My AICS Requests
                </a>
                <a href="aics-requirements.php" class="flyout-link-item <?php echo ($portal_current_page === 'aics-requirements.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-clipboard-check"></i> Requirements Checklist
                </a>
                <a href="track-request.php" class="flyout-link-item <?php echo (in_array($portal_current_page, ['track-request.php', 'track-aics.php']) && !$is_track_housing) ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-search-location"></i> Track Requests
                </a>
            </div>
        </div>

        <!-- 3. Housing Services (Side Flyout) -->
        <div class="portal-nav-item">
            <a href="javascript:void(0);" class="portal-nav-btn portal-flyout-trigger <?php echo $is_housing_active ? 'active' : ''; ?>" data-flyout="flyoutHousing" aria-label="Housing Programs" role="button" aria-haspopup="true">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-house-chimney"></i>
                    <span class="portal-nav-label">Housing</span>
                </div>
                <span class="portal-chevron-indicator" data-flyout-toggle="flyoutHousing" title="More Options"><i class="fas fa-chevron-right"></i></span>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">Housing Programs</span>
                <?php endif; ?>
            </a>

            <div class="portal-side-flyout" id="flyoutHousing" role="menu">
                <div class="flyout-header-tag"><i class="fas fa-house-chimney"></i> Housing Services</div>
                <a href="housing-programs.php" class="flyout-link-item <?php echo ($portal_current_page === 'housing-programs.php' || $portal_current_page === 'housing.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-home"></i> Available Programs
                </a>
                <a href="my-housing.php" class="flyout-link-item <?php echo ($portal_current_page === 'my-housing.php' || $portal_current_page === 'housing-applications.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-file-invoice"></i> My Applications
                </a>
                <a href="track-request.php?type=housing" class="flyout-link-item <?php echo ($is_track_housing || $portal_current_page === 'view-housing.php') ? 'active' : ''; ?>" role="menuitem">
                    <i class="fas fa-search-location"></i> Track Housing
                </a>
            </div>
        </div>

        <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
        <!-- 4. Senior Citizen Services (Direct Link) - Exclusively Visible for Senior Citizens (Age 60+) -->
        <?php if (!empty($user_is_senior)): ?>
        <div class="portal-nav-item">
            <a href="senior.php" class="portal-nav-btn <?php echo $is_senior_active ? 'active' : ''; ?>" aria-label="Senior Citizen Services">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-user-clock"></i>
                    <span class="portal-nav-label">Senior Citizen</span>
                    <span class="portal-tag-senior-pill" title="Auto-Detected Senior Citizen (Age 60+)"><i class="fas fa-check"></i> 60+</span>
                </div>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">Senior Citizen (60+)</span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 5. PWD Services (Direct Link) -->
        <div class="portal-nav-item">
            <a href="pwd.php" class="portal-nav-btn <?php echo $is_pwd_active ? 'active' : ''; ?>" aria-label="PWD Services">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-wheelchair"></i>
                    <span class="portal-nav-label">PWD</span>
                </div>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">PWD Services</span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Section Divider: Tools & Services -->
        <div class="portal-sidebar-divider">
            <span>Tools &amp; Services</span>
        </div>

        <!-- 6. Event Calendar -->
        <div class="portal-nav-item">
            <a href="calendar.php" class="portal-nav-btn <?php echo $is_calendar_active ? 'active' : ''; ?>" aria-label="Event Calendar">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="portal-nav-label">Event Calendar</span>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Event Calendar</span><?php endif; ?>
            </a>
        </div>

        <!-- 7. Printable Forms -->
        <div class="portal-nav-item">
            <a href="print-forms.php" class="portal-nav-btn <?php echo $is_print_active ? 'active' : ''; ?>" aria-label="Printable Forms">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-print"></i>
                    <span class="portal-nav-label">Printable Forms</span>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Printable Forms</span><?php endif; ?>
            </a>
        </div>


        <!-- 9. Helpdesk -->
        <div class="portal-nav-item">
            <a href="feedback.php" class="portal-nav-btn <?php echo $is_help_active ? 'active' : ''; ?>" aria-label="Helpdesk &amp; Feedback">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-headset"></i>
                    <span class="portal-nav-label">Helpdesk</span>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Helpdesk</span><?php endif; ?>
            </a>
        </div>

        <!-- 10. Settings -->
        <div class="portal-nav-item">
            <a href="settings.php" class="portal-nav-btn <?php echo $is_settings_active ? 'active' : ''; ?>" aria-label="Settings">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-cog"></i>
                    <span class="portal-nav-label">Settings</span>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Settings</span><?php endif; ?>
            </a>
        </div>

    </nav>

    <!-- Sidebar Footer / Logout -->
    <div class="portal-sidebar-footer">
        <div class="portal-nav-item">
            <a href="logout.php" class="portal-nav-btn portal-logout-btn" aria-label="Sign Out" title="Sign Out">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="portal-nav-label">Sign Out</span>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Sign Out</span><?php endif; ?>
            </a>
        </div>
    </div>

</aside>

<script>
// Side-oriented Popup / Flyout & Mobile Drawer Script for Portal Sidebar
(function() {
    function initPortalFlyouts() {
        const sidebar = document.getElementById('portalSidebar');
        const overlay = document.getElementById('portalSidebarOverlay');
        const closeBtn = document.getElementById('portalSidebarCloseBtn');
        const menuBtn = document.getElementById('portalMenuToggleBtn');
        const navItems = document.querySelectorAll('.portal-nav-item');
        const flyouts = document.querySelectorAll('.portal-side-flyout');
        const chevronToggles = document.querySelectorAll('[data-flyout-toggle]');
        const closeTimers = {};

        function isMobileMode() {
            return window.innerWidth <= 992;
        }

        // Attach flyouts inside their nav items on mobile so they behave as accordions;
        // Detach to document.body on desktop so they escape container bounds.
        function syncFlyoutDOM() {
            const mobile = isMobileMode();
            flyouts.forEach(flyout => {
                const flyoutId = flyout.id;
                const parentNav = sidebar ? (sidebar.querySelector(`[data-flyout="${flyoutId}"]`)?.closest('.portal-nav-item')) : null;
                if (mobile) {
                    if (parentNav && flyout.parentElement !== parentNav) {
                        parentNav.appendChild(flyout);
                    }
                } else {
                    if (flyout.parentElement !== document.body) {
                        document.body.appendChild(flyout);
                    }
                }
            });
        }
        syncFlyoutDOM();
        window.addEventListener('resize', syncFlyoutDOM);

        function closeMobileSidebar() {
            if (sidebar) sidebar.classList.remove('mobile-open');
            if (overlay) overlay.classList.remove('active');
            document.body.classList.remove('portal-sidebar-locked');
        }

        function openMobileSidebar() {
            if (sidebar) sidebar.classList.add('mobile-open');
            if (overlay) overlay.classList.add('active');
            document.body.classList.add('portal-sidebar-locked');
        }

        function toggleMobileSidebar() {
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        }

        window.openSidebar = openMobileSidebar;
        window.closeSidebar = closeMobileSidebar;
        window.toggleSidebar = toggleMobileSidebar;

        // Unified Navigation Transition Handler
        function triggerNavTransition(e, linkEl) {
            const href = linkEl.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript:')) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;

            const targetFile = href.split('?')[0].split('#')[0].replace(/^.*\//, '');

            if (targetFile === 'logout.php' || linkEl.classList.contains('portal-logout-btn')) {
                closeMobileSidebar();
                window.location.href = href;
                return;
            }

            const topLoader = document.getElementById('portalTopLoader');
            if (topLoader) topLoader.className = 'portal-top-loader active';

            closeAllFlyouts();
            closeMobileSidebar();
            window.location.href = href;
        }

        function positionFlyout(item, flyout) {
            if (isMobileMode()) return;
            const rect = item.getBoundingClientRect();
            const flyoutH = flyout.offsetHeight || 210;
            const itemCenterY = rect.top + (rect.height / 2);

            let topPos = itemCenterY - (flyoutH / 2);
            const minTop = 14;
            const maxTop = window.innerHeight - flyoutH - 14;
            topPos = Math.max(minTop, Math.min(topPos, maxTop));

            flyout.style.left = (rect.right + 10) + 'px';
            flyout.style.top = topPos + 'px';

            const arrowRelY = Math.max(16, Math.min(flyoutH - 16, itemCenterY - topPos));
            flyout.style.setProperty('--flyout-arrow-top', arrowRelY + 'px');
        }

        function closeAllFlyouts() {
            flyouts.forEach(f => {
                f.classList.remove('open');
                f.closest('.portal-nav-item')?.classList.remove('accordion-expanded');
            });
        }

        // Close on sidebar scroll or window resize
        if (sidebar) {
            const scrollNav = sidebar.querySelector('.portal-sidebar-nav');
            if (scrollNav) {
                scrollNav.addEventListener('scroll', function() {
                    if (!isMobileMode()) closeAllFlyouts();
                }, { passive: true });
            }
        }
        window.addEventListener('resize', function() {
            if (!isMobileMode()) closeAllFlyouts();
        }, { passive: true });

        // 1. Direct Sidebar Items Click Handler
        if (sidebar) {
            const directNavLinks = sidebar.querySelectorAll('.portal-sidebar-nav a.portal-nav-btn, .portal-sidebar-footer a.portal-logout-btn');
            directNavLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (e.target.closest('[data-flyout-toggle]')) return;

                    if (this.classList.contains('portal-flyout-trigger')) {
                        e.preventDefault();
                        e.stopPropagation();
                        const targetId = this.getAttribute('data-flyout');
                        const targetFlyout = document.getElementById(targetId);
                        const parentItem = this.closest('.portal-nav-item');
                        if (targetFlyout) {
                            const isOpen = targetFlyout.classList.contains('open');
                            if (isMobileMode()) {
                                // Accordion toggle on mobile
                                flyouts.forEach(f => {
                                    if (f !== targetFlyout) {
                                        f.classList.remove('open');
                                        f.closest('.portal-nav-item')?.classList.remove('accordion-expanded');
                                    }
                                });
                                targetFlyout.classList.toggle('open', !isOpen);
                                if (parentItem) parentItem.classList.toggle('accordion-expanded', !isOpen);
                            } else {
                                closeAllFlyouts();
                                if (!isOpen) {
                                    positionFlyout(parentItem, targetFlyout);
                                    targetFlyout.classList.add('open');
                                }
                            }
                        }
                        return;
                    }

                    triggerNavTransition(e, this);
                });
            });
        }

        // 2. Service Flyout Options Click Handler
        const flyoutLinks = document.querySelectorAll('.portal-side-flyout a.flyout-link-item');
        flyoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                triggerNavTransition(e, this);
            });
        });

        // 3. Safe Hover for Flyout Menus & Items (Desktop Only)
        navItems.forEach((item, idx) => {
            const trigger = item.querySelector('.portal-flyout-trigger');
            if (!trigger) return;
            const targetId = trigger.getAttribute('data-flyout');
            const flyout = document.getElementById(targetId);
            if (!flyout) return;

            item.addEventListener('mouseenter', function() {
                if (isMobileMode()) return;
                if (closeTimers[idx]) {
                    clearTimeout(closeTimers[idx]);
                    delete closeTimers[idx];
                }
                flyouts.forEach(f => {
                    if (f !== flyout) f.classList.remove('open');
                });
                positionFlyout(item, flyout);
                flyout.classList.add('open');
            });

            item.addEventListener('mouseleave', function() {
                if (isMobileMode()) return;
                closeTimers[idx] = setTimeout(() => {
                    flyout.classList.remove('open');
                    delete closeTimers[idx];
                }, 260);
            });

            flyout.addEventListener('mouseenter', function() {
                if (isMobileMode()) return;
                if (closeTimers[idx]) {
                    clearTimeout(closeTimers[idx]);
                    delete closeTimers[idx];
                }
            });

            flyout.addEventListener('mouseleave', function() {
                if (isMobileMode()) return;
                closeTimers[idx] = setTimeout(() => {
                    flyout.classList.remove('open');
                    delete closeTimers[idx];
                }, 220);
            });
        });

        // 4. Chevron specific toggle click
        chevronToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const targetId = this.getAttribute('data-flyout-toggle');
                const targetFlyout = document.getElementById(targetId);
                const parentItem = this.closest('.portal-nav-item');
                if (targetFlyout) {
                    const isOpen = targetFlyout.classList.contains('open');
                    if (isMobileMode()) {
                        flyouts.forEach(f => {
                            if (f !== targetFlyout) {
                                f.classList.remove('open');
                                f.closest('.portal-nav-item')?.classList.remove('accordion-expanded');
                            }
                        });
                        targetFlyout.classList.toggle('open', !isOpen);
                        if (parentItem) parentItem.classList.toggle('accordion-expanded', !isOpen);
                    } else {
                        closeAllFlyouts();
                        if (!isOpen) {
                            positionFlyout(parentItem, targetFlyout);
                            targetFlyout.classList.add('open');
                        }
                    }
                }
            });
        });

        // 5. Document click to close flyouts (Desktop)
        document.addEventListener('click', function(e) {
            if (isMobileMode()) return;
            if (!e.target.closest('.portal-side-flyout') && !e.target.closest('.portal-flyout-trigger')) {
                closeAllFlyouts();
            }
        });

        // 6. Escape key closes mobile drawer & flyouts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllFlyouts();
                closeMobileSidebar();
            }
        });

        // 7. Mobile drawer toggle button listener
        if (menuBtn) {
            menuBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleMobileSidebar();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileSidebar();
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeMobileSidebar();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalFlyouts);
    } else {
        initPortalFlyouts();
    }
})();
</script>
<?php require_once __DIR__ . '/chatbot-widget.php'; ?>
<?php /* include_once __DIR__ . '/mobile-bottom-nav.php'; */ ?>

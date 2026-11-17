<?php
// staff/includes/staff-sidebar.php - Responsive Sidebar for Staff Portal
// Minimal (76px, dark dock) on Staff Dashboard; Expanded (240px, rich maroon gradient) on other staff pages.
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$is_root_staff = ($portal_context ?? '') === 'root' || basename(dirname($_SERVER['PHP_SELF'])) !== 'staff';
$is_dashboard = ($current_page === 'staff-dashboard.php');

$s_base = $is_root_staff ? 'staff/' : '';
$s_dash = $is_root_staff ? 'staff-dashboard.php' : '../staff-dashboard.php';
$s_cal = $is_root_staff ? 'calendar.php' : '../calendar.php';
$s_verif = $is_root_staff ? 'staff/manage-verifications.php' : 'manage-verifications.php';
$s_logout = $is_root_staff ? 'logout.php' : '../logout.php';
$s_admin = $is_root_staff ? 'admin/dashboard.php' : '../admin/dashboard.php';
$s_img = $is_root_staff ? 'images/logo.png' : '../images/logo.png';
$logo_v = @filemtime(__DIR__ . '/../../images/logo.png') ?: time();

// Active state detection
if (!isset($active_nav)) {
    $active_nav = '';
    if ($current_page === 'staff-dashboard.php') $active_nav = 'dashboard';
    elseif (in_array($current_page, ['manage-appointments.php', 'view-appointment.php', 'appointment-settings.php'])) $active_nav = 'appointments';
    elseif ($current_page === 'calendar.php') $active_nav = 'calendar';
    elseif ($current_page === 'qr-scanner.php') $active_nav = 'scanner';
    elseif (in_array($current_page, ['manage-aics.php', 'view-aics.php', 'kanban-aics.php'])) $active_nav = 'aics';
    elseif (in_array($current_page, ['manage-housing.php', 'view-housing.php', 'kanban-housing.php'])) $active_nav = 'housing';
    elseif ($current_page === 'manage-senior.php') $active_nav = 'senior';
    elseif ($current_page === 'manage-pwd.php') $active_nav = 'pwd';
    elseif (in_array($current_page, ['manage-verifications.php', 'view-verification.php'])) $active_nav = 'verifications';
    elseif ($current_page === 'manage-residents.php') $active_nav = 'residents';
    elseif ($current_page === 'verify-documents.php') $active_nav = 'documents';
    elseif ($current_page === 'reports.php') $active_nav = 'reports';
    elseif ($current_page === 'manage-helpdesk.php') $active_nav = 'helpdesk';
    elseif (in_array($current_page, ['announcements.php', 'manage-announcements.php'])) $active_nav = 'announcements';
}

// Permissions detection - Consistent access for Admins and Assigned Staff
if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    $features_path = __DIR__ . '/../../config/features.php';
    if (file_exists($features_path)) {
        require_once $features_path;
    }
}
$is_pwd_senior_enabled = defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR;

if (function_exists('isAdmin') && isAdmin()) {
    $has_aics_access = true;
    $has_housing_access = true;
    $has_senior_access = true;
    $has_pwd_access = true;
    $has_verif_access = true;
} else {
    if (!isset($db) && class_exists('Database')) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    // Check if any access flag is still unresolved
    if (!isset($has_verif_access) || !isset($has_aics_access) || !isset($has_housing_access)) {
        if (isset($db) && isset($_SESSION['user_id'])) {
            try {
                $st_query = "SELECT s.id FROM staff s WHERE s.user_id = :uid";
                $st_stmt = $db->prepare($st_query);
                $st_stmt->execute([':uid' => $_SESSION['user_id']]);
                $st_res = $st_stmt->fetch(PDO::FETCH_ASSOC);
                $sid = $st_res ? $st_res['id'] : 0;

                if ($sid > 0) {
                    $c_query = "SELECT DISTINCT sc.category_name FROM staff_service_assignments ssa JOIN service_categories sc ON ssa.category_id = sc.category_id WHERE ssa.staff_id = :sid AND ssa.is_active = 1";
                    $c_stmt = $db->prepare($c_query);
                    $c_stmt->execute([':sid' => $sid]);
                    $cats = $c_stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($cats)) {
                        if (!isset($has_aics_access)) $has_aics_access = in_array('AICS', $cats);
                        if (!isset($has_housing_access)) $has_housing_access = in_array('Housing', $cats);
                        if (!isset($has_senior_access)) $has_senior_access = in_array('Senior Citizen', $cats) || in_array('Senior', $cats);
                        if (!isset($has_pwd_access)) $has_pwd_access = in_array('PWD', $cats);
                        if (!isset($has_verif_access)) $has_verif_access = in_array('Verification', $cats) || in_array('Residency Verification', $cats);
                    } else {
                        if (!isset($has_aics_access)) $has_aics_access = true;
                        if (!isset($has_housing_access)) $has_housing_access = true;
                        if (!isset($has_senior_access)) $has_senior_access = true;
                        if (!isset($has_pwd_access)) $has_pwd_access = true;
                        if (!isset($has_verif_access)) $has_verif_access = true;
                    }
                } else {
                    if (!isset($has_aics_access)) $has_aics_access = true;
                    if (!isset($has_housing_access)) $has_housing_access = true;
                    if (!isset($has_senior_access)) $has_senior_access = true;
                    if (!isset($has_pwd_access)) $has_pwd_access = true;
                    if (!isset($has_verif_access)) $has_verif_access = true;
                }
            } catch(Exception $e) {
                if (!isset($has_aics_access)) $has_aics_access = true;
                if (!isset($has_housing_access)) $has_housing_access = true;
                if (!isset($has_senior_access)) $has_senior_access = true;
                if (!isset($has_pwd_access)) $has_pwd_access = true;
                if (!isset($has_verif_access)) $has_verif_access = true;
            }
        } else {
            if (!isset($has_aics_access)) $has_aics_access = true;
            if (!isset($has_housing_access)) $has_housing_access = true;
            if (!isset($has_senior_access)) $has_senior_access = true;
            if (!isset($has_pwd_access)) $has_pwd_access = true;
            if (!isset($has_verif_access)) $has_verif_access = true;
        }
    }
}

// Actionable Tasks & Pending Counts for Notification Badges (Item 11)
$staff_badge_counts = [
    'pending_docs' => 0,
    'pending_aics' => 0,
    'pending_housing' => 0,
    'pending_verif' => 0,
    'pending_appts' => 0,
    'today_appts' => 0,
    'pending_helpdesk' => 0
];

if (!isset($db) && class_exists('Database')) {
    $database = new Database();
    $db = $database->getConnection();
}

if (isset($db)) {
    try {
        $staff_badge_counts['pending_docs'] = (int)$db->query("SELECT COUNT(*) FROM documents WHERE verification_status = 'pending'")->fetchColumn();
        $staff_badge_counts['pending_aics'] = (int)$db->query("SELECT COUNT(*) FROM aics_requests WHERE status IN ('submitted', 'pending')")->fetchColumn();
        $staff_badge_counts['pending_housing'] = (int)$db->query("SELECT COUNT(*) FROM housing_beneficiaries WHERE status = 'pending'")->fetchColumn();
        $staff_badge_counts['pending_verif'] = (int)$db->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'")->fetchColumn();
        $staff_badge_counts['pending_appts'] = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('pending', 'for_review')")->fetchColumn();
        $staff_badge_counts['today_appts'] = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('pending', 'confirmed')")->fetchColumn();
        $staff_badge_counts['pending_helpdesk'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open', 'ai_responded', 'under_review')")->fetchColumn();
    } catch(Exception $e) {}
}
?>

<style>
/* Notification Badge for Staff Sidebar */
.portal-badge-cnt {
    background: #DC2626;
    color: #FFFFFF;
    font-size: 0.65rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    margin-left: auto;
    box-shadow: 0 2px 4px rgba(220, 38, 38, 0.4);
}

.portal-sidebar-minimal .portal-badge-cnt {
    position: absolute;
    top: 2px;
    right: 2px;
    margin: 0;
    min-width: 16px;
    height: 16px;
    font-size: 0.6rem;
}

/* User Profile Section in Expanded Sidebar (Part 1 & 2, Item 4) */
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

/* Broken Line Big Border for Profile Circle (Resident & Staff) */
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
    border: 2px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
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
    margin: 0 0 4px;
    white-space: normal;
    word-break: break-word;
    line-height: 1.25;
}

.sidebar-user-role {
    font-size: 0.68rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.78);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* All items scrollable except pinned Sign Out footer (Part 8) */
aside.staff-sidebar.portal-sidebar-expanded {
    height: calc(100vh - 36px) !important;
    max-height: calc(100vh - 36px) !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}
aside.staff-sidebar .portal-sidebar-brand,
aside.staff-sidebar .sidebar-user-expanded {
    flex-shrink: 0 !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-sidebar-nav {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    min-height: 0 !important;
    scrollbar-width: thin !important;
    scrollbar-color: rgba(255, 255, 255, 0.25) transparent !important;
    padding-right: 2px !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-sidebar-nav::-webkit-scrollbar {
    width: 4px;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 4px;
}
aside.staff-sidebar .portal-sidebar-footer {
    flex-shrink: 0 !important;
    margin-top: auto !important;
    padding-top: 8px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
}

/* Big Project Logo in Staff Sidebar */
aside.staff-sidebar.portal-sidebar-expanded .portal-sidebar-brand {
    padding-bottom: 16px !important;
    margin-bottom: 14px !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18) !important;
    text-align: center !important;
}

/* Logo Circle for Minimal Sidebar (Dock) - Perfect Circle */
aside.staff-sidebar.portal-sidebar-minimal .portal-brand-logo-ring,
.portal-sidebar-minimal .portal-brand-logo-ring {
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;
    min-height: 48px !important;
    max-width: 48px !important;
    max-height: 48px !important;
    border-radius: 50% !important;
    aspect-ratio: 1 / 1 !important;
    background: #FFFFFF !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
    border: 2px solid rgba(255, 255, 255, 0.65) !important;
    margin: 0 auto !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}
aside.staff-sidebar.portal-sidebar-minimal .portal-brand-logo-ring img,
.portal-sidebar-minimal .portal-brand-logo-ring img {
    width: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important;
    transform: scale(1.05);
    display: block !important;
}

/* Logo Circle for Expanded Sidebar */
aside.staff-sidebar.portal-sidebar-expanded .portal-brand-logo-ring {
    width: 86px !important;
    height: 86px !important;
    min-width: 86px !important;
    min-height: 86px !important;
    max-width: 86px !important;
    max-height: 86px !important;
    border-radius: 50% !important;
    aspect-ratio: 1 / 1 !important;
    background: #FFFFFF !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 6px 22px rgba(0, 0, 0, 0.35) !important;
    border: 3.5px solid rgba(255, 255, 255, 0.65) !important;
    margin: 0 auto 12px !important;
    cursor: default !important;
    transition: transform 0.25s ease !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-brand-logo-ring:hover {
    transform: scale(1.05) !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-brand-logo-ring img {
    width: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important;
    transform: scale(1.05);
    display: block !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-brand-text-expanded {
    font-size: 0.88rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px !important;
    line-height: 1.25 !important;
}
aside.staff-sidebar.portal-sidebar-expanded .portal-brand-subtext-expanded {
    font-size: 0.70rem !important;
    color: rgba(255, 255, 255, 0.85) !important;
    margin-top: 4px !important;
}

</style>

<!-- Mobile Drawer Overlay -->
<div class="portal-sidebar-overlay" id="portalSidebarOverlay"></div>

<!-- Staff Sidebar (Minimalist on Dashboard, Expanded on Other Pages) -->
<aside class="portal-sidebar-wrap staff-sidebar <?php echo $is_dashboard ? 'portal-sidebar-minimal' : 'portal-sidebar-expanded'; ?>" id="staffSidebar" aria-label="Staff Navigation">

    <!-- Branding Header -->
    <div class="portal-sidebar-brand">
        <div class="portal-brand-logo-ring" title="Palayan City Integrated Portal">
            <img src="<?php echo $s_img; ?>?v=<?php echo $logo_v; ?>" alt="Palayan City Seal" onerror="this.src='<?php echo $is_root_staff ? 'images/logo.png' : '../images/logo.png'; ?>';">
        </div>
        <?php if (!$is_dashboard): ?>
            <div class="portal-brand-text-expanded">ONE PALAYAN-KALINGA HUB</div>
            <div class="portal-brand-subtext-expanded">Staff Operations Portal</div>
        <?php endif; ?>
    </div>

    <!-- Navigation Stack (Dashboard removed - kept in header per Item 2) -->
    <nav class="portal-sidebar-nav">

        <!-- 1. Appointments & Queue (Submenu removed per Item 3) -->
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-appointments.php" class="portal-nav-btn <?php echo in_array($active_nav, ['appointments', 'scanner']) ? 'active' : ''; ?>" aria-label="Appointments">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-calendar-check"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Appointments</span><?php endif; ?>
                </div>
                <?php if ($staff_badge_counts['pending_appts'] > 0): ?>
                    <span class="portal-badge-cnt" title="<?php echo $staff_badge_counts['pending_appts']; ?> pending appointments"><?php echo $staff_badge_counts['pending_appts']; ?></span>
                <?php endif; ?>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Appointments</span><?php endif; ?>
            </a>
        </div>

        <!-- 2. AICS Requests (Submenu removed per Item 4) -->
        <?php if ($has_aics_access): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-aics.php" class="portal-nav-btn <?php echo ($active_nav === 'aics') ? 'active' : ''; ?>" aria-label="AICS Assistance">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-hand-holding-heart"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">AICS Requests</span><?php endif; ?>
                </div>
                <?php if ($staff_badge_counts['pending_aics'] > 0): ?>
                    <span class="portal-badge-cnt" title="<?php echo $staff_badge_counts['pending_aics']; ?> pending AICS requests"><?php echo $staff_badge_counts['pending_aics']; ?></span>
                <?php endif; ?>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">AICS Assistance</span><?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 3. Housing Programs (Submenu removed per Item 4) -->
        <?php if ($has_housing_access): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-housing.php" class="portal-nav-btn <?php echo ($active_nav === 'housing') ? 'active' : ''; ?>" aria-label="Housing Programs">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-house-chimney"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Housing</span><?php endif; ?>
                </div>
                <?php if ($staff_badge_counts['pending_housing'] > 0): ?>
                    <span class="portal-badge-cnt" title="<?php echo $staff_badge_counts['pending_housing']; ?> pending housing applications"><?php echo $staff_badge_counts['pending_housing']; ?></span>
                <?php endif; ?>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Housing Programs</span><?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 4. Senior Citizen Services (OSCA Icon fixed per Item 9) -->
        <?php if (!empty($is_pwd_senior_enabled) && !empty($has_senior_access)): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-senior.php" class="portal-nav-btn <?php echo ($active_nav === 'senior') ? 'active' : ''; ?>" aria-label="Senior Citizen (OSCA)">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-person-cane"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Senior (OSCA)</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">Senior Citizen (OSCA)</span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 5. PWD Services -->
        <?php if (!empty($is_pwd_senior_enabled) && !empty($has_pwd_access)): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-pwd.php" class="portal-nav-btn <?php echo ($active_nav === 'pwd') ? 'active' : ''; ?>" aria-label="PWD Services">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-wheelchair"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">PWD Services</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?>
                    <span class="portal-tooltip">PWD Services</span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Section Divider: Verification & Records -->
        <div class="portal-sidebar-divider">
            <span>Records &amp; Tools</span>
        </div>

        <!-- Residency Verifications (Assigned Staff / Admin) -->
        <?php if (!empty($has_verif_access)): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_verif; ?>" class="portal-nav-btn <?php echo ($active_nav === 'verifications') ? 'active' : ''; ?>" aria-label="Residency Verifications">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-shield-alt"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Verifications</span><?php endif; ?>
                </div>
                <?php if (!empty($staff_badge_counts['pending_verif']) && $staff_badge_counts['pending_verif'] > 0): ?>
                    <span class="portal-badge-cnt" title="<?php echo $staff_badge_counts['pending_verif']; ?> pending verifications"><?php echo $staff_badge_counts['pending_verif']; ?></span>
                <?php endif; ?>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Residency Verifications</span><?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 7. Residents Directory -->
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-residents.php" class="portal-nav-btn <?php echo ($active_nav === 'residents') ? 'active' : ''; ?>" aria-label="Residents Directory">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-users"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Residents</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Residents Directory</span><?php endif; ?>
            </a>
        </div>

        <!-- Calendar Schedule (Moved after Residents Directory) -->
        <div class="portal-nav-item">
            <a href="<?php echo $s_cal; ?>" class="portal-nav-btn <?php echo ($active_nav === 'calendar') ? 'active' : ''; ?>" aria-label="Events Calendar">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-calendar-alt"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Calendar</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Calendar</span><?php endif; ?>
            </a>
        </div>

        <!-- Helpdesk & Citizen Support -->
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>manage-helpdesk.php" class="portal-nav-btn <?php echo ($active_nav === 'helpdesk') ? 'active' : ''; ?>" aria-label="Helpdesk &amp; Support">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-headset"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Helpdesk</span><?php endif; ?>
                </div>
                <?php if (!empty($staff_badge_counts['pending_helpdesk']) && $staff_badge_counts['pending_helpdesk'] > 0): ?>
                    <span class="portal-badge-cnt" title="<?php echo $staff_badge_counts['pending_helpdesk']; ?> pending helpdesk tickets"><?php echo $staff_badge_counts['pending_helpdesk']; ?></span>
                <?php endif; ?>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Helpdesk &amp; Support</span><?php endif; ?>
            </a>
        </div>

        <!-- 8. Reports & Analytics -->
        <div class="portal-nav-item">
            <a href="<?php echo $s_base; ?>reports.php" class="portal-nav-btn <?php echo ($active_nav === 'reports') ? 'active' : ''; ?>" aria-label="Reports &amp; Analytics">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-chart-bar"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Reports</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Reports &amp; Analytics</span><?php endif; ?>
            </a>
        </div>

        <!-- 10. Admin Panel (Conditional) -->
        <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <div class="portal-nav-item">
            <a href="<?php echo $s_admin; ?>" class="portal-nav-btn" aria-label="Admin Panel">
                <div class="portal-nav-btn-left">
                    <i class="fas fa-crown"></i>
                    <?php if (!$is_dashboard): ?><span class="portal-nav-label">Admin Panel</span><?php endif; ?>
                </div>
                <?php if ($is_dashboard): ?><span class="portal-tooltip">Admin Panel</span><?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

    </nav>

    <!-- Sidebar Footer / Logout -->
    <div class="portal-sidebar-footer">
        <div class="portal-nav-item">
            <a href="<?php echo $s_logout; ?>" class="portal-nav-btn portal-logout-btn" aria-label="Sign Out" title="Sign Out">
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
// Toggle inline accordion submenus without preventing navigation on the main link
function toggleStaffSubmenu(event, element) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const navItem = element ? element.closest('.portal-nav-item') : null;
    if (navItem) {
        navItem.classList.toggle('open');
    }
}

// Mobile drawer toggle listener
(function() {
    function initStaffMobileDrawer() {
        const sidebar = document.getElementById('staffSidebar');
        const overlay = document.getElementById('portalSidebarOverlay');
        const menuBtn = document.getElementById('portalMenuToggleBtn') || document.getElementById('staffMobileToggle');

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
        document.addEventListener('DOMContentLoaded', initStaffMobileDrawer);
    } else {
        initStaffMobileDrawer();
    }
})();
</script>

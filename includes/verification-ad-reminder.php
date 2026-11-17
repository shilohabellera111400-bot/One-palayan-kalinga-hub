<?php
// includes/verification-ad-reminder.php
// Floating bottom-right ad-like notification card & service click verification reminder modal
if (defined('PKH_VERIFICATION_AD_REMINDER_LOADED')) {
    return;
}
define('PKH_VERIFICATION_AD_REMINDER_LOADED', true);

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    return;
}
if ((function_exists('isAdmin') && isAdmin()) || (function_exists('isStaff') && isStaff())) {
    return;
}

// Current page check - do not show ad on verification pages or logout
$current_page_file = basename($_SERVER['PHP_SELF'] ?? '');
if (in_array($current_page_file, ['verify-resident.php', 'verify-documents.php', 'logout.php'], true)) {
    return;
}

// Determine verification status
$is_verified = false;
$user_verification_status = $_SESSION['verification_status'] ?? '';
$user_full_name = $_SESSION['full_name'] ?? 'Palayan Resident';

// If not marked verified in session, check fresh DB value to be 100% accurate
if ($user_verification_status !== 'verified') {
    try {
        if (!isset($db) || !($db instanceof PDO)) {
            if (class_exists('Database')) {
                $db_inst = new Database();
                $db = $db_inst->getConnection();
            }
        }
        if (isset($db) && isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT verification_status, first_name, last_name FROM users WHERE user_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $_SESSION['user_id']]);
            $u_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u_row) {
                $user_verification_status = $u_row['verification_status'] ?? 'pending';
                $_SESSION['verification_status'] = $user_verification_status;
                if (!empty($u_row['first_name'])) {
                    $user_full_name = trim($u_row['first_name'] . ' ' . ($u_row['last_name'] ?? ''));
                }
            }
        }
    } catch (Exception $e) {}
}

if ($user_verification_status === 'verified') {
    return; // User is fully verified! No reminder needed.
}

$is_new_registration = isset($_SESSION['just_registered']) || isset($_GET['registered']) || (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true);
?>
<style>
/* ==========================================================================
   PKH VERIFICATION AD-LIKE NOTIFICATION & SERVICE MODAL STYLES
   ========================================================================== */

/* 1. Floating Ad Notification Card (Upper Right Corner) */
.pkh-ad-card {
    position: fixed;
    top: 85px;
    right: 24px;
    bottom: auto;
    width: 380px;
    max-width: calc(100vw - 32px);
    background: var(--ref-card, #FFFFFF);
    color: var(--ref-text, #1E293B);
    border: 1.5px solid #F59E0B;
    border-radius: 22px;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.16), 0 4px 16px rgba(245, 158, 11, 0.15);
    padding: 18px 20px 16px;
    z-index: 999990;
    font-family: 'Plus Jakarta Sans', 'Poppins', -apple-system, sans-serif;
    transform: translateY(-40px) scale(0.92);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
    overflow: hidden;
}

[data-theme="dark"] .pkh-ad-card {
    background: #182228 !important;
    color: #F1F5F9 !important;
    border-color: #D97706 !important;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.5), 0 4px 18px rgba(217, 119, 6, 0.25) !important;
}

.pkh-ad-card.pkh-ad-active {
    transform: translateY(0) scale(1);
    opacity: 1;
    pointer-events: auto;
}

/* Wiggle animation when user clicks a service */
.pkh-ad-card.pkh-wiggle {
    animation: pkhAdWiggle 0.65s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

@keyframes pkhAdWiggle {
    10%, 90% { transform: translate3d(-3px, 0, 0) scale(1.02); }
    20%, 80% { transform: translate3d(5px, 0, 0) scale(1.02); }
    30%, 50%, 70% { transform: translate3d(-6px, 0, 0) scale(1.03); }
    40%, 60% { transform: translate3d(6px, 0, 0) scale(1.03); }
}

/* Ad Countdown Progress Loading Line */
.pkh-ad-progress-track {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: #E2E8F0;
    border-radius: 22px 22px 0 0;
    overflow: hidden;
    z-index: 100;
}
[data-theme="dark"] .pkh-ad-progress-track {
    background: #334155;
}

.pkh-ad-progress-fill {
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, #800000 0%, #E11D48 40%, #EA580C 75%, #F59E0B 100%);
    box-shadow: 0 0 12px rgba(225, 29, 72, 0.7);
    transform-origin: left center;
    border-radius: 22px 22px 0 0;
    transition: width 0.08s linear;
}

/* When pinned (user clicked), line stays solid accent */
.pkh-ad-card.pkh-ad-pinned .pkh-ad-progress-fill {
    width: 100% !important;
    background: #16A34A !important;
    box-shadow: 0 0 8px rgba(22, 163, 74, 0.5) !important;
}

/* Top bar: live badge & close */
.pkh-ad-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.pkh-ad-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #92400E;
    background: rgba(245, 158, 11, 0.14);
    padding: 3px 10px;
    border-radius: 50px;
    border: 1px solid rgba(245, 158, 11, 0.35);
}

[data-theme="dark"] .pkh-ad-tag {
    color: #FCD34D;
    background: rgba(245, 158, 11, 0.22);
}

.pkh-live-dot {
    width: 7px;
    height: 7px;
    background: #D97706;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 rgba(217, 119, 6, 0.4);
    animation: pkhPulseDot 1.8s infinite;
}

@keyframes pkhPulseDot {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(217, 119, 6, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
}

.pkh-ad-close-btn {
    background: none;
    border: none;
    color: #94A3B8;
    cursor: pointer;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.pkh-ad-close-btn:hover {
    background: rgba(0, 0, 0, 0.07);
    color: #1E293B;
}

[data-theme="dark"] .pkh-ad-close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
}

/* Body: Icon + Content */
.pkh-ad-body {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.pkh-shield-icon-wrap {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 14px;
    background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
    border: 1.5px solid #F59E0B;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #B45309;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.15);
}

[data-theme="dark"] .pkh-shield-icon-wrap {
    background: linear-gradient(135deg, #2D2415 0%, #3B2E17 100%);
    color: #FBBF24;
    border-color: #B45309;
}

.pkh-ad-content-col {
    flex: 1;
}

.pkh-ad-title {
    font-size: 0.94rem;
    font-weight: 800;
    color: var(--ref-text, #1E293B);
    margin: 0 0 4px 0;
    line-height: 1.3;
}

[data-theme="dark"] .pkh-ad-title {
    color: #F8FAFC !important;
}

.pkh-ad-desc {
    font-size: 0.77rem;
    color: #64748B;
    line-height: 1.45;
    margin: 0;
}

[data-theme="dark"] .pkh-ad-desc {
    color: #94A3B8 !important;
}

/* Steps Preview Tag */
.pkh-ad-steps-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(245, 158, 11, 0.08);
    border: 1px dashed rgba(245, 158, 11, 0.4);
    border-radius: 10px;
    padding: 7px 12px;
    margin-bottom: 14px;
    font-size: 0.71rem;
    font-weight: 700;
    color: #B45309;
}

[data-theme="dark"] .pkh-ad-steps-pill {
    background: rgba(245, 158, 11, 0.12);
    color: #FCD34D;
}

/* Action Buttons */
.pkh-ad-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pkh-ad-btn-verify {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
    color: #FFFFFF !important;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 9px 16px;
    border-radius: 50px;
    text-decoration: none !important;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3);
    transition: all 0.22s ease;
}

.pkh-ad-btn-verify:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.42);
    color: #FFFFFF !important;
}

.pkh-ad-btn-later {
    background: transparent;
    border: 1px solid var(--ref-border, #CBD5E1);
    color: #64748B;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pkh-ad-btn-later:hover {
    background: rgba(0, 0, 0, 0.04);
    color: #1E293B;
}

[data-theme="dark"] .pkh-ad-btn-later {
    border-color: rgba(255, 255, 255, 0.15);
    color: #94A3B8;
}

[data-theme="dark"] .pkh-ad-btn-later:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #F1F5F9;
}

/* 2. Minimized Floating Bubble (Bottom Right Corner) */
.pkh-ad-mini-pill {
    position: fixed;
    top: 85px;
    right: 24px;
    bottom: auto;
    background: var(--ref-card, #FFFFFF);
    color: #92400E;
    border: 1.5px solid #F59E0B;
    border-radius: 50px;
    padding: 9px 18px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.77rem;
    font-weight: 800;
    cursor: pointer;
    display: none;
    align-items: center;
    gap: 8px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(245, 158, 11, 0.25);
    z-index: 999980;
    transition: all 0.25s ease;
}

[data-theme="dark"] .pkh-ad-mini-pill {
    background: #182228;
    color: #FCD34D;
    border-color: #D97706;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.pkh-ad-mini-pill:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(245, 158, 11, 0.4);
}

.pkh-ad-mini-pill.pkh-mini-active {
    display: inline-flex;
    animation: pkhMiniPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pkhMiniPop {
    0% { transform: scale(0.6); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

/* 3. Verification Reminder Modal (Center Screen) */
.pkh-service-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.28s ease;
    padding: 16px;
    box-sizing: border-box;
}

.pkh-service-modal-overlay.pkh-modal-active {
    opacity: 1;
    pointer-events: auto;
}

.pkh-service-modal-box {
    width: 500px;
    max-width: 100%;
    background: var(--ref-card, #FFFFFF);
    color: var(--ref-text, #1E293B);
    border: 2px solid #F59E0B;
    border-radius: 28px;
    padding: 32px 30px 28px;
    position: relative;
    box-shadow: 0 25px 65px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(245, 158, 11, 0.2);
    font-family: 'Plus Jakarta Sans', 'Poppins', -apple-system, sans-serif;
    transform: translateY(24px) scale(0.95);
    transition: transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-sizing: border-box;
    text-align: center;
}

[data-theme="dark"] .pkh-service-modal-box {
    background: #182228 !important;
    color: #F8FAFC !important;
    border-color: #D97706 !important;
    box-shadow: 0 30px 75px rgba(0, 0, 0, 0.6) !important;
}

.pkh-service-modal-overlay.pkh-modal-active .pkh-service-modal-box {
    transform: translateY(0) scale(1);
}

.pkh-modal-close-corner {
    position: absolute;
    top: 18px;
    right: 20px;
    background: none;
    border: none;
    color: #94A3B8;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.pkh-modal-close-corner:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #1E293B;
}

[data-theme="dark"] .pkh-modal-close-corner:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
}

.pkh-modal-badge-row {
    margin-bottom: 12px;
}

.pkh-modal-pill-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(245, 158, 11, 0.16);
    color: #B45309;
    border: 1px solid rgba(245, 158, 11, 0.4);
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 4px 14px;
    border-radius: 50px;
}

[data-theme="dark"] .pkh-modal-pill-tag {
    color: #FCD34D;
    background: rgba(245, 158, 11, 0.22);
}

.pkh-modal-icon-center {
    display: flex;
    justify-content: center;
    margin-bottom: 14px;
}

.pkh-modal-icon-ring {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
    border: 2.5px solid #F59E0B;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    color: #B45309;
    box-shadow: 0 8px 24px rgba(245, 158, 11, 0.25);
    animation: pkhIconBounce 2.5s infinite;
}

[data-theme="dark"] .pkh-modal-icon-ring {
    background: linear-gradient(135deg, #2D2415 0%, #3B2E17 100%);
    color: #FBBF24;
    border-color: #D97706;
}

@keyframes pkhIconBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

.pkh-modal-heading {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--ref-text, #1E293B);
    margin: 0 0 10px 0;
}

[data-theme="dark"] .pkh-modal-heading {
    color: #FFFFFF !important;
}

/* Targeted service highlight banner */
.pkh-target-service-callout {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    background: rgba(127, 3, 3, 0.06);
    border: 1px solid rgba(127, 3, 3, 0.16);
    border-left: 4px solid #7F0303;
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 14px;
}

[data-theme="dark"] .pkh-target-service-callout {
    background: rgba(127, 3, 3, 0.18);
    border-color: rgba(248, 113, 113, 0.3);
    border-left-color: #F87171;
}

.pkh-target-service-callout i {
    font-size: 1.25rem;
    color: #7F0303;
}

[data-theme="dark"] .pkh-target-service-callout i {
    color: #F87171;
}

.pkh-callout-sub {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.03em;
}

[data-theme="dark"] .pkh-callout-sub {
    color: #94A3B8;
}

.pkh-target-service-callout strong {
    font-size: 0.88rem;
    color: #7F0303;
    font-weight: 800;
}

[data-theme="dark"] .pkh-target-service-callout strong {
    color: #FECACA;
}

.pkh-modal-message {
    font-size: 0.82rem;
    color: #475569;
    line-height: 1.55;
    margin: 0 0 16px 0;
}

[data-theme="dark"] .pkh-modal-message {
    color: #CBD5E1 !important;
}

/* Checklist cards inside modal */
.pkh-modal-checklist {
    text-align: left;
    background: var(--ref-card-alt, #F8FAFC);
    border-radius: 14px;
    padding: 12px 16px;
    margin-bottom: 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.06));
}

[data-theme="dark"] .pkh-modal-checklist {
    background: #111A20;
    border-color: rgba(255, 255, 255, 0.08);
}

.pkh-check-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.76rem;
    line-height: 1.4;
    color: #334155;
}

[data-theme="dark"] .pkh-check-item {
    color: #E2E8F0;
}

.pkh-check-item i {
    color: #10B981;
    font-size: 0.9rem;
    margin-top: 2px;
    flex-shrink: 0;
}

/* Modal action buttons */
.pkh-modal-actions-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pkh-modal-btn-confirm {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
    color: #FFFFFF !important;
    font-size: 0.9rem;
    font-weight: 800;
    padding: 13px 24px;
    border-radius: 50px;
    text-decoration: none !important;
    box-shadow: 0 6px 20px rgba(127, 3, 3, 0.35);
    transition: all 0.22s ease;
    box-sizing: border-box;
}

.pkh-modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(127, 3, 3, 0.48);
    color: #FFFFFF !important;
}

.pkh-modal-btn-cancel {
    background: none;
    border: none;
    color: #64748B;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 6px 12px;
    cursor: pointer;
    transition: color 0.2s ease;
}

.pkh-modal-btn-cancel:hover {
    color: #1E293B;
    text-decoration: underline;
}

[data-theme="dark"] .pkh-modal-btn-cancel:hover {
    color: #F8FAFC;
}

@media (max-width: 600px) {
    .pkh-ad-card {
        top: 75px;
        bottom: auto;
        right: 14px;
        left: 14px;
        width: auto;
        max-width: none;
        border-radius: 20px;
        padding: 16px 16px 14px;
    }
    .pkh-ad-mini-pill {
        top: 75px;
        bottom: auto;
        right: 14px;
        padding: 8px 14px;
        font-size: 0.72rem;
    }
    .pkh-service-modal-box {
        padding: 24px 20px 20px;
        border-radius: 22px;
    }
    .pkh-modal-heading {
        font-size: 1.18rem;
    }
}
</style>

<!-- Floating Ad Notification Card (Upper Right Corner) -->
<div id="pkhVerificationAdCard" class="pkh-ad-card" role="region" aria-label="Account Verification Notice">
    <!-- Animated Countdown Loading Line -->
    <div class="pkh-ad-progress-track">
        <div class="pkh-ad-progress-fill" id="pkhAdProgressBar"></div>
    </div>

    <div class="pkh-ad-top">
        <div class="pkh-ad-tag">
            <span class="pkh-live-dot"></span>
            <span><?php echo $is_new_registration ? 'ACCOUNT REGISTERED' : 'ACTION REQUIRED'; ?></span>
        </div>
        <button type="button" class="pkh-ad-close-btn" id="pkhAdCloseBtn" title="Minimize notification" aria-label="Minimize notification">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="pkh-ad-body">
        <div class="pkh-shield-icon-wrap">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="pkh-ad-content-col">
            <h4 class="pkh-ad-title">
                <?php echo $is_new_registration ? 'Welcome! Verify Your Account' : 'Residency Verification Required'; ?>
            </h4>
            <p class="pkh-ad-desc">
                Welcome to One Palayan - Kalinga Hub! Your account must be verified by CSWDO before you can apply for social welfare aid (AICS), housing, OSCA/PWD programs, or book appointments.
            </p>
        </div>
    </div>

    <div class="pkh-ad-steps-pill">
        <span><i class="fas fa-id-card"></i> 1. Submit ID</span>
        <i class="fas fa-chevron-right" style="font-size: 0.65rem; opacity: 0.5;"></i>
        <span><i class="fas fa-home"></i> 2. Proof of Residency</span>
    </div>

    <div class="pkh-ad-actions">
        <a href="verify-resident.php" class="pkh-ad-btn-verify">
            <i class="fas fa-check-circle"></i>
            <span>Verify Account Now</span>
        </a>
        <button type="button" class="pkh-ad-btn-later" id="pkhAdLaterBtn">
            Remind Later
        </button>
    </div>
</div>

<!-- Minimized Floating Pill (Upper Right Corner) -->
<button type="button" id="pkhAdMiniPill" class="pkh-ad-mini-pill" title="Click to open verification reminder">
    <span class="pkh-live-dot"></span>
    <i class="fas fa-shield-alt"></i>
    <span>Verify Account First</span>
</button>

<!-- Service Click Verification Reminder Modal (Center Screen) -->
<div id="pkhServiceVerifyModal" class="pkh-service-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="pkhModalTitle">
    <div class="pkh-service-modal-box">
        <button type="button" class="pkh-modal-close-corner" id="pkhModalCloseBtn" aria-label="Close dialog">&times;</button>
        
        <div class="pkh-modal-badge-row">
            <span class="pkh-modal-pill-tag">
                <i class="fas fa-lock"></i> Verification Required
            </span>
        </div>

        <div class="pkh-modal-icon-center">
            <div class="pkh-modal-icon-ring">
                <i class="fas fa-shield-alt"></i>
            </div>
        </div>

        <h3 id="pkhModalTitle" class="pkh-modal-heading">Complete Verification First</h3>
        
        <div class="pkh-target-service-callout">
            <i class="fas fa-hand-holding-heart"></i>
            <div>
                <span class="pkh-callout-sub">Service Requested:</span>
                <strong id="pkhTargetServiceName">Palayan City Welfare Program</strong>
            </div>
        </div>

        <p class="pkh-modal-message">
            Access to this service is reserved for verified Palayan City residents. Before submitting an application or scheduling an office appointment, please complete the residency verification with the City Social Welfare and Development Office (CSWDO).
        </p>

        <div class="pkh-modal-checklist">
            <div class="pkh-check-item">
                <i class="fas fa-check-circle"></i>
                <span><strong>Valid Government ID:</strong> PhilSys, Voter's ID, Driver's License, or Postal ID</span>
            </div>
            <div class="pkh-check-item">
                <i class="fas fa-check-circle"></i>
                <span><strong>Proof of Palayan Residency:</strong> Barangay Certificate of Residency or Billing</span>
            </div>
            <div class="pkh-check-item">
                <i class="fas fa-check-circle"></i>
                <span><strong>Fast Validation:</strong> CSWDO staff review and rapid digital approval</span>
            </div>
        </div>

        <div class="pkh-modal-actions-row">
            <a href="verify-resident.php" class="pkh-modal-btn-confirm">
                <i class="fas fa-user-check"></i>
                <span>Proceed to Verification Now</span>
            </a>
            <button type="button" class="pkh-modal-btn-cancel" id="pkhModalDismissBtn">
                I'll Do This Later
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    // -------------------------------------------------------------
    // PKH Verification Ad & Service Interception Controller
    // -------------------------------------------------------------
    const adCard = document.getElementById('pkhVerificationAdCard');
    const miniPill = document.getElementById('pkhAdMiniPill');
    const closeBtn = document.getElementById('pkhAdCloseBtn');
    const laterBtn = document.getElementById('pkhAdLaterBtn');
    const progressBar = document.getElementById('pkhAdProgressBar');

    const modalOverlay = document.getElementById('pkhServiceVerifyModal');
    const modalCloseBtn = document.getElementById('pkhModalCloseBtn');
    const modalDismissBtn = document.getElementById('pkhModalDismissBtn');
    const targetServiceEl = document.getElementById('pkhTargetServiceName');

    const STORAGE_KEY = 'pkh_verification_ad_minimized_v1';
    const PINNED_KEY = 'pkh_verification_ad_pinned_v1';

    let isPinned = false;

    const TOTAL_DURATION_MS = 8000; // 8 seconds display like an ad
    let remainingMs = TOTAL_DURATION_MS;
    let isPaused = false;
    let lastFrameTime = null;
    let animFrameId = null;

    function stepProgress(timestamp) {
        if (!lastFrameTime) lastFrameTime = timestamp;
        const delta = timestamp - lastFrameTime;
        lastFrameTime = timestamp;

        if (!isPaused && !isPinned) {
            remainingMs -= delta;
            const pct = Math.max(0, (remainingMs / TOTAL_DURATION_MS) * 100);
            if (progressBar) {
                progressBar.style.width = pct + '%';
            }
            if (remainingMs <= 0) {
                minimizeAdCard();
                return;
            }
        }
        animFrameId = requestAnimationFrame(stepProgress);
    }

    function startProgress(durationMs = TOTAL_DURATION_MS) {
        if (isPinned) return;
        remainingMs = durationMs;
        isPaused = false;
        lastFrameTime = null;
        if (progressBar) progressBar.style.width = '100%';
        if (animFrameId) cancelAnimationFrame(animFrameId);
        animFrameId = requestAnimationFrame(stepProgress);
    }

    function pauseProgress() {
        isPaused = true;
    }

    function resumeProgress() {
        if (isPinned) return;
        // When resuming after mouse leaves, ensure at least 3.5s remaining
        if (remainingMs < 3500) {
            remainingMs = 3500;
        }
        isPaused = false;
        lastFrameTime = null;
    }

    function stopProgress() {
        isPaused = true;
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }
    }

    function pinAdCard() {
        isPinned = true;
        stopProgress();
        if (adCard) adCard.classList.add('pkh-ad-pinned');
        if (progressBar) progressBar.style.width = '100%';
        try {
            sessionStorage.setItem(PINNED_KEY, '1');
            sessionStorage.removeItem(STORAGE_KEY);
        } catch(e) {}
    }

    // 1. Initialize Floating Ad State
    function showAdCard() {
        if (!adCard) return;
        if (miniPill) miniPill.classList.remove('pkh-mini-active');
        adCard.classList.add('pkh-ad-active');
        if (isPinned) {
            pinAdCard();
        } else {
            adCard.classList.remove('pkh-ad-pinned');
            startProgress(TOTAL_DURATION_MS);
        }
    }

    function minimizeAdCard() {
        if (!adCard) return;
        stopProgress();
        adCard.classList.remove('pkh-ad-active');
        if (miniPill) miniPill.classList.add('pkh-mini-active');
        try {
            sessionStorage.setItem(STORAGE_KEY, '1');
        } catch(e) {}
    }

    function restoreAdCard(withWiggle = false) {
        if (!adCard) return;
        pinAdCard(); // User actively opened it - pin it so it stays
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch(e) {}
        showAdCard();
        if (withWiggle) {
            adCard.classList.remove('pkh-wiggle');
            void adCard.offsetWidth; // Force reflow
            adCard.classList.add('pkh-wiggle');
            setTimeout(() => {
                if (adCard) adCard.classList.remove('pkh-wiggle');
            }, 700);
        }
    }

    // On page load: check minimized state
    let isMinimized = false;
    try {
        isMinimized = (sessionStorage.getItem(STORAGE_KEY) === '1');
    } catch(e) {}

    // Delay appearance slightly for smooth entrance
    setTimeout(() => {
        if (isMinimized && !isPinned) {
            if (miniPill) miniPill.classList.add('pkh-mini-active');
        } else {
            showAdCard();
        }
    }, 450);

    // Event listeners for close / minimize / restore
    if (closeBtn) closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        isPinned = false;
        try { sessionStorage.removeItem(PINNED_KEY); } catch(e) {}
        minimizeAdCard();
    });
    if (laterBtn) laterBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        isPinned = false;
        try { sessionStorage.removeItem(PINNED_KEY); } catch(e) {}
        minimizeAdCard();
    });
    if (miniPill) miniPill.addEventListener('click', () => restoreAdCard(true));

    // When cursor is pointing to the card, the countdown stops (loading line freezes)
    if (adCard) {
        adCard.addEventListener('mouseenter', () => {
            pauseProgress();
        });

        // When cursor leaves, countdown resumes (loading line drains)
        adCard.addEventListener('mouseleave', () => {
            if (!isPinned && adCard.classList.contains('pkh-ad-active')) {
                resumeProgress();
            }
        });

        // When clicked, it stays!
        adCard.addEventListener('click', (e) => {
            if (e.target.closest('#pkhAdCloseBtn') || e.target.closest('#pkhAdLaterBtn')) {
                return;
            }
            pinAdCard();
        });
    }

    // 2. Service Modal Controls
    function openServiceModal(serviceName) {
        if (targetServiceEl) {
            targetServiceEl.textContent = serviceName || 'Citizen Welfare Service';
        }
        if (modalOverlay) {
            modalOverlay.style.display = 'flex';
            void modalOverlay.offsetWidth;
            modalOverlay.classList.add('pkh-modal-active');
        }
        // Also expand and wiggle the bottom-right ad note!
        restoreAdCard(true);
    }

    function closeServiceModal() {
        if (!modalOverlay) return;
        modalOverlay.classList.remove('pkh-modal-active');
        setTimeout(() => {
            if (modalOverlay && !modalOverlay.classList.contains('pkh-modal-active')) {
                modalOverlay.style.display = 'none';
            }
        }, 300);
        // Keep the bottom right ad visible as a persistent reminder
        showAdCard();
    }

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeServiceModal);
    if (modalDismissBtn) modalDismissBtn.addEventListener('click', closeServiceModal);
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                closeServiceModal();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('pkh-modal-active')) {
            closeServiceModal();
        }
    });

    // 3. Global Service Click Interceptor (Capturing Phase)
    // Intercepts any click directed toward service pages, application forms, or appointment booking
    document.addEventListener('click', function(e) {
        const target = e.target;
        if (!target) return;

        // Check if inside any service trigger
        const trigger = target.closest(
            'a, button, article, div.ref-service-card, div.service-card, div.service-card-minimal, ' +
            '.category-apply-btn, .quick-link-card, [data-service-trigger]'
        );

        if (!trigger) return;

        // Extract href and onclick
        const href = (trigger.getAttribute('href') || '').toLowerCase().trim();
        const onclickAttr = (trigger.getAttribute('onclick') || '').toLowerCase().trim();
        const combined = href + ' ' + onclickAttr;

        // Allow sidebar flyout openers to toggle their flyouts
        if (trigger.classList.contains('sidenav-flyout-trigger') || trigger.hasAttribute('data-flyout')) {
            return;
        }

        // Whitelist safe actions (verification itself, logout, profile view, notifications, helpdesk)
        if (combined.includes('verify-resident') || combined.includes('verify-documents') || 
            combined.includes('logout') || combined.includes('profile.php') || 
            combined.includes('settings.php') || combined.includes('announcements.php') ||
            combined.includes('notifications.php') || combined.includes('contact.php') ||
            combined.includes('feedback.php') || combined.includes('documents.php') ||
            (href === '#' || href === 'javascript:void(0);' || href === 'javascript:;' || href === '')) {
            
            // Special case: if trigger has a specific service onclick even with empty href
            const hasServiceOnclick = onclickAttr.includes('aics') || onclickAttr.includes('housing') || onclickAttr.includes('appointment');
            if (!hasServiceOnclick) {
                return;
            }
        }

        // Detect service patterns
        const isAics = combined.includes('aics') || trigger.classList.contains('card-blue') || trigger.id === 'sideFlyoutAics';
        const isHousing = combined.includes('housing') || trigger.classList.contains('card-yellow') || trigger.id === 'sideFlyoutHousing';
        const isAppointment = combined.includes('appointment') || trigger.classList.contains('card-pink') || trigger.id === 'sideFlyoutApp';
        const isSenior = combined.includes('senior');
        const isPwd = combined.includes('pwd');

        // Check if this is indeed a service action
        const isService = isAics || isHousing || isAppointment || isSenior || isPwd || 
                          trigger.classList.contains('category-apply-btn') || 
                          trigger.classList.contains('ref-service-card') ||
                          trigger.classList.contains('service-card-minimal') ||
                          trigger.hasAttribute('data-service-trigger');

        if (!isService) return;

        // Intercept and prevent navigation!
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Determine friendly service name
        let svcName = 'Palayan City Welfare Service';
        if (isAics) {
            svcName = 'AICS Financial & Medical Assistance';
        } else if (isHousing) {
            svcName = 'Socialized Housing Programs';
        } else if (isAppointment) {
            svcName = 'CSWDO Appointment Desk Booking';
        } else if (isSenior) {
            svcName = 'Senior Citizen (OSCA) Benefits';
        } else if (isPwd) {
            svcName = 'Persons with Disability (PWD) Services';
        } else {
            const headingEl = trigger.querySelector('.service-card-title, h3, h4, span') || trigger;
            if (headingEl && headingEl.textContent.trim().length > 2) {
                svcName = headingEl.textContent.trim().replace(/\s+/g, ' ');
            }
        }

        // Open the reminder modal and pop up / shake the ad card!
        openServiceModal(svcName);
    }, true); // capturing phase ensures inline onclick cannot fire before this handler!
})();
</script>

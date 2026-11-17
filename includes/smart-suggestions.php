<?php
// includes/smart-suggestions.php - Smart Service Suggestion Pop-up & Contextual Notification Component
// Integrates proactive suggestions, dismissal controls, cooldown logic, and interactive requirements modal.

if (!isset($db) && class_exists('Database')) {
    $database = new Database();
    $db = $database->getConnection();
}

require_once __DIR__ . '/smart-suggestions-helper.php';

$current_page_name = basename($_SERVER['PHP_SELF'] ?? '');
$smart_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Proactive smart suggestions ONLY appear when user is on services.php (never on dashboard.php)
$is_services_page = ($current_page_name === 'services.php');
$smart_suggestions_list = [];

if ($is_services_page) {
    $smart_suggestions_list = getSmartSuggestions($db, $smart_user_id, $current_page_name);
}
?>

<?php if (!empty($smart_suggestions_list)): ?>
<style>
/* ========== CSWDO SMART SUGGESTIONS FLOATING DOCK ========== */
.cswdo-suggestion-dock {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 320px;
    max-width: calc(100vw - 32px);
    background: var(--bg-card, #FFFFFF);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(15, 65, 74, 0.10), 0 2px 6px rgba(0, 0, 0, 0.04);
    z-index: 99990;
    overflow: hidden;
    opacity: 0;
    transform: translateY(30px) scale(0.96);
    pointer-events: none;
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1), transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

[data-theme="dark"] .cswdo-suggestion-dock {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.5), 0 4px 16px rgba(0, 0, 0, 0.3);
}

.cswdo-suggestion-dock.is-visible {
    opacity: 1 !important;
    transform: translateY(0) scale(1) !important;
    pointer-events: auto !important;
    display: block !important;
    visibility: visible !important;
}

.cswdo-suggestion-dock.is-hiding {
    opacity: 0 !important;
    transform: translateY(20px) scale(0.95) !important;
    pointer-events: none !important;
}

/* Progress Bar Track */
.ssd-progress-track {
    width: 100%;
    height: 3.5px;
    background: rgba(0, 0, 0, 0.06);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .ssd-progress-track {
    background: rgba(255, 255, 255, 0.08);
}

.ssd-progress-bar {
    height: 100%;
    width: 100%;
    background: #7F0303;
    transition: width 0.1s linear;
}

/* Header Bar */
.ssd-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px 10px;
    background: rgba(127, 3, 3, 0.04);
    border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.06));
}

[data-theme="dark"] .ssd-header {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.06);
}

.ssd-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ssd-timer-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted, #718096);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 20px;
    transition: all 0.2s ease;
}

[data-theme="dark"] .ssd-timer-badge {
    background: rgba(255, 255, 255, 0.08);
    color: #CBD5E0;
}

.ssd-timer-badge i {
    font-size: 0.72rem;
    color: #718096;
}

[data-theme="dark"] .ssd-timer-badge i {
    color: #A0AEC0;
}

.ssd-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    color: #7F0303;
    padding: 3px 10px;
    background: #FFF;
    border-radius: 20px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .ssd-badge {
    background: #1F3037;
    color: #FF8A80;
}

.ssd-badge i {
    font-size: 0.8rem;
    color: #f59e0b;
    animation: ssdPulseGlow 2s infinite ease-in-out;
}

@keyframes ssdPulseGlow {
    0%, 100% { transform: scale(1); opacity: 0.9; }
    50% { transform: scale(1.2); opacity: 1; }
}

.ssd-btn-close {
    background: transparent;
    border: none;
    color: var(--text-muted, #718096);
    cursor: pointer;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
    transition: all 0.2s ease;
}

.ssd-btn-close:hover {
    background: rgba(0, 0, 0, 0.08);
    color: var(--text-primary, #1A202C);
}

[data-theme="dark"] .ssd-btn-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
}

/* Body */
.ssd-body {
    padding: 16px 18px 12px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.ssd-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #FFFFFF;
    background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25);
}

.ssd-content {
    flex: 1;
    min-width: 0;
}

.ssd-title {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--text-primary, #1A202C);
    margin: 0 0 3px;
    line-height: 1.35;
}

[data-theme="dark"] .ssd-title {
    color: #F7FAFC;
}

.ssd-tagline {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--text-muted, #718096);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.ssd-message {
    font-size: 0.82rem;
    line-height: 1.45;
    color: var(--text-secondary, #4A5568);
    margin: 0 0 8px;
}

[data-theme="dark"] .ssd-message {
    color: #CBD5E0;
}

.ssd-advisory-note {
    font-size: 0.72rem;
    color: var(--text-muted, #718096);
    display: flex;
    align-items: flex-start;
    gap: 5px;
    line-height: 1.3;
    padding-top: 4px;
    border-top: 1px dashed var(--border-color, rgba(0, 0, 0, 0.08));
}

[data-theme="dark"] .ssd-advisory-note {
    border-color: rgba(255, 255, 255, 0.08);
}

.ssd-advisory-note i {
    font-size: 0.74rem;
    margin-top: 2px;
    color: #0288D1;
}

/* Actions */
.ssd-actions {
    padding: 4px 18px 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.ssd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 11px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.ssd-btn-primary {
    background: #7F0303;
    color: #FFFFFF !important;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.3);
    flex: 1 1 auto;
}

.ssd-btn-primary:hover {
    background: #9A1515;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.4);
    color: #FFFFFF;
}

.ssd-btn-secondary {
    background: rgba(0, 0, 0, 0.05);
    color: var(--text-primary, #1A202C);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    flex: 1 1 auto;
}

[data-theme="dark"] .ssd-btn-secondary {
    background: rgba(255, 255, 255, 0.06);
    color: #E2E8F0;
    border-color: rgba(255, 255, 255, 0.1);
}

.ssd-btn-secondary:hover {
    background: rgba(0, 0, 0, 0.09);
    transform: translateY(-1px);
}

[data-theme="dark"] .ssd-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

.ssd-btn-subtle {
    background: transparent;
    color: var(--text-muted, #718096);
    border: 1px solid transparent;
    padding: 6px 10px;
    font-size: 0.78rem;
    font-weight: 600;
}

.ssd-btn-subtle:hover {
    color: var(--text-primary, #1A202C);
    background: rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .ssd-btn-subtle:hover {
    color: #FFFFFF;
    background: rgba(255, 255, 255, 0.06);
}

/* Footer / Never link */
.ssd-footer {
    padding: 4px 18px 10px;
    text-align: right;
    border-top: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
    background: rgba(0, 0, 0, 0.015);
}

[data-theme="dark"] .ssd-footer {
    border-color: rgba(255, 255, 255, 0.04);
    background: rgba(0, 0, 0, 0.1);
}

.ssd-link-never {
    background: none;
    border: none;
    font-size: 0.71rem;
    color: var(--text-muted, #888);
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.ssd-link-never:hover {
    color: var(--text-primary, #444);
}

/* Mobile responsive docking */
@media (max-width: 768px) {
    .cswdo-suggestion-dock {
        bottom: 74px !important;
        left: 12px !important;
        right: 12px !important;
        width: auto !important;
        max-width: 100% !important;
        border-radius: 14px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18) !important;
    }
    .ssd-header {
        padding: 8px 12px 6px !important;
    }
    .ssd-badge {
        font-size: 0.68rem !important;
        padding: 2px 7px !important;
    }
    .ssd-body {
        padding: 10px 12px 8px !important;
        gap: 10px !important;
    }
    .ssd-icon-wrap {
        width: 34px !important;
        height: 34px !important;
        font-size: 1rem !important;
        border-radius: 10px !important;
    }
    .ssd-title {
        font-size: 0.84rem !important;
        margin-bottom: 2px !important;
    }
    .ssd-tagline {
        display: none !important;
    }
    .ssd-message {
        font-size: 0.74rem !important;
        line-height: 1.35 !important;
        margin-bottom: 4px !important;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .ssd-advisory-note {
        display: none !important;
    }
    .ssd-actions {
        padding: 2px 12px 10px !important;
        gap: 6px !important;
    }
    .ssd-btn {
        padding: 6px 10px !important;
        font-size: 0.74rem !important;
        border-radius: 8px !important;
    }
    .ssd-btn-subtle {
        padding: 4px 6px !important;
        font-size: 0.7rem !important;
    }
    .ssd-footer {
        display: none !important;
    }
}

/* ========== INTERACTIVE REQUIREMENTS MODAL ========== */
.srm-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(10, 16, 20, 0.65);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.srm-modal-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.srm-modal-card {
    background: var(--bg-card, #FFFFFF);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    border-radius: 24px;
    max-width: 640px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    transform: translateY(20px) scale(0.96);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.srm-modal-backdrop.active .srm-modal-card {
    transform: translateY(0) scale(1);
}

[data-theme="dark"] .srm-modal-card {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
}

/* Modal Header */
.srm-modal-header {
    padding: 20px 24px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    background: rgba(127, 3, 3, 0.03);
}

[data-theme="dark"] .srm-modal-header {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.08);
}

.srm-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.srm-icon-badge {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #7F0303;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
}

.srm-sub-badge {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #7F0303;
    display: block;
    margin-bottom: 2px;
}

[data-theme="dark"] .srm-sub-badge {
    color: #FF8A80;
}

.srm-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-primary, #1A202C);
    margin: 0;
    line-height: 1.25;
}

[data-theme="dark"] .srm-title {
    color: #F7FAFC;
}

.srm-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: var(--text-muted, #718096);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.srm-close-btn:hover {
    background: rgba(0, 0, 0, 0.08);
    color: var(--text-primary, #1A202C);
}

[data-theme="dark"] .srm-close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #FFFFFF;
}

/* Modal Body */
.srm-modal-body {
    padding: 22px 24px;
    overflow-y: auto;
    max-height: calc(90vh - 170px);
}

.srm-notice-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 0.83rem;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 18px;
    line-height: 1.45;
}

[data-theme="dark"] .srm-notice-box {
    background: rgba(30, 64, 175, 0.15);
    border-color: rgba(96, 165, 250, 0.3);
    color: #93c5fd;
}

.srm-notice-box i {
    font-size: 1.1rem;
    margin-top: 2px;
    flex-shrink: 0;
}

.srm-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.srm-info-item {
    background: rgba(0, 0, 0, 0.02);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.06));
    border-radius: 12px;
    padding: 12px 14px;
}

[data-theme="dark"] .srm-info-item {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.06);
}

.srm-info-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--text-muted, #718096);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.srm-info-val {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-primary, #1A202C);
    line-height: 1.35;
}

[data-theme="dark"] .srm-info-val {
    color: #E2E8F0;
}

/* Checklist */
.srm-checklist-section {
    background: var(--bg-primary, #F8FAFC);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    border-radius: 14px;
    padding: 16px 18px;
}

[data-theme="dark"] .srm-checklist-section {
    background: #0F181B;
    border-color: rgba(255, 255, 255, 0.08);
}

.srm-section-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--text-primary, #1A202C);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

[data-theme="dark"] .srm-section-title {
    color: #F7FAFC;
}

.srm-section-title small {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted, #718096);
}

.srm-checklist {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.srm-check-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.85rem;
    color: var(--text-secondary, #334155);
    line-height: 1.4;
    cursor: pointer;
    user-select: none;
}

[data-theme="dark"] .srm-check-item {
    color: #CBD5E1;
}

.srm-check-item input[type="checkbox"] {
    margin-top: 3px;
    width: 17px;
    height: 17px;
    accent-color: #7F0303;
    cursor: pointer;
    flex-shrink: 0;
}

/* Modal Footer */
.srm-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    background: rgba(0, 0, 0, 0.015);
}

[data-theme="dark"] .srm-modal-footer {
    background: rgba(0, 0, 0, 0.15);
    border-color: rgba(255, 255, 255, 0.08);
}

.srm-footer-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.srm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 12px;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: none;
}

.srm-btn-primary {
    background: #7F0303;
    color: #FFFFFF !important;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.3);
}

.srm-btn-primary:hover {
    background: #9A1515;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.4);
}

.srm-btn-secondary {
    background: rgba(0, 0, 0, 0.06);
    color: var(--text-primary, #1A202C);
    border: 1px solid var(--border-color, rgba(0, 0, 0, 0.08));
}

[data-theme="dark"] .srm-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: #F1F5F9;
    border-color: rgba(255, 255, 255, 0.1);
}

.srm-btn-secondary:hover {
    background: rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .srm-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.14);
}

.srm-btn-print {
    background: transparent;
    color: #0288D1;
    border: 1.5px solid #0288D1;
}

.srm-btn-print:hover {
    background: rgba(2, 136, 209, 0.08);
    transform: translateY(-1px);
}

/* Hide intrusive countdown progress bar and timer badge (Not showy / Not ad-like) */
.ssd-progress-track,
.ssd-timer-badge {
    display: none !important;
}

/* Subtle & Minimalist Dock Tweaks */
.ssd-header {
    padding: 9px 14px 7px !important;
    background: rgba(15, 65, 74, 0.03) !important;
}

.ssd-body {
    padding: 12px 14px 8px !important;
    gap: 10px !important;
}

.ssd-icon-wrap {
    width: 36px !important;
    height: 36px !important;
    border-radius: 10px !important;
    font-size: 1rem !important;
    background: #0F414A !important;
    box-shadow: 0 3px 8px rgba(15, 65, 74, 0.2) !important;
}

.ssd-title {
    font-size: 0.88rem !important;
    font-weight: 700 !important;
    line-height: 1.3 !important;
}

.ssd-message {
    font-size: 0.76rem !important;
    line-height: 1.4 !important;
}

.ssd-badge {
    color: #0F414A !important;
    font-size: 0.7rem !important;
    padding: 2px 8px !important;
    background: rgba(15, 65, 74, 0.06) !important;
    box-shadow: none !important;
}

.ssd-badge i {
    animation: none !important;
    color: #C9A86C !important;
}

.ssd-actions {
    padding: 4px 14px 10px !important;
    gap: 8px !important;
}

.ssd-btn {
    padding: 6px 14px !important;
    font-size: 0.74rem !important;
    border-radius: 20px !important;
}

.ssd-btn-subtle {
    font-size: 0.7rem !important;
    padding: 4px 6px !important;
}

.ssd-footer {
    padding: 4px 14px 8px !important;
}

.ssd-link-never {
    font-size: 0.65rem !important;
}

</style>

<!-- CSWDO SMART SUGGESTION FLOATING DOCK -->
<div id="cswdoSmartSuggestionCard" class="cswdo-suggestion-dock" role="region" aria-label="Smart Service Suggestion">
    <div class="ssd-progress-track">
        <div class="ssd-progress-bar" id="ssdProgressBar"></div>
    </div>
    <div class="ssd-header">
        <div class="ssd-badge" id="ssdBannerBadge">
            <i class="fas fa-lightbulb"></i>
            <span id="ssdBannerText">Recommended For You</span>
        </div>
        <div class="ssd-header-actions">
            <span class="ssd-timer-badge" id="ssdTimerBadge" title="Suggestion will auto-dismiss in seconds">
                <i class="fas fa-stopwatch"></i>
                <span id="ssdTimerCount">8s</span>
            </span>
            <button type="button" class="ssd-btn-close" id="ssdDismissBtn" title="Dismiss for now (Maybe Later)" aria-label="Dismiss">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="ssd-body">
        <div class="ssd-icon-wrap" id="ssdIconWrap">
            <i id="ssdIcon" class="fas fa-hand-holding-heart"></i>
        </div>
        <div class="ssd-content">
            <h4 id="ssdTitle" class="ssd-title">Ready to apply for AICS Assistance?</h4>
            <div id="ssdTagline" class="ssd-tagline">CSWDO • Crisis Intervention Unit</div>
            <p id="ssdMessage" class="ssd-message">
                Financial aid for emergency medical, hospital, funeral, or educational expenses.
            </p>
            <div class="ssd-advisory-note">
                <i class="fas fa-info-circle"></i>
                <span id="ssdDisclaimer">Subject to CSWDO social worker evaluation.</span>
            </div>
        </div>
    </div>

    <div class="ssd-actions">
        <a href="#" id="ssdApplyBtn" class="ssd-btn ssd-btn-primary">
            <span id="ssdApplyText">Apply Now</span>
            <i class="fas fa-arrow-right"></i>
        </a>
        <button type="button" id="ssdRequirementsBtn" class="ssd-btn ssd-btn-secondary">
            <i class="fas fa-list-check"></i>
            <span>View Requirements</span>
        </button>
        <button type="button" id="ssdMaybeLaterBtn" class="ssd-btn ssd-btn-subtle">
            <i class="fas fa-clock"></i>
            <span>Maybe Later</span>
        </button>
    </div>

    <div class="ssd-footer">
        <button type="button" id="ssdNeverShowBtn" class="ssd-link-never">
            Don't show this suggestion again
        </button>
    </div>
</div>

<!-- SMART REQUIREMENTS MODAL -->
<div id="smartRequirementsModal" class="srm-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="srmModalTitle">
    <div class="srm-modal-card">
        <div class="srm-modal-header">
            <div class="srm-header-left">
                <div class="srm-icon-badge" id="srmIconBadge">
                    <i id="srmHeaderIcon" class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <span class="srm-sub-badge" id="srmSubBadge">Official Citizen's Charter</span>
                    <h3 id="srmModalTitle" class="srm-title">Program Requirements</h3>
                </div>
            </div>
            <button type="button" class="srm-close-btn" onclick="closeSmartRequirementsModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="srm-modal-body">
            <div class="srm-info-grid">
                <div class="srm-info-item">
                    <div class="srm-info-label"><i class="fas fa-user-check"></i> Who May Avail</div>
                    <div class="srm-info-val" id="srmEligibilityVal">Bonafide residents of Palayan City meeting program criteria.</div>
                </div>
                <div class="srm-info-item">
                    <div class="srm-info-label"><i class="fas fa-building"></i> Processing Window &amp; Time</div>
                    <div class="srm-info-val" id="srmProcessingVal">CSWDO Special Lane • 1 Day Processing</div>
                </div>
            </div>

            <div class="srm-checklist-section">
                <div class="srm-section-title">
                    <i class="fas fa-tasks"></i> Required Documents Checklist
                    <small>(Interactive self-check)</small>
                </div>
                <ul class="srm-checklist" id="srmChecklistContainer">
                    <!-- Populated dynamically -->
                </ul>
            </div>
        </div>

        <div class="srm-modal-footer">
            <div class="srm-footer-left">
                <a href="#" id="srmPrintBtn" class="srm-btn srm-btn-print" target="_blank" style="display: none;">
                    <i class="fas fa-print"></i>
                    <span id="srmPrintLabel">Print Pre-filled Form</span>
                </a>
            </div>
            <div class="srm-footer-right">
                <button type="button" class="srm-btn srm-btn-secondary" onclick="closeSmartRequirementsModal()">
                    Close
                </button>
                <a href="#" id="srmProceedApplyBtn" class="srm-btn srm-btn-primary">
                    <span>Proceed to Apply Now</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // 1. Suggestions data passed from server
    const SUGGESTIONS = <?php echo json_encode($smart_suggestions_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const STORAGE_KEY = 'cswdo_smart_suggestions_cooldowns';
    const ROTATION_KEY = 'cswdo_last_rotation_service';
    const LAST_POP_KEY = 'cswdo_last_pop_time';
    const NAV_THROTTLE_MS = 45000; // 45s throttle to prevent pop-up on every rapid page click
    const AUTO_DISMISS_SECONDS = 8;
    const TICK_MS = 100;

    if (!Array.isArray(SUGGESTIONS) || SUGGESTIONS.length === 0) {
        return;
    }

    // Check user preference
    const isGloballyDisabled = localStorage.getItem('cswdo_suggestions_enabled') === 'false';
    if (isGloballyDisabled) {
        return;
    }

    // Navigation throttle: prevent popping on every page navigation / click
    const now = Date.now();
    const lastPopTime = parseInt(sessionStorage.getItem(LAST_POP_KEY) || '0', 10);
    if (lastPopTime && (now - lastPopTime < NAV_THROTTLE_MS)) {
        return; // Skip displaying on this rapid page navigation
    }

    // Helper: read cooldowns
    function getCooldowns() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    function saveCooldowns(cd) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cd));
        } catch (e) {}
    }

    // 2. Alternating sequence: 1st: AICS -> 2nd: Housing -> 3rd: Appointment
    const ROTATION_SEQUENCE = ['aics', 'housing', 'appointment'];
    const cooldowns = getCooldowns();

    // Determine next in rotation order
    const lastRotationId = localStorage.getItem(ROTATION_KEY);
    let nextIdx = 0;
    if (lastRotationId) {
        const prevIdx = ROTATION_SEQUENCE.indexOf(lastRotationId);
        if (prevIdx !== -1) {
            nextIdx = (prevIdx + 1) % ROTATION_SEQUENCE.length;
        }
    }

    const currentPath = window.location.pathname.toLowerCase();
    let selectedSuggestion = null;

    // Search in alternating order starting from nextIdx
    for (let i = 0; i < ROTATION_SEQUENCE.length; i++) {
        const candId = ROTATION_SEQUENCE[(nextIdx + i) % ROTATION_SEQUENCE.length];
        
        // Don't suggest the service if the user is already on that service's page
        if (currentPath.includes(candId)) {
            continue;
        }

        // Check if on active cooldown
        const expiry = cooldowns[candId] || 0;
        if (now < expiry) {
            continue;
        }

        const match = SUGGESTIONS.find(s => s.id === candId);
        if (match) {
            selectedSuggestion = match;
            break;
        }
    }

    // Fallback: If rotation candidates are on cooldown or current page, check other suggestions
    if (!selectedSuggestion) {
        for (let i = 0; i < SUGGESTIONS.length; i++) {
            const item = SUGGESTIONS[i];
            const expiry = cooldowns[item.id] || 0;
            // Skip permanently dismissed (> 7 days) and current page
            if ((expiry - now < 7 * 86400 * 1000) && !currentPath.includes(item.id)) {
                selectedSuggestion = item;
                break;
            }
        }
    }

    // Secondary Fallback if user is browsing and all candidates were skipped
    if (!selectedSuggestion && SUGGESTIONS.length > 0) {
        selectedSuggestion = SUGGESTIONS.find(s => !currentPath.includes(s.id)) || SUGGESTIONS[0];
    }

    if (!selectedSuggestion) {
        return;
    }

    // Save selected suggestion into rotation memory
    localStorage.setItem(ROTATION_KEY, selectedSuggestion.id);

    // Elements
    const dockEl        = document.getElementById('cswdoSmartSuggestionCard');
    const badgeTextEl   = document.getElementById('ssdBannerText');
    const iconWrapEl    = document.getElementById('ssdIconWrap');
    const iconEl        = document.getElementById('ssdIcon');
    const titleEl       = document.getElementById('ssdTitle');
    const taglineEl     = document.getElementById('ssdTagline');
    const messageEl     = document.getElementById('ssdMessage');
    const disclaimerEl  = document.getElementById('ssdDisclaimer');
    const applyBtn      = document.getElementById('ssdApplyBtn');
    const applyText     = document.getElementById('ssdApplyText');
    const reqBtn        = document.getElementById('ssdRequirementsBtn');
    const laterBtn      = document.getElementById('ssdMaybeLaterBtn');
    const dismissBtn    = document.getElementById('ssdDismissBtn');
    const neverBtn      = document.getElementById('ssdNeverShowBtn');
    const progressBarEl = document.getElementById('ssdProgressBar');
    const timerCountEl  = document.getElementById('ssdTimerCount');

    // Populate dock content
    badgeTextEl.textContent   = selectedSuggestion.badge || 'Recommended For You';
    titleEl.textContent       = selectedSuggestion.title;
    taglineEl.textContent     = selectedSuggestion.tagline;
    messageEl.textContent     = selectedSuggestion.message;
    disclaimerEl.textContent  = selectedSuggestion.disclaimer;
    applyBtn.href             = selectedSuggestion.apply_url;
    applyText.textContent     = selectedSuggestion.apply_label || 'Apply Now';

    if (selectedSuggestion.icon) {
        iconEl.className = selectedSuggestion.icon;
    }
    if (selectedSuggestion.accent_color) {
        iconWrapEl.style.background = `linear-gradient(135deg, ${selectedSuggestion.accent_color} 0%, #1A202C 150%)`;
        applyBtn.style.background   = selectedSuggestion.accent_color;
        if (progressBarEl) {
            progressBarEl.style.background = selectedSuggestion.accent_color;
        }
    }

    // Auto-dismiss countdown timer logic with pause on hover
    let remainingMs = AUTO_DISMISS_SECONDS * 1000;
    let dismissTimer = null;
    let isHovered = false;

    function updateTimerUI() {
        const pct = Math.max(0, (remainingMs / (AUTO_DISMISS_SECONDS * 1000)) * 100);
        if (progressBarEl) {
            progressBarEl.style.width = pct + '%';
        }
        if (timerCountEl) {
            timerCountEl.textContent = Math.ceil(remainingMs / 1000) + 's';
        }
    }

    function startAutoDismissTimer() {
        if (dismissTimer) clearInterval(dismissTimer);
        updateTimerUI();
        dismissTimer = setInterval(() => {
            if (!isHovered) {
                remainingMs -= TICK_MS;
                updateTimerUI();
                if (remainingMs <= 0) {
                    clearInterval(dismissTimer);
                    dismissTimer = null;
                    // Auto-dismiss: smoothly remove from user's sight
                    hideDock(0);
                }
            }
        }, TICK_MS);
    }

    // Pause timer on mouse hover so user can read or click comfortably
    if (dockEl) {
        dockEl.addEventListener('mouseenter', function() {
            isHovered = true;
        });
        dockEl.addEventListener('mouseleave', function() {
            isHovered = false;
        });
    }

    // Prompt appearance after 600ms
    setTimeout(() => {
        if (dockEl) {
            sessionStorage.setItem(LAST_POP_KEY, Date.now().toString());
            dockEl.style.display = 'block';
            dockEl.classList.remove('is-hiding');
            dockEl.classList.add('is-visible');
            startAutoDismissTimer();
        }
    }, 4500);

    // Dismissal function with animation
    function hideDock(durationMs) {
        if (dismissTimer) {
            clearInterval(dismissTimer);
            dismissTimer = null;
        }
        if (!dockEl) return;
        dockEl.classList.remove('is-visible');
        dockEl.classList.add('is-hiding');
        setTimeout(() => {
            dockEl.style.display = 'none';
        }, 350);

        // Always update last pop time so navigating right after dismissal is throttled
        sessionStorage.setItem(LAST_POP_KEY, Date.now().toString());

        if (durationMs > 0 && selectedSuggestion) {
            const cd = getCooldowns();
            cd[selectedSuggestion.id] = Date.now() + durationMs;
            saveCooldowns(cd);
        }
    }

    // Event: Apply Now clicked
    applyBtn.addEventListener('click', function() {
        hideDock(60 * 86400 * 1000);
    });

    // Event: Maybe Later (30 minutes cooldown)
    laterBtn.addEventListener('click', function() {
        hideDock(30 * 60 * 1000);
    });

    // Event: X Close button (15 minutes cooldown)
    dismissBtn.addEventListener('click', function() {
        hideDock(15 * 60 * 1000);
    });

    // Event: Don't show again (30 days cooldown)
    neverBtn.addEventListener('click', function() {
        hideDock(30 * 86400 * 1000);
    });

    // Event: View Requirements Modal
    reqBtn.addEventListener('click', function() {
        openSmartRequirementsModal(selectedSuggestion);
    });

    // Expose modal functions
    window.openSmartRequirementsModal = function(item) {
        const modal = document.getElementById('smartRequirementsModal');
        if (!modal) return;

        const sugg = item || selectedSuggestion;
        if (!sugg) return;

        // Stop auto-dismiss timer and hide floating dock while modal is open
        if (dismissTimer) {
            clearInterval(dismissTimer);
            dismissTimer = null;
        }
        if (dockEl) {
            dockEl.classList.remove('is-visible');
            dockEl.classList.add('is-hiding');
            setTimeout(() => { dockEl.style.display = 'none'; }, 350);
        }

        document.getElementById('srmModalTitle').textContent     = sugg.service_name || sugg.title;
        document.getElementById('srmSubBadge').textContent       = sugg.tagline || "Official Citizen's Charter";
        document.getElementById('srmEligibilityVal').textContent = sugg.eligibility_summary || 'Bonafide Palayan City resident.';
        document.getElementById('srmProcessingVal').textContent  = `${sugg.window_info} • ${sugg.processing_time}`;

        const proceedBtn = document.getElementById('srmProceedApplyBtn');
        proceedBtn.href = sugg.apply_url;

        // Print Form link
        const printBtn = document.getElementById('srmPrintBtn');
        const printLbl = document.getElementById('srmPrintLabel');
        if (sugg.print_url) {
            printBtn.href = sugg.print_url;
            printLbl.textContent = sugg.print_label || 'Print Pre-filled Form';
            printBtn.style.display = 'inline-flex';
        } else {
            printBtn.style.display = 'none';
        }

        // Populate checklist
        const checklistContainer = document.getElementById('srmChecklistContainer');
        checklistContainer.innerHTML = '';
        if (Array.isArray(sugg.requirements_list)) {
            sugg.requirements_list.forEach((reqText, idx) => {
                const li = document.createElement('li');
                li.className = 'srm-check-item';
                const chkId = `srm_chk_${idx}`;
                li.innerHTML = `
                    <input type="checkbox" id="${chkId}">
                    <label for="${chkId}">${reqText}</label>
                `;
                checklistContainer.appendChild(li);
            });
        }

        // Header icon & accent
        const iconBadge = document.getElementById('srmIconBadge');
        const headerIcon = document.getElementById('srmHeaderIcon');
        if (sugg.icon) headerIcon.className = sugg.icon;
        if (sugg.accent_color) {
            iconBadge.style.background = sugg.accent_color;
            proceedBtn.style.background = sugg.accent_color;
        }

        modal.classList.add('active');
    };

    window.closeSmartRequirementsModal = function() {
        const modal = document.getElementById('smartRequirementsModal');
        if (modal) {
            modal.classList.remove('active');
        }
    };

    // Close on click outside modal card
    const modalEl = document.getElementById('smartRequirementsModal');
    if (modalEl) {
        modalEl.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSmartRequirementsModal();
            }
        });
    }

    // Keyboard ESC listener
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (modalEl && modalEl.classList.contains('active')) {
                closeSmartRequirementsModal();
            }
        }
    });

    // Provide a helper to reset dismissed suggestions & rotation (used for testing or in Settings)
    window.resetCswdoSuggestionsCooldown = function() {
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(ROTATION_KEY);
        localStorage.removeItem('cswdo_suggestions_enabled');
        sessionStorage.removeItem(LAST_POP_KEY);
        console.log('CSWDO smart suggestion cooldowns, rotation, and session throttle have been reset.');
    };

})();
</script>
<?php endif; ?>

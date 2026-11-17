<?php
// includes/process-toast.php - Sliding Process Toast Card (Upper Right Corner)
// Universal for Admin, Staff & Resident operations (e.g. notifications, announcements, updates, actions)
$session_success_msg = $_SESSION['success'] ?? ($success ?? ($ann_success ?? ''));
$session_error_msg = $_SESSION['error'] ?? ($error ?? ($ann_error ?? ''));

// Clear session alerts once captured so they don't persist on subsequent loads
if (!empty($_SESSION['success'])) unset($_SESSION['success']);
if (!empty($_SESSION['error'])) unset($_SESSION['error']);
?>
<!-- Upper-Right Sliding Process Toast Container -->
<div class="pkh-sliding-toast-container" id="pkhSlidingToastContainer" aria-live="polite" aria-atomic="true"></div>

<style>
/* ==========================================================================
   SLIDING PROCESS NOTIFICATION CARD (UPPER RIGHT CORNER)
   ========================================================================== */
.pkh-sliding-toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
    max-width: 420px;
    width: calc(100% - 48px);
}
.pkh-sliding-toast {
    pointer-events: auto;
    background: #FFFFFF;
    color: #1E293B;
    border-radius: 18px;
    padding: 16px 20px;
    box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.22), 0 0 0 1.5px rgba(5, 150, 105, 0.3);
    border-left: 6px solid #059669;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transform: translateX(130%);
    opacity: 0;
    transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
    position: relative;
    overflow: hidden;
}
.pkh-sliding-toast.show {
    transform: translateX(0);
    opacity: 1;
}
.pkh-sliding-toast.hide {
    transform: translateX(130%);
    opacity: 0;
}
.pkh-sliding-toast.toast-error {
    border-left-color: #DC2626;
    box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.22), 0 0 0 1.5px rgba(220, 38, 38, 0.3);
}
.pkh-sliding-toast-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: rgba(5, 150, 105, 0.12);
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.pkh-sliding-toast.toast-error .pkh-sliding-toast-icon {
    background: rgba(220, 38, 38, 0.12);
    color: #DC2626;
}
.pkh-sliding-toast-content {
    flex: 1;
    min-width: 0;
}
.pkh-sliding-toast-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #059669;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pkh-sliding-toast.toast-error .pkh-sliding-toast-title {
    color: #DC2626;
}
.pkh-sliding-toast-desc {
    font-size: 0.84rem;
    line-height: 1.45;
    color: #475569;
    margin: 0;
    word-break: break-word;
}
.pkh-sliding-toast-close {
    background: none;
    border: none;
    color: #94A3B8;
    cursor: pointer;
    font-size: 1.15rem;
    padding: 0;
    margin-left: 8px;
    line-height: 1;
    transition: color 0.2s;
}
.pkh-sliding-toast-close:hover {
    color: #0F172A;
}
.pkh-sliding-toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, #10B981, #059669);
    width: 100%;
    animation: toastTimer 5s linear forwards;
}
.pkh-sliding-toast.toast-error .pkh-sliding-toast-progress {
    background: linear-gradient(90deg, #EF4444, #DC2626);
}
@keyframes toastTimer {
    from { width: 100%; }
    to { width: 0%; }
}
[data-theme="dark"] .pkh-sliding-toast {
    background: #1E293B;
    color: #F8FAFC;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
}
[data-theme="dark"] .pkh-sliding-toast-desc {
    color: #CBD5E1;
}

/* Hide redundant static inline banners across Admin, Staff & Resident pages so only the sliding toast remains */
.alert-success,
.alert-error,
.alert-danger,
.alert-warning,
.alert-info,
.ann-alert-success,
.ann-alert-error,
.profile-alert-success,
.profile-alert-error,
.alert-custom.alert-success,
.alert-custom.alert-error {
    display: none !important;
}
</style>

<script>
window._pkhShownToastMessages = window._pkhShownToastMessages || new Set();

window.showProcessToast = function(message, isError = false, title = null) {
    if (!message || typeof message !== 'string') return;
    const cleanMsg = message.trim();
    if (!cleanMsg) return;

    const container = document.getElementById('pkhSlidingToastContainer');
    if (!container) return;

    if (!title) {
        title = isError ? 'Action Failed' : 'Process Completed';
    }

    // STRICTLY ONE SLIDING CARD: Remove any existing toast so only one sliding animation remains
    const existingToasts = container.querySelectorAll('.pkh-sliding-toast');
    existingToasts.forEach(t => t.remove());

    // Record message key for deduplication
    window._pkhShownToastMessages.add(cleanMsg.toLowerCase());

    const toast = document.createElement('div');
    toast.className = `pkh-sliding-toast ${isError ? 'toast-error' : 'toast-success'}`;
    toast.innerHTML = `
        <div class="pkh-sliding-toast-icon">
            <i class="fas ${isError ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i>
        </div>
        <div class="pkh-sliding-toast-content">
            <h5 class="pkh-sliding-toast-title">${title}</h5>
            <p class="pkh-sliding-toast-desc">${cleanMsg}</p>
        </div>
        <button type="button" class="pkh-sliding-toast-close" title="Dismiss" aria-label="Dismiss">&times;</button>
        <div class="pkh-sliding-toast-progress"></div>
    `;

    container.appendChild(toast);

    // Trigger sliding animation
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    const closeBtn = toast.querySelector('.pkh-sliding-toast-close');
    const dismiss = () => {
        toast.classList.remove('show');
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 450);
    };

    if (closeBtn) closeBtn.addEventListener('click', dismiss);

    // Auto dismiss after 5s
    setTimeout(dismiss, 5000);
};

// Auto-trigger on page load with deduplication and hide static inline banners
document.addEventListener('DOMContentLoaded', function() {
    let initialTriggered = false;

    <?php if (!empty($session_success_msg)): ?>
        window.showProcessToast(<?php echo json_encode($session_success_msg); ?>, false, "Process Completed");
        initialTriggered = true;
    <?php endif; ?>
    <?php if (!empty($session_error_msg)): ?>
        window.showProcessToast(<?php echo json_encode($session_error_msg); ?>, true, "Notice / Error");
        initialTriggered = true;
    <?php endif; ?>

    // Check for existing inline static alerts on the page and gracefully convert to sliding toasts
    const staticAlerts = document.querySelectorAll('.alert-success, .alert-error, .alert-danger, .profile-alert-success, .profile-alert-error, .ann-alert-success, .ann-alert-error, .alert-custom.alert-success, .alert-custom.alert-error');
    staticAlerts.forEach(alert => {
        if (alert.closest('#pkhSlidingToastContainer')) return;

        // Clone element to safely extract text without close buttons
        const clone = alert.cloneNode(true);
        const closeBtns = clone.querySelectorAll('.close-alert, .alert-close, button, i.fa-times');
        closeBtns.forEach(btn => btn.remove());
        const text = clone.textContent.replace(/[\n\r\t]+/g, ' ').replace(/\s+/g, ' ').trim();

        // Always hide the inline alert banner so only the sliding toast remains
        alert.style.setProperty('display', 'none', 'important');

        // Only trigger sliding toast if this message hasn't already been shown
        if (text && !window._pkhShownToastMessages.has(text.toLowerCase()) && !initialTriggered) {
            const isErr = alert.classList.contains('alert-error') || alert.classList.contains('alert-danger') || alert.classList.contains('ann-alert-error') || alert.classList.contains('profile-alert-error');
            window.showProcessToast(text, isErr, isErr ? 'Notice / Error' : 'Process Completed');
            initialTriggered = true;
        }
    });
});
</script>

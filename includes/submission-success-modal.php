<?php
// includes/submission-success-modal.php - Universal Succession Modal for AICS & Housing Applications
if (!defined('SUBMISSION_MODAL_STYLES_LOADED')) {
    define('SUBMISSION_MODAL_STYLES_LOADED', true);
?>
<style>
/* ==========================================================================
   UNIVERSAL APPLICATION SUCCESSION MODAL
   ========================================================================== */
.pkh-success-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 20px;
    box-sizing: border-box;
    animation: pkhFadeIn 0.25s ease-out;
}

.pkh-success-modal-card {
    background: #FFFFFF;
    color: #1E293B;
    width: 100%;
    max-width: 440px;
    border-radius: 24px;
    padding: 32px 28px 28px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25), 0 4px 16px rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.2);
    position: relative;
    box-sizing: border-box;
    animation: pkhScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

[data-theme="dark"] .pkh-success-modal-card {
    background: #182228;
    color: #F1F5F9;
    border-color: rgba(16, 185, 129, 0.35);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
}

.pkh-success-icon-wrap {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: #FFFFFF;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 10px 24px rgba(16, 185, 129, 0.35);
    margin-bottom: 20px;
    animation: pkhBounceIn 0.5s ease-out;
}

.pkh-success-modal-card h3 {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 8px 0;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .pkh-success-modal-card h3 {
    color: #F8FAFC;
}

.pkh-success-modal-card p {
    font-size: 0.88rem;
    color: #64748B;
    line-height: 1.5;
    margin: 0 0 20px 0;
}

[data-theme="dark"] .pkh-success-modal-card p {
    color: #94A3B8;
}

.pkh-success-track-box {
    background: rgba(16, 185, 129, 0.08);
    border: 1.5px dashed rgba(16, 185, 129, 0.4);
    border-radius: 16px;
    padding: 12px 16px;
    margin-bottom: 24px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: center;
}

.pkh-success-track-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #059669;
}

[data-theme="dark"] .pkh-success-track-label {
    color: #34D399;
}

.pkh-success-track-val {
    font-size: 1.45rem;
    font-weight: 900;
    color: #0F172A;
    letter-spacing: 0.04em;
    font-family: monospace, sans-serif;
}

[data-theme="dark"] .pkh-success-track-val {
    color: #FFFFFF;
}

.pkh-success-btns {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pkh-btn-see {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #7F0303;
    color: #FFFFFF !important;
    font-weight: 700;
    font-size: 0.95rem;
    padding: 12px 20px;
    border-radius: 14px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
    transition: all 0.2s ease;
}

.pkh-btn-see:hover {
    background: #990404;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(127, 3, 3, 0.35);
}

.pkh-btn-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: rgba(127, 3, 3, 0.08);
    color: #7F0303 !important;
    font-weight: 700;
    font-size: 0.92rem;
    padding: 11px 20px;
    border-radius: 14px;
    text-decoration: none;
    border: 1px solid rgba(127, 3, 3, 0.2);
    transition: all 0.2s ease;
}

.pkh-btn-add:hover {
    background: rgba(127, 3, 3, 0.14);
    transform: translateY(-1px);
}

[data-theme="dark"] .pkh-btn-add {
    background: rgba(255, 255, 255, 0.08);
    color: #F87171 !important;
    border-color: rgba(255, 255, 255, 0.15);
}

[data-theme="dark"] .pkh-btn-add:hover {
    background: rgba(255, 255, 255, 0.14);
}

@keyframes pkhFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pkhScaleUp {
    from { opacity: 0; transform: scale(0.92); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes pkhBounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    60% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}
</style>
<?php } ?>

<?php
if (!empty($submission_success) && !empty($success_tracking_no)):
    $s_tracking = $success_tracking_no;
    $s_service_type = $success_service_type ?? 'AICS';
    $see_url = ($s_service_type === 'Housing') ? 'my-housing.php' : 'aics-requests.php';
    $add_url = ($s_service_type === 'Housing') ? 'housing-programs.php' : 'aics.php';
    $see_label = ($s_service_type === 'Housing') ? 'See My Housing Application' : 'See My Request';
    $add_label = ($s_service_type === 'Housing') ? 'Apply for Another Housing Program' : 'Add a Request';
    $msg_text = ($s_service_type === 'Housing')
        ? 'Your housing application has been successfully filed with the CSWDO Palayan Housing Division. Caseworkers will review your documents.'
        : 'Your welfare aid application has been successfully submitted to CSWDO Palayan City for assessment and document validation.';
?>
<div id="submissionSuccessModal" class="pkh-success-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="pkhSuccessTitle">
    <div class="pkh-success-modal-card">
        <div class="pkh-success-icon-wrap">
            <i class="fas fa-check"></i>
        </div>
        <h3 id="pkhSuccessTitle">Application Submitted!</h3>
        <p><?php echo $msg_text; ?></p>

        <div class="pkh-success-track-box">
            <span class="pkh-success-track-label">Official Tracking Number</span>
            <span class="pkh-success-track-val"><?php echo htmlspecialchars($s_tracking); ?></span>
        </div>

        <div class="pkh-success-btns">
            <a href="<?php echo $see_url; ?>" class="pkh-btn-see">
                <i class="fas fa-list-check"></i>
                <span><?php echo $see_label; ?></span>
            </a>
            <a href="<?php echo $add_url; ?>" class="pkh-btn-add">
                <i class="fas fa-plus-circle"></i>
                <span><?php echo $add_label; ?></span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

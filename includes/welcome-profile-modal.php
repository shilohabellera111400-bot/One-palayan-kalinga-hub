<?php
// includes/welcome-profile-modal.php - Celebratory Welcome & Step-by-Step Profile Completion Modal for Verified Residents
if (!defined('WELCOME_PROFILE_MODAL_LOADED')) {
    define('WELCOME_PROFILE_MODAL_LOADED', true);

    $modal_user_name = $_SESSION['full_name'] ?? 'Resident';
    $modal_first_name = explode(' ', trim($modal_user_name))[0] ?? 'Resident';
    $modal_user_id = $_SESSION['user_id'] ?? 0;
    
    // Check profile completion state if not already defined
    if (!isset($has_profile_details)) {
        $has_profile_details = false;
        try {
            $chk_m = $db->prepare("SELECT birth_date, civil_status, occupation FROM residents WHERE user_id = :uid LIMIT 1");
            $chk_m->execute([':uid' => $modal_user_id]);
            $res_m = $chk_m->fetch(PDO::FETCH_ASSOC);
            $has_profile_details = !empty($res_m['birth_date']) && !empty($res_m['civil_status']);
            $has_socio_details = !empty($res_m['occupation']);
        } catch (Exception $e) {
            $has_socio_details = false;
        }
    } else {
        $has_socio_details = !empty($resident_record['occupation'] ?? '');
    }

    $modal_pct = $profile_percent ?? ($has_profile_details ? 75 : 40);
    $should_auto_open = !empty($_SESSION['show_welcome_modal']) || isset($_GET['verified_welcome']);
    
    // Clear session flag once consumed
    if (!empty($_SESSION['show_welcome_modal'])) {
        unset($_SESSION['show_welcome_modal']);
    }
?>
<style>
/* ==========================================================================
   VERIFIED RESIDENT WELCOME & STEP-BY-STEP PROFILE COMPLETION MODAL
   ========================================================================== */
.pkh-welcome-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999998;
    padding: 20px;
    box-sizing: border-box;
    opacity: 0;
    transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.pkh-welcome-modal-overlay.active {
    display: flex;
    opacity: 1;
}

.welcome-confetti-canvas {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 999999;
}

.pkh-welcome-modal-card {
    background: #FFFFFF;
    color: #1E293B;
    width: 100%;
    max-width: 580px;
    max-height: 90vh;
    border-radius: 28px;
    box-shadow: 0 25px 60px -10px rgba(127, 3, 3, 0.25), 0 20px 40px -15px rgba(0, 0, 0, 0.35);
    border: 1.5px solid rgba(201, 168, 108, 0.35);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transform: scale(0.92) translateY(20px);
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-sizing: border-box;
    z-index: 1000000;
}
.pkh-welcome-modal-overlay.active .pkh-welcome-modal-card {
    transform: scale(1) translateY(0);
}

[data-theme="dark"] .pkh-welcome-modal-card {
    background: #182228;
    color: #F1F5F9;
    border-color: rgba(201, 168, 108, 0.3);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
}

/* Modal Close Button */
.pkh-modal-close-btn {
    position: absolute;
    top: 18px;
    right: 18px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.06);
    border: 1px solid rgba(15, 23, 42, 0.1);
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    z-index: 10;
}
.pkh-modal-close-btn:hover {
    background: #7F0303;
    color: #FFFFFF;
    border-color: #7F0303;
    transform: scale(1.08);
}
[data-theme="dark"] .pkh-modal-close-btn {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
    color: #94A3B8;
}
[data-theme="dark"] .pkh-modal-close-btn:hover {
    background: #9A1515;
    color: #FFFFFF;
}

/* Modal Header Celebration */
.welcome-modal-header {
    background: linear-gradient(135deg, rgba(127, 3, 3, 0.06) 0%, rgba(5, 150, 105, 0.08) 50%, rgba(201, 168, 108, 0.12) 100%);
    padding: 32px 30px 22px;
    text-align: center;
    position: relative;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
[data-theme="dark"] .welcome-modal-header {
    background: linear-gradient(135deg, rgba(127, 3, 3, 0.2) 0%, rgba(5, 150, 105, 0.18) 50%, rgba(201, 168, 108, 0.15) 100%);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.welcome-seal-badge {
    width: 76px;
    height: 76px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    box-shadow: 0 10px 25px rgba(5, 150, 105, 0.35);
    position: relative;
    animation: sealPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes sealPop {
    0% { transform: scale(0.3) rotate(-20deg); opacity: 0; }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

.welcome-seal-badge .seal-check {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #C9A86C;
    border: 2px solid #FFFFFF;
    color: #7F0303;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
}

.welcome-ribbon {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 4px 14px;
    border-radius: 30px;
    background: rgba(5, 150, 105, 0.14);
    border: 1px solid rgba(5, 150, 105, 0.35);
    color: #059669;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}
[data-theme="dark"] .welcome-ribbon {
    background: rgba(52, 211, 153, 0.15);
    color: #34D399;
}

.welcome-modal-header h2 {
    margin: 0 0 8px;
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-primary, #191D1E);
    line-height: 1.3;
}
.welcome-modal-header p {
    margin: 0;
    font-size: 0.88rem;
    color: var(--text-muted, #64748B);
    line-height: 1.5;
    max-width: 460px;
    margin-left: auto;
    margin-right: auto;
}

/* Modal Body: Scrollable Stepper Guide */
.welcome-modal-body {
    padding: 24px 28px;
    overflow-y: auto;
    flex: 1;
}

.welcome-progress-bar-wrap {
    background: rgba(15, 23, 42, 0.05);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 20px;
    border: 1px solid rgba(0, 0, 0, 0.06);
}
[data-theme="dark"] .welcome-progress-bar-wrap {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
}
.progress-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.progress-meta-row .label {
    color: var(--text-muted, #64748B);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.progress-meta-row .val {
    color: #7F0303;
    font-weight: 800;
}
[data-theme="dark"] .progress-meta-row .val {
    color: #C9A86C;
}

.welcome-bar-track {
    width: 100%;
    height: 8px;
    background: #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}
[data-theme="dark"] .welcome-bar-track {
    background: #334155;
}
.welcome-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #059669 0%, #10B981 60%, #C9A86C 100%);
    border-radius: 10px;
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Step-by-Step Interactive Guide List */
.step-guide-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.guide-step-card {
    background: var(--bg-card, #FFFFFF);
    border: 1.5px solid #E2E8F0;
    border-radius: 16px;
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: all 0.2s ease;
    position: relative;
}
[data-theme="dark"] .guide-step-card {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.1);
}

.guide-step-card.done {
    border-color: rgba(5, 150, 105, 0.3);
    background: rgba(5, 150, 105, 0.03);
}
.guide-step-card.active {
    border-color: #7F0303;
    background: rgba(127, 3, 3, 0.03);
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.08);
}
[data-theme="dark"] .guide-step-card.active {
    border-color: #C9A86C;
    background: rgba(201, 168, 108, 0.06);
}

.guide-step-num {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: #E2E8F0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.95rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}
.guide-step-card.done .guide-step-num {
    background: #059669;
    color: #FFFFFF;
}
.guide-step-card.active .guide-step-num {
    background: #7F0303;
    color: #FFFFFF;
    box-shadow: 0 3px 10px rgba(127, 3, 3, 0.3);
}
[data-theme="dark"] .guide-step-card.active .guide-step-num {
    background: #C9A86C;
    color: #182228;
}

.guide-step-content {
    flex: 1;
    min-width: 0;
}
.guide-step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}
.guide-step-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-primary, #191D1E);
    margin: 0;
}
.guide-step-desc {
    font-size: 0.78rem;
    color: var(--text-muted, #64748B);
    line-height: 1.45;
    margin: 0;
}

.guide-step-pill {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.guide-step-pill.done {
    background: rgba(5, 150, 105, 0.15);
    color: #059669;
}
.guide-step-pill.current {
    background: rgba(127, 3, 3, 0.12);
    color: #7F0303;
    border: 1px solid rgba(127, 3, 3, 0.25);
    animation: pulseKicker 2s infinite;
}
[data-theme="dark"] .guide-step-pill.current {
    background: rgba(201, 168, 108, 0.2);
    color: #C9A86C;
    border-color: rgba(201, 168, 108, 0.4);
}
.guide-step-pill.next {
    background: rgba(100, 116, 139, 0.12);
    color: #64748B;
}

/* Modal Actions Footer */
.welcome-modal-footer {
    padding: 18px 28px 24px;
    background: rgba(15, 23, 42, 0.02);
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
}
[data-theme="dark"] .welcome-modal-footer {
    background: rgba(255, 255, 255, 0.02);
    border-top-color: rgba(255, 255, 255, 0.08);
}

.btn-welcome-primary {
    background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%);
    color: #FFFFFF !important;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 700;
    padding: 12px 24px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.35);
    border: none;
    cursor: pointer;
}
.btn-welcome-primary:hover {
    background: linear-gradient(135deg, #9A1515 0%, #B91C1C 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.45);
}

.btn-welcome-secondary {
    background: transparent;
    color: var(--text-muted, #64748B);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 11px 18px;
    border-radius: 50px;
    border: 1.5px solid rgba(100, 116, 139, 0.25);
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-welcome-secondary:hover {
    color: var(--text-primary, #191D1E);
    border-color: rgba(100, 116, 139, 0.45);
    background: rgba(0, 0, 0, 0.02);
}

@media (max-width: 640px) {
    .pkh-welcome-modal-card {
        max-height: 94vh;
        border-radius: 20px;
    }
    .welcome-modal-header {
        padding: 24px 20px 16px;
    }
    .welcome-modal-body {
        padding: 16px 20px;
    }
    .welcome-modal-footer {
        padding: 14px 20px 20px;
        flex-direction: column-reverse;
        width: 100%;
    }
    .btn-welcome-primary, .btn-welcome-secondary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- Fullscreen Celebration Particle Canvas -->
<canvas id="welcomeConfettiCanvas" class="welcome-confetti-canvas"></canvas>

<!-- Modal Overlay -->
<div id="welcomeProfileModalOverlay" class="pkh-welcome-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="welcomeModalTitle">
    <div class="pkh-welcome-modal-card">
        <!-- Close Button -->
        <button type="button" class="pkh-modal-close-btn" onclick="closeWelcomeProfileModal()" aria-label="Close Guide">
            <i class="fas fa-times"></i>
        </button>

        <!-- Header Celebration -->
        <div class="welcome-modal-header">
            <div class="welcome-seal-badge">
                <i class="fas fa-shield-halved"></i>
                <div class="seal-check"><i class="fas fa-check"></i></div>
            </div>
            <div class="welcome-ribbon">
                <i class="fas fa-check-double"></i> You are fully Verified
            </div>
            <h2 id="welcomeModalTitle">You are Fully Verified, <?php echo htmlspecialchars($modal_first_name); ?>! 🎉</h2>
            <p>Congratulations! Your residency and identity in Palayan City are officially authenticated and fully verified. Complete your profile details below to get instant access to all CSWDO social welfare services.</p>
        </div>

        <!-- Scrollable Body with Step-by-Step Roadmap -->
        <div class="welcome-modal-body">
            <!-- Progress Status Bar -->
            <div class="welcome-progress-bar-wrap">
                <div class="progress-meta-row">
                    <span class="label"><i class="fas fa-list-check"></i> Onboarding Roadmap</span>
                    <span class="val"><?php echo $modal_pct; ?>% Profile Completed</span>
                </div>
                <div class="welcome-bar-track">
                    <div class="welcome-bar-fill" style="width: <?php echo $modal_pct; ?>%;"></div>
                </div>
            </div>

            <!-- 4-Stage Step-by-Step Process -->
            <div class="step-guide-list">
                <!-- Step 1: Residency & ID Verification (Done) -->
                <div class="guide-step-card done">
                    <div class="guide-step-num"><i class="fas fa-check"></i></div>
                    <div class="guide-step-content">
                        <div class="guide-step-header">
                            <h4 class="guide-step-title">1. Residency & ID Authentication</h4>
                            <span class="guide-step-pill done"><i class="fas fa-check-circle"></i> Verified ✓</span>
                        </div>
                        <p class="guide-step-desc">PhilSys / Government ID credentials and biometric facial alignment approved on city records.</p>
                    </div>
                </div>

                <!-- Step 2: Citizen Demographics & Civil Status (Action Required) -->
                <div class="guide-step-card <?php echo $has_profile_details ? 'done' : 'active'; ?>">
                    <div class="guide-step-num"><?php echo $has_profile_details ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                    <div class="guide-step-content">
                        <div class="guide-step-header">
                            <h4 class="guide-step-title">2. Personal Demographics & Civil Status</h4>
                            <?php if ($has_profile_details): ?>
                                <span class="guide-step-pill done"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php else: ?>
                                <span class="guide-step-pill current"><i class="fas fa-pen-to-square"></i> Next Step</span>
                            <?php endif; ?>
                        </div>
                        <p class="guide-step-desc">Birth date, gender, civil status, and family role needed to calculate your social welfare assistance tier.</p>
                    </div>
                </div>

                <!-- Step 3: Socio-Economic & Family Background -->
                <div class="guide-step-card <?php echo $has_socio_details ? 'done' : (!$has_profile_details ? '' : 'active'); ?>">
                    <div class="guide-step-num"><?php echo $has_socio_details ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                    <div class="guide-step-content">
                        <div class="guide-step-header">
                            <h4 class="guide-step-title">3. Household & Socio-Economic Info</h4>
                            <?php if ($has_socio_details): ?>
                                <span class="guide-step-pill done"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php elseif ($has_profile_details): ?>
                                <span class="guide-step-pill current"><i class="fas fa-arrow-right"></i> Next Step</span>
                            <?php else: ?>
                                <span class="guide-step-pill next"><i class="fas fa-clock"></i> Step 3</span>
                            <?php endif; ?>
                        </div>
                        <p class="guide-step-desc">Declared occupation, monthly household income bracket, and dependents for AICS & Housing qualifications.</p>
                    </div>
                </div>

                <!-- Step 4: Emergency Contacts & Citizen QR Credential -->
                <div class="guide-step-card <?php echo !empty($avatar_url) ? 'done' : ''; ?>">
                    <div class="guide-step-num"><?php echo !empty($avatar_url) ? '<i class="fas fa-check"></i>' : '4'; ?></div>
                    <div class="guide-step-content">
                        <div class="guide-step-header">
                            <h4 class="guide-step-title">4. Emergency Contact & Citizen QR ID</h4>
                            <?php if (!empty($avatar_url)): ?>
                                <span class="guide-step-pill done"><i class="fas fa-check-circle"></i> Ready</span>
                            <?php else: ?>
                                <span class="guide-step-pill next"><i class="fas fa-qrcode"></i> Final Step</span>
                            <?php endif; ?>
                        </div>
                        <p class="guide-step-desc">Emergency contact person and portrait photo for your official digital citizen QR pass at Palayan City Hall.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="welcome-modal-footer">
            <button type="button" class="btn-welcome-secondary" onclick="closeWelcomeProfileModal()">
                <i class="fas fa-house"></i> Explore Dashboard First
            </button>
            <a href="update-profile-info.php?guided=1" class="btn-welcome-primary">
                <i class="fas fa-user-edit"></i> Proceed to Complete Profile &rarr;
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    const userId = <?php echo json_encode($modal_user_id); ?>;
    const shouldAutoOpen = <?php echo json_encode($should_auto_open); ?>;
    const storageKey = 'pkh_verified_welcome_seen_' + userId;

    // Check if dismissed before
    const hasBeenDismissed = localStorage.getItem(storageKey) === '1';

    window.openWelcomeProfileModal = function() {
        const overlay = document.getElementById('welcomeProfileModalOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        // Force reflow
        void overlay.offsetWidth;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        fireWelcomeConfetti();
    };

    window.closeWelcomeProfileModal = function(remember = true) {
        const overlay = document.getElementById('welcomeProfileModalOverlay');
        if (!overlay) return;
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 350);
        if (remember) {
            localStorage.setItem(storageKey, '1');
        }
    };

    // Close on backdrop click
    const overlayEl = document.getElementById('welcomeProfileModalOverlay');
    if (overlayEl) {
        overlayEl.addEventListener('click', function(e) {
            if (e.target === this) {
                closeWelcomeProfileModal(true);
            }
        });
    }

    // Automatic popup removed per user request
    // The modal will not pop up on page load.

    // Confetti Animation Controller (Pure HTML5 Canvas)
    function fireWelcomeConfetti() {
        const canvas = document.getElementById('welcomeConfettiCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        const colors = ['#7F0303', '#C9A86C', '#059669', '#10B981', '#0F414A', '#E11D48'];
        const particles = [];
        const count = 110;

        for (let i = 0; i < count; i++) {
            particles.push({
                x: width / 2 + (Math.random() - 0.5) * 300,
                y: height / 3 + (Math.random() - 0.5) * 100,
                vx: (Math.random() - 0.5) * 14,
                vy: (Math.random() * -12) - 4,
                size: Math.random() * 8 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                rotation: Math.random() * 360,
                rotSpeed: (Math.random() - 0.5) * 12,
                opacity: 1,
                decay: Math.random() * 0.007 + 0.005
            });
        }

        let animId = null;
        let startTime = performance.now();

        function renderConfetti(time) {
            ctx.clearRect(0, 0, width, height);
            let alive = false;

            for (let i = 0; i < particles.length; i++) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.28; // gravity
                p.vx *= 0.985;
                p.rotation += p.rotSpeed;
                p.opacity -= p.decay;

                if (p.opacity > 0) {
                    alive = true;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation * Math.PI / 180);
                    ctx.fillStyle = p.color;
                    ctx.globalAlpha = Math.max(0, p.opacity);
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.7);
                    ctx.restore();
                }
            }

            if (alive && (time - startTime < 4200)) {
                animId = requestAnimationFrame(renderConfetti);
            } else {
                ctx.clearRect(0, 0, width, height);
                if (animId) cancelAnimationFrame(animId);
            }
        }

        animId = requestAnimationFrame(renderConfetti);
    }
})();
</script>
<?php
}
?>

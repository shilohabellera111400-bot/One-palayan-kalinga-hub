# scratch/restore_welcome_modal.py

with open('dashboard.php', 'r', encoding='utf-8') as f:
    code = f.read().replace('\r\n', '\n')

# 1. Add show_welcome_modal calculation around line 155
target_guided = """    $guided_title = 'Account Fully Verified 🎉';
    $guided_desc = 'You have unrestricted access to all CSWDO Online Services, Appointments, AICS Financial Assistance, and Senior/PWD benefits.';
    $guided_btn = 'View Profile';
}"""

replacement_guided = """    $guided_title = 'Account Fully Verified 🎉';
    $guided_desc = 'You have unrestricted access to all CSWDO Online Services, Appointments, AICS Financial Assistance, and Senior/PWD benefits.';
    $guided_btn = 'View Profile';
}

// Check whether to show the Verified Resident Welcome & Profile Completion modal
$show_welcome_modal = false;
if ($verification_status === 'verified' && !isStaff() && !isAdmin()) {
    if (!empty($_SESSION['show_welcome_modal']) || !$has_profile_details) {
        $show_welcome_modal = true;
    }
}"""

if target_guided in code:
    code = code.replace(target_guided, replacement_guided, 1)
    print("Guided step logic updated with show_welcome_modal check.")
else:
    print("Target guided not found!")

# 2. Add portal-motion.css in <head>
target_head = """    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600;1,700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800&display=swap" rel="stylesheet">"""

replacement_head = """    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,600;1,700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-motion.css?v=<?php echo @filemtime(__DIR__ . '/css/portal-motion.css') ?: time(); ?>">"""

if target_head in code:
    code = code.replace(target_head, replacement_head, 1)
    print("portal-motion.css added to <head>.")
else:
    print("Target head not found!")

# 3. Add modal HTML right after <body>
target_body = """<body>
    <!-- Outer Dashboard Frame -->
    <div class="dashboard-container">"""

replacement_body = """<body>
    <?php if ($show_welcome_modal): ?>
    <!-- OFFICIALLY VERIFIED RESIDENT WELCOME CELEBRATION & PROFILE COMPLETION GUIDE MODAL -->
    <div class="welcome-modal-backdrop" id="welcomeModalBackdrop" data-auto-open="true" role="dialog" aria-modal="true" aria-labelledby="welcomeModalTitle">
        <div class="welcome-modal-card" style="max-width: 580px;">
            <div class="welcome-modal-glow"></div>
            <div class="welcome-modal-header">
                <div class="welcome-badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; border-color: rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-certificate"></i> Officially Verified Resident
                </div>
                <button type="button" class="welcome-modal-close" id="welcomeCloseBtn" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="welcome-modal-icon-wrap">
                <div class="welcome-icon-ring" style="background: linear-gradient(135deg, #10B981 0%, #047857 100%);">
                    <i class="fas fa-user-check"></i>
                </div>
                <span class="welcome-sparkle-dot dot-1"></span>
                <span class="welcome-sparkle-dot dot-2"></span>
            </div>
            <div class="welcome-modal-body">
                <h2 id="welcomeModalTitle" style="font-size: 1.4rem; font-weight: 800; margin-bottom: 8px;">
                    Welcome to Palayan Kalinga Hub! 🎉
                </h2>
                <p style="font-size: 0.88rem; color: var(--ref-muted, #64748B); line-height: 1.5; margin-bottom: 18px;">
                    Congratulations, <strong><?php echo htmlspecialchars($user['first_name'] ?? 'Resident'); ?></strong>! Your residency has been officially validated and approved by the <strong>City Social Welfare and Development Office (CSWDO)</strong>.
                </p>

                <!-- Interactive Guide to Complete Profile (Visual 3-Step Milestone Roadmap) -->
                <div class="welcome-guide-steps">
                    <!-- Step 1: Verified (Completed) -->
                    <div class="guide-step-card step-done">
                        <div class="step-num-badge"><i class="fas fa-check"></i></div>
                        <div class="step-details">
                            <div class="step-head">
                                <strong>1. Residency Verification</strong>
                                <span class="step-tag-done"><i class="fas fa-shield-alt"></i> Officially Verified</span>
                            </div>
                            <p>Government ID and residence records validated &amp; approved by CSWDO.</p>
                        </div>
                    </div>

                    <!-- Step 2: Complete Profile (Active / Action Needed) -->
                    <div class="guide-step-card step-active">
                        <div class="step-num-badge step-pulse">2</div>
                        <div class="step-details">
                            <div class="step-head">
                                <strong>2. Complete Resident Profile</strong>
                                <span class="step-tag-action"><i class="fas fa-edit"></i> Action Required</span>
                            </div>
                            <p>Add birthdate, civil status, occupation, and emergency contact for 1-click requests.</p>
                        </div>
                    </div>

                    <!-- Step 3: Access All Services -->
                    <div class="guide-step-card step-locked">
                        <div class="step-num-badge">3</div>
                        <div class="step-details">
                            <div class="step-head">
                                <strong>3. 1-Click Assistance &amp; Benefits</strong>
                                <span class="step-tag-lock"><i class="fas fa-unlock"></i> Unlocks Services</span>
                            </div>
                            <p>Fast-track financial grants, medical support, housing, and priority consultations.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="welcome-modal-actions">
                <a href="update-profile-info.php?guided=1" id="welcomeCompleteProfileBtn" class="btn-welcome-primary">
                    <span><i class="fas fa-user-edit"></i> Complete My Profile Now</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <button type="button" id="welcomeMaybeLaterBtn" class="btn-welcome-subtle">
                    I'll complete this later &bull; Explore Dashboard
                </button>
            </div>
        </div>
    </div>
    <canvas id="welcomeConfettiCanvas" style="position: fixed; inset: 0; pointer-events: none; z-index: 999999; width: 100vw; height: 100vh;"></canvas>
    <?php endif; ?>

    <!-- Outer Dashboard Frame -->
    <div class="dashboard-container">"""

if target_body in code:
    code = code.replace(target_body, replacement_body, 1)
    print("Welcome modal HTML added after <body>.")
else:
    print("Target body not found!")

# 4. Replace bottom scripts with Confetti & portal-motion.js
target_scripts = """    <!-- Verified Resident Celebration Modal & Interactive Confetti Controller -->
    <?php if ($verification_status === 'verified' && !isStaff() && !isAdmin()): ?>
    <script>
        (function() {
            const userId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
            window.__pkh_user_id = userId;
        })();
    </script>
    <script src="js/celebration-confetti.js"></script>
    <?php endif; ?>"""

replacement_scripts = """    <script>
        (function() {
            const userId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
            window.__pkh_user_id = userId;
            
            // Confetti Cannon for Welcome Modal
            function fireWelcomeConfetti() {
                const canvas = document.getElementById('welcomeConfettiCanvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;

                const colors = ['#7B1113', '#C9A86C', '#10B981', '#3B82F6', '#F59E0B', '#EC4899'];
                const particles = [];
                for (let i = 0; i < 90; i++) {
                    particles.push({
                        x: canvas.width / 2 + (Math.random() - 0.5) * 200,
                        y: canvas.height * 0.4 + (Math.random() - 0.5) * 100,
                        vx: (Math.random() - 0.5) * 12,
                        vy: -Math.random() * 10 - 4,
                        size: Math.random() * 8 + 4,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        rotation: Math.random() * 360,
                        rotSpeed: (Math.random() - 0.5) * 10,
                        opacity: 1
                    });
                }

                let animFrame;
                let startTime = Date.now();
                function render() {
                    const elapsed = Date.now() - startTime;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    let alive = 0;

                    particles.forEach(p => {
                        p.x += p.vx;
                        p.y += p.vy;
                        p.vy += 0.28; // gravity
                        p.rotation += p.rotSpeed;
                        if (elapsed > 1800) {
                            p.opacity = Math.max(0, p.opacity - 0.02);
                        }

                        if (p.opacity > 0 && p.y < canvas.height + 20) {
                            alive++;
                            ctx.save();
                            ctx.translate(p.x, p.y);
                            ctx.rotate((p.rotation * Math.PI) / 180);
                            ctx.globalAlpha = p.opacity;
                            ctx.fillStyle = p.color;
                            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                            ctx.restore();
                        }
                    });

                    if (alive > 0 && elapsed < 4000) {
                        animFrame = requestAnimationFrame(render);
                    } else {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                    }
                }
                animFrame = requestAnimationFrame(render);
            }

            // Expose for portal-motion.js
            window.fireDirectConfetti = fireWelcomeConfetti;

            // Welcome Modal Controller
            const modal = document.getElementById('welcomeModalBackdrop');
            const closeBtn = document.getElementById('welcomeCloseBtn');
            const maybeLaterBtn = document.getElementById('welcomeMaybeLaterBtn');
            const completeBtn = document.getElementById('welcomeCompleteProfileBtn');

            if (modal) {
                // Auto-open with confetti
                setTimeout(() => {
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                    fireWelcomeConfetti();
                }, 200);

                function dismissModal() {
                    modal.classList.remove('open');
                    document.body.style.overflow = '';
                    try {
                        sessionStorage.setItem('pkh_welcome_seen_' + userId, '1');
                    } catch(e) {}
                }

                closeBtn?.addEventListener('click', dismissModal);
                maybeLaterBtn?.addEventListener('click', dismissModal);

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) dismissModal();
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('open')) {
                        dismissModal();
                    }
                });
            }
        })();
    </script>
    <script src="js/portal-motion.js"></script>"""

if target_scripts in code:
    code = code.replace(target_scripts, replacement_scripts, 1)
    print("Bottom scripts replaced with confetti and portal-motion.js.")
else:
    print("Target scripts not found!")

with open('dashboard.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Dashboard restoration completed successfully.")

/**
 * js/portal-motion.js
 * One Palayan-Kalinga Hub - Unified Motion & Onboarding Engine
 * Handles:
 * 1. Seamless Page Transitions (Enter & Exit)
 * 2. Active Navigation Indicator Gliding (Header & Sidebar)
 * 3. Welcome Animation Modal After Login
 * 4. Interactive Spotlight Profile Tour & Tooltips with Animated Arrows
 * 5. Dynamic Real-time Profile Completion Progress & 100% Celebration
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. SEAMLESS PAGE TRANSITIONS & TOP LOADER (REQ 4, 5, 8)
    // =========================================================================
    const PageTransitions = {
        topLoader: null,
        mainContent: null,
        isTransitioning: false,

        init() {
            // Create top loader line if not already in DOM
            this.topLoader = document.getElementById('portalTopLoader');
            if (!this.topLoader) {
                this.topLoader = document.createElement('div');
                this.topLoader.id = 'portalTopLoader';
                this.topLoader.className = 'portal-top-loader';
                document.body.appendChild(this.topLoader);
            }

            this.mainContent = document.querySelector('.main-content, .dashboard-main, .portal-page-content');

            // Intercept internal portal navigation clicks
            document.addEventListener('click', (e) => this.handleLinkClick(e));

            // Support bfcache (Browser Back/Forward button)
            window.addEventListener('pageshow', (e) => {
                if (e.persisted || this.isTransitioning) {
                    this.resetTransitionState();
                }
            });
        },

        resetTransitionState() {
            this.isTransitioning = false;
            if (this.mainContent) {
                this.mainContent.classList.remove('portal-page-exiting');
                this.mainContent.style.opacity = '';
                this.mainContent.style.transform = '';
            }
            if (this.topLoader) {
                this.topLoader.className = 'portal-top-loader';
            }
        },

        handleLinkClick(e) {
            const anchor = e.target.closest('a');
            if (!anchor) return;

            const href = anchor.getAttribute('href');
            if (!href) return;

            // Disqualify non-navigational links
            if (
                href.startsWith('#') ||
                href.startsWith('javascript:') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:') ||
                anchor.target === '_blank' ||
                anchor.hasAttribute('download') ||
                e.ctrlKey || e.metaKey || e.shiftKey || e.altKey ||
                e.button !== 0 // Left click only
            ) {
                return;
            }

            // Exclude flyout toggle buttons and specific modals
            if (
                anchor.classList.contains('portal-flyout-trigger') ||
                anchor.hasAttribute('data-flyout-toggle') ||
                anchor.hasAttribute('data-no-transition') ||
                anchor.classList.contains('portal-logout-btn') ||
                href.includes('logout.php')
            ) {
                return;
            }

            // Resolve full URL to verify same-origin
            let targetUrl;
            try {
                targetUrl = new URL(href, window.location.href);
            } catch (err) {
                return;
            }

            if (targetUrl.origin !== window.location.origin) return;

            // If same path and search query, don't retransition
            if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
                return;
            }

            // Valid internal page navigation
            e.preventDefault();
            this.executeTransition(targetUrl.href, anchor);
        },

        executeTransition(destinationUrl, clickedAnchor) {
            if (this.isTransitioning) return;
            this.isTransitioning = true;

            // Start Top Progress Loader
            if (this.topLoader) {
                this.topLoader.className = 'portal-top-loader active';
            }

            // Update Navigation Indicator immediately for instant visual feedback
            if (clickedAnchor) {
                NavIndicators.glideToElement(clickedAnchor);
            }

            // Apply exit animation to page container (220ms subtle fade & slide)
            if (this.mainContent) {
                this.mainContent.classList.add('portal-page-exiting');
            }

            setTimeout(() => {
                window.location.href = destinationUrl;
            }, 210);
        }
    };

    // =========================================================================
    // 2. ACTIVE NAVIGATION INDICATOR GLIDING (REQ 5, 6)
    // =========================================================================
    const NavIndicators = {
        headerIndicator: null,
        headerNav: null,
        sidebarIndicator: null,
        sidebarNav: null,

        init() {
            this.initHeaderNav();
            this.initSidebarNav();
        },

        initHeaderNav() {
            this.headerNav = document.querySelector('.portal-header-center-nav');
            if (!this.headerNav) return;

            // Create header sliding pill if needed
            this.headerIndicator = this.headerNav.querySelector('.portal-header-sliding-pill');
            if (!this.headerIndicator) {
                this.headerIndicator = document.createElement('div');
                this.headerIndicator.className = 'portal-header-sliding-pill';
                this.headerNav.prepend(this.headerIndicator);
            }

            const activePill = this.headerNav.querySelector('.portal-hdr-pill.active');
            if (activePill) {
                this.positionHeaderIndicator(activePill, false);
            }

            // Update on resize
            window.addEventListener('resize', () => {
                const current = this.headerNav.querySelector('.portal-hdr-pill.active');
                if (current) this.positionHeaderIndicator(current, false);
            });
        },

        positionHeaderIndicator(pill, animate = true) {
            if (!this.headerIndicator || !pill) return;
            if (!animate) {
                this.headerIndicator.style.transition = 'none';
            } else {
                this.headerIndicator.style.transition = '';
            }

            const left = pill.offsetLeft;
            const width = pill.offsetWidth;
            this.headerIndicator.style.transform = `translateX(${left}px)`;
            this.headerIndicator.style.width = `${width}px`;
            this.headerIndicator.style.opacity = '1';

            if (!animate) {
                // Force reflow and re-enable transition
                this.headerIndicator.offsetHeight;
                this.headerIndicator.style.transition = '';
            }
        },

        initSidebarNav() {
            this.sidebarNav = document.querySelector('.portal-sidebar-nav');
            if (!this.sidebarNav) return;

            // Create sidebar sliding indicator
            this.sidebarIndicator = this.sidebarNav.querySelector('.portal-sidebar-sliding-indicator');
            if (!this.sidebarIndicator) {
                this.sidebarIndicator = document.createElement('div');
                this.sidebarIndicator.className = 'portal-sidebar-sliding-indicator';
                this.sidebarNav.prepend(this.sidebarIndicator);
            }

            const activeBtn = this.sidebarNav.querySelector('.portal-nav-btn.active');
            if (activeBtn) {
                this.positionSidebarIndicator(activeBtn, false);
            }

            window.addEventListener('resize', () => {
                const current = this.sidebarNav.querySelector('.portal-nav-btn.active');
                if (current) this.positionSidebarIndicator(current, false);
            });
        },

        positionSidebarIndicator(btn, animate = true) {
            if (!this.sidebarIndicator || !btn) return;
            const navWrap = btn.closest('.portal-nav-item');
            if (!navWrap) return;

            if (!animate) {
                this.sidebarIndicator.style.transition = 'none';
            } else {
                this.sidebarIndicator.style.transition = '';
            }

            const top = navWrap.offsetTop;
            const height = btn.offsetHeight;
            this.sidebarIndicator.style.transform = `translateY(${top}px)`;
            this.sidebarIndicator.style.height = `${height}px`;
            this.sidebarIndicator.style.opacity = '1';

            if (!animate) {
                this.sidebarIndicator.offsetHeight;
                this.sidebarIndicator.style.transition = '';
            }
        },

        glideToElement(clickedElement) {
            if (this.headerNav && this.headerNav.contains(clickedElement)) {
                const pill = clickedElement.closest('.portal-hdr-pill');
                if (pill) {
                    this.headerNav.querySelectorAll('.portal-hdr-pill').forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    this.positionHeaderIndicator(pill, true);
                }
            } else if (this.sidebarNav && this.sidebarNav.contains(clickedElement)) {
                const btn = clickedElement.closest('.portal-nav-btn');
                if (btn) {
                    this.sidebarNav.querySelectorAll('.portal-nav-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    this.positionSidebarIndicator(btn, true);
                }
            }
        }
    };

    // =========================================================================
    // 3. WELCOME ANIMATION MODAL AFTER LOGIN (REQ 1)
    // =========================================================================
    const WelcomeModal = {
        backdrop: null,
        completeBtn: null,
        laterBtn: null,
        closeBtn: null,

        init() {
            this.backdrop = document.getElementById('welcomeModalBackdrop');
            if (!this.backdrop) return;

            this.completeBtn = document.getElementById('welcomeCompleteProfileBtn');
            this.laterBtn = document.getElementById('welcomeMaybeLaterBtn');
            this.closeBtn = document.getElementById('welcomeCloseBtn');

            const uid = window.__pkh_user_id || '';
            const userKey = uid ? 'pkh_welcome_dismissed_user_' + uid : 'pkh_welcome_dismissed';
            const isDismissed = localStorage.getItem(userKey) === '1' || localStorage.getItem('pkh_welcome_seen') === '1' || sessionStorage.getItem('palayan_welcome_seen') === '1';

            // ONLY auto-open if data-auto-open is explicitly set to true AND user has not dismissed it yet
            if (this.backdrop.dataset.autoOpen === 'true' && !isDismissed) {
                setTimeout(() => {
                    this.open();
                }, 220);
            }

            if (this.laterBtn) {
                this.laterBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.dismiss();
                });
            }

            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.dismiss();
                });
            }

            if (this.completeBtn) {
                this.completeBtn.addEventListener('click', () => {
                    this.recordDismissal();
                });
            }

            // Dismiss when clicking outer backdrop
            this.backdrop.addEventListener('click', (e) => {
                if (e.target === this.backdrop) {
                    this.dismiss();
                }
            });

            // Escape key listener
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.backdrop.classList.contains('open')) {
                    this.dismiss();
                }
            });

            // Expose global open helper for banner button
            window.openWelcomeModal = () => this.open();
            window.dismissWelcomeModal = () => this.dismiss();
        },

        recordDismissal() {
            const uid = window.__pkh_user_id || '';
            if (uid) {
                localStorage.setItem('pkh_welcome_dismissed_user_' + uid, '1');
                localStorage.setItem('pkh_verified_celebration_seen_user_' + uid, '1');
            }
            localStorage.setItem('pkh_welcome_seen', '1');
            sessionStorage.setItem('palayan_welcome_seen', '1');
        },

        open() {
            if (!this.backdrop) return;
            this.backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
            if (typeof fireDirectConfetti === 'function') {
                try { fireDirectConfetti(); } catch(e) {}
            }
        },

        dismiss() {
            if (!this.backdrop) return;
            this.backdrop.classList.remove('open');
            document.body.style.overflow = '';
            this.recordDismissal();
        },

        transitionToOnboarding() {
            if (!this.backdrop) return;
            this.backdrop.classList.remove('open');
            document.body.style.overflow = '';
            this.recordDismissal();

            setTimeout(() => {
                const targetUrl = (this.completeBtn && this.completeBtn.getAttribute('href')) ? this.completeBtn.getAttribute('href') : 'update-profile-info.php?guided=1';
                PageTransitions.executeTransition(targetUrl, null);
            }, 120);
        }
    };

    // =========================================================================
    // 4. INTERACTIVE PROFILE ONBOARDING SPOTLIGHT GUIDE (REQ 2)
    // =========================================================================
    const SpotlightTour = {
        backdrop: null,
        cutoutBox: null,
        pointerArrow: null,
        tooltipCard: null,
        pulseRing: null,
        currentStep: 0,
        steps: [],

        init() {
            // Define Tour Steps with Contextual Copy
            this.steps = [
                {
                    target: '#onboardingAvatarGroup, #avatarClickTarget, .profile-image',
                    title: 'Profile Picture 📸',
                    badge: 'Step 1 of 4 • Recognition',
                    text: 'Start here! Add a profile picture so people and CSWDO officers can easily recognize you on official passes.',
                    arrowDir: 'left',
                    actionPrompt: 'Upload Photo'
                },
                {
                    target: '#onboardingOverviewSection1, #onboardingNameGroup, input[name="first_name"]',
                    title: 'Personal Information 👤',
                    badge: 'Step 2 of 4 • Legal Identity',
                    text: 'Verify your full legal name as it appears on your government identification to ensure official record matching.',
                    arrowDir: 'top',
                    actionPrompt: 'Check Name'
                },
                {
                    target: '#onboardingOverviewSection2, #onboardingContactGroup, input[name="contact_number"]',
                    title: 'Contact Information 📱',
                    badge: 'Step 3 of 4 • Communication',
                    text: 'Keep your mobile number and contact details up to date to receive urgent SMS notifications and appointment alerts.',
                    arrowDir: 'top',
                    actionPrompt: 'Update Phone'
                },
                {
                    target: '#onboardingOverviewSection3, #onboardingAddressGroup, #addressField, textarea[name="address"]',
                    title: 'Palayan City Address 📍',
                    badge: 'Step 4 of 4 • Residency',
                    text: 'Provide your active street and barangay to verify your residence within Palayan City jurisdiction.',
                    arrowDir: 'top',
                    actionPrompt: 'Save Profile'
                }
            ];

            // Trigger buttons in DOM
            const startBtn = document.getElementById('startTourBtn');
            if (startBtn) {
                startBtn.addEventListener('click', () => this.start(0));
            }

            // Auto-trigger if onboarding URL query parameter is set
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('onboarding') === '1' || urlParams.get('guided') === '1') {
                setTimeout(() => {
                    this.start(0);
                }, 400);
            }
        },

        createTourElements() {
            if (this.backdrop) return;

            // 1. Backdrop
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'spotlight-backdrop';
            document.body.appendChild(this.backdrop);

            // 2. Cutout Box
            this.cutoutBox = document.createElement('div');
            this.cutoutBox.className = 'spotlight-cutout-box';
            this.pulseRing = document.createElement('div');
            this.pulseRing.className = 'spotlight-pulse-ring';
            this.cutoutBox.appendChild(this.pulseRing);
            document.body.appendChild(this.cutoutBox);

            // 3. Pointer Arrow
            this.pointerArrow = document.createElement('div');
            this.pointerArrow.className = 'spotlight-pointer-arrow';
            this.pointerArrow.innerHTML = '<i class="fas fa-hand-point-down"></i>';
            document.body.appendChild(this.pointerArrow);

            // 4. Floating Tooltip Card
            this.tooltipCard = document.createElement('div');
            this.tooltipCard.className = 'spotlight-tooltip-card';
            this.tooltipCard.innerHTML = `
                <div class="tooltip-step-badge" id="tourStepBadge">Step 1 of 4</div>
                <h4 id="tourStepTitle">Profile Picture</h4>
                <p id="tourStepText">Start here! Add a profile picture so people can easily recognize you.</p>
                <div class="tooltip-controls-row">
                    <div class="tooltip-step-dots" id="tourStepDots"></div>
                    <div class="tooltip-buttons">
                        <button type="button" class="btn-tooltip-skip" id="tourSkipBtn">Skip Tour</button>
                        <button type="button" class="btn-tooltip-prev" id="tourPrevBtn">Back</button>
                        <button type="button" class="btn-tooltip-next" id="tourNextBtn">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            `;
            document.body.appendChild(this.tooltipCard);

            // Bind Controls
            this.tooltipCard.querySelector('#tourSkipBtn').addEventListener('click', () => this.end());
            this.tooltipCard.querySelector('#tourPrevBtn').addEventListener('click', () => this.prev());
            this.tooltipCard.querySelector('#tourNextBtn').addEventListener('click', () => this.next());
            this.backdrop.addEventListener('click', () => this.end());

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (!this.backdrop.classList.contains('active')) return;
                if (e.key === 'Escape') this.end();
                if (e.key === 'ArrowRight') this.next();
                if (e.key === 'ArrowLeft') this.prev();
            });

            // Handle window resize while open
            window.addEventListener('resize', () => {
                if (this.backdrop.classList.contains('active')) {
                    this.renderStep(this.currentStep);
                }
            });
        },

        start(stepIndex = 0) {
            this.createTourElements();
            this.currentStep = stepIndex;
            this.backdrop.classList.add('active');
            this.cutoutBox.classList.add('active');
            this.pointerArrow.classList.add('active');
            this.tooltipCard.classList.add('active');
            this.renderStep(this.currentStep);
        },

        renderStep(index) {
            if (index < 0 || index >= this.steps.length) return;
            const step = this.steps[index];

            // Resolve target in DOM: ONLY select elements that are actually visible (non-zero width & height)
            let targetEl = null;
            const selectors = step.target.split(',');
            for (let sel of selectors) {
                const candidates = document.querySelectorAll(sel.trim());
                for (let cand of candidates) {
                    const r = cand.getBoundingClientRect();
                    const style = window.getComputedStyle(cand);
                    if (r.width > 0 && r.height > 0 && style.display !== 'none' && style.visibility !== 'hidden' && cand.offsetParent !== null) {
                        targetEl = cand;
                        break;
                    }
                }
                if (targetEl) break;
            }

            // If target is inside a hidden tab or modal, fallback to current visible container
            if (!targetEl) {
                const overviewView = document.getElementById('profileOverviewView');
                const editView = document.getElementById('profileEditView');
                if (overviewView && overviewView.offsetParent !== null) {
                    const sections = overviewView.querySelectorAll('.overview-section-box');
                    if (index === 1 && sections[0]) targetEl = sections[0];
                    else if (index === 2 && sections[1]) targetEl = sections[1];
                    else if (index === 3 && sections[2]) targetEl = sections[2];
                    else targetEl = overviewView;
                } else if (editView && editView.offsetParent !== null) {
                    targetEl = editView;
                } else {
                    targetEl = document.getElementById('onboardingAvatarGroup') || document.querySelector('.profile-card');
                }
            }

            if (!targetEl) {
                if (index < this.steps.length - 1) {
                    this.renderStep(index + 1);
                } else {
                    this.end();
                }
                return;
            }

            // Scroll target comfortably into center view
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Delay to let smooth scroll settle before measuring viewport coordinates
            setTimeout(() => {
                const rect = targetEl.getBoundingClientRect();
                const padding = 10;

                // Position Cutout Box around target with padding
                const top = Math.max(12, rect.top - padding);
                const left = Math.max(12, rect.left - padding);
                const width = Math.max(60, rect.width + padding * 2);
                const height = Math.max(40, rect.height + padding * 2);
                const borderRadius = targetEl.classList.contains('profile-image') ? '50%' : '18px';

                this.cutoutBox.style.top = `${top}px`;
                this.cutoutBox.style.left = `${left}px`;
                this.cutoutBox.style.width = `${width}px`;
                this.cutoutBox.style.height = `${height}px`;
                this.cutoutBox.style.borderRadius = borderRadius;

                // Update Tooltip Content
                this.tooltipCard.querySelector('#tourStepBadge').textContent = step.badge;
                this.tooltipCard.querySelector('#tourStepTitle').textContent = step.title;
                this.tooltipCard.querySelector('#tourStepText').textContent = step.text;

                // Update Dots
                const dotsContainer = this.tooltipCard.querySelector('#tourStepDots');
                dotsContainer.innerHTML = '';
                this.steps.forEach((_, i) => {
                    const dot = document.createElement('div');
                    dot.className = `tooltip-dot ${i === index ? 'active' : ''}`;
                    dotsContainer.appendChild(dot);
                });

                // Update Prev Button
                const prevBtn = this.tooltipCard.querySelector('#tourPrevBtn');
                prevBtn.style.display = index === 0 ? 'none' : 'inline-flex';

                // Update Next Button
                const nextBtn = this.tooltipCard.querySelector('#tourNextBtn');
                if (index === this.steps.length - 1) {
                    nextBtn.innerHTML = 'Complete Guide <i class="fas fa-check"></i>';
                } else {
                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
                }

                // Smart Tooltip & Arrow Positioning (avoid overflowing screen)
                const tooltipWidth = this.tooltipCard.offsetWidth || 340;
                const tooltipHeight = this.tooltipCard.offsetHeight || 190;
                const viewW = window.innerWidth;
                const viewH = window.innerHeight;

                let tooltipTop, tooltipLeft, arrowTop, arrowLeft, arrowIcon;

                // Prefer placing tooltip below the element if space allows
                if (top + height + tooltipHeight + 24 < viewH) {
                    tooltipTop = top + height + 24;
                    tooltipLeft = Math.min(Math.max(16, left), viewW - tooltipWidth - 16);
                    arrowTop = top + height + 2;
                    arrowLeft = left + width / 2 - 12;
                    arrowIcon = '<i class="fas fa-hand-point-up"></i>';
                } else if (top - tooltipHeight - 24 > 0) {
                    // Above the element
                    tooltipTop = top - tooltipHeight - 24;
                    tooltipLeft = Math.min(Math.max(16, left), viewW - tooltipWidth - 16);
                    arrowTop = top - 28;
                    arrowLeft = left + width / 2 - 12;
                    arrowIcon = '<i class="fas fa-hand-point-down"></i>';
                } else if (left + width + tooltipWidth + 24 < viewW) {
                    // To the right
                    tooltipTop = Math.max(16, top);
                    tooltipLeft = left + width + 24;
                    arrowTop = top + height / 2 - 14;
                    arrowLeft = left + width + 4;
                    arrowIcon = '<i class="fas fa-hand-point-left"></i>';
                } else {
                    // To the left or centered fallback
                    tooltipTop = Math.max(16, top);
                    tooltipLeft = Math.max(16, left - tooltipWidth - 24);
                    arrowTop = top + height / 2 - 14;
                    arrowLeft = left - 28;
                    arrowIcon = '<i class="fas fa-hand-point-right"></i>';
                }

                this.tooltipCard.style.top = `${tooltipTop}px`;
                this.tooltipCard.style.left = `${tooltipLeft}px`;

                this.pointerArrow.innerHTML = arrowIcon;
                this.pointerArrow.style.top = `${arrowTop}px`;
                this.pointerArrow.style.left = `${arrowLeft}px`;
            }, 260);
        },

        next() {
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                this.renderStep(this.currentStep);
            } else {
                this.end();
            }
        },

        prev() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.renderStep(this.currentStep);
            }
        },

        end() {
            if (!this.backdrop) return;
            this.backdrop.classList.remove('active');
            this.cutoutBox.classList.remove('active');
            this.pointerArrow.classList.remove('active');
            this.tooltipCard.classList.remove('active');

            // Highlight the Save button gently to guide user to conclude
            const saveBtn = document.getElementById('saveProfileBtn') || document.querySelector('button[name="update_profile"]');
            if (saveBtn) {
                saveBtn.classList.add('guided-target-glow');
                setTimeout(() => saveBtn.classList.remove('guided-target-glow'), 3500);
            }
        }
    };

    // =========================================================================
    // 5. SMART PROFILE COMPLETION PROGRESS & 100% CELEBRATION (REQ 3)
    // =========================================================================
    const ProfileProgress = {
        card: null,
        circle: null,
        percentText: null,
        fillBar: null,
        titleEl: null,
        subtitleEl: null,
        nextBtn: null,
        celebrationBanner: null,
        circumference: 175.93, // 2 * PI * 28

        init() {
            this.card = document.getElementById('smartProgressCard');
            if (!this.card) return;

            this.circle = document.getElementById('progressRingCircle');
            this.percentText = document.getElementById('progressPercentText');
            this.fillBar = document.getElementById('progressFillBar');
            this.titleEl = document.getElementById('progressCardTitle');
            this.subtitleEl = document.getElementById('progressCardSubtitle');
            this.nextBtn = document.getElementById('nextIncompleteBtn');
            this.celebrationBanner = document.getElementById('profile100Banner');

            // Listen to real-time input modifications on profile form
            const form = document.getElementById('profileForm');
            if (form) {
                form.addEventListener('input', () => this.evaluateProgress(false));
                form.addEventListener('change', () => this.evaluateProgress(false));
            }

            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', () => this.focusNextIncomplete());
            }

            // Clickable milestone pills in card
            const milestonePills = this.card.querySelectorAll('.milestone-pill');
            milestonePills.forEach(pill => {
                pill.addEventListener('click', () => {
                    const targetSel = pill.getAttribute('data-target');
                    if (targetSel) {
                        const target = document.querySelector(targetSel);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            target.focus();
                            target.classList.add('guided-target-glow');
                            setTimeout(() => target.classList.remove('guided-target-glow'), 2500);
                        }
                    }
                });
            });

            // Initial calculation
            this.evaluateProgress(true);
        },

        evaluateProgress(isInitial = false) {
            // Milestone 1: Verified (assumed verified for this dashboard/profile access)
            const isVerified = true;

            // Milestone 2: Profile Picture Uploaded
            const avatarImg = document.getElementById('avatarImg');
            const hasAvatar = !!(avatarImg && avatarImg.src && !avatarImg.src.includes('data:') && !avatarImg.classList.contains('default-avatar'));

            // Milestone 3: Full Name Provided
            const firstNameInput = document.querySelector('input[name="first_name"]');
            const lastNameInput = document.querySelector('input[name="last_name"]');
            const hasName = !!(firstNameInput && firstNameInput.value.trim().length >= 2 &&
                               lastNameInput && lastNameInput.value.trim().length >= 2);

            // Milestone 4: Contact Number Provided
            const contactInput = document.querySelector('input[name="contact_number"]');
            const hasContact = !!(contactInput && contactInput.value.trim().length >= 7);

            // Milestone 5: Address Completed
            const addressInput = document.querySelector('textarea[name="address"]') || document.getElementById('addressField');
            const hasAddress = !!(addressInput && addressInput.value.trim().length >= 5);

            // Compute percentage (5 equal milestones = 20% each)
            let score = 0;
            if (isVerified) score += 20;
            if (hasAvatar) score += 20;
            if (hasName) score += 20;
            if (hasContact) score += 20;
            if (hasAddress) score += 20;

            // Update UI
            this.renderProgress(score, { isVerified, hasAvatar, hasName, hasContact, hasAddress }, isInitial);
        },

        renderProgress(percent, milestones, isInitial) {
            // 1. Text & Bar
            if (this.percentText) {
                this.percentText.textContent = `${percent}%`;
            }
            if (this.fillBar) {
                this.fillBar.style.width = `${percent}%`;
            }

            // 2. Circular SVG Ring
            if (this.circle) {
                const offset = this.circumference - (percent / 100) * this.circumference;
                this.circle.style.strokeDashoffset = offset;
            }

            // 3. Update Milestone Pills
            this.updateMilestonePill('#pillPhoto', milestones.hasAvatar, 'Photo');
            this.updateMilestonePill('#pillName', milestones.hasName, 'Full Name');
            this.updateMilestonePill('#pillContact', milestones.hasContact, 'Contact');
            this.updateMilestonePill('#pillAddress', milestones.hasAddress, 'Address');

            // 4. Handle 100% Complete State
            if (percent >= 100) {
                this.card.classList.add('is-complete');
                if (this.titleEl) this.titleEl.innerHTML = 'Profile Complete! 🎉';
                if (this.subtitleEl) this.subtitleEl.textContent = 'Outstanding! All required citizen details are recorded. Your account is 100% active and verified.';
                if (this.nextBtn) this.nextBtn.style.display = 'none';
                if (this.celebrationBanner) this.celebrationBanner.style.display = 'flex';

                // Launch subtle confetti burst if newly reached 100%
                if (!isInitial && !this.card.hasAttribute('data-celebrated')) {
                    this.card.setAttribute('data-celebrated', 'true');
                    this.fireCelebrationParticles();
                }
            } else {
                this.card.classList.remove('is-complete');
                if (this.titleEl) this.titleEl.textContent = 'Complete Your Profile';
                if (this.subtitleEl) {
                    const missing = [];
                    if (!milestones.hasAvatar) missing.push('profile photo');
                    if (!milestones.hasName) missing.push('full name');
                    if (!milestones.hasContact) missing.push('contact number');
                    if (!milestones.hasAddress) missing.push('residential address');
                    this.subtitleEl.textContent = `Your profile is ${percent}% complete. Add your ${missing[0] || 'details'} to reach 100%.`;
                }
                if (this.nextBtn) this.nextBtn.style.display = 'inline-flex';
                if (this.celebrationBanner) this.celebrationBanner.style.display = 'none';
            }
        },

        updateMilestonePill(selector, isDone, label) {
            const pill = this.card.querySelector(selector);
            if (!pill) return;
            if (isDone) {
                pill.className = 'milestone-pill done';
                pill.innerHTML = `<i class="fas fa-check-circle"></i> ${label}`;
            } else {
                pill.className = 'milestone-pill missing';
                pill.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${label}`;
            }
        },

        focusNextIncomplete() {
            // Find first missing field
            const avatarImg = document.getElementById('avatarImg');
            if (!avatarImg || avatarImg.classList.contains('default-avatar')) {
                const photoTarget = document.getElementById('avatarClickTarget') || document.getElementById('changePhotoBtn');
                if (photoTarget) {
                    photoTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    photoTarget.classList.add('guided-target-glow');
                    setTimeout(() => photoTarget.classList.remove('guided-target-glow'), 2800);
                    return;
                }
            }

            const firstName = document.querySelector('input[name="first_name"]');
            if (firstName && !firstName.value.trim()) {
                firstName.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstName.focus();
                firstName.classList.add('guided-target-glow');
                setTimeout(() => firstName.classList.remove('guided-target-glow'), 2800);
                return;
            }

            const contact = document.querySelector('input[name="contact_number"]');
            if (contact && !contact.value.trim()) {
                contact.scrollIntoView({ behavior: 'smooth', block: 'center' });
                contact.focus();
                contact.classList.add('guided-target-glow');
                setTimeout(() => contact.classList.remove('guided-target-glow'), 2800);
                return;
            }

            const address = document.querySelector('textarea[name="address"]') || document.getElementById('addressField');
            if (address && !address.value.trim()) {
                address.scrollIntoView({ behavior: 'smooth', block: 'center' });
                address.focus();
                address.classList.add('guided-target-glow');
                setTimeout(() => address.classList.remove('guided-target-glow'), 2800);
                return;
            }
        },

        fireCelebrationParticles() {
            let canvas = document.getElementById('portalCelebrationCanvas');
            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.id = 'portalCelebrationCanvas';
                canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:99999;';
                document.body.appendChild(canvas);
            }

            const ctx = canvas.getContext('2d');
            let width = (canvas.width = window.innerWidth);
            let height = (canvas.height = window.innerHeight);

            const colors = ['#10B981', '#7F0303', '#D8BA98', '#F59E0B', '#3B82F6'];
            const particles = [];

            for (let i = 0; i < 70; i++) {
                particles.push({
                    x: width / 2,
                    y: height / 2 - 80,
                    w: Math.random() * 8 + 4,
                    h: Math.random() * 5 + 3,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    vx: (Math.random() - 0.5) * 14,
                    vy: Math.random() * -12 - 3,
                    gravity: 0.38,
                    rot: Math.random() * 360,
                    rotSpeed: (Math.random() - 0.5) * 12,
                    opacity: 1
                });
            }

            const startTime = Date.now();
            function render() {
                ctx.clearRect(0, 0, width, height);
                let alive = false;

                for (let p of particles) {
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += p.gravity;
                    p.rot += p.rotSpeed;

                    if (Date.now() - startTime > 1200) {
                        p.opacity -= 0.024;
                    }

                    if (p.opacity > 0 && p.y < height) {
                        alive = true;
                        ctx.save();
                        ctx.translate(p.x, p.y);
                        ctx.rotate((p.rot * Math.PI) / 180);
                        ctx.fillStyle = p.color;
                        ctx.globalAlpha = Math.max(0, p.opacity);
                        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                        ctx.restore();
                    }
                }

                if (alive) {
                    requestAnimationFrame(render);
                } else {
                    ctx.clearRect(0, 0, width, height);
                }
            }
            render();
        }
    };

    // =========================================================================
    // INITIALIZATION ON DOM READY
    // =========================================================================
    function initPortalMotionSystem() {
        PageTransitions.init();
        NavIndicators.init();
        WelcomeModal.init();
        SpotlightTour.init();
        ProfileProgress.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalMotionSystem);
    } else {
        initPortalMotionSystem();
    }

    // Expose helpers on window for external triggers
    window.PalayanMotion = {
        PageTransitions,
        NavIndicators,
        WelcomeModal,
        SpotlightTour,
        ProfileProgress
    };
})();

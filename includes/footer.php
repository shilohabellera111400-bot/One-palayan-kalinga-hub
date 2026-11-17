<?php
if (!function_exists('cms_get')) {
    require_once __DIR__ . '/cms-helper.php';
}
?>
<footer class="footer" id="mainGlobalFooter">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section footer-brand-section">
                <div class="footer-brand-header">
                    <img src="<?php echo cms_image('site_logo', 'images/palayan-logo.png'); ?>" alt="<?php echo htmlspecialchars(cms_get('site_title', 'Palayan City')); ?> Logo" class="footer-brand-logo" onerror="this.src='images/logo.png'">
                    <div>
                        <h3 class="footer-heading-brand"><?php echo htmlspecialchars(cms_get('site_title', 'ONE PALAYAN-KALINGA HUB')); ?></h3>
                        <p class="footer-sub-brand"><?php echo htmlspecialchars(cms_get('site_tagline', 'City Social Welfare and Development Office (CSWDO)')); ?></p>
                    </div>
                </div>
                <p class="footer-tagline"><?php echo htmlspecialchars(cms_get('footer_tagline', 'Streamlined Social Services, Compassionate Care, and Transparent Citizen Support for Every Palayan Resident.')); ?></p>
                <div class="footer-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars(cms_get('contact_address', 'City Government Complex, In front of Palayan City National High School, Palayan City, Nueva Ecija')); ?></span>
                </div>
            </div>

            <div class="footer-section">
                <h4 class="footer-heading">Services &amp; Programs</h4>
                <ul class="footer-links-list">
                    <li><a href="services.php"><i class="fas fa-chevron-right"></i> All City Services</a></li>
                    <li><a href="appointments.php"><i class="fas fa-chevron-right"></i> Appointment Booking</a></li>
                    <li><a href="aics.php"><i class="fas fa-chevron-right"></i> AICS Financial Aid</a></li>
                    <li><a href="housing.php"><i class="fas fa-chevron-right"></i> Socialized Housing</a></li>
                    <li><a href="senior.php"><i class="fas fa-chevron-right"></i> Senior Citizen Affairs (OSCA)</a></li>
                    <li><a href="pwd.php"><i class="fas fa-chevron-right"></i> Persons with Disability (PDAO)</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4 class="footer-heading">Citizen Resources</h4>
                <ul class="footer-links-list">
                    <li><a href="services.php#charter"><i class="fas fa-chevron-right"></i> Citizen's Charter</a></li>
                    <li><a href="announcements.php"><i class="fas fa-chevron-right"></i> City Announcements</a></li>
                    <li><a href="user-guide.php"><i class="fas fa-chevron-right"></i> Resident User Guide</a></li>
                    <li><a href="faqs.php"><i class="fas fa-chevron-right"></i> Frequently Asked Questions</a></li>
                    <li><a href="privacy-policy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="terms.php"><i class="fas fa-chevron-right"></i> Terms of Service</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4 class="footer-heading">Connect With CSWDO</h4>
                <p class="footer-desc">Reach out for inquiries, urgent welfare assistance, or schedule verification.</p>
                <div class="footer-contact-item">
                    <i class="fas fa-phone"></i>
                    <span><?php echo htmlspecialchars(cms_get('contact_telephone', '(044) 958 2210')); ?> &bull; <?php echo htmlspecialchars(cms_get('contact_mobile', '+63 (970) 300 8195')); ?></span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars(cms_get('contact_email', 'cswdo@palayancity.gov.ph')); ?></span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-clock"></i>
                    <span><?php echo htmlspecialchars(cms_get('office_hours', 'Mon - Fri: 8:00 AM - 5:00 PM')); ?></span>
                </div>
                <div class="footer-social-links">
                    <a href="<?php echo htmlspecialchars(cms_get('facebook_url', 'https://www.facebook.com/PalayanCSWDO')); ?>" target="_blank" rel="noopener noreferrer" title="CSWDO Palayan Facebook Page" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://m.me/PalayanCSWDO" target="_blank" rel="noopener noreferrer" title="CSWDO Palayan Messenger" aria-label="Messenger">
                        <i class="fab fa-facebook-messenger"></i>
                    </a>
                    <a href="mailto:cswdo@palayancity.gov.ph" title="Send Email" aria-label="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-info">
                <p>&copy; <?php echo date('Y'); ?> <strong>One Palayan-Kalinga Hub</strong>. All rights reserved.</p>
                <p class="footer-sub-info">Official Digital Platform of the City Social Welfare and Development Office, Palayan City.</p>
            </div>
            <div class="footer-stats">
                <span class="footer-stat-pill"><i class="fas fa-calendar-check"></i> <strong id="totalAppointments">0</strong> Appointments</span>
                <span class="footer-stat-pill"><i class="fas fa-hand-holding-heart"></i> <strong id="totalAICS">0</strong> AICS Requests</span>
                <span class="footer-stat-pill"><i class="fas fa-home"></i> <strong id="totalHousing">0</strong> Housing Grants</span>
            </div>
        </div>
    </div>
</footer>

<style>
/* ========== UNIFIED GREEN CSWDO FOOTER ========== */
#mainGlobalFooter.footer,
.footer {
    background: #0F414A !important;
    color: #FFFFFF !important;
    padding: 60px 0 26px !important;
    margin-top: auto !important;
    position: relative !important;
    z-index: 10 !important;
    border-top: 3px solid rgba(201, 168, 108, 0.4) !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

#mainGlobalFooter .container,
.footer .container {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
    box-sizing: border-box;
}

#mainGlobalFooter .footer-grid,
.footer .footer-grid {
    display: grid !important;
    grid-template-columns: 2fr 1.2fr 1.2fr 1.5fr !important;
    gap: 40px !important;
    margin-bottom: 40px !important;
}

.footer-brand-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}

.footer-brand-logo {
    width: 48px;
    height: 48px;
    object-fit: contain;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.25));
}

.footer-heading-brand {
    color: #E8D5A8 !important;
    font-size: 1.15rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px !important;
    margin: 0 0 4px !important;
}

.footer-sub-brand {
    color: #96C0CE !important;
    font-size: 0.78rem !important;
    font-weight: 600 !important;
    margin: 0 !important;
    letter-spacing: 0.3px !important;
}

.footer-tagline {
    color: rgba(255, 255, 255, 0.78) !important;
    font-size: 0.86rem !important;
    line-height: 1.6 !important;
    margin: 0 0 16px !important;
}

.footer-heading {
    color: #D8BA98 !important;
    font-size: 0.96rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.6px !important;
    margin: 0 0 18px !important;
    padding-bottom: 8px !important;
    border-bottom: 1px solid rgba(216, 186, 152, 0.25) !important;
}

.footer-links-list {
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
}

.footer-links-list li a {
    color: rgba(255, 255, 255, 0.75) !important;
    text-decoration: none !important;
    font-size: 0.85rem !important;
    transition: all 0.22s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
}

.footer-links-list li a i {
    font-size: 0.65rem !important;
    color: #96C0CE !important;
    transition: transform 0.2s ease !important;
}

.footer-links-list li a:hover {
    color: #E8D5A8 !important;
    transform: translateX(4px) !important;
}

.footer-links-list li a:hover i {
    color: #E8D5A8 !important;
    transform: translateX(2px) !important;
}

.footer-desc {
    color: rgba(255, 255, 255, 0.75) !important;
    font-size: 0.84rem !important;
    line-height: 1.5 !important;
    margin-bottom: 14px !important;
}

.footer-contact-item {
    display: flex !important;
    align-items: flex-start !important;
    gap: 10px !important;
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.82rem !important;
    line-height: 1.5 !important;
    margin-bottom: 10px !important;
}

.footer-contact-item i {
    color: #C9A86C !important;
    font-size: 0.9rem !important;
    margin-top: 3px !important;
    flex-shrink: 0 !important;
    width: 16px !important;
}

.footer-social-links {
    display: flex !important;
    gap: 10px !important;
    margin-top: 18px !important;
}

.footer-social-links a {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px !important;
    height: 36px !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.08) !important;
    color: #E8D5A8 !important;
    font-size: 0.9rem !important;
    text-decoration: none !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    transition: all 0.22s ease !important;
}

.footer-social-links a:hover {
    background: #C9A86C !important;
    color: #0F414A !important;
    border-color: #C9A86C !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

.footer-bottom {
    padding-top: 24px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    gap: 16px !important;
}

.footer-info p {
    color: rgba(255, 255, 255, 0.65) !important;
    font-size: 0.78rem !important;
    margin: 0 !important;
}

.footer-info strong {
    color: #E8D5A8 !important;
}

.footer-sub-info {
    font-size: 0.72rem !important;
    color: rgba(255, 255, 255, 0.48) !important;
    margin-top: 3px !important;
}

.footer-stats {
    display: flex !important;
    gap: 10px !important;
    flex-wrap: wrap !important;
}

.footer-stat-pill {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    padding: 6px 12px !important;
    border-radius: 999px !important;
    font-size: 0.76rem !important;
    color: rgba(255, 255, 255, 0.85) !important;
}

.footer-stat-pill i {
    color: #C9A86C !important;
    font-size: 0.74rem !important;
}

.footer-stat-pill strong {
    color: #FFFFFF !important;
}

@media (max-width: 992px) {
    #mainGlobalFooter .footer-grid,
    .footer .footer-grid {
        grid-template-columns: 1fr 1fr !important;
        gap: 32px !important;
    }
}

@media (max-width: 640px) {
    #mainGlobalFooter .footer-grid,
    .footer .footer-grid {
        grid-template-columns: 1fr !important;
        gap: 28px !important;
    }
    .footer-bottom {
        flex-direction: column !important;
        text-align: center !important;
    }
    .footer-stats {
        justify-content: center !important;
    }
}
</style>

<script>
// Dynamic stats loader
(function() {
    function loadFooterStats() {
        if (typeof fetch !== 'function') return;
        fetch('api/get-stats.php')
            .then(res => res.json())
            .then(data => {
                const appt = document.getElementById('totalAppointments');
                const aics = document.getElementById('totalAICS');
                const hsg = document.getElementById('totalHousing');
                if (appt && data.appointments) appt.textContent = data.appointments;
                if (aics && data.aics) aics.textContent = data.aics;
                if (hsg && data.housing) hsg.textContent = data.housing;
            })
            .catch(() => {});
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadFooterStats);
    } else {
        loadFooterStats();
    }
})();
</script>
<?php require_once __DIR__ . '/verification-ad-reminder.php'; ?>
<?php require_once __DIR__ . '/chatbot-widget.php'; ?>

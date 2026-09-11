<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-section">
                <h3>ONE PALAYAN-KALINGA HUB</h3>
                <p>CSWDO Palayan City</p>
                <p class="footer-tagline">Streamlined Social Services For Every Palayan Resident</p>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="track-aics.php">Track AICS</a></li>
                    <li><a href="housing.php">Housing Programs</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Resources</h4>
                <ul>
                    <li><a href="user-guide.php">User Guide</a></li>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Use</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Contact Us</h4>
                <p><i class="fas fa-map-marker-alt"></i> City Social Welfare and Development Office<br>Palayan City, Nueva Ecija</p>
                <p><i class="fas fa-phone"></i> +63 (XXX) XXX XXXX</p>
                <p><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-viber"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-info">
                <p>&copy; <?php echo date('Y'); ?> One Palayan-Kalinga Hub. All rights reserved.</p>
                <p class="developer">Developed for CSWDO Palayan City</p>
            </div>
            <div class="footer-stats">
                <span><i class="fas fa-calendar-check"></i> <span id="totalAppointments">0</span> Appointments</span>
                <span><i class="fas fa-hand-holding-heart"></i> <span id="totalAICS">0</span> AICS Served</span>
                <span><i class="fas fa-home"></i> <span id="totalHousing">0</span> Housing Beneficiaries</span>
            </div>
        </div>
    </div>
</footer>

<style>
.footer {
    background: #1a1a1a;
    color: white;
    padding: 60px 0 20px;
    margin-top: auto;
}

.footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1.5fr;
    gap: 40px;
    margin-bottom: 40px;
}

.footer-section h3 {
    color: #ff6600;
    margin-bottom: 15px;
    font-size: 1.2rem;
}

.footer-section h4 {
    color: #ff6600;
    margin-bottom: 20px;
    font-size: 1rem;
}

.footer-tagline {
    color: #999;
    font-size: 0.9rem;
    margin-top: 10px;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 10px;
}

.footer-section ul li a {
    color: #999;
    text-decoration: none;
    transition: color 0.3s;
}

.footer-section ul li a:hover {
    color: #ff6600;
}

.footer-section p {
    color: #999;
    margin-bottom: 10px;
    line-height: 1.6;
}

.footer-section p i {
    width: 20px;
    margin-right: 10px;
    color: #ff6600;
}

.social-links {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.social-links a {
    color: #999;
    font-size: 1.2rem;
    transition: color 0.3s;
}

.social-links a:hover {
    color: #ff6600;
}

.footer-bottom {
    padding-top: 20px;
    border-top: 1px solid #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.footer-info p {
    color: #666;
    margin: 0;
}

.developer {
    font-size: 0.8rem;
    margin-top: 5px !important;
}

.footer-stats {
    display: flex;
    gap: 20px;
    color: #999;
    font-size: 0.9rem;
}

.footer-stats span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.footer-stats i {
    color: #ff6600;
}

@media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }

    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }

    .footer-stats {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
// Update footer stats via AJAX
function updateFooterStats() {
    fetch('api/get-stats.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalAppointments').textContent = data.appointments || '0';
            document.getElementById('totalAICS').textContent = data.aics || '0';
            document.getElementById('totalHousing').textContent = data.housing || '0';
        })
        .catch(error => console.error('Error loading stats:', error));
}

// Update stats on page load
document.addEventListener('DOMContentLoaded', updateFooterStats);
</script>
<script src="js/draft-preservation.js"></script>
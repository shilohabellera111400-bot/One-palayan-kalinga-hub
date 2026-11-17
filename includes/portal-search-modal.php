<?php
// includes/portal-search-modal.php - Integrated Dashboard & Portal Search Modal
$user_search_records = [];

if (isset($db) && isset($_SESSION['user_id'])) {
    try {
        // Fetch recent appointments for search index
        $s_app_stmt = $db->prepare("SELECT a.appointment_id, s.service_name, a.appointment_date, a.appointment_time, a.status 
                                    FROM appointments a 
                                    JOIN services s ON a.service_id = s.service_id 
                                    WHERE a.user_id = :uid 
                                    ORDER BY a.appointment_date DESC LIMIT 5");
        $s_app_stmt->bindParam(':uid', $_SESSION['user_id']);
        $s_app_stmt->execute();
        while ($r = $s_app_stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_search_records[] = [
                'type' => 'appointment',
                'title' => 'Appointment: ' . $r['service_name'],
                'sub' => date('M d, Y', strtotime($r['appointment_date'])) . ' • ' . ucfirst($r['status']),
                'url' => 'my-appointments.php',
                'icon' => 'fas fa-calendar-check'
            ];
        }

        // Fetch recent AICS requests for search index
        $s_aics_stmt = $db->prepare("SELECT request_id, request_type, amount_requested, status, submission_date 
                                     FROM aics_requests 
                                     WHERE user_id = :uid 
                                     ORDER BY submission_date DESC LIMIT 5");
        $s_aics_stmt->bindParam(':uid', $_SESSION['user_id']);
        $s_aics_stmt->execute();
        while ($r = $s_aics_stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_search_records[] = [
                'type' => 'aics',
                'title' => 'AICS Request: ' . ucfirst($r['request_type']),
                'sub' => '₱' . number_format((float)$r['amount_requested'], 2) . ' • ' . ucfirst($r['status'] ?? 'Pending'),
                'url' => 'aics-requests.php',
                'icon' => 'fas fa-hand-holding-usd'
            ];
        }

        // Fetch housing application if exists
        $s_h_stmt = $db->prepare("SELECT application_id, preferred_project, status 
                                  FROM housing_applications 
                                  WHERE user_id = :uid 
                                  LIMIT 1");
        $s_h_stmt->bindParam(':uid', $_SESSION['user_id']);
        $s_h_stmt->execute();
        $h_res = $s_h_stmt->fetch(PDO::FETCH_ASSOC);
        if ($h_res) {
            $user_search_records[] = [
                'type' => 'housing',
                'title' => 'Housing Application: ' . ($h_res['preferred_project'] ?: 'San Lorenzo'),
                'sub' => 'Status: ' . ucfirst($h_res['status'] ?? 'Pending'),
                'url' => 'my-housing.php',
                'icon' => 'fas fa-home'
            ];
        }
    } catch (Exception $e) {
        // Fail silently
    }
}
?>
<style>
/* ========== INTEGRATED PORTAL SEARCH MODAL ========== */
.portal-search-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 18, 22, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 80px 20px 40px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.portal-search-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.portal-search-card {
    width: 100%;
    max-width: 620px;
    background: var(--ref-card, #FFFFFF);
    border-radius: 26px;
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    transform: translateY(-16px) scale(0.97);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .portal-search-card {
    background: #152227;
    border-color: rgba(255, 255, 255, 0.1);
}

.portal-search-overlay.active .portal-search-card {
    transform: translateY(0) scale(1);
}

.portal-search-input-wrap {
    display: flex;
    align-items: center;
    padding: 16px 22px;
    border-bottom: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
    gap: 14px;
}

.portal-search-input-wrap i.search-icon {
    font-size: 1.15rem;
    color: #7F0303;
}

[data-theme="dark"] .portal-search-input-wrap i.search-icon {
    color: #F87171;
}

.portal-search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 1rem;
    font-weight: 600;
    color: var(--ref-text, #192126);
    outline: none;
    font-family: inherit;
}

.portal-search-input::placeholder {
    color: var(--ref-muted, #71818B);
    font-weight: 400;
}

.btn-search-close {
    background: rgba(0, 0, 0, 0.06);
    border: none;
    color: var(--ref-muted, #71818B);
    font-size: 0.8rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

[data-theme="dark"] .btn-search-close {
    background: rgba(255, 255, 255, 0.1);
    color: #E5EFF2;
}

.btn-search-close:hover {
    background: #7F0303;
    color: #FFFFFF;
}

.portal-search-body {
    max-height: 420px;
    overflow-y: auto;
    padding: 14px 16px;
}

.search-section-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--ref-muted, #71818B);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 6px 12px;
    margin-bottom: 4px;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 14px;
    border-radius: 14px;
    text-decoration: none;
    color: var(--ref-text, #192126);
    transition: all 0.18s ease;
}

.search-result-item:hover,
.search-result-item.selected {
    background: rgba(127, 3, 3, 0.08);
    transform: translateX(4px);
}

[data-theme="dark"] .search-result-item:hover,
[data-theme="dark"] .search-result-item.selected {
    background: rgba(255, 255, 255, 0.08);
}

.search-result-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--ref-canvas, #E4EFE7);
    color: #7F0303;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

[data-theme="dark"] .search-result-icon {
    background: #0D1619;
    color: #F87171;
}

.search-result-text {
    flex: 1;
    min-width: 0;
}

.search-result-text h5 {
    font-size: 0.86rem;
    font-weight: 700;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-text p {
    font-size: 0.72rem;
    color: var(--ref-muted, #71818B);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-arrow {
    color: var(--ref-muted, #71818B);
    font-size: 0.75rem;
}

.search-empty-state {
    text-align: center;
    padding: 36px 20px;
    color: var(--ref-muted, #71818B);
    font-size: 0.84rem;
}

.portal-search-footer {
    padding: 10px 20px;
    border-top: 1px solid var(--ref-border, rgba(0, 0, 0, 0.06));
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.7rem;
    color: var(--ref-muted, #71818B);
    background: rgba(0, 0, 0, 0.02);
}

[data-theme="dark"] .portal-search-footer {
    background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 600px) {
    .portal-search-overlay {
        padding: 20px 12px 20px;
    }
    .portal-search-card {
        border-radius: 20px;
        max-height: 85vh;
    }
    .portal-search-input {
        font-size: 0.95rem;
    }
    .portal-search-footer {
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }
}
</style>

<!-- Search Modal Overlay -->
<div class="portal-search-overlay" id="portalSearchOverlay" role="dialog" aria-modal="true" aria-label="Portal Search">
    <div class="portal-search-card">
        <div class="portal-search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="portal-search-input" id="portalSearchInput" placeholder="Search services, appointments, AICS, housing, records..." autocomplete="off">
            <button type="button" class="btn-search-close" id="portalSearchCloseBtn">ESC</button>
        </div>

        <div class="portal-search-body" id="portalSearchResults">
            <!-- Services Section -->
            <div class="search-category" id="searchServicesCategory">
                <div class="search-section-label">Portal Services &amp; Programs</div>
                <div class="search-items-group">
                    <a href="housing-applications.php" class="search-result-item" data-keywords="housing san lorenzo shelter applications relocation home">
                        <div class="search-result-icon"><i class="fas fa-home"></i></div>
                        <div class="search-result-text">
                            <h5>Housing Programs &amp; Applications</h5>
                            <p>Socialized housing packages, San Lorenzo Heights, and relocation assistance.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="aics.php" class="search-result-item" data-keywords="aics assistance financial medical burial hospital cash relief emergency money crisis aid">
                        <div class="search-result-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="search-result-text">
                            <h5>AICS Financial &amp; Medical Assistance</h5>
                            <p>Emergency medical, hospital, burial, and crisis financial support through CSWDO.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="appointments.php" class="search-result-item" data-keywords="appointment book schedule interview desk social worker visit meeting">
                        <div class="search-result-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="search-result-text">
                            <h5>Appointment Booking Desk</h5>
                            <p>Schedule face-to-face social worker interviews, document verification, and CSWDO visits.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="documents.php" class="search-result-item" data-keywords="documents upload files certificate id proof valid residency">
                        <div class="search-result-icon"><i class="fas fa-folder"></i></div>
                        <div class="search-result-text">
                            <h5>My Documents &amp; Uploads</h5>
                            <p>View, upload, and track validity of submitted citizen credentials and proof.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="announcements.php" class="search-result-item" data-keywords="announcements news updates advisories city hall events news">
                        <div class="search-result-icon"><i class="fas fa-bullhorn"></i></div>
                        <div class="search-result-text">
                            <h5>Portal Announcements &amp; Advisories</h5>
                            <p>Official Palayan City notices, assistance schedules, and public news.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="senior.php" class="search-result-item" data-keywords="senior citizen elderly osca pension benefits 60">
                        <div class="search-result-icon"><i class="fas fa-person-cane"></i></div>
                        <div class="search-result-text">
                            <h5>Senior Citizen Services</h5>
                            <p>OSCA social welfare programs, pension assistance, and Senior ID issuance.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="pwd.php" class="search-result-item" data-keywords="pwd disability discount accessibility assistance person with disability">
                        <div class="search-result-icon"><i class="fas fa-wheelchair"></i></div>
                        <div class="search-result-text">
                            <h5>PWD Services</h5>
                            <p>Support for Persons with Disabilities, ID applications, and welfare subsidies.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>

                    <a href="contact.php" class="search-result-item" data-keywords="contact helpdesk hotlines support office cswdo phone call numbers">
                        <div class="search-result-icon"><i class="fas fa-headset"></i></div>
                        <div class="search-result-text">
                            <h5>CSWDO Helpdesk &amp; Hotlines</h5>
                            <p>Emergency hotlines, office directory, and resident support inquiries.</p>
                        </div>
                        <i class="fas fa-chevron-right search-result-arrow"></i>
                    </a>
                </div>
            </div>

            <!-- User Records Section -->
            <?php if (!empty($user_search_records)): ?>
            <div class="search-category" id="searchRecordsCategory" style="margin-top: 14px;">
                <div class="search-section-label">Your Personal Activity &amp; Filings</div>
                <div class="search-items-group">
                    <?php foreach ($user_search_records as $rec): ?>
                        <a href="<?php echo htmlspecialchars($rec['url']); ?>" class="search-result-item" data-keywords="<?php echo htmlspecialchars(strtolower($rec['title'] . ' ' . $rec['sub'] . ' ' . $rec['type'])); ?>">
                            <div class="search-result-icon"><i class="<?php echo htmlspecialchars($rec['icon']); ?>"></i></div>
                            <div class="search-result-text">
                                <h5><?php echo htmlspecialchars($rec['title']); ?></h5>
                                <p><?php echo htmlspecialchars($rec['sub']); ?></p>
                            </div>
                            <i class="fas fa-chevron-right search-result-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="search-empty-state" id="searchEmptyState" style="display: none;">
                <i class="fas fa-search" style="font-size: 1.8rem; margin-bottom: 10px; opacity: 0.4;"></i>
                <p>No matches found for your query. Try searching for "AICS", "appointment", "housing", or "documents".</p>
            </div>
        </div>

        <div class="portal-search-footer">
            <span>Tip: Press <kbd>ESC</kbd> to close</span>
            <span>Palayan Kalinga Hub Search</span>
        </div>
    </div>
</div>

<script>
// Search Modal Interactive Logic
(function() {
    function initPortalSearch() {
        const overlay = document.getElementById('portalSearchOverlay');
        const input = document.getElementById('portalSearchInput');
        const openBtn = document.getElementById('openPortalSearchBtn');
        const closeBtn = document.getElementById('portalSearchCloseBtn');
        const resultsBox = document.getElementById('portalSearchResults');
        const emptyState = document.getElementById('searchEmptyState');
        const items = resultsBox ? resultsBox.querySelectorAll('.search-result-item') : [];

        function openSearch() {
            if (!overlay) return;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                if (input) {
                    input.value = '';
                    input.focus();
                    filterItems('');
                }
            }, 50);
        }

        function closeSearch() {
            if (!overlay) return;
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        function filterItems(q) {
            q = q.trim().toLowerCase();
            let visibleCount = 0;

            items.forEach(item => {
                const keywords = (item.getAttribute('data-keywords') || '') + ' ' + item.textContent.toLowerCase();
                if (!q || keywords.includes(q)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide/Show section labels
            document.querySelectorAll('.search-category').forEach(cat => {
                const groupItems = cat.querySelectorAll('.search-result-item');
                const hasVisible = Array.from(groupItems).some(it => it.style.display !== 'none');
                cat.style.display = hasVisible ? 'block' : 'none';
            });

            if (emptyState) {
                emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }

        if (openBtn) openBtn.addEventListener('click', openSearch);
        if (closeBtn) closeBtn.addEventListener('click', closeSearch);

        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeSearch();
            });
        }

        if (input) {
            input.addEventListener('input', (e) => filterItems(e.target.value));
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSearch();
            // Ctrl + K or / to open search
            if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
                e.preventDefault();
                openSearch();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalSearch);
    } else {
        initPortalSearch();
    }
})();
</script>
<script src="js/portal-autofilter.js"></script>

<!-- Smart Suggestions & Contextual Notifications Component -->
<?php include_once __DIR__ . '/smart-suggestions.php'; ?>


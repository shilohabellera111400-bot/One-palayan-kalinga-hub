<?php
// admin/includes/admin-search-modal.php - Interactive Admin Real-Time Search Modal
?>
<style>
/* ========== ADMIN LIVE SEARCH MODAL ========== */
.admin-search-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10, 18, 22, 0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 70px 20px 40px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.admin-search-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.admin-search-card {
    width: 100%;
    max-width: 680px;
    background: var(--ref-card);
    border-radius: 26px;
    border: 1px solid var(--ref-border);
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.32);
    overflow: hidden;
    transform: translateY(-16px) scale(0.98);
    transition: all 0.24s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}

.admin-search-overlay.active .admin-search-card {
    transform: translateY(0) scale(1);
}

.admin-search-input-wrap {
    display: flex;
    align-items: center;
    padding: 16px 22px;
    border-bottom: 1px solid var(--ref-border);
    gap: 14px;
    background: var(--ref-card);
}

.admin-search-input-wrap i.search-icon {
    font-size: 1.15rem;
    color: var(--ref-maroon);
}

.admin-search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 1rem;
    font-weight: 600;
    color: var(--ref-text);
    outline: none;
    font-family: inherit;
}

.admin-search-input::placeholder {
    color: var(--ref-muted);
    font-weight: 400;
}

.btn-admin-search-close {
    background: rgba(0, 0, 0, 0.06);
    border: none;
    color: var(--ref-muted);
    font-size: 0.76rem;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

[data-theme="dark"] .btn-admin-search-close {
    background: rgba(255, 255, 255, 0.1);
    color: #E5EFF2;
}

.btn-admin-search-close:hover {
    background: var(--ref-maroon);
    color: #FFFFFF;
}

.admin-search-body {
    overflow-y: auto;
    padding: 14px 16px;
    flex: 1;
}

.search-category-title {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--ref-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 8px 12px 4px;
    margin-bottom: 2px;
}

.search-result-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 14px;
    border-radius: 14px;
    text-decoration: none;
    color: var(--ref-text);
    transition: all 0.15s ease;
    margin-bottom: 3px;
}

.search-result-row:hover,
.search-result-row.selected {
    background: rgba(127, 3, 3, 0.08);
    transform: translateX(4px);
}

[data-theme="dark"] .search-result-row:hover,
[data-theme="dark"] .search-result-row.selected {
    background: rgba(255, 255, 255, 0.08);
}

.search-result-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--ref-canvas);
    color: var(--ref-maroon);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

[data-theme="dark"] .search-result-icon {
    color: #F87171;
}

.search-result-content {
    flex: 1;
    min-width: 0;
}

.search-result-content h5 {
    font-size: 0.86rem;
    font-weight: 700;
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-content p {
    font-size: 0.72rem;
    color: var(--ref-muted);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    background: var(--ref-canvas);
    color: var(--ref-muted);
    border: 1px solid var(--ref-border);
    white-space: nowrap;
}

.admin-search-status-box {
    text-align: center;
    padding: 36px 20px;
    color: var(--ref-muted);
    font-size: 0.86rem;
}

.admin-search-status-box i {
    display: block;
    font-size: 2.2rem;
    margin-bottom: 10px;
    opacity: 0.4;
}

.admin-search-footer {
    padding: 10px 20px;
    border-top: 1px solid var(--ref-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--ref-muted);
    background: rgba(0, 0, 0, 0.02);
}

[data-theme="dark"] .admin-search-footer {
    background: rgba(255, 255, 255, 0.02);
}
</style>

<!-- Admin Search Overlay -->
<div class="admin-search-overlay" id="adminSearchOverlay" role="dialog" aria-modal="true" aria-label="Administrative Search">
    <div class="admin-search-card">
        <div class="admin-search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="admin-search-input" id="adminLiveSearchInput" placeholder="Search users, residents, AICS, appointments, verifications, announcements..." autocomplete="off">
            <button type="button" class="btn-admin-search-close" id="adminSearchCloseBtn">ESC</button>
        </div>

        <div class="admin-search-body" id="adminSearchResults">
            <!-- Default Quick Links -->
            <div id="adminSearchDefaultView">
                <div class="search-category-title">Quick Administrative Access</div>
                <a href="manage-verifications.php" class="search-result-row">
                    <div class="search-result-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="search-result-content">
                        <h5>Review Residency Verifications</h5>
                        <p>Evaluate pending resident proof of residency requests</p>
                    </div>
                    <span class="search-result-badge">Review</span>
                </a>
                <a href="announcements.php" class="search-result-row">
                    <div class="search-result-icon"><i class="fas fa-bullhorn"></i></div>
                    <div class="search-result-content">
                        <h5>Publish New Announcement</h5>
                        <p>Broadcast city announcements to residents portal</p>
                    </div>
                    <span class="search-result-badge">Publish</span>
                </a>
                <a href="calendar.php" class="search-result-row">
                    <div class="search-result-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="search-result-content">
                        <h5>Calendar &amp; Event Schedules</h5>
                        <p>Manage civic events and scheduled activities</p>
                    </div>
                    <span class="search-result-badge">Schedule</span>
                </a>
                <a href="manage-residents.php" class="search-result-row">
                    <div class="search-result-icon"><i class="fas fa-user-friends"></i></div>
                    <div class="search-result-content">
                        <h5>Resident Directory</h5>
                        <p>Search all registered residents across barangays</p>
                    </div>
                    <span class="search-result-badge">Directory</span>
                </a>
                <a href="audit-logs.php" class="search-result-row">
                    <div class="search-result-icon"><i class="fas fa-history"></i></div>
                    <div class="search-result-content">
                        <h5>System Audit Logs</h5>
                        <p>Review system security, logins, and administrative actions</p>
                    </div>
                    <span class="search-result-badge">Security</span>
                </a>
            </div>

            <!-- Dynamic Search Results will render here -->
            <div id="adminSearchDynamicResults" style="display: none;"></div>
        </div>

        <div class="admin-search-footer">
            <span><i class="fas fa-keyboard"></i> Use <b>&uarr;</b> <b>&darr;</b> to navigate, <b>Enter</b> to open</span>
            <span>Press <b>ESC</b> to close</span>
        </div>
    </div>
</div>

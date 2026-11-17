<?php
// admin/includes/admin-footer.php - Unified Admin Footer & Scripts
?>
</main>
</div><!-- /.app-container -->

<?php require_once __DIR__ . '/admin-search-modal.php'; ?>

<!-- Global Admin JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ========== THEME TOGGLE ==========
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('theme', theme);
        } catch(e) {}
        document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
        
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
        if (typeof updateChartsTheme === 'function') {
            try { updateChartsTheme(); } catch(e) {}
        }
    }

    const savedTheme = localStorage.getItem('theme') ||
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(current);
        });
    }

    // ========== SEARCH MODAL LOGIC ==========
    const searchWrap = document.getElementById('adminSearchWrap');
    const searchTrigger = document.getElementById('adminSearchTrigger');
    const searchOverlay = document.getElementById('adminSearchOverlay');
    const searchCloseBtn = document.getElementById('adminSearchCloseBtn');
    const liveSearchInput = document.getElementById('adminLiveSearchInput');
    const defaultView = document.getElementById('adminSearchDefaultView');
    const dynamicResults = document.getElementById('adminSearchDynamicResults');

    function openSearchModal() {
        if (!searchOverlay) return;
        searchOverlay.classList.add('active');
        if (liveSearchInput) {
            liveSearchInput.value = '';
            setTimeout(() => liveSearchInput.focus(), 80);
        }
        if (defaultView) defaultView.style.display = 'block';
        if (dynamicResults) {
            dynamicResults.style.display = 'none';
            dynamicResults.innerHTML = '';
        }
    }

    function closeSearchModal() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('active');
    }

    if (searchWrap) searchWrap.addEventListener('click', openSearchModal);
    if (searchTrigger) searchTrigger.addEventListener('focus', openSearchModal);
    if (searchCloseBtn) searchCloseBtn.addEventListener('click', closeSearchModal);

    if (searchOverlay) {
        searchOverlay.addEventListener('click', function (e) {
            if (e.target === searchOverlay) closeSearchModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openSearchModal();
        } else if (e.key === 'Escape' && searchOverlay && searchOverlay.classList.contains('active')) {
            closeSearchModal();
        }
    });

    // Real-Time AJAX Search with Debounce
    let searchTimer = null;
    if (liveSearchInput) {
        liveSearchInput.addEventListener('input', function () {
            const val = this.value.trim();
            clearTimeout(searchTimer);

            if (val.length < 2) {
                if (defaultView) defaultView.style.display = 'block';
                if (dynamicResults) {
                    dynamicResults.style.display = 'none';
                    dynamicResults.innerHTML = '';
                }
                return;
            }

            if (defaultView) defaultView.style.display = 'none';
            if (dynamicResults) {
                dynamicResults.style.display = 'block';
                dynamicResults.innerHTML = `
                    <div class="admin-search-status-box">
                        <i class="fas fa-spinner fa-spin" style="color: var(--ref-maroon);"></i>
                        <p>Searching administrative records across database...</p>
                    </div>
                `;
            }

            searchTimer = setTimeout(() => {
                fetch('admin-search-ajax.php?q=' + encodeURIComponent(val))
                    .then(res => res.json())
                    .then(data => {
                        if (!dynamicResults) return;

                        if (!data.success || data.count === 0 || !data.results) {
                            dynamicResults.innerHTML = `
                                <div class="admin-search-status-box">
                                    <i class="fas fa-search-minus"></i>
                                    <p>No administrative records found matching "<b>${escapeHtml(val)}</b>"</p>
                                    <small style="color: var(--ref-muted);">Try searching by applicant name, reference number, ID, or email.</small>
                                </div>
                            `;
                            return;
                        }

                        let htmlContent = '';
                        for (const [category, items] of Object.entries(data.results)) {
                            htmlContent += `<div class="search-category-title">${escapeHtml(category)} (${items.length})</div>`;
                            items.forEach(item => {
                                htmlContent += `
                                    <a href="${escapeHtml(item.url)}" class="search-result-row">
                                        <div class="search-result-icon"><i class="${escapeHtml(item.icon)}"></i></div>
                                        <div class="search-result-content">
                                            <h5>${escapeHtml(item.title)}</h5>
                                            <p>${escapeHtml(item.subtitle)}</p>
                                        </div>
                                        <span class="search-result-badge">${escapeHtml(item.badge)}</span>
                                    </a>
                                `;
                            });
                        }
                        dynamicResults.innerHTML = htmlContent;
                    })
                    .catch(err => {
                        if (dynamicResults) {
                            dynamicResults.innerHTML = `
                                <div class="admin-search-status-box">
                                    <i class="fas fa-exclamation-triangle" style="color: #DC2626;"></i>
                                    <p>Search failed. Please try again.</p>
                                </div>
                            `;
                        }
                    });
            }, 250);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
<!-- Universal Auto-Filter & Live-Search -->
<script src="../js/portal-autofilter.js"></script>
</body>
</html>

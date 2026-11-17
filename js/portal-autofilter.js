/**
 * Universal Auto-Filter & Live-Search Engine
 * One Palayan-Kalinga Hub (Admin, Staff, and Resident Portals)
 *
 * Automatically triggers filtering on dropdown/date changes and debounces
 * search inputs across all search and filter modules.
 */
(function () {
    'use strict';

    function initPortalAutoFilter() {
        // 1. Standalone in-memory DOM table search inputs (like #globalSearch)
        initClientTableSearch();

        // 2. Server-side GET filter forms
        initServerFilterForms();
    }

    /**
     * Initializes in-memory DOM table searches that don't reload the page
     */
    function initClientTableSearch() {
        const clientSearchInputs = document.querySelectorAll('#globalSearch, input[data-table-filter], .client-table-search');
        clientSearchInputs.forEach(input => {
            if (input.dataset.autoFilterBound) return;
            input.dataset.autoFilterBound = 'true';

            let debounceTimer = null;
            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const query = input.value.trim().toLowerCase();
                    const targetTable = input.dataset.targetTable 
                        ? document.querySelector(input.dataset.targetTable)
                        : document.querySelector('.data-table, .senior-table, .log-table, table:not(.fc-scrollgrid-sync-table)');
                    
                    if (!targetTable) return;
                    const rows = targetTable.querySelectorAll('tbody tr:not(.no-filter)');
                    rows.forEach(row => {
                        // Skip empty state row if present
                        if (row.querySelector('.no-data') || row.classList.contains('no-data-row')) return;
                        const text = row.textContent.toLowerCase();
                        row.style.display = (query === '' || text.indexOf(query) > -1) ? '' : 'none';
                    });
                }, 150);
            });
        });
    }

    /**
     * Initializes standard GET filter forms across Admin, Staff, and Resident modules
     */
    function initServerFilterForms() {
        const forms = document.querySelectorAll('form[method="GET"], form.filter-form, form.filter-toolbar-form, #filterForm');

        forms.forEach(form => {
            // Skip forms explicitly flagged or already bound
            if (form.dataset.autoFilterBound || form.dataset.noAutoFilter) return;
            form.dataset.autoFilterBound = 'true';

            // Skip staff/manage-aics.php filterForm if performAicsFilter handles it via AJAX
            if (form.id === 'filterForm' && typeof window.performAicsFilter === 'function') {
                return;
            }

            let debounceTimer = null;

            // A. Dropdown (<select>) changes -> trigger filter immediately
            const selects = form.querySelectorAll('select:not([data-no-auto-filter])');
            selects.forEach(select => {
                select.addEventListener('change', function () {
                    clearTimeout(debounceTimer);
                    executeFilterSubmit(form, select.name);
                });
            });

            // B. Date input changes -> trigger filter immediately
            const dateInputs = form.querySelectorAll('input[type="date"]:not([data-no-auto-filter])');
            dateInputs.forEach(dateInput => {
                dateInput.addEventListener('change', function () {
                    clearTimeout(debounceTimer);
                    executeFilterSubmit(form, dateInput.name);
                });
            });

            // C. Search input debounced typing (450ms)
            const searchInputs = form.querySelectorAll(
                'input[type="text"][name*="search"], input[type="search"], input[name="search"], input[name="app_search"], input[name="q"], input[name="applicant_search"], input.filter-control[placeholder*="search" i], input.filter-input[placeholder*="search" i]'
            );

            searchInputs.forEach(searchInput => {
                const inputName = searchInput.name || 'search';

                // Restore cursor and focus if page reloaded from auto-search
                try {
                    const focusedField = sessionStorage.getItem('portal_autosearch_focused');
                    if (focusedField === inputName) {
                        sessionStorage.removeItem('portal_autosearch_focused');
                        searchInput.focus();
                        const len = searchInput.value.length;
                        searchInput.setSelectionRange(len, len);
                    }
                } catch (e) {}

                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    try {
                        sessionStorage.setItem('portal_autosearch_focused', inputName);
                    } catch (e) {}

                    debounceTimer = setTimeout(() => {
                        executeFilterSubmit(form, inputName);
                    }, 450);
                });

                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(debounceTimer);
                        try {
                            sessionStorage.setItem('portal_autosearch_focused', inputName);
                        } catch (e) {}
                        executeFilterSubmit(form, inputName);
                    }
                });
            });

            // D. Prevent form intercept when Export CSV or other explicit action buttons are clicked
            form.addEventListener('submit', function (e) {
                const submitter = e.submitter;
                if (submitter && (submitter.name === 'export' || submitter.value === 'csv' || submitter.classList.contains('btn-csv') || submitter.classList.contains('export-btn'))) {
                    // Let default browser file download proceed
                    return;
                }
            });
        });
    }

    /**
     * Executes the filter form submission safely and smoothly
     */
    function executeFilterSubmit(form, triggeredByName) {
        if (!form) return;

        // Reset pagination input to page 1 on filter/search change
        const pageInput = form.querySelector('input[name="page"], input[name="p"]');
        if (pageInput) {
            pageInput.value = '1';
        }

        // Show subtle visual loading state if table container exists
        const tableCard = document.querySelector('.table-container, .table-card, .data-table, .senior-table');
        if (tableCard) {
            tableCard.style.transition = 'opacity 0.2s ease';
            tableCard.style.opacity = '0.5';
            tableCard.style.pointerEvents = 'none';
        }

        // Submit form
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    // Auto-run on DOM ready and support dynamic/AJAX content loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPortalAutoFilter);
    } else {
        initPortalAutoFilter();
    }

    // Expose utility globally
    window.initPortalAutoFilter = initPortalAutoFilter;
})();

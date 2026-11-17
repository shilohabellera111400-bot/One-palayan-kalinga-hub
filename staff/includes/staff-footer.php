<?php
// staff/includes/staff-footer.php - Shared Footer Scripts for Staff Portal
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Sidebar Drawer Toggle
    var mobileToggle = document.getElementById('staffMobileToggle');
    var sidebar = document.getElementById('staffSidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
        });

        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('mobile-open') && !sidebar.contains(e.target) && e.target !== mobileToggle) {
                sidebar.classList.remove('mobile-open');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }

    // Client-side quick table search
    var searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('table:not(.fc-scrollgrid-sync-table) tbody tr, .request-item');
            if (rows.length > 0) {
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = (filter === '' || text.indexOf(filter) > -1) ? '' : 'none';
                });
            }
        });
    }
});
</script>
<!-- Universal Auto-Filter & Live-Search -->
<script src="../js/portal-autofilter.js"></script>
</body>
</html>

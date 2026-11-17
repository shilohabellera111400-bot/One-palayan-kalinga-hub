<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_document'])) {
    csrf_verify();
    $document_id = sanitize($_POST['document_id']);
    $verification_status = sanitize($_POST['verification_status']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    // Validation: Only allowed verification statuses
    if (!in_array($verification_status, ['verified', 'rejected'])) {
        $error = "Invalid verification decision selected.";
    } elseif ($verification_status === 'rejected' && empty(trim($remarks))) {
        $error = "A disapproval reason / feedback remark is required when disapproving a document.";
    } else {
        $update_query = "UPDATE documents SET 
                          verification_status = :status,
                          verified_by = :user_id,
                          verification_date = NOW(),
                          remarks = :remarks
                          WHERE document_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $verification_status);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->bindParam(':remarks', $remarks);
        $update_stmt->bindParam(':id', $document_id);
        
        if ($update_stmt->execute()) {
            // Get user_id for notification
            $user_query = "SELECT user_id, document_type FROM documents WHERE document_id = :id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':id', $document_id);
            $user_stmt->execute();
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $type_clean = ucfirst(str_replace('_', ' ', $user['document_type'] ?? 'document'));
                $status_text = ($verification_status === 'verified') ? 'VERIFIED and APPROVED' : 'RETURNED / DISAPPROVED';
                $notif_title = ($verification_status === 'verified') ? 'Document Verified' : 'Document Disapproved';
                $notif_msg = "Your uploaded requirement ({$type_clean}) has been {$status_text} by the CSWDO verification officer.";
                if (!empty($remarks)) {
                    $notif_msg .= " Officer Remarks: {$remarks}";
                }

                $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES (:user_id, :title, :message, 'document')";
                $notif_stmt = $db->prepare($notif_query);
                $notif_stmt->bindParam(':user_id', $user['user_id']);
                $notif_stmt->bindParam(':title', $notif_title);
                $notif_stmt->bindParam(':message', $notif_msg);
                $notif_stmt->execute();
            }
            
            $status_label = ($verification_status === 'verified') ? 'verified and approved' : 'marked as disapproved/returned';
            $success = "Document #DOC-" . str_pad($document_id, 5, '0', STR_PAD_LEFT) . " successfully {$status_label}. Citizen notified.";
        } else {
            $error = "Failed to update document verification status. Please try again.";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT d.*, u.first_name, u.last_name, u.email, u.contact_number,
                 v.first_name as verifier_fn, v.last_name as verifier_ln
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          LEFT JOIN users v ON d.verified_by = v.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all' && !empty($status_filter)) {
    $query .= " AND d.verification_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR d.document_type LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$documents_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
if (!$stats) {
    $stats = ['total' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0];
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$page_title = 'Verify Documents - CSWDO Staff Portal';
$active_nav = 'documents';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
/* Header Action Buttons */
.staff-subheader-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-head-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid var(--ref-border);
    background: var(--ref-card);
    color: var(--ref-text);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: all 0.18s ease;
}
.btn-head-action:hover {
    background: var(--ref-canvas);
    border-color: var(--ref-border-strong);
    color: var(--ref-maroon);
    transform: translateY(-1px);
}
.btn-head-action.primary {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}
.btn-head-action.primary:hover {
    background: var(--ref-maroon-dark);
    color: #FFFFFF;
}

/* Redesigned Minimalist Status Summary Cards (Part 2, Item 7) */
.stats-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 22px;
}
.stat-summary-card {
    background: var(--ref-card);
    border-radius: 16px;
    padding: 14px 18px;
    border: 1px solid var(--ref-border);
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    color: inherit;
    transition: all 0.18s ease;
}
.stat-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    border-color: var(--ref-maroon);
}
.stat-summary-card.active-stat {
    border: 2px solid var(--ref-maroon);
    background: #FFFAF8;
}
.stat-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.stat-icon-wrap.total { background: #F1F5F9; color: #475569; }
.stat-icon-wrap.pending { background: #FEF3C7; color: #D97706; }
.stat-icon-wrap.verified { background: #DCFCE7; color: #16A34A; }
.stat-icon-wrap.rejected { background: #FEE2E2; color: #DC2626; }

.stat-details {
    display: flex;
    flex-direction: column;
}
.stat-details h3 {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--ref-text);
    line-height: 1.1;
    margin: 0 0 2px 0;
}
.stat-details p {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0;
}

/* Filter Bar */
.filter-card {
    background: var(--ref-card);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 22px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
}
.filter-form-grid {
    display: grid;
    grid-template-columns: 1fr 2fr auto auto;
    gap: 12px;
    align-items: end;
}
.filter-form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.filter-form-group label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.filter-form-group input, .filter-form-group select {
    height: 38px;
    padding: 0 12px;
    border: 1px solid var(--ref-border);
    border-radius: 10px;
    font-size: 0.82rem;
    background: var(--ref-canvas);
    color: var(--ref-text);
    outline: none;
    transition: all 0.15s;
}
.filter-form-group input:focus, .filter-form-group select:focus {
    border-color: var(--ref-maroon);
    background: var(--ref-card);
}
.btn-filter-apply {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border: none;
    height: 38px;
    padding: 0 18px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.8rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.btn-filter-apply:hover { background: var(--ref-maroon-dark); }
.btn-filter-reset {
    background: var(--ref-canvas);
    color: var(--ref-muted);
    border: 1px solid var(--ref-border);
    height: 38px;
    padding: 0 14px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-filter-reset:hover { color: var(--ref-maroon); border-color: var(--ref-maroon); }

/* Table and Records Card */
.table-card {
    background: var(--ref-card);
    border-radius: 18px;
    padding: 20px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
    overflow: visible;
}
.table-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ref-border);
}
.table-card .card-header h2 {
    color: var(--ref-text);
    font-size: 1.05rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.table-card .card-header .count-badge {
    background: var(--ref-canvas);
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.72rem;
    color: var(--ref-muted);
    font-weight: 700;
}

.table-container {
    overflow-x: auto;
    overflow-y: visible;
    padding-bottom: 40px; /* space for dropdown menus */
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.data-table th {
    background: var(--ref-canvas);
    color: var(--ref-muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    padding: 10px 14px;
    border-bottom: 1px solid var(--ref-border);
    text-align: left;
}
.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--ref-border);
    color: var(--ref-text);
    vertical-align: middle;
    position: relative;
}
.data-table tr:hover td {
    background: #FAF8F5;
}

/* Status Badges */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
}
.status-pill.pending { background: #FEF3C7; color: #B45309; }
.status-pill.verified { background: #DCFCE7; color: #15803D; }
.status-pill.rejected { background: #FEE2E2; color: #B91C1C; }

/* Document Attachment Pill */
.btn-doc-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    border-radius: 8px;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ref-maroon);
    text-decoration: none;
    transition: all 0.15s;
}
.btn-doc-link:hover {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}

/* Standardized Row Action Dropdown (Part 2, Item 5) */
.row-action-dropdown { position: relative; display: inline-block; }
.btn-row-action {
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    border-radius: 50px;
    padding: 5px 14px;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ref-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s;
}
.btn-row-action:hover, .row-action-dropdown.open .btn-row-action {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}
.row-action-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    min-width: 210px;
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.18);
    z-index: 1200;
    padding: 6px;
    text-align: left;
}
.row-action-dropdown.open .row-action-menu {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.action-menu-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--ref-text);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    width: 100%;
    transition: all 0.15s;
}
.action-menu-item:hover {
    background: var(--ref-canvas);
    color: var(--ref-maroon);
}
.action-menu-item.danger-item { color: #DC2626; }
.action-menu-item.danger-item:hover { background: #FEE2E2; color: #991B1B; }
.action-menu-divider { height: 1px; background: var(--ref-border); margin: 4px 0; }

/* Modal */
.modal { position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); display: none; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal.active { display: flex; }
.modal-content { background: var(--ref-card); border-radius: var(--ref-radius-xl); width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; border: 1px solid var(--ref-border); box-shadow: var(--ref-shadow-lg); animation: modalPop 0.18s ease-out; }
@keyframes modalPop { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--ref-border); display: flex; justify-content: space-between; align-items: center; background: var(--ref-card-subtle); }
.modal-header h2 { font-size: 1.15rem; font-weight: 800; color: var(--ref-text); margin: 0; display: flex; align-items: center; gap: 10px; }
.modal-body { padding: 22px 24px; }
.info-card-mini { background: var(--ref-canvas); border-radius: 12px; padding: 14px; margin-bottom: 18px; border: 1px solid var(--ref-border); }
.info-row-mini { display: flex; justify-content: space-between; margin-bottom: 6px; }
.info-row-mini:last-child { margin-bottom: 0; }
.info-label-mini { font-size: 0.76rem; color: var(--ref-muted); font-weight: 600; }
.info-value-mini { font-size: 0.82rem; font-weight: 700; color: var(--ref-text); }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 700; font-size: 0.78rem; color: var(--ref-text); text-transform: uppercase; letter-spacing: 0.04em; }
.form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ref-border); border-radius: 10px; font-size: 0.84rem; background: var(--ref-card); color: var(--ref-text); outline: none; font-family: inherit; box-sizing: border-box; }
.form-group select:focus, .form-group textarea:focus { border-color: var(--ref-maroon); box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08); }

@media (max-width: 900px) {
    .stats-summary-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 580px) {
    .stats-summary-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Verify Documents';
            $banner_subtitle = 'Review, authenticate, and validate citizen-submitted requirements, IDs, and certificates';
            $banner_icon = 'fa-file-circle-check';
            $banner_actions = [
                ['label' => 'Export Data', 'url' => 'export-documents.php?status=' . urlencode($status_filter) . '&search=' . urlencode($search), 'icon' => 'fa-download', 'type' => 'primary'],
                ['label' => 'View Reports', 'url' => 'reports.php?category=documents', 'icon' => 'fa-chart-line', 'type' => 'secondary']
            ];
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <!-- Feedback Alerts -->
            <?php if(!empty($error)): ?>
                <div class="alert alert-error" style="background: #FEE2E2; color: #991B1B; padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; border-left: 4px solid #EF4444; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle"></i> <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if(!empty($success)): ?>
                <div class="alert alert-success" style="background: #F0FDF4; color: #166534; padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; border-left: 4px solid #22C55E; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
        
            <!-- Redesigned Minimalist Status Summary Cards (Part 2, Item 7) -->
            <div class="stats-summary-grid">
                <a href="verify-documents.php?status=all" class="stat-summary-card <?php echo $status_filter == 'all' ? 'active-stat' : ''; ?>">
                    <div class="stat-icon-wrap total"><i class="fas fa-folder-open"></i></div>
                    <div class="stat-details">
                        <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
                        <p>Total Records</p>
                    </div>
                </a>
                <a href="verify-documents.php?status=pending" class="stat-summary-card <?php echo $status_filter == 'pending' ? 'active-stat' : ''; ?>">
                    <div class="stat-icon-wrap pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <h3 style="color:#D97706;"><?php echo (int)($stats['pending'] ?? 0); ?></h3>
                        <p>Pending Review</p>
                    </div>
                </a>
                <a href="verify-documents.php?status=verified" class="stat-summary-card <?php echo $status_filter == 'verified' ? 'active-stat' : ''; ?>">
                    <div class="stat-icon-wrap verified"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <h3 style="color:#16A34A;"><?php echo (int)($stats['verified'] ?? 0); ?></h3>
                        <p>Verified</p>
                    </div>
                </a>
                <a href="verify-documents.php?status=rejected" class="stat-summary-card <?php echo $status_filter == 'rejected' ? 'active-stat' : ''; ?>">
                    <div class="stat-icon-wrap rejected"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-details">
                        <h3 style="color:#DC2626;"><?php echo (int)($stats['rejected'] ?? 0); ?></h3>
                        <p>Disapproved</p>
                    </div>
                </a>
            </div>
            
            <!-- Compact Filter Bar -->
            <div class="filter-card">
                <form method="GET" id="filterForm">
                    <div class="filter-form-grid">
                        <div class="filter-form-group">
                            <label><i class="fas fa-filter"></i> Status Filter</label>
                            <select name="status" id="statusFilter" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                                <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified &amp; Approved</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved / Returned</option>
                            </select>
                        </div>
                        
                        <div class="filter-form-group">
                            <label><i class="fas fa-search"></i> Search Applicant or Document</label>
                            <input type="text" name="search" placeholder="Type resident name, email, or requirement type..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div>
                            <button type="submit" class="btn-filter-apply">
                                <i class="fas fa-search"></i> Apply
                            </button>
                        </div>
                        <div>
                            <?php if($status_filter != 'all' || !empty($search)): ?>
                            <a href="verify-documents.php" class="btn-filter-reset">
                                <i class="fas fa-times"></i> Clear
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Verification Data Table -->
            <div class="table-card">
                <div class="card-header">
                    <h2><i class="fas fa-list-check" style="color: var(--ref-maroon);"></i> Document Verification Queue</h2>
                    <span class="count-badge"><?php echo count($documents_list); ?> records displayed</span>
                </div>

                <?php if(empty($documents_list)): ?>
                    <div style="text-align: center; padding: 48px 20px;">
                        <i class="fas fa-folder-open" style="font-size: 40px; color: #CBD5E1; margin-bottom: 12px; display: block;"></i>
                        <h4 style="margin: 0 0 6px 0; color: var(--ref-text); font-weight: 700;">No Documents Found</h4>
                        <p style="margin: 0; color: var(--ref-muted); font-size: 0.82rem;">Try clearing your search query or selecting a different status filter.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Document ID &amp; Type</th>
                                    <th>Citizen Applicant</th>
                                    <th>Uploaded Date</th>
                                    <th>File Attachment</th>
                                    <th>Status</th>
                                    <th>Review Remarks</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($documents_list as $doc): ?>
                                <?php 
                                    $ext = strtolower(pathinfo($doc['document_path'] ?? '', PATHINFO_EXTENSION));
                                    $iconClass = ($ext === 'pdf') ? 'fa-file-pdf' : (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'fa-file-image' : 'fa-file-alt');
                                    $iconColor = ($ext === 'pdf') ? '#DC2626' : (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? '#2563EB' : '#475569');
                                    $docIdLabel = 'DOC-' . str_pad($doc['document_id'], 5, '0', STR_PAD_LEFT);
                                    $cleanType = ucfirst(str_replace('_', ' ', $doc['document_type'] ?? 'Requirement'));
                                    $applicantName = htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--ref-text); display: flex; align-items: center; gap: 7px;">
                                            <i class="fas <?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>; font-size: 0.95rem;"></i>
                                            <span><?php echo $cleanType; ?></span>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--ref-muted); margin-top: 2px; font-family: monospace;">
                                            #<?php echo $docIdLabel; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--ref-text);">
                                            <?php echo $applicantName; ?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--ref-muted); margin-top: 2px;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doc['email'] ?? 'N/A'); ?>
                                            <?php if(!empty($doc['contact_number'])): ?>
                                                • <i class="fas fa-phone"></i> <?php echo htmlspecialchars($doc['contact_number']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo !empty($doc['upload_date']) ? date('M d, Y', strtotime($doc['upload_date'])) : 'N/A'; ?>
                                        </div>
                                        <div style="font-size: 0.72rem; color: var(--ref-muted); margin-top: 2px;">
                                            <i class="fas fa-clock"></i> <?php echo !empty($doc['upload_date']) ? date('h:i A', strtotime($doc['upload_date'])) : ''; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(!empty($doc['document_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($doc['document_path']); ?>" target="_blank" class="btn-doc-link" title="Open file in new tab">
                                                <i class="fas fa-external-link-alt"></i> Open File
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--ref-muted); font-size: 0.74rem;">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($doc['verification_status'] === 'verified'): ?>
                                            <span class="status-pill verified"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php elseif($doc['verification_status'] === 'rejected'): ?>
                                            <span class="status-pill rejected"><i class="fas fa-times-circle"></i> Disapproved</span>
                                        <?php else: ?>
                                            <span class="status-pill pending"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 220px;">
                                        <?php if(!empty($doc['remarks'])): ?>
                                            <div style="font-size: 0.74rem; color: var(--ref-text); line-height: 1.3;">
                                                <?php echo htmlspecialchars($doc['remarks']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.72rem; color: var(--ref-muted); font-style: italic;">None provided</span>
                                        <?php endif; ?>
                                        <?php if(!empty($doc['verifier_fn'])): ?>
                                            <div style="font-size: 0.68rem; color: var(--ref-muted); margin-top: 3px;">
                                                <i class="fas fa-user-check"></i> By <?php echo htmlspecialchars($doc['verifier_fn'] . ' ' . $doc['verifier_ln']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <!-- Standardized Minimalist Row Action Dropdown -->
                                        <div class="row-action-dropdown">
                                            <button type="button" class="btn-dot-action" onclick="toggleRowAction(event, this)" title="Actions" aria-label="Row Actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="row-action-menu">
                                                <button type="button" class="action-menu-item" onclick='openVerifyModal(<?php echo json_encode($doc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                    <i class="fas fa-file-signature" style="color: var(--ref-maroon);"></i> 
                                                    <?php echo $doc['verification_status'] === 'pending' ? 'Review &amp; Verify' : 'Update Decision'; ?>
                                                </button>
                                                <?php if(!empty($doc['document_path'])): ?>
                                                    <a href="../<?php echo htmlspecialchars($doc['document_path']); ?>" target="_blank" class="action-menu-item">
                                                        <i class="fas fa-external-link-alt" style="color: #2563EB;"></i> Open Attachment
                                                    </a>
                                                <?php endif; ?>
                                                <a href="manage-residents.php?search=<?php echo urlencode($doc['first_name'] . ' ' . $doc['last_name']); ?>" class="action-menu-item">
                                                    <i class="fas fa-user" style="color: #059669;"></i> Resident Profile
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div> <!-- Close table-card -->
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<!-- Verification Modal -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-signature" style="color: var(--ref-maroon);"></i> Verify Document</h2>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ref-muted);">&times;</button>
        </div>
        
        <form method="POST" id="verifyForm" onsubmit="return handleFormSubmit(this)">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <input type="hidden" name="document_id" id="modal_document_id">
                
                <div class="info-card-mini">
                    <div class="info-row-mini">
                        <span class="info-label-mini">Document ID:</span>
                        <span class="info-value-mini" id="modal_document_id_display" style="font-family: monospace; color: var(--ref-maroon);"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Document Type:</span>
                        <span class="info-value-mini" id="modal_document_info"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Citizen Applicant:</span>
                        <span class="info-value-mini" id="modal_uploader"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Attachment File:</span>
                        <span class="info-value-mini">
                            <a href="#" id="modal_document_link" target="_blank" class="btn-doc-link" style="padding: 2px 8px;">
                                <i class="fas fa-external-link-alt"></i> View Uploaded File
                            </a>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_status"><i class="fas fa-stamp"></i> Verification Decision</label>
                    <select name="verification_status" id="modal_status" required onchange="toggleRemarksField()">
                        <option value="verified">✅ Verified &amp; Approved — Requirement is valid</option>
                        <option value="rejected">❌ Disapproved / Return — Requirement is invalid or unreadable</option>
                    </select>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="modal_remarks" style="color: #DC2626;">
                        <i class="fas fa-exclamation-circle"></i> Disapproval / Return Feedback (Mandatory, emailed/notified to resident):
                    </label>
                    <textarea name="remarks" id="modal_remarks" rows="3" 
                              placeholder="Please clearly explain to the citizen what needs correction or re-uploading..."></textarea>
                </div>

                <div class="form-group" id="approval_notes_field">
                    <label for="modal_approval_remarks" style="color: var(--ref-muted);">
                        Optional Officer Approval Notes:
                    </label>
                    <textarea name="approval_remarks" id="modal_approval_remarks" rows="2" 
                              placeholder="Optional notes or reference details for office records..."></textarea>
                </div>
            </div>
            
            <div class="modal-header" style="border-top: 1px solid var(--ref-border); justify-content: flex-end; gap: 10px; padding: 14px 24px;">
                <button type="button" onclick="closeModal()" class="btn-filter-reset" style="cursor: pointer;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="verify_document" id="btnSubmitVerification" class="btn-filter-apply" style="cursor: pointer;">
                    <i class="fas fa-save"></i> Save Decision
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRowAction(e, btn) {
    e.stopPropagation();
    const dropdown = btn.closest('.row-action-dropdown');
    if (!dropdown) return;
    const isOpen = dropdown.classList.contains('open') || dropdown.classList.contains('active');
    closeAllActionMenus();
    if (!isOpen) {
        const btnRect = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - btnRect.bottom;
        if (spaceBelow < 220) {
            dropdown.classList.add('dropup');
        }
        dropdown.classList.add('open');
        dropdown.classList.add('active');
    }
}

function closeAllActionMenus() {
    document.querySelectorAll('.row-action-dropdown.open, .row-action-dropdown.active').forEach(dd => {
        dd.classList.remove('open', 'active', 'dropup');
    });
}

function openVerifyModal(doc) {
    closeAllActionMenus();
    document.getElementById('modal_document_id').value = doc.document_id;
    document.getElementById('modal_document_id_display').textContent = 'DOC-' + String(doc.document_id).padStart(5, '0');
    
    const cleanType = (doc.document_type || 'Document').replace(/_/g, ' ');
    document.getElementById('modal_document_info').textContent = cleanType.charAt(0).toUpperCase() + cleanType.slice(1);
    
    document.getElementById('modal_uploader').textContent = (doc.first_name || '') + ' ' + (doc.last_name || '');
    
    const docLink = document.getElementById('modal_document_link');
    if (doc.document_path) {
        docLink.href = '../' + doc.document_path;
        docLink.style.display = 'inline-flex';
    } else {
        docLink.style.display = 'none';
    }
    
    const statusVal = (doc.verification_status === 'rejected') ? 'rejected' : 'verified';
    document.getElementById('modal_status').value = statusVal;
    
    if (statusVal === 'rejected') {
        document.getElementById('modal_remarks').value = doc.remarks || '';
    } else {
        document.getElementById('modal_approval_remarks').value = doc.remarks || '';
    }
    
    toggleRemarksField();
    document.getElementById('verifyModal').classList.add('active');
}

function closeModal() {
    document.getElementById('verifyModal').classList.remove('active');
    document.getElementById('modal_remarks').value = '';
    document.getElementById('modal_approval_remarks').value = '';
    const submitBtn = document.getElementById('btnSubmitVerification');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Decision';
    }
}

function toggleRemarksField() {
    const status = document.getElementById('modal_status').value;
    const remarksField = document.getElementById('remarks_field');
    const remarksText = document.getElementById('modal_remarks');
    const approvalField = document.getElementById('approval_notes_field');
    
    if (status === 'rejected') {
        remarksField.style.display = 'block';
        remarksText.required = true;
        approvalField.style.display = 'none';
    } else {
        remarksField.style.display = 'none';
        remarksText.required = false;
        approvalField.style.display = 'block';
    }
}

function handleFormSubmit(form) {
    const status = document.getElementById('modal_status').value;
    const remarksText = document.getElementById('modal_remarks');
    const approvalNotes = document.getElementById('modal_approval_remarks');
    
    if (status === 'rejected') {
        if (!remarksText.value.trim()) {
            alert('Please enter a disapproval or return reason for the citizen.');
            remarksText.focus();
            return false;
        }
        if (!confirm('Are you sure you want to DISAPPROVE / RETURN this document? The resident will be notified to upload a replacement.')) {
            return false;
        }
    } else if (status === 'verified') {
        if (!confirm('Confirm marking this document as VERIFIED and APPROVED?')) {
            return false;
        }
    }
    
    // Copy approval notes to remarks if status is verified
    if (status === 'verified' && approvalNotes.value.trim()) {
        remarksText.value = approvalNotes.value.trim();
    }
    
    const submitBtn = document.getElementById('btnSubmitVerification');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    return true;
}

window.onclick = function(event) {
    const modal = document.getElementById('verifyModal');
    if (event.target === modal) {
        closeModal();
    }
    closeAllActionMenus();
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeAllActionMenus();
    }
});
</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

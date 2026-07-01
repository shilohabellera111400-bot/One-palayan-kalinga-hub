<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_document'])) {
    $document_id = sanitize($_POST['document_id']);
    $verification_status = sanitize($_POST['verification_status']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
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
        $user_query = "SELECT user_id FROM documents WHERE document_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $document_id);
        $user_stmt->execute();
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Document Verified', 
                        'Your document has been " . $verification_status . "', 'general')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $user['user_id']);
        $notif_stmt->execute();
        
        $success = "Document verification status updated!";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT d.*, u.first_name, u.last_name, u.email 
          FROM documents d
          JOIN users u ON d.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $query .= " AND d.verification_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR d.document_type LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Documents - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Enhanced navbar with home link -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="../index.php" style="text-decoration: none; color: inherit;">
                    <h1>ONE PALAYAN-KALINGA HUB</h1>
                    <p class="subtitle">CSWDO Palayan City</p>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="../services.php">Services</a></li>
                <li><a href="../appointments.php">Appointments</a></li>
                <li><a href="../track-aics.php">Track AICS</a></li>
                <li><a href="../housing.php">Housing Program</a></li>
                <li class="dropdown">
                    <a href="#"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?> <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="../staff-dashboard.php">Dashboard</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>Staff</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> AICS Requests</a></li>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Housing</a></li>
                <li><a href="verify-documents.php" class="active"><i class="fas fa-file-alt"></i> Verify Documents</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="menu-divider"></li>
                <li><a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <!-- Breadcrumb navigation -->
            <div class="breadcrumb">
                <a href="../staff-dashboard.php">Dashboard</a> > 
                <a href="verify-documents.php">Verify Documents</a>
            </div>
            
            <h1>Verify Documents</h1>
            
            <!-- Quick navigation bar -->
            <div class="quick-nav">
                <a href="../index.php" class="quick-nav-btn">
                    <i class="fas fa-home"></i> Main Site
                </a>
                <a href="../staff-dashboard.php" class="quick-nav-btn">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage-appointments.php" class="quick-nav-btn">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="manage-aics.php" class="quick-nav-btn">
                    <i class="fas fa-hand-holding-heart"></i> AICS
                </a>
                <a href="manage-housing.php" class="quick-nav-btn">
                    <i class="fas fa-home"></i> Housing
                </a>
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-value"><?php echo $stats['verified']; ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <input type="text" name="search" placeholder="Search by name or document type..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-primary">Filter</button>
                        <?php if($status_filter != 'all' || !empty($search)): ?>
                        <a href="verify-documents.php" class="btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Documents List -->
            <div class="documents-grid">
                <?php while($doc = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="document-card status-<?php echo $doc['verification_status']; ?>">
                    <div class="document-header">
                        <span class="document-type"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></span>
                        <span class="status-badge status-<?php echo $doc['verification_status']; ?>">
                            <?php echo ucfirst($doc['verification_status']); ?>
                        </span>
                    </div>
                    
                    <div class="document-body">
                        <h4><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></h4>
                        <p class="document-meta">
                            <i class="fas fa-calendar-alt"></i> Uploaded: <?php echo date('M d, Y h:i A', strtotime($doc['upload_date'])); ?><br>
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doc['email']); ?>
                        </p>
                        
                        <div class="document-preview">
                            <a href="../<?php echo $doc['document_path']; ?>" target="_blank" class="btn-small">
                                <i class="fas fa-file-pdf"></i> View Document
                            </a>
                        </div>
                        
                        <?php if(!empty($doc['remarks'])): ?>
                        <div class="document-remarks">
                            <i class="fas fa-comment"></i> <strong>Remarks:</strong> <?php echo htmlspecialchars($doc['remarks']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($doc['verification_status'] == 'pending'): ?>
                    <div class="document-actions">
                        <button onclick="showVerifyModal(<?php echo htmlspecialchars(json_encode($doc)); ?>)" 
                                class="btn-primary btn-small">
                            <i class="fas fa-check-circle"></i> Verify Now
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <!-- Verification Modal -->
    <div id="verifyModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2><i class="fas fa-file-signature"></i> Verify Document</h2>
            <form method="POST" id="verifyForm">
                <input type="hidden" name="document_id" id="modal_document_id">
                
                <div class="form-group">
                    <label>Document</label>
                    <p id="modal_document_info" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label>Uploaded by</label>
                    <p id="modal_uploader" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">Verification Status</label>
                    <select name="verification_status" id="modal_status" required onchange="toggleRemarksField()">
                        <option value="verified">✅ Verified - Document is valid</option>
                        <option value="rejected">❌ Rejected - Document is invalid</option>
                    </select>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="modal_remarks">Rejection Reason *</label>
                    <textarea name="remarks" id="modal_remarks" rows="3" 
                              placeholder="Please explain why this document is being rejected..."></textarea>
                </div>
                
                <div class="form-group">
                    <a href="#" id="modal_document_link" target="_blank" class="btn-small">
                        <i class="fas fa-external-link-alt"></i> Open Document
                    </a>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="verify_document" class="btn-primary">
                        <i class="fas fa-save"></i> Submit Verification
                    </button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    /* Additional styles for navigation */
    .breadcrumb {
        padding: 10px 0;
        margin-bottom: 20px;
        color: #666;
    }
    
    .breadcrumb a {
        color: #003366;
        text-decoration: none;
    }
    
    .breadcrumb a:hover {
        text-decoration: underline;
    }
    
    .quick-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .quick-nav-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        background: #f5f5f5;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.9rem;
        transition: all 0.3s;
    }
    
    .quick-nav-btn:hover {
        background: #003366;
        color: white;
    }
    
    .quick-nav-btn i {
        font-size: 1rem;
    }
    
    .sidebar-menu a i {
        width: 20px;
        margin-right: 10px;
        text-align: center;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #003366;
    }
    
    .stat-card.pending { border-left-color: #ff9800; }
    .stat-card.verified { border-left-color: #4CAF50; }
    .stat-card.rejected { border-left-color: #f44336; }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #003366;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }
    
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filters-form {
        display: flex;
        gap: 15px;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .search-group {
        display: flex;
        gap: 10px;
    }
    
    .search-group input {
        flex: 1;
    }
    
    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .document-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #ddd;
    }
    
    .document-card.status-pending { border-left-color: #ff9800; }
    .document-card.status-verified { border-left-color: #4CAF50; }
    .document-card.status-rejected { border-left-color: #f44336; }
    
    .document-header {
        background: #f5f5f5;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
    }
    
    .document-type {
        font-weight: bold;
        color: #003366;
    }
    
    .status-badge {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-verified { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    
    .document-body {
        padding: 15px;
    }
    
    .document-body h4 {
        color: #333;
        margin-bottom: 5px;
    }
    
    .document-meta {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }
    
    .document-meta i {
        width: 16px;
        margin-right: 5px;
    }
    
    .document-preview {
        margin: 15px 0;
    }
    
    .document-remarks {
        background: #fff3cd;
        padding: 10px;
        border-radius: 5px;
        font-size: 0.9rem;
        color: #856404;
    }
    
    .document-remarks i {
        margin-right: 5px;
    }
    
    .document-actions {
        padding: 15px;
        background: #f9f9f9;
        border-top: 1px solid #eee;
        text-align: right;
    }
    
    .btn-small {
        display: inline-block;
        padding: 5px 10px;
        background: #003366;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
    }
    
    .btn-small:hover {
        background: #002244;
    }
    
    .btn-primary {
        background: #003366;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-content h2 {
        color: #003366;
        margin-bottom: 20px;
    }
    
    .modal-content h2 i {
        margin-right: 10px;
    }
    
    .readonly-field {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 5px;
        color: #333;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .documents-grid {
            grid-template-columns: 1fr;
        }
        
        .filters-form {
            flex-direction: column;
        }
        
        .quick-nav {
            flex-direction: column;
        }
        
        .quick-nav-btn {
            width: 100%;
            justify-content: center;
        }
        
        .form-actions {
            flex-direction: column;
        }
    }
    </style>
    
    <script>
    function showVerifyModal(document) {
        document.getElementById('modal_document_id').value = document.document_id;
        document.getElementById('modal_document_info').textContent = 
            ucfirst(document.document_type.replace(/_/g, ' '));
        document.getElementById('modal_uploader').textContent = 
            document.first_name + ' ' + document.last_name;
        document.getElementById('modal_document_link').href = '../' + document.document_path;
        document.getElementById('verifyModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('verifyModal').style.display = 'none';
        document.getElementById('remarks_field').style.display = 'none';
        document.getElementById('modal_remarks').value = '';
        document.getElementById('modal_status').value = 'verified';
    }
    
    function toggleRemarksField() {
        const status = document.getElementById('modal_status').value;
        const remarksField = document.getElementById('remarks_field');
        
        if (status === 'rejected') {
            remarksField.style.display = 'block';
            document.getElementById('modal_remarks').required = true;
        } else {
            remarksField.style.display = 'none';
            document.getElementById('modal_remarks').required = false;
        }
    }
    
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('verifyModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
    
    <script src="../js/main.js"></script>
</body>
</html>
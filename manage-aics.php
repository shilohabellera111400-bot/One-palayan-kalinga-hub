<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php'; // Add Mailer

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.email 
          FROM aics_requests a
          JOIN users u ON a.user_id = u.user_id";

$where = [];
$params = [];

if ($status_filter != 'all') {
    $where[] = "a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR a.purpose LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY a.submission_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $aics_id = sanitize($_POST['aics_id']);
    $new_status = sanitize($_POST['new_status']);
    $amount_approved = sanitize($_POST['amount_approved'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Build update query based on status
        $update_query = "UPDATE aics_requests SET 
                          status = :status,
                          amount_approved = :amount";
        
        // Add status-specific fields
        if ($new_status == 'reviewing') {
            $update_query .= ", reviewed_by = :user_id, review_date = NOW()";
        } elseif ($new_status == 'approved') {
            $update_query .= ", approved_by = :user_id, approval_date = NOW()";
        } elseif ($new_status == 'released') {
            $update_query .= ", release_date = NOW()";
        } elseif ($new_status == 'rejected') {
            $update_query .= ", rejection_reason = :remarks";
        }
        
        $update_query .= " WHERE aics_id = :aics_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':amount', $amount_approved);
        
        if ($new_status == 'reviewing' || $new_status == 'approved') {
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        
        if ($new_status == 'rejected') {
            $update_stmt->bindParam(':remarks', $remarks);
        }
        
        $update_stmt->bindParam(':aics_id', $aics_id);
        $update_stmt->execute();
        
        // Get user details for notification and email
        $user_query = "SELECT u.email, u.first_name, u.last_name, a.* 
                       FROM aics_requests a
                       JOIN users u ON a.user_id = u.user_id
                       WHERE a.aics_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $aics_id);
        $user_stmt->execute();
        $aics_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create in-app notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'AICS Status Updated', 
                        CONCAT('Your AICS request status has been updated to: ', :status), 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $aics_details['user_id']);
        $notif_stmt->bindParam(':status', $new_status);
        $notif_stmt->execute();
        
        // Send email notification
        try {
            $mailer = new Mailer();
            $user_name = $aics_details['first_name'] . ' ' . $aics_details['last_name'];
            
            // Add approved amount to details if applicable
            if ($new_status == 'approved') {
                $aics_details['amount_approved'] = $amount_approved;
            }
            
            // Add rejection reason if applicable
            if ($new_status == 'rejected') {
                $aics_details['rejection_reason'] = $remarks;
            }
            
            $email_result = $mailer->sendAICSUpdate(
                $aics_details['email'],
                $user_name,
                $aics_details
            );
            
            if ($email_result['success']) {
                $email_status = "Email notification sent.";
            } else {
                $email_status = "Email sending failed: " . $email_result['message'];
                // Log but don't fail the transaction
                error_log($email_status);
            }
        } catch (Exception $e) {
            $email_status = "Email error: " . $e->getMessage();
            error_log($email_status);
        }
        
        $db->commit();
        
        // Set success message with email status
        $success = "Status updated successfully! " . ($email_status ?? '');
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Get statistics for dashboard
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
    FROM aics_requests";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage AICS - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Simplified navbar for staff pages -->
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
                <li><a href="manage-aics.php" class="active"><i class="fas fa-hand-holding-heart"></i> AICS Requests</a></li>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Housing</a></li>
                <li><a href="verify-documents.php"><i class="fas fa-file-alt"></i> Verify Documents</a></li>
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
                <a href="manage-aics.php">AICS Requests</a>
            </div>
            
            <h1>Manage AICS Requests</h1>
            
            <!-- Status Messages -->
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="filterByStatus('all')">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Requests</p>
                    </div>
                </div>
                <div class="stat-card submitted" onclick="filterByStatus('submitted')">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $stats['submitted']; ?></h3>
                        <p>Submitted</p>
                    </div>
                </div>
                <div class="stat-card reviewing" onclick="filterByStatus('reviewing')">
                    <div class="stat-icon"><i class="fas fa-search"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $stats['reviewing']; ?></h3>
                        <p>Under Review</p>
                    </div>
                </div>
                <div class="stat-card approved" onclick="filterByStatus('approved')">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $stats['approved']; ?></h3>
                        <p>Approved</p>
                    </div>
                </div>
            </div>
            
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
                <a href="manage-housing.php" class="quick-nav-btn">
                    <i class="fas fa-home"></i> Housing
                </a>
                <a href="verify-documents.php" class="quick-nav-btn">
                    <i class="fas fa-file-alt"></i> Documents
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" class="filters-form" id="filterForm">
                    <div class="filter-group">
                        <select name="status" id="statusFilter">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="reviewing" <?php echo $status_filter == 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <input type="text" name="search" placeholder="Search by name or purpose..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <?php if($status_filter != 'all' || !empty($search)): ?>
                        <a href="manage-aics.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Results -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Applicant</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($stmt->rowCount() > 0): ?>
                            <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong>AICS-<?php echo str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?><br>
                                    <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['contact_number']); ?></small><br>
                                    <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></small>
                                </td>
                                <td>
                                    <span class="request-type"><?php echo ucfirst($row['request_type']); ?></span>
                                </td>
                                <td><strong>₱<?php echo number_format($row['amount_requested'], 2); ?></strong></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($row['submission_date'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($row['submission_date'])); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <i class="fas fa-<?php 
                                            echo $row['status'] == 'submitted' ? 'clock' : 
                                                ($row['status'] == 'reviewing' ? 'search' : 
                                                ($row['status'] == 'approved' ? 'check-circle' : 
                                                ($row['status'] == 'released' ? 'gift' : 'times-circle'))); 
                                        ?>"></i>
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="showUpdateModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                            class="btn-small btn-update">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                    <a href="view-aics.php?id=<?php echo $row['aics_id']; ?>" class="btn-small btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 10px;"></i>
                                    <p>No AICS requests found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update AICS Request</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="updateForm">
                <input type="hidden" name="aics_id" id="modal_aics_id">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Applicant</label>
                    <p id="modal_applicant" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Tracking Number</label>
                    <p id="modal_tracking" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label for="modal_status"><i class="fas fa-sync-alt"></i> New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleFields()">
                        <option value="reviewing">🔍 Under Review</option>
                        <option value="approved">✅ Approve</option>
                        <option value="rejected">❌ Reject</option>
                        <option value="released">💰 Release Payment</option>
                    </select>
                </div>
                
                <div class="form-group" id="amount_field" style="display: none;">
                    <label for="amount_approved"><i class="fas fa-money-bill"></i> Approved Amount (₱)</label>
                    <input type="number" name="amount_approved" id="amount_approved" 
                           min="0" step="100" placeholder="Enter approved amount">
                    <small>This will be the final amount released to the beneficiary</small>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="remarks"><i class="fas fa-comment"></i> Rejection Reason / Remarks</label>
                    <textarea name="remarks" id="remarks" rows="3" 
                              placeholder="Enter reason for rejection or additional remarks"></textarea>
                    <small>This will be shared with the applicant</small>
                </div>
                
                <!-- Email Preview -->
                <div class="email-preview" id="emailPreview" style="display: none; background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4><i class="fas fa-envelope"></i> Email Notification Preview</h4>
                    <p id="emailMessage"></p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_status" class="btn-primary">
                        <i class="fas fa-save"></i> Update Status & Notify
                    </button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    /* Additional styles for the enhanced interface */
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
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.3s, box-shadow 0.3s;
        border-left: 4px solid #003366;
    }
    
    .stat-card.submitted { border-left-color: #ff9800; }
    .stat-card.reviewing { border-left-color: #2196F3; }
    .stat-card.approved { border-left-color: #4CAF50; }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    .stat-icon {
        font-size: 2.5rem;
        color: #003366;
    }
    
    .stat-details h3 {
        font-size: 1.8rem;
        color: #003366;
        margin-bottom: 5px;
    }
    
    .stat-details p {
        color: #666;
        font-size: 0.9rem;
    }
    
    .request-type {
        display: inline-block;
        padding: 3px 8px;
        background: #e3f2fd;
        color: #003366;
        border-radius: 3px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .btn-update {
        background: #ff9800;
    }
    
    .btn-update:hover {
        background: #f57c00;
    }
    
    .btn-view {
        background: #003366;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .modal-header h2 {
        color: #003366;
        margin: 0;
    }
    
    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }
    
    .close-btn:hover {
        color: #333;
    }
    
    .email-preview {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
    }
    
    .email-preview h4 {
        color: #003366;
        margin-bottom: 10px;
    }
    
    .email-preview p {
        color: #666;
        font-size: 0.95rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filters-form {
            flex-direction: column;
        }
        
        .search-group {
            flex-direction: column;
        }
        
        .data-table {
            font-size: 0.9rem;
        }
    }
    </style>
    
    <script>
    function showUpdateModal(request) {
        document.getElementById('modal_aics_id').value = request.aics_id;
        document.getElementById('modal_applicant').textContent = 
            request.first_name + ' ' + request.last_name;
        document.getElementById('modal_tracking').textContent = 
            'AICS-' + String(request.aics_id).padStart(6, '0');
        document.getElementById('updateModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('updateModal').style.display = 'none';
        document.getElementById('amount_field').style.display = 'none';
        document.getElementById('remarks_field').style.display = 'none';
        document.getElementById('emailPreview').style.display = 'none';
        document.getElementById('amount_approved').value = '';
        document.getElementById('remarks').value = '';
        document.getElementById('modal_status').value = 'reviewing';
    }
    
    function toggleFields() {
        const status = document.getElementById('modal_status').value;
        const amountField = document.getElementById('amount_field');
        const remarksField = document.getElementById('remarks_field');
        const emailPreview = document.getElementById('emailPreview');
        const emailMessage = document.getElementById('emailMessage');
        
        amountField.style.display = 'none';
        remarksField.style.display = 'none';
        emailPreview.style.display = 'block';
        
        if (status === 'approved') {
            amountField.style.display = 'block';
            document.getElementById('amount_approved').required = true;
            emailMessage.innerHTML = '✅ Email will be sent notifying the applicant of APPROVAL. ' +
                                   'They will be able to see the approved amount.';
        } else if (status === 'rejected') {
            remarksField.style.display = 'block';
            document.getElementById('remarks').required = true;
            emailMessage.innerHTML = '❌ Email will be sent notifying the applicant of REJECTION. ' +
                                   'Your remarks will be included as the reason.';
        } else if (status === 'reviewing') {
            emailMessage.innerHTML = '🔍 Email will be sent notifying the applicant that their request is UNDER REVIEW.';
        } else if (status === 'released') {
            emailMessage.innerHTML = '💰 Email will be sent notifying the applicant that their payment has been RELEASED.';
        }
    }
    
    function filterByStatus(status) {
        document.getElementById('statusFilter').value = status;
        document.getElementById('filterForm').submit();
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('updateModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
    
    <script src="../js/main.js"></script>
</body>
</html>
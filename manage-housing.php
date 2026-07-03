<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$program_filter = isset($_GET['program']) ? sanitize($_GET['program']) : 'all';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $beneficiary_id = sanitize($_POST['beneficiary_id']);
    $new_status = sanitize($_POST['new_status']);
    $unit_assigned = sanitize($_POST['unit_assigned'] ?? '');
    $interview_date = sanitize($_POST['interview_date'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $update_query = "UPDATE housing_beneficiaries SET 
                      status = :status,
                      unit_assigned = :unit,
                      interview_date = :interview_date";
    
    if ($new_status == 'approved') {
        $update_query .= ", approved_by = :user_id, approval_date = NOW()";
    } elseif ($new_status == 'rejected') {
        $update_query .= ", housing_need_assessment = CONCAT(housing_need_assessment, '\nRejection Reason: ', :remarks)";
    }
    
    $update_query .= " WHERE beneficiary_id = :id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':unit', $unit_assigned);
    $update_stmt->bindParam(':interview_date', $interview_date);
    
    if ($new_status == 'approved') {
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    } elseif ($new_status == 'rejected') {
        $update_stmt->bindParam(':remarks', $remarks);
    }
    
    $update_stmt->bindParam(':id', $beneficiary_id);
    
    if ($update_stmt->execute()) {
        // Get full housing + user details for email and notification
        $user_query = "SELECT h.*, p.program_name, u.user_id, u.email, u.first_name, u.last_name
                       FROM housing_beneficiaries h
                       JOIN housing_programs p ON h.program_id = p.program_id
                       JOIN users u ON h.user_id = u.user_id
                       WHERE h.beneficiary_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $beneficiary_id);
        $user_stmt->execute();
        $housing_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if ($housing_details) {
            // Create in-app notification
            $notif_msg = "Your housing application for {$housing_details['program_name']} has been updated to: " . ucfirst($new_status);
            $notif_query = "INSERT INTO notifications (user_id, title, message, type)
                            VALUES (:user_id, 'Housing Application Updated', :msg, 'housing')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $housing_details['user_id']);
            $notif_stmt->bindParam(':msg', $notif_msg);
            $notif_stmt->execute();

            // Send email notification
            try {
                $mailer = new Mailer();
                $user_name = $housing_details['first_name'] . ' ' . $housing_details['last_name'];
                // Attach current unit assignment to details
                $housing_details['unit_assigned'] = $unit_assigned;
                $email_result = $mailer->sendHousingUpdate(
                    $housing_details['email'],
                    $user_name,
                    $housing_details
                );
                $email_status = $email_result['success']
                    ? 'Email notification sent.'
                    : 'Email failed: ' . $email_result['message'];
                if (!$email_result['success']) error_log($email_status);
            } catch (Exception $e) {
                error_log('Housing email error: ' . $e->getMessage());
            }
        }

        $success = 'Application status updated successfully! ' . ($email_status ?? '');
    }
}

// Get all housing programs for filter
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->query($programs_query);

// Build main query
$query = "SELECT h.*, p.program_name, p.location, u.first_name, u.last_name, u.contact_number, u.email
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($program_filter != 'all') {
    $query .= " AND h.program_id = :program";
    $params[':program'] = $program_filter;
}

if ($status_filter != 'all') {
    $query .= " AND h.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY h.application_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
    FROM housing_beneficiaries";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Housing - Staff Dashboard</title>
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
                <li><a href="manage-housing.php" class="active"><i class="fas fa-home"></i> Housing</a></li>
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
                <a href="manage-housing.php">Housing Applications</a>
            </div>
            
            <h1>Manage Housing Applications</h1>
            
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
            </div>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card qualified">
                    <div class="stat-value"><?php echo $stats['qualified']; ?></div>
                    <div class="stat-label">Qualified</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-value"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <select name="program">
                            <option value="all">All Programs</option>
                            <?php 
                            // Reset pointer for programs query
                            $programs_stmt->execute();
                            while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                            <option value="<?php echo $program['program_id']; ?>" 
                                <?php echo $program_filter == $program['program_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status">
                            <option value="all">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="qualified" <?php echo $status_filter == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <input type="text" name="search" placeholder="Search by name or contact..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-primary">Filter</button>
                        <?php if($program_filter != 'all' || $status_filter != 'all' || !empty($search)): ?>
                        <a href="manage-housing.php" class="btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Applications Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Applicant</th>
                            <th>Program</th>
                            <th>Household</th>
                            <th>Income</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td>#<?php echo $app['beneficiary_id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?><br>
                                <small><?php echo htmlspecialchars($app['contact_number']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['program_name']); ?></td>
                            <td><?php echo $app['household_composition']; ?> persons</td>
                            <td>₱<?php echo number_format($app['monthly_income'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="showUpdateModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                                        class="btn-small"><i class="fas fa-edit"></i> Update</button>
                                <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="btn-small"><i class="fas fa-eye"></i> View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2><i class="fas fa-edit"></i> Update Housing Application</h2>
            <form method="POST" id="updateForm">
                <input type="hidden" name="beneficiary_id" id="modal_beneficiary_id">
                
                <div class="form-group">
                    <label>Applicant</label>
                    <p id="modal_applicant" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label>Program</label>
                    <p id="modal_program" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleHousingFields()">
                        <option value="qualified">Mark as Qualified</option>
                        <option value="approved">Approve & Assign Unit</option>
                        <option value="rejected">Reject</option>
                        <option value="released">Mark as Released</option>
                        <option value="pending">Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group" id="unit_field" style="display: none;">
                    <label for="unit_assigned">Unit Number</label>
                    <input type="text" name="unit_assigned" id="unit_assigned" 
                           placeholder="e.g., Block 1, Lot 5">
                </div>
                
                <div class="form-group" id="interview_field" style="display: none;">
                    <label for="interview_date">Interview Date</label>
                    <input type="date" name="interview_date" id="interview_date"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="remarks">Rejection Reason / Remarks</label>
                    <textarea name="remarks" id="remarks" rows="3" 
                              placeholder="Enter reason for rejection..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_status" class="btn-primary"><i class="fas fa-save"></i> Update Status</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary"><i class="fas fa-times"></i> Cancel</button>
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
    .stat-card.qualified { border-left-color: #2196F3; }
    .stat-card.approved { border-left-color: #4CAF50; }
    
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
    
    .table-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f5f5f5;
        padding: 12px;
        text-align: left;
        color: #333;
        font-weight: 600;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .status-pending { background: #fff3cd; color: #856404; }
    .status-qualified { background: #cce5ff; color: #004085; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    .status-released { background: #d1ecf1; color: #0c5460; }
    
    .btn-small {
        display: inline-block;
        padding: 5px 10px;
        background: #003366;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 0.9rem;
        margin: 0 2px;
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
    
    .readonly-field {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 5px;
        color: #333;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
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
        
        .data-table {
            font-size: 0.9rem;
        }
        
        .data-table td, 
        .data-table th {
            padding: 8px;
        }
    }
    </style>
    
    <script>
    function showUpdateModal(application) {
        document.getElementById('modal_beneficiary_id').value = application.beneficiary_id;
        document.getElementById('modal_applicant').textContent = 
            application.first_name + ' ' + application.last_name;
        document.getElementById('modal_program').textContent = application.program_name;
        document.getElementById('updateModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('updateModal').style.display = 'none';
        document.getElementById('unit_field').style.display = 'none';
        document.getElementById('interview_field').style.display = 'none';
        document.getElementById('remarks_field').style.display = 'none';
        document.getElementById('unit_assigned').value = '';
        document.getElementById('interview_date').value = '';
        document.getElementById('remarks').value = '';
    }
    
    function toggleHousingFields() {
        const status = document.getElementById('modal_status').value;
        const unitField = document.getElementById('unit_field');
        const interviewField = document.getElementById('interview_field');
        const remarksField = document.getElementById('remarks_field');
        
        unitField.style.display = 'none';
        interviewField.style.display = 'none';
        remarksField.style.display = 'none';
        
        if (status === 'approved') {
            unitField.style.display = 'block';
            interviewField.style.display = 'block';
            document.getElementById('unit_assigned').required = true;
            document.getElementById('interview_date').required = true;
        } else if (status === 'qualified') {
            interviewField.style.display = 'block';
            document.getElementById('interview_date').required = true;
        } else if (status === 'rejected') {
            remarksField.style.display = 'block';
            document.getElementById('remarks').required = true;
        }
    }
    
    // Close modal when clicking outside
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
<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get AICS ID from URL
$aics_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$aics_id) {
    redirect('manage-aics.php');
}

// Fetch main AICS request details
$query = "SELECT a.*, 
          u.first_name, u.last_name, u.middle_name, u.email, u.contact_number, 
          u.street, u.barangay, u.city, u.province, u.postal_code,
          CONCAT(u.first_name, ' ', u.last_name) as full_name
          FROM aics_requests a
          JOIN users u ON a.user_id = u.user_id
          WHERE a.aics_id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $aics_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header('Location: manage-aics.php?error=Request not found');
    exit;
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch specific details based on request type
$specific_details = null;
$detail_table = '';
$detail_columns = [];

switch($request['request_type']) {
    case 'cash_relief':
        $detail_query = "SELECT * FROM aics_cash_relief_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Cash Relief Details';
        break;
        
    case 'educational':
        $detail_query = "SELECT * FROM aics_educational_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Educational Assistance Details';
        break;
        
    case 'food':
        $detail_query = "SELECT * FROM aics_food_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Food Assistance Details';
        break;
        
    case 'funeral':
        $detail_query = "SELECT * FROM aics_funeral_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Funeral Assistance Details';
        break;
        
    case 'material':
        $detail_query = "SELECT * FROM aics_material_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Material Assistance Details';
        break;
        
    case 'medical':
        $detail_query = "SELECT * FROM aics_medical_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Medical Assistance Details';
        break;
        
    case 'transportation':
        $detail_query = "SELECT * FROM aics_transportation_details WHERE aics_id = :id";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':id', $aics_id);
        $detail_stmt->execute();
        $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        $detail_table = 'Transportation Assistance Details';
        break;
}

// Fetch supporting documents
$docs_query = "SELECT * FROM aics_documents WHERE aics_id = :id ORDER BY upload_date DESC";
$docs_stmt = $db->prepare($docs_query);
$docs_stmt->bindParam(':id', $aics_id);
$docs_stmt->execute();
$documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff who processed this request
$staff_info = [];
if ($request['reviewed_by']) {
    $staff_query = "SELECT s.first_name, s.last_name, s.position FROM staff s WHERE s.user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $request['reviewed_by']);
    $staff_stmt->execute();
    $staff_info['reviewer'] = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

if ($request['approved_by']) {
    $staff_query = "SELECT s.first_name, s.last_name, s.position FROM staff s WHERE s.user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $request['approved_by']);
    $staff_stmt->execute();
    $staff_info['approver'] = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get status color and icon
function getStatusInfo($status) {
    $statuses = [
        'submitted' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Submitted', 'bg' => '#fff3e0'],
        'reviewing' => ['color' => '#2196F3', 'icon' => 'fa-search', 'text' => 'Under Review', 'bg' => '#e3f2fd'],
        'approved' => ['color' => '#4CAF50', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => '#e8f5e9'],
        'rejected' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Rejected', 'bg' => '#ffebee'],
        'released' => ['color' => '#9C27B0', 'icon' => 'fa-gift', 'text' => 'Released', 'bg' => '#f3e5f5']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}

// Helper function to format field names for display
function formatFieldName($field) {
    $mapping = [
        'emergency_type' => 'Type of Emergency',
        'affected_members' => 'Affected Family Members',
        'incident_date' => 'Incident Date',
        'incident_report_no' => 'Incident Report Number',
        'police_report_no' => 'Police Report Number',
        'student_name' => 'Student Name',
        'school_name' => 'School Name',
        'grade_level' => 'Grade/Year Level',
        'school_year' => 'School Year',
        'tuition_fee' => 'Tuition Fee',
        'enrollment_status' => 'Enrollment Status',
        'household_size' => 'Household Size',
        'monthly_income' => 'Monthly Income',
        'special_needs' => 'Special Needs / Dietary Restrictions',
        'assistance_type' => 'Assistance Type',
        'deceased_name' => 'Name of Deceased',
        'relationship' => 'Relationship to Deceased',
        'funeral_home' => 'Funeral Home',
        'death_certificate_no' => 'Death Certificate Number',
        'funeral_contract_no' => 'Funeral Contract Number',
        'transfer_permit_no' => 'Transfer Permit Number',
        'client_type' => 'Client Type',
        'group_name' => 'Group/Organization Name',
        'number_of_members' => 'Number of Members',
        'materials_requested' => 'Materials Requested',
        'estimated_value' => 'Estimated Value',
        'hospital_name' => 'Hospital Name',
        'doctor_name' => 'Doctor Name',
        'destination' => 'Destination',
        'travel_date' => 'Travel Date',
        'number_of_persons' => 'Number of Persons',
        'estimated_fare' => 'Estimated Fare',
        'reason_type' => 'Reason for Travel',
        'purpose' => 'Purpose / Explanation'
    ];
    return $mapping[$field] ?? ucwords(str_replace('_', ' ', $field));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View AICS Request #<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?> - Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }

        .navbar { background: #003366; color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 1000; }
        .navbar .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .nav-brand h1 { font-size: 1.5rem; margin-bottom: 5px; }
        .nav-brand .subtitle { font-size: 0.8rem; opacity: 0.9; }
        .nav-menu { list-style: none; display: flex; gap: 20px; }
        .nav-menu a { color: white; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .dropdown-menu { position: absolute; background: white; color: #333; min-width: 180px; border-radius: 5px; display: none; }
        .dropdown:hover .dropdown-menu { display: block; }

        .dashboard-container { display: flex; min-height: calc(100vh - 80px); }
        .sidebar { width: 280px; background: #2c3e50; color: white; position: sticky; top: 80px; height: calc(100vh - 80px); overflow-y: auto; }
        .user-profile { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu { list-style: none; padding: 20px 0; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #ecf0f1; text-decoration: none; transition: all 0.3s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: #34495e; border-left: 3px solid #ff9800; }
        .sidebar-menu li a i { width: 20px; color: #ff9800; }
        .menu-divider { border-top: 1px solid rgba(255,255,255,0.1); margin: 10px 0; padding-top: 10px; padding-left: 20px; font-size: 0.75rem; color: #7f8c8d; }

        .main-content { flex: 1; padding: 30px; background: #f5f7fb; }
        .breadcrumb { margin-bottom: 20px; color: #666; }
        .breadcrumb a { color: #003366; text-decoration: none; }

        .request-header { background: white; border-radius: 16px; padding: 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .request-title h1 { color: #003366; margin-bottom: 8px; }
        .tracking-number { color: #666; font-size: 0.9rem; }
        .status-badge-large { display: inline-flex; align-items: center; gap: 10px; padding: 10px 20px; border-radius: 50px; font-weight: 600; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px; }
        .info-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .info-card h3 { color: #003366; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #ff9800; display: flex; align-items: center; gap: 10px; }
        .info-row { display: flex; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .info-label { width: 160px; font-weight: 600; color: #666; }
        .info-value { flex: 1; color: #333; }

        .documents-list { display: grid; gap: 12px; }
        .document-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px; }
        .document-name { display: flex; align-items: center; gap: 10px; }
        .btn-download { background: #003366; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; }
        .btn-download:hover { background: #002244; }

        .action-buttons { display: flex; gap: 12px; margin-top: 24px; }
        .btn-primary, .btn-secondary { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #003366; color: white; }
        .btn-primary:hover { background: #002244; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-print { background: #17a2b8; }

        .request-type-badge { display: inline-block; padding: 4px 12px; background: #e3f2fd; color: #003366; border-radius: 20px; font-size: 0.85rem; font-weight: 500; margin-left: 10px; }

        @media print {
            .sidebar, .navbar, .action-buttons, .breadcrumb { display: none; }
            .main-content { padding: 0; margin: 0; }
            .info-card { break-inside: avoid; }
        }

        @media (max-width: 768px) {
            .dashboard-container { flex-direction: column; }
            .sidebar { width: 100%; position: static; height: auto; }
            .info-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
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
            <div class="breadcrumb">
                <a href="../staff-dashboard.php">Dashboard</a> > 
                <a href="manage-aics.php">AICS Requests</a> > 
                View Request #<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?>
            </div>
            
            <?php $statusInfo = getStatusInfo($request['status']); ?>
            <div class="request-header">
                <div class="request-title">
                    <h1>
                        <i class="fas fa-hand-holding-heart"></i> 
                        <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?> Assistance
                        <span class="request-type-badge">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($request['request_type']); ?>
                        </span>
                    </h1>
                    <div class="tracking-number">
                        <strong>Tracking Number:</strong> AICS-<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>">
                        <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                        <?php echo $statusInfo['text']; ?>
                    </span>
                </div>
            </div>
            
            <div class="info-grid">
                <!-- Applicant Information -->
                <div class="info-card">
                    <h3><i class="fas fa-user-circle"></i> Applicant Information</h3>
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['full_name']); ?></div>
                    </div>
                    <?php if(!empty($request['middle_name'])): ?>
                    <div class="info-row">
                        <div class="info-label">Middle Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['middle_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">Contact Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['contact_number']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['email']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Address:</div>
                        <div class="info-value">
                            <?php 
                            $address = [];
                            if(!empty($request['street'])) $address[] = $request['street'];
                            if(!empty($request['barangay'])) $address[] = $request['barangay'];
                            if(!empty($request['city'])) $address[] = $request['city'];
                            if(!empty($request['province'])) $address[] = $request['province'];
                            echo htmlspecialchars(implode(', ', $address));
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Request Summary -->
                <div class="info-card">
                    <h3><i class="fas fa-file-alt"></i> Request Summary</h3>
                    <div class="info-row">
                        <div class="info-label">Request Type:</div>
                        <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Amount Requested:</div>
                        <div class="info-value"><strong>₱<?php echo number_format($request['amount_requested'], 2); ?></strong></div>
                    </div>
                    <?php if($request['amount_approved'] > 0): ?>
                    <div class="info-row">
                        <div class="info-label">Amount Approved:</div>
                        <div class="info-value"><strong style="color: #4CAF50;">₱<?php echo number_format($request['amount_approved'], 2); ?></strong></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">Submission Date:</div>
                        <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($request['submission_date'])); ?></div>
                    </div>
                </div>
                
                <!-- Specific Details based on request type -->
                <?php if($specific_details): ?>
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> <?php echo $detail_table; ?></h3>
                    <?php foreach($specific_details as $key => $value): ?>
                        <?php if($key != 'id' && $key != 'aics_id' && !empty($value)): ?>
                        <div class="info-row">
                            <div class="info-label"><?php echo formatFieldName($key); ?>:</div>
                            <div class="info-value">
                                <?php 
                                if($key == 'amount_requested' || $key == 'estimated_value' || $key == 'tuition_fee' || $key == 'monthly_income' || $key == 'estimated_fare') {
                                    echo '₱' . number_format($value, 2);
                                } elseif($key == 'travel_date' || $key == 'incident_date') {
                                    echo date('F d, Y', strtotime($value));
                                } else {
                                    echo nl2br(htmlspecialchars($value));
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Processing Information -->
                <div class="info-card">
                    <h3><i class="fas fa-clipboard-list"></i> Processing Information</h3>
                    <div class="info-row">
                        <div class="info-label">Current Status:</div>
                        <div class="info-value">
                            <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 12px; font-size: 0.85rem;">
                                <?php echo $statusInfo['text']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if($request['reviewed_by'] && isset($staff_info['reviewer'])): ?>
                    <div class="info-row">
                        <div class="info-label">Reviewed By:</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff_info['reviewer']['first_name'] . ' ' . $staff_info['reviewer']['last_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Review Date:</div>
                        <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($request['review_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($request['approved_by'] && isset($staff_info['approver'])): ?>
                    <div class="info-row">
                        <div class="info-label">Approved By:</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff_info['approver']['first_name'] . ' ' . $staff_info['approver']['last_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Approval Date:</div>
                        <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($request['approval_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($request['release_date']): ?>
                    <div class="info-row">
                        <div class="info-label">Release Date:</div>
                        <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($request['release_date'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($request['rejection_reason']): ?>
                    <div class="info-row">
                        <div class="info-label">Rejection Reason:</div>
                        <div class="info-value" style="color: #f44336;"><?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Supporting Documents -->
                <div class="info-card">
                    <h3><i class="fas fa-paperclip"></i> Supporting Documents</h3>
                    <?php if(count($documents) > 0): ?>
                        <div class="documents-list">
                            <?php foreach($documents as $doc): ?>
                            <div class="document-item">
                                <div class="document-name">
                                    <i class="fas fa-file-<?php 
                                        $ext = pathinfo($doc['document_path'] ?? $doc['file_name'] ?? '', PATHINFO_EXTENSION);
                                        echo $ext == 'pdf' ? 'pdf' : ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' ? 'image' : 'alt');
                                    ?>"></i>
                                    <?php echo htmlspecialchars($doc['document_type'] ?? $doc['document_name'] ?? 'Document'); ?>
                                </div>
                                <a href="../uploads/aics/<?php echo $doc['document_path'] ?? $doc['file_path']; ?>" class="btn-download" target="_blank">
                                    <i class="fas fa-download"></i> View
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <i class="fas fa-folder-open"></i> No documents uploaded
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="manage-aics.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <?php if($request['status'] != 'released' && $request['status'] != 'rejected'): ?>
                <button onclick="showUpdateModal()" class="btn-primary">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                <?php endif; ?>
                <button onclick="window.print()" class="btn-primary btn-print">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 20px; width: 90%; max-width: 550px; max-height: 90vh; overflow-y: auto; animation: slideIn 0.3s ease;">
            <div style="padding: 20px 25px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="color: #003366;"><i class="fas fa-edit"></i> Update Status</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST" action="manage-aics.php">
                <div style="padding: 25px;">
                    <input type="hidden" name="aics_id" value="<?php echo $aics_id; ?>">
                    
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #6c757d;">Applicant:</span>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($request['full_name']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6c757d;">Tracking Number:</span>
                            <span style="font-weight: 500;">AICS-<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">New Status</label>
                        <select name="new_status" id="modal_status" required onchange="toggleFields()" style="width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 10px;">
                            <option value="reviewing">🔍 Under Review</option>
                            <option value="approved">✅ Approve</option>
                            <option value="rejected">❌ Reject</option>
                            <option value="released">💰 Release Payment</option>
                        </select>
                    </div>
                    
                    <div id="amount_field" style="display: none; margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Approved Amount (₱)</label>
                        <input type="number" name="amount_approved" id="amount_approved" min="0" step="100" placeholder="Enter approved amount" style="width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 10px;">
                    </div>
                    
                    <div id="remarks_field" style="display: none; margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rejection Reason</label>
                        <textarea name="remarks" id="remarks" rows="3" placeholder="Enter reason for rejection" style="width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 10px;"></textarea>
                    </div>
                    
                    <div id="emailPreview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 12px; margin-top: 15px;">
                        <h4 style="margin-bottom: 10px;"><i class="fas fa-envelope"></i> Email Preview</h4>
                        <p id="emailMessage" style="color: #6c757d; font-size: 13px;"></p>
                    </div>
                </div>
                
                <div style="padding: 15px 25px; border-top: 1px solid #e9ecef; display: flex; gap: 12px;">
                    <button type="submit" name="update_status" class="btn-primary" style="padding: 10px 20px;">Update & Notify</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
    
    <script>
        function showUpdateModal() {
            document.getElementById('updateModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('updateModal').style.display = 'none';
            document.getElementById('amount_field').style.display = 'none';
            document.getElementById('remarks_field').style.display = 'none';
            document.getElementById('emailPreview').style.display = 'none';
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
                emailMessage.innerHTML = '✅ Email will be sent notifying the applicant of APPROVAL.';
            } else if (status === 'rejected') {
                remarksField.style.display = 'block';
                document.getElementById('remarks').required = true;
                emailMessage.innerHTML = '❌ Email will be sent notifying the applicant of REJECTION.';
            } else if (status === 'reviewing') {
                emailMessage.innerHTML = '🔍 Email will be sent notifying the applicant that their request is UNDER REVIEW.';
            } else if (status === 'released') {
                emailMessage.innerHTML = '💰 Email will be sent notifying the applicant that their payment has been RELEASED.';
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Get housing application ID from URL
$application_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$application_id) {
    redirect('my-housing.php');
}

// First, check what columns exist in users table
$check_columns = $db->query("SHOW COLUMNS FROM users");
$user_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $user_columns[] = $col['Field'];
}

// Build user fields dynamically
$user_fields = "u.first_name, u.last_name, u.email, u.contact_number";
if(in_array('middle_name', $user_columns)) {
    $user_fields .= ", u.middle_name";
}
if(in_array('street', $user_columns)) {
    $user_fields .= ", u.street";
}
if(in_array('barangay', $user_columns)) {
    $user_fields .= ", u.barangay";
}
if(in_array('city', $user_columns)) {
    $user_fields .= ", u.city";
}
if(in_array('province', $user_columns)) {
    $user_fields .= ", u.province";
}
if(in_array('postal_code', $user_columns)) {
    $user_fields .= ", u.postal_code";
}

// Fetch housing application details for the logged-in user
$query = "SELECT h.*, 
          p.program_name, p.location, p.description as program_description, p.total_units,
          $user_fields,
          s.first_name as staff_first_name, s.last_name as staff_last_name, s.position as staff_position
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          LEFT JOIN staff s ON h.approved_by = s.user_id
          WHERE h.beneficiary_id = :id AND h.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $application_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('my-housing.php?error=Application not found');
}

$application = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch documents for this application
$doc_query = "SELECT * FROM documents 
              WHERE user_id = :user_id 
              ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':user_id', $_SESSION['user_id']);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to clean document path
function cleanDocumentPath($path) {
    if (empty($path)) {
        return '';
    }
    $filename = basename($path);
    return 'uploads/' . $filename;
}

// Get status color and icon
function getHousingStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => '#fff3e0'],
        'qualified' => ['color' => '#2196F3', 'icon' => 'fa-search', 'text' => 'Qualified', 'bg' => '#e3f2fd'],
        'approved' => ['color' => '#4CAF50', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => '#e8f5e9'],
        'rejected' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Rejected', 'bg' => '#ffebee'],
        'released' => ['color' => '#9C27B0', 'icon' => 'fa-gift', 'text' => 'Released', 'bg' => '#f3e5f5']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Housing Application - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-100: #F1F3F8;
            --gray-200: #E8ECF2;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
        }

        /* ========== LAYOUT ========== */
        .app-container { display: flex; min-height: 100vh; background: var(--gray-50); }

        /* ========== SIDEBAR - MAROON THEME ========== */
        .sidebar {
            width: 280px;
            background: var(--maroon);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }
        .logo-text h2 {
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .logo-text p {
            color: rgba(255,255,255,0.65);
            font-size: 0.65rem;
            margin-top: 2px;
        }

        .user-info-sidebar {
            padding: 0 20px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        .user-info-sidebar h4 {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .user-info-sidebar span {
            color: rgba(255,255,255,0.6);
            font-size: 0.7rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 8px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 22px; font-size: 1.1rem; color: rgba(255,255,255,0.7); }
        .sidebar-menu li a:hover { background: rgba(255,255,255,0.12); color: white; }
        .sidebar-menu li a.active {
            background: var(--maroon-light);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .sidebar-menu li a.active i { color: white; }

        .sidebar-footer {
            padding: 20px 16px 30px 16px;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            background: rgba(255,255,255,0.08);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.18); color: white; }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 28px 36px;
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--maroon);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Application Header */
        .app-header {
            background: white;
            border-radius: var(--radius-card);
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .app-title h1 {
            color: var(--midnight);
            margin-bottom: 8px;
            font-size: 1.5rem;
        }
        .app-id {
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .info-card h3 {
            color: var(--maroon);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--alabaster);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 160px;
            font-weight: 600;
            color: var(--gray-600);
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: var(--gray-700);
        }

        /* Documents Section */
        .documents-list {
            display: grid;
            gap: 12px;
        }
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--gray-50);
            border-radius: 12px;
            transition: all 0.3s;
        }
        .document-item:hover {
            background: var(--gray-100);
        }
        .document-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-download {
            background: var(--maroon);
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        .btn-download:hover {
            background: var(--maroon-light);
        }

        /* Housing Need Section */
        .housing-need {
            background: var(--gray-50);
            border-radius: var(--radius-element);
            padding: 16px;
            margin-top: 16px;
        }
        .housing-need h4 {
            color: var(--midnight);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .housing-need-content {
            color: var(--gray-600);
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        .btn-print {
            background: #17a2b8;
            color: white;
        }
        .btn-print:hover {
            background: #138496;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray-600);
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .sidebar { width: 250px; }
            .main-content { margin-left: 250px; padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
            .app-header { flex-direction: column; gap: 15px; text-align: center; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 5px; }
            .action-buttons { flex-direction: column; }
        }
        @media print {
            .sidebar, .navbar, .action-buttons, .breadcrumb { display: none; }
            .main-content { margin-left: 0; padding: 0; }
            .info-card { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar - Maroon Theme -->
    <div class="sidebar">
        <div>
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-home"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga<br>Hub</h2>
                    <p>Integrated Service Portal</p>
                </div>
            </div>
            
            <div class="user-info-sidebar">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <span>Resident</span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
                <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
                <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
                <li><a href="my-housing.php" class="active"><i class="fas fa-building"></i> My Housing</a></li>
                <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > 
            <a href="my-housing.php">My Housing</a> > 
            View Application #<?php echo $application_id; ?>
        </div>
        
        <?php $statusInfo = getHousingStatusInfo($application['status']); ?>
        
        <!-- Application Header -->
        <div class="app-header">
            <div class="app-title">
                <h1><i class="fas fa-home"></i> Housing Application Details</h1>
                <div class="app-id">
                    <strong>Application ID:</strong> #<?php echo $application_id; ?>
                </div>
            </div>
            <div>
                <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>">
                    <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                    <?php echo $statusInfo['text']; ?>
                </span>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <!-- Housing Program Details -->
            <div class="info-card">
                <h3><i class="fas fa-building"></i> Housing Program</h3>
                <div class="info-row">
                    <div class="info-label">Program Name:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($application['program_name']); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Location:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['location']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Units:</div>
                    <div class="info-value"><?php echo $application['total_units']; ?> units</div>
                </div>
                <?php if(!empty($application['unit_assigned'])): ?>
                <div class="info-row">
                    <div class="info-label">Unit Assigned:</div>
                    <div class="info-value"><strong style="color: #4CAF50;"><?php echo htmlspecialchars($application['unit_assigned']); ?></strong></div>
                </div>
                <?php endif; ?>
                <?php if(!empty($application['program_description'])): ?>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($application['program_description'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Your Information -->
            <div class="info-card">
                <h3><i class="fas fa-user-circle"></i> Your Information</h3>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                </div>
                <?php if(isset($application['middle_name']) && !empty($application['middle_name'])): ?>
                <div class="info-row">
                    <div class="info-label">Middle Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['middle_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Contact Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['contact_number']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['email']); ?></div>
                </div>
                <?php if(isset($application['street']) || isset($application['barangay'])): ?>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">
                        <?php 
                        $address = [];
                        if(isset($application['street']) && !empty($application['street'])) $address[] = $application['street'];
                        if(isset($application['barangay']) && !empty($application['barangay'])) $address[] = $application['barangay'];
                        if(isset($application['city']) && !empty($application['city'])) $address[] = $application['city'];
                        if(isset($application['province']) && !empty($application['province'])) $address[] = $application['province'];
                        echo htmlspecialchars(implode(', ', $address));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Application Details -->
            <div class="info-card">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <div class="info-row">
                    <div class="info-label">Household Size:</div>
                    <div class="info-value"><?php echo $application['household_composition']; ?> persons</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Monthly Income:</div>
                    <div class="info-value">₱<?php echo number_format($application['monthly_income'], 2); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Application Date:</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($application['application_date'])); ?></div>
                </div>
                <?php if(!empty($application['interview_date'])): ?>
                <div class="info-row">
                    <div class="info-label">Interview Date:</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($application['interview_date'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Processing Information -->
            <div class="info-card">
                <h3><i class="fas fa-clipboard-list"></i> Processing Information</h3>
                <div class="info-row">
                    <div class="info-label">Current Status:</div>
                    <div class="info-value">
                        <span style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                </div>
                <?php if(!empty($application['approved_by']) && !empty($application['staff_first_name'])): ?>
                <div class="info-row">
                    <div class="info-label">Processed By:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['staff_first_name'] . ' ' . $application['staff_last_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Position:</div>
                    <div class="info-value"><?php echo htmlspecialchars($application['staff_position']); ?></div>
                </div>
                <?php endif; ?>
                <?php if(!empty($application['approval_date'])): ?>
                <div class="info-row">
                    <div class="info-label">Approval Date:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($application['approval_date'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Housing Need Assessment -->
        <?php if(!empty($application['housing_need_assessment'])): ?>
        <div class="info-card" style="margin-bottom: 24px;">
            <h3><i class="fas fa-clipboard-list"></i> Housing Need Assessment</h3>
            <div class="housing-need">
                <div class="housing-need-content">
                    <?php echo nl2br(htmlspecialchars($application['housing_need_assessment'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Supporting Documents -->
        <div class="info-card" style="margin-bottom: 24px;">
            <h3><i class="fas fa-paperclip"></i> Supporting Documents</h3>
            <?php if(count($documents) > 0): ?>
                <div class="documents-list">
                    <?php foreach($documents as $doc): ?>
                    <?php 
                    $doc_path = cleanDocumentPath($doc['document_path'] ?? $doc['file_path'] ?? '');
                    $doc_name = $doc['document_type'] ?? $doc['document_name'] ?? 'Document';
                    $file_ext = pathinfo($doc['document_path'] ?? $doc['file_name'] ?? '', PATHINFO_EXTENSION);
                    ?>
                    <div class="document-item">
                        <div class="document-name">
                            <i class="fas fa-file-<?php 
                                echo $file_ext == 'pdf' ? 'pdf' : ($file_ext == 'jpg' || $file_ext == 'jpeg' || $file_ext == 'png' ? 'image' : 'alt');
                            ?>"></i>
                            <?php echo htmlspecialchars($doc_name); ?>
                        </div>
                        <a href="<?php echo $doc_path; ?>" class="btn-download" target="_blank">
                            <i class="fas fa-download"></i> View
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No documents attached to this application</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="my-housing.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Housing
            </a>
            <button onclick="window.print()" class="btn-primary btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</div>
</body>
</html>
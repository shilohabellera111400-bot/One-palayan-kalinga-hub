<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$appointment_id) {
    redirect('manage-appointments.php');
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

// Fetch appointment details
$query = "SELECT a.*, s.service_name, s.description as service_description, s.duration_minutes,
          $user_fields
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          JOIN users u ON a.user_id = u.user_id
          WHERE a.appointment_id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $appointment_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('manage-appointments.php?error=Appointment not found');
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get document attachments for this appointment
$doc_query = "SELECT * FROM documents WHERE appointment_id = :id ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':id', $appointment_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff who processed this appointment (if any)
$staff_info = [];
if ($appointment['processed_by']) {
    $staff_query = "SELECT s.first_name, s.last_name, s.position 
                    FROM staff s 
                    WHERE s.user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $appointment['processed_by']);
    $staff_stmt->execute();
    $staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Get status color and icon
function getAppointmentStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => '#fff3e0'],
        'confirmed' => ['color' => '#2196F3', 'icon' => 'fa-check-circle', 'text' => 'Confirmed', 'bg' => '#e3f2fd'],
        'completed' => ['color' => '#4CAF50', 'icon' => 'fa-check-double', 'text' => 'Completed', 'bg' => '#e8f5e9'],
        'cancelled' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Cancelled', 'bg' => '#ffebee']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment #<?php echo $appointment_id; ?> - Staff</title>
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
            --gray-50: #E8ECF2;
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
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .logo-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.6rem;
        }
        .logo-text h2 {
            color: white;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
        .logo-text p {
            color: rgba(255,255,255,0.65);
            font-size: 0.7rem;
            margin-top: 4px;
        }

        .staff-profile {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 16px;
        }
        .staff-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--tan), var(--light-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--maroon);
        }
        .staff-profile h4 {
            color: white;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 4px; }
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
        .menu-divider {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
            padding-top: 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            padding-left: 16px;
        }

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
            padding: 32px 40px;
            min-height: 100vh;
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

        /* Appointment Header */
        .appointment-header {
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
        .appointment-title h1 {
            color: var(--midnight);
            margin-bottom: 8px;
            font-size: 1.5rem;
        }
        .appointment-id {
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
            width: 140px;
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

        /* Notes Section */
        .notes-section {
            background: var(--gray-50);
            border-radius: var(--radius-element);
            padding: 16px;
            margin-top: 16px;
        }
        .notes-section h4 {
            color: var(--midnight);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notes-content {
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
            .appointment-header { flex-direction: column; gap: 15px; text-align: center; }
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
                <div class="logo-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>
            </div>
            
            <div class="staff-profile">
                <div class="staff-avatar">
                    <?php echo $initials; ?>
                </div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p>Staff Member</p>
                <span class="staff-badge">
                    <i class="fas fa-user-check"></i> Staff
                </span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-divider">Appointments</li>
                <li><a href="manage-appointments.php" class="active"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
                <li class="menu-divider">Services</li>
                <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> AICS Requests</a></li>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Housing</a></li>
                <li class="menu-divider">Documents</li>
                <li><a href="verify-documents.php"><i class="fas fa-file-alt"></i> Verify Documents</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="menu-divider"></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="breadcrumb">
            <a href="../staff-dashboard.php">Dashboard</a> > 
            <a href="manage-appointments.php">Appointments</a> > 
            View Appointment #<?php echo $appointment_id; ?>
        </div>
        
        <?php $statusInfo = getAppointmentStatusInfo($appointment['status']); ?>
        
        <!-- Appointment Header -->
        <div class="appointment-header">
            <div class="appointment-title">
                <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
                <div class="appointment-id">
                    <strong>Appointment ID:</strong> #<?php echo $appointment_id; ?>
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
            <!-- Client Information -->
            <div class="info-card">
                <h3><i class="fas fa-user-circle"></i> Client Information</h3>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                </div>
                <?php if(isset($appointment['middle_name']) && !empty($appointment['middle_name'])): ?>
                <div class="info-row">
                    <div class="info-label">Middle Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['middle_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Contact Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['contact_number']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email Address:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></div>
                </div>
                <?php if(isset($appointment['street']) || isset($appointment['barangay'])): ?>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">
                        <?php 
                        $address = [];
                        if(!empty($appointment['street'])) $address[] = $appointment['street'];
                        if(!empty($appointment['barangay'])) $address[] = $appointment['barangay'];
                        if(!empty($appointment['city'])) $address[] = $appointment['city'];
                        if(!empty($appointment['province'])) $address[] = $appointment['province'];
                        echo htmlspecialchars(implode(', ', $address));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Appointment Details -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Appointment Details</h3>
                <div class="info-row">
                    <div class="info-label">Service:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Duration:</div>
                    <div class="info-value"><?php echo $appointment['duration_minutes'] ?? '30'; ?> minutes</div>
                </div>
                <?php if(!empty($appointment['purpose'])): ?>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($appointment['purpose'])); ?></div>
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
                <div class="info-row">
                    <div class="info-label">Booked On:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['created_at'])); ?></div>
                </div>
                <?php if($appointment['processed_by'] && !empty($staff_info)): ?>
                <div class="info-row">
                    <div class="info-label">Processed By:</div>
                    <div class="info-value"><?php echo htmlspecialchars($staff_info['first_name'] . ' ' . $staff_info['last_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Position:</div>
                    <div class="info-value"><?php echo htmlspecialchars($staff_info['position']); ?></div>
                </div>
                <?php endif; ?>
                <?php if(isset($appointment['updated_at']) && $appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                <div class="info-row">
                    <div class="info-label">Last Updated:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['updated_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Service Description -->
            <?php if(!empty($appointment['service_description'])): ?>
            <div class="info-card">
                <h3><i class="fas fa-concierge-bell"></i> Service Description</h3>
                <div class="info-value" style="line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($appointment['service_description'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Supporting Documents -->
        <div class="info-card" style="margin-bottom: 24px;">
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
                        <a href="../uploads/<?php echo $doc['document_path'] ?? $doc['file_path']; ?>" class="btn-download" target="_blank">
                            <i class="fas fa-download"></i> View
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No documents attached to this appointment</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Staff Notes -->
        <?php if(!empty($appointment['notes'])): ?>
        <div class="info-card" style="margin-bottom: 24px;">
            <h3><i class="fas fa-sticky-note"></i> Staff Notes</h3>
            <div class="notes-section">
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="manage-appointments.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <?php if($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
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
<div id="updateModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 20px; width: 90%; max-width: 550px; max-height: 90vh; overflow-y: auto; animation: slideIn 0.3s ease;">
        <div style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="color: var(--maroon);"><i class="fas fa-edit"></i> Update Appointment Status</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" action="manage-appointments.php">
            <div style="padding: 25px;">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                
                <div style="background: var(--gray-50); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--gray-600);">Client:</span>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--gray-600);">Service:</span>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">New Status</label>
                    <select name="new_status" id="modal_status" required style="width: 100%; padding: 12px; border: 1px solid var(--gray-200); border-radius: 10px;">
                        <option value="confirmed">✅ Confirm Appointment</option>
                        <option value="completed">✔️ Mark as Completed</option>
                        <option value="cancelled">❌ Cancel Appointment</option>
                        <option value="pending">⏰ Mark as Pending</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label for="modal_notes">Notes / Remarks</label>
                    <textarea name="notes" id="modal_notes" rows="3" 
                              placeholder="Add any notes about this appointment..."
                              style="width: 100%; padding: 12px; border: 1px solid var(--gray-200); border-radius: 10px;"></textarea>
                </div>
            </div>
            
            <div style="padding: 15px 25px; border-top: 1px solid var(--gray-200); display: flex; gap: 12px; justify-content: flex-end;">
                <button type="submit" name="update_status" class="btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
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
    document.getElementById('modal_notes').value = '';
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

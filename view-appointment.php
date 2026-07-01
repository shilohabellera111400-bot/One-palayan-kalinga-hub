<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$appointment_id) {
    redirect('my-appointments.php');
}

// Fetch appointment details for the logged-in user
$query = "SELECT a.*, s.service_name, s.description as service_description, s.duration_minutes,
          u.first_name, u.last_name, u.email, u.contact_number
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          JOIN users u ON a.user_id = u.user_id
          WHERE a.appointment_id = :id AND a.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $appointment_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('my-appointments.php?error=Appointment not found');
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get document attachments for this appointment
$doc_query = "SELECT * FROM documents WHERE appointment_id = :id ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':id', $appointment_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>View Appointment - One Palayan-Kalinga Hub</title>
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

        .user-profile {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 16px;
            text-align: center;
        }
        .user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--tan), var(--light-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--maroon);
        }
        .user-profile h4 {
            color: white;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .user-profile p {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
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
        .btn-cancel {
            background: #f44336;
            color: white;
        }
        .btn-cancel:hover {
            background: #d32f2f;
        }
        .btn-cancel:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Toast Message */
        .toast-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
        }
        .toast-success { border-left-color: #4caf50; }
        .toast-error { border-left-color: #f44336; }
        .toast-success i { color: #4caf50; }
        .toast-error i { color: #f44336; }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-message.fade-out {
            animation: fadeOut 0.3s ease forwards;
        }
        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(100%); }
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
                    <p>Integrated Service Portal</p>
                </div>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo $initials; ?>
                </div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p>Resident</p>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="appointments.php" class="active"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
                    <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                    <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
                    <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
                    <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
                    <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
                    <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
                    <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-globe"></i> Main Site</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > 
            <a href="my-appointments.php">My Appointments</a> > 
            View Appointment #<?php echo $appointment_id; ?>
        </div>
        
        <?php $statusInfo = getAppointmentStatusInfo($appointment['status']); ?>
        
        <!-- Appointment Header -->
        <div class="appointment-header">
            <div class="appointment-title">
                <h1><i class="fas fa-calendar-check"></i> Appointment Details</h1>
                <div class="appointment-id">
                    <strong>Reference Number:</strong> #<?php echo $appointment_id; ?>
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
            <!-- Service Information -->
            <div class="info-card">
                <h3><i class="fas fa-concierge-bell"></i> Service Information</h3>
                <div class="info-row">
                    <div class="info-label">Service:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong></div>
                </div>
                <?php if(!empty($appointment['service_description'])): ?>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($appointment['service_description'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Duration:</div>
                    <div class="info-value"><?php echo $appointment['duration_minutes'] ?? '30'; ?> minutes</div>
                </div>
            </div>
            
            <!-- Appointment Details -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Appointment Details</h3>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></div>
                </div>
                <?php if(!empty($appointment['purpose'])): ?>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($appointment['purpose'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Booked On:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['created_at'])); ?></div>
                </div>
            </div>
            
            <!-- Your Information -->
            <div class="info-card">
                <h3><i class="fas fa-user-circle"></i> Your Information</h3>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Contact Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['contact_number']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></div>
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="info-card">
                <h3><i class="fas fa-clipboard-list"></i> Status Information</h3>
                <div class="info-row">
                    <div class="info-label">Current Status:</div>
                    <div class="info-value">
                        <span style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                </div>
                <?php if($appointment['updated_at'] && $appointment['updated_at'] != $appointment['created_at']): ?>
                <div class="info-row">
                    <div class="info-label">Last Updated:</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($appointment['updated_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
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
                        <a href="uploads/<?php echo $doc['document_path'] ?? $doc['file_path']; ?>" class="btn-download" target="_blank">
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
            <a href="my-appointments.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Appointments
            </a>
            <?php if($appointment['status'] == 'pending'): ?>
            <button id="cancelAppointmentBtn" class="btn-primary btn-cancel" data-id="<?php echo $appointment_id; ?>">
                <i class="fas fa-times-circle"></i> Cancel Appointment
            </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-primary btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</div>

<script>
// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// Cancel appointment with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-id');
            
            if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                return;
            }
            
            // Disable button to prevent multiple clicks
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
            // Send AJAX request
            fetch('cancel-appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ appointment_id: appointmentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update the status badge on the page
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> Cancel Appointment';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                cancelBtn.disabled = false;
                cancelBtn.innerHTML = '<i class="fas fa-times-circle"></i> Cancel Appointment';
            });
        });
    }
});
</script>
</body>
</html>
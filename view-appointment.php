<?php
require_once 'config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

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
$doc_query = "
    SELECT document_id, document_name, file_path, file_size, uploaded_at as upload_date 
    FROM appointment_documents 
    WHERE appointment_id = :id 
    UNION ALL 
    SELECT document_id, document_type as document_name, document_path as file_path, NULL as file_size, upload_date 
    FROM documents 
    WHERE appointment_id = :id2 
    ORDER BY upload_date DESC
";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->execute([':id' => $appointment_id, ':id2' => $appointment_id]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status audit trail
$hist_stmt = $db->prepare("
    SELECT h.*, h.new_status as status, h.remarks as notes, CONCAT(u.first_name, ' ', u.last_name) as action_by_name 
    FROM appointment_status_history h 
    LEFT JOIN users u ON h.performed_by = u.user_id 
    WHERE h.appointment_id = :id 
    ORDER BY h.created_at ASC
");
$hist_stmt->execute([':id' => $appointment_id]);
$status_history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status color and icon
function getAppointmentStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => 'rgba(255, 152, 0, 0.12)'],
        'confirmed' => ['color' => '#2196F3', 'icon' => 'fa-check-circle', 'text' => 'Confirmed', 'bg' => 'rgba(33, 150, 243, 0.12)'],
        'completed' => ['color' => '#4CAF50', 'icon' => 'fa-check-double', 'text' => 'Completed', 'bg' => 'rgba(76, 175, 80, 0.12)'],
        'cancelled' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Cancelled', 'bg' => 'rgba(244, 67, 54, 0.12)']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => 'rgba(153, 153, 153, 0.12)'];
}

// Get user details for profile picture
$user_info_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_info_stmt = $db->prepare($user_info_query);
$user_info_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_info_stmt->execute();
$user_p = $user_info_stmt->fetch(PDO::FETCH_ASSOC);

// Profile picture path
$profile_picture = $user_p['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture
    : '';

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== BREADCRUMB ========== */
        .breadcrumb {
            margin-bottom: 24px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--maroon-light);
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb i {
            margin: 0 6px;
        }

        /* ========== APPOINTMENT HEADER ========== */
        .appointment-header {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .appointment-title h1 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }
        .appointment-title .ref-number {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .appointment-title .ref-number strong {
            color: var(--text-primary);
        }
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
        }

        /* ========== INFO GRID ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .info-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .info-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .info-card .card-header h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
        }
        .info-card .card-header i {
            color: var(--maroon-light);
            font-size: 1.1rem;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--text-muted);
            flex-shrink: 0;
            font-size: 0.82rem;
        }
        .info-value {
            flex: 1;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .info-value strong {
            color: var(--text-primary);
        }

        /* ========== DOCUMENTS ========== */
        .documents-list {
            display: grid;
            gap: 10px;
        }
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        .document-item:hover {
            background: var(--bg-primary);
            border-color: var(--maroon-light);
        }
        .document-name {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .document-name i {
            font-size: 1.1rem;
            color: var(--maroon-light);
        }
        .btn-download {
            background: var(--maroon);
            color: white;
            padding: 6px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-download:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== NOTES ========== */
        .notes-section {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 16px 20px;
            border-left: 4px solid var(--maroon-light);
        }
        .notes-content {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .btn-secondary:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-print {
            background: #17a2b8;
            color: white;
        }
        .btn-print:hover {
            background: #138496;
        }
        .btn-cancel {
            background: #EF5350;
            color: white;
        }
        .btn-cancel:hover {
            background: #C62828;
        }
        .btn-cancel:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        .btn-success:hover {
            background: #388E3C;
        }

        /* ========== TOAST ========== */
        .toast-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            padding: 16px 24px;
            border-radius: var(--radius-element);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .toast-success { border-left-color: #4CAF50; }
        .toast-error { border-left-color: #EF5350; }
        .toast-success i { color: #4CAF50; }
        .toast-error i { color: #EF5350; }
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

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.4;
            display: block;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .info-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 992px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .appointment-header { flex-direction: column; align-items: stretch; text-align: center; }
            .status-badge-large { align-self: center; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 4px; }
            .action-buttons { flex-direction: column; }
            .btn { justify-content: center; }
            .info-card { padding: 18px 20px; }
        }
        @media (max-width: 480px) {
            .appointment-header { padding: 16px 18px; }
            .info-card { padding: 14px 16px; }
        }
        @media print {
            .sidebar, .top-bar, .action-buttons, .breadcrumb, .theme-toggle { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
            .appointment-header { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Page Title Subheader -->
            <div class="portal-page-subheader" style="margin-bottom: 24px;">
                <div class="page-title">
                    <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--ref-text, #191D1E); margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar-check" style="color: #7F0303;"></i> Appointment Details
                    </h1>
                    <p style="font-size: 0.85rem; color: var(--ref-muted, #71818B); margin: 4px 0 0;">View your appointment information</p>
                </div>
            </div>
        
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <a href="my-appointments.php">My Appointments</a>
            <i class="fas fa-chevron-right"></i>
            <span>Appointment #<?php echo $appointment_id; ?></span>
        </div>
        
        <?php $statusInfo = getAppointmentStatusInfo($appointment['status']); ?>
        
        <?php 
            $refNum = !empty($appointment['reference_number']) ? $appointment['reference_number'] : ('PAL-SOC-' . date('Y', strtotime($appointment['created_at'])) . '-' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT));
        ?>
        
        <!-- Appointment Header -->
        <div class="appointment-header">
            <div class="appointment-title">
                <h1><?php echo htmlspecialchars($appointment['service_name']); ?></h1>
                <div class="ref-number">
                    <strong>Reference Number:</strong> <span style="font-family: 'Courier Prime', monospace; font-size: 1.15rem; font-weight: 700; color: var(--maroon);"><?php echo htmlspecialchars($refNum); ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <?php echo getAppointmentBadge($appointment['status']); ?>
                <a href="appointment-slip.php?ref=<?php echo urlencode($refNum); ?>" target="_blank" class="btn btn-primary" style="background: var(--maroon); color: #fff; text-decoration: none; padding: 9px 20px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-print"></i> Digital Appointment Slip
                </a>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <!-- Service Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-concierge-bell"></i>
                    <h3>Service Information</h3>
                </div>
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
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Appointment Details</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value"><strong><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong></div>
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
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Your Information</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong></div>
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
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Status Information</h3>
                </div>
                <div class="info-row">
                    <div class="info-label">Current Status:</div>
                    <div class="info-value">
                        <span style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 14px; border-radius: 20px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
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
            <div class="card-header">
                <i class="fas fa-paperclip"></i>
                <h3>Supporting Documents</h3>
                <span style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 50px;"><?php echo count($documents); ?> file(s)</span>
            </div>
            <?php if(count($documents) > 0): ?>
                <div class="documents-list">
                    <?php foreach($documents as $doc): ?>
                    <div class="document-item">
                        <?php 
                            $filePath = $doc['file_path'] ?? ($doc['document_path'] ?? '');
                            $docName = $doc['document_name'] ?? ($doc['document_type'] ?? 'Document');
                            $ext = strtolower(pathinfo($filePath ?: $docName, PATHINFO_EXTENSION));
                            $iconClass = $ext === 'pdf' ? 'pdf' : (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? 'image' : 'alt');
                            if (!empty($filePath)) {
                                if (strpos($filePath, 'http') === 0 || strpos($filePath, '/') === 0) {
                                    $docUrl = $filePath;
                                } elseif (strpos($filePath, 'uploads/') === 0) {
                                    $docUrl = $filePath;
                                } else {
                                    $docUrl = 'uploads/' . $filePath;
                                }
                            } else {
                                $docUrl = '#';
                            }
                        ?>
                        <div class="document-name">
                            <i class="fas fa-file-<?php echo $iconClass; ?>"></i>
                            <?php echo htmlspecialchars($docName); ?>
                            <span style="font-size: 0.65rem; color: var(--text-muted);">
                                <?php echo date('M d, Y', strtotime($doc['upload_date'] ?? 'now')); ?>
                            </span>
                        </div>
                        <a href="<?php echo htmlspecialchars($docUrl); ?>" class="btn-download" target="_blank">
                            <i class="fas fa-eye"></i> View
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
            <div class="card-header">
                <i class="fas fa-sticky-note"></i>
                <h3>Staff Notes</h3>
            </div>
            <div class="notes-section">
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status History & Audit Trail Timeline -->
        <div class="info-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <i class="fas fa-history" style="color: var(--maroon);"></i>
                <h3>Appointment Status History & Activity Trail</h3>
                <span style="margin-left: auto; font-size: 0.72rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 50px;">
                    <?php echo count($status_history); ?> update(s)
                </span>
            </div>
            <?php if(!empty($status_history)): ?>
                <div style="display: flex; flex-direction: column; gap: 12px; padding: 10px 0;">
                    <?php foreach($status_history as $idx => $hist): ?>
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background: #F3F4F6; color: var(--maroon); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; border: 1.5px solid var(--border-color);">
                            <?php echo $idx + 1; ?>
                        </div>
                        <div style="flex: 1; background: var(--bg-subtle); padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; flex-wrap: wrap; gap: 6px;">
                                <div><?php echo getAppointmentBadge($hist['status']); ?></div>
                                <span style="font-size: 0.72rem; color: var(--text-muted);">
                                    <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($hist['created_at'])); ?>
                                </span>
                            </div>
                            <?php if(!empty($hist['notes'])): ?>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">
                                <?php echo htmlspecialchars($hist['notes']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($hist['action_by_name'])): ?>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">
                                Action by: <strong><?php echo htmlspecialchars($hist['action_by_name']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 20px;">
                    <p style="margin: 0; font-size: 0.82rem; color: var(--text-muted);">No status updates recorded yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="my-appointments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Appointments
            </a>
            <?php if($appointment['status'] == 'pending'): ?>
            <button id="cancelAppointmentBtn" class="btn btn-cancel" data-id="<?php echo $appointment_id; ?>">
                <i class="fas fa-times-circle"></i> Cancel Appointment
            </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</div>

<script>
// ========== MOBILE SIDEBAR TOGGLE ==========
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebarClose = document.getElementById('sidebarClose');

function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle ? themeToggle.querySelector('i') : null;
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
}

// ========== TOAST NOTIFICATION ==========
function showToast(message, type = 'success') {
    if (window.showProcessToast) {
        window.showProcessToast(message, type === 'error', type === 'error' ? 'Notice / Error' : 'Process Completed');
        return;
    }

    document.querySelectorAll('.toast-message').forEach(t => t.remove());
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

// ========== CANCEL APPOINTMENT ==========
document.addEventListener('DOMContentLoaded', function() {
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-id');
            
            if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                return;
            }
            
            cancelBtn.disabled = true;
            cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
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

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'my-appointments.php?search=' + encodeURIComponent(query);
        }
    }
});
</script>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

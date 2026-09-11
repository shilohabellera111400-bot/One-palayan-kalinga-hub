<?php
// verify-resident.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user already has a verification request
$check_query = "SELECT * FROM verification_requests WHERE user_id = :user_id ORDER BY verification_id DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$existing_request = $check_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's current verification status
$user_query = "SELECT verification_status, profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_status = $user_stmt->fetch(PDO::FETCH_ASSOC);
$verification_status = $user_status['verification_status'] ?? 'pending';

$error = '';
$success = '';
// Helper function to handle upload or base64 decoding from camera capture
function saveVerificationFile($fieldName, $capturedFieldName, $uploader) {
    // 1. Check if camera snapshot base64 was captured
    if (!empty($_POST[$capturedFieldName])) {
        $base64_string = $_POST[$capturedFieldName];
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = substr($base64_string, strpos($base64_string, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    $dir = __DIR__ . '/uploads/verification/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = uniqid('cap_', true) . '.' . $type;
                    $file_path = 'uploads/verification/' . $filename;
                    if (file_put_contents(__DIR__ . '/' . $file_path, $data)) {
                        return $file_path;
                    }
                }
            }
        }
    }

    // 2. Check if file was uploaded standardly
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $file = [
            'name' => $_FILES[$fieldName]['name'],
            'type' => $_FILES[$fieldName]['type'],
            'tmp_name' => $_FILES[$fieldName]['tmp_name'],
            'error' => $_FILES[$fieldName]['error'],
            'size' => $_FILES[$fieldName]['size']
        ];
        return $uploader->upload($file, 'verification');
    }

    return null;
}

// Handle verification submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_verification'])) {
    csrf_verify();
    $id_type = sanitize($_POST['id_type']);
    $id_number = sanitize($_POST['id_number']);
    $barangay = sanitize($_POST['barangay']);
    $street = sanitize($_POST['street'] ?? '');
    $city = sanitize($_POST['city'] ?? 'Palayan City');
    $province = sanitize($_POST['province'] ?? 'Nueva Ecija');
    $postal_code = sanitize($_POST['postal_code'] ?? '3132');
    
    // Validate required fields
    if (empty($id_type) || empty($id_number) || empty($barangay)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if user already has a pending request
        if ($existing_request && $existing_request['status'] == 'pending') {
            $error = "You already have a pending verification request. Please wait for staff to review your documents.";
        } else {
            $uploader = new FileUploader();
            
            // Save Valid ID file or snapshot
            $id_file_path = saveVerificationFile('valid_id', 'captured_id_image', $uploader);
            if (empty($id_file_path)) {
                $error = "Please upload or capture your valid ID.";
            }
            
            // Save Selfie with ID file or snapshot
            if (empty($error)) {
                $selfie_file_path = saveVerificationFile('selfie_file', 'captured_selfie_image', $uploader);
                if (empty($selfie_file_path)) {
                    $error = "Please upload or capture your selfie with the ID.";
                }
            }
            
            // Save Barangay Certificate (optional in table schema, but we collect it)
            if (empty($error)) {
                $address_file_path = saveVerificationFile('address_file', 'captured_address_image', $uploader);
                if (empty($address_file_path)) {
                    $address_file_path = ''; 
                }
            }
            
            if (empty($error)) {
                // Build complete address
                $complete_address = trim($street . ', ' . $barangay . ', ' . $city . ', ' . $province . ' ' . $postal_code);
                
                // Insert verification request
                $query = "INSERT INTO verification_requests 
                          (user_id, id_type, id_number, id_file_path, proof_of_residency_path, barangay_certificate_path, barangay_name, complete_address, remarks) 
                          VALUES (:user_id, :id_type, :id_number, :id_path, :residency_path, :cert_path, :barangay, :address, :remarks)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':id_type', $id_type);
                $stmt->bindParam(':id_number', $id_number);
                $stmt->bindParam(':id_path', $id_file_path);
                $stmt->bindParam(':residency_path', $selfie_file_path);
                $stmt->bindParam(':cert_path', $address_file_path);
                $stmt->bindParam(':barangay', $barangay);
                $stmt->bindParam(':address', $complete_address);
                
                $remarks = "Resident verification request submitted.";
                $stmt->bindParam(':remarks', $remarks);
                
                if ($stmt->execute()) {
                    // Update user's verification status
                    $update_user = "UPDATE users SET verification_status = 'pending' WHERE user_id = :user_id";
                    $update_stmt = $db->prepare($update_user);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $update_stmt->execute();
                    
                    // Refresh existing_request variable so the view updates instantly
                    $check_stmt->execute();
                    $existing_request = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    $verification_status = 'pending';
                    
                    $success = "Your verification request has been submitted successfully. Please wait for staff to review your documents.";
                } else {
                    $error = "Failed to submit verification request. Please try again.";
                }
            }
        }
    }
}
// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user_status['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
    ? $profile_picture 
    : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Residency - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css">
    <style>
        .verification-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 30px 36px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            max-width: 700px;
            margin: 0 auto;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        /* Header with gold accent */
        .card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            position: relative;
        }
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--gold);
        }
        .card-header .header-icon {
            width: 48px;
            height: 48px;
            background: var(--gold-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon);
            font-size: 1.4rem;
        }
        .card-header h2 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
        }
        .card-header p {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin: 2px 0 0;
        }
        .card-header .header-text {
            flex: 1;
        }

        /* Status Card */
        .status-card {
            text-align: center;
            padding: 30px 20px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 2.2rem;
        }
        .status-pending .status-icon { background: rgba(255, 152, 0, 0.12); color: var(--color-warning); }
        .status-verified .status-icon { background: rgba(76, 175, 80, 0.12); color: var(--color-success); }
        .status-rejected .status-icon { background: rgba(244, 67, 54, 0.12); color: var(--color-danger); }
        .status-not-submitted .status-icon { background: rgba(201, 168, 108, 0.15); color: var(--gold); }
        
        .status-card h2 {
            color: var(--text-primary);
            font-size: 1.15rem;
            margin-bottom: 6px;
        }
        .status-card p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .status-card .small {
            font-size: 0.75rem;
            margin-top: 8px;
            color: var(--text-muted);
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .status-badge.verified { background: rgba(76, 175, 80, 0.12); color: var(--color-success); }
        .status-badge.pending { background: rgba(255, 152, 0, 0.12); color: var(--color-warning); }
        .status-badge.rejected { background: rgba(244, 67, 54, 0.12); color: var(--color-danger); }

        /* Form styling */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .form-group label i {
            color: var(--gold);
            margin-right: 6px;
            width: 18px;
        }
        .form-group label .required-star {
            color: var(--color-danger);
            margin-left: 2px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Document Upload - Clean & Minimal */
        .upload-section {
            margin: 20px 0 16px;
        }
        .upload-section .upload-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
            display: block;
            margin-bottom: 8px;
        }
        .upload-section .upload-label i {
            color: var(--gold);
            margin-right: 6px;
            width: 18px;
        }
        .upload-box {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-element);
            padding: 24px 20px;
            text-align: center;
            background: var(--badge-bg);
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        .upload-box:hover {
            border-color: var(--gold);
            background: var(--bg-card);
        }
        .upload-box i {
            font-size: 2.2rem;
            color: var(--gold);
            opacity: 0.6;
            margin-bottom: 8px;
            display: block;
        }
        .upload-box p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }
        .upload-box .file-types {
            color: var(--text-muted);
            font-size: 0.7rem;
            margin-top: 4px;
        }
        .upload-box input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-name {
            display: none;
            padding: 6px 14px;
            background: rgba(201, 168, 108, 0.12);
            border-radius: 6px;
            color: var(--gold);
            font-size: 0.8rem;
            margin-top: 10px;
            word-break: break-all;
        }
        .file-name.show {
            display: inline-block;
        }
        .upload-box.has-file {
            border-color: var(--gold);
            border-style: solid;
            background: rgba(201, 168, 108, 0.05);
        }

        /* ========== MULTI-STEP STEPPER ========== */
        .verification-stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 35px;
            padding: 10px 0;
            position: relative;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .step-label {
            margin-top: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-align: center;
            transition: all 0.4s;
        }
        .step-line {
            height: 2px;
            background: var(--border-color);
            flex: 1;
            margin: 0 -15px;
            position: relative;
            top: -18px; /* align with vertical center of circle */
            z-index: 1;
            transition: all 0.4s;
        }
        .step-item.active .step-circle {
            background: var(--white);
            border-color: var(--gold);
            color: var(--gold);
            box-shadow: 0 0 0 4px rgba(201, 168, 108, 0.15);
        }
        .step-item.active .step-label {
            color: var(--gold);
            font-weight: 700;
        }
        .step-item.completed .step-circle {
            background: var(--maroon);
            border-color: var(--maroon);
            color: white;
        }
        .step-item.completed .step-label {
            color: var(--text-primary);
        }
        .step-line.filled {
            background: var(--maroon);
        }

        /* ========== STEP CONTENT ========== */
        .step-content {
            display: none;
            border: none;
            padding: 0;
            margin: 0;
            animation: fadeIn 0.4s ease both;
        }
        .step-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== MODE TOGGLES ========== */
        .mode-toggles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: var(--badge-bg);
            padding: 4px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .mode-btn {
            background: transparent;
            border: none;
            padding: 8px 16px;
            border-radius: 26px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .mode-btn.active {
            background: var(--white);
            color: var(--maroon);
            box-shadow: var(--shadow-sm);
        }

        /* ========== CAMERA SYSTEM ========== */
        .camera-panel {
            background: var(--badge-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            overflow: hidden;
            margin-bottom: 18px;
            text-align: center;
        }
        .video-viewport {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .video-viewport video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* mirror effect */
        }
        .camera-overlay {
            position: absolute;
            inset: 20px;
            border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .camera-overlay::before {
            content: 'Align document inside this box';
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            background: rgba(0, 0, 0, 0.6);
            padding: 4px 10px;
            border-radius: 4px;
        }
        .camera-overlay.selfie-overlay::before {
            content: 'Align your face here';
        }
        .camera-overlay.selfie-overlay {
            border-radius: 50%;
            width: 70%;
            height: 80%;
            margin: auto;
        }
        .camera-controls {
            padding: 14px;
            display: flex;
            justify-content: center;
            gap: 12px;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
        }
        .btn-camera {
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-camera.capture {
            background: var(--maroon);
            color: white;
        }
        .btn-camera.capture:hover {
            background: var(--maroon-light);
        }
        .btn-camera.retake {
            background: var(--border-color);
            color: var(--text-secondary);
        }
        .btn-camera.retake:hover {
            background: var(--text-muted);
            color: white;
        }

        .preview-holder {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            background: var(--border-color);
            display: none;
            align-items: center;
            justify-content: center;
        }
        .preview-holder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-element);
        }

        /* ========== SUMMARY TABLE ========== */
        .summary-block {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            margin-bottom: 8px;
        }
        .summary-label {
            color: var(--text-muted);
            font-weight: 500;
        }
        .summary-val {
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
        }
        .summary-photo-preview {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .summary-photo-preview .thumb {
            flex: 1;
            aspect-ratio: 4/3;
            border-radius: 8px;
            background: var(--border-color);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-color);
        }
        .summary-photo-preview .thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .summary-photo-preview .thumb-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 0.65rem;
            text-align: center;
            padding: 2px 0;
        }

        /* ========== ACTIONS ROW ========== */
        .step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            gap: 16px;
        }
        .btn-action {
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-action.prev {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-action.prev:hover {
            background: var(--border-color);
        }
        .btn-action.next {
            background: var(--maroon);
            color: white;
            flex: 1;
            justify-content: center;
        }
        .btn-action.next:hover {
            background: var(--maroon-light);
            transform: translateY(-1px);
        }

        .alert-mini {
            background: rgba(244, 67, 54, 0.08);
            border: 1px solid rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            margin-bottom: 12px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px 24px; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .verification-card { padding: 20px; margin: 0 10px; }
            .status-card { padding: 20px; }
            .status-icon { width: 60px; height: 60px; font-size: 1.8rem; }
            .card-header { flex-direction: column; text-align: center; }
            .card-header::after { left: 50%; transform: translateX(-50%); }
            .btn-action { padding: 10px 20px; font-size: 0.8rem; }
            .page-title h1 { font-size: 1.3rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 14px 16px; }
            .verification-card { padding: 16px; margin: 0; }
            .form-group input, .form-group select, .form-group textarea { padding: 8px 12px; font-size: 0.8rem; }
        }</style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Integrated Service Portal</span>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <span>Resident</span>
                </div>
            </div>
            
            <?php include __DIR__ . '/includes/usersidenav.php'; ?>
            
            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-shield-alt" style="color: var(--gold); margin-right: 10px;"></i> Verify Residency</h1>
                        <p>Complete your verification to access all services</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

        <div class="verification-card">
            <div class="card-header">
                <div class="header-icon"><i class="fas fa-id-card"></i></div>
                <div class="header-text">
                    <h2>Residency Verification</h2>
                    <p>Submit your valid ID and address to verify your residency</p>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if($verification_status == 'verified'): ?>
                <div class="status-card status-verified">
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <h2>✓ You are Verified!</h2>
                    <p>Your residency has been verified. You now have full access to all services.</p>
                    <p class="small">
                        <span class="status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                    </p>
                </div>
            <?php elseif($verification_status == 'rejected' || $verification_status == 'not_submitted' || empty($verification_status) || $verification_status == 'pending_resubmission' || $existing_request == null): ?>
                <?php if($verification_status == 'rejected'): ?>
                    <div class="status-card status-rejected">
                        <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                        <h2>Verification Failed</h2>
                        <p>Your verification request was rejected. Please submit a new ID, selfie, and address.</p>
                        <?php if($existing_request && !empty($existing_request['rejection_reason'])): ?>
                            <div class="alert alert-info" style="margin-top: 15px; text-align: left;">
                                <i class="fas fa-info-circle"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($existing_request['rejection_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="status-card status-not-submitted">
                        <div class="status-icon"><i class="fas fa-shield-alt"></i></div>
                        <h2>Verify Your Account</h2>
                        <p>Please submit a valid ID, selfie, and your address to verify your residency in Palayan City.</p>
                        <p class="small"><i class="fas fa-clock"></i> Verification usually takes 1-3 business days.</p>
                    </div>
                <?php endif; ?>

                <!-- Verification Stepper -->
                <div class="verification-stepper">
                    <div class="step-item active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal Details</div>
                    </div>
                    <div class="step-line" data-line="1"></div>
                    <div class="step-item" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Proof of ID</div>
                    </div>
                    <div class="step-line" data-line="2"></div>
                    <div class="step-item" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Selfie with ID</div>
                    </div>
                    <div class="step-line" data-line="3"></div>
                    <div class="step-item" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Proof of Address</div>
                    </div>
                </div>

                <!-- Live camera notification alerts -->
                <div class="alert-mini" id="stepAlert"><i class="fas fa-exclamation-triangle"></i> <span id="stepAlertText"></span></div>

                <!-- Verification Form -->
                <form method="POST" enctype="multipart/form-data" id="verifyForm">
                    <?php echo csrf_field(); ?>
                    <!-- Hidden fields to hold captured webcam base64 images -->
                    <input type="hidden" name="captured_id_image" id="captured_id_image">
                    <input type="hidden" name="captured_selfie_image" id="captured_selfie_image">
                    <input type="hidden" name="captured_address_image" id="captured_address_image">

                    <!-- STEP 1: Personal Details -->
                    <fieldset class="step-content active" data-fieldset="1">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Valid ID Type <span class="required-star">*</span></label>
                            <select name="id_type" id="id_type" required>
                                <option value="">Select ID Type</option>
                                <option value="PhilSys ID">PhilSys ID (National ID)</option>
                                <option value="Driver's License">Driver's License</option>
                                <option value="UMID">UMID</option>
                                <option value="Passport">Passport</option>
                                <option value="Postal ID">Postal ID</option>
                                <option value="Voter's ID">Voter's ID</option>
                                <option value="Senior Citizen ID">Senior Citizen ID</option>
                                <option value="PWD ID">PWD ID</option>
                                <option value="Other">Other Government ID</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> ID Number <span class="required-star">*</span></label>
                            <input type="text" name="id_number" id="id_number" placeholder="Enter your ID number" required>
                        </div>
                    </fieldset>

                    <!-- STEP 2: Proof of Identity -->
                    <fieldset class="step-content" data-fieldset="2">
                        <div class="upload-section">
                            <label class="upload-label"><i class="fas fa-id-card-alt"></i> Proof of Identity <span class="required-star">*</span></label>
                            
                            <!-- Toggle camera or upload mode -->
                            <div class="mode-toggles">
                                <button type="button" class="mode-btn active" data-mode-btn="upload" data-step-mode="id">
                                    <i class="fas fa-file-upload"></i> Upload File
                                </button>
                                <button type="button" class="mode-btn" data-mode-btn="camera" data-step-mode="id">
                                    <i class="fas fa-camera"></i> Use Camera
                                </button>
                            </div>

                            <!-- Mode 1: Upload box -->
                            <div class="upload-box-wrapper" id="idUploadMode">
                                <div class="upload-box" id="idUploadBox">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Click to upload Valid ID</strong> or drag and drop</p>
                                    <div class="file-types">PDF, JPG, PNG up to 5MB</div>
                                    <input type="file" name="valid_id" id="valid_id" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-name" id="idFileName"></div>
                                </div>
                            </div>

                            <!-- Mode 2: Camera panel -->
                            <div class="camera-panel-wrapper" id="idCameraMode" style="display: none;">
                                <div class="camera-panel">
                                    <div class="video-viewport">
                                        <video id="idVideo" autoplay playsinline></video>
                                        <div class="camera-overlay"></div>
                                        <div class="preview-holder" id="idPreviewHolder">
                                            <img id="idPreviewImg" src="" alt="Valid ID Preview">
                                        </div>
                                    </div>
                                    <div class="camera-controls">
                                        <button type="button" class="btn-camera capture" id="idCaptureBtn">
                                            <i class="fas fa-camera"></i> Capture ID
                                        </button>
                                        <button type="button" class="btn-camera retake" id="idRetakeBtn" style="display: none;">
                                            <i class="fas fa-redo"></i> Retake
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- STEP 3: Selfie with Proof -->
                    <fieldset class="step-content" data-fieldset="3">
                        <div class="upload-section">
                            <label class="upload-label"><i class="fas fa-portrait"></i> Selfie with your Document <span class="required-star">*</span></label>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 15px;">
                                Please take a selfie holding your ID next to your face. Make sure your face and the ID are both clearly visible.
                            </p>

                            <!-- Toggle camera or upload mode -->
                            <div class="mode-toggles">
                                <button type="button" class="mode-btn" data-mode-btn="upload" data-step-mode="selfie">
                                    <i class="fas fa-file-upload"></i> Upload File
                                </button>
                                <button type="button" class="mode-btn active" data-mode-btn="camera" data-step-mode="selfie">
                                    <i class="fas fa-camera"></i> Use Camera
                                </button>
                            </div>

                            <!-- Mode 1: Upload box -->
                            <div class="upload-box-wrapper" id="selfieUploadMode" style="display: none;">
                                <div class="upload-box" id="selfieUploadBox">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Click to upload Selfie</strong> or drag and drop</p>
                                    <div class="file-types">JPG, PNG up to 5MB</div>
                                    <input type="file" name="selfie_file" id="selfie_file" accept=".jpg,.jpeg,.png">
                                    <div class="file-name" id="selfieFileName"></div>
                                </div>
                            </div>

                            <!-- Mode 2: Camera panel -->
                            <div class="camera-panel-wrapper" id="selfieCameraMode">
                                <div class="camera-panel">
                                    <div class="video-viewport">
                                        <video id="selfieVideo" autoplay playsinline></video>
                                        <div class="camera-overlay selfie-overlay"></div>
                                        <div class="preview-holder" id="selfiePreviewHolder">
                                            <img id="selfiePreviewImg" src="" alt="Selfie Preview">
                                        </div>
                                    </div>
                                    <div class="camera-controls">
                                        <button type="button" class="btn-camera capture" id="selfieCaptureBtn">
                                            <i class="fas fa-camera"></i> Take Selfie
                                        </button>
                                        <button type="button" class="btn-camera retake" id="selfieRetakeBtn" style="display: none;">
                                            <i class="fas fa-redo"></i> Retake
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- STEP 4: Address Details & Barangay Certificate -->
                    <fieldset class="step-content" data-fieldset="4">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Barangay <span class="required-star">*</span></label>
                            <select name="barangay" id="barangay" required>
                                <option value="">Select Barangay</option>
                                <option value="Atate">Atate</option>
                                <option value="Aulo">Aulo</option>
                                <option value="Bagong Buhay">Bagong Buhay</option>
                                <option value="Baryo Militar">Baryo Militar</option>
                                <option value="Caballero">Caballero</option>
                                <option value="Caimito">Caimito</option>
                                <option value="Doña Josefa">Doña Josefa</option>
                                <option value="Ganaderia">Ganaderia</option>
                                <option value="Imelda Valley I">Imelda Valley I</option>
                                <option value="Imelda Valley II">Imelda Valley II</option>
                                <option value="Malate">Malate</option>
                                <option value="Maligaya">Maligaya</option>
                                <option value="Manacnac">Manacnac</option>
                                <option value="Mapaet">Mapaet</option>
                                <option value="Marcos Village">Marcos Village</option>
                                <option value="Popolon(Pagas)">Popolon(Pagas)</option>
                                <option value="Santolan">Santolan</option>
                                <option value="Sapang Buho">Sapang Buho</option>
                                <option value="Singalat">Singalat</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-road"></i> Street/Block/Lot</label>
                                <input type="text" name="street" id="street" placeholder="e.g. 123 Mabini St.">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-map-pin"></i> City/Municipality</label>
                                <input type="text" name="city" value="Palayan City" readonly class="disabled-field" style="opacity: 0.7; background: var(--border-color); cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-map"></i> Province</label>
                                <input type="text" name="province" value="Nueva Ecija" readonly class="disabled-field" style="opacity: 0.7; background: var(--border-color); cursor: not-allowed;">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                                <input type="text" name="postal_code" value="3132" readonly class="disabled-field" style="opacity: 0.7; background: var(--border-color); cursor: not-allowed;">
                            </div>
                        </div>

                        <!-- Barangay Certificate Upload / Capture Section -->
                        <div class="upload-section" style="margin-top: 25px;">
                            <label class="upload-label"><i class="fas fa-file-invoice"></i> Proof of Address / Barangay Certificate <span class="required-star">*</span></label>
                            
                            <div class="mode-toggles">
                                <button type="button" class="mode-btn active" data-mode-btn="upload" data-step-mode="address">
                                    <i class="fas fa-file-upload"></i> Upload File
                                </button>
                                <button type="button" class="mode-btn" data-mode-btn="camera" data-step-mode="address">
                                    <i class="fas fa-camera"></i> Use Camera
                                </button>
                            </div>

                            <!-- Mode 1: Upload box -->
                            <div class="upload-box-wrapper" id="addressUploadMode">
                                <div class="upload-box" id="addressUploadBox">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Click to upload Barangay Certificate</strong> or drag and drop</p>
                                    <div class="file-types">PDF, JPG, PNG up to 5MB</div>
                                    <input type="file" name="address_file" id="address_file" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-name" id="addressFileName"></div>
                                </div>
                            </div>

                            <!-- Mode 2: Camera panel -->
                            <div class="camera-panel-wrapper" id="addressCameraMode" style="display: none;">
                                <div class="camera-panel">
                                    <div class="video-viewport">
                                        <video id="addressVideo" autoplay playsinline></video>
                                        <div class="camera-overlay"></div>
                                        <div class="preview-holder" id="addressPreviewHolder">
                                            <img id="addressPreviewImg" src="" alt="Barangay Certificate Preview">
                                        </div>
                                    </div>
                                    <div class="camera-controls">
                                        <button type="button" class="btn-camera capture" id="addressCaptureBtn">
                                            <i class="fas fa-camera"></i> Capture Document
                                        </button>
                                        <button type="button" class="btn-camera retake" id="addressRetakeBtn" style="display: none;">
                                            <i class="fas fa-redo"></i> Retake
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Block (shown before final submit) -->
                        <div class="summary-block" id="formSummary" style="margin-top: 30px;">
                            <div class="summary-title"><i class="fas fa-clipboard-check"></i> Verification Summary</div>
                            <div class="summary-row">
                                <span class="summary-label">ID Type:</span>
                                <span class="summary-val" id="sumIdType">-</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">ID Number:</span>
                                <span class="summary-val" id="sumIdNumber">-</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Barangay:</span>
                                <span class="summary-val" id="sumBarangay">-</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Street Address:</span>
                                <span class="summary-val" id="sumStreet">-</span>
                            </div>
                            <div class="summary-row" style="margin-top: 10px;">
                                <span class="summary-label" style="font-weight: bold;">Captured Documents:</span>
                            </div>
                            <div class="summary-photo-preview">
                                <div class="thumb" id="sumThumbId">
                                    <img src="" alt="ID Thumb">
                                    <div class="thumb-label">Valid ID</div>
                                </div>
                                <div class="thumb" id="sumThumbSelfie">
                                    <img src="" alt="Selfie Thumb">
                                    <div class="thumb-label">Selfie</div>
                                </div>
                                <div class="thumb" id="sumThumbAddress">
                                    <img src="" alt="Address Thumb">
                                    <div class="thumb-label">Certificate</div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- STEPPER NAVIGATION BUTTONS -->
                    <div class="step-actions">
                        <button type="button" class="btn-action prev" id="prevBtn" style="display: none;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <button type="button" class="btn-action next" id="nextBtn">
                            Next Step <i class="fas fa-chevron-right"></i>
                        </button>
                        <button type="submit" name="submit_verification" class="btn-action next" id="submitBtn" style="display: none;">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            <?php elseif($existing_request && $existing_request['status'] == 'pending'): ?>
                <!-- Pending Status Card -->
                <div class="status-card status-pending">
                    <div class="status-icon"><i class="fas fa-clock"></i></div>
                    <h2>Verification Pending</h2>
                    <p>Your verification request is currently being reviewed by our staff.</p>
                    <p class="small">
                        <span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>
                        &nbsp;·&nbsp; Submitted: <?php echo date('F d, Y', strtotime($existing_request['submitted_date'])); ?>
                    </p>
                    <?php if(!empty($existing_request['barangay_name'])): ?>
                    <p class="small" style="margin-top: 8px;">
                        <i class="fas fa-map-marker-alt"></i> Barangay: <?php echo htmlspecialchars($existing_request['barangay_name']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

// ========== STEPPER SYSTEM ==========
let currentStep = 1;
const totalSteps = 4;

const verifyForm = document.getElementById('verifyForm');
if (verifyForm) {
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    const stepAlert = document.getElementById('stepAlert');
    const stepAlertText = document.getElementById('stepAlertText');

    function showAlert(message) {
        stepAlertText.textContent = message;
        stepAlert.style.display = 'flex';
        window.scrollTo({ top: stepAlert.offsetTop - 100, behavior: 'smooth' });
    }

    function hideAlert() {
        stepAlert.style.display = 'none';
    }

    function updateStepperUI() {
        hideAlert();
        document.querySelectorAll('.step-item').forEach((item, index) => {
            const stepNum = index + 1;
            item.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                item.classList.add('active');
            } else if (stepNum < currentStep) {
                item.classList.add('completed');
            }
        });

        document.querySelectorAll('.step-line').forEach((line, index) => {
            const lineNum = index + 1;
            line.classList.remove('filled');
            if (lineNum < currentStep) {
                line.classList.add('filled');
            }
        });

        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.remove('active');
        });
        document.querySelector(`.step-content[data-fieldset="${currentStep}"]`).classList.add('active');

        // Stop active camera streams of other steps
        stopAllCameras();

        // Control buttons display
        if (currentStep === 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
        } else if (currentStep === totalSteps) {
            prevBtn.style.display = 'inline-flex';
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
            buildSummary();
        } else {
            prevBtn.style.display = 'inline-flex';
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';

            // Auto start camera if the entering step is in camera mode
            if (currentStep === 2 && currentModes.id === 'camera') startCamera('id');
            if (currentStep === 3 && currentModes.selfie === 'camera') startCamera('selfie');
        }
    }

    // Step validation checks
    function validateCurrentStep() {
        if (currentStep === 1) {
            const idType = document.getElementById('id_type').value;
            const idNumber = document.getElementById('id_number').value.trim();
            if (!idType) {
                showAlert("Please select a Valid ID type.");
                return false;
            }
            if (!idNumber) {
                showAlert("Please enter your ID number.");
                return false;
            }
            return true;
        }

        if (currentStep === 2) {
            if (currentModes.id === 'upload') {
                const idFileInput = document.getElementById('valid_id');
                if (!idFileInput.files || idFileInput.files.length === 0) {
                    showAlert("Please upload your Valid ID file.");
                    return false;
                }
            } else {
                const capturedId = document.getElementById('captured_id_image').value;
                if (!capturedId) {
                    showAlert("Please capture a photo of your Valid ID.");
                    return false;
                }
            }
            return true;
        }

        if (currentStep === 3) {
            if (currentModes.selfie === 'upload') {
                const selfieFileInput = document.getElementById('selfie_file');
                if (!selfieFileInput.files || selfieFileInput.files.length === 0) {
                    showAlert("Please upload your Selfie file.");
                    return false;
                }
            } else {
                const capturedSelfie = document.getElementById('captured_selfie_image').value;
                if (!capturedSelfie) {
                    showAlert("Please take a selfie with your ID.");
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    nextBtn.addEventListener('click', () => {
        if (validateCurrentStep()) {
            currentStep++;
            updateStepperUI();
        }
    });

    prevBtn.addEventListener('click', () => {
        currentStep--;
        updateStepperUI();
    });

    // ========== MODE TOGGLES & UPLOADS ==========
    const currentModes = {
        id: 'upload',
        selfie: 'camera', // selfie defaults to camera
        address: 'upload'
    };

    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.getAttribute('data-mode-btn'); // camera or upload
            const stepMode = this.getAttribute('data-step-mode'); // id, selfie, address
            
            // Toggle active styling
            this.parentElement.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            currentModes[stepMode] = mode;
            hideAlert();

            const uploadWrapper = document.getElementById(`${stepMode}UploadMode`);
            const cameraWrapper = document.getElementById(`${stepMode}CameraMode`);

            if (mode === 'upload') {
                uploadWrapper.style.display = 'block';
                cameraWrapper.style.display = 'none';
                stopCamera(stepMode);
            } else {
                uploadWrapper.style.display = 'none';
                cameraWrapper.style.display = 'block';
                startCamera(stepMode);
            }
        });
    });

    // Helper for file upload inputs display
    function bindFileUploadDisplay(boxId, inputId, displayId) {
        const box = document.getElementById(boxId);
        const input = document.getElementById(inputId);
        const display = document.getElementById(displayId);

        if (box && input && display) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const name = this.files[0].name;
                    const size = (this.files[0].size / 1024 / 1024).toFixed(2);
                    display.textContent = `📎 ${name} (${size} MB)`;
                    display.classList.add('show');
                    box.classList.add('has-file');
                    hideAlert();
                } else {
                    display.classList.remove('show');
                    box.classList.remove('has-file');
                }
            });

            box.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--gold)';
                this.style.background = 'rgba(201, 168, 108, 0.08)';
            });

            box.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.background = '';
            });

            box.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.background = '';
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    }

    bindFileUploadDisplay('idUploadBox', 'valid_id', 'idFileName');
    bindFileUploadDisplay('selfieUploadBox', 'selfie_file', 'selfieFileName');
    bindFileUploadDisplay('addressUploadBox', 'address_file', 'addressFileName');

    // ========== WEBCAM CAMERA LOGIC ==========
    const cameraStreams = {};

    function startCamera(stepMode) {
        const video = document.getElementById(`${stepMode}Video`);
        if (!video) return;

        // If stream is already running, skip
        if (cameraStreams[stepMode]) return;

        navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: stepMode === 'selfie' ? 'user' : 'environment' } 
        })
        .then(stream => {
            cameraStreams[stepMode] = stream;
            video.srcObject = stream;
            video.play();
            hideAlert();
        })
        .catch(err => {
            console.error(`Camera error for ${stepMode}:`, err);
            showAlert("Could not access your camera. Please ensure camera permissions are allowed, or fallback to File Upload mode.");
            
            // Revert back to upload mode automatically
            const btn = document.querySelector(`.mode-btn[data-mode-btn="upload"][data-step-mode="${stepMode}"]`);
            if (btn) btn.click();
        });
    }

    function stopCamera(stepMode) {
        const stream = cameraStreams[stepMode];
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            delete cameraStreams[stepMode];
        }
        const video = document.getElementById(`${stepMode}Video`);
        if (video) video.srcObject = null;
    }

    function stopAllCameras() {
        Object.keys(cameraStreams).forEach(stopCamera);
    }

    // Capture Snapshots
    function captureSnapshot(stepMode) {
        const video = document.getElementById(`${stepMode}Video`);
        const previewHolder = document.getElementById(`${stepMode}PreviewHolder`);
        const previewImg = document.getElementById(`${stepMode}PreviewImg`);
        const captureBtn = document.getElementById(`${stepMode}CaptureBtn`);
        const retakeBtn = document.getElementById(`${stepMode}RetakeBtn`);
        const hiddenInput = document.getElementById(`captured_${stepMode}_image`);

        if (!video || !previewImg || !previewHolder || !hiddenInput) return;

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        
        const ctx = canvas.getContext('2d');
        
        // Mirror selfie captured snapshot
        if (stepMode === 'selfie') {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        }
        
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const dataUrl = canvas.toDataURL('image/jpeg');
        hiddenInput.value = dataUrl;
        
        previewImg.src = dataUrl;
        previewHolder.style.display = 'flex';
        video.style.display = 'none';
        
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-flex';
        
        // Stop stream to save device resource
        stopCamera(stepMode);
    }

    function retakeSnapshot(stepMode) {
        const video = document.getElementById(`${stepMode}Video`);
        const previewHolder = document.getElementById(`${stepMode}PreviewHolder`);
        const previewImg = document.getElementById(`${stepMode}PreviewImg`);
        const captureBtn = document.getElementById(`${stepMode}CaptureBtn`);
        const retakeBtn = document.getElementById(`${stepMode}RetakeBtn`);
        const hiddenInput = document.getElementById(`captured_${stepMode}_image`);

        if (!video || !previewImg || !previewHolder || !hiddenInput) return;

        hiddenInput.value = '';
        previewImg.src = '';
        previewHolder.style.display = 'none';
        video.style.display = 'block';
        
        captureBtn.style.display = 'inline-flex';
        retakeBtn.style.display = 'none';

        startCamera(stepMode);
    }

    // Bind captures
    const captureModes = ['id', 'selfie', 'address'];
    captureModes.forEach(mode => {
        const cBtn = document.getElementById(`${mode}CaptureBtn`);
        const rBtn = document.getElementById(`${mode}RetakeBtn`);
        if (cBtn) cBtn.addEventListener('click', () => captureSnapshot(mode));
        if (rBtn) rBtn.addEventListener('click', () => retakeSnapshot(mode));
    });

    // ========== BUILD SUMMARY BLOCK ==========
    function buildSummary() {
        const idType = document.getElementById('id_type').value;
        const idNumber = document.getElementById('id_number').value;
        const barangay = document.getElementById('barangay').value;
        const street = document.getElementById('street').value;

        document.getElementById('sumIdType').textContent = idType || '-';
        document.getElementById('sumIdNumber').textContent = idNumber || '-';
        document.getElementById('sumBarangay').textContent = barangay || '-';
        document.getElementById('sumStreet').textContent = street || 'N/A';

        // Summary thumbnail previews
        // ID Document thumb
        const thumbId = document.querySelector('#sumThumbId img');
        if (currentModes.id === 'camera' && document.getElementById('captured_id_image').value) {
            thumbId.src = document.getElementById('captured_id_image').value;
        } else {
            thumbId.src = 'images/palayan-city.png'; // fallback or generic icon
        }

        // Selfie document thumb
        const thumbSelfie = document.querySelector('#sumThumbSelfie img');
        if (currentModes.selfie === 'camera' && document.getElementById('captured_selfie_image').value) {
            thumbSelfie.src = document.getElementById('captured_selfie_image').value;
        } else {
            thumbSelfie.src = 'images/palayan-city.png';
        }

        // Barangay Certificate thumb
        const thumbAddress = document.querySelector('#sumThumbAddress img');
        if (currentModes.address === 'camera' && document.getElementById('captured_address_image').value) {
            thumbAddress.src = document.getElementById('captured_address_image').value;
        } else {
            thumbAddress.src = 'images/palayan-city.png';
        }
    }

    // Submit Validation Check
    verifyForm.addEventListener('submit', function(e) {
        const barangay = document.getElementById('barangay').value;
        if (!barangay) {
            e.preventDefault();
            showAlert("Please select your Barangay before submitting.");
            return false;
        }
        
        // Final check that we have files
        if (currentModes.address === 'upload') {
            const addressInput = document.getElementById('address_file');
            if (!addressInput.files || addressInput.files.length === 0) {
                e.preventDefault();
                showAlert("Please upload your Barangay Certificate file.");
                return false;
            }
        } else {
            const capturedAddress = document.getElementById('captured_address_image').value;
            if (!capturedAddress) {
                e.preventDefault();
                showAlert("Please capture a photo of your Barangay Certificate.");
                return false;
            }
        }
        
        return true;
    });

    // Start Step 1
    updateStepperUI();
}
</script>
</body>
</html>

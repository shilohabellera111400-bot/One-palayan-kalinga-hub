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

// Handle verification submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_verification'])) {
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
            
            // Upload Valid ID
            if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['valid_id']['name'],
                    'type' => $_FILES['valid_id']['type'],
                    'tmp_name' => $_FILES['valid_id']['tmp_name'],
                    'error' => $_FILES['valid_id']['error'],
                    'size' => $_FILES['valid_id']['size']
                ];
                $id_file_path = $uploader->upload($file, 'verification');
            } else {
                $error = "Please upload a valid ID.";
            }
            
            if (empty($error)) {
                // Build complete address
                $complete_address = trim($street . ', ' . $barangay . ', ' . $city . ', ' . $province . ' ' . $postal_code);
                
                // Insert verification request
                $query = "INSERT INTO verification_requests 
                          (user_id, id_type, id_number, id_file_path, barangay_name, complete_address, remarks) 
                          VALUES (:user_id, :id_type, :id_number, :id_path, :barangay, :address, :remarks)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':id_type', $id_type);
                $stmt->bindParam(':id_number', $id_number);
                $stmt->bindParam(':id_path', $id_file_path);
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
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gold: #C9A86C;
            --gold-light: #E8D5A8;
            
            --bg-primary: #F8F9FC;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            --input-bg: #FFFFFF;
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .app-container { display: flex; min-height: 100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-info-sidebar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--maroon-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }
        .user-avatar-sidebar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info-sidebar h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .user-info-sidebar span {
            color: rgba(255,255,255,0.5);
            font-size: 0.65rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 14px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 0.95rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--divider);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover { 
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: var(--color-success);
            border-left-color: var(--color-success);
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            border-left-color: var(--color-danger);
        }
        .alert-info {
            background: rgba(33, 150, 243, 0.12);
            color: var(--color-info);
            border-left-color: var(--color-info);
        }
        .alert i { font-size: 1.2rem; }

        /* ========== VERIFICATION CARD ========== */
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

        /* Form */
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

        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-submit:disabled {
            background: var(--badge-bg);
            cursor: not-allowed;
            transform: none;
            color: var(--text-muted);
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
            .btn-submit { width: 100%; justify-content: center; }
            .page-title h1 { font-size: 1.3rem; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 14px 16px; }
            .verification-card { padding: 16px; margin: 0; }
            .form-group input, .form-group select, .form-group textarea { padding: 8px 12px; font-size: 0.8rem; }
            .upload-box { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Integrated Service Portal</span>
            </div>
        </div>
        
        <div class="user-info-sidebar">
            <div class="user-avatar-sidebar">
                <?php if(!empty($avatar_url)): ?>
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
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
            <li><a href="verify-resident.php" class="active"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-shield-alt" style="color: var(--gold); margin-right: 10px;"></i> Verify Residency</h1>
                <p>Complete your verification to access all services</p>
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
            <?php elseif($verification_status == 'rejected'): ?>
                <div class="status-card status-rejected">
                    <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                    <h2>Verification Failed</h2>
                    <p>Your verification request was rejected. Please submit a new ID and address.</p>
                    <?php if($existing_request && !empty($existing_request['rejection_reason'])): ?>
                        <div class="alert alert-info" style="margin-top: 15px; text-align: left;">
                            <i class="fas fa-info-circle"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($existing_request['rejection_reason']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Show form for re-submission -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Valid ID Type <span class="required-star">*</span></label>
                        <select name="id_type" required>
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
                        <input type="text" name="id_number" placeholder="Enter your ID number" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Barangay <span class="required-star">*</span></label>
                        <select name="barangay" required>
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
                            <input type="text" name="street" placeholder="e.g. 123 Mabini St.">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-pin"></i> City/Municipality</label>
                            <input type="text" name="city" value="Palayan City" readonly class="disabled-field">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map"></i> Province</label>
                            <input type="text" name="province" value="Nueva Ecija" readonly class="disabled-field">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                            <input type="text" name="postal_code" value="3132" readonly class="disabled-field">
                        </div>
                    </div>
                    
                    <div class="upload-section">
                        <label class="upload-label"><i class="fas fa-upload"></i> Upload Valid ID <span class="required-star">*</span></label>
                        <div class="upload-box" id="uploadBox">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p><strong>Click to upload</strong> or drag and drop</p>
                            <div class="file-types">PDF, JPG, PNG up to 5MB</div>
                            <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="fileName"></div>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_verification" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Verification Request
                    </button>
                </form>
            <?php elseif($existing_request && $existing_request['status'] == 'pending'): ?>
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
            <?php else: ?>
                <div class="status-card status-not-submitted">
                    <div class="status-icon"><i class="fas fa-shield-alt"></i></div>
                    <h2>Verify Your Account</h2>
                    <p>Please submit a valid ID and your address to verify your residency in Palayan City.</p>
                    <p class="small"><i class="fas fa-clock"></i> Verification usually takes 1-3 business days.</p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Valid ID Type <span class="required-star">*</span></label>
                        <select name="id_type" required>
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
                        <input type="text" name="id_number" placeholder="Enter your ID number" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Barangay <span class="required-star">*</span></label>
                        <select name="barangay" required>
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
                            <input type="text" name="street" placeholder="e.g. 123 Mabini St.">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-pin"></i> City/Municipality</label>
                            <input type="text" name="city" value="Palayan City" readonly class="disabled-field">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map"></i> Province</label>
                            <input type="text" name="province" value="Nueva Ecija" readonly class="disabled-field">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                            <input type="text" name="postal_code" value="3132" readonly class="disabled-field">
                        </div>
                    </div>
                    
                    <div class="upload-section">
                        <label class="upload-label"><i class="fas fa-upload"></i> Upload Valid ID <span class="required-star">*</span></label>
                        <div class="upload-box" id="uploadBox">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p><strong>Click to upload</strong> or drag and drop</p>
                            <div class="file-types">PDF, JPG, PNG up to 5MB</div>
                            <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="file-name" id="fileName"></div>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_verification" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Verification Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// ========== FILE UPLOAD DISPLAY ==========
const uploadBox = document.getElementById('uploadBox');
const fileInput = uploadBox.querySelector('input[type="file"]');
const fileNameDisplay = document.getElementById('fileName');

fileInput.addEventListener('change', function() { vbgty67
    if (this.files && this.files[0]) {
        const name = this.files[0].name;
        const size = (this.files[0].size / 1024 / 1024).toFixed(2);
        fileNameDisplay.textContent = `📎 ${name} (${size} MB)`;
        fileNameDisplay.classList.add('show');
        uploadBox.classList.add('has-file');
    } else {
        fileNameDisplay.classList.remove('show');
        uploadBox.classList.remove('has-file');
    }
});

// Drag and drop visual feedback
uploadBox.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = 'var(--gold)';
    this.style.background = 'rgba(201, 168, 108, 0.08)';
});

uploadBox.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.background = '';
});

uploadBox.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.background = '';
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    }
});
</script>
</body>
</html>
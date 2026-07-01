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
    $barangay_name = sanitize($_POST['barangay_name']);
    $complete_address = sanitize($_POST['complete_address']);
    
    // Validate required fields
    if (empty($id_type) || empty($id_number) || empty($barangay_name) || empty($complete_address)) {
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
            
            // Upload Proof of Residency
            if (isset($_FILES['proof_of_residency']) && $_FILES['proof_of_residency']['error'] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['proof_of_residency']['name'],
                    'type' => $_FILES['proof_of_residency']['type'],
                    'tmp_name' => $_FILES['proof_of_residency']['tmp_name'],
                    'error' => $_FILES['proof_of_residency']['error'],
                    'size' => $_FILES['proof_of_residency']['size']
                ];
                $proof_path = $uploader->upload($file, 'verification');
            } else {
                $error = "Please upload proof of residency.";
            }
            
            // Upload Barangay Certificate (optional)
            $barangay_path = null;
            if (isset($_FILES['barangay_certificate']) && $_FILES['barangay_certificate']['error'] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['barangay_certificate']['name'],
                    'type' => $_FILES['barangay_certificate']['type'],
                    'tmp_name' => $_FILES['barangay_certificate']['tmp_name'],
                    'error' => $_FILES['barangay_certificate']['error'],
                    'size' => $_FILES['barangay_certificate']['size']
                ];
                $barangay_path = $uploader->upload($file, 'verification');
            }
            
            if (empty($error)) {
                // Insert verification request
                $query = "INSERT INTO verification_requests 
                          (user_id, id_type, id_number, id_file_path, proof_of_residency_path, 
                           barangay_certificate_path, barangay_name, complete_address, remarks) 
                          VALUES (:user_id, :id_type, :id_number, :id_path, :proof_path, 
                                  :barangay_path, :barangay_name, :address, :remarks)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':id_type', $id_type);
                $stmt->bindParam(':id_number', $id_number);
                $stmt->bindParam(':id_path', $id_file_path);
                $stmt->bindParam(':proof_path', $proof_path);
                $stmt->bindParam(':barangay_path', $barangay_path);
                $stmt->bindParam(':barangay_name', $barangay_name);
                $stmt->bindParam(':address', $complete_address);
                
                $remarks = "Resident of Barangay $barangay_name";
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
            line-height: 1.5;
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
            padding: 28px 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            max-width: 800px;
            margin: 0 auto;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .verification-card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        .verification-card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .verification-card .card-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2.5rem;
        }
        .status-pending .status-icon { background: rgba(255, 152, 0, 0.15); color: var(--color-warning); }
        .status-verified .status-icon { background: rgba(76, 175, 80, 0.15); color: var(--color-success); }
        .status-rejected .status-icon { background: rgba(244, 67, 54, 0.15); color: var(--color-danger); }
        .status-not-submitted .status-icon { background: rgba(127, 3, 3, 0.08); color: var(--maroon-light); }
        
        .status-card h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .status-card p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .status-card .small {
            font-size: 0.75rem;
            margin-top: 8px;
        }

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
            color: var(--maroon-light);
            margin-right: 5px;
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
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }

        /* Document Upload */
        .document-upload {
            background: var(--badge-bg);
            padding: 16px 18px;
            border-radius: var(--radius-element);
            margin-bottom: 16px;
            border: 2px dashed var(--border-color);
            transition: all 0.3s;
        }
        .document-upload:hover {
            border-color: var(--maroon-light);
        }
        .document-upload label {
            font-weight: 600;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 8px;
            font-size: 0.82rem;
        }
        .document-upload label i {
            color: var(--maroon-light);
            margin-right: 5px;
        }
        .document-upload input[type="file"] {
            width: 100%;
            padding: 8px 0;
            color: var(--text-primary);
            cursor: pointer;
        }
        .document-upload small {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 0.7rem;
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
            .main-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .verification-card { padding: 20px; margin: 0 10px; }
            .status-card { padding: 20px; }
            .status-icon { width: 60px; height: 60px; font-size: 2rem; }
            .document-upload { padding: 12px 14px; }
            .btn-submit { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .verification-card { padding: 16px; }
            .form-group input, .form-group select, .form-group textarea { padding: 8px 12px; font-size: 0.8rem; }
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
                <h1><i class="fas fa-shield-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> Verify Your Residency</h1>
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
                <i class="fas fa-id-card"></i>
                <h2>Residency Verification</h2>
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
                    <p class="small"><i class="fas fa-check-circle" style="color: var(--color-success);"></i> Verified Resident</p>
                </div>
            <?php elseif($verification_status == 'rejected'): ?>
                <div class="status-card status-rejected">
                    <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                    <h2>Verification Failed</h2>
                    <p>Your verification request was rejected. Please submit new documents.</p>
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
                        <select name="barangay_name" required>
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
                    
                    <div class="form-group">
                        <label><i class="fas fa-home"></i> Complete Address <span class="required-star">*</span></label>
                        <textarea name="complete_address" rows="2" placeholder="Enter your complete address in Palayan City" required></textarea>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-id-card"></i> Valid ID (Photo/Scan) <span class="required-star">*</span></label>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Upload a clear photo or scan of your valid ID (PDF, JPG, PNG up to 5MB)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-alt"></i> Proof of Residency <span class="required-star">*</span></label>
                        <input type="file" name="proof_of_residency" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Utility bill (electric, water, internet) or barangay certificate</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-signature"></i> Barangay Certificate (Optional)</label>
                        <input type="file" name="barangay_certificate" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Barangay Clearance or Certificate of Residency (recommended)</small>
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
                    <p class="small"><i class="far fa-calendar-alt"></i> Submitted on: <?php echo date('F d, Y', strtotime($existing_request['submitted_date'])); ?></p>
                    <p class="small"><i class="fas fa-info-circle"></i> Please check back later or wait for email notification.</p>
                </div>
            <?php else: ?>
                <div class="status-card status-not-submitted">
                    <div class="status-icon"><i class="fas fa-shield-alt"></i></div>
                    <h2>Verify Your Account</h2>
                    <p>Please submit the required documents to verify your residency in Palayan City.</p>
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
                        <select name="barangay_name" required>
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
                    
                    <div class="form-group">
                        <label><i class="fas fa-home"></i> Complete Address <span class="required-star">*</span></label>
                        <textarea name="complete_address" rows="2" placeholder="Enter your complete address in Palayan City" required></textarea>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-id-card"></i> Valid ID (Photo/Scan) <span class="required-star">*</span></label>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Upload a clear photo or scan of your valid ID (PDF, JPG, PNG up to 5MB)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-alt"></i> Proof of Residency <span class="required-star">*</span></label>
                        <input type="file" name="proof_of_residency" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Utility bill (electric, water, internet) or barangay certificate</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-signature"></i> Barangay Certificate (Optional)</label>
                        <input type="file" name="barangay_certificate" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Barangay Clearance or Certificate of Residency (recommended)</small>
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
</script>
</body>
</html>
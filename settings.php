<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get current user data
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get additional profile data based on role
$profile_data = [];
if ($user['role'] == 'resident') {
    $profile_query = "SELECT * FROM residents WHERE user_id = :user_id";
    $profile_stmt = $db->prepare($profile_query);
    $profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $profile_stmt->execute();
    $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($user['role'] == 'staff' || $user['role'] == 'admin') {
    $profile_query = "SELECT * FROM staff WHERE user_id = :user_id";
    $profile_stmt = $db->prepare($profile_query);
    $profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $profile_stmt->execute();
    $profile_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle profile update
$profile_success = '';
$profile_error = '';

if (isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $contact_number = sanitize($_POST['contact_number']);
    $address = sanitize($_POST['address']);
    
    // Check if email already exists for other users
    $check_query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $profile_error = "Email already exists in the system.";
    } else {
        $update_query = "UPDATE users SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        contact_number = :contact_number, 
                        address = :address 
                        WHERE user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':contact_number', $contact_number);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['full_name'] = $first_name . ' ' . $last_name;
            $profile_success = "Profile updated successfully!";
            
            // Refresh user data
            $user_stmt->execute();
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $profile_error = "Failed to update profile.";
        }
    }
}

// Handle password change
$password_success = '';
$password_error = '';

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE user_id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $password_success = "Password changed successfully!";
                } else {
                    $password_error = "Failed to change password.";
                }
            } else {
                $password_error = "New password must be at least 8 characters long.";
            }
        } else {
            $password_error = "New passwords do not match.";
        }
    } else {
        $password_error = "Current password is incorrect.";
    }
}

// Handle notification settings
$notification_success = '';

if (isset($_POST['save_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
    $status_updates = isset($_POST['status_updates']) ? 1 : 0;
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    $notification_success = "Notification preferences saved!";
}

// Handle account deletion
if (isset($_POST['delete_account']) && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'DELETE') {
    $db->beginTransaction();
    
    try {
        if ($user['role'] == 'resident') {
            $delete_resident = "DELETE FROM residents WHERE user_id = :user_id";
            $stmt = $db->prepare($delete_resident);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
        } elseif ($user['role'] == 'staff' || $user['role'] == 'admin') {
            $delete_staff = "DELETE FROM staff WHERE user_id = :user_id";
            $stmt = $db->prepare($delete_staff);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
        }
        
        $delete_notif = "DELETE FROM notifications WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_notif);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $delete_docs = "DELETE FROM documents WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_docs);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $delete_user = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_user);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $db->commit();
        
        session_destroy();
        redirect('register.php?deleted=1');
        
    } catch (Exception $e) {
        $db->rollBack();
        $profile_error = "Failed to delete account: " . $e->getMessage();
    }
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $log_query = "INSERT INTO audit_logs (user_id, action, ip_address) 
                  VALUES (:user_id, 'logout', :ip)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    
    session_destroy();
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo file_exists(__DIR__ . '/css/portal-layout.css') ? filemtime(__DIR__ . '/css/portal-layout.css') : time(); ?>">
    <?php if (isStaff() || isAdmin()): ?>
    <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo file_exists(__DIR__ . '/css/staff-portal.css') ? filemtime(__DIR__ . '/css/staff-portal.css') : time(); ?>">
    <?php endif; ?>
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
            align-items: flex-start;
        }

        .settings-sidebar {
            background: var(--ref-card, #FFFFFF);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            position: sticky;
            top: 20px;
        }

        .user-info {
            padding: 26px 20px;
            text-align: center;
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%);
            color: white;
        }

        .user-avatar {
            width: 76px;
            height: 76px;
            background: #FFFFFF;
            color: #7F0303;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 1.6rem;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .user-info h3 {
            margin: 0 0 4px 0;
            font-size: 1.1rem;
            font-weight: 800;
            color: #FFFFFF;
        }

        .user-info p {
            margin: 0;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
        }

        .settings-menu {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .settings-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            color: var(--ref-text, #191D1E);
            text-decoration: none;
            border-radius: 12px;
            font-size: 0.84rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .settings-menu-item i {
            width: 20px;
            font-size: 0.95rem;
            color: var(--ref-muted, #71818B);
            transition: color 0.2s ease;
        }

        .settings-menu-item:hover {
            background: rgba(127, 3, 3, 0.06);
            color: #7F0303;
        }

        .settings-menu-item:hover i {
            color: #7F0303;
        }

        .settings-menu-item.active {
            background: #7F0303;
            color: #FFFFFF;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .settings-menu-item.active i {
            color: #FFFFFF;
        }

        .logout-item {
            margin-top: 10px;
            border-top: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            padding-top: 12px;
            color: #DC2626;
        }

        .logout-item i {
            color: #DC2626;
        }

        .logout-item:hover {
            background: #FEE2E2;
            color: #991B1B;
        }

        /* Settings Content */
        .settings-content {
            background: var(--ref-card, #FFFFFF);
            border-radius: 20px;
            padding: 32px 36px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .section-title {
            color: var(--ref-text, #191D1E);
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #7F0303;
            font-size: 1.25rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 0.84rem;
            color: var(--ref-text, #191D1E);
        }

        .form-group label i {
            color: #7F0303;
            margin-right: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 16px;
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.15));
            border-radius: 10px;
            font-size: 0.88rem;
            background: var(--ref-card, #FFFFFF);
            color: var(--ref-text, #191D1E);
            transition: all 0.2s ease;
            outline: none;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #7F0303;
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }

        .form-group input:disabled,
        .form-group select:disabled {
            background: rgba(0, 0, 0, 0.03);
            color: var(--ref-muted, #71818B);
            cursor: not-allowed;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--ref-muted, #71818B);
            font-size: 0.76rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .btn-save {
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%);
            color: white;
            padding: 11px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #9A1515 0%, #B72222 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.35);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* Notification Items */
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: var(--ref-canvas, #E4EFE7);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.05));
        }

        .notification-info h4 {
            margin: 0 0 4px 0;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ref-text, #191D1E);
        }

        .notification-info p {
            margin: 0;
            color: var(--ref-muted, #71818B);
            font-size: 0.78rem;
        }

        /* Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #CBD5E1;
            transition: .3s;
            border-radius: 26px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background-color: #7F0303;
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        /* Danger Zone */
        .danger-zone {
            margin-top: 36px;
            padding: 22px;
            background: #FEF2F2;
            border-radius: 14px;
            border: 1px solid #FCA5A5;
        }

        .danger-zone h3 {
            color: #DC2626;
            margin: 0 0 8px 0;
            font-size: 1.05rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .danger-zone p {
            color: #7F1D1D;
            font-size: 0.82rem;
            margin: 0 0 16px 0;
        }

        .btn-danger {
            background: #DC2626;
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            border: none;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--ref-card, #FFFFFF);
            padding: 30px;
            border-radius: 20px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 18px;
        }

        .btn-secondary {
            background: var(--ref-canvas, #E4EFE7);
            color: var(--ref-text, #191D1E);
            border: 1px solid var(--ref-border, rgba(0,0,0,0.1));
            padding: 9px 22px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* Dark Mode Overrides */
        [data-theme="dark"] .settings-sidebar,
        [data-theme="dark"] .settings-content,
        [data-theme="dark"] .modal-content {
            background: #141E22 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }

        [data-theme="dark"] .notification-item {
            background: #0C1417 !important;
            border-color: rgba(255, 255, 255, 0.06) !important;
        }

        [data-theme="dark"] .form-group input,
        [data-theme="dark"] .form-group select,
        [data-theme="dark"] .form-group textarea {
            background: #0C1417 !important;
            color: #E5EFF2 !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }

        [data-theme="dark"] .danger-zone {
            background: rgba(220, 38, 38, 0.12) !important;
            border-color: rgba(220, 38, 38, 0.3) !important;
        }

        [data-theme="dark"] .danger-zone p {
            color: #FCA5A5 !important;
        }

        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .settings-sidebar {
                position: static;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar -->
        <?php if (isStaff() || isAdmin()): ?>
            <?php include __DIR__ . '/staff/includes/staff-sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident/Staff Portal Header -->
            <?php if (isStaff() || isAdmin()): ?>
                <?php include __DIR__ . '/staff/includes/staff-header.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/portal-header.php'; ?>
            <?php endif; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Account Settings & Preferences';
            $banner_subtitle = 'Manage your account security, personal profile information, and portal preferences';
            $banner_icon = 'fa-cog';
            include __DIR__ . '/includes/portal-banner.php';
            ?>

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                            <?php else: ?>
                                <span><?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p><i class="fas fa-id-badge" style="color: #D8BA98; margin-right: 4px;"></i> <?php echo ucfirst($user['role']); ?></p>
                    </div>

                    <div class="settings-menu">
                        <a href="#" class="settings-menu-item active" data-section="profile">
                            <i class="fas fa-user-circle"></i>
                            <span>Profile Information</span>
                        </a>
                        <a href="#" class="settings-menu-item" data-section="password">
                            <i class="fas fa-lock"></i>
                            <span>Password & Security</span>
                        </a>
                        <a href="#" class="settings-menu-item" data-section="notifications">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <a href="#" class="settings-menu-item" data-section="preferences">
                            <i class="fas fa-sliders-h"></i>
                            <span>Preferences</span>
                        </a>
                        <a href="#" class="settings-menu-item" data-section="privacy">
                            <i class="fas fa-shield-alt"></i>
                            <span>Privacy</span>
                        </a>
                        <a href="?logout=1" class="settings-menu-item logout-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Success/Error Messages -->
                    <?php if($profile_success): ?>
                        <div class="alert alert-success"><?php echo $profile_success; ?></div>
                    <?php endif; ?>
                    
                    <?php if($profile_error): ?>
                        <div class="alert alert-error"><?php echo $profile_error; ?></div>
                    <?php endif; ?>
                    
                    <?php if($password_success): ?>
                        <div class="alert alert-success"><?php echo $password_success; ?></div>
                    <?php endif; ?>
                    
                    <?php if($password_error): ?>
                        <div class="alert alert-error"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    
                    <?php if($notification_success): ?>
                        <div class="alert alert-success"><?php echo $notification_success; ?></div>
                    <?php endif; ?>

                    <!-- Profile Section -->
                    <div id="profile-section" class="settings-section active">
                        <h2 class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Profile Information
                        </h2>

                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> First Name</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Contact Number</label>
                                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Address</label>
                                <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small>Username cannot be changed</small>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> Account Role</label>
                                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            </div>

                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>

                        <!-- Additional resident fields if applicable -->
                        <?php if($user['role'] == 'resident' && $profile_data): ?>
                        <div style="margin-top: 40px;">
                            <h3 class="section-title" style="font-size: 1.15rem;">
                                <i class="fas fa-users"></i>
                                Additional Resident Details
                            </h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="text" value="<?php echo $profile_data['date_of_birth'] ?? 'Not set'; ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Gender</label>
                                    <input type="text" value="<?php echo $profile_data['gender'] ?? 'Not set'; ?>" disabled>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Civil Status</label>
                                    <input type="text" value="<?php echo $profile_data['civil_status'] ?? 'Not set'; ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <input type="text" value="<?php echo $profile_data['occupation'] ?? 'Not set'; ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Password Section -->
                    <div id="password-section" class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-lock"></i>
                            Password & Security
                        </h2>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> New Password</label>
                                    <input type="password" name="new_password" required>
                                    <small>Minimum 8 characters</small>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                                    <input type="password" name="confirm_password" required>
                                </div>
                            </div>

                            <button type="submit" name="change_password" class="btn-save">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>

                        <!-- Last Login Info -->
                        <div style="margin-top: 36px; padding: 18px 20px; background: var(--ref-canvas, #E4EFE7); border-radius: 12px; border: 1px solid var(--ref-border, rgba(0,0,0,0.06));">
                            <h4 style="color: var(--ref-text, #191D1E); margin: 0 0 10px 0; font-size: 0.92rem; font-weight: 700;">Recent Security Log</h4>
                            <p style="margin: 0 0 6px 0; font-size: 0.82rem; color: var(--ref-muted, #71818B);"><i class="fas fa-clock" style="color: #7F0303; margin-right: 6px;"></i> Last login: <?php echo isset($user['last_login']) ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'First login'; ?></p>
                            <p style="margin: 0; font-size: 0.82rem; color: var(--ref-muted, #71818B);"><i class="fas fa-network-wired" style="color: #7F0303; margin-right: 6px;"></i> Current IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></p>
                        </div>
                    </div>

                    <!-- Notifications Section -->
                    <div id="notifications-section" class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-bell"></i>
                            Notification Preferences
                        </h2>

                        <form method="POST" action="">
                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Email Notifications</h4>
                                    <p>Receive updates and alerts via email</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>SMS Notifications</h4>
                                    <p>Receive text messages for important updates</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="sms_notifications" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Status Updates</h4>
                                    <p>Notifications when your requests are updated</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="status_updates" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Newsletter & Program Bulletins</h4>
                                    <p>Receive updates about new programs and community services</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="newsletter">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" name="save_notifications" class="btn-save" style="margin-top: 14px;">
                                <i class="fas fa-save"></i> Save Notification Preferences
                            </button>
                        </form>
                    </div>

                    <!-- Preferences Section -->
                    <div id="preferences-section" class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-sliders-h"></i>
                            System Preferences
                        </h2>

                        <form>
                            <div class="form-group">
                                <label><i class="fas fa-language"></i> Language</label>
                                <select>
                                    <option value="en">English (Default)</option>
                                    <option value="fil">Filipino</option>
                                    <option value="ceb">Cebuano</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Timezone</label>
                                <select>
                                    <option value="Asia/Manila" selected>Asia/Manila (GMT+8)</option>
                                </select>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Compact View</h4>
                                    <p>Display denser items per table page</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="button" class="btn-save" onclick="alert('Preferences saved successfully!');" style="margin-top: 14px;">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </form>
                    </div>

                    <!-- Privacy Section -->
                    <div id="privacy-section" class="settings-section">
                        <h2 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Privacy Settings
                        </h2>

                        <form>
                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Profile Visibility</h4>
                                    <p>Allow authorized caseworkers to view your citizen profile</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Activity Status</h4>
                                    <p>Show when you are currently active on the portal</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </form>

                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>Once you delete your account, there is no going back. All submitted applications and records will be deleted.</p>
                            <button type="button" class="btn-danger" onclick="showDeleteModal()">
                                <i class="fas fa-trash"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="color: #DC2626; margin: 0 0 12px 0; font-weight: 800;"><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
            <p style="font-size: 0.84rem; color: var(--ref-muted, #71818B); margin: 0 0 16px 0;">This action is permanent and cannot be undone. All your personal data and documents will be permanently removed.</p>
            <p style="font-size: 0.84rem; font-weight: 700; margin: 0 0 10px 0;">Type <strong>DELETE</strong> to confirm:</p>
            <form method="POST" id="deleteForm">
                <input type="text" name="confirm_delete" placeholder="DELETE" required style="width: 100%; padding: 10px; border: 1px solid var(--ref-border); border-radius: 8px; text-align: center; font-weight: 700; margin-bottom: 16px; outline: none;">
                <div class="modal-actions">
                    <button type="button" onclick="closeDeleteModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" name="delete_account" class="btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.settings-menu-item[data-section]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelectorAll('.settings-menu-item').forEach(i => i.classList.remove('active'));
                document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
                
                this.classList.add('active');
                
                const sectionId = this.getAttribute('data-section');
                const targetSec = document.getElementById(sectionId + '-section');
                if (targetSec) targetSec.classList.add('active');
            });
        });

        function showDeleteModal() {
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        };

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Password match validation
        document.querySelector('form[action=""][method="POST"]')?.addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'change_password') {
                const newPass = document.querySelector('input[name="new_password"]').value;
                const confirmPass = document.querySelector('input[name="confirm_password"]').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                }
            }
        });
    </script>

    <!-- Portal Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

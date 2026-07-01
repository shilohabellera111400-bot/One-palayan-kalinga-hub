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
    
    // You would need a user_settings table for this
    // For now, we'll just show a success message
    $notification_success = "Notification preferences saved!";
}

// Handle account deletion
if (isset($_POST['delete_account']) && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'DELETE') {
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Delete related records based on role
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
        
        // Delete notifications
        $delete_notif = "DELETE FROM notifications WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_notif);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        // Delete documents (files will remain, but you might want to delete them too)
        $delete_docs = "DELETE FROM documents WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_docs);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        // Finally delete user
        $delete_user = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($delete_user);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $db->commit();
        
        // Logout and redirect
        session_destroy();
        redirect('register.php?deleted=1');
        
    } catch (Exception $e) {
        $db->rollBack();
        $profile_error = "Failed to delete account: " . $e->getMessage();
    }
}

// Handle logout (separate endpoint)
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    // Log logout action
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
    <title>Settings - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            padding: 40px 0;
            background: #f9f9f9;
            min-height: calc(100vh - 80px);
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h1 {
            color: #003366;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .settings-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* Settings Sidebar */
        .settings-sidebar {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .user-info {
            padding: 30px 20px;
            text-align: center;
            background: linear-gradient(135deg, #003366 0%, #004d99 100%);
            color: white;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: #ff6600;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: white;
            border: 3px solid white;
        }

        .user-info h3 {
            margin-bottom: 5px;
        }

        .user-info p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .settings-menu {
            padding: 20px 0;
        }

        .settings-menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .settings-menu-item:hover {
            background: #f5f5f5;
            border-left-color: #ff6600;
        }

        .settings-menu-item.active {
            background: #f0f7ff;
            border-left-color: #003366;
            color: #003366;
            font-weight: 600;
        }

        .settings-menu-item i {
            width: 20px;
            color: #ff6600;
        }

        .settings-menu-item.active i {
            color: #003366;
        }

        .logout-item {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            color: #f44336;
        }

        .logout-item i {
            color: #f44336;
        }

        /* Settings Content */
        .settings-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .section-title {
            color: #003366;
            font-size: 1.5rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #ff6600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group label i {
            color: #ff6600;
            width: 20px;
            margin-right: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            background: #003366;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-save:hover {
            background: #002244;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,51,102,0.3);
        }

        .btn-danger {
            background: #f44336;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Notification Toggles */
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .notification-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .notification-info p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
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
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #003366;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Danger Zone */
        .danger-zone {
            margin-top: 40px;
            padding: 20px;
            background: #fff3f3;
            border-radius: 5px;
            border: 1px solid #fcc;
        }

        .danger-zone h3 {
            color: #f44336;
            margin-bottom: 15px;
        }

        .danger-zone p {
            color: #666;
            margin-bottom: 20px;
        }

        /* Modal */
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
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-content h3 {
            color: #f44336;
            margin-bottom: 15px;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-content input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .settings-sidebar {
                position: static;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="settings-container">
        <div class="container">
            <!-- Page Header -->
            <div class="settings-header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
                <p>Manage your account settings, profile information, and preferences</p>
            </div>

            <!-- Settings Grid -->
            <div class="settings-grid">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p><?php echo ucfirst($user['role']); ?></p>
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
                                <label><i class="fas fa-badge"></i> Account Type</label>
                                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            </div>

                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>

                        <!-- Additional resident fields if applicable -->
                        <?php if($user['role'] == 'resident' && $profile_data): ?>
                        <div style="margin-top: 40px;">
                            <h3 class="section-title" style="font-size: 1.2rem;">
                                <i class="fas fa-users"></i>
                                Additional Information
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
                        <div style="margin-top: 40px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
                            <h4 style="color: #333; margin-bottom: 15px;">Recent Activity</h4>
                            <p><i class="fas fa-clock"></i> Last login: <?php echo isset($user['last_login']) ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'First login'; ?></p>
                            <p><i class="fas fa-network-wired"></i> Current IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
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
                                    <input type="checkbox" name="sms_notifications">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Appointment Reminders</h4>
                                    <p>Get reminded about upcoming appointments</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="appointment_reminders" checked>
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
                                    <h4>Newsletter</h4>
                                    <p>Receive updates about new programs and services</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="newsletter">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" name="save_notifications" class="btn-save">
                                <i class="fas fa-save"></i> Save Preferences
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
                                    <option value="en">English</option>
                                    <option value="fil">Filipino</option>
                                    <option value="ceb">Cebuano</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Theme</label>
                                <select>
                                    <option value="light">Light Mode</option>
                                    <option value="dark">Dark Mode</option>
                                    <option value="system">System Default</option>
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
                                    <p>Show more items per page</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" class="btn-save">
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
                                    <p>Allow others to see your profile</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Activity Status</h4>
                                    <p>Show when you're active</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Data Sharing</h4>
                                    <p>Allow anonymous usage data collection</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save Privacy Settings
                            </button>
                        </form>

                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>Once you delete your account, there is no going back. Please be certain.</p>
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
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
            <p>This action is permanent and cannot be undone. All your data will be permanently removed.</p>
            <p>Type <strong>DELETE</strong> to confirm:</p>
            <form method="POST" id="deleteForm">
                <input type="text" name="confirm_delete" placeholder="DELETE" required>
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
                
                // Remove active class from all menu items and sections
                document.querySelectorAll('.settings-menu-item').forEach(i => i.classList.remove('active'));
                document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Show corresponding section
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId + '-section').classList.add('active');
            });
        });

        // Delete modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }

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
            if (e.submitter.name === 'change_password') {
                const newPass = document.querySelector('input[name="new_password"]').value;
                const confirmPass = document.querySelector('input[name="confirm_password"]').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                }
            }
        });
    </script>

    <script src="js/main.js"></script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
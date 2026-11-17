<?php
// admin/profile.php - System Administrator Profile & Security Settings
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $contact_number = sanitize($_POST['contact_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "First name, last name, and email address are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } else {
        // Check if email belongs to another user
        $check_stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id LIMIT 1");
        $check_stmt->execute([':email' => $email, ':user_id' => $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $error = "This email is already in use by another user.";
        } else {
            $update_stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, contact_number = :contact_number, address = :address WHERE user_id = :user_id");
            $saved = $update_stmt->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':email' => $email,
                ':contact_number' => $contact_number,
                ':address' => $address,
                ':user_id' => $user_id
            ]);

            if ($saved) {
                $_SESSION['full_name'] = trim($first_name . ' ' . $last_name);
                $_SESSION['email'] = $email;
                $success = "Profile details updated successfully!";
            } else {
                $error = "Failed to update profile details. Please try again.";
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Fetch current user hash
    $pwd_stmt = $db->prepare("SELECT password FROM users WHERE user_id = :user_id LIMIT 1");
    $pwd_stmt->execute([':user_id' => $user_id]);
    $current_hash = $pwd_stmt->fetchColumn();

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif (!password_verify($current_password, $current_hash)) {
        $error = "Your current password does not match our records.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation password do not match.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $up_pwd = $db->prepare("UPDATE users SET password = :password WHERE user_id = :user_id");
        if ($up_pwd->execute([':password' => $new_hash, ':user_id' => $user_id])) {
            $success = "Password changed successfully! Keep your credentials safe.";
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}

// Handle Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($file['size'] > $max_size) {
            $error = "Avatar image must be smaller than 5MB.";
        } elseif (!in_array($real_mime, $allowed_mimes)) {
            $error = "Only JPG, PNG, WEBP, and GIF images are allowed.";
        } else {
            $upload_dir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
            $destination = $upload_dir . $filename;
            $db_path = 'uploads/profiles/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $db->prepare("UPDATE users SET profile_picture = :pic WHERE user_id = :user_id")
                   ->execute([':pic' => $db_path, ':user_id' => $user_id]);
                $_SESSION['profile_picture'] = $db_path;
                $success = "Administrator avatar updated successfully!";
            } else {
                $error = "Could not upload profile picture. Check folder permissions.";
            }
        }
    } else {
        $error = "Please choose a valid image file to upload.";
    }
}

// Handle Remove Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    $db->prepare("UPDATE users SET profile_picture = NULL WHERE user_id = :user_id")
       ->execute([':user_id' => $user_id]);
    unset($_SESSION['profile_picture']);
    $success = "Profile photo removed.";
}

// Refresh current admin data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$avatar_src = '';
if (!empty($admin['profile_picture'])) {
    $avatar_src = '../' . ltrim($admin['profile_picture'], '/');
}

$page_title = 'Administrator Profile & Security';
$active_sidebar = '';
$active_nav = 'profile';

require_once __DIR__ . '/includes/admin-head.php';
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<style>
/* ========== ADMIN PROFILE PAGE STYLES ========== */
.profile-page-wrapper {
    padding: 24px 28px 48px;
    max-width: 1320px;
    margin: 0 auto;
}

.profile-header-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 22px 28px;
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    border-radius: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.profile-banner-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.profile-banner-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, #FDE68A 0%, #D97706 100%);
    color: #78350F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.28);
    flex-shrink: 0;
}

.profile-banner-text h2 {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 4px;
    line-height: 1.2;
}

.profile-banner-text p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
}

.profile-banner-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-banner-dash {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 50px;
    background: #7F0303;
    color: #FFFFFF;
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 700;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.22);
}

.btn-banner-dash:hover {
    background: #9a1515;
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* Alert Boxes */
.profile-alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.88rem;
    font-weight: 600;
}

.profile-alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #059669;
}

.profile-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

/* Two-column Layout */
.profile-grid-layout {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: flex-start;
}

/* Card Common */
.profile-panel-card {
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, rgba(0,0,0,0.08));
    border-radius: 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
    padding: 24px;
    margin-bottom: 24px;
}

.panel-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.08));
}

.panel-title-row i {
    color: #7F0303;
    font-size: 1.15rem;
}

.panel-title-row h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-primary);
}

/* Avatar Box */
.profile-avatar-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 10px 0 16px;
}

.profile-avatar-ring {
    position: relative;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.profile-avatar-ring::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 3px dashed #D97706;
    animation: avatarSpin 28s linear infinite;
    pointer-events: none;
}

@keyframes avatarSpin {
    100% { transform: rotate(360deg); }
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #FFFFFF;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #FDE68A 0%, #D97706 100%);
    color: #78350F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.8rem;
    border: 3px solid #FFFFFF;
}

.profile-name-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 4px;
}

.profile-username-tag {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
    margin-bottom: 10px;
}

.profile-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    background: linear-gradient(135deg, #FDE68A 0%, #D97706 100%);
    color: #78350F;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
    margin-bottom: 16px;
}

/* Metadata List */
.profile-meta-list {
    list-style: none;
    padding: 0;
    margin: 0;
    border-top: 1px solid var(--border-color, rgba(0,0,0,0.08));
    padding-top: 14px;
}

.profile-meta-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 0.82rem;
}

.profile-meta-label {
    color: var(--text-muted);
    font-weight: 600;
}

.profile-meta-value {
    color: var(--text-primary);
    font-weight: 700;
}

/* Avatar Upload Form */
.avatar-form-wrapper {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px dashed var(--border-color, rgba(0,0,0,0.1));
    text-align: center;
}

.btn-file-select {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    background: var(--badge-bg, #F1F3F8);
    color: var(--text-primary);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid var(--border-color, rgba(0,0,0,0.1));
}

.btn-file-select:hover {
    background: rgba(127, 3, 3, 0.08);
    color: #7F0303;
    border-color: #7F0303;
}

.btn-avatar-submit {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    background: #7F0303;
    color: #FFFFFF;
    font-size: 0.78rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    margin-top: 8px;
    transition: all 0.2s ease;
}

.btn-avatar-submit:hover {
    background: #9a1515;
}

/* Form Groups */
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.form-group-full {
    margin-bottom: 16px;
}

.profile-form-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.profile-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.profile-input-icon {
    position: absolute;
    left: 14px;
    color: var(--text-muted);
    font-size: 0.95rem;
    pointer-events: none;
}

.profile-input-control {
    width: 100%;
    padding: 10px 14px 10px 42px;
    border-radius: 10px;
    border: 1.5px solid var(--border-color, rgba(0,0,0,0.12));
    background: var(--card-bg, #FFFFFF);
    color: var(--text-primary);
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.profile-input-control:focus {
    border-color: #7F0303;
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
}

.profile-input-control[readonly] {
    background: var(--badge-bg, rgba(0,0,0,0.03));
    color: var(--text-muted);
    cursor: not-allowed;
}

.btn-save-profile {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 26px;
    border-radius: 10px;
    background: #7F0303;
    color: #FFFFFF;
    border: none;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.2);
}

.btn-save-profile:hover {
    background: #9a1515;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.28);
}

/* Password Checklist */
.pwd-requirements {
    background: var(--badge-bg, #F8FAFC);
    border: 1px solid var(--border-color, rgba(0,0,0,0.08));
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
}

.pwd-requirements h5 {
    margin: 0 0 6px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-primary);
}

.pwd-requirements ul {
    margin: 0;
    padding-left: 18px;
    font-size: 0.78rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

@media (max-width: 992px) {
    .profile-grid-layout {
        grid-template-columns: 1fr;
    }
    .form-grid-2 {
        grid-template-columns: 1fr;
    }
    .profile-page-wrapper {
        padding: 16px;
    }
}
</style>

<div class="profile-page-wrapper">
    <!-- Top Hero Banner -->
    <div class="profile-header-banner">
        <div class="profile-banner-left">
            <div class="profile-banner-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="profile-banner-text">
                <h2>Administrator Profile &amp; Account</h2>
                <p>Manage your system administrator credentials, contact information, and security settings.</p>
            </div>
        </div>
        <div class="profile-banner-actions">
            <a href="dashboard.php" class="btn-banner-dash">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="settings.php" class="btn-banner-dash" style="background: transparent; color: var(--text-primary); border: 1px solid var(--border-color); box-shadow: none;">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
    </div>

    <!-- Alert Notices -->
    <?php if (!empty($success)): ?>
    <div class="profile-alert profile-alert-success">
        <i class="fas fa-check-circle" style="font-size: 1.15rem;"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="profile-alert profile-alert-error">
        <i class="fas fa-exclamation-circle" style="font-size: 1.15rem;"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- Two-column Grid -->
    <div class="profile-grid-layout">
        <!-- Left: Profile Summary & Avatar Card -->
        <div>
            <div class="profile-panel-card">
                <div class="profile-avatar-center">
                    <div class="profile-avatar-ring">
                        <?php if (!empty($avatar_src) && file_exists(__DIR__ . '/' . $avatar_src)): ?>
                            <img src="<?php echo htmlspecialchars($avatar_src); ?>?v=<?php echo time(); ?>" alt="Admin Avatar" class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="profile-name-title"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h3>
                    <span class="profile-username-tag">@<?php echo htmlspecialchars($admin['username']); ?></span>
                    <span class="profile-role-badge">
                        <i class="fas fa-shield-alt"></i> System Administrator
                    </span>
                </div>

                <ul class="profile-meta-list">
                    <li class="profile-meta-item">
                        <span class="profile-meta-label">User ID:</span>
                        <span class="profile-meta-value">#<?php echo (int)$admin['user_id']; ?></span>
                    </li>
                    <li class="profile-meta-item">
                        <span class="profile-meta-label">Account Status:</span>
                        <span class="profile-meta-value" style="color: #059669;">
                            <i class="fas fa-check-circle"></i> Active
                        </span>
                    </li>
                    <li class="profile-meta-item">
                        <span class="profile-meta-label">Role Scope:</span>
                        <span class="profile-meta-value">Super Admin</span>
                    </li>
                    <li class="profile-meta-item">
                        <span class="profile-meta-label">Registered Since:</span>
                        <span class="profile-meta-value"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></span>
                    </li>
                    <li class="profile-meta-item">
                        <span class="profile-meta-label">Last Login:</span>
                        <span class="profile-meta-value"><?php echo !empty($admin['last_login']) ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Active Session'; ?></span>
                    </li>
                </ul>

                <!-- Avatar Upload Action -->
                <div class="avatar-form-wrapper">
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="update_avatar" value="1">
                        <label for="avatarInput" class="btn-file-select">
                            <i class="fas fa-camera"></i> Change Photo
                        </label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display: none;" onchange="this.form.submit()">
                    </form>
                    <?php if (!empty($admin['profile_picture'])): ?>
                    <form method="POST" style="margin-top: 6px;">
                        <input type="hidden" name="remove_avatar" value="1">
                        <button type="submit" onclick="return confirm('Remove your profile photo?')" style="background: none; border: none; color: #dc2626; font-size: 0.74rem; font-weight: 700; cursor: pointer; text-decoration: underline;">
                            <i class="fas fa-trash-alt"></i> Remove Photo
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Profile Editing Forms -->
        <div>
            <!-- Card 1: Personal Details Form -->
            <div class="profile-panel-card">
                <div class="panel-title-row">
                    <i class="fas fa-user-edit"></i>
                    <h3>Personal &amp; Contact Details</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-grid-2">
                        <div>
                            <label class="profile-form-label">First Name *</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-user profile-input-icon"></i>
                                <input type="text" name="first_name" class="profile-input-control" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                            </div>
                        </div>
                        <div>
                            <label class="profile-form-label">Last Name *</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-user profile-input-icon"></i>
                                <input type="text" name="last_name" class="profile-input-control" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div>
                            <label class="profile-form-label">Username (System Identifier)</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-at profile-input-icon"></i>
                                <input type="text" class="profile-input-control" value="<?php echo htmlspecialchars($admin['username']); ?>" readonly title="Username cannot be altered">
                            </div>
                        </div>
                        <div>
                            <label class="profile-form-label">Email Address *</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-envelope profile-input-icon"></i>
                                <input type="email" name="email" class="profile-input-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div>
                            <label class="profile-form-label">Contact Number</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-phone profile-input-icon"></i>
                                <input type="text" name="contact_number" class="profile-input-control" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($admin['contact_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="profile-form-label">Office / Assignment Address</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-map-marker-alt profile-input-icon"></i>
                                <input type="text" name="address" class="profile-input-control" placeholder="City Hall, Palayan City" value="<?php echo htmlspecialchars($admin['address'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn-save-profile">
                            <i class="fas fa-save"></i> Save Profile Details
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card 2: Security & Password Update -->
            <div class="profile-panel-card">
                <div class="panel-title-row">
                    <i class="fas fa-lock"></i>
                    <h3>Security &amp; Change Password</h3>
                </div>

                <div class="pwd-requirements">
                    <h5><i class="fas fa-shield-alt" style="color: #D97706;"></i> Security Guidelines:</h5>
                    <ul>
                        <li>Must be at least 8 characters long.</li>
                        <li>Combine uppercase, lowercase, numbers, and symbols for high entropy.</li>
                        <li>Do not share your administrator password with anyone.</li>
                    </ul>
                </div>

                <form method="POST">
                    <input type="hidden" name="update_password" value="1">

                    <div class="form-group-full">
                        <label class="profile-form-label">Current Password *</label>
                        <div class="profile-input-wrap">
                            <i class="fas fa-key profile-input-icon"></i>
                            <input type="password" name="current_password" class="profile-input-control" placeholder="Enter your current password" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div>
                            <label class="profile-form-label">New Password *</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-lock profile-input-icon"></i>
                                <input type="password" name="new_password" class="profile-input-control" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                        <div>
                            <label class="profile-form-label">Confirm New Password *</label>
                            <div class="profile-input-wrap">
                                <i class="fas fa-check-double profile-input-icon"></i>
                                <input type="password" name="confirm_password" class="profile-input-control" placeholder="Repeat new password" required minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 10px;">
                        <button type="submit" class="btn-save-profile" style="background: #0F414A;">
                            <i class="fas fa-shield-alt"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>

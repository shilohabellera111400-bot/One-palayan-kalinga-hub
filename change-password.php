<?php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin can also change password
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    csrf_verify();
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif (!$uppercase || !$lowercase || !$number) {
        $error = "New password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } else {
        // Get current user's password hash
        $query = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_query = "UPDATE users SET password = :password WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                // Log the password change
                $log_query = "INSERT INTO audit_logs (user_id, action, description, ip_address) 
                              VALUES (:user_id, 'password_change', 'User changed their password', :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                $log_stmt->execute();
                
                $success = "Password changed successfully! Please login again with your new password.";
                
                // Optional: Logout user and redirect to login
                // session_destroy();
                // redirect('login.php?success=Password changed successfully. Please login with your new password.');
            } else {
                $error = "Failed to change password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// Get user data for display
$user_query = "SELECT first_name, last_name, email FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Change Password - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
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
            --gray-50: #E4EFE7;
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
            background: var(--ref-canvas, #E4EFE7) !important;
            color: var(--gray-700);
            line-height: 1.5;
        }

        /* ========== LAYOUT ========== */
        .app-container {
            display: flex;
            min-height: 100vh;
            background: var(--ref-canvas, #E4EFE7) !important;
            width: 100% !important;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1 1 0% !important;
            min-width: 0 !important;
            padding: 24px 32px;
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

        /* Change Password Card */
        .change-password-card {
            max-width: 550px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-card);
            padding: 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        .change-password-card h2 {
            color: var(--midnight);
            margin-bottom: 8px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
        }
        .card-subtitle {
            text-align: center;
            color: var(--gray-600);
            margin-bottom: 30px;
            font-size: 0.85rem;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.85rem;
        }
        .form-group label i {
            color: var(--maroon);
            margin-right: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-element);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127,3,3,0.1);
        }
        .password-strength {
            margin-top: 8px;
            font-size: 0.7rem;
        }
        .strength-bar {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        .strength-weak { background: #f44336; width: 25%; }
        .strength-fair { background: #ff9800; width: 50%; }
        .strength-good { background: #2196F3; width: 75%; }
        .strength-strong { background: #4caf50; width: 100%; }
        .btn-primary {
            width: 100%;
            background: var(--maroon);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .btn-secondary {
            display: inline-block;
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 12px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        .requirements {
            background: var(--gray-50);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-top: 20px;
        }
        .requirements h4 {
            font-size: 0.8rem;
            margin-bottom: 10px;
            color: var(--midnight);
        }
        .requirements ul {
            list-style: none;
            padding: 0;
        }
        .requirements li {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .requirements li i {
            width: 16px;
            font-size: 0.7rem;
        }
        .requirements li.valid i { color: #4caf50; }
        .requirements li.invalid i { color: #f44336; }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .main-content { padding: 16px 12px; }
            .change-password-card { padding: 24px; margin: 0 auto; width: 100%; }
            .action-buttons { flex-direction: column; }
            .btn-secondary { text-align: center; }
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

        <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Change Password & Security';
            $banner_subtitle = 'Update your credentials and maintain high security for your portal profile';
            $banner_icon = 'fa-key';
            $banner_actions = [
                ['label' => 'Account Settings', 'url' => 'settings.php', 'icon' => 'fa-cog', 'type' => 'primary'],
                ['label' => 'My Profile', 'url' => 'profile.php', 'icon' => 'fa-user', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>
        
        <div class="change-password-card">
            <h2><i class="fas fa-key"></i> Change Password</h2>
            <p class="card-subtitle">Update your password to keep your account secure</p>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Account:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
                (<?php echo htmlspecialchars($user['email']); ?>)
            </div>
            
            <form method="POST" id="passwordForm">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> New Password</label>
                    <input type="password" name="new_password" id="new_password" required onkeyup="checkPasswordStrength()">
                    <div class="password-strength">
                        <span id="strengthText">Password strength</span>
                        <div class="strength-bar">
                            <div class="strength-bar-fill" id="strengthBar"></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-double"></i> Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required onkeyup="checkPasswordMatch()">
                    <div id="matchMessage" style="font-size: 0.7rem; margin-top: 5px;"></div>
                </div>
                
                <div class="requirements">
                    <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
                    <ul>
                        <li id="lengthReq" class="invalid"><i class="fas fa-circle"></i> At least 8 characters</li>
                        <li id="upperReq" class="invalid"><i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)</li>
                        <li id="lowerReq" class="invalid"><i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)</li>
                        <li id="numberReq" class="invalid"><i class="fas fa-circle"></i> At least 1 number (0-9)</li>
                    </ul>
                </div>
                
                <button type="submit" name="change_password" class="btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Change Password
                </button>
                
                <div class="action-buttons">
                    <a href="profile.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    // Check requirements
    const lengthValid = password.length >= 8;
    const upperValid = /[A-Z]/.test(password);
    const lowerValid = /[a-z]/.test(password);
    const numberValid = /[0-9]/.test(password);
    
    // Update requirement indicators
    document.getElementById('lengthReq').className = lengthValid ? 'valid' : 'invalid';
    document.getElementById('upperReq').className = upperValid ? 'valid' : 'invalid';
    document.getElementById('lowerReq').className = lowerValid ? 'valid' : 'invalid';
    document.getElementById('numberReq').className = numberValid ? 'valid' : 'invalid';
    
    // Update icons
    if (lengthValid) document.getElementById('lengthReq').innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
    else document.getElementById('lengthReq').innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
    
    if (upperValid) document.getElementById('upperReq').innerHTML = '<i class="fas fa-check-circle"></i> At least 1 uppercase letter (A-Z)';
    else document.getElementById('upperReq').innerHTML = '<i class="fas fa-circle"></i> At least 1 uppercase letter (A-Z)';
    
    if (lowerValid) document.getElementById('lowerReq').innerHTML = '<i class="fas fa-check-circle"></i> At least 1 lowercase letter (a-z)';
    else document.getElementById('lowerReq').innerHTML = '<i class="fas fa-circle"></i> At least 1 lowercase letter (a-z)';
    
    if (numberValid) document.getElementById('numberReq').innerHTML = '<i class="fas fa-check-circle"></i> At least 1 number (0-9)';
    else document.getElementById('numberReq').innerHTML = '<i class="fas fa-circle"></i> At least 1 number (0-9)';
    
    // Calculate strength
    let strength = 0;
    if (lengthValid) strength++;
    if (upperValid) strength++;
    if (lowerValid) strength++;
    if (numberValid) strength++;
    
    // Update strength bar
    if (strength === 0) {
        strengthBar.className = 'strength-bar-fill';
        strengthBar.style.width = '0%';
        strengthText.innerHTML = 'Password strength: Very Weak';
        strengthText.style.color = '#999';
    } else if (strength === 1) {
        strengthBar.className = 'strength-bar-fill strength-weak';
        strengthBar.style.width = '25%';
        strengthText.innerHTML = 'Password strength: Weak';
        strengthText.style.color = '#f44336';
    } else if (strength === 2) {
        strengthBar.className = 'strength-bar-fill strength-fair';
        strengthBar.style.width = '50%';
        strengthText.innerHTML = 'Password strength: Fair';
        strengthText.style.color = '#ff9800';
    } else if (strength === 3) {
        strengthBar.className = 'strength-bar-fill strength-good';
        strengthBar.style.width = '75%';
        strengthText.innerHTML = 'Password strength: Good';
        strengthText.style.color = '#2196F3';
    } else {
        strengthBar.className = 'strength-bar-fill strength-strong';
        strengthBar.style.width = '100%';
        strengthText.innerHTML = 'Password strength: Strong';
        strengthText.style.color = '#4caf50';
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const matchMessage = document.getElementById('matchMessage');
    const submitBtn = document.getElementById('submitBtn');
    
    if (confirm === '') {
        matchMessage.innerHTML = '';
        matchMessage.style.color = '';
        submitBtn.disabled = false;
    } else if (password === confirm) {
        matchMessage.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
        matchMessage.style.color = '#4caf50';
        submitBtn.disabled = false;
    } else {
        matchMessage.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
        matchMessage.style.color = '#f44336';
        submitBtn.disabled = true;
    }
}

// Validate before submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
    }
    
    if (!/[A-Z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one uppercase letter!');
        return false;
    }
    
    if (!/[a-z]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one lowercase letter!');
        return false;
    }
    
    if (!/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one number!');
        return false;
    }
    
    return true;
});
</script>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

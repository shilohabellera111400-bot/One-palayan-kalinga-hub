<?php
require_once __DIR__ . '/config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$token = sanitize($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';
$token_valid = false;
$user_id = null;
$username = '';

if (empty($token)) {
    $error = "Wala kaming nakitang token sa pag-reset ng password.";
} else {
    $database = new Database();
    $db = $database->getConnection();
    
    // Find valid token in database
    $query = "SELECT r.*, u.first_name, u.last_name, u.username 
              FROM password_resets r 
              JOIN users u ON r.user_id = u.user_id 
              WHERE r.token = :token AND r.expires_at > NOW() 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        $token_valid = true;
        $user_id = $reset['user_id'];
        $username = $reset['username'];
    } else {
        $error = "Ang link sa pag-reset ng password ay hindi wasto o nag-expire na. Mangyaring humiling ng bagong link.";
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password']) && $token_valid) {
    csrf_verify();
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Password validation rules
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Mangyaring punan ang lahat ng mga patlang.";
    } elseif (strlen($new_password) < 8) {
        $error = "Ang bagong password ay dapat may hindi bababa sa 8 character.";
    } elseif (!$uppercase || !$lowercase || !$number) {
        $error = "Dapat naglalaman ang password ng kahit isang malaking titik (A-Z), maliit na titik (a-z), at numero (0-9).";
    } elseif ($new_password !== $confirm_password) {
        $error = "Hindi nagtutugma ang password at ang kumpirmasyon.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        try {
            $db->beginTransaction();
            
            // Update user password
            $update_query = "UPDATE users SET password = :password WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();
            
            // Delete reset token
            $delete_query = "DELETE FROM password_resets WHERE user_id = :user_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':user_id', $user_id);
            $delete_stmt->execute();
            
            // Insert audit log
            $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values, ip_address) 
                          VALUES (:user_id, 'password_reset', 'users', 'Reset password via email link', :ip)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':user_id', $user_id);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $log_stmt->bindParam(':ip', $ip);
            $log_stmt->execute();
            
            $db->commit();
            $success = "Matagumpay na na-reset ang iyong password! Maaari ka nang mag-log in gamit ang iyong bagong password.";
            $token_valid = false; // Hide form on success
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "May naganap na error habang nag-a-update ng password: " . $e->getMessage();
        }
    }
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .requirements {
            background: rgba(15, 65, 74, 0.04);
            border: 1px solid rgba(15, 65, 74, 0.08);
            border-radius: 12px;
            padding: 16px;
            margin-top: 15px;
        }
        [data-theme="dark"] .requirements {
            background: rgba(255, 255, 255, 0.02);
            border-color: rgba(255, 255, 255, 0.05);
        }
        .requirements h4 {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--midnight);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        [data-theme="dark"] .requirements h4 {
            color: var(--text-primary);
        }
        .requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .requirements li {
            font-size: 0.76rem;
            color: #717D92;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        .requirements li.valid {
            color: #059669;
            font-weight: 600;
        }
        .requirements li.valid i {
            color: #059669;
        }
        .requirements li.invalid i {
            color: #CBD0DC;
        }
        .password-strength {
            margin-top: 8px;
        }
        .password-strength span {
            font-size: 0.72rem;
            color: #717D92;
            font-weight: 500;
        }
        .strength-bar {
            height: 4px;
            background: #E4E7EF;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
        .strength-weak { background: #EF5350; }
        .strength-medium { background: #FFB74D; }
        .strength-strong { background: #66BB6A; }
    </style>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">

    <div class="animated-bg"></div>

    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Left Side - Brand Panel -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png" alt="Logo">
                    </div>
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>

                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h4>Reset ng Password</h4>
                            <p>Magtakda ng bagong secure na password sa iyong account</p>
                        </div>
                    </div>
                </div>

                <div class="brand-quote">
                    <p>"Serving Palayan City with excellence and compassion"</p>
                    <span>— CSWDO Palayan City</span>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="auth-form-panel">
                <div class="auth-header">
                    <h2>Reset Password</h2>
                    <p>Pakilagay ang iyong bagong password sa ibaba.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: #FFF5F5; border: 1.5px solid #FEB2B2; padding: 12px 16px; border-radius: 12px; color: #C53030;">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; background: #F0FDF4; border: 1.5px solid #A7F3D0; padding: 12px 16px; border-radius: 12px; color: #065F46;">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($token_valid): ?>
                    <form method="POST" action="" id="resetForm" onsubmit="return validateForm()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group" style="margin-bottom: 8px; color: #717D92; font-size: 0.8rem;">
                            <span>Resetting password for: <strong>@<?php echo htmlspecialchars($username); ?></strong></span>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password <span class="required" style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" onkeyup="checkPasswordStrength()">
                            </div>
                            <div class="password-strength">
                                <span id="strengthText">Password strength</span>
                                <div class="strength-bar">
                                    <div class="strength-bar-fill" id="strengthBar"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label><i class="fas fa-check-double"></i> Confirm Password <span class="required" style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" onkeyup="checkPasswordMatch()">
                            </div>
                            <div id="matchMessage" style="font-size: 0.72rem; margin-top: 5px;"></div>
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

                        <button type="submit" name="reset_password" id="submitBtn" class="btn-primary" style="margin-top: 20px; width: 100%; padding: 14px; background: #7F0303; color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s;" disabled>
                            UPDATE PASSWORD <i class="fas fa-save" style="margin-left: 8px;"></i>
                        </button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #E4E7E9; text-align: center;">
                    <p>Bumalik sa <a href="login.php" style="color: #7F0303; font-weight: 600; text-decoration: none;">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            const lengthValid = password.length >= 8;
            const upperValid = /[A-Z]/.test(password);
            const lowerValid = /[a-z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            
            // Update visual checkboxes
            updateReqStatus('lengthReq', lengthValid);
            updateReqStatus('upperReq', upperValid);
            updateReqStatus('lowerReq', lowerValid);
            updateReqStatus('numberReq', numberValid);
            
            // Calculate score
            let score = 0;
            if (lengthValid) score++;
            if (upperValid && lowerValid) score++;
            if (numberValid) score++;
            
            // Update bar
            strengthBar.className = 'strength-bar-fill';
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = 'Password strength';
            } else if (score === 1) {
                strengthBar.style.width = '33%';
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
            } else if (score === 2) {
                strengthBar.style.width = '66%';
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Moderate password';
            } else if (score === 3) {
                strengthBar.style.width = '100%';
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
            }
            
            validateAll();
        }
        
        function updateReqStatus(id, isValid) {
            const el = document.getElementById(id);
            if (isValid) {
                el.className = 'valid';
                el.querySelector('i').className = 'fas fa-check-circle';
            } else {
                el.className = 'invalid';
                el.querySelector('i').className = 'fas fa-circle';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const msg = document.getElementById('matchMessage');
            
            if (confirm.length === 0) {
                msg.textContent = '';
            } else if (password === confirm) {
                msg.textContent = 'Passwords match';
                msg.style.color = '#059669';
            } else {
                msg.textContent = 'Passwords do not match';
                msg.style.color = '#DC2626';
            }
            
            validateAll();
        }
        
        function validateAll() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            const lengthValid = password.length >= 8;
            const upperValid = /[A-Z]/.test(password);
            const lowerValid = /[a-z]/.test(password);
            const numberValid = /[0-9]/.test(password);
            const matchValid = password === confirm && confirm.length > 0;
            
            if (lengthValid && upperValid && lowerValid && numberValid && matchValid) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }
        
        function validateForm() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            if (password !== confirm) {
                return false;
            }
            return true;
        }
    </script>
</body>
</html>

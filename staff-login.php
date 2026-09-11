<?php
// staff-login.php
require_once __DIR__ . '/config/database.php';

// If already logged in as staff or admin, redirect to appropriate dashboard
if (isStaff() || isAdmin()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff-dashboard.php');
    }
    exit();
}

// If logged in as regular user (resident), logout first
if (isLoggedIn()) {
    session_destroy();
    session_start();
}

$error = '';
$field_errors = [];
$form_data = [
    'username' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Store username for persistence
    $form_data['username'] = $username;
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
        if (empty($username)) $field_errors['username'] = true;
        if (empty($password)) $field_errors['password'] = true;
    } else {
        // Check in users table with staff or admin role
        $query = "SELECT user_id, username, password, first_name, last_name, role, status 
                  FROM users 
                  WHERE (username = :username OR email = :username2) 
                  AND role IN ('staff', 'admin')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username',  $username);
        $stmt->bindParam(':username2', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user['status'] != 'active') {
                $error = "Your account is inactive. Please contact the administrator.";
            } else if (password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                // Update last login
                $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':user_id', $user['user_id']);
                $update_stmt->execute();
                
                // Log login
                $log_query = "INSERT INTO audit_logs (user_id, action, ip_address) 
                              VALUES (:user_id, 'staff_login', :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(':user_id', $user['user_id']);
                $log_stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                $log_stmt->execute();
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    redirect('admin/dashboard.php');
                } else {
                    redirect('staff-dashboard.php');
                }
                exit();
            } else {
                $error = "Invalid password.";
                $field_errors['password'] = true;
            }
        } else {
            $error = "Invalid staff credentials. Please use the staff login portal.";
            $field_errors['username'] = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0A1628 0%, #1a2a4a 50%, #0F414A 100%);
            position: relative;
            overflow-x: hidden;
        }

        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0F414A 0%, #7F0303 50%, #3698aa 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            z-index: 0;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .floating-shapes .shape {
            position: absolute;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .floating-shapes .shape-1 { width: 400px; height: 400px; top: -100px; right: -100px; animation-duration: 25s; }
        .floating-shapes .shape-2 { width: 250px; height: 250px; bottom: -50px; left: -50px; animation-duration: 20s; animation-delay: 2s; }
        .floating-shapes .shape-3 { width: 150px; height: 150px; top: 40%; left: 20%; animation-duration: 18s; animation-delay: 5s; }
        .floating-shapes .shape-4 { width: 200px; height: 200px; bottom: 20%; right: 15%; animation-duration: 22s; animation-delay: 8s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            z-index: 2;
        }

        .auth-card {
            display: flex;
            flex-direction: row;
            max-width: 1100px;
            width: 100%;
            background: rgba(255,255,255,0.98);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .auth-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.45);
        }

        .auth-brand {
            flex: 1;
            background: linear-gradient(135deg, rgba(15,65,74,0.85) 0%, rgba(127,3,3,0.8) 100%), url('images/palayan-bg.png');
            background-size: cover;
            background-position: center;
            padding: 48px 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .auth-brand::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -30%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }

        .auth-brand::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -20%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }

        .logo-circle {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }

        .logo-circle:hover {
            transform: scale(1.05);
        }

        .logo-circle img {
            width: 110px;
            height: auto;
        }

        .brand-logo h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-logo p {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .brand-features {
            position: relative;
            z-index: 2;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .feature-item i {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #D8BA98;
        }

        .feature-item h4 {
            color: white;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .feature-item p {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }

        .brand-quote {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.15);
            position: relative;
            z-index: 2;
        }

        .brand-quote p {
            color: rgba(255,255,255,0.9);
            font-style: italic;
            font-size: 0.85rem;
        }

        .brand-quote span {
            display: block;
            margin-top:10px;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.6);
        }

        .auth-form {
            flex: 1;
            padding: 48px 40px;
            background: white;
        }

        .auth-header {
            margin-bottom: 28px;
        }

        .auth-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0F414A;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: #6B7A8A;
            font-size: 0.85rem;
        }

        .staff-badge {
            display: inline-block;
            background: #7F0303;
            color: white;
            padding: 4px 16px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #3A4A5A;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .form-group label i {
            color: #7F0303;
            margin-right: 8px;
        }

        .form-group label .required {
            color: #DC2626;
            margin-left: 2px;
        }

        .input-group {
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #E4E7E9;
            border-radius: 16px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: white;
            font-family: inherit;
        }

        .input-group input:focus {
            outline: none;
            border-color: #0F414A;
            box-shadow: 0 0 0 3px rgba(15,65,74,0.1);
        }

        .input-group input.error {
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
            background: #FFF5F5;
        }

        .input-group input.success {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
            background: #F0FDF4;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #A0ABB8;
            transition: color 0.3s;
        }

        .input-group .toggle-password:hover {
            color: #7F0303;
        }

        .field-error-hint {
            display: block;
            margin-top: 4px;
            font-size: 0.7rem;
            color: #DC2626;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .field-error-hint.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: #7F0303;
            color: white;
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
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #9a1515;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127,3,3,0.3);
        }

        .resident-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
        }

        .resident-link a {
            color: #7F0303;
            text-decoration: none;
            font-weight: 500;
        }

        .resident-link a:hover {
            text-decoration: underline;
        }

        .auth-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #E4E7E9;
        }

        .auth-footer p {
            color: #6B7A8A;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }

        .auth-footer a {
            color: #0F414A;
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            color: #7F0303;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
            width: 100%;
        }

        .toast {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 14px;
            animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
        }

        .toast.hide {
            animation: slideOutRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateX(50px) scale(0.95);
            }
        }

        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .toast-icon.error {
            background: #FEE2E2;
            color: #DC2626;
        }

        .toast-icon.success {
            background: #D1FAE5;
            color: #059669;
        }

        .toast-icon.warning {
            background: #FEF3C7;
            color: #D97706;
        }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-content .title {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1a1a2e;
            margin-bottom: 3px;
        }

        .toast-content .message {
            font-size: 0.8rem;
            color: #4A5568;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: #A0ABB8;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.3s;
            flex-shrink: 0;
            margin-top: -4px;
        }

        .toast-close:hover {
            color: #7F0303;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: currentColor;
            opacity: 0.2;
            animation: progressBar 5s linear forwards;
        }

        .toast.error { border-left-color: #DC2626; }
        .toast.success { border-left-color: #059669; }
        .toast.warning { border-left-color: #D97706; }

        @keyframes progressBar {
            from { width: 100%; }
            to { width: 0%; }
        }

        .alert {
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .alert-info {
            background: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }

        .alert-info a {
            color: #0369A1;
            text-decoration: underline;
        }

        .security-note {
            background: #F8F9FC;
            padding: 12px 16px;
            border-radius: 16px;
            margin-top: 20px;
            font-size: 0.7rem;
            color: #8C9AA6;
            text-align: center;
            border: 1px solid #E4E7E9;
        }

        .security-note i {
            color: #7F0303;
            margin-right: 5px;
        }

        @media (max-width: 950px) {
            .auth-card {
                flex-direction: column;
                max-width: 500px;
            }
            
            .auth-brand {
                padding: 32px 24px;
            }
            
            .brand-features {
                display: none;
            }
            
            .brand-quote {
                margin-top: 20px;
            }
            
            .auth-form {
                padding: 32px 28px;
            }

            .toast-container {
                top: 10px;
                right: 10px;
                max-width: 340px;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 20px;
            }
            
            .auth-form {
                padding: 24px 20px;
            }
            
            .auth-header h2 {
                font-size: 1.5rem;
            }
            
            .logo-circle {
                width: 80px;
                height: 80px;
            }
            
            .logo-circle img {
                width: 50px;
            }

            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: unset;
            }

            .toast {
                padding: 14px 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Gradient Background -->
    <div class="animated-bg"></div>
    
    <!-- Floating Decorative Shapes -->
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Left Side - Branding -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png" alt="One Palayan-Kalinga Hub Logo" onerror="this.src='https://placehold.co/80x80?text=PKH'">
                    </div>
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <h4>Real-time Analytics</h4>
                            <p>Monitor service performance and requests</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users-cog"></i>
                        <div>
                            <h4>Beneficiary Management</h4>
                            <p>Track and manage all beneficiaries</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <h4>Document Verification</h4>
                            <p>Review and verify submitted documents</p>
                        </div>
                    </div>
                </div>
                
                <div class="brand-quote">
                    <p>"Efficient service delivery through modern technology"</p>
                    <span>— CSWDO Palayan City</span>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="auth-form">
                <div class="auth-header">
                    <h2>Staff & Admin Portal</h2>
                    <p>Authorized personnel only</p>
                    <span class="staff-badge"><i class="fas fa-user-tie"></i> Restricted Access</span>
                </div>
                
                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username or Email <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="text" id="username" name="username" required placeholder="Enter your username or email"
                                   value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        </div>
                        <span class="field-error-hint" id="usernameError">Please enter your username or email</span>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label style="margin: 0;"><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                            <a href="forgot-password.php" style="font-size: 0.8rem; color: #7F0303; text-decoration: none; font-weight: 500; transition: color 0.3s;" onmouseover="this.style.color='#9a1515'" onmouseout="this.style.color='#7F0303'">Forgot Password?</a>
                        </div>
                        <div class="input-group">
                            <input type="password" name="password" required id="password" placeholder="Enter your password">
                            <span class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <span class="field-error-hint" id="passwordError">Please enter your password</span>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        ACCESS DASHBOARD <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="resident-link">
                    <i class="fas fa-user"></i> Are you a resident? <a href="login.php">Click here for Resident Login</a>
                </div>
                
                <div class="auth-footer">
                    <p><a href="index.php"><i class="fas fa-arrow-left"></i> Back to Main Site</a></p>
                </div>
                
                <div class="security-note">
                    <i class="fas fa-shield-alt"></i> This portal is for authorized CSWDO staff and administrators only. All access is logged and monitored.
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== TOAST NOTIFICATION SYSTEM ==========
        function showToast(type, title, message, duration = 5000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            
            const icons = {
                error: 'fa-exclamation-circle',
                success: 'fa-check-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon ${type}">
                    <i class="fas ${icons[type] || 'fa-info-circle'}"></i>
                </div>
                <div class="toast-content">
                    <div class="title">${title}</div>
                    <div class="message">${message}</div>
                </div>
                <button class="toast-close" onclick="closeToast(this)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="toast-progress" style="animation-duration: ${duration/1000}s;"></div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                closeToast(toast.querySelector('.toast-close'));
            }, duration);
        }

        function closeToast(closeBtn) {
            const toast = closeBtn.closest('.toast');
            if (toast) {
                toast.classList.add('hide');
                setTimeout(() => {
                    toast.remove();
                }, 400);
            }
        }

        // ========== TOGGLE PASSWORD VISIBILITY ==========
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        // ========== LIVE FIELD VALIDATION ==========
        // Username validation
        const usernameInput = document.getElementById('username');
        const usernameError = document.getElementById('usernameError');

        usernameInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                usernameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                usernameError.classList.remove('visible');
            }
        });

        usernameInput.addEventListener('input', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                usernameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                usernameError.classList.remove('visible');
            }
        });

        // Password validation
        const passwordInput = document.getElementById('password');
        const passwordError = document.getElementById('passwordError');

        passwordInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                passwordError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                passwordError.classList.remove('visible');
            }
        });

        passwordInput.addEventListener('input', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                passwordError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                passwordError.classList.remove('visible');
            }
        });

        // ========== FORM SUBMISSION ==========
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            let hasError = false;
            
            // Validate username
            if (!username) {
                document.getElementById('username').classList.add('error');
                document.getElementById('usernameError').classList.add('visible');
                hasError = true;
            }
            
            // Validate password
            if (!password) {
                document.getElementById('password').classList.add('error');
                document.getElementById('passwordError').classList.add('visible');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                showToast('error', 'Validation Error', 'Please fill in all required fields.');
                return false;
            }
            
            return true;
        });

        // ========== SHOW PHP ERRORS AS TOAST ==========
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($error): ?>
                showToast('error', 'Login Error', `<?php echo addslashes($error); ?>`, 6000);
            <?php endif; ?>
            
            // Highlight fields with errors
            <?php if(isset($field_errors['username'])): ?>
                document.getElementById('username').classList.add('error');
                document.getElementById('usernameError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['password'])): ?>
                document.getElementById('password').classList.add('error');
                document.getElementById('passwordError').classList.add('visible');
            <?php endif; ?>
        });
    </script>
</body>
</html>
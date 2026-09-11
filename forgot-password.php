<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_reset'])) {
    csrf_verify();
    
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Mangyaring ilagay ang iyong email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Mangyaring ilagay ang isang wastong email address.";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Find user with this email
        $query = "SELECT user_id, first_name, last_name, username FROM users WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate a secure unique token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Clean up any old tokens for this user
            $delete_query = "DELETE FROM password_resets WHERE user_id = :user_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':user_id', $user['user_id']);
            $delete_stmt->execute();
            
            // Save token
            $insert_query = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user['user_id']);
            $insert_stmt->bindParam(':token', $token);
            $insert_stmt->bindParam(':expires_at', $expires_at);
            
            if ($insert_stmt->execute()) {
                $reset_link = APP_URL . "/reset-password.php?token={$token}";
                
                // Send reset email
                $mailer = new Mailer();
                $mailer->sendPasswordReset($email, $user['first_name'] . ' ' . $user['last_name'], $reset_link);
            }
        }
        
        // Always show the same success message to prevent user enumeration
        $success = "Kung ang email na iyon ay may kaugnay na account, ipinadala namin ang mga tagubilin para sa pag-reset ng password. Mangyaring suriin ang iyong inbox at spam folder.";
    }
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    
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
            background: linear-gradient(135deg, #0F414A 0%, #761c1c 50%, #31aac0 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            z-index: 0;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
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
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .floating-shapes .shape-1 {
            width: 400px;
            height: 400px;
            top: -100px;
            right: -100px;
            animation-duration: 25s;
        }

        .floating-shapes .shape-2 {
            width: 250px;
            height: 250px;
            bottom: -50px;
            left: -50px;
            animation-duration: 20s;
            animation-delay: 2s;
        }

        .floating-shapes .shape-3 {
            width: 150px;
            height: 150px;
            top: 40%;
            left: 20%;
            animation-duration: 18s;
            animation-delay: 5s;
        }

        .floating-shapes .shape-4 {
            width: 200px;
            height: 200px;
            bottom: 20%;
            right: 15%;
            animation-duration: 22s;
            animation-delay: 8s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-30px) rotate(10deg);
            }
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            position: relative;
            z-index: 2;
        }

        .auth-card {
            display: flex;
            flex-direction: row;
            max-width: 1100px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .auth-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45);
        }

        .auth-brand {
            flex: 1;
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.85) 0%, rgba(127, 3, 3, 0.8) 100%), url('images/palayan-bg.png');
            background-size: cover;
            background-position: center;
            padding: 40px 28px;
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
            background: rgba(255, 255, 255, 0.08);
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
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
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
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
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
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .brand-logo p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.7rem;
            margin-top: 3px;
        }

        .welcome-message {
            position: relative;
            z-index: 2;
            text-align: center;
            margin: 20px 0;
        }

        .welcome-message h3 {
            color: white;
            font-size: 2.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: -0.3px;
        }

        .welcome-message p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .benefits-list {
            text-align: left;
            margin-top: 20px;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .benefit-item i {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #D8BA98;
        }

        .benefit-item span {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.75rem;
        }

        .brand-quote {
            margin-top: 7px;
            padding-top: 7px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-quote p {
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            font-size: 0.75rem;
        }

        .brand-quote span {
            display: block;
            margin-top: 6px;
            font-size: 0.6rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .auth-form {
            flex: 1.2;
            padding: 32px 32px;
            background: white;
            overflow-y: auto;
            max-height: 90vh;
        }

        .auth-form::-webkit-scrollbar {
            width: 4px;
        }

        .auth-form::-webkit-scrollbar-track {
            background: #E4E7E9;
            border-radius: 4px;
        }

        .auth-form::-webkit-scrollbar-thumb {
            background: #7F0303;
            border-radius: 4px;
        }

        .auth-header {
            margin-bottom: 20px;
        }

        .auth-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0F414A;
            margin-bottom: 4px;
        }

        .auth-header p {
            color: #6B7A8A;
            font-size: 0.75rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #3A4A5A;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .form-group label i {
            color: #7F0303;
            margin-right: 6px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #E4E7E9;
            border-radius: 12px;
            font-size: 0.8rem;
            transition: all 0.3s;
            font-family: inherit;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0F414A;
            box-shadow: 0 0 0 3px rgba(15, 65, 74, 0.1);
        }

        .form-group input.error {
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            background: #FFF5F5;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 55px;
        }

        .input-group {
            position: relative;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #A0ABB8;
            transition: color 0.3s;
            font-size: 0.8rem;
        }

        .input-group .toggle-password:hover {
            color: #7F0303;
        }

        .password-hint {
            display: block;
            margin-top: 4px;
            font-size: 0.6rem;
            color: #8C9AA6;
        }

        .password-strength {
            margin-top: 5px;
            height: 3px;
            border-radius: 3px;
            background: #E4E7E9;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }

        .strength-weak {
            width: 25%;
            background: #DC2626;
        }

        .strength-fair {
            width: 50%;
            background: #F59E0B;
        }

        .strength-good {
            width: 75%;
            background: #3B82F6;
        }

        .strength-strong {
            width: 100%;
            background: #10B981;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0 18px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 14px;
            height: 14px;
            cursor: pointer;
            accent-color: #7F0303;
        }

        .checkbox-group label {
            color: #5C6B7A;
            font-size: 0.7rem;
            cursor: pointer;
            margin: 0;
        }

        .btn-primary {
            width: 100%;
            padding: 11px;
            background: #7F0303;
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #9a1515;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.3);
        }

        .auth-footer {
            text-align: center;
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #E4E7E9;
        }

        .auth-footer p {
            color: #6B7A8A;
            margin-bottom: 8px;
            font-size: 0.7rem;
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
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

        .toast-content .message a {
            color: #7F0303;
            font-weight: 600;
            text-decoration: none;
        }

        .toast-content .message a:hover {
            text-decoration: underline;
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

        .toast.error {
            border-left-color: #DC2626;
        }

        .toast.success {
            border-left-color: #059669;
        }

        .toast.warning {
            border-left-color: #D97706;
        }

        @keyframes progressBar {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        .field-error-hint {
            display: block;
            margin-top: 3px;
            font-size: 0.6rem;
            color: #DC2626;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .field-error-hint.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .field-success-hint {
            display: block;
            margin-top: 3px;
            font-size: 0.6rem;
            color: #059669;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .field-success-hint.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .form-group input.success {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            background: #F0FDF4;
        }

        @media (max-width: 950px) {
            .auth-card {
                flex-direction: column;
                max-width: 500px;
            }

            .auth-brand {
                padding: 30px 24px;
            }

            .welcome-message h3 {
                font-size: 1.1rem;
            }

            .welcome-message p {
                font-size: 0.7rem;
            }

            .benefits-list {
                display: none;
            }

            .brand-quote {
                margin-top: 15px;
            }

            .auth-form {
                padding: 25px 28px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .toast-container {
                top: 10px;
                right: 10px;
                max-width: 340px;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 15px;
            }

            .auth-form {
                padding: 20px;
            }

            .auth-header h2 {
                font-size: 1.3rem;
            }

            .logo-circle {
                width: 70px;
                height: 70px;
            }

            .logo-circle img {
                width: 45px;
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

                <div class="welcome-message"><div class="benefits-list">
                    <div class="benefit-item">
                        <i class="fas fa-key"></i>
                        <div>
                            <span><strong>Ligtas na Access</strong><br>
                            I-reset ang iyong password nang madali</span>
                        </div></div></div><div class="brand-quote">
                    <p>"Serving Palayan City with excellence and compassion"</p>
                    <span>— CSWDO Palayan City</span>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="auth-form">
                <div class="auth-header">
                    <h2>Forgot Password?</h2>
                    <p>Ilagay ang iyong email address upang makatanggap ng link sa pag-reset ng password.</p>
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
                <?php else: ?>
                    <form method="POST" action="" id="forgotForm">
                        <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address <span class="required" style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="email" id="email" name="email" required placeholder="Enter your email address">
                            </div>
                        </div>
                        <button type="submit" name="request_reset" class="btn-primary" style="margin-top: 15px; width: 100%; padding: 14px; background: #7F0303; color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            SEND RESET LINK <i class="fas fa-paper-plane" style="margin-left: 8px;"></i>
                        </button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #E4E7E9; text-align: center;">
                    <p>Bumalik sa <a href="login.php" style="color: #7F0303; font-weight: 600; text-decoration: none;">Sign In</a></p>
                    <p><a href="index.php" style="color: #0F414A; font-weight: 600; text-decoration: none;"><i class="fas fa-arrow-left"></i> Bumalik sa Home</a></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>



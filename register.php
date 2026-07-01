<?php
// Include database configuration
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    if (isAdmin() || isStaff()) {
        redirect('staff-dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

// Rate limiting for registration attempts
$register_key = 'register_attempts_' . $_SERVER['REMOTE_ADDR'];
$register_attempts = isset($_SESSION[$register_key]) ? $_SESSION[$register_key] : 0;

if ($register_attempts >= 10) {
    $error = "Too many registration attempts. Please try again later.";
    $blocked = true;
} else {
    $blocked = false;
}

$error = '';
$success = '';
$field_errors = [];

// Preserve form data
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'contact_number' => '',
    'address' => '',
    'password' => '',
    'confirm_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$blocked) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Sanitize inputs and preserve data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $address = sanitize($_POST['address']);
    
    // Store form data for persistence
    $form_data = [
        'username' => $username,
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'contact_number' => $contact_number,
        'address' => $address,
        'password' => '', // Don't preserve passwords for security
        'confirm_password' => '' // Don't preserve passwords for security
    ];
    
    // Additional validations
    $errors = [];
    
    // First name validation
    if (empty($first_name)) {
        $errors[] = "First name is required.";
        $field_errors['first_name'] = true;
    }
    
    // Last name validation
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
        $field_errors['last_name'] = true;
    }
    
    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3-20 characters and can only contain letters, numbers, and underscore.";
        $field_errors['username'] = true;
    }
    
    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
        $field_errors['email'] = true;
    }
    
    // Password validation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
        $field_errors['password'] = true;
        $field_errors['confirm_password'] = true;
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
        $field_errors['password'] = true;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
        $field_errors['password'] = true;
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
        $field_errors['password'] = true;
    }
    
    // Phone number validation
    if (!empty($contact_number)) {
        $phone_pattern = '/^(09|\+639)\d{9}$/';
        if (!preg_match($phone_pattern, $contact_number)) {
            $errors[] = "Please enter a valid Philippine mobile number (e.g., 09123456789 or +639123456789).";
            $field_errors['contact_number'] = true;
        }
    }
    
    if (empty($errors)) {
        // Check if username or email exists
        $query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Username or email already exists. Please choose a different one.";
            $_SESSION[$register_key] = ++$register_attempts;
            $field_errors['username'] = true;
            $field_errors['email'] = true;
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, address, role, created_at) 
                      VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, :address, 'resident', NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':address', $address);
            
            if ($stmt->execute()) {
                unset($_SESSION[$register_key]);
                $success = "Registration successful! You can now <a href='login.php'>login here</a>.";
                // Clear form data on success
                $form_data = [
                    'username' => '',
                    'email' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'contact_number' => '',
                    'address' => '',
                    'password' => '',
                    'confirm_password' => ''
                ];
            } else {
                $error = "Registration failed. Please try again.";
                $_SESSION[$register_key] = ++$register_attempts;
            }
        }
    } else {
        $error = implode("<br>", $errors);
        $_SESSION[$register_key] = ++$register_attempts;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - One Palayan-Kalinga Hub</title>
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
            background: linear-gradient(135deg, #0F414A 0%, #761c1c 50%, #31aac0 100%);
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
            padding: 30px;
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
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-logo p {
            color: rgba(255,255,255,0.8);
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
            color: rgba(255,255,255,0.85);
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
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #D8BA98;
        }

        .benefit-item span {
            color: rgba(255,255,255,0.85);
            font-size: 0.75rem;
        }

        .brand-quote {
            margin-top: 7px;
            padding-top: 7px;
            border-top: 1px solid rgba(255,255,255,0.15);
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-quote p {
            color: rgba(255,255,255,0.9);
            font-style: italic;
            font-size: 0.75rem;
        }

        .brand-quote span {
            display: block;
            margin-top: 6px;
            font-size: 0.6rem;
            color: rgba(255,255,255,0.6);
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
            box-shadow: 0 0 0 3px rgba(15,65,74,0.1);
        }

        .form-group input.error {
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
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

        .strength-weak { width: 25%; background: #DC2626; }
        .strength-fair { width: 50%; background: #F59E0B; }
        .strength-good { width: 75%; background: #3B82F6; }
        .strength-strong { width: 100%; background: #10B981; }

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
            box-shadow: 0 8px 20px rgba(127,3,3,0.3);
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

        .toast.error { border-left-color: #DC2626; }
        .toast.success { border-left-color: #059669; }
        .toast.warning { border-left-color: #D97706; }

        @keyframes progressBar {
            from { width: 100%; }
            to { width: 0%; }
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
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
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
<body>
    <div class="animated-bg"></div>
    
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png" alt="One Palayan-Kalinga Hub Logo" onerror="this.src='https://placehold.co/80x80?text=PKH'">
                    </div>
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>
                
                <div class="welcome-message">
                    <h3>Welcome to a Better Tomorrow!</h3>
                    <p>Join thousands of Palayan City residents who already enjoy fast, transparent, and accessible social services.</p>
                    
                    <div class="benefits-list">
                        <div class="benefit-item">
                            <i class="fas fa-clock"></i>
                            <span>Faster processing of requests</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Real-time application tracking</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure and confidential data</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-bell"></i>
                            <span>Instant notifications and updates</span>
                        </div>
                    </div>
                </div>
                
                <div class="brand-quote">
                    <p>"Serbisyong may malasakit, mabilis at maaasahan"</p>
                    <span>— CSWDO Palayan City</span>
                </div>
            </div>

            <div class="auth-form">
                <div class="auth-header">
                    <h2>Create an account</h2>
                    <p>Join our community and access social services</p>
                </div>
                
                <form method="POST" action="" id="registerForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First name <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="first_name" name="first_name" required placeholder="First name" 
                                   value="<?php echo htmlspecialchars($form_data['first_name']); ?>">
                            <span class="field-error-hint" id="firstNameError">First name is required</span>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last name <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="last_name" name="last_name" required placeholder="Last name"
                                   value="<?php echo htmlspecialchars($form_data['last_name']); ?>">
                            <span class="field-error-hint" id="lastNameError">Last name is required</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Username <span style="color:#DC2626;">*</span></label>
                        <input type="text" id="username" name="username" required placeholder="Choose a username"
                               value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        <span class="field-error-hint" id="usernameError">Username must be 3-20 characters (letters, numbers, underscore)</span>
                        <small class="password-hint">3-20 characters (letters, numbers, underscore)</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email <span style="color:#DC2626;">*</span></label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com"
                               value="<?php echo htmlspecialchars($form_data['email']); ?>">
                        <span class="field-error-hint" id="emailError">Please enter a valid email address</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Contact number</label>
                        <input type="tel" id="contact_number" name="contact_number" placeholder="09123456789"
                               value="<?php echo htmlspecialchars($form_data['contact_number']); ?>">
                        <span class="field-error-hint" id="contactError">Please enter a valid Philippine mobile number</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea id="address" name="address" rows="2" placeholder="Your complete address"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password <span style="color:#DC2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" required placeholder="Create a password">
                            <span class="toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <span class="field-error-hint" id="passwordError">Password must be at least 8 characters with uppercase and number</span>
                        <small class="password-hint">At least 8 characters with uppercase and number</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm password <span style="color:#DC2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                            <span class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <span class="field-error-hint" id="confirmError">Passwords do not match</span>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="newsletter" checked>
                        <label for="newsletter">I would like to receive a weekly newsletter</label>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        SIGN ME UP <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                    <p><a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
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
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const toggleIcon = input.parentElement.querySelector('.toggle-password i');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        // ========== PASSWORD STRENGTH METER ==========
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0) {
                strengthBar.style.width = '0%';
            } else if (strength === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBar.classList.add('strength-fair');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // ========== LIVE FIELD VALIDATION ==========
        // Username validation
        const usernameInput = document.getElementById('username');
        const usernameError = document.getElementById('usernameError');
        const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;

        usernameInput.addEventListener('blur', function() {
            if (this.value && !usernameRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                usernameError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                usernameError.classList.remove('visible');
            }
        });

        usernameInput.addEventListener('input', function() {
            if (this.value && !usernameRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                usernameError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                usernameError.classList.remove('visible');
            } else {
                this.classList.remove('error');
                this.classList.remove('success');
                usernameError.classList.remove('visible');
            }
        });

        // Email validation
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        emailInput.addEventListener('blur', function() {
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                emailError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                emailError.classList.remove('visible');
            }
        });

        emailInput.addEventListener('input', function() {
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                emailError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                emailError.classList.remove('visible');
            } else {
                this.classList.remove('error');
                this.classList.remove('success');
                emailError.classList.remove('visible');
            }
        });

        // Phone validation
        const phoneInput = document.getElementById('contact_number');
        const contactError = document.getElementById('contactError');
        const phoneRegex = /^(09|\+639)\d{9}$/;

        phoneInput.addEventListener('blur', function() {
            if (this.value && !phoneRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                contactError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                contactError.classList.remove('visible');
            }
        });

        phoneInput.addEventListener('input', function() {
            if (this.value && !phoneRegex.test(this.value)) {
                this.classList.add('error');
                this.classList.remove('success');
                contactError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                contactError.classList.remove('visible');
            } else {
                this.classList.remove('error');
                this.classList.remove('success');
                contactError.classList.remove('visible');
            }
        });

        // Password validation
        const passwordInput = document.getElementById('password');
        const passwordError = document.getElementById('passwordError');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            if (val && (val.length < 8 || !val.match(/[A-Z]/) || !val.match(/[0-9]/))) {
                this.classList.add('error');
                this.classList.remove('success');
                passwordError.classList.add('visible');
            } else if (val) {
                this.classList.remove('error');
                this.classList.add('success');
                passwordError.classList.remove('visible');
            } else {
                this.classList.remove('error');
                this.classList.remove('success');
                passwordError.classList.remove('visible');
            }
        });

        // Confirm password validation
        const confirmInput = document.getElementById('confirm_password');
        const confirmError = document.getElementById('confirmError');

        confirmInput.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            if (this.value && this.value !== password) {
                this.classList.add('error');
                this.classList.remove('success');
                confirmError.classList.add('visible');
            } else if (this.value) {
                this.classList.remove('error');
                this.classList.add('success');
                confirmError.classList.remove('visible');
            } else {
                this.classList.remove('error');
                this.classList.remove('success');
                confirmError.classList.remove('visible');
            }
        });

        // First name validation
        const firstNameInput = document.getElementById('first_name');
        const firstNameError = document.getElementById('firstNameError');

        firstNameInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                firstNameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                firstNameError.classList.remove('visible');
            }
        });

        firstNameInput.addEventListener('input', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                firstNameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                firstNameError.classList.remove('visible');
            }
        });

        // Last name validation
        const lastNameInput = document.getElementById('last_name');
        const lastNameError = document.getElementById('lastNameError');

        lastNameInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                lastNameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                lastNameError.classList.remove('visible');
            }
        });

        lastNameInput.addEventListener('input', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                lastNameError.classList.add('visible');
            } else {
                this.classList.remove('error');
                this.classList.add('success');
                lastNameError.classList.remove('visible');
            }
        });

        // ========== FORM SUBMISSION ==========
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const contact = document.getElementById('contact_number').value;
            const first_name = document.getElementById('first_name').value;
            const last_name = document.getElementById('last_name').value;
            
            let hasError = false;
            
            // Validate first name
            if (!first_name.trim()) {
                document.getElementById('first_name').classList.add('error');
                document.getElementById('firstNameError').classList.add('visible');
                hasError = true;
            }
            
            // Validate last name
            if (!last_name.trim()) {
                document.getElementById('last_name').classList.add('error');
                document.getElementById('lastNameError').classList.add('visible');
                hasError = true;
            }
            
            // Validate username
            if (!usernameRegex.test(username)) {
                document.getElementById('username').classList.add('error');
                document.getElementById('usernameError').classList.add('visible');
                hasError = true;
            }
            
            // Validate email
            if (!emailRegex.test(email)) {
                document.getElementById('email').classList.add('error');
                document.getElementById('emailError').classList.add('visible');
                hasError = true;
            }
            
            // Validate password
            if (password.length < 8 || !password.match(/[A-Z]/) || !password.match(/[0-9]/)) {
                document.getElementById('password').classList.add('error');
                document.getElementById('passwordError').classList.add('visible');
                hasError = true;
            }
            
            // Validate confirm password
            if (password !== confirm) {
                document.getElementById('confirm_password').classList.add('error');
                document.getElementById('confirmError').classList.add('visible');
                hasError = true;
            }
            
            // Validate contact
            if (contact && !phoneRegex.test(contact)) {
                document.getElementById('contact_number').classList.add('error');
                document.getElementById('contactError').classList.add('visible');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                showToast('error', 'Validation Error', 'Please fix the highlighted fields before submitting.');
                return false;
            }
            
            return true;
        });

        // ========== SHOW EXISTING PHP ERRORS AS TOAST ==========
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($error): ?>
                showToast('error', 'Registration Error', `<?php echo addslashes(strip_tags($error)); ?>`, 6000);
            <?php endif; ?>
            
            <?php if($success): ?>
                showToast('success', 'Registration Successful!', `<?php echo addslashes(strip_tags($success)); ?>`, 8000);
                <?php if(empty($form_data['username'])): ?>
                // Clear form on success
                document.querySelectorAll('#registerForm input, #registerForm textarea').forEach(el => {
                    if (el.type !== 'password' && el.type !== 'checkbox') {
                        el.value = '';
                    }
                    if (el.type === 'password') {
                        el.value = '';
                    }
                });
                <?php endif; ?>
            <?php endif; ?>
            
            // Highlight fields with errors
            <?php if(isset($field_errors['first_name'])): ?>
                document.getElementById('first_name').classList.add('error');
                document.getElementById('firstNameError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['last_name'])): ?>
                document.getElementById('last_name').classList.add('error');
                document.getElementById('lastNameError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['username'])): ?>
                document.getElementById('username').classList.add('error');
                document.getElementById('usernameError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['email'])): ?>
                document.getElementById('email').classList.add('error');
                document.getElementById('emailError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['contact_number'])): ?>
                document.getElementById('contact_number').classList.add('error');
                document.getElementById('contactError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['password'])): ?>
                document.getElementById('password').classList.add('error');
                document.getElementById('passwordError').classList.add('visible');
            <?php endif; ?>
            
            <?php if(isset($field_errors['confirm_password'])): ?>
                document.getElementById('confirm_password').classList.add('error');
                document.getElementById('confirmError').classList.add('visible');
            <?php endif; ?>
        });
    </script>
</body>
</html>
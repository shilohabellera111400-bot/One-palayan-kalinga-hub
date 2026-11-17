<?php
// Include database configuration
require_once __DIR__ . '/config/database.php';

// Safe redirect parameter handling
$raw_redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$redirect = '';
if (!empty($raw_redirect)) {
    $raw_redirect = trim(urldecode($raw_redirect));
    if (preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[a-zA-Z0-9_\-=&]*)?$/', $raw_redirect) && 
        !str_contains($raw_redirect, 'login') && 
        !str_contains($raw_redirect, 'register') && 
        !str_contains($raw_redirect, 'logout')) {
        $redirect = $raw_redirect;
    }
}

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } elseif (isStaff()) {
        redirect('staff-dashboard.php');
    } else {
        if (!empty($redirect)) {
            redirect($redirect);
        }
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
    'barangay' => '',
    'street' => '',
    'address' => '',
    'password' => '',
    'confirm_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$blocked) {
    csrf_verify();
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
    $barangay = sanitize($_POST['barangay'] ?? '');
    $street = sanitize($_POST['street'] ?? '');
    $address = !empty($street) ? $street . ', Brgy. ' . $barangay : ($barangay ? 'Brgy. ' . $barangay : '');

    // Store form data for persistence
    $form_data = [
        'username' => $username,
        'email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'contact_number' => $contact_number,
        'barangay' => $barangay,
        'street' => $street,
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

    // Barangay validation
    if (empty($barangay)) {
        $errors[] = "Please select your barangay.";
        $field_errors['barangay'] = true;
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
            // Create user with Resident role (Role ID 3)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, address, role, role_id, created_at) 
                      VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, :address, 'resident', 3, NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':address', $address);

            if ($stmt->execute()) {
                $new_user_id = $db->lastInsertId();
                
                // Create resident profile with barangay and official resident number
                if ($new_user_id) {
                    $new_res_num = generateResidentNumber($db);
                    $res_stmt = $db->prepare("INSERT INTO residents (user_id, resident_number, first_name, last_name, contact_number, email, street, barangay, city, province, is_active) VALUES (:uid, :res_num, :fn, :ln, :cn, :em, :st, :brgy, 'Palayan City', 'Nueva Ecija', 1)");
                    $res_stmt->bindParam(':uid', $new_user_id);
                    $res_stmt->bindParam(':res_num', $new_res_num);
                    $res_stmt->bindParam(':fn', $first_name);
                    $res_stmt->bindParam(':ln', $last_name);
                    $res_stmt->bindParam(':cn', $contact_number);
                    $res_stmt->bindParam(':em', $email);
                    $res_stmt->bindParam(':st', $street);
                    $res_stmt->bindParam(':brgy', $barangay);
                    $res_stmt->execute();
                }

                // Send welcome SMS via TextBee Gateway
                if (!empty($contact_number)) {
                    try {
                        require_once __DIR__ . '/includes/SMSGateway.php';
                        $smsGateway = new SMSGateway();
                        $smsGateway->sendAccountRegistrationWelcome($contact_number, trim($first_name . ' ' . $last_name), $username, (int)$new_user_id);
                    } catch (\Throwable $smsErr) {
                        error_log("Registration welcome SMS error: " . $smsErr->getMessage());
                    }
                }

                unset($_SESSION[$register_key]);
                $success = "Registration successful! Click the button below to proceed to login.";
                // Clear form data on success
                $form_data = [
                    'username' => '',
                    'email' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'contact_number' => '',
                    'barangay' => '',
                    'street' => '',
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
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Poppins:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #051A16 0%, #092721 40%, #0F414A 80%, #3B0D12 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Refined Deep Forest Green with Soft Green & Professional Maroon Accents - Exact match to login.php */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                #051814 0%, 
                #092620 18%, 
                #3A0E13 36%, 
                #0E3830 52%, 
                #4E1218 70%, 
                #0B2E26 86%, 
                #0F414A 100%
            );
            background-size: 260% 260%;
            animation: gradientShift 24s ease-in-out infinite;
            z-index: 0;
        }

        /* Subtle Ambient Lighting Overlay */
        .ambient-lighting {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            background: 
                radial-gradient(circle at 18% 22%, rgba(167, 215, 197, 0.12) 0%, transparent 48%),
                radial-gradient(circle at 82% 78%, rgba(127, 3, 3, 0.16) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(15, 65, 74, 0.22) 0%, transparent 65%);
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

        /* Dynamic Floating Circles - Expanded & Refined */
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
            background: radial-gradient(circle, rgba(147, 197, 253, 0.08) 0%, rgba(255, 255, 255, 0.02) 70%, transparent 100%);
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.04);
            animation: floatShape 20s infinite ease-in-out;
        }

        .floating-shapes .shape-1 {
            width: 420px;
            height: 420px;
            top: -120px;
            right: -100px;
            animation-duration: 26s;
        }

        .floating-shapes .shape-2 {
            width: 260px;
            height: 260px;
            bottom: -60px;
            left: -60px;
            animation-duration: 22s;
            animation-delay: 2s;
        }

        .floating-shapes .shape-3 {
            width: 140px;
            height: 140px;
            top: 38%;
            left: 14%;
            animation-duration: 18s;
            animation-delay: 5s;
        }

        .floating-shapes .shape-4 {
            width: 220px;
            height: 220px;
            bottom: 22%;
            right: 12%;
            animation-duration: 24s;
            animation-delay: 8s;
        }

        .floating-shapes .shape-5 {
            width: 90px;
            height: 90px;
            top: 18%;
            right: 28%;
            animation-duration: 16s;
            animation-delay: 3s;
        }

        .floating-shapes .shape-6 {
            width: 170px;
            height: 170px;
            bottom: 12%;
            left: 24%;
            animation-duration: 23s;
            animation-delay: 6s;
        }

        .floating-shapes .shape-7 {
            width: 110px;
            height: 110px;
            top: 62%;
            right: 36%;
            animation-duration: 20s;
            animation-delay: 1s;
        }

        .floating-shapes .shape-8 {
            width: 75px;
            height: 75px;
            top: 10%;
            left: 42%;
            animation-duration: 17s;
            animation-delay: 7s;
        }

        .floating-shapes .shape-9 {
            width: 300px;
            height: 300px;
            top: 50%;
            left: 52%;
            animation-duration: 28s;
            animation-delay: 4s;
        }

        @keyframes floatShape {
            0%, 100% {
                transform: translateY(0) rotate(0deg) scale(1);
            }
            50% {
                transform: translateY(-30px) rotate(10deg) scale(1.04);
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
            transition: transform 0.5s cubic-bezier(0.65, 0, 0.35, 1), box-shadow 0.3s;
            position: relative;
        }

        .auth-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45);
        }

        .auth-brand, .auth-form {
            transition: transform 0.5s cubic-bezier(0.65, 0, 0.35, 1), opacity 0.45s ease;
            will-change: transform, opacity;
        }

        /* Swapped Layout for Register: Form on Left, Brand Info on Right */
        @media (min-width: 901px) {
            .auth-card {
                flex-direction: row-reverse;
            }

            .auth-card.card-switching-to-login .auth-brand {
                transform: translateX(-100%);
                opacity: 0.82;
            }
            .auth-card.card-switching-to-login .auth-form {
                transform: translateX(100%);
                opacity: 0.82;
            }

            .auth-card.entering-from-login .auth-brand {
                animation: slideBrandInFromLeft 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            .auth-card.entering-from-login .auth-form {
                animation: slideFormInFromRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            @keyframes slideBrandInFromLeft {
                from { transform: translateX(-100%); opacity: 0.7; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideFormInFromRight {
                from { transform: translateX(100%); opacity: 0.7; }
                to { transform: translateX(0); opacity: 1; }
            }
        }

        @media (max-width: 900px) {
            .auth-card.card-switching-to-login {
                opacity: 0;
                transform: translateY(20px) scale(0.97);
            }
            .auth-card.entering-from-login {
                animation: fadeInRegisterMobile 0.5s ease forwards;
            }
            @keyframes fadeInRegisterMobile {
                from { opacity: 0; transform: translateY(-20px) scale(0.97); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
        }

        .auth-brand {
            font-family: 'Poppins', sans-serif;
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
            overflow: hidden;
            padding: 0;
        }

        .logo-circle:hover {
            transform: scale(1.05);
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-logo h2 {
            color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .brand-logo p {
            color: rgba(255, 255, 255, 0.85);
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            font-weight: 500;
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
            font-family: 'Poppins', sans-serif;
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -0.3px;
        }

        .welcome-message p {
            color: rgba(255, 255, 255, 0.88);
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-bottom: 20px;
            font-weight: 400;
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
            color: rgba(255, 255, 255, 0.88);
            font-family: 'Poppins', sans-serif;
            font-size: 0.78rem;
            font-weight: 500;
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
            font-family: 'Poppins', sans-serif;
            font-style: italic;
            font-size: 0.78rem;
            font-weight: 400;
        }

        .brand-quote span {
            display: block;
            margin-top: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 500;
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
            font-family: 'Dancing Script', cursive;
            font-size: 2.5rem;
            font-weight: 700;
            color: #0F414A;
            margin-bottom: 4px;
            line-height: 1.2;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #E4E7E9;
            border-radius: 12px;
            font-size: 0.8rem;
            transition: all 0.3s;
            font-family: inherit;
            background: white;
            color: #3A4A5A;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0F414A;
            box-shadow: 0 0 0 3px rgba(15, 65, 74, 0.1);
        }

        /* Registration Success Modal & Interactive Guidance */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 22, 40, 0.82);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 20px;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-card {
            background: #ffffff;
            padding: 36px 32px 30px;
            border-radius: 28px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 70px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.2);
            transform: translateY(30px) scale(0.92);
            transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }
        .modal-overlay.active .modal-card {
            transform: translateY(0) scale(1);
        }

        /* Animated Success Checkmark Ring */
        .success-animation-wrap {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pulse-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.18);
            animation: pulseRing 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        .pulse-ring-2 {
            animation-delay: 0.6s;
        }
        @keyframes pulseRing {
            0% { transform: scale(0.7); opacity: 0.8; }
            50% { transform: scale(1.35); opacity: 0.3; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .success-icon-wrap {
            position: relative;
            width: 76px;
            height: 76px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
            animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes bounceIn {
            0% { transform: scale(0); }
            60% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        .modal-card h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0F414A;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .modal-card .welcome-sub {
            font-size: 0.92rem;
            color: #4A5568;
            line-height: 1.5;
            margin-bottom: 22px;
        }

        /* Next Steps Roadmap */
        .guidance-roadmap {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 18px;
            padding: 16px 18px;
            text-align: left;
            margin-bottom: 24px;
        }
        .roadmap-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748B;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .roadmap-steps {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .roadmap-step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
        }
        .roadmap-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 14px;
            top: 28px;
            width: 2px;
            height: 18px;
            background: #CBD5E1;
        }
        .step-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
            z-index: 2;
        }
        .step-badge.done {
            background: #D1FAE5;
            color: #059669;
        }
        .step-badge.current {
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.35);
            animation: pulseCurrent 1.8s ease-in-out infinite;
        }
        @keyframes pulseCurrent {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        .step-badge.locked {
            background: #E2E8F0;
            color: #94A3B8;
        }
        .step-text {
            flex: 1;
        }
        .step-text h4 {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1E293B;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .step-text p {
            font-size: 0.78rem;
            color: #64748B;
            margin: 2px 0 0;
            line-height: 1.35;
        }

        .btn-modal-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px 24px;
            background: linear-gradient(135deg, #7F0303 0%, #a01010 100%);
            color: white;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(127, 3, 3, 0.35);
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .btn-modal-login::before {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: all 0.6s;
        }
        .btn-modal-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(127, 3, 3, 0.45);
            color: white;
        }
        .btn-modal-login:hover::before {
            left: 100%;
        }

        /* Guided Glowing Button & Pointer Pill */
        @keyframes guidedGlowPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7), 0 8px 25px rgba(127, 3, 3, 0.35);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(16, 185, 129, 0), 0 12px 30px rgba(16, 185, 129, 0.45);
                transform: scale(1.025);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0), 0 8px 25px rgba(127, 3, 3, 0.35);
                transform: scale(1);
            }
        }
        .guided-btn-glow {
            animation: guidedGlowPulse 2s infinite ease-in-out !important;
            position: relative;
        }
        .guided-pointer-pill {
            position: absolute;
            top: -12px;
            right: 18px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            animation: floatPointer 1.8s infinite ease-in-out;
            pointer-events: none;
            z-index: 5;
        }
        @keyframes floatPointer {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .canvas-confetti {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
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

<body>
    <div class="animated-bg"></div>
    <div class="ambient-lighting"></div>

    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
        <div class="shape shape-5"></div>
        <div class="shape shape-6"></div>
        <div class="shape shape-7"></div>
        <div class="shape shape-8"></div>
        <div class="shape shape-9"></div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png?v=<?php echo @filemtime(__DIR__ . '/images/logo.png') ?: time(); ?>" alt="One Palayan-Kalinga Hub Logo"
                            onerror="this.src='https://placehold.co/80x80?text=PKH'">
                    </div>
                    <h2>ONE PALAYAN-KALINGA HUB</h2>
                    <p>CSWDO Palayan City</p>
                </div>

                <div class="welcome-message">
                    <h3>Compassionate Care &amp; Dedicated Public Service</h3>
                    <p>Your official digital gateway to fast, transparent, and compassionate social welfare assistance, priority appointments, and community support for every Palayan family.</p>

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
                    <h2>Create an Account</h2>
                    <p>Join our community and access social services</p>
                </div>

                <form method="POST" action="" id="registerForm" novalidate>
                    <?php echo csrf_field(); ?>
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
                        <span class="field-error-hint" id="usernameError">Username must be 3-20 characters (letters,
                            numbers, underscore)</span>
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
                        <span class="field-error-hint" id="contactError">Please enter a valid Philippine mobile
                            number</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Barangay <span style="color:#DC2626;">*</span></label>
                            <select id="barangay" name="barangay" required>
                                <option value="">Select Barangay</option>
                                <?php 
                                $palayan_barangays = [
                                    "Atate", "Aulo", "Bagong Buhay", "Baryo Militar", "Caballero", "Caimito",
                                    "Doña Josefa", "Ganaderia", "Imelda Valley I", "Imelda Valley II", "Malate",
                                    "Maligaya", "Manacnac", "Mapaet", "Marcos Village", "Popolon(Pagas)",
                                    "Santolan", "Sapang Buho", "Singalat"
                                ];
                                foreach ($palayan_barangays as $bgy):
                                ?>
                                    <option value="<?php echo htmlspecialchars($bgy); ?>" <?php echo (isset($form_data['barangay']) && $form_data['barangay'] === $bgy) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bgy); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error-hint" id="barangayError">Please select your barangay</span>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-road"></i> Street / House No.</label>
                            <input type="text" id="street" name="street" placeholder="e.g. Purok 3, Mabini St."
                                value="<?php echo htmlspecialchars($form_data['street'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password <span style="color:#DC2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" required
                                placeholder="Create a password">
                            <span class="toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <span class="field-error-hint" id="passwordError">Password must be at least 8 characters with
                            uppercase and number</span>
                        <small class="password-hint">At least 8 characters with uppercase and number</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm password <span
                                style="color:#DC2626;">*</span></label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                placeholder="Confirm your password">
                            <span class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <span class="field-error-hint" id="confirmError">Passwords do not match</span>
                    </div>

                    <button type="submit" class="btn-primary">
                        SIGN ME UP <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" id="signInBtn">Sign in here</a></p>
                    <p><a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== TOAST NOTIFICATION SYSTEM ==========
        function showToast(type, title, message, duration = 5000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            // STRICTLY ONE SLIDING CARD: Remove any existing toast so only one sliding animation remains
            container.querySelectorAll('.toast').forEach(t => t.remove());

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
                <div class="toast-progress" style="animation-duration: ${duration / 1000}s;"></div>
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
        document.getElementById('password').addEventListener('input', function () {
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

        usernameInput.addEventListener('blur', function () {
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

        usernameInput.addEventListener('input', function () {
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

        emailInput.addEventListener('blur', function () {
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

        emailInput.addEventListener('input', function () {
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

        phoneInput.addEventListener('blur', function () {
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

        phoneInput.addEventListener('input', function () {
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

        passwordInput.addEventListener('input', function () {
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

        confirmInput.addEventListener('input', function () {
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

        firstNameInput.addEventListener('blur', function () {
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

        firstNameInput.addEventListener('input', function () {
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

        lastNameInput.addEventListener('blur', function () {
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

        lastNameInput.addEventListener('input', function () {
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
        document.getElementById('registerForm').addEventListener('submit', function (e) {
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

            // Validate barangay
            const barangay = document.getElementById('barangay').value;
            if (!barangay) {
                document.getElementById('barangay').classList.add('error');
                document.getElementById('barangayError').classList.add('visible');
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
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($error): ?>
                showToast('error', 'Registration Error', `<?php echo addslashes(strip_tags($error)); ?>`, 6000);
            <?php endif; ?>

            <?php if ($success): ?>
                showToast('success', 'Registration Successful!', `<?php echo addslashes(strip_tags($success)); ?>`, 8000);
                <?php if (empty($form_data['username'])): ?>
                    // Clear form on success
                    document.querySelectorAll('#registerForm input, #registerForm select, #registerForm textarea').forEach(el => {
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
            <?php if (isset($field_errors['first_name'])): ?>
                document.getElementById('first_name').classList.add('error');
                document.getElementById('firstNameError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['last_name'])): ?>
                document.getElementById('last_name').classList.add('error');
                document.getElementById('lastNameError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['username'])): ?>
                document.getElementById('username').classList.add('error');
                document.getElementById('usernameError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['email'])): ?>
                document.getElementById('email').classList.add('error');
                document.getElementById('emailError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['barangay'])): ?>
                document.getElementById('barangay').classList.add('error');
                document.getElementById('barangayError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['contact_number'])): ?>
                document.getElementById('contact_number').classList.add('error');
                document.getElementById('contactError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['password'])): ?>
                document.getElementById('password').classList.add('error');
                document.getElementById('passwordError').classList.add('visible');
            <?php endif; ?>

            <?php if (isset($field_errors['confirm_password'])): ?>
                document.getElementById('confirm_password').classList.add('error');
                document.getElementById('confirmError').classList.add('visible');
            <?php endif; ?>
        });
    </script>

    <!-- Registration Success Popup Modal with Confetti & Step Guidance -->
    <div class="modal-overlay <?php echo !empty($success) ? 'active' : ''; ?>" id="successModal">
        <canvas class="canvas-confetti" id="regConfettiCanvas"></canvas>
        <div class="modal-card">
            <!-- Animated Icon with Ripple Rings -->
            <div class="success-animation-wrap">
                <div class="pulse-ring"></div>
                <div class="pulse-ring pulse-ring-2"></div>
                <div class="success-icon-wrap">
                    <i class="fas fa-check"></i>
                </div>
            </div>

            <h3>Registration Successful! 🎉</h3>
            <p class="welcome-sub">Welcome to <strong>One Palayan-Kalinga Hub</strong>! Your account is ready. Follow this quick 3-step journey to unlock all city services:</p>

            <!-- Interactive 3-Step Visual Roadmap -->
            <div class="guidance-roadmap">
                <div class="roadmap-title">
                    <i class="fas fa-map-signs"></i> Next Steps to Full Access
                </div>
                <div class="roadmap-steps">
                    <div class="roadmap-step">
                        <div class="step-badge done"><i class="fas fa-check"></i></div>
                        <div class="step-text">
                            <h4>Step 1: Account Created</h4>
                            <p>Your resident credentials have been registered in the Palayan City portal.</p>
                        </div>
                    </div>
                    <div class="roadmap-step">
                        <div class="step-badge current"><i class="fas fa-sign-in-alt"></i></div>
                        <div class="step-text">
                            <h4>Step 2: Sign In to Portal</h4>
                            <p>Log in using your new username and password.</p>
                        </div>
                    </div>
                    <div class="roadmap-step">
                        <div class="step-badge locked"><i class="fas fa-shield-alt"></i></div>
                        <div class="step-text">
                            <h4>Step 3: Residency Verification</h4>
                            <p>Submit a valid government ID & selfie to activate AICS, Senior/PWD aid, and appointment booking.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <a href="login.php?registered=1<?php echo !empty($redirect) ? '&redirect=' . urlencode($redirect) : ''; ?>" class="btn-modal-login guided-btn-glow">
                    <span class="guided-pointer-pill"><i class="fas fa-arrow-right"></i> Next: Sign In</span>
                    Proceed to Sign In &amp; Verify <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Celebration Confetti JS -->
    <script>
        (function() {
            const modal = document.getElementById('successModal');
            const canvas = document.getElementById('regConfettiCanvas');
            if (!modal || !canvas) return;

            function runConfetti() {
                const ctx = canvas.getContext('2d');
                let width = canvas.width = window.innerWidth;
                let height = canvas.height = window.innerHeight;

                window.addEventListener('resize', () => {
                    width = canvas.width = window.innerWidth;
                    height = canvas.height = window.innerHeight;
                });

                const pieces = [];
                const colors = ['#10B981', '#7F0303', '#D8BA98', '#F59E0B', '#3B82F6', '#EC4899'];

                for (let i = 0; i < 90; i++) {
                    pieces.push({
                        x: width / 2 + (Math.random() - 0.5) * 60,
                        y: height / 2 + (Math.random() - 0.5) * 40,
                        w: Math.random() * 9 + 4,
                        h: Math.random() * 5 + 3,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        vx: (Math.random() - 0.5) * 16,
                        vy: (Math.random() * -14) - 4,
                        gravity: 0.35 + Math.random() * 0.2,
                        rotation: Math.random() * 360,
                        rotationSpeed: (Math.random() - 0.5) * 12,
                        opacity: 1
                    });
                }

                let animationFrame;
                let startTime = Date.now();

                function render() {
                    ctx.clearRect(0, 0, width, height);
                    let alive = false;

                    for (let p of pieces) {
                        p.x += p.vx;
                        p.y += p.vy;
                        p.vy += p.gravity;
                        p.vx *= 0.98;
                        p.rotation += p.rotationSpeed;

                        if (Date.now() - startTime > 1500) {
                            p.opacity -= 0.015;
                        }

                        if (p.opacity > 0 && p.y < height + 50) {
                            alive = true;
                            ctx.save();
                            ctx.translate(p.x, p.y);
                            ctx.rotate((p.rotation * Math.PI) / 180);
                            ctx.fillStyle = p.color;
                            ctx.globalAlpha = Math.max(0, p.opacity);
                            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                            ctx.restore();
                        }
                    }

                    if (alive) {
                        animationFrame = requestAnimationFrame(render);
                    }
                }

                render();
            }

            if (modal.classList.contains('active')) {
                setTimeout(runConfetti, 150);
            }
        })();

        // ========== PAGE SWITCHING ANIMATION ==========
        document.addEventListener('DOMContentLoaded', function () {
            const authCard = document.querySelector('.auth-card');
            const signInBtn = document.getElementById('signInBtn');

            if (signInBtn && authCard) {
                signInBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    authCard.classList.add('card-switching-to-login');
                    setTimeout(function () {
                        window.location.href = 'login.php?from=register<?php echo !empty($redirect) ? '&redirect=' . urlencode($redirect) : ''; ?>';
                    }, 450);
                });
            }

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('from') === 'login' && authCard) {
                authCard.classList.add('entering-from-login');
            }
        });
    </script>
</body>

</html>
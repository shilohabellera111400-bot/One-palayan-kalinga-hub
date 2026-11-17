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

// Check if user is already logged in - redirect to their respective dashboard
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

$error = '';
$field_errors = [];
$form_data = [
    'username' => '',
    'password' => ''
];

// Show session-expired notice if redirected here from timeout
$timeout_notice = isset($_GET['timeout']) && $_GET['timeout'] == '1';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token before doing anything
    csrf_verify();
    $database = new Database();
    $db = $database->getConnection();

    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    // Store username and password for persistence
    $form_data['username'] = $username;
    $form_data['password'] = $password;

    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username/email and password.";
        if (empty($username))
            $field_errors['username'] = true;
        if (empty($password))
            $field_errors['password'] = true;

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error, 'field_errors' => $field_errors]);
            exit();
        }
    } else {
        // Unified authentication query for Admin (1), Staff (2), and Resident (3)
        $query = "SELECT user_id, username, password, first_name, last_name, role, role_id, status, is_active, verification_status, profile_picture 
                  FROM users 
                  WHERE (username = :username OR email = :username2)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':username2', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if user account is active
            $is_user_active = ($user['status'] === 'active') && (!isset($user['is_active']) || (int)$user['is_active'] === 1);

            if (!$is_user_active) {
                $error = "Your account is inactive. Please contact the administrator.";
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error, 'field_errors' => ['username' => true]]);
                    exit();
                }
            } else if (password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Determine Role ID & Role Name based on RBAC rules
                $role_id = isset($user['role_id']) ? (int)$user['role_id'] : 3;
                if ($role_id <= 0) {
                    $role_id = match($user['role'] ?? 'resident') {
                        'admin' => ROLE_ADMIN,
                        'staff' => ROLE_STAFF,
                        default => ROLE_RESIDENT
                    };
                }

                $role_name = match($role_id) {
                    ROLE_ADMIN => 'admin',
                    ROLE_STAFF => 'staff',
                    ROLE_RESIDENT => 'resident',
                    default => 'resident'
                };

                // Standard Session Initialization
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $_SESSION['role_id'] = $role_id;
                $_SESSION['role'] = $role_name;
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $_SESSION['last_activity'] = time();
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? '';

                // Role-based Context & Routing
                if ($role_id === ROLE_ADMIN) {
                    // Role ID 1 - Admin: Full system administration access
                    $_SESSION['is_admin'] = true;
                    $target_redirect = 'admin/dashboard.php';
                    $audit_action = 'admin_login';
                } elseif ($role_id === ROLE_STAFF) {
                    // Role ID 2 - Staff: Authorized operational access
                    $staff_stmt = $db->prepare("SELECT id, position FROM staff WHERE user_id = :uid LIMIT 1");
                    $staff_stmt->execute([':uid' => $user['user_id']]);
                    $staff_row = $staff_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($staff_row) {
                        $_SESSION['staff_id'] = (int)$staff_row['id'];
                        $_SESSION['staff_position'] = $staff_row['position'] ?? '';
                    }
                    $target_redirect = 'staff-dashboard.php';
                    $audit_action = 'staff_login';
                } else {
                    // Role ID 3 - Resident: Access to personal profiles, services, applications, and appointments
                    $_SESSION['just_logged_in'] = true;
                    if ((isset($_GET['registered']) && $_GET['registered'] == '1') || (isset($_POST['registered']) && $_POST['registered'] == '1')) {
                        $_SESSION['just_registered'] = true;
                    }
                    $_SESSION['verification_status'] = $user['verification_status'] ?? 'pending';

                    // Check resident profile & auto-detect Senior Citizen status (Age 60+)
                    $chk_res = $db->prepare("SELECT birth_date, civil_status, senior_citizen FROM residents WHERE user_id = :uid LIMIT 1");
                    $chk_res->execute([':uid' => $user['user_id']]);
                    $res_row = $chk_res->fetch(PDO::FETCH_ASSOC);
                    
                    $is_senior = false;
                    if ($res_row) {
                        if (!empty($res_row['birth_date']) && $res_row['birth_date'] !== '0000-00-00') {
                            try {
                                $b_date = new DateTime($res_row['birth_date']);
                                $now = new DateTime();
                                if ($now->diff($b_date)->y >= 60) {
                                    $is_senior = true;
                                    if (($res_row['senior_citizen'] ?? 0) != 1) {
                                        $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")->execute([':uid' => $user['user_id']]);
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                        if (!$is_senior && ($res_row['senior_citizen'] ?? 0) == 1) {
                            $is_senior = true;
                        }
                    }
                    $_SESSION['is_senior_citizen'] = $is_senior;

                    // Set welcome popup flag for verified residents when logging in
                    if (($user['verification_status'] ?? '') === 'verified') {
                        $_SESSION['show_welcome_modal'] = true;
                    }

                    $target_redirect = !empty($redirect) ? $redirect : 'dashboard.php';
                    $audit_action = 'resident_login';
                }

                // Update last login timestamp
                $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':user_id', $user['user_id']);
                $update_stmt->execute();

                // Record audit log
                try {
                    $log_query = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (:user_id, :action, :ip)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->bindParam(':user_id', $user['user_id']);
                    $log_stmt->bindParam(':action', $audit_action);
                    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $log_stmt->bindParam(':ip', $client_ip);
                    $log_stmt->execute();
                } catch (Exception $e) {
                    error_log("Login audit log error: " . $e->getMessage());
                }

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'redirect' => $target_redirect,
                        'role_id' => $role_id,
                        'role' => $role_name,
                        'full_name' => $_SESSION['full_name']
                    ]);
                    exit();
                }

                redirect($target_redirect);
                exit();
            } else {
                $error = "Invalid username/email or password.";
                $field_errors['password'] = true;
                $field_errors['username'] = true;

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error, 'field_errors' => $field_errors]);
                    exit();
                }
            }
        } else {
            $error = "Invalid username/email or password.";
            $field_errors['username'] = true;
            $field_errors['password'] = true;

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error, 'field_errors' => $field_errors]);
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - One Palayan-Kalinga Hub</title>
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

        /* Refined Deep Forest Green with Soft Green & Professional Maroon Accents */
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
            background: radial-gradient(circle, rgba(167, 215, 197, 0.09) 0%, rgba(255, 255, 255, 0.02) 70%, transparent 100%);
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
            padding: 40px;
            position: relative;
            z-index: 2;
        }

        .auth-card {
            display: flex;
            flex-direction: row;
            max-width: 1100px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.4);
            transition: transform 0.5s cubic-bezier(0.65, 0, 0.35, 1), box-shadow 0.3s;
            position: relative;
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 35px 70px -12px rgba(0, 0, 0, 0.45);
        }

        .auth-brand, .auth-form {
            transition: transform 0.5s cubic-bezier(0.65, 0, 0.35, 1), opacity 0.45s ease;
            will-change: transform, opacity;
        }

        /* Smooth visual position-switch transition to Register */
        @media (min-width: 901px) {
            .auth-card.card-switching-to-register .auth-brand {
                transform: translateX(100%);
                opacity: 0.82;
            }
            .auth-card.card-switching-to-register .auth-form {
                transform: translateX(-100%);
                opacity: 0.82;
            }

            .auth-card.entering-from-register .auth-brand {
                animation: slideBrandIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            .auth-card.entering-from-register .auth-form {
                animation: slideFormIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            @keyframes slideBrandIn {
                from { transform: translateX(100%); opacity: 0.7; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideFormIn {
                from { transform: translateX(-100%); opacity: 0.7; }
                to { transform: translateX(0); opacity: 1; }
            }
        }

        @media (max-width: 900px) {
            .auth-card.card-switching-to-register {
                opacity: 0;
                transform: translateY(20px) scale(0.97);
            }
            .auth-card.entering-from-register {
                animation: fadeInMobile 0.5s ease forwards;
            }
            @keyframes fadeInMobile {
                from { opacity: 0; transform: translateY(-20px) scale(0.97); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
        }

        .auth-brand {
            font-family: 'Poppins', sans-serif;
            flex: 1;
            background: linear-gradient(160deg, rgba(15, 65, 74, 0.85) 0%, rgba(127, 3, 3, 0.8) 100%), url('images/palayan-bg.png');
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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
            font-family: 'Poppins', sans-serif;
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        }

        .brand-logo p {
            font-family: 'Poppins', sans-serif;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.85rem;
            font-weight: 500;
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
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #D8BA98;
        }

        .feature-item h4 {
            font-family: 'Poppins', sans-serif;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .feature-item p {
            font-family: 'Poppins', sans-serif;
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.78rem;
            font-weight: 400;
        }

        .brand-quote {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            position: relative;
            z-index: 2;
        }

        .brand-quote p {
            font-family: 'Poppins', sans-serif;
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            font-size: 0.88rem;
            font-weight: 400;
        }

        .brand-quote span {
            font-family: 'Poppins', sans-serif;
            display: block;
            margin-top: 8px;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 500;
        }

        .auth-form {
            flex: 1;
            padding: 48px 40px;
            background: white;
        }

        .auth-header {
            margin-bottom: 36px;
            padding-bottom: 6px;
        }

        .auth-header h2 {
            font-family: 'Dancing Script', cursive;
            font-size: 2.7rem;
            font-weight: 700;
            color: #0F414A;
            line-height: 1.2;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .auth-header p {
            color: #6B7A8A;
            font-size: 0.85rem;
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
            box-shadow: 0 0 0 3px rgba(15, 65, 74, 0.1);
        }

        .input-group input.error {
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            background: #FFF5F5;
        }

        .input-group input.success {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0 24px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #7F0303;
        }

        .checkbox-group label {
            color: #5C6B7A;
            font-size: 0.85rem;
            cursor: pointer;
            margin: 0;
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
        }

        .btn-primary:hover {
            background: #9a1515;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.3);
        }

        /* Smooth Top Progress Transition Bar */
        .auth-transition-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 4px;
            width: 0%;
            background: linear-gradient(90deg, #10B981, #059669, #C9A86C, #7F0303);
            z-index: 99999;
            transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 0 14px rgba(16, 185, 129, 0.85);
            pointer-events: none;
        }

        /* Button States: Authenticating & Success */
        .btn-primary.is-authenticating {
            background: #5E0202 !important;
            cursor: wait !important;
            pointer-events: none !important;
            opacity: 0.95;
            box-shadow: 0 4px 14px rgba(94, 2, 2, 0.4) !important;
        }
        .btn-primary.is-success {
            background: linear-gradient(135deg, #10B981, #059669) !important;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4) !important;
            pointer-events: none !important;
            color: #ffffff !important;
        }

        /* Card Scale & Blur Transition into Dashboard */
        .auth-card.is-logging-in {
            transform: scale(0.982) translateY(-4px);
            opacity: 0.15;
            filter: blur(2.5px);
            transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        /* Gentle Card Shake on Validation / Auth Error */
        @keyframes authCardShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }
        .auth-card.shake {
            animation: authCardShake 0.45s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        /* Minimal Spinner */
        .login-spinner {
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: loginSpin 0.75s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }
        @keyframes loginSpin {
            to { transform: rotate(360deg); }
        }

        .staff-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
        }

        .staff-link a {
            color: #7F0303;
            text-decoration: none;
            font-weight: 500;
        }

        .staff-link a:hover {
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

        /* Guided Post-Registration Banner */
        .guided-registration-banner {
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
            border: 1.5px solid #10B981;
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
            animation: bannerSlideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes bannerSlideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .guided-banner-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #10B981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            animation: iconPulse 2s infinite ease-in-out;
        }
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        .guided-banner-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #065F46;
        }
        .guided-banner-desc {
            font-size: 0.78rem;
            color: #047857;
            margin-top: 2px;
            line-height: 1.35;
        }

        /* Guided Glowing Button & Floating Pointer Pill */
        @keyframes guidedGlowPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7), 0 8px 20px rgba(127, 3, 3, 0.3);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(16, 185, 129, 0), 0 12px 28px rgba(16, 185, 129, 0.45);
                transform: scale(1.025);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0), 0 8px 20px rgba(127, 3, 3, 0.3);
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

        @media (max-width: 900px) {
            .auth-card {
                flex-direction: column;
                max-width: 500px;
                border-radius: 28px;
            }

            .auth-brand {
                padding: 24px 20px;
            }

            .brand-features {
                display: none;
            }

            .brand-quote {
                margin-top: 16px;
            }

            .auth-form {
                padding: 28px 24px;
            }

            .toast-container {
                top: 10px;
                right: 10px;
                max-width: 340px;
            }
        }

        @media (max-width: 768px) {
            .auth-container {
                padding: 16px 12px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .auth-card {
                border-radius: 22px;
                box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            }

            .auth-brand {
                padding: 14px 16px !important;
            }

            .brand-logo {
                text-align: center;
            }

            .logo-circle {
                width: 44px !important;
                height: 44px !important;
                margin: 0 auto 6px !important;
            }

            .logo-circle img {
                width: 28px !important;
                height: 28px !important;
            }

            .brand-title-text {
                font-size: 0.92rem !important;
                margin-bottom: 2px !important;
                letter-spacing: 0.4px !important;
            }

            .brand-logo p {
                font-size: 0.72rem !important;
                margin-bottom: 0 !important;
                opacity: 0.85;
            }

            .brand-quote {
                display: none !important;
            }

            .auth-form {
                padding: 16px 16px 20px !important;
            }

            .auth-header {
                margin-bottom: 12px !important;
                padding-bottom: 0 !important;
                text-align: center;
            }

            .auth-logos-row {
                gap: 10px !important;
                margin-bottom: 8px !important;
            }

            .auth-logos-row img {
                width: 38px !important;
                height: 38px !important;
            }

            .cursive-title {
                font-size: 1.35rem !important;
                margin-bottom: 2px !important;
            }

            .auth-header p {
                font-size: 0.76rem !important;
                margin-top: 2px !important;
            }

            .form-group {
                margin-bottom: 11px !important;
            }

            .form-group label {
                margin-bottom: 4px !important;
                font-size: 0.76rem !important;
            }

            .input-group input {
                padding: 10px 14px !important;
                font-size: 0.86rem !important;
                border-radius: 12px !important;
            }

            .btn-login {
                padding: 10px !important;
                font-size: 0.88rem !important;
                border-radius: 12px !important;
                margin-top: 4px !important;
            }

            .auth-footer {
                margin-top: 14px !important;
                padding-top: 12px !important;
            }

            .auth-footer p {
                font-size: 0.75rem !important;
                margin-bottom: 6px !important;
            }

            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: unset;
            }

            .toast {
                padding: 12px 14px;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 10px 8px;
            }
            .auth-card {
                border-radius: 18px;
            }
            .auth-brand {
                padding: 12px 14px !important;
            }
            .auth-form {
                padding: 14px 14px 18px !important;
            }
            .cursive-title {
                font-size: 1.25rem !important;
            }
        }
    </style>
</head>

<body>
    <!-- Top Smooth Page Transition Bar -->
    <div id="authTransitionBar" class="auth-transition-bar"></div>

    <!-- Animated Gradient Background -->
    <div class="animated-bg"></div>
    <div class="ambient-lighting"></div>

    <!-- Floating Decorative Shapes -->
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

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Left Side - Branding with Background Image -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png?v=<?php echo @filemtime(__DIR__ . '/images/logo.png') ?: time(); ?>" alt="One Palayan-Kalinga Hub Logo"
                            onerror="this.src='https://placehold.co/80x80?text=PKH'">
                    </div>
                    <h2 class="brand-title-text">ONE PALAYAN-KALINGA HUB</h2>
                    <p>CSWDO Palayan City</p>
                </div>

                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-calendar-check"></i>
                        <div>
                            <h4>Easy Appointment</h4>
                            <p>Book your schedule online</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-hand-holding-heart"></i>
                        <div>
                            <h4>AICS Assistance</h4>
                            <p>Track your request status</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-home"></i>
                        <div>
                            <h4>Housing Programs</h4>
                            <p>Apply for housing assistance</p>
                        </div>
                    </div>
                </div>

                <div class="brand-quote">
                    <p>"Serving Palayan City with excellence and compassion"</p>
                    <span>— CSWDO Palayan City</span>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div class="auth-form">
                <div class="auth-header">
                    <div class="auth-logos-row" style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 16px;">
                        <img src="images/palayan-city-seal.png" alt="Lungsod ng Palayan Seal" style="width: 76px; height: 76px; border-radius: 50%; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.12));">
                        <img src="images/cswdo-palayan-logo.png" alt="CSWDO Palayan City Logo" style="width: 76px; height: 76px; border-radius: 50%; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.12));">
                    </div>
                    <h2 class="cursive-title">Welcome Back, Palayano!</h2>
                    <p style="color: #64748B; font-size: 0.88rem; margin-top: 6px;">Sign in to access your city social services &amp; assistance</p>
                </div>

                <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                    <div class="guided-registration-banner">
                        <div class="guided-banner-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="guided-banner-content">
                            <div class="guided-banner-title">Registration Successful! 🎉</div>
                            <div class="guided-banner-desc">Please log in below to submit your <strong>Residency Verification</strong> and activate city services.</div>
                        </div>
                    </div>
                <?php endif; ?>


                <?php if ($timeout_notice): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <div>Your session has expired due to inactivity. Please sign in again.</div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    <?php echo csrf_field(); ?>
                    <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                        <input type="hidden" name="registered" value="1">
                    <?php endif; ?>
                    <?php if (!empty($redirect)): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username or Email <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="text" id="username" name="username" required
                                placeholder="Enter your username or email"
                                value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        </div>
                        <span class="field-error-hint" id="usernameError">Please enter your username or email</span>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" required id="password"
                                placeholder="Enter your password"
                                value="<?php echo htmlspecialchars($form_data['password']); ?>">
                            <span class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye-slash"></i>
                            </span>
                        </div>
                        <span class="field-error-hint" id="passwordError">Please enter your password</span>
                        <div style="display: flex; justify-content: flex-end; margin-top: 6px; margin-bottom: 2px;">
                            <a href="forgot-password.php" style="font-size: 0.82rem; color: #7F0303; text-decoration: none; font-weight: 600; transition: color 0.3s;" onmouseover="this.style.color='#9a1515'" onmouseout="this.style.color='#7F0303'">Forgot Password?</a>
                        </div>
                    </div>
                    <button type="submit" id="loginSubmitBtn" class="btn-primary <?php echo (isset($_GET['registered']) && $_GET['registered'] == '1') ? 'guided-btn-glow' : ''; ?>">
                        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                            <span class="guided-pointer-pill"><i class="fas fa-shield-alt"></i> Step 1: Verify</span>
                            SIGN IN TO VERIFY ACCOUNT <i class="fas fa-arrow-right"></i>
                        <?php else: ?>
                            SIGN IN <i class="fas fa-arrow-right"></i>
                        <?php endif; ?>
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" id="createAccountBtn">Create account</a></p>
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

        // ========== LIVE FIELD VALIDATION & ERROR RESET ==========
        const usernameInput = document.getElementById('username');
        const usernameError = document.getElementById('usernameError');
        const passwordInput = document.getElementById('password');
        const passwordError = document.getElementById('passwordError');

        function clearCredentialErrors() {
            usernameInput.classList.remove('error');
            passwordInput.classList.remove('error');
            usernameError.classList.remove('visible');
            passwordError.classList.remove('visible');
        }

        usernameInput.addEventListener('input', function () {
            clearCredentialErrors();
            if (!this.value.trim()) {
                this.classList.add('error');
                usernameError.classList.add('visible');
            } else {
                this.classList.add('success');
            }
        });

        passwordInput.addEventListener('input', function () {
            clearCredentialErrors();
            if (!this.value) {
                this.classList.add('error');
                passwordError.classList.add('visible');
            } else {
                this.classList.add('success');
            }
        });

        usernameInput.addEventListener('blur', function () {
            if (!this.value.trim()) {
                this.classList.add('error');
                this.classList.remove('success');
                usernameError.classList.add('visible');
            }
        });

        passwordInput.addEventListener('blur', function () {
            if (!this.value) {
                this.classList.add('error');
                this.classList.remove('success');
                passwordError.classList.add('visible');
            }
        });

        // ========== SMOOTH AJAX FORM SUBMISSION & PAGE TRANSITION ==========
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('loginSubmitBtn') || loginForm.querySelector('button[type="submit"]');
        const transitionBar = document.getElementById('authTransitionBar');

        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            let hasError = false;

            if (!username) {
                usernameInput.classList.add('error');
                usernameError.classList.add('visible');
                hasError = true;
            }

            if (!password) {
                passwordInput.classList.add('error');
                passwordError.classList.add('visible');
                hasError = true;
            }

            if (hasError) {
                if (authCard) {
                    authCard.classList.remove('shake');
                    void authCard.offsetWidth;
                    authCard.classList.add('shake');
                }
                showToast('error', 'Validation Error', 'Please enter both your username/email and password.');
                return false;
            }

            // Lock button & show authenticating animation
            const originalBtnHTML = submitBtn.innerHTML;
            submitBtn.classList.add('is-authenticating');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="login-spinner"></span> Authenticating...';

            if (transitionBar) {
                transitionBar.style.width = '40%';
            }

            try {
                const formData = new FormData(loginForm);
                formData.append('ajax', '1');

                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Smooth Transition to Dashboard
                    if (transitionBar) {
                        transitionBar.style.width = '100%';
                    }
                    submitBtn.classList.remove('is-authenticating');
                    submitBtn.classList.add('is-success');
                    submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verification Successful! Entering Hub...';

                    if (authCard) {
                        authCard.classList.add('is-logging-in');
                    }

                    // Seamless redirection timing matching CSS transition
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 420);
                } else {
                    // Revert button & shake card
                    if (transitionBar) {
                        transitionBar.style.width = '0%';
                    }
                    submitBtn.classList.remove('is-authenticating');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;

                    if (authCard) {
                        authCard.classList.remove('shake');
                        void authCard.offsetWidth;
                        authCard.classList.add('shake');
                    }

                    if (data.field_errors) {
                        if (data.field_errors.username) {
                            usernameInput.classList.add('error');
                        }
                        if (data.field_errors.password) {
                            passwordInput.classList.add('error');
                        }
                    } else {
                        usernameInput.classList.add('error');
                        passwordInput.classList.add('error');
                    }

                    showToast('error', 'Login Error', data.error || 'Invalid credentials. Please try again.', 6000);
                    passwordInput.value = '';
                    passwordInput.focus();
                }
            } catch (err) {
                console.warn('AJAX submit fallback:', err);
                // Graceful fallback to native form submission
                loginForm.submit();
            }
        });

        // ========== PAGE SWITCHING ANIMATION ==========
        const urlParams = new URLSearchParams(window.location.search);
        const authCard = document.querySelector('.auth-card');

        if (urlParams.get('from') === 'register' && authCard) {
            authCard.classList.add('entering-from-register');
        }

        const createAccountBtn = document.getElementById('createAccountBtn');
        if (createAccountBtn && authCard) {
            createAccountBtn.addEventListener('click', function(e) {
                e.preventDefault();
                authCard.classList.remove('entering-from-register');
                authCard.classList.add('card-switching-to-register');
                setTimeout(() => {
                    window.location.href = 'register.php?from=login<?php echo !empty($redirect) ? '&redirect=' . urlencode($redirect) : ''; ?>';
                }, 450);
            });
        }

        // ========== SHOW PHP ERRORS AS TOAST & HIGHLIGHT BOTH FIELDS ==========
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                showToast('success', 'Registration Completed', 'Please sign in to proceed to residency verification.', 7000);
            <?php endif; ?>

            <?php if ($error): ?>
                showToast('error', 'Login Error', `<?php echo addslashes($error); ?>`, 6000);
            <?php endif; ?>

            // Highlight both fields in red warning state on authentication failure
            <?php if (isset($field_errors['username'])): ?>
                usernameInput.classList.add('error');
                if (!usernameInput.value.trim()) {
                    usernameError.classList.add('visible');
                }
            <?php endif; ?>

            <?php if (isset($field_errors['password'])): ?>
                passwordInput.classList.add('error');
                if (!passwordInput.value) {
                    passwordError.classList.add('visible');
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>
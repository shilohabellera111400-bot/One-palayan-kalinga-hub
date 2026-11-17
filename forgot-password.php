<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle Restart / Cancel
if (isset($_GET['action']) && $_GET['action'] === 'restart') {
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_phone']);
    unset($_SESSION['reset_name']);
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_verified']);
    unset($_SESSION['reset_attempts']);
    unset($_SESSION['reset_resend_cooldown']);
    redirect('forgot-password.php');
}

// Determine active step: 'email' (Step 1), 'verify' (Step 2), 'password' (Step 3), 'success' (Step 4)
$step = $_SESSION['reset_step'] ?? 'email';

// If user attempts to access verify or password step without email in session, fallback to step 1
if (($step === 'verify' || $step === 'password') && empty($_SESSION['reset_user_id'])) {
    $step = 'email';
    $_SESSION['reset_step'] = 'email';
}

// Helper to mask email for privacy (e.g. jo***e@gmail.com)
function maskEmail($email) {
    if (empty($email)) return '';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(3, $len - 3)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

// Helper to mask phone for privacy (e.g. 0917****567)
function maskPhone($phone) {
    if (empty($phone)) return '';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) >= 10) {
        return substr($clean, 0, 4) . '****' . substr($clean, -3);
    }
    return $phone;
}

// ==========================================
// 1. STEP 1: REQUEST VERIFICATION CODE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    csrf_verify();
    
    $input = trim(sanitize($_POST['email'] ?? ''));
    
    if (empty($input)) {
        $error = "Mangyaring ilagay ang iyong email address o mobile number.";
    } else {
        $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL);
        $user = null;
        
        if ($isEmail) {
            // Find user by email
            $query = "SELECT user_id, first_name, last_name, username, email, contact_number, status FROM users WHERE email = :email LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $input);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Find user by mobile number or username
            $cleanPhone = preg_replace('/[^0-9]/', '', $input);
            $stdPhone = (strpos($cleanPhone, '63') === 0) ? ('0' . substr($cleanPhone, 2)) : $cleanPhone;
            $e164Phone = (strpos($cleanPhone, '09') === 0) ? ('+63' . substr($cleanPhone, 1)) : ('+' . $cleanPhone);

            $query = "SELECT user_id, first_name, last_name, username, email, contact_number, status 
                      FROM users 
                      WHERE (contact_number = :raw OR contact_number = :std OR contact_number = :e164 OR username = :uname)
                      LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':raw'   => $input,
                ':std'   => $stdPhone,
                ':e164'  => $e164Phone,
                ':uname' => $input
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($user) {
            // Check if account is inactive/banned
            if (isset($user['status']) && $user['status'] === 'inactive') {
                $error = "Ang account na ito ay kasalukuyang hindi aktibo. Mangyaring makipag-ugnayan sa CSWDO Palayan.";
            } else {
                // Generate secure 6-digit numeric OTP code
                $code = strval(random_int(100000, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Remove previous reset tokens for this user
                $del_stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :uid");
                $del_stmt->execute([':uid' => $user['user_id']]);
                
                // Save OTP code in database
                $ins_stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, :expires)");
                $ins_stmt->execute([
                    ':uid'     => $user['user_id'],
                    ':token'   => $code,
                    ':expires' => $expires_at
                ]);
                
                $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];

                // 1. Send Email (if available)
                if (!empty($user['email'])) {
                    try {
                        $mailer = new Mailer();
                        $mailer->sendPasswordResetCode($user['email'], $full_name, $code);
                    } catch (Exception $e) {
                        error_log("Failed sending reset OTP code email: " . $e->getMessage());
                    }
                }

                // 2. Send SMS via TextBee (if mobile number exists)
                if (!empty($user['contact_number'])) {
                    try {
                        require_once __DIR__ . '/includes/SMSGateway.php';
                        $smsGateway = new SMSGateway();
                        $smsGateway->sendPasswordResetOTP($user['contact_number'], $full_name, $code, (int)$user['user_id']);
                    } catch (\Throwable $e) {
                        error_log("Failed sending reset OTP code SMS: " . $e->getMessage());
                    }
                }
                
                // Store state in session
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email']   = $user['email'] ?? '';
                $_SESSION['reset_phone']   = $user['contact_number'] ?? '';
                $_SESSION['reset_name']    = $full_name;
                $_SESSION['reset_step']    = 'verify';
                $_SESSION['reset_attempts'] = 0;
                $_SESSION['reset_cooldown'] = time() + 60; // 60-second resend cooldown
                
                $step = 'verify';
                if (!empty($user['contact_number']) && !empty($user['email'])) {
                    $success = "Ipinadala na ang 6-digit verification code sa iyong email (" . maskEmail($user['email']) . ") at SMS (" . maskPhone($user['contact_number']) . ")!";
                } elseif (!empty($user['contact_number'])) {
                    $success = "Ipinadala na ang 6-digit verification code via SMS sa (" . maskPhone($user['contact_number']) . ")!";
                } else {
                    $success = "Ipinadala na ang 6-digit verification code sa iyong email (" . maskEmail($user['email']) . ")!";
                }
            }
        } else {
            // User not found: provide clear feedback
            $error = "Walang nahanap na account na nakarehistro sa impormasyong ito. Pakisuri ang tamang baybay.";
        }
    }
}

// ==========================================
// 2. STEP 2: RESEND VERIFICATION CODE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code']) && !empty($_SESSION['reset_user_id'])) {
    csrf_verify();
    
    // Check cooldown
    $now = time();
    $cooldown = $_SESSION['reset_cooldown'] ?? 0;
    if ($now < $cooldown) {
        $remaining = $cooldown - $now;
        $error = "Maghintay ng {$remaining} segundo bago humiling muli ng bagong code.";
    } else {
        // Generate new 6-digit code
        $code = strval(random_int(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $del_stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :uid");
        $del_stmt->execute([':uid' => $_SESSION['reset_user_id']]);
        
        $ins_stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, :expires)");
        $ins_stmt->execute([
            ':uid'     => $_SESSION['reset_user_id'],
            ':token'   => $code,
            ':expires' => $expires_at
        ]);
        
        $full_name = $_SESSION['reset_name'] ?? 'Resident';

        // Resend Email
        if (!empty($_SESSION['reset_email'])) {
            try {
                $mailer = new Mailer();
                $mailer->sendPasswordResetCode($_SESSION['reset_email'], $full_name, $code);
            } catch (Exception $e) {
                error_log("Failed resending reset code email: " . $e->getMessage());
            }
        }

        // Resend SMS
        if (!empty($_SESSION['reset_phone'])) {
            try {
                require_once __DIR__ . '/includes/SMSGateway.php';
                $smsGateway = new SMSGateway();
                $smsGateway->sendPasswordResetOTP($_SESSION['reset_phone'], $full_name, $code, (int)$_SESSION['reset_user_id']);
            } catch (\Throwable $e) {
                error_log("Failed resending reset code SMS: " . $e->getMessage());
            }
        }
        
        $_SESSION['reset_cooldown'] = time() + 60;
        $_SESSION['reset_attempts'] = 0;
        $success = "May bagong 6-digit verification code na ipinadala sa iyong account!";
    }
    $step = 'verify';
}

// ==========================================
// 3. STEP 2: VERIFY OTP CODE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code']) && !empty($_SESSION['reset_user_id'])) {
    csrf_verify();
    
    // Combine 6 boxes if sent individually, or read single input
    $code_input = trim($_POST['otp_code'] ?? '');
    if (empty($code_input) && isset($_POST['digit_0'])) {
        $code_input = trim($_POST['digit_0'] . $_POST['digit_1'] . $_POST['digit_2'] . $_POST['digit_3'] . $_POST['digit_4'] . $_POST['digit_5']);
    }
    
    $_SESSION['reset_attempts'] = ($_SESSION['reset_attempts'] ?? 0) + 1;
    
    if (empty($code_input) || strlen($code_input) !== 6 || !ctype_digit($code_input)) {
        $error = "Pakilagay ang kumpletong 6-digit verification code.";
        $step = 'verify';
    } elseif ($_SESSION['reset_attempts'] > 5) {
        // Exceeded allowed attempts
        $del_stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :uid");
        $del_stmt->execute([':uid' => $_SESSION['reset_user_id']]);
        
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_step']);
        $error = "Masyadong maraming maling pagsubok para sa code na ito. Mangyaring humiling muli ng bago para sa iyong seguridad.";
        $step = 'email';
    } else {
        // Verify code in database
        $v_stmt = $db->prepare("SELECT * FROM password_resets WHERE user_id = :uid AND token = :token AND expires_at > NOW() LIMIT 1");
        $v_stmt->execute([
            ':uid'   => $_SESSION['reset_user_id'],
            ':token' => $code_input
        ]);
        $valid_reset = $v_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($valid_reset) {
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_step']     = 'password';
            $step = 'password';
            $success = "Matagumpay na na-verify ang iyong code! Maaari ka nang maglagay ng bagong password.";
        } else {
            $remaining_attempts = max(0, 5 - $_SESSION['reset_attempts']);
            $error = "Maling verification code o nag-expire na ito. Pakisubukang muli (May natitira pang {$remaining_attempts} pagsubok).";
            $step = 'verify';
        }
    }
}

// ==========================================
// 4. STEP 3: SAVE NEW PASSWORD
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_password']) && !empty($_SESSION['reset_user_id'])) {
    csrf_verify();
    
    if (empty($_SESSION['reset_verified'])) {
        $error = "Kailangan munang ma-verify ang iyong code bago makapag-reset ng password.";
        $step = 'verify';
    } else {
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Password strength validation
        $uppercase = preg_match('@[A-Z]@', $new_password);
        $lowercase = preg_match('@[a-z]@', $new_password);
        $number    = preg_match('@[0-9]@', $new_password);
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Mangyaring punan ang lahat ng kinakailangang impormasyon.";
            $step = 'password';
        } elseif (strlen($new_password) < 8) {
            $error = "Ang bagong password ay dapat may hindi bababa sa 8 characters.";
            $step = 'password';
        } elseif (!$uppercase || !$lowercase || !$number) {
            $error = "Dapat naglalaman ang password ng kahit isang malaking titik (A-Z), maliit na titik (a-z), at numero (0-9).";
            $step = 'password';
        } elseif ($new_password !== $confirm_password) {
            $error = "Hindi nagtutugma ang bagong password at ang kumpirmasyon.";
            $step = 'password';
        } else {
            // Hash and update password
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            
            try {
                $db->beginTransaction();
                
                $upd_stmt = $db->prepare("UPDATE users SET password = :pwd WHERE user_id = :uid");
                $upd_stmt->execute([
                    ':pwd' => $hashed,
                    ':uid' => $_SESSION['reset_user_id']
                ]);
                
                // Clear reset token from database
                $del_stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :uid");
                $del_stmt->execute([':uid' => $_SESSION['reset_user_id']]);
                
                // Insert security audit log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $audit_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values, ip_address) 
                                           VALUES (:uid, 'password_reset_otp', 'users', 'Reset password via verified 6-digit OTP code', :ip)");
                $audit_stmt->execute([
                    ':uid' => $_SESSION['reset_user_id'],
                    ':ip'  => $ip
                ]);
                
                $db->commit();
                
                // Send security notification email
                try {
                    $mailer = new Mailer();
                    if (!empty($_SESSION['reset_email'])) {
                        $mailer->sendPasswordChangeAlert($_SESSION['reset_email'], $_SESSION['reset_name'] ?? 'Resident', $ip);
                    }
                } catch (Exception $e) {
                    error_log("Failed sending password change alert email: " . $e->getMessage());
                }

                // Send security notification SMS via TextBee
                if (!empty($_SESSION['reset_phone'])) {
                    try {
                        require_once __DIR__ . '/includes/SMSGateway.php';
                        $smsGateway = new SMSGateway();
                        $smsGateway->sendPasswordChangeAlert($_SESSION['reset_phone'], $_SESSION['reset_name'] ?? 'Resident', $ip, (int)$_SESSION['reset_user_id']);
                    } catch (\Throwable $e) {
                        error_log("Failed sending password change alert SMS: " . $e->getMessage());
                    }
                }
                
                // Clean up session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_phone']);
                unset($_SESSION['reset_name']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_attempts']);
                unset($_SESSION['reset_cooldown']);
                
                $_SESSION['reset_step'] = 'success';
                $step = 'success';
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "May naganap na error habang sine-save ang password: " . $e->getMessage();
                $step = 'password';
            }
        }
    }
}

// Calculate remaining cooldown seconds for UI
$remaining_cooldown = max(0, ($_SESSION['reset_cooldown'] ?? 0) - time());
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

        :root {
            --primary: #7F0303;
            --primary-hover: #9A1515;
            --primary-dark: #5A0202;
            --teal: #0F414A;
            --teal-light: #1A6A78;
            --surface-card: #FFFFFF;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --border-light: #E2E8F0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0A1628 0%, #1a2a4a 50%, #0F414A 100%);
            position: relative;
            overflow-x: hidden;
            color: var(--text-main);
        }

        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0F414A 0%, #7F0303 50%, #1A6A78 100%);
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
            inset: 0;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .floating-shapes .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .floating-shapes .shape-1 { width: 380px; height: 380px; top: -100px; right: -100px; animation-duration: 25s; }
        .floating-shapes .shape-2 { width: 240px; height: 240px; bottom: -50px; left: -50px; animation-duration: 20s; }
        .floating-shapes .shape-3 { width: 140px; height: 140px; top: 40%; left: 15%; animation-duration: 18s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-25px) rotate(8deg); }
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            position: relative;
            z-index: 2;
        }

        .auth-card {
            display: flex;
            flex-direction: row;
            max-width: 1050px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        /* Left Side - Brand Panel */
        .auth-brand {
            flex: 0 0 42%;
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.92) 0%, rgba(127, 3, 3, 0.88) 100%), url('images/palayan-bg.png');
            background-size: cover;
            background-position: center;
            padding: 44px 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            color: #ffffff;
        }

        .auth-brand::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -20%;
            width: 280px;
            height: 280px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-logo {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .logo-circle {
            width: 105px;
            height: 105px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .logo-circle img {
            width: 90px;
            height: auto;
        }

        .brand-logo h2 {
            color: white;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .brand-logo p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.76rem;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .brand-feature-box {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 25px 0;
        }

        .brand-feature-box h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #FDE68A;
        }

        .brand-feature-box p {
            font-size: 0.8rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
        }

        .brand-quote {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-quote p {
            font-style: italic;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.92);
        }

        .brand-quote span {
            display: block;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 4px;
        }

        /* Right Side - Form Panel */
        .auth-form {
            flex: 1;
            padding: 44px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        /* Steps Indicator Bar */
        .reset-steps-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
            position: relative;
        }

        .step-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #94A3B8;
            transition: all 0.25s ease;
        }

        .step-pill .step-circle {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E2E8F0;
            color: #64748B;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .step-pill.active {
            color: var(--primary);
        }

        .step-pill.active .step-circle {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(127, 3, 3, 0.35);
        }

        .step-pill.done {
            color: #059669;
        }

        .step-pill.done .step-circle {
            background: #10B981;
            color: #ffffff;
        }

        .step-divider {
            flex: 1;
            height: 2px;
            background: #E2E8F0;
            margin: 0 10px;
        }

        .step-divider.active {
            background: #10B981;
        }

        .auth-header {
            margin-bottom: 22px;
        }

        .auth-header h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .auth-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.55;
        }

        /* Alerts */
        .alert {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #FFF5F5;
            border: 1.5px solid #FECDD3;
            color: #9F1239;
        }

        .alert-success {
            background: #F0FDF4;
            border: 1.5px solid #BBF7D0;
            color: #166534;
        }

        .alert i {
            margin-top: 2px;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-group label i {
            color: var(--teal);
            margin-right: 4px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            font-size: 0.9rem;
            color: var(--text-main);
            background: #F8FAFC;
            transition: all 0.2s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.12);
        }

        .toggle-password-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 6px;
            transition: color 0.2s;
        }

        .toggle-password-btn:hover {
            color: var(--text-main);
        }

        /* 6-Digit OTP Box Grid */
        .otp-inputs-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 22px 0 16px;
        }

        .otp-box {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 800;
            font-family: monospace;
            color: var(--primary);
            background: #F8FAFC;
            border: 2px solid #CBD5E1;
            border-radius: 12px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .otp-box:focus {
            outline: none;
            background: #FFFFFF;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.15);
            transform: translateY(-2px);
        }

        .otp-box.filled {
            border-color: #0F414A;
            background: #FFFFFF;
        }

        /* Buttons */
        .btn-submit-main {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #ffffff;
            border: none;
            border-radius: 40px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.3);
            transition: all 0.25s ease;
            margin-top: 10px;
        }

        .btn-submit-main:hover {
            background: linear-gradient(135deg, var(--primary-hover) 0%, var(--primary-dark) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(127, 3, 3, 0.4);
        }

        .btn-submit-main:active {
            transform: translateY(0);
        }

        /* OTP Helper & Resend Controls */
        .otp-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border-light);
        }

        .btn-resend {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-resend:hover:not(:disabled) {
            background: #FEE2E2;
            text-decoration: underline;
        }

        .btn-resend:disabled {
            color: #94A3B8;
            cursor: not-allowed;
            text-decoration: none;
        }

        /* Password Strength Bar & Checklist */
        .pwd-strength-container {
            margin: 12px 0 16px;
        }

        .pwd-strength-bar {
            height: 6px;
            background: #E2E8F0;
            border-radius: 3px;
            overflow: hidden;
            display: flex;
            margin-bottom: 8px;
        }

        .pwd-strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        .strength-weak { width: 33%; background: #EF4444; }
        .strength-medium { width: 66%; background: #F59E0B; }
        .strength-strong { width: 100%; background: #10B981; }

        .pwd-checklist {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .pwd-rule {
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .pwd-rule i {
            font-size: 0.7rem;
            color: #CBD5E1;
        }

        .pwd-rule.passed {
            color: #059669;
            font-weight: 600;
        }

        .pwd-rule.passed i {
            color: #10B981;
        }

        /* Success Step Animation Card */
        .success-panel {
            text-align: center;
            padding: 20px 10px;
        }

        .success-icon-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #D1FAE5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 18px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.25);
            animation: bounceIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Footer Links */
        .auth-footer {
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--border-light);
            text-align: center;
            font-size: 0.82rem;
        }

        .auth-footer p {
            margin-bottom: 6px;
            color: var(--text-muted);
        }

        .auth-footer a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }

        .auth-footer a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .auth-card {
                flex-direction: column;
                max-width: 520px;
            }
            .auth-brand {
                padding: 30px 24px;
            }
            .auth-form {
                padding: 32px 26px;
            }
            .brand-feature-box {
                display: none;
            }
            .otp-box {
                width: 44px;
                height: 52px;
                font-size: 1.4rem;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 15px 12px;
            }
            .auth-form {
                padding: 24px 18px;
            }
            .auth-header h2 {
                font-size: 1.35rem;
            }
            .otp-inputs-wrapper {
                gap: 6px;
            }
            .otp-box {
                width: 40px;
                height: 48px;
                font-size: 1.25rem;
            }
            .pwd-checklist {
                grid-template-columns: 1fr;
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
    </div>

    <div class="auth-container">
        <div class="auth-card">
            
            <!-- Left Side - Brand Panel -->
            <div class="auth-brand">
                <div class="brand-logo">
                    <div class="logo-circle">
                        <img src="images/logo.png?v=<?php echo @filemtime(__DIR__ . '/images/logo.png') ?: time(); ?>" alt="CSWDO Logo">
                    </div>
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>CSWDO Palayan City</p>
                </div>

                <div class="brand-feature-box">
                    <h4><i class="fas fa-shield-alt"></i> 2FA Code Verification</h4>
                    <p>Protektado ang inyong account gamit ang one-time 6-digit verification code na ligtas na ipinapadala sa inyong rehistradong email.</p>
                </div>

                <div class="brand-quote">
                    <p>"Serving Palayan City with excellence and compassion"</p>
                    <span>— City Social Welfare &amp; Development Office</span>
                </div>
            </div>

            <!-- Right Side - Interactive Step Form -->
            <div class="auth-form">

                <!-- Steps Navigation Indicator -->
                <div class="reset-steps-bar">
                    <div class="step-pill <?php echo ($step === 'email') ? 'active' : 'done'; ?>">
                        <span class="step-circle"><?php echo ($step === 'email') ? '1' : '<i class="fas fa-check"></i>'; ?></span>
                        <span>Email</span>
                    </div>
                    <div class="step-divider <?php echo ($step === 'verify' || $step === 'password' || $step === 'success') ? 'active' : ''; ?>"></div>
                    <div class="step-pill <?php echo ($step === 'verify') ? 'active' : (($step === 'password' || $step === 'success') ? 'done' : ''); ?>">
                        <span class="step-circle"><?php echo ($step === 'password' || $step === 'success') ? '<i class="fas fa-check"></i>' : '2'; ?></span>
                        <span>Verify Code</span>
                    </div>
                    <div class="step-divider <?php echo ($step === 'password' || $step === 'success') ? 'active' : ''; ?>"></div>
                    <div class="step-pill <?php echo ($step === 'password') ? 'active' : (($step === 'success') ? 'done' : ''); ?>">
                        <span class="step-circle"><?php echo ($step === 'success') ? '<i class="fas fa-check"></i>' : '3'; ?></span>
                        <span>New Password</span>
                    </div>
                </div>

                <!-- Global Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success && $step !== 'success'): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>

                <!-- ============================================== -->
                <!-- STEP 1: ENTER REGISTERED EMAIL OR PHONE         -->
                <!-- ============================================== -->
                <?php if ($step === 'email'): ?>
                    <div class="auth-header">
                        <h2><i class="fas fa-key" style="color: var(--primary);"></i> Forgot Password?</h2>
                        <p>Ilagay ang iyong rehistradong email address o mobile number upang makatanggap ng <strong>6-digit verification code</strong> para ma-reset ang password.</p>
                    </div>

                    <form method="POST" action="" id="requestForm">
                        <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-user-shield"></i> Registered Email o Mobile Number <span style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="text" id="email" name="email" required placeholder="halimbawa: juan@gmail.com o 09171234567" autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ($_SESSION['reset_email'] ?? '')); ?>">
                            </div>
                        </div>

                        <button type="submit" name="send_code" class="btn-submit-main">
                            Ipadala ang Verification Code <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>

                <!-- ============================================== -->
                <!-- STEP 2: VERIFY 6-DIGIT OTP CODE               -->
                <!-- ============================================== -->
                <?php elseif ($step === 'verify'): ?>
                    <div class="auth-header">
                        <h2><i class="fas fa-envelope-open-text" style="color: var(--primary);"></i> Enter 6-Digit Code</h2>
                        <p>
                            Ipinadala namin ang one-time code sa: <br>
                            <?php if (!empty($_SESSION['reset_email'])): ?>
                                <strong style="color: var(--teal); font-size: 0.95rem;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(maskEmail($_SESSION['reset_email'])); ?></strong><br>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['reset_phone'])): ?>
                                <strong style="color: var(--primary); font-size: 0.95rem;"><i class="fas fa-mobile-alt"></i> SMS: <?php echo htmlspecialchars(maskPhone($_SESSION['reset_phone'])); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>

                    <form method="POST" action="" id="verifyForm">
                        <?php echo csrf_field(); ?>
                        
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">
                            <i class="fas fa-shield-alt" style="color: var(--teal);"></i> Verification Code (OTP)
                        </label>

                        <!-- 6 Discrete Digit Boxes -->
                        <div class="otp-inputs-wrapper" id="otpContainer">
                            <input type="text" maxlength="1" class="otp-box" data-index="0" inputmode="numeric" pattern="[0-9]*" autocomplete="off" autofocus>
                            <input type="text" maxlength="1" class="otp-box" data-index="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            <input type="text" maxlength="1" class="otp-box" data-index="2" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            <input type="text" maxlength="1" class="otp-box" data-index="3" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            <input type="text" maxlength="1" class="otp-box" data-index="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                            <input type="text" maxlength="1" class="otp-box" data-index="5" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        </div>

                        <!-- Combined Hidden Input for Backend -->
                        <input type="hidden" name="otp_code" id="otp_code" value="">

                        <button type="submit" name="verify_code" class="btn-submit-main" id="btnVerifyCode">
                            I-verify ang Code <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <!-- Resend & Change Email Controls -->
                    <div class="otp-meta-row">
                        <form method="POST" action="" id="resendForm" style="display: inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="resend_code" value="1">
                            <button type="submit" class="btn-resend" id="btnResend" <?php echo ($remaining_cooldown > 0) ? 'disabled' : ''; ?>>
                                <i class="fas fa-redo"></i> Ipadala Muli ang Code
                            </button>
                            <span id="cooldownText" style="color: #94A3B8; font-size: 0.78rem; <?php echo ($remaining_cooldown <= 0) ? 'display:none;' : ''; ?>">
                                (Maghintay ng <span id="cooldownSeconds"><?php echo $remaining_cooldown; ?></span>s)
                            </span>
                        </form>

                        <a href="forgot-password.php?action=restart" style="color: var(--teal); font-weight: 600; text-decoration: none; font-size: 0.8rem;">
                            <i class="fas fa-edit"></i> Palitan ang Email
                        </a>
                    </div>

                <!-- ============================================== -->
                <!-- STEP 3: SET NEW PASSWORD                       -->
                <!-- ============================================== -->
                <?php elseif ($step === 'password'): ?>
                    <div class="auth-header">
                        <h2><i class="fas fa-lock" style="color: var(--primary);"></i> Create New Password</h2>
                        <p>Ilagay ang iyong bagong ligtas na password para sa iyong account.</p>
                    </div>

                    <form method="POST" action="" id="newPasswordForm">
                        <?php echo csrf_field(); ?>

                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-key"></i> Bagong Password <span style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="password" id="new_password" name="new_password" required placeholder="Hindi bababa sa 8 characters" autofocus autocomplete="new-password">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Real-time Strength Meter -->
                        <div class="pwd-strength-container">
                            <div class="pwd-strength-bar">
                                <div class="pwd-strength-fill" id="strengthBar"></div>
                            </div>
                            <div class="pwd-checklist">
                                <div class="pwd-rule" id="rule-length"><i class="fas fa-circle"></i> 8+ characters</div>
                                <div class="pwd-rule" id="rule-upper"><i class="fas fa-circle"></i> May Capital letter (A-Z)</div>
                                <div class="pwd-rule" id="rule-lower"><i class="fas fa-circle"></i> May Maliit na titik (a-z)</div>
                                <div class="pwd-rule" id="rule-number"><i class="fas fa-circle"></i> May Numero (0-9)</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-check-circle"></i> Kumpirmahin ang Password <span style="color: #DC2626;">*</span></label>
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="I-type muli ang bagong password" autocomplete="new-password">
                                <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="matchNotice" style="font-size: 0.75rem; margin-top: 5px; display: none;"></div>
                        </div>

                        <button type="submit" name="save_password" class="btn-submit-main" id="btnSavePassword">
                            I-save ang Bagong Password <i class="fas fa-save"></i>
                        </button>
                    </form>

                <!-- ============================================== -->
                <!-- STEP 4: SUCCESS CONFIRMATION                   -->
                <!-- ============================================== -->
                <?php elseif ($step === 'success'): ?>
                    <div class="success-panel">
                        <div class="success-icon-badge">
                            <i class="fas fa-check"></i>
                        </div>
                        <h2 style="font-size: 1.55rem; font-weight: 800; color: #0F414A; margin-bottom: 8px;">
                            Password Reset Successful!
                        </h2>
                        <p style="font-size: 0.88rem; color: #475569; line-height: 1.6; max-width: 380px; margin: 0 auto 24px;">
                            Matagumpay na na-update ang password para sa iyong One Palayan-Kalinga Hub account. Maaari ka nang mag-sign in gamit ang iyong bagong password.
                        </p>

                        <a href="login.php" class="btn-submit-main" style="text-decoration: none; width: auto; padding: 13px 36px; display: inline-flex;">
                            Mag-Sign In Ngayon <i class="fas fa-sign-in-alt"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Bottom Navigation -->
                <div class="auth-footer">
                    <p>Bumalik sa <a href="login.php">Sign In</a></p>
                    <p><a href="index.php" style="color: var(--teal);"><i class="fas fa-arrow-left"></i> Bumalik sa Home</a></p>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Toggle Password Show/Hide
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {

            // =====================================
            // OTP 6-Digit Auto-Focus & Paste Engine
            // =====================================
            const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
            const hiddenOtpInput = document.getElementById('otp_code');
            const btnVerifyCode = document.getElementById('btnVerifyCode');

            if (otpBoxes.length === 6) {
                // Focus first box
                otpBoxes[0].focus();

                function updateCombinedOtp() {
                    const code = otpBoxes.map(b => b.value.trim()).join('');
                    if (hiddenOtpInput) hiddenOtpInput.value = code;
                    
                    otpBoxes.forEach(b => {
                        if (b.value.trim() !== '') {
                            b.classList.add('filled');
                        } else {
                            b.classList.remove('filled');
                        }
                    });
                }

                otpBoxes.forEach((box, index) => {
                    // Handle single digit input
                    box.addEventListener('input', function (e) {
                        const val = this.value.replace(/[^0-9]/g, '');
                        this.value = val ? val.slice(-1) : '';

                        updateCombinedOtp();

                        if (this.value && index < 5) {
                            otpBoxes[index + 1].focus();
                        }
                    });

                    // Handle Backspace & Arrows
                    box.addEventListener('keydown', function (e) {
                        if (e.key === 'Backspace') {
                            if (!this.value && index > 0) {
                                otpBoxes[index - 1].focus();
                                otpBoxes[index - 1].value = '';
                                updateCombinedOtp();
                            }
                        } else if (e.key === 'ArrowLeft' && index > 0) {
                            otpBoxes[index - 1].focus();
                        } else if (e.key === 'ArrowRight' && index < 5) {
                            otpBoxes[index + 1].focus();
                        }
                    });

                    // Handle Paste (e.g. User pastes 123456 from email)
                    box.addEventListener('paste', function (e) {
                        e.preventDefault();
                        const pasted = (e.clipboardData || window.clipboardData).getData('text');
                        const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);

                        if (digits) {
                            for (let i = 0; i < 6; i++) {
                                if (i < digits.length) {
                                    otpBoxes[i].value = digits[i];
                                } else {
                                    otpBoxes[i].value = '';
                                }
                            }
                            updateCombinedOtp();

                            const nextFocus = Math.min(digits.length, 5);
                            otpBoxes[nextFocus].focus();
                        }
                    });
                });

                // Auto validate form submission has 6 digits
                const verifyForm = document.getElementById('verifyForm');
                if (verifyForm) {
                    verifyForm.addEventListener('submit', function (e) {
                        updateCombinedOtp();
                        if (!hiddenOtpInput.value || hiddenOtpInput.value.length !== 6) {
                            e.preventDefault();
                            alert('Pakilagay ang kumpletong 6-digit verification code.');
                            otpBoxes[0].focus();
                        }
                    });
                }
            }

            // =====================================
            // Resend Cooldown Countdown Timer
            // =====================================
            const btnResend = document.getElementById('btnResend');
            const cooldownText = document.getElementById('cooldownText');
            const cooldownSeconds = document.getElementById('cooldownSeconds');

            if (cooldownSeconds && btnResend) {
                let seconds = parseInt(cooldownSeconds.textContent, 10);
                if (seconds > 0) {
                    const timer = setInterval(() => {
                        seconds--;
                        cooldownSeconds.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(timer);
                            btnResend.disabled = false;
                            cooldownText.style.display = 'none';
                        }
                    }, 1000);
                }
            }

            // =====================================
            // Password Strength & Checklist Live Meter
            // =====================================
            const pwdInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const matchNotice = document.getElementById('matchNotice');

            const ruleLength = document.getElementById('rule-length');
            const ruleUpper = document.getElementById('rule-upper');
            const ruleLower = document.getElementById('rule-lower');
            const ruleNumber = document.getElementById('rule-number');

            if (pwdInput) {
                pwdInput.addEventListener('input', function () {
                    const val = this.value;

                    const hasLength = val.length >= 8;
                    const hasUpper = /[A-Z]/.test(val);
                    const hasLower = /[a-z]/.test(val);
                    const hasNumber = /[0-9]/.test(val);

                    // Update Checklist UI
                    updateRule(ruleLength, hasLength);
                    updateRule(ruleUpper, hasUpper);
                    updateRule(ruleLower, hasLower);
                    updateRule(ruleNumber, hasNumber);

                    // Calculate score (0-4)
                    let score = 0;
                    if (hasLength) score++;
                    if (hasUpper) score++;
                    if (hasLower) score++;
                    if (hasNumber) score++;

                    if (strengthBar) {
                        strengthBar.className = 'pwd-strength-fill';
                        if (score <= 2) {
                            strengthBar.classList.add('strength-weak');
                        } else if (score === 3) {
                            strengthBar.classList.add('strength-medium');
                        } else if (score === 4) {
                            strengthBar.classList.add('strength-strong');
                        }
                    }

                    checkMatch();
                });
            }

            if (confirmInput) {
                confirmInput.addEventListener('input', checkMatch);
            }

            function updateRule(el, passed) {
                if (!el) return;
                const icon = el.querySelector('i');
                if (passed) {
                    el.classList.add('passed');
                    if (icon) icon.className = 'fas fa-check-circle';
                } else {
                    el.classList.remove('passed');
                    if (icon) icon.className = 'fas fa-circle';
                }
            }

            function checkMatch() {
                if (!confirmInput || !pwdInput || !matchNotice) return;
                const pwd = pwdInput.value;
                const confirm = confirmInput.value;

                if (!confirm) {
                    matchNotice.style.display = 'none';
                    return;
                }

                matchNotice.style.display = 'block';
                if (pwd === confirm) {
                    matchNotice.style.color = '#059669';
                    matchNotice.innerHTML = '<i class="fas fa-check-circle"></i> Nagtutugma ang mga password';
                } else {
                    matchNotice.style.color = '#DC2626';
                    matchNotice.innerHTML = '<i class="fas fa-times-circle"></i> Hindi nagtutugma ang password';
                }
            }
        });
    </script>
</body>
</html>

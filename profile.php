<?php
// profile.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

$verification_status = $user_verify['verification_status'] ?? 'pending';

// Get user data
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture'])) {
    csrf_verify();
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {

        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        if ($_FILES['profile_picture']['size'] > $max_size) {
            $error = "Image is too large. Maximum size is 5MB.";
        } else {
            // Verify the actual file content, not just its extension
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $_FILES['profile_picture']['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed_mimes[$real_mime])) {
                $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
            } else {
                $file_ext = $allowed_mimes[$real_mime];
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Delete old profile picture if it exists and lives in the uploads dir
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }

                $filename = 'user_' . $_SESSION['user_id'] . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                    $update_query = "UPDATE users SET profile_picture = :profile_picture WHERE user_id = :user_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':profile_picture', $filepath);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);

                    if ($update_stmt->execute()) {
                        $success = "Profile picture updated successfully!";
                        // Refresh user data
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Failed to update profile picture.";
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    csrf_verify();
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $address = sanitize($_POST['address']);

    $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                      contact_number = :contact_number, address = :address 
                      WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':first_name', $first_name);
    $update_stmt->bindParam(':last_name', $last_name);
    $update_stmt->bindParam(':contact_number', $contact_number);
    $update_stmt->bindParam(':address', $address);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);

    if ($update_stmt->execute()) {
        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
        $success = "Profile updated successfully!";

        // Refresh user data
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Failed to update profile.";
    }
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture . '?v=' . filemtime($profile_picture)
    : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;

            --bg-primary: #F8F9FC;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E8ECF2;

            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);

            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            --input-bg: #FFFFFF;

            --radius-card: 20px;
            --radius-element: 12px;

            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;

            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);

            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;

            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;

            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .app-container { display: flex; min-height: 100vh; }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: background 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        .sidebar-header {
            padding: 28px 20px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-info-sidebar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--maroon-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }
        .user-avatar-sidebar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info-sidebar h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .user-info-sidebar span {
            color: rgba(255,255,255,0.5);
            font-size: 0.65rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 14px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 0.95rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); transform: translateX(3px); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--divider);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--text-sidebar);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 36px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05) rotate(15deg);
            box-shadow: var(--shadow-md);
        }
        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--bg-card);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover {
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 380px;
        }
        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            border-radius: var(--radius-element);
            background: var(--card-bg);
            box-shadow: var(--shadow-lg);
            border-left: 4px solid transparent;
            transform: translateX(120%);
            opacity: 0;
            animation: toastIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            position: relative;
            overflow: hidden;
        }
        .toast.hide {
            animation: toastOut 0.35s ease forwards;
        }
        @keyframes toastIn {
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastOut {
            to { transform: translateX(120%); opacity: 0; }
        }
        .toast-success { border-left-color: var(--color-success); }
        .toast-error { border-left-color: var(--color-danger); }
        .toast-icon { font-size: 1.2rem; margin-top: 1px; flex-shrink: 0; }
        .toast-success .toast-icon { color: var(--color-success); }
        .toast-error .toast-icon { color: var(--color-danger); }
        .toast-message { font-size: 0.85rem; color: var(--text-primary); font-weight: 500; flex: 1; }
        .toast-close {
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 0.9rem; flex-shrink: 0;
            transition: color 0.2s;
        }
        .toast-close:hover { color: var(--text-primary); }
        .toast-progress {
            position: absolute;
            bottom: 0; left: 0; height: 3px;
            background: currentColor;
            opacity: 0.35;
            animation: toastProgress 4.2s linear forwards;
        }
        .toast-success .toast-progress { color: var(--color-success); }
        .toast-error .toast-progress { color: var(--color-danger); }
        @keyframes toastProgress {
            from { width: 100%; } to { width: 0%; }
        }

        /* Static fallback alert (hidden once JS toast fires, kept for no-JS) */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
            animation: fadeInDown 0.4s ease;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: var(--color-success);
            border-left-color: var(--color-success);
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            border-left-color: var(--color-danger);
        }
        .alert i { font-size: 1.2rem; }

        /* ========== ENTRY ANIMATIONS ========== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        .animate-in {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
        }

        /* ========== PROFILE LAYOUT ========== */
        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 28px;
        }

        /* ========== PROFILE CARD ========== */
        .profile-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 28px 24px;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .profile-card:hover { box-shadow: var(--shadow-hover); }

        .profile-image {
            width: 140px;
            height: 140px;
            margin: 0 auto 16px;
            position: relative;
            border-radius: 50%;
            cursor: pointer;
        }
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--maroon-light);
            transition: filter 0.3s ease, background 0.4s ease;
        }
        .profile-image img.img-loading {
            filter: blur(6px);
            background: var(--badge-bg);
        }
        .profile-image .default-avatar {
            width: 100%;
            height: 100%;
            background: var(--badge-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--maroon-light);
            border: 4px solid var(--maroon-light);
        }
        .profile-image-overlay {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(0,0,0,0.45);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .profile-image:hover .profile-image-overlay { opacity: 1; }

        .upload-btn {
            margin-top: 12px;
            background: var(--maroon);
            color: white;
            padding: 8px 18px;
            border-radius: 50px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            border: none;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .upload-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .upload-btn:active { transform: translateY(0) scale(0.97); }
        .upload-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .profile-name {
            margin-top: 16px;
        }
        .profile-name h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .profile-name p {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-top: 4px;
        }

        /* Verification Badge */
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 12px;
            box-shadow: var(--shadow-sm);
        }
        .badge-verified {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .badge-pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .badge-pending .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--color-warning);
            animation: pulseDot 1.6s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.7); }
        }
        .badge-rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }
        .verify-link {
            display: inline-block;
            margin-top: 10px;
            color: var(--maroon-light);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .verify-link:hover {
            text-decoration: underline;
            color: var(--maroon);
        }

        /* ========== PROFILE FORM ========== */
        .profile-form {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .profile-form:hover { box-shadow: var(--shadow-hover); }
        .profile-form .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        .profile-form .form-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .profile-form .form-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
        }
        .form-group label i {
            color: var(--maroon-light);
            margin-right: 6px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.2s;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
            transform: translateY(-1px);
        }
        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group small {
            color: var(--text-muted);
            font-size: 0.7rem;
            display: block;
            margin-top: 4px;
        }
        .char-counter {
            text-align: right;
            font-size: 0.68rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-primary:active { transform: translateY(0) scale(0.97); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }
        .btn-secondary:active { transform: translateY(0) scale(0.97); }

        .fa-spin-fast { animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ========== CROP MODAL ========== */
        .crop-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(10, 14, 23, 0.65);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px;
            animation: fadeInDown 0.25s ease;
        }
        .crop-modal-backdrop.open { display: flex; }
        .crop-modal {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            width: 100%;
            max-width: 460px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .crop-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
        }
        .crop-modal-header h3 {
            font-size: 1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .crop-modal-header button {
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1.1rem;
            transition: color 0.2s, transform 0.2s;
        }
        .crop-modal-header button:hover { color: var(--text-primary); transform: rotate(90deg); }
        .crop-modal-body {
            padding: 20px 22px;
        }
        .crop-canvas-wrap {
            width: 100%;
            height: 300px;
            background: #111;
            border-radius: var(--radius-element);
            overflow: hidden;
        }
        .crop-canvas-wrap img { display: block; max-width: 100%; }
        .crop-controls {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 16px;
        }
        .crop-controls .zoom-icon { color: var(--text-muted); font-size: 0.85rem; }
        .crop-controls input[type="range"] {
            flex: 1;
            accent-color: var(--maroon);
        }
        .crop-rotate-buttons { display: flex; gap: 8px; }
        .crop-icon-btn {
            width: 38px; height: 38px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .crop-icon-btn:hover { background: var(--maroon); color: #fff; border-color: var(--maroon); }
        .crop-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 22px;
            border-top: 1px solid var(--border-color);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .profile-layout { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .profile-card { padding: 20px; }
            .profile-form { padding: 20px; }
            .profile-image { width: 120px; height: 120px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-primary,
            .form-actions .btn-secondary { width: 100%; justify-content: center; }
            .toast-container { left: 16px; right: 16px; max-width: none; top: 16px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .profile-image { width: 100px; height: 100px; }
            .profile-image .default-avatar { font-size: 3rem; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Integrated Service Portal</span>
            </div>
        </div>

        <div class="user-info-sidebar">
            <div class="user-avatar-sidebar">
                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <span>Resident</span>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
            <li><a href="verify-resident.php"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php"><i class="fas fa-folder-open"></i> My Documents</a></li>
            <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>

        <div class="sidebar-footer">
            <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-user-circle" style="color: var(--maroon-light); margin-right: 10px;"></i> My Profile</h1>
                <p>Manage your personal information</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
            </div>
        </div>

        <!-- No-JS fallback alerts -->
        <noscript>
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </noscript>

        <div class="profile-layout">
            <!-- Profile Picture Section -->
            <div class="profile-card animate-in" style="animation-delay: 0.05s;">
                <div class="profile-image" id="avatarClickTarget">
                    <?php if(!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture" id="avatarImg">
                    <?php else: ?>
                        <div class="default-avatar"><i class="fas fa-user-circle"></i></div>
                    <?php endif; ?>
                    <div class="profile-image-overlay"><i class="fas fa-camera"></i></div>
                </div>
                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                    <?php echo csrf_field(); ?>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;">
                    <button type="button" class="upload-btn" id="changePhotoBtn" onclick="document.getElementById('profile_picture').click()">
                        <i class="fas fa-camera"></i> Change Photo
                    </button>
                    <input type="hidden" name="upload_picture" value="1">
                </form>
                <div class="profile-name">
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p>Resident</p>
                </div>

                <!-- Verification Status Badge -->
                <div>
                    <?php if($verification_status == 'verified'): ?>
                        <span class="verification-badge badge-verified">
                            <i class="fas fa-check-circle"></i> Verified Resident
                        </span>
                    <?php elseif($verification_status == 'pending'): ?>
                        <span class="verification-badge badge-pending">
                            <span class="dot"></span> Pending Verification
                        </span>
                        <br>
                        <a href="verify-resident.php" class="verify-link">
                            <i class="fas fa-shield-alt"></i> Complete Verification
                        </a>
                    <?php elseif($verification_status == 'rejected'): ?>
                        <span class="verification-badge badge-rejected">
                            <i class="fas fa-times-circle"></i> Verification Failed
                        </span>
                        <br>
                        <a href="verify-resident.php" class="verify-link">
                            <i class="fas fa-redo-alt"></i> Re-submit Documents
                        </a>
                    <?php else: ?>
                        <span class="verification-badge badge-pending">
                            <span class="dot"></span> Not Verified
                        </span>
                        <br>
                        <a href="verify-resident.php" class="verify-link">
                            <i class="fas fa-shield-alt"></i> Verify Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Information Form -->
            <div class="profile-form animate-in" style="animation-delay: 0.15s;">
                <div class="form-header">
                    <i class="fas fa-edit"></i>
                    <h2>Edit Profile Information</h2>
                </div>
                <form method="POST" action="" id="profileForm">
                    <?php echo csrf_field(); ?>
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
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small>Email cannot be changed</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" placeholder="e.g., 09123456789">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" rows="3" maxlength="255" id="addressField" placeholder="Enter your complete address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        <div class="char-counter"><span id="addressCount">0</span>/255</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn-primary" id="saveProfileBtn">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="change-password.php" class="btn-secondary">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<!-- Crop Modal -->
<div class="crop-modal-backdrop" id="cropBackdrop">
    <div class="crop-modal">
        <div class="crop-modal-header">
            <h3><i class="fas fa-crop-alt"></i> Adjust Your Photo</h3>
            <button type="button" id="cropCloseBtn" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="crop-modal-body">
            <div class="crop-canvas-wrap">
                <img id="cropperImage" src="" alt="Crop preview">
            </div>
            <div class="crop-controls">
                <i class="fas fa-search-minus zoom-icon"></i>
                <input type="range" id="zoomRange" min="0" max="3" step="0.05" value="0">
                <i class="fas fa-search-plus zoom-icon"></i>
                <div class="crop-rotate-buttons">
                    <button type="button" class="crop-icon-btn" id="rotateLeftBtn" title="Rotate left"><i class="fas fa-undo"></i></button>
                    <button type="button" class="crop-icon-btn" id="rotateRightBtn" title="Rotate right"><i class="fas fa-redo"></i></button>
                </div>
            </div>
        </div>
        <div class="crop-modal-footer">
            <button type="button" class="btn-secondary" id="cropCancelBtn">Cancel</button>
            <button type="button" class="btn-primary" id="cropApplyBtn"><i class="fas fa-check"></i> Apply &amp; Upload</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// ========== TOAST NOTIFICATIONS ==========
const toastContainer = document.getElementById('toastContainer');

function showToast(type, message) {
    if (!message) return;
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = `
        <i class="fas ${icon} toast-icon"></i>
        <span class="toast-message"></span>
        <button class="toast-close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
        <div class="toast-progress"></div>
    `;
    toast.querySelector('.toast-message').textContent = message;
    toastContainer.appendChild(toast);

    const dismiss = () => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 350);
    };
    const timer = setTimeout(dismiss, 4200);
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(timer);
        dismiss();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const serverSuccess = <?php echo json_encode($success); ?>;
    const serverError = <?php echo json_encode($error); ?>;
    if (serverSuccess) showToast('success', serverSuccess);
    if (serverError) showToast('error', serverError);
});

// ========== ADDRESS CHARACTER COUNTER ==========
const addressField = document.getElementById('addressField');
const addressCount = document.getElementById('addressCount');
function updateAddressCount() {
    addressCount.textContent = addressField.value.length;
}
if (addressField) {
    updateAddressCount();
    addressField.addEventListener('input', updateAddressCount);
}

// ========== AVATAR SKELETON LOAD-IN ==========
const avatarImg = document.getElementById('avatarImg');
if (avatarImg) {
    if (!avatarImg.complete) avatarImg.classList.add('img-loading');
    avatarImg.addEventListener('load', () => avatarImg.classList.remove('img-loading'));
}

// ========== PROFILE FORM SUBMIT LOADING STATE ==========
const profileForm = document.getElementById('profileForm');
const saveProfileBtn = document.getElementById('saveProfileBtn');
profileForm.addEventListener('submit', () => {
    saveProfileBtn.disabled = true;
    saveProfileBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin-fast"></i> Saving...';
});

// ========== CLICK AVATAR TO CHANGE PHOTO ==========
document.getElementById('avatarClickTarget').addEventListener('click', () => {
    document.getElementById('profile_picture').click();
});

// ========== IMAGE CROPPING ==========
const fileInput = document.getElementById('profile_picture');
const pictureForm = document.getElementById('pictureForm');
const changePhotoBtn = document.getElementById('changePhotoBtn');
const cropBackdrop = document.getElementById('cropBackdrop');
const cropperImageEl = document.getElementById('cropperImage');
const zoomRange = document.getElementById('zoomRange');
let cropper = null;

function openCropModal(dataUrl) {
    cropBackdrop.classList.add('open');
    cropperImageEl.src = dataUrl;

    if (cropper) { cropper.destroy(); cropper = null; }

    cropper = new Cropper(cropperImageEl, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.9,
        background: false,
        responsive: true,
        zoomOnWheel: false,
    });
    zoomRange.value = 0;
}

function closeCropModal() {
    cropBackdrop.classList.remove('open');
    if (cropper) { cropper.destroy(); cropper = null; }
    fileInput.value = '';
}

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;

    if (typeof Cropper === 'undefined') {
        // Fallback: Cropper failed to load (e.g. CDN blocked) — upload as-is
        pictureForm.submit();
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => openCropModal(e.target.result);
    reader.readAsDataURL(file);
});

zoomRange.addEventListener('input', () => {
    if (cropper) cropper.zoomTo(parseFloat(zoomRange.value));
});
document.getElementById('rotateLeftBtn').addEventListener('click', () => cropper && cropper.rotate(-90));
document.getElementById('rotateRightBtn').addEventListener('click', () => cropper && cropper.rotate(90));
document.getElementById('cropCloseBtn').addEventListener('click', closeCropModal);
document.getElementById('cropCancelBtn').addEventListener('click', closeCropModal);
cropBackdrop.addEventListener('click', (e) => {
    if (e.target === cropBackdrop) closeCropModal();
});

document.getElementById('cropApplyBtn').addEventListener('click', () => {
    if (!cropper) return;
    const applyBtn = document.getElementById('cropApplyBtn');
    applyBtn.disabled = true;
    applyBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin-fast"></i> Processing...';

    const canvas = cropper.getCroppedCanvas({
        width: 500,
        height: 500,
        imageSmoothingQuality: 'high',
    });

    canvas.toBlob((blob) => {
        const croppedFile = new File([blob], 'profile.jpg', { type: 'image/jpeg' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(croppedFile);
        fileInput.files = dataTransfer.files;

        changePhotoBtn.disabled = true;
        changePhotoBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin-fast"></i> Uploading...';

        cropBackdrop.classList.remove('open');
        pictureForm.submit();
    }, 'image/jpeg', 0.92);
});
</script>
</body>
</html>
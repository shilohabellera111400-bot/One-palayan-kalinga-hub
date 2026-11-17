<?php
// profile.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and residents can view and edit their profile

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

$verification_status = $user_verify['verification_status'] ?? 'pending';
if (isStaff() || isAdmin()) {
    $verification_status = 'verified';
}

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
                        $_SESSION['profile_picture'] = $filepath;
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

        // Sync residents table if resident record exists
        try {
            $sync_res = $db->prepare("UPDATE residents SET first_name = :first_name, last_name = :last_name, contact_number = :contact_number WHERE user_id = :user_id");
            $sync_res->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':contact_number' => $contact_number,
                ':user_id' => $_SESSION['user_id']
            ]);
        } catch (Exception $e) {
            // Ignore if error
        }

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
$has_avatar = !empty($profile_picture) && (file_exists($profile_picture) || file_exists(__DIR__ . '/' . ltrim($profile_picture, '/')));
$avatar_url = $has_avatar ? (ltrim($profile_picture, '/') . '?v=' . (@filemtime($profile_picture) ?: time())) : '';

// Check if user has completed resident demographics in residents table
$has_profile_details = false;
try {
    $res_query = "SELECT * FROM residents WHERE user_id = :user_id";
    $res_stmt = $db->prepare($res_query);
    $res_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $res_stmt->execute();
    $resident_record = $res_stmt->fetch(PDO::FETCH_ASSOC);
    $has_profile_details = !empty($resident_record['birth_date']) && !empty($resident_record['civil_status']);
} catch (Exception $e) {
    $has_profile_details = false;
}

$just_updated_profile = ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile']) && empty($error));
$just_uploaded_picture = ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture']) && empty($error));
$is_100_ready = ($verification_status === 'verified' && $has_profile_details);

if ($just_updated_profile && $is_100_ready) {
    $success = "Profile saved successfully! Your citizen account is now 100% Complete & Verified! 🎉";
} elseif (isset($_GET['upload_photo']) && $_GET['upload_photo'] == '1' && empty($success)) {
    $success = "Profile details saved successfully! Please select or capture your official profile picture below.";
}

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
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <?php if (isStaff() || isAdmin()): ?>
    <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo file_exists(__DIR__ . '/css/staff-portal.css') ? filemtime(__DIR__ . '/css/staff-portal.css') : time(); ?>">
    <?php endif; ?>
    <style>
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
            .profile-layout { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .profile-card { padding: 20px 16px; }
            .profile-form { padding: 20px 16px; }
            .profile-image { width: 120px; height: 120px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-primary,
            .form-actions .btn-secondary { width: 100%; justify-content: center; }
            .toast-container { left: 16px; right: 16px; max-width: none; top: 16px; }
        }
        @media (max-width: 480px) {
            .profile-image { width: 100px; height: 100px; }
            .profile-image .default-avatar { font-size: 3rem; }
        }

                /* Hide / Show Profile Complete Card (Req: hide button instead of dismiss) */
        .btn-hide-profile-card {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(127, 3, 3, 0.08);
            border: 1.5px solid rgba(127, 3, 3, 0.22);
            color: #7F0303;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            z-index: 10;
        }
        .btn-hide-profile-card:hover {
            background: #7F0303;
            color: #ffffff;
            border-color: #7F0303;
            transform: translateY(-1px);
        }
        [data-theme="dark"] .btn-hide-profile-card {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.25);
            color: #E8EDF5;
        }
        [data-theme="dark"] .btn-hide-profile-card:hover {
            background: #7F0303;
            color: #ffffff;
        }

        /* Guided photo upload step 3 theme match - Maroon/Tan */
        .guided-banner-icon {
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.25) !important;
        }
        .guided-pointer-pill {
            background: linear-gradient(135deg, #7F0303, #A31212) !important;
            color: #ffffff !important;
        }

        /* ========== GUIDED ONBOARDING BANNER & PULSE ========== */
        .guided-profile-banner {
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.06) 0%, rgba(201, 168, 108, 0.18) 100%);
            border: 1.5px solid rgba(201, 168, 108, 0.45);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            animation: fadeInDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-sm);
        }
        .guided-banner-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }
        .guided-banner-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
            animation: iconPulse 2.2s infinite ease-in-out;
        }
        .guided-banner-badge {
            display: inline-block;
            padding: 3px 10px;
            background: var(--maroon);
            color: white;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .guided-banner-text h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 3px;
        }
        .guided-banner-text p {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.45;
            margin: 0;
        }
        .btn-focus-photo {
            padding: 11px 22px;
            background: linear-gradient(135deg, #7F0303, #9a1515);
            color: white !important;
            border: none;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.3);
            transition: all 0.3s;
            flex-shrink: 0;
            text-decoration: none;
        }
        .btn-focus-photo:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(127, 3, 3, 0.4);
        }

        /* Pulsing Guided Glow on Avatar Upload Target */
        .guided-pulse-target {
            animation: guidedGlowRing 2s infinite ease-in-out !important;
            border: 3px solid #7F0303 !important;
        }
        @keyframes guidedGlowRing {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0.65);
            }
            50% {
                box-shadow: 0 0 0 16px rgba(16, 185, 129, 0);
            }
        }

        /* Guided Glowing Button & Floating Pointer Pill */
        @keyframes guidedGlowPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0.7), 0 8px 25px rgba(127, 3, 3, 0.35);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(16, 185, 129, 0), 0 12px 30px rgba(127, 3, 3, 0.5);
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
            right: 14px;
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.4);
            animation: floatPointer 1.8s infinite ease-in-out;
            pointer-events: none;
            z-index: 15;
            white-space: nowrap;
        }
        @keyframes floatPointer {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        /* Flow Trail inside Profile Banner */
        .profile-flow-trail {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .profile-flow-step {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .profile-flow-step.done {
            background: rgba(127, 3, 3, 0.08);
            color: #7F0303;
            border: 1px solid rgba(127, 3, 3, 0.2);
        }
        .profile-flow-step.current {
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            box-shadow: 0 3px 8px rgba(127, 3, 3, 0.4);
            animation: currentStepPulse 2s infinite ease-in-out;
        }
        @keyframes currentStepPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .profile-flow-step.upcoming {
            background: var(--badge-bg);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        .profile-flow-step.ready-100 {
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            box-shadow: 0 3px 12px rgba(16, 185, 129, 0.55);
            animation: currentStepPulse 2s infinite ease-in-out;
        }

        .canvas-confetti-profile {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 99999;
        }
        .profile-flow-arrow {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        @media (max-width: 768px) {
            .guided-profile-banner {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn-focus-photo {
                width: 100%;
                justify-content: center;
            }
        }

        /* ========== MINIMALIST PROFILE OVERVIEW & EDIT (Req 6) ========== */
        .profile-overview-card {
            background: var(--card-bg, #FFFFFF);
            border-radius: var(--radius-card, 20px);
            border: 1px solid var(--border-color, #E8ECF2);
            box-shadow: var(--shadow-md, 0 8px 30px rgba(0, 0, 0, 0.08));
            padding: 28px 32px;
            transition: all 0.3s ease;
        }
        .overview-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color, #E8ECF2);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .overview-header-titles h2 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary, #191D1E);
            margin: 0 0 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .overview-header-titles p {
            font-size: 0.86rem;
            color: var(--text-muted, #64748B);
            margin: 0;
        }
        .btn-edit-profile-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: linear-gradient(135deg, #7F0303 0%, #A31212 100%);
            color: #FFFFFF;
            font-size: 0.88rem;
            font-weight: 700;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.25);
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .btn-edit-profile-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.35);
            background: linear-gradient(135deg, #9a1515 0%, #7F0303 100%);
        }
        .overview-section-box {
            background: var(--bg-primary, #F8F9FC);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color, #E8ECF2);
        }
        [data-theme="dark"] .overview-section-box {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .overview-section-title {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text-primary, #191D1E);
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .overview-section-title i {
            color: #7F0303;
        }
        [data-theme="dark"] .overview-section-title i {
            color: #C9A86C;
        }
        .overview-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 24px;
        }
        @media (max-width: 768px) {
            .overview-info-grid {
                grid-template-columns: 1fr;
            }
        }
        .overview-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .overview-info-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #64748B);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .overview-info-val {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary, #191D1E);
            word-break: break-word;
        }
        .overview-badge-val {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            width: fit-content;
        }
        .overview-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px dashed var(--border-color, #E8ECF2);
        }
        .btn-security-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            background: transparent;
            border: 1px solid var(--border-color, #E8ECF2);
            color: var(--text-primary, #191D1E);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-security-link:hover {
            background: rgba(127, 3, 3, 0.08);
            border-color: #7F0303;
            color: #7F0303;
        }
        .btn-cancel-edit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 50px;
            background: transparent;
            border: 1px solid var(--border-color, #E8ECF2);
            color: var(--text-muted, #64748B);
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-cancel-edit:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-primary, #191D1E);
        }
        .btn-dismiss-card {
            position: absolute;
            top: 16px;
            right: 18px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color, #E8ECF2);
            color: var(--text-muted, #64748B);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            z-index: 10;
        }
        .btn-dismiss-card:hover {
            background: rgba(127, 3, 3, 0.1);
            color: #7F0303;
            transform: scale(1.08);
        }
        [data-theme="dark"] .btn-dismiss-card {
            background: rgba(255, 255, 255, 0.08);
            color: #94A3B8;
        }
        [data-theme="dark"] .btn-dismiss-card:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #FFF;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isStaff() || isAdmin()): ?>
            <?php include __DIR__ . '/staff/includes/staff-sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php if (isStaff() || isAdmin()): ?>
                <?php include __DIR__ . '/staff/includes/staff-header.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/portal-header.php'; ?>
            <?php endif; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "My Profile & Account";
            $banner_subtitle = "Official citizen records and verified resident contact details";
            $banner_icon = "fa-user-circle";
            $banner_actions = [
                ["label" => "Edit Profile", "url" => "javascript:void(0);", "onclick" => "toggleEditProfile(true)", "icon" => "fa-pen", "type" => "primary"]
            ];
            if (!isStaff() && !isAdmin()) {
                $banner_actions[] = ["label" => "Verify Residency", "url" => "verify-resident.php", "icon" => "fa-id-card", "type" => "secondary"];
            }
            include __DIR__ . "/includes/portal-banner.php";
            ?>

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

        <!-- Full-screen Confetti Canvas for 100% Ready Celebration -->
        <?php if ($is_100_ready && ($just_updated_profile || isset($_GET['complete']))): ?>
        <canvas class="canvas-confetti-profile" id="profileConfettiCanvas"></canvas>
        <?php endif; ?>

        <?php if (!isStaff() && !isAdmin() && !$is_100_ready): ?>
            <?php if ($verification_status !== 'verified'): ?>
                <!-- STEP 1 NEEDED: VERIFY RESIDENCY FIRST -->
                <div class="guided-profile-banner" id="guidedProfileBanner">
                    <div class="guided-banner-left">
                        <div class="guided-banner-icon" style="background: linear-gradient(135deg, #7F0303, #A31212);">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="guided-banner-text">
                            <div class="guided-banner-badge" style="background: #7F0303;">Step 1 Needed • Account Verification</div>
                            <h3>Step 1 Required: Verify Your Palayan Residency 🏛️</h3>
                            <p>To access all portal services and update your official records, please submit your government ID for verification.</p>
                            
                            <div class="profile-flow-trail">
                                <span class="profile-flow-step current"><i class="fas fa-hand-point-right"></i> 1. Verify Account</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step upcoming"><i class="fas fa-user-edit"></i> 2. Complete Profile</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step upcoming"><i class="fas fa-camera"></i> 3. Upload Photo</span>
                            </div>
                        </div>
                    </div>
                    <a href="verify-resident.php" class="btn-focus-photo guided-btn-glow" style="background: linear-gradient(135deg, #7F0303, #A31212);">
                        <span class="guided-pointer-pill">Step 1</span>
                        <i class="fas fa-shield-alt"></i> Verify Residency <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php elseif (!$has_profile_details): ?>
                <!-- STEP 2 NEEDED: COMPLETE RESIDENT DEMOGRAPHICS FIRST -->
                <div class="guided-profile-banner" id="guidedProfileBanner">
                    <div class="guided-banner-left">
                        <div class="guided-banner-icon" style="background: linear-gradient(135deg, #7F0303, #A31212);">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="guided-banner-text">
                            <div class="guided-banner-badge">Step 2 Needed • Complete Profile Details</div>
                            <h3>Step 2 Required: Complete Your Resident Information 📝</h3>
                            <p>Before uploading your photo, please fill out your demographic, civil status, and household details so the CSWDO can record your profile.</p>
                            
                            <div class="profile-flow-trail">
                                <span class="profile-flow-step done"><i class="fas fa-check"></i> 1. Verify Account</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step current"><i class="fas fa-user-edit"></i> 2. Complete Profile</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step upcoming"><i class="fas fa-camera"></i> 3. Upload Photo</span>
                            </div>
                        </div>
                    </div>
                    <a href="update-profile-info.php?guided=1" class="btn-focus-photo guided-btn-glow">
                        <span class="guided-pointer-pill">Step 2</span>
                        <i class="fas fa-edit"></i> Complete Profile Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php elseif (!$has_avatar): ?>
                <!-- STEP 3: UPLOAD PHOTO -->
                <div class="guided-profile-banner" id="guidedProfileBanner">
                    <div class="guided-banner-left">
                        <div class="guided-banner-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="guided-banner-text">
                            <div class="guided-banner-badge">Step 3 of 3 • Citizen Onboarding</div>
                            <h3>Step 3: Upload Your Resident Profile Photo 📸</h3>
                            <p>Having your portrait photo ready allows the CSWDO to print your official claim stubs and ID booklet with zero delays.</p>
                            
                            <div class="profile-flow-trail">
                                <span class="profile-flow-step done"><i class="fas fa-check"></i> 1. Verify Account</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step done"><i class="fas fa-check"></i> 2. Complete Profile</span>
                                <i class="fas fa-arrow-right profile-flow-arrow"></i>
                                <span class="profile-flow-step current"><i class="fas fa-camera"></i> 3. Upload Photo</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-focus-photo guided-btn-glow" onclick="document.getElementById('profile_picture').click()">
                        <span class="guided-pointer-pill">Click Here</span>
                        <i class="fas fa-upload"></i> Upload Photo Now
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        
        <!-- Show Profile Card Bar when hidden -->
        <div id="showProfileCardBar" style="display: none; margin-bottom: 16px; text-align: right;">
            <button type="button" class="btn-hide-profile-card" style="position: static; display: inline-flex;" onclick="toggleHideProfileCompleteCard()">
                <i class="fas fa-eye"></i> <span>Show Profile Status Card</span>
            </button>
        </div>

        <!-- Smart Dynamic Profile Completion Indicator (Req 3) -->
        <div class="smart-profile-progress-card <?php echo $is_100_ready ? 'is-complete' : ''; ?>" id="smartProgressCard">
            <button type="button" class="btn-hide-profile-card" id="hideProfileCardBtn" onclick="toggleHideProfileCompleteCard()" aria-label="Hide Profile Card" title="Hide Card">
                <i class="fas fa-eye-slash"></i> <span>Hide</span>
            </button>
            <div class="progress-card-header">
                <div class="progress-header-left">
                    <div class="progress-ring-container">
                        <svg class="progress-ring" width="64" height="64" viewBox="0 0 68 68">
                            <circle class="progress-ring-bg" cx="34" cy="34" r="28" stroke-width="6" fill="transparent" />
                            <circle class="progress-ring-circle" id="progressRingCircle" cx="34" cy="34" r="28" stroke-width="6" fill="transparent" 
                                stroke-dasharray="175.93" 
                                stroke-dashoffset="<?php echo 175.93 - (175.93 * ($is_100_ready ? 1 : 0.6)); ?>" />
                        </svg>
                        <div class="progress-ring-text" id="progressPercentText"><?php echo $is_100_ready ? '100%' : '60%'; ?></div>
                    </div>
                    <div class="progress-info-text">
                        <div class="progress-status-badge" id="progressStatusBadge">
                            <i class="fas <?php echo $is_100_ready ? 'fa-check-circle' : 'fa-tasks'; ?>"></i> 
                            <?php echo $is_100_ready ? 'Profile Complete' : 'Profile In Progress'; ?>
                        </div>
                        <h3 id="progressCardTitle"><?php echo $is_100_ready ? 'Profile Complete! 🎉' : 'Complete Your Profile'; ?></h3>
                        <p id="progressCardSubtitle">
                            <?php echo $is_100_ready ? 'Outstanding! All required citizen details are recorded. Your account is 100% active and verified.' : 'Complete all required fields below to enable one-click assistance applications.'; ?>
                        </p>
                    </div>
                </div>
                <div class="progress-header-right">
                    <button type="button" class="btn-start-tour" id="startTourBtn" title="Launch step-by-step onboarding walkthrough">
                        <i class="fas fa-magic"></i> <span>Interactive Guide</span>
                    </button>
                    <?php if (!$is_100_ready): ?>
                    <button type="button" class="btn-next-incomplete" id="nextIncompleteBtn">
                        <i class="fas fa-arrow-down"></i> <span>Next Missing Field</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Linear Progress Track -->
            <div class="progress-track-wrapper">
                <div class="progress-track">
                    <div class="progress-fill-bar" id="progressFillBar" style="width: <?php echo $is_100_ready ? '100%' : '60%'; ?>;"></div>
                </div>
                <div class="progress-milestone-ticks">
                    <button type="button" class="milestone-pill done" title="Residency Verification">
                        <i class="fas fa-check-circle"></i> Verified
                    </button>
                    <button type="button" class="milestone-pill <?php echo !empty($avatar_url) ? 'done' : 'missing'; ?>" id="pillPhoto" data-target="#onboardingAvatarGroup" title="Click to focus Photo">
                        <i class="fas <?php echo !empty($avatar_url) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> Photo
                    </button>
                    <button type="button" class="milestone-pill <?php echo (!empty($user['first_name']) && !empty($user['last_name'])) ? 'done' : 'missing'; ?>" id="pillName" data-target="#onboardingOverviewSection1, #onboardingNameGroup" title="Click to focus Name">
                        <i class="fas <?php echo (!empty($user['first_name']) && !empty($user['last_name'])) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> Full Name
                    </button>
                    <button type="button" class="milestone-pill <?php echo !empty($user['contact_number']) ? 'done' : 'missing'; ?>" id="pillContact" data-target="#onboardingOverviewSection2, #onboardingContactGroup" title="Click to focus Contact">
                        <i class="fas <?php echo !empty($user['contact_number']) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> Contact
                    </button>
                    <button type="button" class="milestone-pill <?php echo !empty($user['address']) ? 'done' : 'missing'; ?>" id="pillAddress" data-target="#onboardingOverviewSection3, #onboardingAddressGroup" title="Click to focus Address">
                        <i class="fas <?php echo !empty($user['address']) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> Address
                    </button>
                </div>
            </div>

            <!-- 100% Celebration Banner -->
            <div class="profile-100-celebration" id="profile100Banner" style="display: <?php echo $is_100_ready ? 'flex' : 'none'; ?>;">
                <div class="celebration-badge-title">
                    <i class="fas fa-medal"></i> Profile 100% Complete! 🎉
                </div>
                <p>All citizen records, contact information, and portrait photo are validated. You have unlocked one-click submissions for all Palayan City CSWDO services.</p>
            </div>
        </div>

        <div class="profile-layout">

            <!-- Profile Picture Section -->
            <div class="profile-card animate-in" style="animation-delay: 0.05s;" id="onboardingAvatarGroup">
                <div class="profile-image" id="avatarClickTarget">
                    <?php if(!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture" id="avatarImg" class="img-skeleton-load" onload="this.classList.add('loaded')">
                    <?php else: ?>
                        <div class="default-avatar"><i class="fas fa-user-circle"></i></div>
                    <?php endif; ?>
                    <div class="profile-image-overlay"><i class="fas fa-camera"></i></div>
                </div>
                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                    <?php echo csrf_field(); ?>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display: none;">
                    <button type="button" class="upload-btn <?php echo empty($avatar_url) ? 'guided-btn-glow' : ''; ?>" id="changePhotoBtn" onclick="document.getElementById('profile_picture').click()">
                        <?php if (empty($avatar_url)): ?>
                            <span class="guided-pointer-pill" style="top: -12px; left: 50%; transform: translateX(-50%);"><i class="fas fa-hand-point-down"></i> Step 3: Upload Photo</span>
                        <?php endif; ?>
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

            <!-- Profile Overview (Read-Only by Default) (Req 6) -->
            <div class="profile-overview-card animate-in" style="animation-delay: 0.15s;" id="profileOverviewView">
                <div class="overview-card-header">
                    <div class="overview-header-titles">
                        <h2><i class="fas fa-id-card" style="color: #7F0303;"></i> Personal Information Overview</h2>
                        <p>Official citizen records and verified resident contact details</p>
                    </div>
                    <button type="button" class="btn-edit-profile-trigger" id="editProfileToggleBtn" onclick="toggleEditProfile(true)" aria-label="Edit Profile Information">
                        <i class="fas fa-pen"></i> <span>Edit Profile</span>
                    </button>
                </div>

                <!-- Section 1: Basic Account Details -->
                <div class="overview-section-box" id="onboardingOverviewSection1">
                    <div class="overview-section-title">
                        <i class="fas fa-user-circle"></i> Basic Account Details
                    </div>
                    <div class="overview-info-grid">
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-user"></i> Full Name</span>
                            <span class="overview-info-val"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-envelope"></i> Email Address</span>
                            <span class="overview-info-val"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-user-tag"></i> Username</span>
                            <span class="overview-info-val"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-shield-alt"></i> Account Status</span>
                            <span class="overview-badge-val <?php echo $verification_status === 'verified' ? 'badge-verified' : 'badge-pending'; ?>">
                                <i class="fas <?php echo $verification_status === 'verified' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                <?php echo $verification_status === 'verified' ? 'Verified Resident' : 'Pending Verification'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Contact & Location Information -->
                <div class="overview-section-box" id="onboardingOverviewSection2">
                    <div class="overview-section-title">
                        <i class="fas fa-map-marker-alt"></i> Contact &amp; Residential Location
                    </div>
                    <div class="overview-info-grid">
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-phone"></i> Contact Number</span>
                            <span class="overview-info-val"><?php echo !empty($user['contact_number']) ? htmlspecialchars($user['contact_number']) : '<span style="color: var(--text-muted); font-style: italic;">Not provided</span>'; ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-home"></i> Street / House Details</span>
                            <span class="overview-info-val"><?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : (!empty($resident_record['street']) ? htmlspecialchars($resident_record['street']) : '<span style="color: var(--text-muted); font-style: italic;">Not provided</span>'); ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-city"></i> Barangay</span>
                            <span class="overview-info-val"><?php echo !empty($resident_record['barangay']) ? htmlspecialchars($resident_record['barangay']) : 'Palayan City'; ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-map"></i> City &amp; Province</span>
                            <span class="overview-info-val"><?php echo !empty($resident_record['city']) ? htmlspecialchars($resident_record['city'] . ', ' . ($resident_record['province'] ?? 'Nueva Ecija')) : 'Palayan City, Nueva Ecija'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Demographic & Civil Information -->
                <div class="overview-section-box" id="onboardingOverviewSection3">
                    <div class="overview-section-title">
                        <i class="fas fa-id-badge"></i> Demographics &amp; Resident Classification
                    </div>
                    <div class="overview-info-grid">
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-calendar"></i> Date of Birth</span>
                            <span class="overview-info-val"><?php echo (!empty($resident_record['birth_date']) && $resident_record['birth_date'] !== '0000-00-00') ? date('F j, Y', strtotime($resident_record['birth_date'])) : '<span style="color: var(--text-muted); font-style: italic;">Not recorded</span>'; ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-heart"></i> Civil Status</span>
                            <span class="overview-info-val"><?php echo !empty($resident_record['civil_status']) ? htmlspecialchars(ucfirst($resident_record['civil_status'])) : '<span style="color: var(--text-muted); font-style: italic;">Not recorded</span>'; ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                            <span class="overview-info-val"><?php echo !empty($resident_record['gender']) ? htmlspecialchars(ucfirst($resident_record['gender'])) : '<span style="color: var(--text-muted); font-style: italic;">Not recorded</span>'; ?></span>
                        </div>
                        <div class="overview-info-item">
                            <span class="overview-info-label"><i class="fas fa-users"></i> Classification</span>
                            <span class="overview-info-val">
                                <?php 
                                    $tags = [];
                                    if (!empty($resident_record['senior_citizen'])) $tags[] = 'Senior Citizen';
                                    if (!empty($resident_record['pwd'])) $tags[] = 'PWD';
                                    if (empty($tags)) $tags[] = 'Regular Citizen';
                                    echo htmlspecialchars(implode(', ', $tags));
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Overview Actions Row -->
                <div class="overview-actions-row">
                    <a href="change-password.php" class="btn-security-link">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <?php if (!$has_profile_details): ?>
                    <a href="update-profile-info.php" class="btn-security-link" style="color: #7F0303; border-color: rgba(127, 3, 3, 0.3);">
                        <i class="fas fa-user-edit"></i> Complete Full Resident Demographics
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Edit Form (Hidden by default, shown when user clicks Edit) (Req 6) -->
            <div class="profile-overview-card animate-in" style="animation-delay: 0.15s; display: none;" id="profileEditView">
                <div class="overview-card-header">
                    <div class="overview-header-titles">
                        <h2><i class="fas fa-edit" style="color: #7F0303;"></i> Edit Personal Information</h2>
                        <p>Modify your contact number, full name, or residential address</p>
                    </div>
                    <button type="button" class="btn-cancel-edit" onclick="toggleEditProfile(false)">
                        <i class="fas fa-times"></i> Cancel Editing
                    </button>
                </div>
                <form method="POST" action="" id="profileForm">
                    <?php echo csrf_field(); ?>
                    <div class="form-row" id="onboardingNameGroup">
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
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color: var(--text-muted); font-size: 0.76rem; margin-top: 4px; display: block;">Email address is verified and permanently linked to your citizen account.</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>

                    <div class="form-group" id="onboardingContactGroup">
                        <label><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" placeholder="e.g., 09123456789">
                    </div>

                    <div class="form-group" id="onboardingAddressGroup">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea name="address" rows="3" maxlength="255" id="addressField" placeholder="Enter your complete address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        <div class="char-counter"><span id="addressCount">0</span>/255</div>
                    </div>

                    <div class="form-actions" style="display: flex; align-items: center; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                        <button type="submit" name="update_profile" class="btn-primary" id="saveProfileBtn" style="padding: 10px 26px; border-radius: 50px;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn-cancel-edit" onclick="toggleEditProfile(false)">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <a href="change-password.php" class="btn-secondary" style="margin-left: auto; border-radius: 50px;">
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
            <button type="button" class="btn-primary guided-btn-glow" id="cropApplyBtn">
                <span class="guided-pointer-pill" style="top: -12px; right: 10px;"><i class="fas fa-check"></i> Click Apply</span>
                <i class="fas fa-check"></i> Apply &amp; Upload
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
// ========== PROFILE OVERVIEW / EDIT TOGGLE (Req 6) ==========
function toggleEditProfile(showEdit) {
    const overviewView = document.getElementById('profileOverviewView');
    const editView = document.getElementById('profileEditView');
    if (overviewView && editView) {
        if (showEdit) {
            overviewView.style.display = 'none';
            editView.style.display = 'block';
            window.scrollTo({ top: editView.offsetTop - 80, behavior: 'smooth' });
        } else {
            editView.style.display = 'none';
            overviewView.style.display = 'block';
            window.scrollTo({ top: overviewView.offsetTop - 80, behavior: 'smooth' });
        }
    }
}

// ========== PROFILE COMPLETE CARD HIDE / SHOW ==========
function toggleHideProfileCompleteCard() {
    const card = document.getElementById('smartProgressCard');
    const showBar = document.getElementById('showProfileCardBar');
    if (!card) return;
    
    const isHidden = (card.style.display === 'none');
    if (!isHidden) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(-10px)';
        card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
        setTimeout(() => {
            card.style.display = 'none';
            if (showBar) showBar.style.display = 'block';
        }, 250);
        localStorage.setItem('profile_card_hidden_pref', '1');
    } else {
        card.style.display = 'block';
        if (showBar) showBar.style.display = 'none';
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 10);
        localStorage.removeItem('profile_card_hidden_pref');
    }
}

// Backwards compatibility for any dismiss calls
function dismissProfileCompleteCard() {
    toggleHideProfileCompleteCard();
}

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('profile_card_hidden_pref') === '1' || localStorage.getItem('profile_complete_card_dismissed') === '1') {
        const card = document.getElementById('smartProgressCard');
        const showBar = document.getElementById('showProfileCardBar');
        if (card) card.style.display = 'none';
        if (showBar) showBar.style.display = 'block';
    }
});
</script>
<script>
// ========== MOBILE SIDEBAR TOGGLE ==========
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebarClose = document.getElementById('sidebarClose');

function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle ? themeToggle.querySelector('i') : null;
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
}

// ========== TOAST NOTIFICATIONS ==========
const toastContainer = document.getElementById('toastContainer');

function showToast(type, message) {
    if (!message) return;
    if (window.showProcessToast) {
        window.showProcessToast(message, type === 'error', type === 'error' ? 'Notice / Error' : 'Process Completed');
        return;
    }
    if (toastContainer) {
        toastContainer.querySelectorAll('.toast').forEach(t => t.remove());
    }
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

// ========== GUIDED ONBOARDING HIGHLIGHT & CONFETTI ==========
document.addEventListener('DOMContentLoaded', function () {
    const is100Ready = <?php echo $is_100_ready ? 'true' : 'false'; ?>;
    const hasAvatar = <?php echo $has_avatar ? 'true' : 'false'; ?>;
    const justUpdated = <?php echo $just_updated_profile ? 'true' : 'false'; ?>;

    // Only pulse photo upload target if photo is NOT yet uploaded
    if (!hasAvatar) {
        const avatarTarget = document.getElementById('avatarClickTarget');
        const changeBtn = document.getElementById('changePhotoBtn');
        if (avatarTarget) avatarTarget.classList.add('guided-pulse-target');
        if (changeBtn) changeBtn.classList.add('guided-pulse-target');

        const removePulse = () => {
            if (avatarTarget) avatarTarget.classList.remove('guided-pulse-target');
            if (changeBtn) changeBtn.classList.remove('guided-pulse-target');
        };

        if (avatarTarget) avatarTarget.addEventListener('click', removePulse, { once: true });
        if (changeBtn) changeBtn.addEventListener('click', removePulse, { once: true });
        setTimeout(removePulse, 10000);
    }

    // Auto-focus and highlight profile picture upload if navigated from update-profile-info.php
    <?php if (isset($_GET['upload_photo']) && $_GET['upload_photo'] == '1'): ?>
    const photoTarget = document.getElementById('avatarClickTarget') || document.getElementById('changePhotoBtn');
    if (photoTarget) {
        photoTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        photoTarget.classList.add('guided-pulse-target');
        setTimeout(() => {
            const fileInput = document.getElementById('profile_picture');
            if (fileInput) fileInput.click();
        }, 700);
    }
    <?php endif; ?>

    // Launch Confetti when 100% Ready
    <?php if ($is_100_ready && ($just_updated_profile || isset($_GET['complete']))): ?>
    launchProfileConfetti();
    <?php endif; ?>
});

function launchProfileConfetti() {
    const canvas = document.getElementById('profileConfettiCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;

    window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    });

    const particles = [];
    const colors = ['#10B981', '#7F0303', '#D8BA98', '#F59E0B', '#3B82F6', '#EC4899', '#6366F1'];

    for (let i = 0; i < 95; i++) {
        particles.push({
            x: width / 2 + (Math.random() - 0.5) * 120,
            y: height / 3 + (Math.random() - 0.5) * 80,
            w: Math.random() * 10 + 5,
            h: Math.random() * 6 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            vx: (Math.random() - 0.5) * 16,
            vy: (Math.random() * -14) - 4,
            gravity: 0.35 + Math.random() * 0.18,
            rotation: Math.random() * 360,
            rotationSpeed: (Math.random() - 0.5) * 14,
            opacity: 1
        });
    }

    let start = Date.now();
    function draw() {
        ctx.clearRect(0, 0, width, height);
        let active = false;

        for (let p of particles) {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += p.gravity;
            p.vx *= 0.985;
            p.rotation += p.rotationSpeed;

            if (Date.now() - start > 1800) {
                p.opacity -= 0.018;
            }

            if (p.opacity > 0 && p.y < height + 60) {
                active = true;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate((p.rotation * Math.PI) / 180);
                ctx.fillStyle = p.color;
                ctx.globalAlpha = Math.max(0, p.opacity);
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            }
        }

        if (active) {
            requestAnimationFrame(draw);
        }
    }
    draw();
}
</script>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

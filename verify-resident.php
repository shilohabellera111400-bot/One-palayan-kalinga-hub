<?php
// verify-resident.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';
if (!isLoggedIn()) {
    redirect('login.php');
}
// Staff and residents can verify residency
$database = new Database();
$db = $database->getConnection();
// Check if user already has a verification request
$check_query = "SELECT * FROM verification_requests WHERE user_id = :user_id ORDER BY verification_id DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$existing_request = $check_stmt->fetch(PDO::FETCH_ASSOC);
// Get user's current verification status and details
$user_query = "SELECT verification_status, profile_picture, first_name, last_name, email, contact_number, address 
               FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_status = $user_stmt->fetch(PDO::FETCH_ASSOC);
$verification_status = $user_status['verification_status'] ?? 'pending';

// Fetch resident registered address details for autofill in Step 4
$default_barangay = '';
$default_street = '';
try {
    $res_query = "SELECT barangay, street, address FROM residents WHERE user_id = :user_id LIMIT 1";
    $res_stmt = $db->prepare($res_query);
    $res_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $res_data = $res_stmt->fetch(PDO::FETCH_ASSOC);
    if ($res_data) {
        $default_barangay = trim($res_data['barangay'] ?? '');
        $default_street = trim($res_data['street'] ?? '');
        if (empty($default_street) && !empty($res_data['address'])) {
            $default_street = trim($res_data['address']);
        }
    }
} catch (Exception $e) {}

// Fallback from users table address if residents table was empty
if (empty($default_barangay) && !empty($user_status['address'])) {
    $addr_str = $user_status['address'];
    $palayan_brgys = ['Atate', 'Aulo', 'Bagong Buhay', 'Baryo Militar', 'Caballero', 'Caimito', 'Doña Josefa', 'Ganaderia', 'Imelda Valley I', 'Imelda Valley II', 'Malate', 'Maligaya', 'Manacnac', 'Mapaet', 'Marcos Village', 'Popolon(Pagas)', 'Popolon (Pagas)', 'Santolan', 'Sapang Buho', 'Singalat'];
    foreach ($palayan_brgys as $b) {
        if (stripos($addr_str, $b) !== false) {
            $default_barangay = ($b === 'Popolon (Pagas)') ? 'Popolon(Pagas)' : $b;
            break;
        }
    }
    if (preg_match('/^(.*?),\s*(?:Brgy\.?|Barangay)\s*/i', $addr_str, $m_str)) {
        $default_street = trim($m_str[1]);
    } elseif (empty($default_street)) {
        $default_street = trim(str_ireplace(['Brgy. ' . $default_barangay, 'Barangay ' . $default_barangay, $default_barangay], '', $addr_str));
    }
}

// Fallback from prior verification request if any
if (empty($default_barangay) && !empty($existing_request['barangay_name'])) {
    $default_barangay = trim($existing_request['barangay_name']);
}
$error = '';
$success = '';
// Helper function to handle upload or base64 decoding from camera capture
function saveVerificationFile($fieldName, $capturedFieldName, $uploader) {
    // 1. Check if camera snapshot base64 was captured
    if (!empty($_POST[$capturedFieldName])) {
        $base64_string = $_POST[$capturedFieldName];
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = substr($base64_string, strpos($base64_string, ',') + 1);
            $type = strtolower($type[1]);
            if (in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                $data = base64_decode($data);
                if ($data !== false) {
                    $dir = __DIR__ . '/uploads/verification/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = uniqid('cap_', true) . '.' . ($type === 'jpeg' ? 'jpeg' : $type);
                    $file_path = 'uploads/verification/' . $filename;
                    if (file_put_contents(__DIR__ . '/' . $file_path, $data)) {
                        return $file_path;
                    }
                }
            }
        }
    }
    // 2. Check if file was uploaded standardly
    if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $file = [
            'name' => $_FILES[$fieldName]['name'],
            'type' => $_FILES[$fieldName]['type'],
            'tmp_name' => $_FILES[$fieldName]['tmp_name'],
            'error' => $_FILES[$fieldName]['error'],
            'size' => $_FILES[$fieldName]['size']
        ];
        return $uploader->upload($file, 'verification');
    }
    return null;
}
// Handle verification submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_verification'])) {
    csrf_verify();
    $id_type = sanitize($_POST['id_type']);
    $id_number = sanitize($_POST['id_number']);
    $barangay = sanitize($_POST['barangay']);
    $street = sanitize($_POST['street'] ?? '');
    $city = sanitize($_POST['city'] ?? 'Palayan City');
    $province = sanitize($_POST['province'] ?? 'Nueva Ecija');
    $postal_code = sanitize($_POST['postal_code'] ?? '3132');
    // Validate required fields
    if (empty($id_type) || empty($id_number) || empty($barangay)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if user already has a pending request
        if ($existing_request && $existing_request['status'] == 'pending') {
            $error = "You already have a pending verification request. Please wait for staff to review your documents.";
        } else {
            $uploader = new FileUploader();
            // Save Valid ID file or snapshot (Front Side)
            $id_file_path = saveVerificationFile('valid_id', 'captured_id_image', $uploader);
            if (empty($id_file_path)) {
                $error = "Please upload or capture the FRONT side of your valid ID.";
            }
            // Save Valid ID file or snapshot (Back Side)
            $id_back_file_path = saveVerificationFile('valid_id_back', 'captured_id_back_image', $uploader);
            if (empty($error) && empty($id_back_file_path)) {
                $error = "Please upload or capture the BACK side of your valid ID.";
            }
            // Save Selfie with ID file or snapshot
            if (empty($error)) {
                $selfie_file_path = saveVerificationFile('selfie_file', 'captured_selfie_image', $uploader);
                if (empty($selfie_file_path)) {
                    $error = "Please upload or capture your selfie with the ID.";
                }
            }
            $address_file_path = null;
            if (empty($error)) {
                // Build complete address
                $complete_address = trim($street . ', ' . $barangay . ', ' . $city . ', ' . $province . ' ' . $postal_code);
                // Insert verification request
                $query = "INSERT INTO verification_requests 
                          (user_id, id_type, id_number, id_file_path, id_back_file_path, proof_of_residency_path, barangay_certificate_path, barangay_name, complete_address, remarks) 
                          VALUES (:user_id, :id_type, :id_number, :id_path, :id_back_path, :residency_path, :cert_path, :barangay, :address, :remarks)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':id_type', $id_type);
                $stmt->bindParam(':id_number', $id_number);
                $stmt->bindParam(':id_path', $id_file_path);
                $stmt->bindParam(':id_back_path', $id_back_file_path);
                $stmt->bindParam(':residency_path', $selfie_file_path);
                $stmt->bindParam(':cert_path', $address_file_path);
                $stmt->bindParam(':barangay', $barangay);
                $stmt->bindParam(':address', $complete_address);
                $remarks = "Resident verification request submitted.";
                $stmt->bindParam(':remarks', $remarks);
                if ($stmt->execute()) {
                    // Update user's verification status
                    $update_user = "UPDATE users SET verification_status = 'pending' WHERE user_id = :user_id";
                    $update_stmt = $db->prepare($update_user);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $update_stmt->execute();
                    // Refresh existing_request variable so the view updates instantly
                    $check_stmt->execute();
                    $existing_request = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    $verification_status = 'pending';
                    $_SESSION['verification_status'] = 'pending';
                    // Send acknowledgment SMS via TextBee
                    try {
                        $user_phone_q = "SELECT contact_number, first_name, last_name FROM users WHERE user_id = :uid";
                        $up_stmt = $db->prepare($user_phone_q);
                        $up_stmt->execute([':uid' => $_SESSION['user_id']]);
                        $u_info = $up_stmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($u_info['contact_number'])) {
                            require_once __DIR__ . '/includes/SMSGateway.php';
                            $smsGateway = new SMSGateway();
                            $fullName = trim(($u_info['first_name'] ?? '') . ' ' . ($u_info['last_name'] ?? ''));
                            $smsGateway->sendVerificationRequestReceivedSMS($u_info['contact_number'], $fullName, (int)$_SESSION['user_id']);
                        }
                    } catch (\Throwable $smsErr) {
                        error_log("Verification submission SMS acknowledgement error: " . $smsErr->getMessage());
                    }
                    $success = "Your verification request has been submitted successfully! CSWDO staff will review your documents.";
                } else {
                    $error = "Failed to submit verification request. Please try again.";
                }
            }
        }
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
$profile_picture = $user_status['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
    ? $profile_picture 
    : '';
// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residency Verification - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google MediaPipe 3D Face Landmarker & Vision Utils for Live Face Alignment (Pinned Stable Versions) -->
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils@0.4.1633559042/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh@0.4.1633559619/face_mesh.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <?php if (isStaff() || isAdmin()): ?>
    <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo file_exists(__DIR__ . '/css/staff-portal.css') ? filemtime(__DIR__ . '/css/staff-portal.css') : time(); ?>">
    <?php endif; ?>
    <style>
        /* ========================================================
           MODERN LANDSCAPE RESIDENCY VERIFICATION LAYOUT
           ======================================================== */
        :root {
            --verif-gold: #C9A86C;
            --verif-gold-light: #F7F3EA;
            --verif-emerald: #059669;
            --verif-emerald-light: rgba(5, 150, 105, 0.12);
            --verif-amber: #D97706;
            --verif-amber-light: rgba(217, 119, 6, 0.12);
            --verif-crimson: #DC2626;
            --verif-crimson-light: rgba(220, 38, 38, 0.12);
            --verif-navy: #0F414A;
            --verif-maroon: #7F0303;
        }
        .portal-landscape-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding-bottom: 40px;
        }
        /* Landscape Grid: 360px Sidebar Guide + Expanded Canvas */
        .verif-landscape-grid {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 28px;
            align-items: start;
        }
        /* ========== LEFT COLUMN: GOV GUIDE & PROGRESS ========== */
        .verif-sidebar-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            position: sticky;
            top: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            transition: all 0.3s ease;
        }
        .gov-seal-badge {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .gov-seal-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--verif-maroon), var(--verif-navy));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
            flex-shrink: 0;
        }
        .gov-seal-text h3 {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 2px;
            line-height: 1.25;
        }
        .gov-seal-text p {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin: 0;
            font-weight: 500;
        }
        /* Side Stepper Navigator */
        .side-stepper {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .side-step-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-element);
            border: 1px solid transparent;
            background: var(--badge-bg);
            transition: all 0.3s ease;
        }
        .side-step-item.active {
            background: rgba(201, 168, 108, 0.15);
            border-color: var(--verif-gold);
            transform: translateX(4px);
        }
        .side-step-item.completed {
            background: var(--verif-emerald-light);
            border-color: rgba(5, 150, 105, 0.3);
        }
        .side-step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--border-color);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .side-step-item.active .side-step-num {
            background: var(--verif-gold);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.3);
        }
        .side-step-item.completed .side-step-num {
            background: var(--verif-emerald);
            color: #fff;
        }
        .side-step-info {
            flex: 1;
        }
        .side-step-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .side-step-desc {
            font-size: 0.68rem;
            color: var(--text-muted);
            margin: 0;
        }
        /* Tips Card */
        .verif-tips-box {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 16px;
            border: 1px solid var(--border-color);
        }
        .verif-tips-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .verif-tips-title i {
            color: var(--verif-gold);
        }
        .verif-tips-list {
            margin: 0;
            padding: 0 0 0 16px;
            font-size: 0.72rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        .privacy-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.68rem;
            color: var(--text-muted);
            padding: 8px 12px;
            background: rgba(15, 65, 74, 0.05);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .privacy-pill i {
            color: var(--verif-navy);
            font-size: 0.85rem;
        }
        /* ========== RIGHT COLUMN: MAIN CANVAS CARD ========== */
        .verif-main-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            padding: 32px;
            transition: all 0.3s ease;
        }
        /* Modern Government Banner Header */
        .canvas-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            position: relative;
        }
        .canvas-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background: var(--verif-gold);
        }
        .canvas-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .canvas-header-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--verif-gold-light);
            color: var(--verif-maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .canvas-header-text h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
        }
        .canvas-header-text p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 3px 0 0;
        }
        .step-indicator-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--verif-gold-light);
            border: 1px solid rgba(201, 168, 108, 0.4);
            border-radius: 50px;
            color: var(--verif-maroon);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        /* ========== SUBMITTED DOCUMENT SHOWCASE (PERSISTENCE) ========== */
        .submitted-docs-showcase {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        .showcase-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .showcase-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .showcase-header h3 i {
            color: var(--verif-gold);
        }
        .doc-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .doc-display-card {
            background: var(--badge-bg);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
        }
        .doc-display-card:hover {
            border-color: var(--verif-gold);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .doc-display-top {
            padding: 12px 14px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .doc-display-top .doc-name {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .doc-display-top .doc-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            background: var(--verif-emerald-light);
            color: var(--verif-emerald);
        }
        .doc-img-viewport {
            position: relative;
            width: 100%;
            height: 200px;
            background: #0d1317;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
        }
        .doc-img-viewport img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .doc-img-viewport:hover img {
            transform: scale(1.05);
        }
        .doc-hover-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 65, 74, 0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #fff;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(2px);
        }
        .doc-img-viewport:hover .doc-hover-overlay {
            opacity: 1;
        }
        .doc-hover-overlay i {
            font-size: 1.6rem;
            color: var(--verif-gold);
        }
        .doc-hover-overlay span {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .doc-display-bottom {
            padding: 10px 14px;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .doc-meta-sub {
            font-size: 0.68rem;
            color: var(--text-muted);
        }
        .btn-doc-inspect {
            background: transparent;
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-doc-inspect:hover {
            background: var(--verif-maroon);
            color: #fff;
            border-color: var(--verif-maroon);
        }
        /* Detail List Info Rows */
        .info-pill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        .info-pill-item {
            background: var(--badge-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
        }
        .info-pill-label {
            font-size: 0.68rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }
        .info-pill-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        /* ========== REAL-TIME CAMERA STUDIO & GUIDANCE ========== */
        .camera-studio-container {
            background: #090D0F;
            border-radius: var(--radius-element);
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border: 2px solid #202b33;
        }
        .camera-viewport-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .camera-viewport-wrap video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Hidden canvas for computer vision pixel analysis */
        #cvAnalysisCanvas {
            display: none;
        }
        /* --- Live HUD Overlay --- */
        .camera-hud-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px;
            z-index: 10;
        }
        /* Dynamic Status Pill Bar */
        .hud-status-banner {
            align-self: center;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 18px;
            border-radius: 50px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .hud-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            animation: hudDotBlink 1.4s infinite;
        }
        @keyframes hudDotBlink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }
        /* Status Colors */
        .hud-status-banner.state-ready {
            border-color: #10b981;
            background: rgba(6, 78, 59, 0.9);
            color: #d1fae5;
        }
        .hud-status-banner.state-ready .hud-status-dot {
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
        }
        .hud-status-banner.state-warning {
            border-color: #f59e0b;
            background: rgba(120, 53, 15, 0.9);
            color: #fef3c7;
        }
        .hud-status-banner.state-warning .hud-status-dot {
            background: #f59e0b;
        }
        .hud-status-banner.state-danger {
            border-color: #ef4444;
            background: rgba(127, 29, 29, 0.9);
            color: #fee2e2;
        }
        /* 3 Real-Time Metric Chips at HUD bottom */
        .hud-metrics-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .hud-metric-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
            font-size: 0.68rem;
            font-weight: 600;
        }
        .hud-metric-chip.metric-good {
            color: #34d399;
            border-color: rgba(52, 211, 153, 0.4);
        }
        .hud-metric-chip.metric-warn {
            color: #fbbf24;
            border-color: rgba(251, 191, 36, 0.4);
        }
        .hud-metric-chip.metric-bad {
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.4);
        }
        /* --- ID CARD ALIGNMENT GUIDE FRAME --- */
        .id-guide-box {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 72%;
            max-width: 480px;
            aspect-ratio: 1.58 / 1; /* Standard ID-1 Card ratio */
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.65); /* Vignette cutout */
            border: 2px solid rgba(255, 255, 255, 0.35);
            transition: all 0.3s ease;
            pointer-events: none;
            overflow: hidden;
        }
        .id-guide-box.state-ready {
            border-color: #10b981;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.65), 0 0 24px rgba(16, 185, 129, 0.5);
        }
        .id-guide-box.state-warning {
            border-color: #f59e0b;
        }
        .id-guide-box.state-danger {
            border-color: #ef4444;
        }
        /* 4 Glowing Corner L-Brackets */
        .guide-corner {
            position: absolute;
            width: 24px;
            height: 24px;
            border-color: var(--verif-gold);
            border-style: solid;
            pointer-events: none;
            transition: border-color 0.3s;
        }
        .corner-tl { top: 0; left: 0; border-width: 4px 0 0 4px; border-top-left-radius: 8px; }
        .corner-tr { top: 0; right: 0; border-width: 4px 4px 0 0; border-top-right-radius: 8px; }
        .corner-bl { bottom: 0; left: 0; border-width: 0 0 4px 4px; border-bottom-left-radius: 8px; }
        .corner-br { bottom: 0; right: 0; border-width: 0 4px 4px 0; border-bottom-right-radius: 8px; }
        .id-guide-box.state-ready .guide-corner { border-color: #10b981; }
        .id-guide-box.state-warning .guide-corner { border-color: #f59e0b; }
        .id-guide-box.state-danger .guide-corner { border-color: #ef4444; }
        /* Scanning Laser Line */
        .scanline-beam {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #10b981, transparent);
            box-shadow: 0 0 10px #10b981, 0 0 20px #10b981;
            opacity: 0.8;
            animation: scanlineSweep 2.5s infinite linear;
        }
        @keyframes scanlineSweep {
            0% { top: 0; opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { top: 100%; opacity: 0; }
        }
        /* Inner dashed card outline & hint badge */
        .guide-inner-border {
            position: absolute;
            inset: 8px;
            border: 1.5px dashed rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .id-guide-box.state-ready .guide-inner-border {
            border-color: rgba(16, 185, 129, 0.6);
            border-style: solid;
        }
        .guide-card-hint {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.68rem;
            font-weight: 700;
            pointer-events: none;
            letter-spacing: 0.3px;
            white-space: nowrap;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .id-guide-box.state-ready .guide-card-hint {
            background: rgba(6, 78, 59, 0.9);
            border-color: #10b981;
            color: #d1fae5;
        }
        /* --- SELFIE FACE & ID ALIGNMENT GUIDE --- */
        .selfie-guide-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 94%;
            max-width: 660px;
            height: 90%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 26px;
            pointer-events: none;
            z-index: 5;
        }
        .selfie-face-oval {
            width: 250px;
            height: 330px;
            border-radius: 50% 50% 48% 48% / 60% 60% 40% 40%;
            border: 3.5px dashed rgba(255, 255, 255, 0.7);
            position: relative;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 0 16px rgba(0, 0, 0, 0.35);
        }
        .selfie-face-oval::after {
            content: 'Center Face';
            position: absolute;
            bottom: -32px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.95);
            white-space: nowrap;
            font-weight: 700;
            background: rgba(15, 23, 42, 0.85);
            padding: 3px 12px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.3px;
        }
        .selfie-id-box-guide {
            width: 195px;
            height: 125px;
            border-radius: 12px;
            border: 3px dashed var(--verif-gold);
            position: relative;
            background: rgba(201, 168, 108, 0.12);
            box-shadow: inset 0 0 16px rgba(201, 168, 108, 0.15), 0 0 16px rgba(0, 0, 0, 0.35);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .selfie-id-box-guide::after {
            content: 'Hold ID Here';
            position: absolute;
            bottom: -32px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.74rem;
            color: #fef3c7;
            white-space: nowrap;
            font-weight: 700;
            background: rgba(15, 23, 42, 0.85);
            padding: 3px 12px;
            border-radius: 14px;
            border: 1px solid rgba(201, 168, 108, 0.4);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.3px;
        }
        .selfie-face-oval.state-ready {
            border-color: #10b981 !important;
            border-style: solid !important;
            box-shadow: 0 0 32px rgba(16, 185, 129, 0.75), inset 0 0 16px rgba(16, 185, 129, 0.25) !important;
            animation: pkhOvalGlowPulse 1.6s infinite ease-in-out;
        }
        @keyframes pkhOvalGlowPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 26px rgba(16, 185, 129, 0.65), inset 0 0 12px rgba(16, 185, 129, 0.2); }
            50% { transform: scale(1.02); box-shadow: 0 0 42px rgba(16, 185, 129, 0.9), inset 0 0 20px rgba(16, 185, 129, 0.35); }
        }
        .selfie-face-oval.state-aligning {
            border-color: #f59e0b;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.45);
        }
        .selfie-face-oval.state-ready::after {
            border-color: #10b981;
            color: #6ee7b7;
            background: rgba(6, 78, 59, 0.9);
        }
        .selfie-face-oval.state-warning {
            border-color: #f59e0b;
        }
        .selfie-face-oval.state-danger {
            border-color: #ef4444;
        }
        .selfie-id-box-guide.state-ready {
            border-color: #10b981;
            border-style: solid;
            background: rgba(16, 185, 129, 0.15);
            box-shadow: inset 0 0 16px rgba(16, 185, 129, 0.25), 0 0 22px rgba(16, 185, 129, 0.55);
        }
        .selfie-id-box-guide.state-ready::after {
            border-color: #10b981;
            color: #6ee7b7;
            background: rgba(6, 78, 59, 0.9);
        }
        /* Countdown overlay for auto capture */
        .countdown-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 25;
            backdrop-filter: blur(2px);
        }
        .countdown-number {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--verif-emerald);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 900;
            box-shadow: 0 0 30px #10b981;
            animation: countPulse 0.9s infinite ease;
        }
        @keyframes countPulse {
            0% { transform: scale(0.8); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        /* --- Camera Studio Controls Bar --- */
        .camera-studio-bar {
            background: #111827;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            border-top: 1px solid #1f2937;
        }
        .studio-options-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .auto-capture-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            user-select: none;
        }
        .auto-capture-toggle input {
            cursor: pointer;
            accent-color: var(--verif-emerald);
            width: 16px;
            height: 16px;
        }
        .studio-shutter-center {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-shutter-main {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--verif-maroon), #9a1515);
            color: #fff;
            border: 3px solid #fff;
            box-shadow: 0 0 16px rgba(127, 3, 3, 0.5);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-shutter-main:hover {
            transform: scale(1.08);
            box-shadow: 0 0 22px rgba(127, 3, 3, 0.7);
        }
        .btn-shutter-main:active {
            transform: scale(0.95);
        }
        .btn-shutter-main.state-ready-pulse {
            background: linear-gradient(135deg, #10B981, #059669) !important;
            border-color: #6EE7B7 !important;
            animation: shutterGlowPulse 1.4s infinite cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        @keyframes shutterGlowPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { transform: scale(1.08); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .btn-camera-util {
            background: #1f2937;
            color: #cbd5e1;
            border: 1px solid #374151;
            padding: 8px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-camera-util:hover {
            background: #374151;
            color: #fff;
        }
        /* Snapshot Preview inside Studio */
        .snapshot-preview-holder {
            display: none;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            background: #000;
        }
        .snapshot-preview-holder img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .snapshot-review-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85), transparent);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-retake {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-retake:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .btn-preview-zoom {
            background: var(--verif-gold);
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-preview-zoom:hover {
            transform: scale(1.04);
        }
        /* ========== FILE UPLOAD MODE PREVIEWS ========== */
        .upload-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-element);
            padding: 30px 20px;
            text-align: center;
            background: var(--badge-bg);
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: var(--verif-gold);
            background: rgba(201, 168, 108, 0.06);
        }
        .upload-dropzone i.upload-main-icon {
            font-size: 2.4rem;
            color: var(--verif-gold);
            opacity: 0.7;
            margin-bottom: 10px;
            display: block;
        }
        .upload-dropzone p {
            font-size: 0.85rem;
            color: var(--text-primary);
            margin: 0;
            font-weight: 600;
        }
        .upload-dropzone .sub-types {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .upload-dropzone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 5;
        }
        /* File Uploaded Inline Preview Card */
        .uploaded-file-card {
            display: none;
            background: var(--card-bg);
            border: 1.5px solid var(--verif-gold);
            border-radius: var(--radius-element);
            padding: 14px;
            margin-top: 14px;
            box-shadow: var(--shadow-sm);
        }
        .uploaded-file-flex {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .uploaded-thumb-preview {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            background: #0f172a;
            object-fit: cover;
            cursor: pointer;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .uploaded-file-meta {
            flex: 1;
            min-width: 0;
        }
        .uploaded-file-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0 0 2px;
        }
        .uploaded-file-size {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin: 0;
        }
        .uploaded-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-file-zoom {
            background: var(--badge-bg);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-file-zoom:hover {
            background: var(--verif-gold);
            color: #fff;
            border-color: var(--verif-gold);
        }
        .btn-file-remove {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: var(--verif-crimson);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-file-remove:hover {
            background: var(--verif-crimson);
            color: #fff;
        }
        /* ========== SUMMARY BLOCK IN STEP 4 ========== */
        .verif-summary-panel {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 20px;
            border: 1px solid var(--border-color);
            margin-top: 24px;
        }
        .summary-title-bar {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text-primary);
            padding-bottom: 8px;
            margin-bottom: 14px;
            border-bottom: 1.5px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-title-bar i {
            color: var(--verif-gold);
        }
        .summary-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }
        .sum-item {
            display: flex;
            flex-direction: column;
            font-size: 0.78rem;
        }
        .sum-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .sum-val {
            font-weight: 700;
            color: var(--text-primary);
        }
        .summary-photos-duo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 14px;
        }
        .sum-photo-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .sum-photo-top {
            padding: 6px 10px;
            background: rgba(15, 65, 74, 0.05);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sum-photo-thumb-wrap {
            position: relative;
            width: 100%;
            height: 120px;
            background: #0f172a;
            cursor: pointer;
        }
        .sum-photo-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sum-photo-thumb-wrap .overlay-zoom-hint {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s;
            gap: 5px;
        }
        .sum-photo-thumb-wrap:hover .overlay-zoom-hint {
            opacity: 1;
        }
        /* Stepper action controls */
        .step-footer-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 28px;
            gap: 16px;
        }
        .btn-step-prev {
            padding: 12px 26px;
            border-radius: 50px;
            background: var(--badge-bg);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-step-prev:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }
        .btn-step-next {
            padding: 12px 32px;
            border-radius: 50px;
            background: linear-gradient(135deg, var(--verif-maroon), #9a1515);
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3);
        }
        .btn-step-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.45);
        }
        /* Glowing Emerald Pulse */
        @keyframes emeraldGlowPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.6), 0 4px 14px rgba(5, 150, 105, 0.3); }
            50% { box-shadow: 0 0 0 10px rgba(5, 150, 105, 0), 0 6px 20px rgba(5, 150, 105, 0.5); }
        }
        .pulse-emerald {
            animation: emeraldGlowPulse 2s infinite ease-in-out;
            background: linear-gradient(135deg, var(--verif-emerald), #047857) !important;
        }
        /* ========== UNIVERSAL FULL-SCREEN LIGHTBOX MODAL ========== */
        .lightbox-modal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(8, 12, 16, 0.92);
            backdrop-filter: blur(8px);
            display: none;
            flex-direction: column;
            animation: fadeInLightbox 0.25s ease;
        }
        @keyframes fadeInLightbox {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .lightbox-modal.active {
            display: flex;
        }
        .lightbox-topbar {
            height: 56px;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(15, 23, 42, 0.7);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .lightbox-title {
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .lightbox-title span {
            color: var(--verif-gold);
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(201, 168, 108, 0.15);
        }
        .lightbox-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-lightbox-tool {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .btn-lightbox-tool:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }
        .btn-lightbox-close {
            background: rgba(220, 38, 38, 0.25);
            border-color: rgba(220, 38, 38, 0.4);
            color: #f87171;
            margin-left: 10px;
        }
        .btn-lightbox-close:hover {
            background: #dc2626;
            color: #fff;
        }
        .lightbox-stage {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            user-select: none;
            cursor: grab;
        }
        .lightbox-stage:active {
            cursor: grabbing;
        }
        .lightbox-stage img {
            max-width: 90%;
            max-height: 85vh;
            object-fit: contain;
            transition: transform 0.15s ease-out;
            transform-origin: center center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            border-radius: 6px;
        }
        .lightbox-bottom-info {
            padding: 10px 24px;
            background: rgba(15, 23, 42, 0.7);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        /* Status Cards */
        .status-hero-banner {
            border-radius: var(--radius-element);
            padding: 24px 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            border: 1px solid transparent;
        }
        .hero-banner-pending {
            background: linear-gradient(135deg, rgba(217, 119, 6, 0.08) 0%, rgba(201, 168, 108, 0.12) 100%);
            border-color: rgba(217, 119, 6, 0.3);
        }
        .hero-banner-verified {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.08) 0%, rgba(52, 211, 153, 0.12) 100%);
            border-color: rgba(5, 150, 105, 0.3);
        }
        .hero-banner-rejected {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.08) 0%, rgba(248, 113, 113, 0.12) 100%);
            border-color: rgba(220, 38, 38, 0.3);
        }
        .hero-banner-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }
        .hero-banner-pending .hero-banner-icon { background: rgba(217, 119, 6, 0.15); color: var(--verif-amber); }
        .hero-banner-verified .hero-banner-icon { background: rgba(5, 150, 105, 0.15); color: var(--verif-emerald); }
        .hero-banner-rejected .hero-banner-icon { background: rgba(220, 38, 38, 0.15); color: var(--verif-crimson); }
        .hero-banner-content h2 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px;
        }
        .hero-banner-content p {
            font-size: 0.82rem;
            color: var(--text-secondary);
            margin: 0 0 6px;
        }
        .hero-banner-content .meta-tag {
            font-size: 0.72rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        /* Resubmit button on rejected state */
        .btn-resubmit-trigger {
            margin-top: 10px;
            padding: 8px 18px;
            border-radius: 30px;
            background: var(--verif-maroon);
            color: #fff;
            border: none;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-resubmit-trigger:hover {
            background: #9a1515;
            transform: translateY(-1px);
        }
        /* Alert notifications */
        .alert-toast-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.25);
            color: #b91c1c;
        }
        .alert-success {
            background: rgba(5, 150, 105, 0.1);
            border: 1px solid rgba(5, 150, 105, 0.25);
            color: #047857;
        }
        /* Stepper navigation states */
        .step-panel-content {
            display: none;
            animation: fadeInStep 0.35s ease;
        }
        .step-panel-content.active {
            display: block;
        }
        @keyframes fadeInStep {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Form elements */
        .form-row-duo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-field-group {
            margin-bottom: 18px;
        }
        .form-field-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-secondary);
        }
        .form-field-group label i {
            color: var(--verif-gold);
            margin-right: 6px;
            width: 16px;
        }
        .form-field-group input,
        .form-field-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
            transition: all 0.2s;
        }
        .form-field-group input:focus,
        .form-field-group select:focus {
            outline: none;
            border-color: var(--verif-gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.2);
        }
        /* Mode Selector Tabs (Camera vs Upload) */
        .mode-tab-buttons {
            display: inline-flex;
            background: var(--badge-bg);
            padding: 4px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            margin-bottom: 18px;
        }
        .mode-tab-btn {
            background: transparent;
            border: none;
            padding: 7px 18px;
            border-radius: 24px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .mode-tab-btn.active {
            background: var(--card-bg);
            color: var(--verif-maroon);
            box-shadow: var(--shadow-sm);
        }
        /* Responsive Media Queries */
        @media (max-width: 992px) {
            .verif-landscape-grid {
                grid-template-columns: 1fr;
            }
            .verif-sidebar-card {
                position: static;
            }
            .selfie-guide-container {
                gap: 20px;
            }
            .selfie-face-oval {
                width: 205px;
                height: 275px;
            }
            .selfie-id-box-guide {
                width: 160px;
                height: 104px;
            }
        }
        @media (max-width: 640px) {
            .verif-main-card {
                padding: 20px 16px;
            }
            .form-row-duo, .summary-info-grid, .summary-photos-duo {
                grid-template-columns: 1fr;
            }
            .camera-viewport-wrap {
                aspect-ratio: 4 / 3;
            }
            .selfie-guide-container {
                gap: 12px;
            }
            .selfie-face-oval {
                width: 155px;
                height: 210px;
            }
            .selfie-id-box-guide {
                width: 120px;
                height: 78px;
            }
            .selfie-face-oval::after, .selfie-id-box-guide::after {
                font-size: 0.65rem;
                padding: 2px 8px;
                bottom: -25px;
            }
        }
        /* ========== DUAL-SIDE ID (FRONT & BACK) CONTROLS ========== */
        .id-dual-side-nav {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 18px;
        }
        .id-side-tab-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--card-bg, #ffffff);
            border: 2px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: left;
        }
        [data-theme="dark"] .id-side-tab-pill {
            background: #1e293b;
            border-color: #334155;
        }
        .id-side-tab-pill:hover {
            border-color: var(--verif-gold, #c9a86c);
            transform: translateY(-1px);
        }
        .id-side-tab-pill.active {
            background: linear-gradient(135deg, rgba(123, 17, 19, 0.08) 0%, rgba(201, 168, 108, 0.12) 100%);
            border-color: var(--verif-maroon, #7b1113);
            box-shadow: 0 4px 12px rgba(123, 17, 19, 0.08);
        }
        [data-theme="dark"] .id-side-tab-pill.active {
            background: linear-gradient(135deg, rgba(123, 17, 19, 0.35) 0%, rgba(201, 168, 108, 0.2) 100%);
            border-color: var(--verif-gold, #c9a86c);
        }
        .pill-side-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--border-color, #e2e8f0);
            color: var(--text-secondary, #64748b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .id-side-tab-pill.active .pill-side-num {
            background: var(--verif-maroon, #7b1113);
            color: #ffffff;
        }
        .id-side-tab-pill.is-captured .pill-side-num {
            background: #10b981 !important;
            color: #ffffff !important;
        }
        .pill-side-content {
            flex: 1;
            min-width: 0;
        }
        .pill-title {
            display: block;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
            margin-bottom: 2px;
        }
        .pill-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted, #94a3b8);
        }
        .pill-status.state-ready {
            color: #10b981;
        }
        .side-flow-navigation {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed var(--border-color, #e2e8f0);
        }
        .btn-side-switch-nav {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid var(--verif-gold, #c9a86c);
            background: linear-gradient(135deg, var(--verif-maroon, #7b1113), #94171a);
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .btn-side-switch-nav:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(123, 17, 19, 0.2);
        }
        .btn-side-switch-nav.secondary {
            background: transparent;
            color: var(--text-primary, #0f172a);
            border-color: var(--border-color, #cbd5e1);
        }
        .summary-photos-duo {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) !important;
            gap: 12px !important;
        }
        /* ========== AI ID CARD OCR SCANNER STYLES ========== */
        .ocr-extracted-card {
            margin-top: 16px;
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.08) 0%, rgba(201, 168, 108, 0.09) 100%);
            border: 1.5px solid rgba(5, 150, 105, 0.4);
            border-radius: 18px;
            padding: 16px 20px;
            text-align: left;
            animation: ocrSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        }
        [data-theme="dark"] .ocr-extracted-card {
            background: rgba(5, 150, 105, 0.14) !important;
            border-color: rgba(52, 211, 153, 0.45) !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
        }
        @keyframes ocrSlideIn {
            0% { opacity: 0; transform: translateY(-10px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .ocr-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .ocr-badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #065F46;
        }
        [data-theme="dark"] .ocr-badge-tag {
            color: #6EE7B7 !important;
        }
        .ocr-status-pill {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            background: rgba(5, 150, 105, 0.16);
            color: #047857;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        [data-theme="dark"] .ocr-status-pill {
            color: #A7F3D0 !important;
            background: rgba(5, 150, 105, 0.28) !important;
        }
        .ocr-data-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .ocr-field-item {
            background: rgba(255, 255, 255, 0.85);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }
        [data-theme="dark"] .ocr-field-item {
            background: rgba(15, 23, 42, 0.75) !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        .ocr-field-label {
            display: block;
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748B;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }
        [data-theme="dark"] .ocr-field-label {
            color: #94A3B8 !important;
        }
        .ocr-field-val {
            font-size: 0.82rem;
            font-weight: 800;
            color: #0F172A;
            word-break: break-all;
        }
        [data-theme="dark"] .ocr-field-val {
            color: #F8FAFC !important;
        }
        .ocr-autofill-hint {
            font-size: 0.74rem;
            color: #047857;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.4;
        }
        [data-theme="dark"] .ocr-autofill-hint {
            color: #34D399 !important;
        }
        /* Highlight fields when auto-filled */
        .ocr-autofilled-glow {
            border-color: #10B981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25) !important;
            background-color: rgba(16, 185, 129, 0.04) !important;
            transition: all 0.4s ease;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar -->
        <?php if (isStaff() || isAdmin()): ?>
            <?php include __DIR__ . '/staff/includes/staff-sidebar.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header -->
            <?php if (isStaff() || isAdmin()): ?>
                <?php include __DIR__ . '/staff/includes/staff-header.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/portal-header.php'; ?>
            <?php endif; ?>
            <div class="portal-landscape-wrapper">
                <!-- Universal Top Hero Banner -->
                <?php
                $banner_title = "Residency Verification";
                $banner_subtitle = "Official citizen accreditation for City of Palayan social welfare and assistance services";
                $banner_icon = "fa-shield-alt";
                $banner_actions = [
                    ["label" => "My Profile", "url" => "profile.php", "icon" => "fa-user", "type" => "secondary"],
                    ["label" => "Portal Home", "url" => "index.php", "icon" => "fa-home", "type" => "primary"]
                ];
                include __DIR__ . "/includes/portal-banner.php";
                ?>
                <!-- Main Landscape Grid Layout -->
                <div class="verif-landscape-grid">
                    <!-- ==============================================
                         LEFT COLUMN: PALAYAN GOV GUIDE & PROGRESS
                         ============================================== -->
                    <aside class="verif-sidebar-card">
                        <!-- City Gov Branding Seal -->
                        <div class="gov-seal-badge">
                            <div class="gov-seal-icon">
                                <i class="fas fa-landmark"></i>
                            </div>
                            <div class="gov-seal-text">
                                <h3>Palayan City CSWDO</h3>
                                <p>Resident Verification Unit</p>
                            </div>
                        </div>
                        <!-- Stepper Progress Tracker (Active during submission) -->
                        <div>
                            <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 10px;">
                                Verification Workflow
                            </div>
                            <div class="side-stepper">
                                <div class="side-step-item active" data-side-step="1">
                                    <div class="side-step-num">1</div>
                                    <div class="side-step-info">
                                        <p class="side-step-title">ID Identification</p>
                                        <p class="side-step-desc">Type & Reference #</p>
                                    </div>
                                </div>
                                <div class="side-step-item" data-side-step="2">
                                    <div class="side-step-num">2</div>
                                    <div class="side-step-info">
                                        <p class="side-step-title">Proof of ID</p>
                                        <p class="side-step-desc">Capture or Upload ID</p>
                                    </div>
                                </div>
                                <div class="side-step-item" data-side-step="3">
                                    <div class="side-step-num">3</div>
                                    <div class="side-step-info">
                                        <p class="side-step-title">Selfie with ID</p>
                                        <p class="side-step-desc">Facial alignment</p>
                                    </div>
                                </div>
                                <div class="side-step-item" data-side-step="4">
                                    <div class="side-step-num">4</div>
                                    <div class="side-step-info">
                                        <p class="side-step-title">Address & Confirm</p>
                                        <p class="side-step-desc">Barangay & Review</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Tips & Best Practices -->
                        <div class="verif-tips-box">
                            <div class="verif-tips-title">
                                <i class="fas fa-lightbulb"></i> Guidelines for Fast Approval
                            </div>
                            <ul class="verif-tips-list">
                                <li>Ensure valid government-issued ID (PhilSys, Driver's License, UMID, etc.).</li>
                                <li>Keep ID flat and completely within the on-screen guide frame.</li>
                                <li>Avoid reflections, glares, or backlighting when capturing.</li>
                                <li>Hold ID steadily beside your face for the selfie.</li>
                            </ul>
                        </div>
                        <!-- Security & Privacy Assurance -->
                        <div class="privacy-pill">
                            <i class="fas fa-user-shield"></i>
                            <div>
                                <strong>RA 10173 Protected</strong><br>
                                Encrypted & restricted to authorized CSWDO personnel.
                            </div>
                        </div>
                    </aside>
                    <!-- ==============================================
                         RIGHT COLUMN: INTERACTIVE CANVAS
                         ============================================== -->
                    <main class="verif-main-card">
                        <!-- System Alert Messages -->
                        <?php if($error): ?>
                            <div class="alert-toast-box alert-error">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert-toast-box alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        <!-- STATE 1: ALREADY VERIFIED -->
                        <?php if($verification_status == 'verified'): ?>
                            <div class="status-hero-banner hero-banner-verified">
                                <div class="hero-banner-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="hero-banner-content">
                                    <h2>Verified Resident Account</h2>
                                    <p>Your residency in Palayan City has been officially validated. You have unrestricted access to all CSWDO social services.</p>
                                    <div class="meta-tag">
                                        <span><i class="fas fa-certificate" style="color: var(--verif-emerald);"></i> Status: Approved</span>
                                        <?php if(!empty($existing_request['submitted_date'])): ?>
                                            <span><i class="fas fa-calendar-check"></i> Date: <?php echo date('F d, Y', strtotime($existing_request['submitted_date'])); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($existing_request['barangay_name'])): ?>
                                            <span><i class="fas fa-map-marker-alt"></i> Barangay: <?php echo htmlspecialchars($existing_request['barangay_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                        <a href="update-profile-info.php?guided=1" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 50px; background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%); color: #ffffff; text-decoration: none; font-size: 0.85rem; font-weight: 700; box-shadow: 0 4px 14px rgba(127, 3, 3, 0.35);">
                                            <i class="fas fa-user-edit"></i> Complete Resident Profile &rarr;
                                        </a>
                                        <button type="button" onclick="openWelcomeProfileModal()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 50px; background: rgba(5, 150, 105, 0.12); color: #059669; border: 1.5px solid rgba(5, 150, 105, 0.35); cursor: pointer; font-size: 0.85rem; font-weight: 700;">
                                            <i class="fas fa-sparkles"></i> Step-by-Step Guide
                                        </button>
                                        <a href="dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 50px; background: #ffffff; color: #1e293b; border: 1.5px solid #cbd5e1; text-decoration: none; font-size: 0.85rem; font-weight: 700;">
                                            <i class="fas fa-home"></i> Go to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Showcase Submitted Documents -->
                            <?php if($existing_request): ?>
                                <div class="submitted-docs-showcase">
                                    <div class="showcase-header">
                                        <h3><i class="fas fa-folder-open"></i> Your Verified Verification Documents</h3>
                                        <span style="font-size: 0.72rem; color: var(--text-muted);"><i class="fas fa-lock"></i> Secured on File</span>
                                    </div>
                                    <div class="doc-cards-grid">
                                        <!-- Valid ID Card -->
                                        <div class="doc-display-card">
                                            <div class="doc-display-top">
                                                <span class="doc-name"><i class="fas fa-id-card"></i> Valid ID</span>
                                                <span class="doc-badge"><?php echo htmlspecialchars($existing_request['id_type']); ?></span>
                                            </div>
                                            <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Valid ID - <?php echo htmlspecialchars($existing_request['id_type']); ?>')">
                                                <img src="<?php echo htmlspecialchars($existing_request['id_file_path']); ?>" alt="Valid ID" onerror="this.src='images/palayan-city.png'">
                                                <div class="doc-hover-overlay">
                                                    <i class="fas fa-search-plus"></i>
                                                    <span>Click to Inspect / Zoom</span>
                                                </div>
                                            </div>
                                            <div class="doc-display-bottom">
                                                <span class="doc-meta-sub">ID #: <?php echo htmlspecialchars($existing_request['id_number']); ?></span>
                                                <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Valid ID')">
                                                    <i class="fas fa-expand"></i> Inspect
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Selfie with ID Card -->
                                        <div class="doc-display-card">
                                            <div class="doc-display-top">
                                                <span class="doc-name"><i class="fas fa-portrait"></i> Selfie with ID</span>
                                                <span class="doc-badge">Identity Proof</span>
                                            </div>
                                            <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Resident Selfie with ID')">
                                                <img src="<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>" alt="Selfie with ID" onerror="this.src='images/palayan-city.png'">
                                                <div class="doc-hover-overlay">
                                                    <i class="fas fa-search-plus"></i>
                                                    <span>Click to Inspect / Zoom</span>
                                                </div>
                                            </div>
                                            <div class="doc-display-bottom">
                                                <span class="doc-meta-sub">Biometric Alignment</span>
                                                <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Selfie with ID')">
                                                    <i class="fas fa-expand"></i> Inspect
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <!-- STATE 2: PENDING REVIEW -->
                        <?php elseif($existing_request && $existing_request['status'] == 'pending'): ?>
                            <div class="status-hero-banner hero-banner-pending">
                                <div class="hero-banner-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="hero-banner-content">
                                    <h2>Verification Under Staff Review</h2>
                                    <p>Your documents have been successfully received and are currently queued for inspection by CSWDO accreditation officers.</p>
                                    <div class="meta-tag">
                                        <span><i class="fas fa-hashtag"></i> Ref: #VR-<?php echo str_pad($existing_request['verification_id'], 5, '0', STR_PAD_LEFT); ?></span>
                                        <span><i class="fas fa-calendar-alt"></i> Submitted: <?php echo date('F d, Y · h:i A', strtotime($existing_request['submitted_date'])); ?></span>
                                        <span><i class="fas fa-map-marker-alt"></i> Barangay: <?php echo htmlspecialchars($existing_request['barangay_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Showcase Submitted Documents with Zoom & Inspection -->
                            <div class="submitted-docs-showcase">
                                <div class="showcase-header">
                                    <h3><i class="fas fa-images"></i> Submitted Verification Documents</h3>
                                    <span style="font-size: 0.72rem; color: var(--text-muted);"><i class="fas fa-eye"></i> Click any image to zoom & inspect</span>
                                </div>
                                <div class="doc-cards-grid">
                                    <!-- Valid ID Card -->
                                    <div class="doc-display-card">
                                        <div class="doc-display-top">
                                            <span class="doc-name"><i class="fas fa-id-card"></i> Valid ID Document</span>
                                            <span class="doc-badge"><?php echo htmlspecialchars($existing_request['id_type']); ?></span>
                                        </div>
                                        <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Valid ID - <?php echo htmlspecialchars($existing_request['id_type']); ?>')">
                                            <img src="<?php echo htmlspecialchars($existing_request['id_file_path']); ?>" alt="Valid ID" onerror="this.src='images/palayan-city.png'">
                                            <div class="doc-hover-overlay">
                                                <i class="fas fa-search-plus"></i>
                                                <span>Click to Inspect / Zoom</span>
                                            </div>
                                        </div>
                                        <div class="doc-display-bottom">
                                            <span class="doc-meta-sub">ID #: <?php echo htmlspecialchars($existing_request['id_number']); ?></span>
                                            <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Valid ID - <?php echo htmlspecialchars($existing_request['id_type']); ?>')">
                                                <i class="fas fa-search-plus"></i> Zoom
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Selfie with ID Card -->
                                    <div class="doc-display-card">
                                        <div class="doc-display-top">
                                            <span class="doc-name"><i class="fas fa-portrait"></i> Selfie with Document</span>
                                            <span class="doc-badge">Biometric</span>
                                        </div>
                                        <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Resident Selfie with ID')">
                                            <img src="<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>" alt="Selfie with ID" onerror="this.src='images/palayan-city.png'">
                                            <div class="doc-hover-overlay">
                                                <i class="fas fa-search-plus"></i>
                                                <span>Click to Inspect / Zoom</span>
                                            </div>
                                        </div>
                                        <div class="doc-display-bottom">
                                            <span class="doc-meta-sub">Status: Awaiting Verification</span>
                                            <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Selfie with ID')">
                                                <i class="fas fa-search-plus"></i> Zoom
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Resident Details Information -->
                            <div class="info-pill-grid">
                                <div class="info-pill-item">
                                    <div class="info-pill-label">Registered Name</div>
                                    <div class="info-pill-value"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                </div>
                                <div class="info-pill-item">
                                    <div class="info-pill-label">ID Type & Number</div>
                                    <div class="info-pill-value"><?php echo htmlspecialchars($existing_request['id_type'] . ' · ' . $existing_request['id_number']); ?></div>
                                </div>
                                <div class="info-pill-item">
                                    <div class="info-pill-label">Barangay Residence</div>
                                    <div class="info-pill-value"><?php echo htmlspecialchars($existing_request['barangay_name']); ?></div>
                                </div>
                                <div class="info-pill-item">
                                    <div class="info-pill-label">Complete Address</div>
                                    <div class="info-pill-value"><?php echo htmlspecialchars($existing_request['complete_address']); ?></div>
                                </div>
                            </div>
                        <!-- STATE 3: REJECTED OR NEW APPLICATION WIZARD -->
                        <?php else: ?>
                            <?php if($verification_status == 'rejected'): ?>
                                <div class="status-hero-banner hero-banner-rejected">
                                    <div class="hero-banner-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="hero-banner-content">
                                        <h2>Verification Requires Resubmission</h2>
                                        <p>Your previous verification request was rejected. Please review the reason below and submit clear, well-aligned photos.</p>
                                        <?php if($existing_request && !empty($existing_request['rejection_reason'])): ?>
                                            <div style="background: rgba(255, 255, 255, 0.7); padding: 8px 12px; border-radius: 6px; margin-top: 6px; font-size: 0.78rem; color: #991b1b;">
                                                <strong>Staff Remarks:</strong> <?php echo htmlspecialchars($existing_request['rejection_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if($existing_request && (!empty($existing_request['id_file_path']) || !empty($existing_request['proof_of_residency_path']))): ?>
                                    <div class="submitted-docs-showcase" style="margin-bottom: 24px; padding-top: 0; border-top: none;">
                                        <div class="showcase-header">
                                            <h3><i class="fas fa-history" style="color: var(--verif-amber);"></i> Previously Submitted Documents</h3>
                                            <span style="font-size: 0.72rem; color: var(--text-muted);"><i class="fas fa-search-plus"></i> Inspect previous photos</span>
                                        </div>
                                        <div class="doc-cards-grid">
                                            <?php if(!empty($existing_request['id_file_path'])): ?>
                                            <div class="doc-display-card">
                                                <div class="doc-display-top">
                                                    <span class="doc-name"><i class="fas fa-id-card"></i> Previous Valid ID</span>
                                                    <span class="doc-badge" style="background: var(--verif-crimson-light); color: var(--verif-crimson);">Rejected</span>
                                                </div>
                                                <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Previously Submitted Valid ID')">
                                                    <img src="<?php echo htmlspecialchars($existing_request['id_file_path']); ?>" alt="Valid ID" onerror="this.src='images/palayan-city.png'">
                                                    <div class="doc-hover-overlay">
                                                        <i class="fas fa-search-plus"></i>
                                                        <span>Inspect Photo</span>
                                                    </div>
                                                </div>
                                                <div class="doc-display-bottom">
                                                    <span class="doc-meta-sub"><?php echo htmlspecialchars($existing_request['id_type']); ?> · <?php echo htmlspecialchars($existing_request['id_number']); ?></span>
                                                    <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['id_file_path']); ?>', 'Previously Submitted Valid ID')">
                                                        <i class="fas fa-search-plus"></i> Zoom
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if(!empty($existing_request['proof_of_residency_path'])): ?>
                                            <div class="doc-display-card">
                                                <div class="doc-display-top">
                                                    <span class="doc-name"><i class="fas fa-portrait"></i> Previous Selfie</span>
                                                    <span class="doc-badge" style="background: var(--verif-crimson-light); color: var(--verif-crimson);">Rejected</span>
                                                </div>
                                                <div class="doc-img-viewport" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Previously Submitted Selfie')">
                                                    <img src="<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>" alt="Selfie with ID" onerror="this.src='images/palayan-city.png'">
                                                    <div class="doc-hover-overlay">
                                                        <i class="fas fa-search-plus"></i>
                                                        <span>Inspect Photo</span>
                                                    </div>
                                                </div>
                                                <div class="doc-display-bottom">
                                                    <span class="doc-meta-sub">Identity Selfie</span>
                                                    <button type="button" class="btn-doc-inspect" onclick="openLightbox('<?php echo htmlspecialchars($existing_request['proof_of_residency_path']); ?>', 'Previously Submitted Selfie')">
                                                        <i class="fas fa-search-plus"></i> Zoom
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <!-- Header Bar of Active Wizard -->
                            <div class="canvas-header">
                                <div class="canvas-header-left">
                                    <div class="canvas-header-icon"><i class="fas fa-camera"></i></div>
                                    <div class="canvas-header-text">
                                        <h2 id="wizardHeaderTitle">Step 1: ID Information</h2>
                                        <p id="wizardHeaderSubtitle">Select your government-issued ID type and ID number</p>
                                    </div>
                                </div>
                                <div class="step-indicator-pill" id="stepPillBadge">
                                    <i class="fas fa-dot-circle"></i> Step 1 of 4
                                </div>
                            </div>
                            <!-- Live Notification Warning -->
                            <div class="alert-toast-box alert-error" id="stepAlert" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="stepAlertText"></span>
                            </div>
                            <!-- Verification Submission Form -->
                            <form method="POST" enctype="multipart/form-data" id="verifyForm">
                                <?php echo csrf_field(); ?>
                                <!-- Base64 inputs for camera captures -->
                                <input type="hidden" name="captured_id_image" id="captured_id_image">
                                <input type="hidden" name="captured_id_back_image" id="captured_id_back_image">
                                <input type="hidden" name="captured_selfie_image" id="captured_selfie_image">
                                <!-- Hidden computer vision analysis canvas -->
                                <canvas id="cvAnalysisCanvas" width="160" height="120"></canvas>
                                <!-- ================= STEP 1: ID INFORMATION ================= -->
                                <div class="step-panel-content active" data-step-panel="1">
                                    <div class="form-field-group">
                                        <label><i class="fas fa-id-card"></i> Valid Government ID Type <span style="color: var(--verif-crimson);">*</span></label>
                                        <select name="id_type" id="id_type" required>
                                            <option value="">-- Choose Government ID --</option>
                                            <option value="PhilSys ID">PhilSys ID (Philippine National ID)</option>
                                            <option value="Driver's License">Driver's License (LTO)</option>
                                            <option value="UMID">UMID (Unified Multi-Purpose ID)</option>
                                            <option value="Passport">Philippine Passport (DFA)</option>
                                            <option value="Postal ID">Postal ID (PhilPost)</option>
                                            <option value="Voter's ID">Voter's ID / Certification (COMELEC)</option>
                                            <option value="Senior Citizen ID">Senior Citizen ID (OSCA)</option>
                                            <option value="PWD ID">Persons with Disability (PWD) ID</option>
                                            <option value="Barangay ID">Barangay Resident ID</option>
                                            <option value="Other">Other Official Government ID</option>
                                        </select>
                                    </div>
                                    <div class="form-field-group">
                                        <label><i class="fas fa-hashtag"></i> ID Card Number <span style="color: var(--verif-crimson);">*</span></label>
                                        <input type="text" name="id_number" id="id_number" placeholder="e.g. 1234-5678-9012-3456" required>
                                    </div>
                                    <div style="background: var(--badge-bg); border-radius: 10px; padding: 14px 18px; border: 1px solid var(--border-color); margin-top: 10px;">
                                        <p style="margin: 0; font-size: 0.78rem; color: var(--text-secondary); line-height: 1.5;">
                                            <i class="fas fa-info-circle" style="color: var(--verif-gold);"></i>
                                            In the next step, you will be guided to position your valid ID in front of your camera or upload a clear photo.
                                        </p>
                                    </div>
                                </div>
                                <!-- ================= STEP 2: PROOF OF IDENTITY (VALID ID: FRONT & BACK) ================= -->
                                <div class="step-panel-content" data-step-panel="2">
                                    <!-- Side Tab Selector: Front Side vs Back Side -->
                                    <div class="id-dual-side-nav">
                                        <button type="button" class="id-side-tab-pill active" id="pillFrontSide" data-side="id">
                                            <div class="pill-side-num">1</div>
                                            <div class="pill-side-content">
                                                <span class="pill-title"><i class="fas fa-id-card"></i> Front Side (Harap)</span>
                                                <span class="pill-status" id="pillStatusFront"><i class="fas fa-clock"></i> Required</span>
                                            </div>
                                        </button>
                                        <button type="button" class="id-side-tab-pill" id="pillBackSide" data-side="id_back">
                                            <div class="pill-side-num">2</div>
                                            <div class="pill-side-content">
                                                <span class="pill-title"><i class="fas fa-id-card-alt"></i> Back Side (Likod)</span>
                                                <span class="pill-status" id="pillStatusBack"><i class="fas fa-clock"></i> Required</span>
                                            </div>
                                        </button>
                                    </div>

                                    <!-- ================= FRONT SIDE PANE ================= -->
                                    <div id="sidePaneFront" class="id-side-pane">
                                        <!-- Mode Tab Switcher: Camera Studio vs Upload -->
                                        <div class="mode-tab-buttons">
                                            <button type="button" class="mode-tab-btn active" data-mode-tab="camera" data-target-step="id">
                                                <i class="fas fa-camera"></i> Live Camera Guide
                                            </button>
                                            <button type="button" class="mode-tab-btn" data-mode-tab="upload" data-target-step="id">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload Front File
                                            </button>
                                        </div>
                                        <!-- ID FRONT CAMERA MODE STUDIO -->
                                        <div id="idCameraSection" class="camera-studio-container">
                                            <div class="camera-viewport-wrap" id="idCameraViewport">
                                                <video id="idVideo" autoplay playsinline muted></video>
                                                <!-- Live HUD Overlay -->
                                                <div class="camera-hud-overlay">
                                                    <div class="hud-status-banner state-danger" id="idHudStatus">
                                                        <span class="hud-status-dot"></span>
                                                        <span id="idHudStatusText">Align front of ID inside frame</span>
                                                    </div>
                                                    <div class="hud-metrics-row">
                                                        <div class="hud-metric-chip metric-bad" id="idMetricLighting">
                                                            <i class="fas fa-sun"></i> Lighting: Checking...
                                                        </div>
                                                        <div class="hud-metric-chip metric-bad" id="idMetricAlignment">
                                                            <i class="fas fa-expand-arrows-alt"></i> Distance: In Frame
                                                        </div>
                                                        <div class="hud-metric-chip metric-bad" id="idMetricClarity">
                                                            <i class="fas fa-magic"></i> Clarity: Sharp
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- ID Card Alignment Guide Frame -->
                                                <div class="id-guide-box" id="idGuideBox">
                                                    <div class="guide-corner corner-tl"></div>
                                                    <div class="guide-corner corner-tr"></div>
                                                    <div class="guide-corner corner-bl"></div>
                                                    <div class="guide-corner corner-br"></div>
                                                    <div class="scanline-beam"></div>
                                                    <div class="guide-inner-border"></div>
                                                    <div class="guide-card-hint">
                                                        <i class="fas fa-id-card"></i>
                                                        <span>Position Front of ID Card</span>
                                                    </div>
                                                </div>
                                                <!-- Auto-Capture 3s Countdown Overlay -->
                                                <div class="countdown-overlay" id="idCountdownOverlay">
                                                    <div class="countdown-number" id="idCountdownNumber">3</div>
                                                </div>
                                                <!-- Snapshot Preview (Shown after capture) -->
                                                <div class="snapshot-preview-holder" id="idSnapshotHolder">
                                                    <img id="idSnapshotImg" src="" alt="Captured Front of ID">
                                                    <div class="snapshot-review-bar">
                                                        <button type="button" class="btn-retake" id="idRetakeBtn">
                                                            <i class="fas fa-redo"></i> Retake Front Photo
                                                        </button>
                                                        <button type="button" class="btn-preview-zoom" id="idZoomBtn">
                                                            <i class="fas fa-search-plus"></i> Inspect Full-Screen
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Studio Bottom Action Bar -->
                                            <div class="camera-studio-bar" id="idStudioBar">
                                                <div class="studio-options-left">
                                                    <label class="auto-capture-toggle">
                                                        <input type="checkbox" id="idAutoCaptureToggle" checked>
                                                        <span><i class="fas fa-bolt" style="color: var(--verif-gold);"></i> Auto-Capture on Alignment</span>
                                                    </label>
                                                </div>
                                                <div class="studio-shutter-center">
                                                    <button type="button" class="btn-shutter-main" id="idManualCaptureBtn" title="Capture Front Snapshot">
                                                        <i class="fas fa-camera"></i>
                                                    </button>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn-camera-util" id="idSwitchCameraBtn" title="Switch Camera">
                                                        <i class="fas fa-sync-alt"></i> Switch Camera
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- ID FRONT FILE UPLOAD MODE -->
                                        <div id="idUploadSection" style="display: none;">
                                            <div class="upload-dropzone" id="idDropzone">
                                                <i class="fas fa-cloud-upload-alt upload-main-icon"></i>
                                                <p>Click to select or Drag & Drop Front of Valid ID</p>
                                                <div class="sub-types">Supported formats: JPG, PNG, WEBP, PDF (Max 10MB)</div>
                                                <input type="file" name="valid_id" id="valid_id" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            </div>
                                            <!-- Uploaded File Display Card -->
                                            <div class="uploaded-file-card" id="idFileDisplayCard">
                                                <div class="uploaded-file-flex">
                                                    <img src="" id="idFilePreviewThumb" class="uploaded-thumb-preview" alt="ID Front Thumbnail">
                                                    <div class="uploaded-file-meta">
                                                        <p class="uploaded-file-name" id="idFileNameText">-</p>
                                                        <p class="uploaded-file-size" id="idFileSizeText">-</p>
                                                    </div>
                                                    <div class="uploaded-actions">
                                                        <button type="button" class="btn-file-zoom" id="idFileInspectBtn">
                                                            <i class="fas fa-search-plus"></i> Inspect
                                                        </button>
                                                        <button type="button" class="btn-file-remove" id="idFileRemoveBtn">
                                                            <i class="fas fa-trash-alt"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Live AI OCR Card Recognition HUD for Front -->
                                        <div id="idOcrResultCard" class="ocr-extracted-card" style="display: none;">
                                            <div class="ocr-card-header">
                                                <div class="ocr-badge-tag">
                                                    <i class="fas fa-magic"></i>
                                                    <span>AI ID Card OCR Scanner</span>
                                                </div>
                                                <span class="ocr-status-pill" id="idOcrStatusPill">
                                                    <i class="fas fa-spinner fa-spin"></i> Reading Card Details...
                                                </span>
                                            </div>
                                            <div class="ocr-data-preview-grid" id="idOcrDataGrid">
                                                <!-- Populated dynamically via JS -->
                                            </div>
                                            <div class="ocr-autofill-hint" id="idOcrHint">
                                                <i class="fas fa-check-circle"></i> ID details automatically detected and filled in your verification form.
                                            </div>
                                        </div>

                                        <div class="side-flow-navigation">
                                            <button type="button" class="btn-side-switch-nav" id="btnNextToBackSide">
                                                <span>Continue to Back of Card (Likod)</span>
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- ================= BACK SIDE PANE ================= -->
                                    <div id="sidePaneBack" class="id-side-pane" style="display: none;">
                                        <!-- Mode Tab Switcher: Camera Studio vs Upload for Back -->
                                        <div class="mode-tab-buttons">
                                            <button type="button" class="mode-tab-btn active" data-mode-tab="camera" data-target-step="id_back">
                                                <i class="fas fa-camera"></i> Live Camera Guide
                                            </button>
                                            <button type="button" class="mode-tab-btn" data-mode-tab="upload" data-target-step="id_back">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload Back File
                                            </button>
                                        </div>
                                        <!-- ID BACK CAMERA MODE STUDIO -->
                                        <div id="id_backCameraSection" class="camera-studio-container">
                                            <div class="camera-viewport-wrap" id="id_backCameraViewport">
                                                <video id="id_backVideo" autoplay playsinline muted></video>
                                                <!-- Live HUD Overlay -->
                                                <div class="camera-hud-overlay">
                                                    <div class="hud-status-banner state-danger" id="id_backHudStatus">
                                                        <span class="hud-status-dot"></span>
                                                        <span id="id_backHudStatusText">Align back of ID inside frame</span>
                                                    </div>
                                                    <div class="hud-metrics-row">
                                                        <div class="hud-metric-chip metric-bad" id="id_backMetricLighting">
                                                            <i class="fas fa-sun"></i> Lighting: Checking...
                                                        </div>
                                                        <div class="hud-metric-chip metric-bad" id="id_backMetricAlignment">
                                                            <i class="fas fa-expand-arrows-alt"></i> Distance: In Frame
                                                        </div>
                                                        <div class="hud-metric-chip metric-bad" id="id_backMetricClarity">
                                                            <i class="fas fa-magic"></i> Clarity: Sharp
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- ID Back Card Alignment Guide Frame -->
                                                <div class="id-guide-box" id="id_backGuideBox">
                                                    <div class="guide-corner corner-tl"></div>
                                                    <div class="guide-corner corner-tr"></div>
                                                    <div class="guide-corner corner-bl"></div>
                                                    <div class="guide-corner corner-br"></div>
                                                    <div class="scanline-beam"></div>
                                                    <div class="guide-inner-border"></div>
                                                    <div class="guide-card-hint">
                                                        <i class="fas fa-id-card-alt"></i>
                                                        <span>Position Back of ID Card</span>
                                                    </div>
                                                </div>
                                                <!-- Auto-Capture 3s Countdown Overlay -->
                                                <div class="countdown-overlay" id="id_backCountdownOverlay">
                                                    <div class="countdown-number" id="id_backCountdownNumber">3</div>
                                                </div>
                                                <!-- Snapshot Preview -->
                                                <div class="snapshot-preview-holder" id="id_backSnapshotHolder">
                                                    <img id="id_backSnapshotImg" src="" alt="Captured Back of ID">
                                                    <div class="snapshot-review-bar">
                                                        <button type="button" class="btn-retake" id="id_backRetakeBtn">
                                                            <i class="fas fa-redo"></i> Retake Back Photo
                                                        </button>
                                                        <button type="button" class="btn-preview-zoom" id="id_backZoomBtn">
                                                            <i class="fas fa-search-plus"></i> Inspect Full-Screen
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Studio Bottom Action Bar -->
                                            <div class="camera-studio-bar" id="id_backStudioBar">
                                                <div class="studio-options-left">
                                                    <label class="auto-capture-toggle">
                                                        <input type="checkbox" id="id_backAutoCaptureToggle" checked>
                                                        <span><i class="fas fa-bolt" style="color: var(--verif-gold);"></i> Auto-Capture on Alignment</span>
                                                    </label>
                                                </div>
                                                <div class="studio-shutter-center">
                                                    <button type="button" class="btn-shutter-main" id="id_backManualCaptureBtn" title="Capture Back Snapshot">
                                                        <i class="fas fa-camera"></i>
                                                    </button>
                                                </div>
                                                <div>
                                                    <button type="button" class="btn-camera-util" id="id_backSwitchCameraBtn" title="Switch Camera">
                                                        <i class="fas fa-sync-alt"></i> Switch Camera
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- ID BACK FILE UPLOAD MODE -->
                                        <div id="id_backUploadSection" style="display: none;">
                                            <div class="upload-dropzone" id="id_backDropzone">
                                                <i class="fas fa-cloud-upload-alt upload-main-icon"></i>
                                                <p>Click to select or Drag & Drop Back of Valid ID</p>
                                                <div class="sub-types">Supported formats: JPG, PNG, WEBP, PDF (Max 10MB)</div>
                                                <input type="file" name="valid_id_back" id="valid_id_back" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            </div>
                                            <!-- Uploaded File Display Card -->
                                            <div class="uploaded-file-card" id="id_backFileDisplayCard">
                                                <div class="uploaded-file-flex">
                                                    <img src="" id="id_backFilePreviewThumb" class="uploaded-thumb-preview" alt="ID Back Thumbnail">
                                                    <div class="uploaded-file-meta">
                                                        <p class="uploaded-file-name" id="id_backFileNameText">-</p>
                                                        <p class="uploaded-file-size" id="id_backFileSizeText">-</p>
                                                    </div>
                                                    <div class="uploaded-actions">
                                                        <button type="button" class="btn-file-zoom" id="id_backFileInspectBtn">
                                                            <i class="fas fa-search-plus"></i> Inspect
                                                        </button>
                                                        <button type="button" class="btn-file-remove" id="id_backFileRemoveBtn">
                                                            <i class="fas fa-trash-alt"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="side-flow-navigation">
                                            <button type="button" class="btn-side-switch-nav secondary" id="btnBackToFrontSide">
                                                <i class="fas fa-arrow-left"></i>
                                                <span>Return to Front Side</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- ================= STEP 3: SELFIE WITH ID ================= -->
                                <div class="step-panel-content" data-step-panel="3">
                                    <p style="font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 14px;">
                                        Please position your face within the oval guide and hold your ID card steadily next to your cheek.
                                    </p>
                                    <!-- Mode Tab Switcher: Camera Studio vs Upload -->
                                    <div class="mode-tab-buttons">
                                        <button type="button" class="mode-tab-btn active" data-mode-tab="camera" data-target-step="selfie">
                                            <i class="fas fa-camera"></i> Live Face Alignment
                                        </button>
                                        <button type="button" class="mode-tab-btn" data-mode-tab="upload" data-target-step="selfie">
                                            <i class="fas fa-cloud-upload-alt"></i> Upload Selfie File
                                        </button>
                                    </div>
                                    <!-- SELFIE CAMERA MODE STUDIO -->
                                    <div id="selfieCameraSection" class="camera-studio-container">
                                        <div class="camera-viewport-wrap" id="selfieCameraViewport">
                                            <video id="selfieVideo" autoplay playsinline muted style="transform: scaleX(-1);"></video>
                                            <canvas id="selfieMeshCanvas" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 4; transform: scaleX(-1);"></canvas>
                                            <!-- Live HUD Overlay -->
                                            <div class="camera-hud-overlay">
                                                <!-- Dynamic Real-time Status Banner -->
                                                <div class="hud-status-banner state-danger" id="selfieHudStatus">
                                                    <span class="hud-status-dot"></span>
                                                    <span id="selfieHudStatusText">Center face in oval and hold ID beside face</span>
                                                </div>
                                                <!-- 4 Real-time Metric Chips -->
                                                <div class="hud-metrics-row">
                                                    <div class="hud-metric-chip metric-bad" id="selfieMetricLighting">
                                                        <i class="fas fa-sun"></i> Lighting: Checking...
                                                    </div>
                                                    <div class="hud-metric-chip metric-bad" id="selfieMetricAlignment">
                                                        <i class="fas fa-user"></i> Face: Centering
                                                    </div>
                                                    <div class="hud-metric-chip metric-bad" id="selfieMetricIdCard">
                                                        <i class="fas fa-id-card"></i> ID: Hold in Guide Box
                                                    </div>
                                                    <div class="hud-metric-chip metric-bad" id="selfieMetricClarity">
                                                        <i class="fas fa-magic"></i> Clarity: Sharp
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Selfie Face & ID Alignment Guide -->
                                            <div class="selfie-guide-container">
                                                <div class="selfie-face-oval" id="selfieFaceOval"></div>
                                                <div class="selfie-id-box-guide" id="selfieIdBoxGuide"></div>
                                            </div>
                                            <!-- Auto-Capture 3s Countdown Overlay -->
                                            <div class="countdown-overlay" id="selfieCountdownOverlay">
                                                <div class="countdown-number" id="selfieCountdownNumber">3</div>
                                            </div>
                                            <!-- Snapshot Preview -->
                                            <div class="snapshot-preview-holder" id="selfieSnapshotHolder">
                                                <img id="selfieSnapshotImg" src="" alt="Captured Selfie with ID">
                                                <div class="snapshot-review-bar">
                                                    <button type="button" class="btn-retake" id="selfieRetakeBtn">
                                                        <i class="fas fa-redo"></i> Retake Selfie
                                                    </button>
                                                    <button type="button" class="btn-preview-zoom" id="selfieZoomBtn">
                                                        <i class="fas fa-search-plus"></i> Inspect Full-Screen
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Studio Bottom Action Bar -->
                                        <div class="camera-studio-bar" id="selfieStudioBar">
                                            <div class="studio-options-left">
                                                <label class="auto-capture-toggle">
                                                    <input type="checkbox" id="selfieAutoCaptureToggle" checked>
                                                    <span><i class="fas fa-bolt" style="color: var(--verif-gold);"></i> Auto-Capture on Alignment</span>
                                                </label>
                                            </div>
                                            <div class="studio-shutter-center">
                                                <button type="button" class="btn-shutter-main" id="selfieManualCaptureBtn" title="Take Selfie Snapshot">
                                                    <i class="fas fa-camera"></i>
                                                </button>
                                            </div>
                                            <div>
                                                <button type="button" class="btn-camera-util" id="selfieSwitchCameraBtn" title="Switch between front and back camera">
                                                    <i class="fas fa-sync-alt"></i> Switch Camera
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- SELFIE FILE UPLOAD MODE -->
                                    <div id="selfieUploadSection" style="display: none;">
                                        <div class="upload-dropzone" id="selfieDropzone">
                                            <i class="fas fa-portrait upload-main-icon"></i>
                                            <p>Click to select or Drag & Drop your Selfie Holding ID</p>
                                            <div class="sub-types">Supported formats: JPG, PNG, WEBP (Max 10MB)</div>
                                            <input type="file" name="selfie_file" id="selfie_file" accept=".jpg,.jpeg,.png,.webp">
                                        </div>
                                        <!-- Uploaded Selfie Display Card -->
                                        <div class="uploaded-file-card" id="selfieFileDisplayCard">
                                            <div class="uploaded-file-flex">
                                                <img src="" id="selfieFilePreviewThumb" class="uploaded-thumb-preview" alt="Selfie Thumbnail">
                                                <div class="uploaded-file-meta">
                                                    <p class="uploaded-file-name" id="selfieFileNameText">-</p>
                                                    <p class="uploaded-file-size" id="selfieFileSizeText">-</p>
                                                </div>
                                                <div class="uploaded-actions">
                                                    <button type="button" class="btn-file-zoom" id="selfieFileInspectBtn">
                                                        <i class="fas fa-search-plus"></i> Inspect
                                                    </button>
                                                    <button type="button" class="btn-file-remove" id="selfieFileRemoveBtn">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- ================= STEP 4: ADDRESS & CONFIRMATION ================= -->
                                <div class="step-panel-content" data-step-panel="4">
                                    <div class="form-field-group">
                                        <label><i class="fas fa-map-marker-alt"></i> Barangay in Palayan City <span style="color: var(--verif-crimson);">*</span></label>
                                        <select name="barangay" id="barangay" required>
                                            <option value="">-- Choose Barangay --</option>
                                            <?php
                                            $palayan_brgys_list = [
                                                'Atate', 'Aulo', 'Bagong Buhay', 'Baryo Militar', 'Caballero', 'Caimito',
                                                'Doña Josefa', 'Ganaderia', 'Imelda Valley I', 'Imelda Valley II', 'Malate',
                                                'Maligaya', 'Manacnac', 'Mapaet', 'Marcos Village', 'Popolon(Pagas)' => 'Popolon (Pagas)',
                                                'Santolan', 'Sapang Buho', 'Singalat'
                                            ];
                                            foreach ($palayan_brgys_list as $k => $v) {
                                                $val = is_numeric($k) ? $v : $k;
                                                $label = $v;
                                                $is_sel = (strcasecmp($default_barangay, $val) === 0 || strcasecmp($default_barangay, $label) === 0);
                                                echo '<option value="' . htmlspecialchars($val) . '" ' . ($is_sel ? 'selected' : '') . '>' . htmlspecialchars($label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-row-duo">
                                        <div class="form-field-group">
                                            <label><i class="fas fa-road"></i> Street / Block / Lot</label>
                                            <input type="text" name="street" id="street" value="<?php echo htmlspecialchars($default_street); ?>" placeholder="e.g. Purok 3, Mabini Street">
                                        </div>
                                        <div class="form-field-group">
                                            <label><i class="fas fa-city"></i> City / Municipality</label>
                                            <input type="text" name="city" value="Palayan City" readonly style="background: var(--badge-bg); cursor: not-allowed; opacity: 0.85;">
                                        </div>
                                    </div>
                                    <div class="form-row-duo">
                                        <div class="form-field-group">
                                            <label><i class="fas fa-map"></i> Province</label>
                                            <input type="text" name="province" value="Nueva Ecija" readonly style="background: var(--badge-bg); cursor: not-allowed; opacity: 0.85;">
                                        </div>
                                        <div class="form-field-group">
                                            <label><i class="fas fa-mail-bulk"></i> Postal Code</label>
                                            <input type="text" name="postal_code" value="3132" readonly style="background: var(--badge-bg); cursor: not-allowed; opacity: 0.85;">
                                        </div>
                                    </div>
                                    <!-- REAL-TIME SUMMARY PREVIEW BLOCK -->
                                    <div class="verif-summary-panel">
                                        <div class="summary-title-bar">
                                            <i class="fas fa-clipboard-check"></i> Verification Submission Review
                                        </div>
                                        <div class="summary-info-grid">
                                            <div class="sum-item">
                                                <span class="sum-label">ID Type</span>
                                                <span class="sum-val" id="sumIdType">-</span>
                                            </div>
                                            <div class="sum-item">
                                                <span class="sum-label">ID Number</span>
                                                <span class="sum-val" id="sumIdNumber">-</span>
                                            </div>
                                            <div class="sum-item">
                                                <span class="sum-label">Barangay</span>
                                                <span class="sum-val" id="sumBarangay">-</span>
                                            </div>
                                            <div class="sum-item">
                                                <span class="sum-label">Street Address</span>
                                                <span class="sum-val" id="sumStreet">-</span>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-top: 10px;">
                                            Attached Document Previews (Click to Zoom)
                                        </div>
                                        <div class="summary-photos-duo">
                                            <div class="sum-photo-box">
                                                <div class="sum-photo-top">
                                                    <span><i class="fas fa-id-card"></i> Front of ID</span>
                                                    <span id="sumBadgeIdMode">Captured</span>
                                                </div>
                                                <div class="sum-photo-thumb-wrap" id="sumWrapId">
                                                    <img id="sumImgId" src="" alt="Front of Valid ID">
                                                    <div class="overlay-zoom-hint"><i class="fas fa-search-plus"></i> Zoom</div>
                                                </div>
                                            </div>
                                            <div class="sum-photo-box">
                                                <div class="sum-photo-top">
                                                    <span><i class="fas fa-id-card-alt"></i> Back of ID</span>
                                                    <span id="sumBadgeIdBackMode">Captured</span>
                                                </div>
                                                <div class="sum-photo-thumb-wrap" id="sumWrapIdBack">
                                                    <img id="sumImgIdBack" src="" alt="Back of Valid ID">
                                                    <div class="overlay-zoom-hint"><i class="fas fa-search-plus"></i> Zoom</div>
                                                </div>
                                            </div>
                                            <div class="sum-photo-box">
                                                <div class="sum-photo-top">
                                                    <span><i class="fas fa-portrait"></i> Selfie with ID</span>
                                                    <span id="sumBadgeSelfieMode">Captured</span>
                                                </div>
                                                <div class="sum-photo-thumb-wrap" id="sumWrapSelfie">
                                                    <img id="sumImgSelfie" src="" alt="Selfie with ID">
                                                    <div class="overlay-zoom-hint"><i class="fas fa-search-plus"></i> Zoom</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- STEP NAVIGATION CONTROLS -->
                                <div class="step-footer-actions">
                                    <button type="button" class="btn-step-prev" id="prevBtn" style="display: none;">
                                        <i class="fas fa-chevron-left"></i> Previous Step
                                    </button>
                                    <div></div>
                                    <button type="button" class="btn-step-next pulse-emerald" id="nextBtn">
                                        Continue to Step 2 <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <button type="submit" name="submit_verification" class="btn-step-next pulse-emerald" id="submitBtn" style="display: none;">
                                        <i class="fas fa-paper-plane"></i> Submit Verification
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </main>
                </div>
            </div>
        </div>
    </div>
    <!-- ==============================================
         UNIVERSAL FULL-SCREEN LIGHTBOX MODAL
         ============================================== -->
    <div class="lightbox-modal" id="docLightboxModal">
        <div class="lightbox-topbar">
            <div class="lightbox-title">
                <i class="fas fa-id-card" style="color: var(--verif-gold);"></i>
                <span id="lightboxDocTitle">Document Inspection</span>
            </div>
            <div class="lightbox-controls">
                <button type="button" class="btn-lightbox-tool" id="lbZoomInBtn" title="Zoom In (+)"><i class="fas fa-search-plus"></i></button>
                <button type="button" class="btn-lightbox-tool" id="lbZoomOutBtn" title="Zoom Out (-)"><i class="fas fa-search-minus"></i></button>
                <button type="button" class="btn-lightbox-tool" id="lbRotateBtn" title="Rotate 90° Clockwise"><i class="fas fa-redo"></i></button>
                <button type="button" class="btn-lightbox-tool" id="lbResetBtn" title="Reset View"><i class="fas fa-undo"></i></button>
                <button type="button" class="btn-lightbox-tool" id="lbFullscreenBtn" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                <button type="button" class="btn-lightbox-tool btn-lightbox-close" id="lbCloseBtn" title="Close (Esc)"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="lightbox-stage" id="lightboxStage">
            <img id="lightboxImage" src="" alt="Document Preview">
        </div>
        <div class="lightbox-bottom-info">
            <span><i class="fas fa-mouse"></i> Use mouse wheel to zoom · Click and drag to pan</span>
            <span id="lightboxZoomLevelText">Zoom: 100%</span>
        </div>
    </div>
    <script>
    // ========================================================
    // LIGHTBOX CONTROLLER (ZOOM, PAN, ROTATE)
    // ========================================================
    const lightboxModal = document.getElementById('docLightboxModal');
    const lightboxImg = document.getElementById('lightboxImage');
    const lightboxTitle = document.getElementById('lightboxDocTitle');
    const lightboxZoomText = document.getElementById('lightboxZoomLevelText');
    const lightboxStage = document.getElementById('lightboxStage');
    let lbState = {
        scale: 1,
        rotation: 0,
        translateX: 0,
        translateY: 0,
        isDragging: false,
        startX: 0,
        startY: 0
    };
    function updateLightboxTransform() {
        if (!lightboxImg) return;
        lightboxImg.style.transform = `translate(${lbState.translateX}px, ${lbState.translateY}px) scale(${lbState.scale}) rotate(${lbState.rotation}deg)`;
        if (lightboxZoomText) {
            lightboxZoomText.textContent = `Zoom: ${Math.round(lbState.scale * 100)}% · Angle: ${lbState.rotation}°`;
        }
    }
    function openLightbox(src, title = 'Document Inspection') {
        if (!lightboxModal || !lightboxImg || !src) return;
        lightboxImg.src = src;
        if (lightboxTitle) lightboxTitle.textContent = title;
        lbState = { scale: 1, rotation: 0, translateX: 0, translateY: 0, isDragging: false, startX: 0, startY: 0 };
        updateLightboxTransform();
        lightboxModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        if (!lightboxModal) return;
        lightboxModal.classList.remove('active');
        document.body.style.overflow = '';
        if (lightboxImg) lightboxImg.src = '';
    }
    if (document.getElementById('lbCloseBtn')) {
        document.getElementById('lbCloseBtn').addEventListener('click', closeLightbox);
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightboxModal && lightboxModal.classList.contains('active')) {
            closeLightbox();
        }
    });
    if (document.getElementById('lbZoomInBtn')) {
        document.getElementById('lbZoomInBtn').addEventListener('click', () => {
            lbState.scale = Math.min(lbState.scale + 0.25, 4.0);
            updateLightboxTransform();
        });
    }
    if (document.getElementById('lbZoomOutBtn')) {
        document.getElementById('lbZoomOutBtn').addEventListener('click', () => {
            lbState.scale = Math.max(lbState.scale - 0.25, 0.4);
            updateLightboxTransform();
        });
    }
    if (document.getElementById('lbRotateBtn')) {
        document.getElementById('lbRotateBtn').addEventListener('click', () => {
            lbState.rotation = (lbState.rotation + 90) % 360;
            updateLightboxTransform();
        });
    }
    if (document.getElementById('lbResetBtn')) {
        document.getElementById('lbResetBtn').addEventListener('click', () => {
            lbState.scale = 1;
            lbState.rotation = 0;
            lbState.translateX = 0;
            lbState.translateY = 0;
            updateLightboxTransform();
        });
    }
    if (document.getElementById('lbFullscreenBtn')) {
        document.getElementById('lbFullscreenBtn').addEventListener('click', () => {
            if (!document.fullscreenElement) {
                lightboxModal.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        });
    }
    // Lightbox Mousewheel Zoom
    if (lightboxStage) {
        lightboxStage.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.15 : 0.15;
            lbState.scale = Math.min(Math.max(lbState.scale + delta, 0.4), 4.0);
            updateLightboxTransform();
        }, { passive: false });
        // Pan with mouse drag
        lightboxStage.addEventListener('mousedown', (e) => {
            if (e.target === lightboxImg || e.target === lightboxStage) {
                lbState.isDragging = true;
                lbState.startX = e.clientX - lbState.translateX;
                lbState.startY = e.clientY - lbState.translateY;
            }
        });
        window.addEventListener('mousemove', (e) => {
            if (lbState.isDragging) {
                lbState.translateX = e.clientX - lbState.startX;
                lbState.translateY = e.clientY - lbState.startY;
                updateLightboxTransform();
            }
        });
        window.addEventListener('mouseup', () => {
            lbState.isDragging = false;
        });
    }
    // ========================================================
    // RESIDENCY VERIFICATION WIZARD & CAMERA ALIGNMENT ENGINE
    // ========================================================
    const verifyForm = document.getElementById('verifyForm');
    if (verifyForm) {
        let currentStep = 1;
        const totalSteps = 4;
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        const submitBtn = document.getElementById('submitBtn');
        const stepAlert = document.getElementById('stepAlert');
        const stepAlertText = document.getElementById('stepAlertText');
        const wizardHeaderTitle = document.getElementById('wizardHeaderTitle');
        const wizardHeaderSubtitle = document.getElementById('wizardHeaderSubtitle');
        const stepPillBadge = document.getElementById('stepPillBadge');
        // Current input modes: 'camera' or 'upload'
        const currentModes = {
            id: 'camera',
            id_back: 'camera',
            selfie: 'camera'
        };
        // Cache for file data URLs to ensure previews NEVER disappear
        const fileDataUrls = {
            id: null,
            id_back: null,
            selfie: null
        };
        let currentIdSide = 'front'; // 'front' or 'back'

        function switchIdSide(side) {
            currentIdSide = side;
            const paneFront = document.getElementById('sidePaneFront');
            const paneBack = document.getElementById('sidePaneBack');
            const pillFront = document.getElementById('pillFrontSide');
            const pillBack = document.getElementById('pillBackSide');

            if (side === 'front') {
                if (paneFront) paneFront.style.display = 'block';
                if (paneBack) paneBack.style.display = 'none';
                if (pillFront) pillFront.classList.add('active');
                if (pillBack) pillBack.classList.remove('active');
                stopCamera('id_back');
                if (currentModes.id === 'camera' && !document.getElementById('captured_id_image')?.value) {
                    startCamera('id');
                }
            } else {
                if (paneFront) paneFront.style.display = 'none';
                if (paneBack) paneBack.style.display = 'block';
                if (pillFront) pillFront.classList.remove('active');
                if (pillBack) pillBack.classList.add('active');
                stopCamera('id');
                if (currentModes.id_back === 'camera' && !document.getElementById('captured_id_back_image')?.value) {
                    startCamera('id_back');
                }
            }
        }

        document.getElementById('pillFrontSide')?.addEventListener('click', () => switchIdSide('front'));
        document.getElementById('pillBackSide')?.addEventListener('click', () => switchIdSide('back'));
        document.getElementById('btnNextToBackSide')?.addEventListener('click', () => switchIdSide('back'));
        document.getElementById('btnBackToFrontSide')?.addEventListener('click', () => switchIdSide('front'));
        function showAlert(msg) {
            if (stepAlert && stepAlertText) {
                stepAlertText.textContent = msg;
                stepAlert.style.display = 'flex';
                stepAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        function hideAlert() {
            if (stepAlert) stepAlert.style.display = 'none';
        }
        const stepMeta = {
            1: { title: "Step 1: ID Information", subtitle: "Select your government-issued ID type and ID number" },
            2: { title: "Step 2: Proof of ID Document", subtitle: "Position your valid ID inside the alignment frame or upload a photo" },
            3: { title: "Step 3: Selfie with ID", subtitle: "Center your face in the oval guide and hold your ID beside your face" },
            4: { title: "Step 4: Residential Address & Review", subtitle: "Select your Palayan City Barangay and confirm details before submitting" }
        };
        function updateWizardUI() {
            hideAlert();
            // 1. Update header titles & step indicator
            if (stepMeta[currentStep]) {
                if (wizardHeaderTitle) wizardHeaderTitle.textContent = stepMeta[currentStep].title;
                if (wizardHeaderSubtitle) wizardHeaderSubtitle.textContent = stepMeta[currentStep].subtitle;
                if (stepPillBadge) stepPillBadge.innerHTML = `<i class="fas fa-dot-circle"></i> Step ${currentStep} of ${totalSteps}`;
            }
            // 2. Update Left Sidebar stepper indicators
            document.querySelectorAll('.side-step-item').forEach(item => {
                const s = parseInt(item.getAttribute('data-side-step'), 10);
                item.classList.remove('active', 'completed');
                if (s === currentStep) {
                    item.classList.add('active');
                } else if (s < currentStep) {
                    item.classList.add('completed');
                }
            });
            // 3. Switch active content panel
            document.querySelectorAll('.step-panel-content').forEach(p => {
                p.classList.remove('active');
            });
            const activePanel = document.querySelector(`.step-panel-content[data-step-panel="${currentStep}"]`);
            if (activePanel) activePanel.classList.add('active');
            // 4. Control buttons display
            if (currentStep === 1) {
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'inline-flex';
                nextBtn.innerHTML = 'Continue to Step 2 <i class="fas fa-chevron-right"></i>';
                submitBtn.style.display = 'none';
                stopAllCameras();
            } else if (currentStep === 2) {
                prevBtn.style.display = 'inline-flex';
                nextBtn.style.display = 'inline-flex';
                nextBtn.innerHTML = 'Continue to Selfie <i class="fas fa-chevron-right"></i>';
                submitBtn.style.display = 'none';
                stopCamera('selfie');
                if (currentIdSide === 'front') {
                    if (currentModes.id === 'camera' && !document.getElementById('captured_id_image')?.value) {
                        startCamera('id');
                    }
                } else {
                    if (currentModes.id_back === 'camera' && !document.getElementById('captured_id_back_image')?.value) {
                        startCamera('id_back');
                    }
                }
            } else if (currentStep === 3) {
                prevBtn.style.display = 'inline-flex';
                nextBtn.style.display = 'inline-flex';
                nextBtn.innerHTML = 'Review & Address <i class="fas fa-chevron-right"></i>';
                submitBtn.style.display = 'none';
                stopCamera('id');
                stopCamera('id_back');
                if (currentModes.selfie === 'camera' && !document.getElementById('captured_selfie_image')?.value) {
                    startCamera('selfie');
                }
            } else if (currentStep === 4) {
                prevBtn.style.display = 'inline-flex';
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-flex';
                stopAllCameras();
                buildSummary();
            }
        }
        // Validate before moving to next step
        function validateStep() {
            if (currentStep === 1) {
                const idType = document.getElementById('id_type').value;
                const idNum = document.getElementById('id_number').value.trim();
                if (!idType) { showAlert("Please select your Valid ID Type."); return false; }
                if (!idNum) { showAlert("Please enter your ID Number."); return false; }
                return true;
            }
            if (currentStep === 2) {
                // Check Front ID
                let hasFront = false;
                if (currentModes.id === 'upload') {
                    const idFile = document.getElementById('valid_id');
                    hasFront = (idFile && idFile.files && idFile.files.length > 0) || Boolean(fileDataUrls.id);
                } else {
                    hasFront = Boolean(document.getElementById('captured_id_image')?.value);
                }
                if (!hasFront) {
                    switchIdSide('front');
                    showAlert("Please capture or upload the FRONT side (Harap) of your Valid ID.");
                    return false;
                }

                // Check Back ID
                let hasBack = false;
                if (currentModes.id_back === 'upload') {
                    const idBackFile = document.getElementById('valid_id_back');
                    hasBack = (idBackFile && idBackFile.files && idBackFile.files.length > 0) || Boolean(fileDataUrls.id_back);
                } else {
                    hasBack = Boolean(document.getElementById('captured_id_back_image')?.value);
                }
                if (!hasBack) {
                    switchIdSide('back');
                    showAlert("Please capture or upload the BACK side (Likod) of your Valid ID.");
                    return false;
                }
                return true;
            }
            if (currentStep === 3) {
                if (currentModes.selfie === 'upload') {
                    const sFile = document.getElementById('selfie_file');
                    if (!sFile.files || sFile.files.length === 0) {
                        showAlert("Please select or drop your Selfie file before proceeding.");
                        return false;
                    }
                } else {
                    const capSelfie = document.getElementById('captured_selfie_image').value;
                    if (!capSelfie) {
                        showAlert("Please take your Selfie with the ID before proceeding.");
                        return false;
                    }
                }
                return true;
            }
            return true;
        }
        nextBtn.addEventListener('click', () => {
            if (validateStep()) {
                currentStep++;
                updateWizardUI();
            }
        });
        prevBtn.addEventListener('click', () => {
            currentStep--;
            updateWizardUI();
        });
        // ========================================================
        // TAB MODE TOGGLES (CAMERA VS UPLOAD)
        // ========================================================
        document.querySelectorAll('.mode-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.getAttribute('data-target-step'); // 'id' or 'selfie'
                const mode = this.getAttribute('data-mode-tab'); // 'camera' or 'upload'
                this.parentElement.querySelectorAll('.mode-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentModes[target] = mode;
                hideAlert();
                const camSection = document.getElementById(`${target}CameraSection`);
                const upSection = document.getElementById(`${target}UploadSection`);
                if (mode === 'camera') {
                    camSection.style.display = 'block';
                    upSection.style.display = 'none';
                    if (!document.getElementById(`captured_${target}_image`).value) {
                        startCamera(target);
                    }
                } else {
                    camSection.style.display = 'none';
                    upSection.style.display = 'block';
                    stopCamera(target);
                }
            });
        });
        // ========================================================
        // FILE UPLOADER & INSTANT FILE-READER PREVIEWS
        // ========================================================
        function setupFileUploadHandler(fileInputId, dropzoneId, displayCardId, thumbId, nameId, sizeId, targetStep) {
            const input = document.getElementById(fileInputId);
            const dropzone = document.getElementById(dropzoneId);
            const card = document.getElementById(displayCardId);
            const thumb = document.getElementById(thumbId);
            const nameEl = document.getElementById(nameId);
            const sizeEl = document.getElementById(sizeId);
            const removeBtn = document.getElementById(`${targetStep}FileRemoveBtn`);
            const inspectBtn = document.getElementById(`${targetStep}FileInspectBtn`);
            function handleFile(file) {
                if (!file) return;
                if (file.size > 10 * 1024 * 1024) {
                    showAlert("File size exceeds 10MB limit. Please upload a smaller image.");
                    input.value = '';
                    return;
                }
                nameEl.textContent = file.name;
                sizeEl.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB · ${file.type || 'Document'}`;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const dataUrl = e.target.result;
                        fileDataUrls[targetStep] = dataUrl;
                        thumb.src = dataUrl;
                        card.style.display = 'block';
                        hideAlert();
                        if (targetStep === 'id') {
                            document.getElementById('pillFrontSide')?.classList.add('is-captured');
                            const st = document.getElementById('pillStatusFront');
                            if (st) st.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Front Ready';
                            runIdCardOcr(dataUrl);
                        } else if (targetStep === 'id_back') {
                            document.getElementById('pillBackSide')?.classList.add('is-captured');
                            const st = document.getElementById('pillStatusBack');
                            if (st) st.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Back Ready';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    // PDF or non-image
                    fileDataUrls[targetStep] = null;
                    thumb.src = 'images/palayan-city.png';
                    card.style.display = 'block';
                    hideAlert();
                }
            }
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    handleFile(this.files[0]);
                }
            });
            // Drag and drop events
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                });
            });
            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    input.files = e.dataTransfer.files;
                    handleFile(e.dataTransfer.files[0]);
                }
            });
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    input.value = '';
                    fileDataUrls[targetStep] = null;
                    thumb.src = '';
                    card.style.display = 'none';
                    if (targetStep === 'id') {
                        const ocrCard = document.getElementById('idOcrResultCard');
                        if (ocrCard) ocrCard.style.display = 'none';
                    }
                });
            }
            if (inspectBtn) {
                inspectBtn.addEventListener('click', () => {
                    if (fileDataUrls[targetStep]) {
                        openLightbox(fileDataUrls[targetStep], `${targetStep.toUpperCase()} Uploaded Document`);
                    }
                });
            }
            if (thumb) {
                thumb.addEventListener('click', () => {
                    if (fileDataUrls[targetStep]) {
                        openLightbox(fileDataUrls[targetStep], `${targetStep.toUpperCase()} Uploaded Document`);
                    }
                });
            }
        }
        setupFileUploadHandler('valid_id', 'idDropzone', 'idFileDisplayCard', 'idFilePreviewThumb', 'idFileNameText', 'idFileSizeText', 'id');
        setupFileUploadHandler('valid_id_back', 'id_backDropzone', 'id_backFileDisplayCard', 'id_backFilePreviewThumb', 'id_backFileNameText', 'id_backFileSizeText', 'id_back');
        setupFileUploadHandler('selfie_file', 'selfieDropzone', 'selfieFileDisplayCard', 'selfieFilePreviewThumb', 'selfieFileNameText', 'selfieFileSizeText', 'selfie');
        // ========================================================
        // LIVE CAMERA ALIGNMENT & COMPUTER VISION QUALITY ENGINE
        // ========================================================
        const cameraStreams = { id: null, id_back: null, selfie: null };
        const facingModes = { id: 'environment', id_back: 'environment', selfie: 'user' };
        let cvAnimFrames = { id: null, id_back: null, selfie: null };
        let autoCaptureTimers = { id: 0, id_back: 0, selfie: 0 };
        let autoCountdownIntervals = { id: null, id_back: null, selfie: null };
        const cvCanvas = document.getElementById('cvAnalysisCanvas');
        const cvCtx = cvCanvas ? cvCanvas.getContext('2d', { willReadFrequently: true }) : null;
        function startCamera(target) {
            const video = document.getElementById(`${target}Video`);
            if (!video) return;
            // Stop existing if any
            stopCamera(target);
            const constraints = {
                video: {
                    facingMode: facingModes[target],
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    cameraStreams[target] = stream;
                    video.srcObject = stream;
                    video.style.display = 'block';
                    if (target === 'selfie') {
                        video.style.transform = (facingModes.selfie === 'user') ? 'scaleX(-1)' : 'none';
                    }
                    // Hide snapshot review if was open
                    const holder = document.getElementById(`${target}SnapshotHolder`);
                    if (holder) holder.style.display = 'none';
                    video.onloadedmetadata = () => {
                        video.play();
                        startCVLoop(target);
                    };
                    hideAlert();
                })
                .catch(err => {
                    console.error("Camera error for " + target, err);
                    showAlert("Camera permission denied or camera device not found. You can switch to 'Upload File' mode above.");
                    // Switch to upload tab automatically
                    const uploadBtn = document.querySelector(`.mode-tab-btn[data-mode-tab="upload"][data-target-step="${target}"]`);
                    if (uploadBtn) uploadBtn.click();
                });
        }
        function stopCamera(target) {
            if (cvAnimFrames[target]) {
                cancelAnimationFrame(cvAnimFrames[target]);
                cvAnimFrames[target] = null;
            }
            if (autoCountdownIntervals[target]) {
                clearInterval(autoCountdownIntervals[target]);
                autoCountdownIntervals[target] = null;
                const cdOverlay = document.getElementById(`${target}CountdownOverlay`);
                if (cdOverlay) cdOverlay.style.display = 'none';
            }
            autoCaptureTimers[target] = 0;
            document.getElementById(`${target}ManualCaptureBtn`)?.classList.remove('state-ready-pulse');
            if (cameraStreams[target]) {
                cameraStreams[target].getTracks().forEach(t => t.stop());
                cameraStreams[target] = null;
            }
            const video = document.getElementById(`${target}Video`);
            if (video) video.srcObject = null;
            if (target === 'selfie' && pkhMeshCanvas && pkhMeshCtx) {
                pkhMeshCtx.clearRect(0, 0, pkhMeshCanvas.width, pkhMeshCanvas.height);
            }
        }
        function stopAllCameras() {
            stopCamera('id');
            stopCamera('selfie');
        }
        // Switch camera front/back
        document.getElementById('idSwitchCameraBtn')?.addEventListener('click', () => {
            facingModes.id = facingModes.id === 'environment' ? 'user' : 'environment';
            startCamera('id');
        });
        document.getElementById('selfieSwitchCameraBtn')?.addEventListener('click', () => {
            facingModes.selfie = facingModes.selfie === 'user' ? 'environment' : 'user';
            startCamera('selfie');
        });
        // =========================================================
        // GOOGLE MEDIAPIPE LIVE FACE ALIGNMENT & 3D MESH ENGINE
        // =========================================================
        let pkhFaceMesh = null;
        let pkhFaceMeshReady = false;
        let pkhLastMeshResults = null;
        let pkhIsProcessingMesh = false;
        let pkhMeshCanvas = null;
        let pkhMeshCtx = null;
        function initGoogleMediaPipe() {
            if (typeof FaceMesh === 'undefined') {
                let retries = 0;
                // Attempt loading from fallback CDN (unpkg) if jsdelivr is throttled or delayed
                if (!document.getElementById('mediapipe-unpkg-fallback')) {
                    const fallbackScript = document.createElement('script');
                    fallbackScript.id = 'mediapipe-unpkg-fallback';
                    fallbackScript.src = 'https://unpkg.com/@mediapipe/face_mesh@0.4.1633559619/face_mesh.js';
                    fallbackScript.crossOrigin = 'anonymous';
                    fallbackScript.onload = () => { initGoogleMediaPipe(); };
                    document.head.appendChild(fallbackScript);
                }
                const pollTimer = setInterval(() => {
                    retries++;
                    if (typeof FaceMesh !== 'undefined') {
                        clearInterval(pollTimer);
                        initGoogleMediaPipe();
                    } else if (retries > 20) {
                        clearInterval(pollTimer);
                        console.log('MediaPipe CDN took longer to load; intelligent fallback canvas CV active.');
                    }
                }, 200);
                return;
            }
            if (pkhFaceMeshReady && pkhFaceMesh) return;
            try {
                pkhFaceMesh = new FaceMesh({
                    locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh@0.4.1633559619/${file}`
                });
                pkhFaceMesh.setOptions({
                    maxNumFaces: 1,
                    refineLandmarks: false,
                    minDetectionConfidence: 0.5,
                    minTrackingConfidence: 0.5
                });
                pkhFaceMesh.onResults((results) => {
                    pkhLastMeshResults = results;
                });
                if (typeof pkhFaceMesh.initialize === 'function') {
                    pkhFaceMesh.initialize().then(() => {
                        pkhFaceMeshReady = true;
                        console.log('✓ Google MediaPipe 3D Face Alignment Engine initialized successfully!');
                    }).catch(err => {
                        console.warn('Google MediaPipe initialize error (using fallback detector):', err);
                        pkhFaceMeshReady = false;
                    });
                } else {
                    pkhFaceMeshReady = true;
                    console.log('✓ Google MediaPipe 3D Face Alignment Engine initialized successfully!');
                }
            } catch (err) {
                console.warn('Google MediaPipe initialization error (using fallback detector):', err);
                pkhFaceMeshReady = false;
            }
        }
        // Initialize on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGoogleMediaPipe);
        } else {
            initGoogleMediaPipe();
        }
        // Real-time Computer Vision Loop (Enhanced with Google MediaPipe)
        function startCVLoop(target) {
            const video = document.getElementById(`${target}Video`);
            if (!video || !cvCtx) return;
            const hudStatus = document.getElementById(`${target}HudStatus`);
            const hudText = document.getElementById(`${target}HudStatusText`);
            const metricLight = document.getElementById(`${target}MetricLighting`);
            const metricAlign = document.getElementById(`${target}MetricAlignment`);
            const metricClarity = document.getElementById(`${target}MetricClarity`);
            const metricIdCard = document.getElementById('selfieMetricIdCard');
            const guideBox = document.getElementById(target === 'id' ? 'idGuideBox' : 'selfieFaceOval');
            const autoToggle = document.getElementById(`${target}AutoCaptureToggle`);
            const shutterBtn = document.getElementById(target === 'id' ? 'idManualCaptureBtn' : 'selfieManualCaptureBtn');
            function analyzeFrame() {
                if (!video.videoWidth || !video.videoHeight || video.paused || video.ended) {
                    cvAnimFrames[target] = requestAnimationFrame(analyzeFrame);
                    return;
                }
                cvCtx.save();
                if (target === 'selfie' && facingModes.selfie === 'user') {
                    cvCtx.translate(160, 0);
                    cvCtx.scale(-1, 1);
                }
                cvCtx.drawImage(video, 0, 0, 160, 120);
                cvCtx.restore();
                const frameData = cvCtx.getImageData(0, 0, 160, 120);
                const d = frameData.data;
                if (target === 'id') {
                    // ===================================================
                    // SPECIALIZED CLOSE-UP ID CENTERING & ALIGNMENT ENGINE
                    // ===================================================
                    const vRect = video.getBoundingClientRect();
                    const gRect = guideBox ? guideBox.getBoundingClientRect() : null;
                    // Compute dynamic guide box coordinates on 160x120 analysis canvas
                    let boxLeft = 24, boxRight = 136, boxTop = 25, boxBottom = 95;
                    if (gRect && vRect.width > 0 && vRect.height > 0) {
                        boxLeft = Math.max(4, Math.floor(((gRect.left - vRect.left) / vRect.width) * 160));
                        boxRight = Math.min(156, Math.ceil(((gRect.right - vRect.left) / vRect.width) * 160));
                        boxTop = Math.max(4, Math.floor(((gRect.top - vRect.top) / vRect.height) * 120));
                        boxBottom = Math.min(116, Math.ceil(((gRect.bottom - vRect.top) / vRect.height) * 120));
                    }
                    const boxMidX = (boxLeft + boxRight) / 2;
                    const boxMidY = (boxTop + boxBottom) / 2;
                    const boxHalfW = Math.max(10, (boxRight - boxLeft) / 2);
                    const boxHalfH = Math.max(10, (boxBottom - boxTop) / 2);
                    let cardTotalLum = 0;
                    let cardPixelCount = 0;
                    let cardMinLum = 255;
                    let cardMaxLum = 0;
                    let overexposedCount = 0;
                    let edgeTransitions = 0;
                    let sharpTextEdges = 0;
                    let totalGradientSum = 0;
                    let sumEdgeX = 0;
                    let sumEdgeY = 0;
                    const quadEdges = [0, 0, 0, 0]; // 0: TL, 1: TR, 2: BL, 3: BR
                    // Sample pixels across the dynamic ID card guide frame
                    for (let y = boxTop; y < boxBottom; y += 2) {
                        const isTop = (y < boxMidY);
                        for (let x = boxLeft; x < boxRight; x += 2) {
                            const isLeft = (x < boxMidX);
                            const quadIdx = (isTop ? 0 : 2) + (isLeft ? 0 : 1);
                            const idx = (y * 160 + x) * 4;
                            const r = d[idx], g = d[idx + 1], b = d[idx + 2];
                            const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                            cardTotalLum += lum;
                            cardPixelCount++;
                            if (lum < cardMinLum) cardMinLum = lum;
                            if (lum > cardMaxLum) cardMaxLum = lum;
                            if (lum > 248) overexposedCount++;
                            // Compute directional local gradient
                            const rIdx = (y * 160 + (x + 1)) * 4;
                            const dIdx = ((y + 1) * 160 + x) * 4;
                            const rLum = 0.299 * d[rIdx] + 0.587 * d[rIdx + 1] + 0.114 * d[rIdx + 2];
                            const dLum = 0.299 * d[dIdx] + 0.587 * d[dIdx + 1] + 0.114 * d[dIdx + 2];
                            const grad = Math.abs(lum - rLum) + Math.abs(lum - dLum);
                            totalGradientSum += grad;
                            if (grad >= 14) {
                                edgeTransitions++;
                                quadEdges[quadIdx]++;
                                sumEdgeX += x;
                                sumEdgeY += y;
                            }
                            if (grad >= 22) {
                                sharpTextEdges++;
                            }
                        }
                    }
                    const avgCardLum = cardPixelCount > 0 ? (cardTotalLum / cardPixelCount) : 100;
                    const cardContrast = cardMaxLum - cardMinLum;
                    const avgGrad = cardPixelCount > 0 ? (totalGradientSum / cardPixelCount) : 0;
                    // Real ID card presence requires substantial printed edges distributed across all 4 quadrants
                    const minQuadEdges = Math.max(16, Math.floor(edgeTransitions * 0.10));
                    const activeQuads = quadEdges.filter(c => c >= minQuadEdges).length;
                    const isCardPresent = (edgeTransitions >= 120) && (sharpTextEdges >= 28) && (activeQuads >= 4) && (cardContrast >= 52);
                    let distState = 'none';
                    let distText = 'No ID Detected';
                    let idGuidance = 'Position your Valid ID inside the guide frame';
                    let lightState = 'good';
                    let lightText = 'Lighting: Good';
                    let clarityState = 'good';
                    let clarityText = 'Clarity: Sharp & Clear';
                    if (!isCardPresent) {
                        distState = 'none';
                        distText = 'No ID Detected';
                        idGuidance = 'Position your Valid ID inside the guide frame';
                        lightState = 'neutral';
                        lightText = 'Align ID Card';
                        clarityState = 'neutral';
                        clarityText = 'Waiting...';
                    } else {
                        // ID card is present - verify that the card centroid is strictly centered in the frame
                        const centroidX = edgeTransitions > 0 ? (sumEdgeX / edgeTransitions) : boxMidX;
                        const centroidY = edgeTransitions > 0 ? (sumEdgeY / edgeTransitions) : boxMidY;
                        const normOffsetX = (centroidX - boxMidX) / boxHalfW;
                        const normOffsetY = (centroidY - boxMidY) / boxHalfH;
                        // Check horizontal and vertical centering
                        if (normOffsetX < -0.18) {
                            distState = 'warn';
                            distText = 'Move ID Right';
                            idGuidance = 'Shift ID card to the right to center it';
                        } else if (normOffsetX > 0.18) {
                            distState = 'warn';
                            distText = 'Move ID Left';
                            idGuidance = 'Shift ID card to the left to center it';
                        } else if (normOffsetY < -0.18) {
                            distState = 'warn';
                            distText = 'Lower ID Card';
                            idGuidance = 'Lower ID card into the center frame';
                        } else if (normOffsetY > 0.18) {
                            distState = 'warn';
                            distText = 'Raise ID Card';
                            idGuidance = 'Raise ID card into the center frame';
                        } else if (edgeTransitions < 160 || sharpTextEdges < 38) {
                            distState = 'warn';
                            distText = 'Move ID Closer';
                            idGuidance = 'Move ID closer so it fills the guide frame';
                        } else {
                            distState = 'good';
                            distText = 'ID Centered ✓';
                            idGuidance = '✓ ID Centered & Sharp — Ready to Capture!';
                        }
                        // Check lighting
                        if (avgCardLum < 36) {
                            lightState = 'bad';
                            lightText = 'Lighting: Too Dim';
                        } else if (overexposedCount > (cardPixelCount * 0.28)) {
                            lightState = 'warn';
                            lightText = 'Lighting: Glare / Tilt ID';
                        } else {
                            lightState = 'good';
                            lightText = 'Lighting: Good';
                        }
                        // Check clarity
                        if (sharpTextEdges < 28 || avgGrad < 3.8) {
                            clarityState = 'warn';
                            clarityText = 'Clarity: Hold Steady';
                        } else {
                            clarityState = 'good';
                            clarityText = 'Clarity: Sharp & Clear';
                        }
                    }
                    // Update UI Metric Chips
                    if (metricLight) {
                        metricLight.className = `hud-metric-chip ${lightState === 'good' ? 'metric-good' : (lightState === 'warn' ? 'metric-warn' : (lightState === 'bad' ? 'metric-bad' : ''))}`;
                        metricLight.innerHTML = `<i class="fas fa-sun"></i> ${lightText}`;
                    }
                    if (metricAlign) {
                        metricAlign.className = `hud-metric-chip ${distState === 'good' ? 'metric-good' : (distState === 'warn' ? 'metric-warn' : 'metric-bad')}`;
                        metricAlign.innerHTML = `<i class="fas fa-id-card"></i> ${distText}`;
                    }
                    if (metricClarity) {
                        metricClarity.className = `hud-metric-chip ${clarityState === 'good' ? 'metric-good' : (clarityState === 'warn' ? 'metric-warn' : '')}`;
                        metricClarity.innerHTML = `<i class="fas fa-magic"></i> ${clarityText}`;
                    }
                    // Determine overall readiness: MUST be centered in frame, good lighting, and sharp clarity!
                    const isReady = (distState === 'good') && (lightState === 'good') && (clarityState === 'good');
                    if (isReady) {
                        if (hudStatus) hudStatus.className = 'hud-status-banner state-ready';
                        if (hudText) hudText.textContent = '✓ ID In Frame & Sharp — Ready to Capture!';
                        if (guideBox) guideBox.className = 'id-guide-box state-ready';
                        if (shutterBtn) shutterBtn.classList.add('state-ready-pulse');
                        // Auto-capture countdown logic (requires ~1s of continuous steady alignment)
                        if (autoToggle && autoToggle.checked) {
                            autoCaptureTimers[target] += 1;
                            if (autoCaptureTimers[target] >= 34 && !autoCountdownIntervals[target]) {
                                triggerCountdownCapture(target);
                            }
                        }
                    } else {
                        autoCaptureTimers[target] = 0;
            document.getElementById(`${target}ManualCaptureBtn`)?.classList.remove('state-ready-pulse');
                        if (autoCountdownIntervals[target]) {
                            clearInterval(autoCountdownIntervals[target]);
                            autoCountdownIntervals[target] = null;
                            const cd = document.getElementById(`${target}CountdownOverlay`);
                            if (cd) cd.style.display = 'none';
                        }
                        if (shutterBtn) shutterBtn.classList.remove('state-ready-pulse');
                        if (distState === 'none') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Position your Valid ID inside the frame';
                            if (guideBox) guideBox.className = 'id-guide-box state-warning';
                        } else if (distState === 'warn') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Move ID closer to fill the guide frame';
                            if (guideBox) guideBox.className = 'id-guide-box state-warning';
                        } else if (lightState === 'bad') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-danger';
                            if (hudText) hudText.textContent = 'Lighting too dim — Move to brighter area';
                            if (guideBox) guideBox.className = 'id-guide-box state-danger';
                        } else if (lightState === 'warn') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Tilt ID slightly to avoid flash reflection';
                            if (guideBox) guideBox.className = 'id-guide-box state-warning';
                        } else if (clarityState !== 'good') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Hold steady — Camera focusing';
                            if (guideBox) guideBox.className = 'id-guide-box state-warning';
                        } else {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Position ID inside the frame';
                            if (guideBox) guideBox.className = 'id-guide-box state-warning';
                        }
                    }
                } else {
                    // ===================================================
                    // GOOGLE MEDIAPIPE REAL-TIME 3D FACE ALIGNMENT ENGINE
                    // ===================================================
                    const videoRect = video.getBoundingClientRect();
                    const ovalEl = document.getElementById('selfieFaceOval');
                    const idBoxEl = document.getElementById('selfieIdBoxGuide');
                    pkhMeshCanvas = document.getElementById('selfieMeshCanvas');
                    if (pkhMeshCanvas && !pkhMeshCtx) {
                        pkhMeshCtx = pkhMeshCanvas.getContext('2d');
                    }
                    // Synchronize mesh canvas resolution to video stream
                    if (pkhMeshCanvas && video.videoWidth > 0 && video.videoHeight > 0) {
                        if (pkhMeshCanvas.width !== video.videoWidth || pkhMeshCanvas.height !== video.videoHeight) {
                            pkhMeshCanvas.width = video.videoWidth;
                            pkhMeshCanvas.height = video.videoHeight;
                        }
                    }
                    // Trigger asynchronous MediaPipe processing frame
                    if (pkhFaceMeshReady && pkhFaceMesh && !pkhIsProcessingMesh && video.readyState >= 2 && video.videoWidth > 0) {
                        pkhIsProcessingMesh = true;
                        try {
                            pkhFaceMesh.send({ image: video })
                                .catch(() => {})
                                .finally(() => { pkhIsProcessingMesh = false; });
                        } catch (e) {
                            pkhIsProcessingMesh = false;
                        }
                    }
                    // Sample lighting & clarity from analysis canvas
                    let faceTotalLum = 0;
                    let facePixelCount = 0;
                    let faceEdgeSum = 0;
                    for (let sy = 20; sy < 100; sy += 3) {
                        for (let sx = 20; sx < 140; sx += 3) {
                            const idx = (sy * 160 + sx) * 4;
                            const r = d[idx], g = d[idx + 1], b = d[idx + 2];
                            const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                            faceTotalLum += lum;
                            facePixelCount++;
                            const rIdx = (sy * 160 + (sx + 1)) * 4;
                            const dIdx = ((sy + 1) * 160 + sx) * 4;
                            const rLum = 0.299 * d[rIdx] + 0.587 * d[rIdx + 1] + 0.114 * d[rIdx + 2];
                            const dLum = 0.299 * d[dIdx] + 0.587 * d[dIdx + 1] + 0.114 * d[dIdx + 2];
                            faceEdgeSum += Math.abs(lum - rLum) + Math.abs(lum - dLum);
                        }
                    }
                    const avgFaceLum = facePixelCount > 0 ? (faceTotalLum / facePixelCount) : 100;
                    const avgFaceGrad = facePixelCount > 0 ? (faceEdgeSum / facePixelCount) : 5;
                    let lightState = 'good';
                    let lightText = 'Lighting: Good';
                    if (avgFaceLum < 30) {
                        lightState = 'bad';
                        lightText = 'Lighting: Too Dim';
                    } else if (avgFaceLum > 245) {
                        lightState = 'warn';
                        lightText = 'Lighting: Glare';
                    }
                    let clarityState = 'good';
                    let clarityText = 'Clarity: Sharp';
                    if (avgFaceGrad < 3.0) {
                        clarityState = 'bad';
                        clarityText = 'Clarity: Blurry';
                    }
                    let alignState = 'none';
                    let alignText = 'Position Face';
                    let alignGuidance = 'Position your face inside the oval guide';
                    let isReady = false;
                    // 1. Process MediaPipe High-Precision 3D Facial Landmarks
                    const hasMediaPipeMesh = pkhLastMeshResults && 
                                            pkhLastMeshResults.multiFaceLandmarks && 
                                            pkhLastMeshResults.multiFaceLandmarks.length > 0;
                    if (hasMediaPipeMesh) {
                        const lm = pkhLastMeshResults.multiFaceLandmarks[0];
                        // Clear & draw live futuristic biometric mesh on transparent canvas
                        if (pkhMeshCtx && pkhMeshCanvas) {
                            pkhMeshCtx.clearRect(0, 0, pkhMeshCanvas.width, pkhMeshCanvas.height);
                            const cw = pkhMeshCanvas.width;
                            const ch = pkhMeshCanvas.height;
                            // Draw key facial tracking landmarks (jawline, eyebrows, nose, mouth)
                            const keyIndices = [10, 338, 297, 332, 284, 251, 389, 356, 454, 323, 361, 288, 397, 365, 379, 378, 400, 377, 152, 148, 176, 149, 150, 136, 172, 58, 132, 93, 234, 127, 162, 21, 54, 103, 67, 109, 1, 4, 33, 263, 61, 291];
                            pkhMeshCtx.save();
                            pkhMeshCtx.fillStyle = (alignState === 'good' && lightState === 'good') ? 'rgba(16, 185, 129, 0.85)' : 'rgba(245, 158, 11, 0.75)';
                            for (let i = 0; i < keyIndices.length; i++) {
                                const pt = lm[keyIndices[i]];
                                if (pt) {
                                    const px = pt.x * cw;
                                    const py = pt.y * ch;
                                    pkhMeshCtx.beginPath();
                                    pkhMeshCtx.arc(px, py, 2.2, 0, 2 * Math.PI);
                                    pkhMeshCtx.fill();
                                }
                            }
                            pkhMeshCtx.restore();
                        }
                        // Compute dynamic oval center relative to video viewport
                        let ovalNormX = 0.40, ovalNormY = 0.50, ovalNormRX = 0.18, ovalNormRY = 0.30;
                        if (ovalEl && videoRect.width > 0 && videoRect.height > 0) {
                            const oRect = ovalEl.getBoundingClientRect();
                            ovalNormX = (oRect.left + oRect.width / 2 - videoRect.left) / videoRect.width;
                            ovalNormY = (oRect.top + oRect.height / 2 - videoRect.top) / videoRect.height;
                            ovalNormRX = (oRect.width / 2) / videoRect.width;
                            ovalNormRY = (oRect.height / 2) / videoRect.height;
                        }
                        // Mirrored camera coordinates
                        const nosePt = lm[4] || lm[1];
                        const faceNormX = (facingModes.selfie === 'user') ? (1.0 - nosePt.x) : nosePt.x;
                        const faceNormY = nosePt.y;
                        const offX = (faceNormX - ovalNormX) / (ovalNormRX || 0.18);
                        const offY = (faceNormY - ovalNormY) / (ovalNormRY || 0.30);
                        // 3D Head Rotation / Yaw (Looking left vs right)
                        const leftEyePt = lm[33];
                        const rightEyePt = lm[263];
                        const distLeftEye = Math.hypot(nosePt.x - leftEyePt.x, nosePt.y - leftEyePt.y);
                        const distRightEye = Math.hypot(nosePt.x - rightEyePt.x, nosePt.y - rightEyePt.y);
                        const yawRatio = distLeftEye / ((distLeftEye + distRightEye) || 1);
                        // 3D Head Tilt / Roll
                        const rollAngle = Math.abs(Math.atan2(rightEyePt.y - leftEyePt.y, rightEyePt.x - leftEyePt.x) * (180 / Math.PI));
                        // 3D Head Pitch (Looking up vs down)
                        const eyeCenterY = (leftEyePt.y + rightEyePt.y) / 2;
                        const chinPt = lm[152];
                        const upperY = Math.abs(nosePt.y - eyeCenterY);
                        const lowerY = Math.abs(chinPt.y - nosePt.y);
                        const pitchRatio = upperY / (lowerY || 0.001);
                        // Face distance / height scale
                        const foreheadPt = lm[10];
                        const faceHeight = Math.hypot(foreheadPt.x - chinPt.x, foreheadPt.y - chinPt.y);
                        // Evaluate alignment criteria
                        if (Math.abs(yawRatio - 0.5) > 0.16) {
                            alignState = 'warn';
                            alignText = 'Turn Face Forward';
                            alignGuidance = 'Look directly forward at the camera';
                        } else if (rollAngle > 10.0) {
                            alignState = 'warn';
                            alignText = 'Straighten Head';
                            alignGuidance = 'Keep your head level without tilting';
                        } else if (pitchRatio < 0.28) {
                            alignState = 'warn';
                            alignText = 'Tilt Head Down';
                            alignGuidance = 'Lower your chin slightly to face camera';
                        } else if (pitchRatio > 0.88) {
                            alignState = 'warn';
                            alignText = 'Tilt Head Up';
                            alignGuidance = 'Raise your head level with camera';
                        } else if (faceHeight < 0.26) {
                            alignState = 'warn';
                            alignText = 'Move Closer';
                            alignGuidance = 'Move closer so your face fills the oval';
                        } else if (faceHeight > 0.78) {
                            alignState = 'warn';
                            alignText = 'Step Back';
                            alignGuidance = 'Step back slightly from camera';
                        } else if (offX < -0.28) {
                            alignState = 'warn';
                            alignText = 'Move Right';
                            alignGuidance = 'Shift your head slightly to the right';
                        } else if (offX > 0.28) {
                            alignState = 'warn';
                            alignText = 'Move Left';
                            alignGuidance = 'Shift your head slightly to the left';
                        } else if (offY < -0.28) {
                            alignState = 'warn';
                            alignText = 'Lower Head';
                            alignGuidance = 'Lower head into the oval frame';
                        } else if (offY > 0.32) {
                            alignState = 'warn';
                            alignText = 'Raise Head';
                            alignGuidance = 'Raise head into the oval frame';
                        } else {
                            alignState = 'good';
                            alignText = 'Face: Centered ✓';
                            alignGuidance = '✓ Face aligned! Hold steady and show ID';
                        }
                    } else {
                        // MediaPipe fallback: Intelligent Real-time Computer Vision facial detection
                        let ovalNormX = 0.40, ovalNormY = 0.50, ovalNormRX = 0.18, ovalNormRY = 0.30;
                        let oLeft = 25, oRight = 85, oTop = 15, oBottom = 95;
                        if (ovalEl && videoRect.width > 0 && videoRect.height > 0) {
                            const oRect = ovalEl.getBoundingClientRect();
                            ovalNormX = (oRect.left + oRect.width / 2 - videoRect.left) / videoRect.width;
                            ovalNormY = (oRect.top + oRect.height / 2 - videoRect.top) / videoRect.height;
                            ovalNormRX = (oRect.width / 2) / videoRect.width;
                            ovalNormRY = (oRect.height / 2) / videoRect.height;
                            // Map oval coordinates to 160x120 canvas
                            if (facingModes.selfie === 'user') {
                                const normL = (oRect.left - videoRect.left) / videoRect.width;
                                const normR = (oRect.right - videoRect.left) / videoRect.width;
                                oLeft = Math.max(2, Math.floor((1.0 - normR) * 160));
                                oRight = Math.min(158, Math.ceil((1.0 - normL) * 160));
                            } else {
                                oLeft = Math.max(2, Math.floor(((oRect.left - videoRect.left) / videoRect.width) * 160));
                                oRight = Math.min(158, Math.ceil(((oRect.right - videoRect.left) / videoRect.width) * 160));
                            }
                            oTop = Math.max(2, Math.floor(((oRect.top - videoRect.top) / videoRect.height) * 120));
                            oBottom = Math.min(118, Math.ceil(((oRect.bottom - videoRect.top) / videoRect.height) * 120));
                        }
                        const oMidX = (oLeft + oRight) / 2;
                        const oMidY = (oTop + oBottom) / 2;
                        const oHalfW = Math.max(10, (oRight - oLeft) / 2);
                        const oHalfH = Math.max(10, (oBottom - oTop) / 2);

                        let skinPixels = 0;
                        let sumSkinX = 0;
                        let sumSkinY = 0;
                        let totalOvalPixels = 0;

                        for (let fy = oTop; fy < oBottom; fy += 2) {
                            for (let fx = oLeft; fx < oRight; fx += 2) {
                                totalOvalPixels++;
                                const idx = (fy * 160 + fx) * 4;
                                const r = d[idx], g = d[idx + 1], b = d[idx + 2];
                                const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                                // Robust human skin tone detector (broad range of lighting & skin undertones)
                                const isSkin = (r > 42 && g > 28 && b > 18 && r > g && (r - b) >= 8 && lum > 32 && lum < 242);
                                if (isSkin) {
                                    skinPixels++;
                                    sumSkinX += fx;
                                    sumSkinY += fy;
                                }
                            }
                        }

                        const skinRatio = totalOvalPixels > 0 ? (skinPixels / totalOvalPixels) : 0;
                        if (skinRatio < 0.10) {
                            alignState = 'none';
                            alignText = 'No Face Detected';
                            alignGuidance = 'Position your face inside the oval guide';
                            if (pkhMeshCtx && pkhMeshCanvas) {
                                pkhMeshCtx.clearRect(0, 0, pkhMeshCanvas.width, pkhMeshCanvas.height);
                            }
                        } else {
                            const centroidX = sumSkinX / skinPixels;
                            const centroidY = sumSkinY / skinPixels;
                            const offX = (centroidX - oMidX) / oHalfW;
                            const offY = (centroidY - oMidY) / oHalfH;

                            // Draw live biometric tracking halo on mesh canvas
                            if (pkhMeshCtx && pkhMeshCanvas && video.videoWidth > 0 && video.videoHeight > 0) {
                                pkhMeshCtx.clearRect(0, 0, pkhMeshCanvas.width, pkhMeshCanvas.height);
                                const cw = pkhMeshCanvas.width;
                                const ch = pkhMeshCanvas.height;
                                const cx = (centroidX / 160) * cw;
                                const cy = (centroidY / 120) * ch;
                                const isCentered = (Math.abs(offX) <= 0.26 && Math.abs(offY) <= 0.26 && skinRatio >= 0.18);
                                pkhMeshCtx.save();
                                pkhMeshCtx.strokeStyle = isCentered ? 'rgba(16, 185, 129, 0.75)' : 'rgba(245, 158, 11, 0.75)';
                                pkhMeshCtx.lineWidth = 2.5;
                                pkhMeshCtx.setLineDash([5, 5]);
                                pkhMeshCtx.beginPath();
                                pkhMeshCtx.ellipse(cx, cy, (oHalfW / 160) * cw * 0.75, (oHalfH / 120) * ch * 0.75, 0, 0, 2 * Math.PI);
                                pkhMeshCtx.stroke();
                                pkhMeshCtx.restore();
                            }

                            if (offX < -0.26) {
                                alignState = 'warn';
                                alignText = 'Move Right';
                                alignGuidance = 'Shift your head slightly right to center in oval';
                            } else if (offX > 0.26) {
                                alignState = 'warn';
                                alignText = 'Move Left';
                                alignGuidance = 'Shift your head slightly left to center in oval';
                            } else if (offY < -0.26) {
                                alignState = 'warn';
                                alignText = 'Lower Head';
                                alignGuidance = 'Lower head slightly into the oval';
                            } else if (offY > 0.26) {
                                alignState = 'warn';
                                alignText = 'Raise Head';
                                alignGuidance = 'Raise head slightly into the oval';
                            } else if (skinRatio < 0.18) {
                                alignState = 'warn';
                                alignText = 'Move Closer';
                                alignGuidance = 'Move closer so your face fills the oval guide';
                            } else {
                                alignState = 'good';
                                alignText = 'Face: Centered ✓';
                                alignGuidance = '✓ Face aligned! Hold steady and show ID';
                            }
                        }
                    }
                    // =========================================================
                    // REAL ID CARD DETECTION BESIDE FACE (STEP 3)
                    // =========================================================
                    let isIdCardHeld = false;
                    if (idBoxEl && videoRect.width > 0 && videoRect.height > 0) {
                        const iRect = idBoxEl.getBoundingClientRect();
                        // Map DOM idBoxEl to 160x120 canvas
                        // Video has CSS transform: scaleX(-1) in user-facing selfie mode
                        let sIdLeft, sIdRight;
                        if (facingModes.selfie === 'user') {
                            const normL = (iRect.left - videoRect.left) / videoRect.width;
                            const normR = (iRect.right - videoRect.left) / videoRect.width;
                            sIdLeft = Math.max(2, Math.floor((1.0 - normR) * 160));
                            sIdRight = Math.min(158, Math.ceil((1.0 - normL) * 160));
                        } else {
                            sIdLeft = Math.max(2, Math.floor(((iRect.left - videoRect.left) / videoRect.width) * 160));
                            sIdRight = Math.min(158, Math.ceil(((iRect.right - videoRect.left) / videoRect.width) * 160));
                        }
                        const sIdTop = Math.max(2, Math.floor(((iRect.top - videoRect.top) / videoRect.height) * 120));
                        const sIdBottom = Math.min(118, Math.ceil(((iRect.bottom - videoRect.top) / videoRect.height) * 120));
                        // Sample pixels inside the ID Card Guide Box beside the face
                        let idEdges = 0;
                        let idSharpEdges = 0;
                        let idMinLum = 255;
                        let idMaxLum = 0;
                        for (let iy = sIdTop; iy < sIdBottom; iy += 2) {
                            for (let ix = sIdLeft; ix < sIdRight; ix += 2) {
                                const idx = (iy * 160 + ix) * 4;
                                const r = d[idx], g = d[idx + 1], b = d[idx + 2];
                                const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                                if (lum < idMinLum) idMinLum = lum;
                                if (lum > idMaxLum) idMaxLum = lum;
                                const rIdx = (iy * 160 + (ix + 1)) * 4;
                                const dIdx = ((iy + 1) * 160 + ix) * 4;
                                const rLum = 0.299 * d[rIdx] + 0.587 * d[rIdx + 1] + 0.114 * d[rIdx + 2];
                                const dLum = 0.299 * d[dIdx] + 0.587 * d[dIdx + 1] + 0.114 * d[dIdx + 2];
                                const grad = Math.abs(lum - rLum) + Math.abs(lum - dLum);
                                if (grad >= 14) idEdges++;
                                if (grad >= 22) idSharpEdges++;
                            }
                        }
                        const idContrast = idMaxLum - idMinLum;
                        // A real ID card held beside face has sharp printed features and significant contrast
                        isIdCardHeld = (idEdges >= 52) && (idSharpEdges >= 15) && (idContrast >= 46);
                        if (isIdCardHeld) {
                            idBoxEl.className = 'selfie-id-box-guide state-ready';
                            if (metricIdCard) {
                                metricIdCard.className = 'hud-metric-chip metric-good';
                                metricIdCard.innerHTML = '<i class="fas fa-id-card"></i> ID: Detected ✓';
                            }
                        } else {
                            idBoxEl.className = 'selfie-id-box-guide state-warning';
                            if (metricIdCard) {
                                metricIdCard.className = 'hud-metric-chip metric-bad';
                                metricIdCard.innerHTML = '<i class="fas fa-id-card"></i> ID: Hold in Box';
                            }
                        }
                    }
                    // In Step 3: Prompt user if face is ready but ID card is not yet held!
                    if (alignState === 'good' && !isIdCardHeld) {
                        alignGuidance = 'Face centered! Now hold your ID card inside the guide box';
                    }
                    // Update UI Metric Chips
                    if (metricLight) {
                        metricLight.className = `hud-metric-chip ${lightState === 'good' ? 'metric-good' : (lightState === 'warn' ? 'metric-warn' : 'metric-bad')}`;
                        metricLight.innerHTML = `<i class="fas fa-sun"></i> ${lightText}`;
                    }
                    if (metricAlign) {
                        metricAlign.className = `hud-metric-chip ${alignState === 'good' ? 'metric-good' : (alignState === 'warn' ? 'metric-warn' : 'metric-bad')}`;
                        metricAlign.innerHTML = `<i class="fas fa-user-check"></i> ${alignText}`;
                    }
                    if (metricClarity) {
                        metricClarity.className = `hud-metric-chip ${clarityState === 'good' ? 'metric-good' : 'metric-bad'}`;
                        metricClarity.innerHTML = `<i class="fas fa-magic"></i> ${clarityText}`;
                    }
                    // Step 3 readiness strictly requires BOTH Face Alignment AND ID Card Presence!
                    isReady = (alignState === 'good') && (lightState === 'good') && (clarityState === 'good') && (isIdCardHeld === true);
                    if (isReady) {
                        if (hudStatus) hudStatus.className = 'hud-status-banner state-ready';
                        if (hudText) hudText.textContent = '✓ Face Centered & Aligned — Ready to Capture!';
                        if (guideBox) guideBox.className = 'selfie-face-oval state-ready';
                        if (shutterBtn) shutterBtn.classList.add('state-ready-pulse');
                        // Auto-capture countdown logic (triggers after ~1s of steady alignment)
                        if (autoToggle && autoToggle.checked) {
                            autoCaptureTimers[target] += 1;
                            if (autoCaptureTimers[target] >= 30 && !autoCountdownIntervals[target]) {
                                triggerCountdownCapture(target);
                            }
                        }
                    } else {
                        autoCaptureTimers[target] = 0;
                        document.getElementById(`${target}ManualCaptureBtn`)?.classList.remove('state-ready-pulse');
                        if (autoCountdownIntervals[target]) {
                            clearInterval(autoCountdownIntervals[target]);
                            autoCountdownIntervals[target] = null;
                            const cd = document.getElementById(`${target}CountdownOverlay`);
                            if (cd) cd.style.display = 'none';
                        }
                        if (shutterBtn) shutterBtn.classList.remove('state-ready-pulse');
                        if (alignState === 'none') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = 'Position your face inside the oval';
                            if (guideBox) guideBox.className = 'selfie-face-oval state-warning';
                        } else if (alignState === 'warn') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = alignGuidance;
                            if (guideBox) guideBox.className = 'selfie-face-oval state-aligning';
                        } else if (lightState === 'bad') {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-danger';
                            if (hudText) hudText.textContent = 'Lighting too dim — Move to brighter area';
                            if (guideBox) guideBox.className = 'selfie-face-oval state-danger';
                        } else {
                            if (hudStatus) hudStatus.className = 'hud-status-banner state-warning';
                            if (hudText) hudText.textContent = alignGuidance;
                            if (guideBox) guideBox.className = 'selfie-face-oval state-warning';
                        }
                    }
                }
                cvAnimFrames[target] = requestAnimationFrame(analyzeFrame);
            }
            cvAnimFrames[target] = requestAnimationFrame(analyzeFrame);
        }
        // Auto-capture Countdown (3... 2... 1... Flash!)
        function triggerCountdownCapture(target) {
            const overlay = document.getElementById(`${target}CountdownOverlay`);
            const numEl = document.getElementById(`${target}CountdownNumber`);
            if (!overlay || !numEl) return;
            let count = 3;
            overlay.style.display = 'flex';
            numEl.textContent = count;
            autoCountdownIntervals[target] = setInterval(() => {
                count--;
                if (count > 0) {
                    numEl.textContent = count;
                } else {
                    clearInterval(autoCountdownIntervals[target]);
                    autoCountdownIntervals[target] = null;
                    overlay.style.display = 'none';
                    executeCapture(target);
                }
            }, 800);
        }
        // Take snapshot from video canvas
        function executeCapture(target) {
            const video = document.getElementById(`${target}Video`);
            const hiddenInput = document.getElementById(`captured_${target}_image`);
            const holder = document.getElementById(`${target}SnapshotHolder`);
            const imgEl = document.getElementById(`${target}SnapshotImg`);
            if (!video || !hiddenInput || !holder || !imgEl) return;
            const c = document.createElement('canvas');
            c.width = video.videoWidth || 1280;
            c.height = video.videoHeight || 720;
            const ctx = c.getContext('2d');
            // Mirror selfie so it matches preview
            if (target === 'selfie' && facingModes.selfie === 'user') {
                ctx.translate(c.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0, c.width, c.height);
            const dataUrl = c.toDataURL('image/jpeg', 0.92);
            hiddenInput.value = dataUrl;
            imgEl.src = dataUrl;
            holder.style.display = 'block';
            video.style.display = 'none';
            // Stop live stream to conserve device resource
            stopCamera(target);
            hideAlert();
            // Update status badges on pills
            if (target === 'id') {
                document.getElementById('pillFrontSide')?.classList.add('is-captured');
                const st = document.getElementById('pillStatusFront');
                if (st) st.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Front Ready';
                runIdCardOcr(dataUrl);
            } else if (target === 'id_back') {
                document.getElementById('pillBackSide')?.classList.add('is-captured');
                const st = document.getElementById('pillStatusBack');
                if (st) st.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> Back Ready';
            }
        }
        // Retake snapshot
        function retakeCapture(target) {
            const hiddenInput = document.getElementById(`captured_${target}_image`);
            const holder = document.getElementById(`${target}SnapshotHolder`);
            const imgEl = document.getElementById(`${target}SnapshotImg`);
            if (hiddenInput) hiddenInput.value = '';
            if (imgEl) imgEl.src = '';
            if (holder) holder.style.display = 'none';
            if (target === 'id') {
                const ocrCard = document.getElementById('idOcrResultCard');
                if (ocrCard) ocrCard.style.display = 'none';
            }
            startCamera(target);
        }
        // Bind Shutter Buttons
        document.getElementById('idManualCaptureBtn')?.addEventListener('click', () => executeCapture('id'));
        document.getElementById('id_backManualCaptureBtn')?.addEventListener('click', () => executeCapture('id_back'));
        document.getElementById('selfieManualCaptureBtn')?.addEventListener('click', () => executeCapture('selfie'));
        document.getElementById('idRetakeBtn')?.addEventListener('click', () => retakeCapture('id'));
        document.getElementById('id_backRetakeBtn')?.addEventListener('click', () => retakeCapture('id_back'));
        document.getElementById('selfieRetakeBtn')?.addEventListener('click', () => retakeCapture('selfie'));
        document.getElementById('id_backSwitchCameraBtn')?.addEventListener('click', () => switchCamera('id_back'));
        // Bind Snapshot Zoom / Lightbox
        document.getElementById('idZoomBtn')?.addEventListener('click', () => {
            const data = document.getElementById('captured_id_image').value;
            if (data) openLightbox(data, 'Captured Valid ID (Front)');
        });
        document.getElementById('id_backZoomBtn')?.addEventListener('click', () => {
            const data = document.getElementById('captured_id_back_image')?.value;
            if (data) openLightbox(data, 'Captured Valid ID (Back)');
        });
        document.getElementById('selfieZoomBtn')?.addEventListener('click', () => {
            const data = document.getElementById('captured_selfie_image').value;
            if (data) openLightbox(data, 'Captured Selfie with ID');
        });
        // ========================================================
        // STEP 4 SUMMARY BUILDER (ENSURES IMAGES DISPLAY WITH ZOOM)
        // ========================================================
        function buildSummary() {
            const idType = document.getElementById('id_type').value;
            const idNumber = document.getElementById('id_number').value;
            const barangay = document.getElementById('barangay').value;
            const street = document.getElementById('street').value;
            document.getElementById('sumIdType').textContent = idType || '-';
            document.getElementById('sumIdNumber').textContent = idNumber || '-';
            document.getElementById('sumBarangay').textContent = barangay || '-';
            document.getElementById('sumStreet').textContent = street || 'N/A';
            // 1. Resolve Valid ID Front Thumbnail Source
            const sumImgId = document.getElementById('sumImgId');
            const sumBadgeIdMode = document.getElementById('sumBadgeIdMode');
            let idSrc = '';
            if (currentModes.id === 'camera' && document.getElementById('captured_id_image')?.value) {
                idSrc = document.getElementById('captured_id_image').value;
                if (sumBadgeIdMode) sumBadgeIdMode.textContent = 'Camera Snapshot';
            } else if (currentModes.id === 'upload' && fileDataUrls.id) {
                idSrc = fileDataUrls.id;
                if (sumBadgeIdMode) sumBadgeIdMode.textContent = 'Uploaded File';
            } else {
                idSrc = 'images/palayan-city.png';
                if (sumBadgeIdMode) sumBadgeIdMode.textContent = 'Not Attached';
            }
            if (sumImgId) sumImgId.src = idSrc;

            // 1b. Resolve Valid ID Back Thumbnail Source
            const sumImgIdBack = document.getElementById('sumImgIdBack');
            const sumBadgeIdBackMode = document.getElementById('sumBadgeIdBackMode');
            let idBackSrc = '';
            if (currentModes.id_back === 'camera' && document.getElementById('captured_id_back_image')?.value) {
                idBackSrc = document.getElementById('captured_id_back_image').value;
                if (sumBadgeIdBackMode) sumBadgeIdBackMode.textContent = 'Camera Snapshot';
            } else if (currentModes.id_back === 'upload' && fileDataUrls.id_back) {
                idBackSrc = fileDataUrls.id_back;
                if (sumBadgeIdBackMode) sumBadgeIdBackMode.textContent = 'Uploaded File';
            } else {
                idBackSrc = 'images/palayan-city.png';
                if (sumBadgeIdBackMode) sumBadgeIdBackMode.textContent = 'Not Attached';
            }
            if (sumImgIdBack) sumImgIdBack.src = idBackSrc;

            // 2. Resolve Selfie Thumbnail Source
            const sumImgSelfie = document.getElementById('sumImgSelfie');
            const sumBadgeSelfieMode = document.getElementById('sumBadgeSelfieMode');
            let selfieSrc = '';
            if (currentModes.selfie === 'camera' && document.getElementById('captured_selfie_image').value) {
                selfieSrc = document.getElementById('captured_selfie_image').value;
                sumBadgeSelfieMode.textContent = 'Camera Snapshot';
            } else if (currentModes.selfie === 'upload' && fileDataUrls.selfie) {
                selfieSrc = fileDataUrls.selfie;
                sumBadgeSelfieMode.textContent = 'Uploaded File';
            } else {
                selfieSrc = 'images/palayan-city.png';
                sumBadgeSelfieMode.textContent = 'Not Attached';
            }
            sumImgSelfie.src = selfieSrc;
            // Bind click to zoom on summary thumbnails
            if (document.getElementById('sumWrapId')) {
                document.getElementById('sumWrapId').onclick = () => {
                    if (idSrc && idSrc !== 'images/palayan-city.png') {
                        openLightbox(idSrc, `Summary: Valid ID Front (${idType})`);
                    }
                };
            }
            if (document.getElementById('sumWrapIdBack')) {
                document.getElementById('sumWrapIdBack').onclick = () => {
                    if (idBackSrc && idBackSrc !== 'images/palayan-city.png') {
                        openLightbox(idBackSrc, `Summary: Valid ID Back (${idType})`);
                    }
                };
            }
            if (document.getElementById('sumWrapSelfie')) {
                document.getElementById('sumWrapSelfie').onclick = () => {
                    if (selfieSrc && selfieSrc !== 'images/palayan-city.png') {
                        openLightbox(selfieSrc, `Summary: Selfie with ID`);
                    }
                };
            }
        }
        // ========================================================
        // AI ID CARD OCR RECOGNITION & AUTO-FILL ENGINE
        // ========================================================
        let isRunningOcr = false;
        async function runIdCardOcr(imageDataUrl) {
            if (!imageDataUrl || isRunningOcr) return;
            isRunningOcr = true;
            const ocrCard = document.getElementById('idOcrResultCard');
            const ocrStatus = document.getElementById('idOcrStatusPill');
            const ocrGrid = document.getElementById('idOcrDataGrid');
            const ocrHint = document.getElementById('idOcrHint');
            if (ocrCard) ocrCard.style.display = 'block';
            if (ocrStatus) {
                ocrStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reading Card Details with AI OCR...';
            }
            if (ocrGrid) {
                ocrGrid.innerHTML = `
                    <div class="ocr-field-item" style="grid-column: 1 / -1;">
                        <span class="ocr-field-label">OCR Progress</span>
                        <span class="ocr-field-val" id="ocrLiveProgress"><i class="fas fa-search"></i> Scanning printed text & card number...</span>
                    </div>
                `;
            }
            try {
                let extractedText = '';
                // 1. Client-Side Tesseract OCR
                if (typeof Tesseract !== 'undefined') {
                    try {
                        const worker = await Tesseract.createWorker('eng');
                        const progressEl = document.getElementById('ocrLiveProgress');
                        if (progressEl) progressEl.innerHTML = '<i class="fas fa-cog fa-spin"></i> Neural network parsing characters...';
                        const ret = await worker.recognize(imageDataUrl);
                        extractedText = ret.data.text || '';
                        await worker.terminate();
                    } catch (tessErr) {
                        console.warn('Tesseract client OCR note:', tessErr);
                    }
                }
                // 2. Send extracted text (or image fallback) to id-ocr-ajax.php for Philippine ID parsing
                const formData = new FormData();
                if (extractedText) {
                    formData.append('raw_text', extractedText);
                } else {
                    formData.append('image_base64', imageDataUrl);
                }
                const resp = await fetch('id-ocr-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const resJson = await resp.json();
                if (resJson && resJson.success && resJson.data) {
                    const d = resJson.data;
                    // Build preview grid
                    let gridHtml = '';
                    if (d.id_type && d.id_type !== 'Other') {
                        gridHtml += `
                            <div class="ocr-field-item">
                                <span class="ocr-field-label">Detected ID Type</span>
                                <span class="ocr-field-val">${escapeHtml(d.id_type)}</span>
                            </div>
                        `;
                    }
                    if (d.id_number) {
                        gridHtml += `
                            <div class="ocr-field-item">
                                <span class="ocr-field-label">Extracted ID Number</span>
                                <span class="ocr-field-val">${escapeHtml(d.id_number)}</span>
                            </div>
                        `;
                    }
                    if (d.name) {
                        gridHtml += `
                            <div class="ocr-field-item">
                                <span class="ocr-field-label">Detected Name</span>
                                <span class="ocr-field-val">${escapeHtml(d.name)}</span>
                            </div>
                        `;
                    }
                    if (d.barangay) {
                        gridHtml += `
                            <div class="ocr-field-item">
                                <span class="ocr-field-label">Detected Barangay</span>
                                <span class="ocr-field-val">${escapeHtml(d.barangay)}</span>
                            </div>
                        `;
                    }
                    if (!gridHtml) {
                        gridHtml = `
                            <div class="ocr-field-item" style="grid-column: 1 / -1;">
                                <span class="ocr-field-label">Document Scanned</span>
                                <span class="ocr-field-val">Valid ID image captured successfully</span>
                            </div>
                        `;
                    }
                    if (ocrGrid) ocrGrid.innerHTML = gridHtml;
                    if (ocrStatus) {
                        ocrStatus.innerHTML = `<i class="fas fa-check-circle"></i> Scanned (${d.confidence || 90}% Confidence)`;
                    }
                    // Auto-fill form fields in Step 1 & Step 4
                    let autoFilledCount = 0;
                    const idTypeEl = document.getElementById('id_type');
                    const idNumEl = document.getElementById('id_number');
                    const barangayEl = document.getElementById('barangay');
                    if (d.id_type && d.id_type !== 'Other' && idTypeEl) {
                        for (let opt of idTypeEl.options) {
                            if (opt.value && (opt.value.toLowerCase().includes(d.id_type.toLowerCase()) || d.id_type.toLowerCase().includes(opt.value.toLowerCase()))) {
                                idTypeEl.value = opt.value;
                                idTypeEl.classList.add('ocr-autofilled-glow');
                                autoFilledCount++;
                                break;
                            }
                        }
                    }
                    if (d.id_number && idNumEl) {
                        idNumEl.value = d.id_number;
                        idNumEl.classList.add('ocr-autofilled-glow');
                        autoFilledCount++;
                    }
                    if (d.barangay && barangayEl) {
                        for (let opt of barangayEl.options) {
                            if (opt.value && opt.value.toLowerCase() === d.barangay.toLowerCase()) {
                                barangayEl.value = opt.value;
                                barangayEl.classList.add('ocr-autofilled-glow');
                                autoFilledCount++;
                                break;
                            }
                        }
                    }
                    if (ocrHint) {
                        if (autoFilledCount > 0) {
                            ocrHint.innerHTML = `<i class="fas fa-check-circle"></i> ID Type, ID Number, and Barangay were automatically auto-filled in your form!`;
                        } else {
                            ocrHint.innerHTML = `<i class="fas fa-info-circle"></i> ID card details captured. Please review and confirm your ID number in Step 1.`;
                        }
                    }
                } else {
                    if (ocrStatus) ocrStatus.innerHTML = '<i class="fas fa-check-circle"></i> Card Photo Attached';
                    if (ocrGrid) {
                        ocrGrid.innerHTML = `
                            <div class="ocr-field-item" style="grid-column: 1 / -1;">
                                <span class="ocr-field-label">Document Photo</span>
                                <span class="ocr-field-val">Image saved. Please confirm your ID Number in Step 1.</span>
                            </div>
                        `;
                    }
                }
            } catch (err) {
                console.error('ID OCR Error:', err);
                if (ocrStatus) ocrStatus.innerHTML = '<i class="fas fa-check-circle"></i> Card Photo Attached';
            } finally {
                isRunningOcr = false;
            }
        }
        // Form Submit Validation
        verifyForm.addEventListener('submit', function(e) {
            const barangay = document.getElementById('barangay').value;
            if (!barangay) {
                e.preventDefault();
                showAlert("Please select your Barangay before submitting your request.");
                return false;
            }
            return true;
        });
        // Initialize Step 1 UI
        updateWizardUI();
    }
    </script>
    <!-- Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
    <!-- Welcome & Profile Completion Modal -->
    <?php if ($verification_status === 'verified'): ?>
        <?php include __DIR__ . '/includes/welcome-profile-modal.php'; ?>
    <?php endif; ?>
</body>
</html>
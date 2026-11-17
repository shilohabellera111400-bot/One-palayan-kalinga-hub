
<?php
// update-profile-info.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and residents can complete their profile

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

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

// Check if user already has a resident record
$check_resident = "SELECT * FROM residents WHERE user_id = :user_id";
$check_stmt = $db->prepare($check_resident);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$existing_resident = $check_stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update - ONLY IF VERIFIED
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile_info'])) {
    if ($verification_status != 'verified') {
        $error = "You must complete residency verification before you can update your profile information.";
    } else {
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $middle_name = sanitize($_POST['middle_name'] ?? '');
        $suffix = sanitize($_POST['suffix'] ?? '');
        $gender = sanitize($_POST['gender']);
        $birth_date = sanitize($_POST['birth_date']);
        $civil_status = sanitize($_POST['civil_status']);
        $contact_number = sanitize($_POST['contact_number']);
        $email = sanitize($_POST['email'] ?? '');
        $street = sanitize($_POST['street']);
        $barangay = sanitize($_POST['barangay']);
        $city = sanitize($_POST['city']);
        $province = sanitize($_POST['province']);
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $household_number = sanitize($_POST['household_number'] ?? '');
        $senior_citizen = isset($_POST['senior_citizen']) ? 1 : 0;
        $pwd = isset($_POST['pwd']) ? 1 : 0;
        
        // Auto-detect Senior Citizen status under RA 9994 (60 years old and above)
        if (!empty($birth_date)) {
            $b_date = new DateTime($birth_date);
            $now = new DateTime();
            $calc_age = $now->diff($b_date)->y;
            if ($calc_age >= 60) {
                $senior_citizen = 1; // Auto-detect and grant senior citizen status
            } else {
                $senior_citizen = 0; // Under 60 cannot be senior citizen
            }
        }

        $occupation = sanitize($_POST['occupation'] ?? '');
        $monthly_income = sanitize($_POST['monthly_income'] ?? 0);
        $educational_attainment = sanitize($_POST['educational_attainment'] ?? '');
        $emergency_contact_name = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_number = sanitize($_POST['emergency_contact_number'] ?? '');
        $relationship = sanitize($_POST['relationship'] ?? '');

        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($birth_date) || 
            empty($civil_status) || empty($street) || empty($barangay) || empty($city) || empty($province)) {
            $error = "Please fill in all required fields.";
        } else {
            if ($existing_resident) {
                // Ensure resident has an official resident number
                $curr_res_num = !empty($existing_resident['resident_number']) ? $existing_resident['resident_number'] : generateResidentNumber($db);
                // Update existing resident
                $query = "UPDATE residents SET 
                          resident_number = :resident_number,
                          first_name = :first_name, last_name = :last_name, middle_name = :middle_name,
                          suffix = :suffix, gender = :gender, birth_date = :birth_date,
                          civil_status = :civil_status, contact_number = :contact_number, email = :email,
                          street = :street, barangay = :barangay, city = :city, province = :province,
                          postal_code = :postal_code, household_number = :household_number,
                          senior_citizen = :senior_citizen, pwd = :pwd,
                          occupation = :occupation, monthly_income = :monthly_income,
                          educational_attainment = :educational_attainment,
                          emergency_contact_name = :emergency_contact_name,
                          emergency_contact_number = :emergency_contact_number, relationship = :relationship
                          WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':resident_number', $curr_res_num);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
            } else {
                // Generate a unique resident number
                $resident_number = generateResidentNumber($db);
            
                $query = "INSERT INTO residents (
                    resident_number, user_id, first_name, last_name, middle_name, suffix, gender, 
                    birth_date, civil_status, contact_number, email, street, barangay, 
                    city, province, postal_code, household_number, 
                    senior_citizen, pwd, occupation, monthly_income, educational_attainment, 
                    emergency_contact_name, emergency_contact_number, relationship, is_active
                ) VALUES (
                    :resident_number, :user_id, :first_name, :last_name, :middle_name, :suffix, :gender,
                    :birth_date, :civil_status, :contact_number, :email, :street, :barangay,
                    :city, :province, :postal_code, :household_number,
                    :senior_citizen, :pwd, :occupation, :monthly_income, :educational_attainment,
                    :emergency_contact_name, :emergency_contact_number, :relationship, 1
                )";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':resident_number', $resident_number);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
            }
            
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':middle_name', $middle_name);
            $stmt->bindParam(':suffix', $suffix);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':birth_date', $birth_date);
            $stmt->bindParam(':civil_status', $civil_status);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':street', $street);
            $stmt->bindParam(':barangay', $barangay);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':province', $province);
            $stmt->bindParam(':postal_code', $postal_code);
            $stmt->bindParam(':household_number', $household_number);
            $stmt->bindParam(':senior_citizen', $senior_citizen);
            $stmt->bindParam(':pwd', $pwd);
            $stmt->bindParam(':occupation', $occupation);
            $stmt->bindParam(':monthly_income', $monthly_income);
            $stmt->bindParam(':educational_attainment', $educational_attainment);
            $stmt->bindParam(':emergency_contact_name', $emergency_contact_name);
            $stmt->bindParam(':emergency_contact_number', $emergency_contact_number);
            $stmt->bindParam(':relationship', $relationship);
            
            if ($stmt->execute()) {
                // Also update users table with contact info
                $update_user = "UPDATE users SET contact_number = :contact_number, email = :email WHERE user_id = :user_id";
                $user_stmt = $db->prepare($update_user);
                $user_stmt->bindParam(':contact_number', $contact_number);
                $user_stmt->bindParam(':email', $email);
                $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $user_stmt->execute();
                
                // Profile successfully completed and saved
                $_SESSION['profile_just_completed'] = true;
                $_SESSION['show_photo_upload_modal'] = true;
                redirect('update-profile-info.php?saved=1');
                exit;
            } else {
                $error = "Failed to save profile information. Please try again.";
            }
        }
    }
}

// Get user's current information
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Use resident data if exists, otherwise use user data
$resident = $existing_resident ?: $user;

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
    ? $profile_picture 
    : '';

// Check if user is 100% complete (verified + demographics + avatar)
$has_resident_details = !empty($existing_resident['birth_date']) && !empty($existing_resident['civil_status']);
$has_avatar = !empty($avatar_url);
$is_100_complete = ($verification_status === 'verified' && $has_resident_details);

// Check for save success modal trigger
$show_save_success_modal = (isset($_GET['saved']) && $_GET['saved'] == '1') || !empty($_SESSION['show_photo_upload_modal']);
if (!empty($_SESSION['show_photo_upload_modal'])) {
    unset($_SESSION['show_photo_upload_modal']);
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <?php if (isStaff() || isAdmin()): ?>
    <link rel="stylesheet" href="css/staff-portal.css?v=<?php echo file_exists(__DIR__ . '/css/staff-portal.css') ? filemtime(__DIR__ . '/css/staff-portal.css') : time(); ?>">
    <?php endif; ?>
    <style>
        /* ========== VERIFICATION BANNER ========== */
        .verification-banner {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .verification-banner .left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .verification-banner .left strong {
            font-size: 0.85rem;
        }
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .badge.status-verified, .profile-status-badge.status-verified { background: var(--color-success); color: white; }
        .badge.status-pending, .profile-status-badge.status-pending { background: var(--color-warning); color: white; }
        .badge.status-rejected, .profile-status-badge.status-rejected { background: var(--color-danger); color: white; }
        .verify-link {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
        }
        .verify-link:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== PROFILE FORM ========== */
        .profile-form-container {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .profile-form-container .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        .profile-form-container .form-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .profile-form-container .form-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .form-section {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .form-section h3 {
            color: var(--text-primary);
            margin-bottom: 16px;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section h3 i {
            color: var(--maroon-light);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .form-group label i {
            color: var(--maroon-light);
            margin-right: 4px;
        }
        .form-group .required-star {
            color: var(--color-danger);
            margin-left: 2px;
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group input.disabled-field,
        .form-group select.disabled-field,
        .form-group textarea.disabled-field {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.7rem;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            padding-top: 4px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--maroon);
            cursor: pointer;
        }

        .form-disabled {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-primary:disabled {
            background: var(--badge-bg);
            cursor: not-allowed;
            transform: none;
            color: var(--text-muted);
        }

        /* ========== MULTI-STEP STEPPER ========== */
        .verification-stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 35px;
            padding: 10px 0;
            position: relative;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .step-label {
            margin-top: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            text-align: center;
            transition: all 0.4s;
            max-width: 100px;
        }
        .step-line {
            height: 2px;
            background: var(--border-color);
            flex: 1;
            margin: 0 -10px;
            position: relative;
            top: -18px; /* align with vertical center of circle */
            z-index: 1;
            transition: all 0.4s;
        }
        .step-item.active .step-circle {
            background: var(--white);
            border-color: var(--gold);
            color: var(--gold);
            box-shadow: 0 0 0 4px rgba(201, 168, 108, 0.15);
        }
        .step-item.active .step-label {
            color: var(--gold);
            font-weight: 700;
        }
        .step-item.completed .step-circle {
            background: var(--maroon);
            border-color: var(--maroon);
            color: white;
        }
        .step-item.completed .step-label {
            color: var(--text-primary);
        }
        .step-line.filled {
            background: var(--maroon);
        }

        /* ========== STEP CONTENT ========== */
        .step-content {
            display: none;
            border: none;
            padding: 0;
            margin: 0;
            animation: fadeIn 0.4s ease both;
        }
        .step-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== FIVE CLEAN INFORMATION CARDS DESIGN SYSTEM ========== */
        .summary-review-wrapper {
            margin-bottom: 24px;
        }

        /* Review Hero Banner */
        .review-hero-banner {
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.05) 0%, rgba(201, 168, 108, 0.12) 100%);
            border: 1px solid rgba(127, 3, 3, 0.15);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        [data-theme="dark"] .review-hero-banner {
            background: linear-gradient(135deg, rgba(139, 26, 26, 0.22) 0%, rgba(30, 41, 59, 0.6) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .review-hero-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .review-seal-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--maroon), #9a1515);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
            flex-shrink: 0;
        }
        .review-hero-tag {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--maroon);
            margin-bottom: 2px;
        }
        [data-theme="dark"] .review-hero-tag {
            color: #fca5a5;
        }
        .review-hero-text h3 {
            margin: 0 0 4px 0;
            font-size: 1.12rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .review-hero-text p {
            margin: 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .review-hero-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .resident-id-badge {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            box-shadow: var(--shadow-sm);
        }
        .resident-id-badge .id-label {
            font-size: 0.64rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .resident-id-badge .id-val {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--maroon);
            font-family: monospace;
        }
        [data-theme="dark"] .resident-id-badge .id-val {
            color: #fca5a5;
        }
        .btn-print-summary {
            background: var(--card-bg);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            padding: 9px 16px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }
        .btn-print-summary:hover {
            background: var(--badge-bg);
            border-color: var(--maroon);
            color: var(--maroon);
            transform: translateY(-1px);
        }

        /* Quick review button at subheader */
        .btn-quick-summary {
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.08), rgba(201, 168, 108, 0.16));
            color: var(--maroon);
            border: 1.5px solid rgba(127, 3, 3, 0.22);
            border-radius: 30px;
            padding: 7px 18px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-quick-summary:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }
        [data-theme="dark"] .btn-quick-summary {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Special Categories Selection Cards (Interactive Form in Step 5) */
        .special-category-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .special-category-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            user-select: none;
        }
        .special-category-card:hover {
            border-color: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .special-category-card.is-selected {
            border-color: var(--maroon);
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.04), rgba(201, 168, 108, 0.08));
            box-shadow: 0 4px 18px rgba(127, 3, 3, 0.12);
        }
        [data-theme="dark"] .special-category-card.is-selected {
            background: linear-gradient(135deg, rgba(139, 26, 26, 0.25), rgba(30, 41, 59, 0.8));
            border-color: #ef4444;
        }
        .sc-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--badge-bg);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .special-category-card.is-selected .sc-icon {
            background: var(--maroon);
            color: white;
        }
        .sc-details {
            flex: 1;
        }
        .sc-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        .sc-title-row h4 {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .sc-status-pill {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 20px;
            background: var(--badge-bg);
            color: var(--text-muted);
            letter-spacing: 0.04em;
        }
        .sc-status-pill.pill-active {
            background: #D1FAE5;
            color: #065F46;
        }
        [data-theme="dark"] .sc-status-pill.pill-active {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }
        .sc-details p {
            margin: 0;
            font-size: 0.76rem;
            color: var(--text-muted);
            line-height: 1.35;
        }
        .sc-checkbox-wrapper {
            display: flex;
            align-items: center;
            padding-top: 4px;
        }
        .sc-checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--maroon);
            cursor: pointer;
        }

        /* ========== FIVE CLEAN INFORMATION CARDS GRID ========== */
        .cards-layout-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) {
            .cards-layout-container {
                grid-template-columns: 1fr;
            }
        }

        /* Individual Gov Info Card */
        .gov-info-card {
            background: var(--card-bg);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 22px;
            box-shadow: 0 4px 18px rgba(15, 65, 74, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .gov-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(127, 3, 3, 0.08);
            border-color: rgba(127, 3, 3, 0.3);
        }

        /* Top Color Accents for each card */
        .card-personal {
            grid-column: 1 / -1;
            background: linear-gradient(180deg, var(--card-bg) 0%, rgba(201, 168, 108, 0.03) 100%);
            border-top: 4px solid var(--maroon);
        }
        .card-address {
            border-top: 4px solid #059669;
        }
        .card-economic {
            border-top: 4px solid #D97706;
        }
        .card-emergency {
            border-top: 4px solid #E11D48;
        }
        .card-special {
            grid-column: 1 / -1;
            border-top: 4px solid #7C3AED;
        }

        /* Card Header */
        .gov-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1.5px solid var(--border-color);
        }
        .gov-card-header-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gov-card-icon-bubble {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .bubble-maroon {
            background: rgba(127, 3, 3, 0.1);
            color: var(--maroon);
        }
        .bubble-emerald {
            background: rgba(5, 150, 105, 0.1);
            color: #059669;
        }
        .bubble-gold {
            background: rgba(217, 119, 6, 0.1);
            color: #D97706;
        }
        .bubble-rose {
            background: rgba(225, 29, 72, 0.1);
            color: #E11D48;
        }
        .bubble-purple {
            background: rgba(124, 58, 237, 0.1);
            color: #7C3AED;
        }
        [data-theme="dark"] .bubble-maroon { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        [data-theme="dark"] .bubble-emerald { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        [data-theme="dark"] .bubble-gold { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        [data-theme="dark"] .bubble-rose { background: rgba(244, 63, 94, 0.2); color: #fb7185; }
        [data-theme="dark"] .bubble-purple { background: rgba(168, 85, 247, 0.2); color: #c084fc; }

        .gov-card-title {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .gov-card-subtitle {
            display: block;
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Card Edit Shortcut Button */
        .gov-card-edit-btn {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .gov-card-edit-btn:hover {
            background: var(--maroon);
            border-color: var(--maroon);
            color: white;
            transform: scale(1.04);
            box-shadow: 0 2px 8px rgba(127, 3, 3, 0.25);
        }
        [data-theme="dark"] .gov-card-edit-btn:hover {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
        }

        /* Card Body & Attribute Layout */
        .gov-card-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .gov-attr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .gov-attr-tile {
            background: var(--badge-bg);
            border: 1px solid rgba(0, 0, 0, 0.04);
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: background 0.2s;
        }
        [data-theme="dark"] .gov-attr-tile {
            border-color: rgba(255, 255, 255, 0.05);
        }
        .gov-attr-tile.tile-featured {
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.03), rgba(201, 168, 108, 0.06));
            border: 1px solid rgba(127, 3, 3, 0.12);
            padding: 12px 16px;
        }
        [data-theme="dark"] .gov-attr-tile.tile-featured {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .tile-col-span {
            grid-column: 1 / -1;
        }
        .gov-attr-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .gov-attr-label i {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .gov-attr-val {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            word-break: break-word;
        }
        .gov-attr-val.val-lg {
            font-size: 1.08rem;
            font-weight: 800;
            color: var(--maroon);
        }
        [data-theme="dark"] .gov-attr-val.val-lg {
            color: #fca5a5;
        }
        .gov-attr-val.val-address {
            font-size: 0.95rem;
            line-height: 1.35;
        }
        .gov-attr-val.val-income {
            color: #059669;
            font-size: 1rem;
            font-weight: 800;
        }
        [data-theme="dark"] .gov-attr-val.val-income {
            color: #34d399;
        }
        .gov-attr-subtext {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Pill Badges */
        .gov-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.74rem;
            font-weight: 700;
        }
        .pill-neutral { background: rgba(0, 0, 0, 0.06); color: var(--text-primary); }
        .pill-male { background: rgba(33, 150, 243, 0.12); color: #1565C0; }
        .pill-female { background: rgba(233, 30, 99, 0.12); color: #C2185B; }
        .pill-info { background: rgba(14, 165, 233, 0.12); color: #0369a1; }
        .pill-success { background: rgba(16, 185, 129, 0.15); color: #065F46; }
        .pill-maroon { background: rgba(127, 3, 3, 0.1); color: var(--maroon); }
        .pill-purple { background: rgba(124, 58, 237, 0.12); color: #6D28D9; }

        [data-theme="dark"] .pill-neutral { background: rgba(255, 255, 255, 0.1); color: #e2e8f0; }
        [data-theme="dark"] .pill-male { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        [data-theme="dark"] .pill-female { background: rgba(244, 63, 94, 0.2); color: #fda4af; }
        [data-theme="dark"] .pill-info { background: rgba(56, 189, 248, 0.2); color: #7dd3fc; }
        [data-theme="dark"] .pill-success { background: rgba(16, 185, 129, 0.25); color: #6ee7b7; }
        [data-theme="dark"] .pill-maroon { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
        [data-theme="dark"] .pill-purple { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; }

        /* Status Summary Tiles for Card 5 */
        .special-status-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }
        .status-summary-tile {
            background: var(--badge-bg);
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.2s;
        }
        .status-summary-tile.is-active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.06), rgba(5, 150, 105, 0.12));
            border-color: #10B981;
        }
        [data-theme="dark"] .status-summary-tile.is-active {
            background: rgba(16, 185, 129, 0.15);
            border-color: #34d399;
        }
        .status-tile-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .status-summary-tile.is-active .status-tile-icon {
            background: #10B981;
            color: white;
        }
        .status-tile-info {
            flex: 1;
        }
        .status-tile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }
        .status-tile-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .status-tile-desc {
            margin: 0;
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.35;
        }
        .gov-card-note {
            background: rgba(0, 0, 0, 0.02);
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.3;
        }
        [data-theme="dark"] .gov-card-note {
            background: rgba(255, 255, 255, 0.02);
        }
        .gov-card-note i {
            color: var(--maroon);
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        [data-theme="dark"] .gov-card-note i {
            color: #fca5a5;
        }

        /* Citizen Declaration Box */
        .citizen-declaration-box {
            background: linear-gradient(135deg, rgba(201, 168, 108, 0.08) 0%, rgba(127, 3, 3, 0.04) 100%);
            border: 1.5px solid rgba(201, 168, 108, 0.35);
            border-radius: var(--radius-card);
            padding: 16px 20px;
            margin-top: 20px;
        }
        .declaration-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }
        .declaration-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: var(--maroon);
            flex-shrink: 0;
        }

        /* Legacy compatibility wrapper */
        .summary-block {
            display: none !important;
        }

        /* Pulse highlight animation for Edit/Adjust jumps */
        @keyframes pulseBorderHighlight {
            0% { box-shadow: 0 0 0 0 rgba(127, 3, 3, 0.5); border-color: var(--maroon); }
            50% { box-shadow: 0 0 0 10px rgba(127, 3, 3, 0); border-color: var(--maroon); }
            100% { box-shadow: 0 0 0 0 rgba(127, 3, 3, 0); }
        }
        .pulse-highlight {
            animation: pulseBorderHighlight 1.5s ease;
        }

        /* Print Styles */
        @media print {
            .portal-sidebar-wrap,
            .portal-sidebar-overlay,
            .portal-page-header,
            .portal-page-subheader,
            .verification-banner,
            .guided-journey-card,
            .verification-stepper,
            .special-category-cards,
            .step-actions,
            .gov-card-edit-btn,
            .btn-print-summary,
            .btn-quick-summary,
            .portal-search-modal-backdrop {
                display: none !important;
            }
            body, .app-container, .main-content {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                color: black !important;
            }
            .profile-form-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .step-content {
                display: block !important;
            }
            .step-content:not([data-fieldset="5"]) {
                display: none !important;
            }
            .cards-layout-container {
                grid-template-columns: 1fr 1fr !important;
                gap: 12px !important;
            }
            .gov-info-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                page-break-inside: avoid;
            }
        }

        /* ========== ACTIONS ROW ========== */
        .step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            gap: 16px;
        }
        .btn-action {
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-action.prev {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .btn-action.prev:hover {
            background: var(--border-color);
        }
        .btn-action.next {
            background: var(--maroon);
            color: white;
            flex: 1;
            justify-content: center;
        }
        .btn-action.next:hover {
            background: var(--maroon-light);
            transform: translateY(-1px);
        }

        .alert-mini {
            background: rgba(244, 67, 54, 0.08);
            border: 1px solid rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            margin-bottom: 12px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        /* Guided Onboarding Journey Card */
        .guided-journey-card {
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.06) 0%, rgba(201, 168, 108, 0.16) 100%);
            border: 1.5px solid rgba(201, 168, 108, 0.4);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            animation: fadeInDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-sm);
        }
        .guided-journey-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--maroon), #9a1515);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3);
            animation: bounceIn 0.5s ease;
        }
        .journey-badge-pill {
            display: inline-block;
            padding: 3px 10px;
            background: linear-gradient(135deg, #7F0303, #9a1515);
            color: white;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .guided-journey-content h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px;
        }
        .guided-journey-content p {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.4;
            margin: 0;
        }
        .journey-flow-trail {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .flow-step {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .flow-step.done {
            background: #D1FAE5;
            color: #059669;
        }
        .flow-step.current {
            background: linear-gradient(135deg, #7F0303, #A31212);
            color: white;
            box-shadow: 0 3px 8px rgba(127, 3, 3, 0.35);
            animation: currentStepPulse 2s infinite ease-in-out;
        }
        @keyframes currentStepPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .flow-step.upcoming {
            background: var(--badge-bg);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        .flow-arrow {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        /* Glowing Action Button */
        @keyframes guidedEmeraldPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0.7), 0 4px 14px rgba(127, 3, 3, 0.3);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(127, 3, 3, 0), 0 6px 20px rgba(127, 3, 3, 0.45);
                transform: scale(1.025);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(127, 3, 3, 0), 0 4px 14px rgba(127, 3, 3, 0.3);
                transform: scale(1);
            }
        }
        .guided-btn-glow-emerald {
            animation: guidedEmeraldPulse 2s infinite ease-in-out !important;
            position: relative;
        }
        .guided-pointer-pill {
            position: absolute;
            top: -12px;
            right: 18px;
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
            z-index: 5;
        }
        @keyframes floatPointer {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .verification-banner { flex-direction: column; text-align: center; }
            .verification-banner .left { justify-content: center; }
            .profile-form-container { padding: 20px; }
            .form-section { padding-bottom: 16px; }
            .checkbox-group { flex-direction: column; gap: 10px; }
            .btn-primary { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .profile-form-container { padding: 16px; }
            .verification-banner { padding: 14px 16px; }
        }

        /* ========== SAVE SUCCESS & PROCEED MODAL ========== */
        .save-success-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .save-success-modal-backdrop.active {
            display: flex;
            opacity: 1;
            animation: saveModalFadeIn 0.3s ease forwards;
        }
        @keyframes saveModalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes saveModalSlideUp {
            from {
                opacity: 0;
                transform: translateY(28px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .save-success-modal-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid rgba(127, 3, 3, 0.16);
            border-radius: 26px;
            max-width: 490px;
            width: 100%;
            padding: 38px 32px 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            animation: saveModalSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        [data-theme="dark"] .save-success-modal-card {
            background: #1a2332;
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
        }
        .save-modal-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-muted, #64748b);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }
        [data-theme="dark"] .save-modal-close-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #94a3b8;
        }
        .save-modal-close-btn:hover {
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon, #7F0303);
            transform: rotate(90deg);
        }
        .save-modal-badge-wrapper {
            position: relative;
            width: 78px;
            height: 78px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .save-modal-icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.38);
            position: relative;
            z-index: 2;
        }
        .save-modal-badge-pulse {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.22);
            animation: saveIconPulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes saveIconPulse {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 0.3; }
        }
        .save-modal-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 50px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 10px;
        }
        [data-theme="dark"] .save-modal-tag {
            background: rgba(16, 185, 129, 0.2);
            color: #34D399;
        }
        .save-modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary, #1e293b);
            margin: 0 0 10px;
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            letter-spacing: -0.4px;
        }
        [data-theme="dark"] .save-modal-title {
            color: #ffffff;
        }
        .save-modal-desc {
            font-size: 0.92rem;
            line-height: 1.55;
            color: var(--text-muted, #64748b);
            margin: 0 0 24px;
            padding: 0 10px;
        }
        [data-theme="dark"] .save-modal-desc {
            color: #94a3b8;
        }
        .save-modal-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .save-modal-btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 22px;
            border-radius: 50px;
            background: linear-gradient(135deg, #7F0303 0%, #a80a0a 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            box-shadow: 0 8px 22px rgba(127, 3, 3, 0.32);
            transition: all 0.25s ease;
            box-sizing: border-box;
        }
        .save-modal-btn-primary:hover {
            background: linear-gradient(135deg, #960404 0%, #b80d0d 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(127, 3, 3, 0.42);
            color: #ffffff !important;
        }
        .save-modal-btn-primary:active {
            transform: translateY(0);
        }
        .save-modal-secondary-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .save-modal-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 11px 16px;
            border-radius: 50px;
            border: 1.5px solid var(--border-color, #C8D0DC);
            background: transparent;
            color: var(--text-primary, #1e293b);
            font-weight: 600;
            font-size: 0.82rem;
            text-decoration: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        [data-theme="dark"] .save-modal-btn-outline {
            border-color: rgba(255, 255, 255, 0.15);
            color: #E8EDF5;
        }
        .save-modal-btn-outline:hover {
            background: rgba(0, 0, 0, 0.04);
            border-color: var(--maroon, #7F0303);
            color: var(--maroon, #7F0303);
        }
        [data-theme="dark"] .save-modal-btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #ff7b7b;
            color: #ff7b7b;
        }
        .save-modal-btn-ghost {
            padding: 11px 16px;
            border-radius: 50px;
            border: none;
            background: rgba(0, 0, 0, 0.04);
            color: var(--text-muted, #64748b);
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        [data-theme="dark"] .save-modal-btn-ghost {
            background: rgba(255, 255, 255, 0.06);
            color: #94a3b8;
        }
        .save-modal-btn-ghost:hover {
            background: rgba(0, 0, 0, 0.08);
            color: var(--text-primary, #1e293b);
        }
        [data-theme="dark"] .save-modal-btn-ghost:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        @media (max-width: 520px) {
            .save-success-modal-card {
                padding: 30px 20px 24px;
                border-radius: 20px;
            }
            .save-modal-secondary-group {
                grid-template-columns: 1fr;
            }
            .save-modal-title {
                font-size: 1.35rem;
            }
            .save-modal-btn-primary {
                font-size: 0.88rem;
                padding: 12px 18px;
            }
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
            $banner_title = "Complete Your Profile";
            $banner_subtitle = "Fill out your complete personal demographic details to access city services";
            $banner_icon = "fa-user-edit";
            $banner_actions = [
                ["label" => "View Profile Overview", "url" => "profile.php", "icon" => "fa-user-circle", "type" => "primary"]
            ];
            if (!isStaff() && !isAdmin()) {
                $banner_actions[] = ["label" => "Verify Residency", "url" => "verify-resident.php", "icon" => "fa-id-card", "type" => "secondary"];
            }
            include __DIR__ . "/includes/portal-banner.php";
            ?>

        <?php if (!isStaff() && !isAdmin()): ?>
        <!-- Verification Status Banner -->
        <div class="verification-banner">
            <div class="left">
                <strong><i class="fas fa-shield-alt"></i> Verification Status:</strong>
                <span class="verification-status status-<?php echo $verification_status; ?>">
                    <i class="fas fa-<?php 
                        echo $verification_status == 'verified' ? 'check-circle' : 
                            ($verification_status == 'pending' ? 'clock' : 'times-circle'); 
                    ?>"></i>
                    <?php echo ucfirst($verification_status); ?>
                </span>
            </div>
            <?php if($verification_status != 'verified'): ?>
            <a href="verify-resident.php" class="verify-link">
                <i class="fas fa-shield-alt"></i> Complete Verification
            </a>
            <?php endif; ?>
        </div>

        <!-- Warning for non-verified users -->
        <?php if($verification_status != 'verified'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Verification Required!</strong> You must complete your residency verification before you can update your profile information. 
            Click the "Complete Verification" button above to proceed.
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!isStaff() && !isAdmin() && !$is_100_complete): ?>
        <!-- Guided Step 2 Banner (Only shown during onboarding before 100% completion) -->
        <div class="guided-journey-card">
            <div class="guided-journey-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div class="guided-journey-content">
                <div class="journey-badge-pill">Step 2 of 3 • Citizen Demographics</div>
                <h3>Step 2: Complete Your Resident Profile 📝</h3>
                <p>Fill out your household, civil status, and emergency details below. Once saved, you will be guided to upload your profile photo to complete onboarding.</p>
                <div class="journey-flow-trail">
                    <span class="flow-step done"><i class="fas fa-check"></i> 1. Verify Account</span>
                    <i class="fas fa-arrow-right flow-arrow"></i>
                    <span class="flow-step current"><i class="fas fa-user-edit"></i> 2. Complete Profile</span>
                    <i class="fas fa-arrow-right flow-arrow"></i>
                    <span class="flow-step upcoming"><i class="fas fa-camera"></i> 3. Upload Photo</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="profile-form-container">
            <div class="form-header">
                <i class="fas fa-user-edit"></i>
                <h2>Profile Information</h2>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <?php if (!$has_avatar): ?>
                    <div class="alert alert-success" style="display: flex; flex-direction: column; gap: 10px; padding: 18px 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 0.95rem;">
                            <i class="fas fa-check-circle" style="color: #7F0303; font-size: 1.25rem;"></i> <?php echo $success; ?>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; border-top: 1px dashed rgba(127, 3, 3, 0.25); padding-top: 10px; margin-top: 4px;">
                            <span style="font-size: 0.85rem; color: #7F0303;">
                                <strong>Great job!</strong> Proceed to Step 3 to upload your official resident portrait photo.
                            </span>
                            <a href="profile.php?guided=1" class="btn-action next guided-btn-glow-emerald" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 9px 20px; font-size: 0.85rem; font-weight: 700; background: linear-gradient(135deg, #7F0303, #A31212); color: white; border-radius: 40px;">
                                <span>Proceed to Step 3: Upload Photo</span> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if(!$existing_resident): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>Complete your profile to proceed.</strong> Your information will be kept secure and confidential.
            </div>
            <?php endif; ?>

            <?php if($verification_status == 'verified'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>You are verified!</strong> You can now update your profile information.
            </div>
            <?php elseif($verification_status == 'rejected'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <strong>Verification rejected.</strong> Please contact CSWDO for assistance.
            </div>
            <?php endif; ?>

            <!-- Profile Stepper -->
            <div class="verification-stepper">
                <div class="step-item active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step-line" data-line="1"></div>
                <div class="step-item" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Address Details</div>
                </div>
                <div class="step-line" data-line="2"></div>
                <div class="step-item" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Economic Info</div>
                </div>
                <div class="step-line" data-line="3"></div>
                <div class="step-item" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Emergency Details</div>
                </div>
                <div class="step-line" data-line="4"></div>
                <div class="step-item" data-step="5">
                    <div class="step-circle">5</div>
                    <div class="step-label">Review & Save</div>
                </div>
            </div>

            <!-- Live validation warning alert -->
            <div class="alert-mini" id="stepAlert"><i class="fas fa-exclamation-triangle"></i> <span id="stepAlertText"></span></div>

            <form method="POST" action="" id="profileForm">
                <!-- STEP 1: Personal Information -->
                <fieldset class="step-content active" data-fieldset="1">
                    <div class="form-section">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name <span class="required-star">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($resident['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required-star">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($resident['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="<?php echo htmlspecialchars($resident['middle_name'] ?? ''); ?>" >
                            </div>
                            <div class="form-group">
                                <label>Suffix</label>
                                <select name="suffix" id="suffix" >
                                    <option value="">None</option>
                                    <option value="Jr." <?php echo ($resident['suffix'] ?? '') == 'Jr.' ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($resident['suffix'] ?? '') == 'Sr.' ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($resident['suffix'] ?? '') == 'III' ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($resident['suffix'] ?? '') == 'IV' ? 'selected' : ''; ?>>IV</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gender <span class="required-star">*</span></label>
                                <select name="gender" id="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($resident['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($resident['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($resident['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Birth Date <span class="required-star">*</span></label>
                                <input type="date" name="birth_date" id="birth_date" value="<?php echo htmlspecialchars($resident['birth_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Civil Status <span class="required-star">*</span></label>
                                <select name="civil_status" id="civil_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Single" <?php echo ($resident['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Married" <?php echo ($resident['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                    <option value="Widowed" <?php echo ($resident['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="Divorced" <?php echo ($resident['civil_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Separated" <?php echo ($resident['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Contact Number <span class="required-star">*</span></label>
                                <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($resident['contact_number'] ?? ''); ?>" placeholder="09123456789" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($resident['email'] ?? $user['email']); ?>" readonly class="disabled-field">
                            <small>Email cannot be changed</small>
                        </div>
                    </div>
                </fieldset>

                <!-- STEP 2: Address Information -->
                <fieldset class="step-content" data-fieldset="2">
                    <div class="form-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Street/Block/Lot <span class="required-star">*</span></label>
                                <input type="text" name="street" id="street" value="<?php echo htmlspecialchars($resident['street'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Barangay <span class="required-star">*</span></label>
                                <input type="text" name="barangay" id="barangay" value="<?php echo htmlspecialchars($resident['barangay'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>City/Municipality <span class="required-star">*</span></label>
                                <input type="text" name="city" id="city" value="<?php echo htmlspecialchars($resident['city'] ?? 'Palayan City'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Province <span class="required-star">*</span></label>
                                <input type="text" name="province" id="province" value="<?php echo htmlspecialchars($resident['province'] ?? 'Nueva Ecija'); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Postal Code</label>
                                <input type="text" name="postal_code" id="postal_code" value="<?php echo htmlspecialchars($resident['postal_code'] ?? '3132'); ?>" >
                            </div>
                            <div class="form-group">
                                <label>Household Number</label>
                                <input type="text" name="household_number" id="household_number" value="<?php echo htmlspecialchars($resident['household_number'] ?? ''); ?>" placeholder="Optional" >
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- STEP 3: Employment & Economic Information -->
                <fieldset class="step-content" data-fieldset="3">
                    <div class="form-section">
                        <h3><i class="fas fa-briefcase"></i> Employment & Economic Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Occupation</label>
                                <input type="text" name="occupation" id="occupation" value="<?php echo htmlspecialchars($resident['occupation'] ?? ''); ?>" >
                            </div>
                            <div class="form-group">
                                <label>Monthly Income (₱)</label>
                                <input type="number" name="monthly_income" id="monthly_income" step="0.01" value="<?php echo htmlspecialchars($resident['monthly_income'] ?? 0); ?>" >
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Educational Attainment</label>
                            <select name="educational_attainment" id="educational_attainment" >
                                <option value="">Select</option>
                                <option value="No Formal Education" <?php echo ($resident['educational_attainment'] ?? '') == 'No Formal Education' ? 'selected' : ''; ?>>No Formal Education</option>
                                <option value="Elementary Level" <?php echo ($resident['educational_attainment'] ?? '') == 'Elementary Level' ? 'selected' : ''; ?>>Elementary Level</option>
                                <option value="Elementary Graduate" <?php echo ($resident['educational_attainment'] ?? '') == 'Elementary Graduate' ? 'selected' : ''; ?>>Elementary Graduate</option>
                                <option value="High School Level" <?php echo ($resident['educational_attainment'] ?? '') == 'High School Level' ? 'selected' : ''; ?>>High School Level</option>
                                <option value="High School Graduate" <?php echo ($resident['educational_attainment'] ?? '') == 'High School Graduate' ? 'selected' : ''; ?>>High School Graduate</option>
                                <option value="College Level" <?php echo ($resident['educational_attainment'] ?? '') == 'College Level' ? 'selected' : ''; ?>>College Level</option>
                                <option value="College Graduate" <?php echo ($resident['educational_attainment'] ?? '') == 'College Graduate' ? 'selected' : ''; ?>>College Graduate</option>
                                <option value="Post Graduate" <?php echo ($resident['educational_attainment'] ?? '') == 'Post Graduate' ? 'selected' : ''; ?>>Post Graduate</option>
                                <option value="Vocational" <?php echo ($resident['educational_attainment'] ?? '') == 'Vocational' ? 'selected' : ''; ?>>Vocational</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- STEP 4: Emergency Contact -->
                <fieldset class="step-content" data-fieldset="4">
                    <div class="form-section">
                        <h3><i class="fas fa-phone-alt"></i> Emergency Contact</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo htmlspecialchars($resident['emergency_contact_name'] ?? ''); ?>" >
                            </div>
                            <div class="form-group">
                                <label>Emergency Contact Number</label>
                                <input type="text" name="emergency_contact_number" id="emergency_contact_number" value="<?php echo htmlspecialchars($resident['emergency_contact_number'] ?? ''); ?>" >
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Relationship to Emergency Contact</label>
                            <input type="text" name="relationship" id="relationship" value="<?php echo htmlspecialchars($resident['relationship'] ?? ''); ?>" >
                        </div>
                    </div>
                </fieldset>

                <!-- STEP 5: Special Categories & Summary Review -->
                <fieldset class="step-content" data-fieldset="5">
                    
                    <!-- Form Section: Special Categories Interactive Selector -->
                    <div class="form-section" style="border-bottom: none; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                            <h3 style="margin: 0;"><i class="fas fa-tags"></i> Special Categories & Welfare Privileges</h3>
                            <span style="font-size: 0.74rem; color: var(--text-muted); font-weight: 500;">Select any applicable classification for this resident</span>
                        </div>

                        <div class="special-category-cards">
                            <!-- Senior Citizen Interactive Card -->
                            <div class="special-category-card <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'is-selected' : ''; ?>" id="cardSeniorSelect" onclick="toggleCategoryCheckbox('senior_citizen')">
                                <div class="sc-icon"><i class="fas fa-person-cane"></i></div>
                                <div class="sc-details">
                                    <div class="sc-title-row">
                                        <h4>Senior Citizen (OSCA)</h4>
                                        <span class="sc-status-pill <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'pill-active' : ''; ?>" id="seniorStatusPill">
                                            <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'Registered' : 'Not Claimed'; ?>
                                        </span>
                                    </div>
                                    <p>Republic Act 9994. Entitles qualified senior citizens (aged 60+) to 20% discounts, medicine subsidies, and OSCA pension programs.</p>
                                </div>
                                <div class="sc-checkbox-wrapper" onclick="event.stopPropagation()">
                                    <input type="checkbox" name="senior_citizen" id="senior_citizen" value="1" <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'checked' : ''; ?> onchange="syncCategoryCard('senior_citizen')">
                                </div>
                            </div>

                            <!-- PWD Interactive Card -->
                            <div class="special-category-card <?php echo ($resident['pwd'] ?? 0) == 1 ? 'is-selected' : ''; ?>" id="cardPwdSelect" onclick="toggleCategoryCheckbox('pwd')">
                                <div class="sc-icon"><i class="fas fa-wheelchair"></i></div>
                                <div class="sc-details">
                                    <div class="sc-title-row">
                                        <h4>Person with Disability (PWD)</h4>
                                        <span class="sc-status-pill <?php echo ($resident['pwd'] ?? 0) == 1 ? 'pill-active' : ''; ?>" id="pwdStatusPill">
                                            <?php echo ($resident['pwd'] ?? 0) == 1 ? 'Registered' : 'Not Claimed'; ?>
                                        </span>
                                    </div>
                                    <p>Republic Act 10754. Entitles cardholders to 20% discounts, priority lane access, and assistive healthcare support desks.</p>
                                </div>
                                <div class="sc-checkbox-wrapper" onclick="event.stopPropagation()">
                                    <input type="checkbox" name="pwd" id="pwd" value="1" <?php echo ($resident['pwd'] ?? 0) == 1 ? 'checked' : ''; ?> onchange="syncCategoryCard('pwd')">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FIVE CLEAN INFORMATION CARDS SUMMARY BLOCK -->
                    <div class="summary-review-wrapper">
                        <!-- Official Review Hero Banner -->
                        <div class="review-hero-banner">
                            <div class="review-hero-left">
                                <div class="review-seal-icon">
                                    <i class="fas fa-id-card-clip"></i>
                                </div>
                                <div class="review-hero-text">
                                    <div class="review-hero-tag">Official Citizen Record</div>
                                    <h3>Resident Profile Summary Review</h3>
                                    <p>Please review your five information categories before finalizing. Click "Edit" on any card to update specific details.</p>
                                </div>
                            </div>
                            <div class="review-hero-right">
                                <div class="resident-id-badge">
                                    <span class="id-label">Citizen Record ID</span>
                                    <span class="id-val"><?php echo htmlspecialchars($existing_resident['resident_number'] ?? 'NEW-RESIDENT'); ?></span>
                                </div>
                                <button type="button" class="btn-print-summary" onclick="window.print()" title="Print Profile Summary">
                                    <i class="fas fa-print"></i> Print Summary
                                </button>
                            </div>
                        </div>

                        <!-- 5 Clean Information Cards Container -->
                        <div class="cards-layout-container">
                            
                            <!-- CARD 1: Personal Details -->
                            <div class="gov-info-card card-personal" id="cardPersonal">
                                <div class="gov-card-header">
                                    <div class="gov-card-header-main">
                                        <div class="gov-card-icon-bubble bubble-maroon">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div>
                                            <h4 class="gov-card-title">Personal Details</h4>
                                            <span class="gov-card-subtitle">Official identity & civil demographics</span>
                                        </div>
                                    </div>
                                    <button type="button" class="gov-card-edit-btn" onclick="goToStep(1)" title="Edit Personal Details">
                                        <i class="fas fa-pen-to-square"></i> Edit
                                    </button>
                                </div>
                                <div class="gov-card-body">
                                    <div class="gov-attr-tile tile-featured">
                                        <span class="gov-attr-label"><i class="fas fa-signature"></i> Full Legal Name</span>
                                        <span class="gov-attr-val val-lg" id="sumFullName">-</span>
                                    </div>
                                    <div class="gov-attr-grid">
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-venus-mars"></i> Gender</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-neutral" id="sumGender">-</span>
                                            </div>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-cake-candles"></i> Birth Date</span>
                                            <span class="gov-attr-val font-mono" id="sumBirthDate">-</span>
                                            <small class="gov-attr-subtext" id="sumAgeBadge"></small>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-ring"></i> Civil Status</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-neutral" id="sumCivilStatus">-</span>
                                            </div>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-mobile-screen-button"></i> Contact Number</span>
                                            <span class="gov-attr-val font-mono" id="sumContact">-</span>
                                        </div>
                                        <div class="gov-attr-tile tile-col-span">
                                            <span class="gov-attr-label"><i class="fas fa-envelope"></i> Linked Account Email</span>
                                            <span class="gov-attr-val" id="sumEmail"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2: Address Details -->
                            <div class="gov-info-card card-address" id="cardAddress">
                                <div class="gov-card-header">
                                    <div class="gov-card-header-main">
                                        <div class="gov-card-icon-bubble bubble-emerald">
                                            <i class="fas fa-map-location-dot"></i>
                                        </div>
                                        <div>
                                            <h4 class="gov-card-title">Address Details</h4>
                                            <span class="gov-card-subtitle">Residential location & household</span>
                                        </div>
                                    </div>
                                    <button type="button" class="gov-card-edit-btn" onclick="goToStep(2)" title="Edit Address Details">
                                        <i class="fas fa-pen-to-square"></i> Edit
                                    </button>
                                </div>
                                <div class="gov-card-body">
                                    <div class="gov-attr-tile tile-featured">
                                        <span class="gov-attr-label"><i class="fas fa-road"></i> Complete Residential Address</span>
                                        <span class="gov-attr-val val-address" id="sumAddress">-</span>
                                    </div>
                                    <div class="gov-attr-grid">
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-house-user"></i> Household Number</span>
                                            <span class="gov-attr-val font-mono" id="sumHousehold">-</span>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-landmark"></i> Jurisdiction</span>
                                            <span class="gov-attr-val">Palayan City, Nueva Ecija</span>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-mail-bulk"></i> Postal Code</span>
                                            <span class="gov-attr-val font-mono" id="sumPostal">3132</span>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-shield-halved"></i> Residency Status</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-success"><i class="fas fa-check-circle"></i> Registered Resident</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 3: Economic Info -->
                            <div class="gov-info-card card-economic" id="cardEconomic">
                                <div class="gov-card-header">
                                    <div class="gov-card-header-main">
                                        <div class="gov-card-icon-bubble bubble-gold">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div>
                                            <h4 class="gov-card-title">Economic Info</h4>
                                            <span class="gov-card-subtitle">Livelihood & educational profile</span>
                                        </div>
                                    </div>
                                    <button type="button" class="gov-card-edit-btn" onclick="goToStep(3)" title="Edit Economic Details">
                                        <i class="fas fa-pen-to-square"></i> Edit
                                    </button>
                                </div>
                                <div class="gov-card-body">
                                    <div class="gov-attr-grid">
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-user-tag"></i> Occupation</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-info" id="sumOccupation">-</span>
                                            </div>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-peso-sign"></i> Declared Monthly Income</span>
                                            <span class="gov-attr-val font-mono val-income" id="sumIncome">₱0.00</span>
                                        </div>
                                        <div class="gov-attr-tile tile-col-span">
                                            <span class="gov-attr-label"><i class="fas fa-graduation-cap"></i> Educational Attainment</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-neutral" id="sumEducation">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="gov-card-note">
                                        <i class="fas fa-circle-info"></i>
                                        <span>Income data assists CSWDO caseworkers in calibrating welfare allowances and evaluating financial eligibility.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 4: Emergency Info -->
                            <div class="gov-info-card card-emergency" id="cardEmergency">
                                <div class="gov-card-header">
                                    <div class="gov-card-header-main">
                                        <div class="gov-card-icon-bubble bubble-rose">
                                            <i class="fas fa-phone-volume"></i>
                                        </div>
                                        <div>
                                            <h4 class="gov-card-title">Emergency Info</h4>
                                            <span class="gov-card-subtitle">Primary designated next-of-kin</span>
                                        </div>
                                    </div>
                                    <button type="button" class="gov-card-edit-btn" onclick="goToStep(4)" title="Edit Emergency Details">
                                        <i class="fas fa-pen-to-square"></i> Edit
                                    </button>
                                </div>
                                <div class="gov-card-body">
                                    <div class="gov-attr-tile tile-featured">
                                        <span class="gov-attr-label"><i class="fas fa-user-shield"></i> Emergency Contact Person</span>
                                        <span class="gov-attr-val val-lg" id="sumEmergName">-</span>
                                    </div>
                                    <div class="gov-attr-grid">
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-people-roof"></i> Relationship</span>
                                            <div class="gov-attr-val">
                                                <span class="gov-pill-badge pill-maroon" id="sumRelationship">-</span>
                                            </div>
                                        </div>
                                        <div class="gov-attr-tile">
                                            <span class="gov-attr-label"><i class="fas fa-phone-flip"></i> Emergency Hotline</span>
                                            <span class="gov-attr-val font-mono" id="sumEmergContact">-</span>
                                        </div>
                                    </div>
                                    <div class="gov-card-note">
                                        <i class="fas fa-bell"></i>
                                        <span>Authorized to receive official emergency notices, disaster relief stubs, and urgent welfare alerts.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 5: Special Categories -->
                            <div class="gov-info-card card-special" id="cardSpecial">
                                <div class="gov-card-header">
                                    <div class="gov-card-header-main">
                                        <div class="gov-card-icon-bubble bubble-purple">
                                            <i class="fas fa-tags"></i>
                                        </div>
                                        <div>
                                            <h4 class="gov-card-title">Special Categories</h4>
                                            <span class="gov-card-subtitle">Government welfare benefits & entitlements</span>
                                        </div>
                                    </div>
                                    <button type="button" class="gov-card-edit-btn" onclick="focusSpecialCategorySelector()" title="Adjust Special Categories">
                                        <i class="fas fa-sliders"></i> Adjust
                                    </button>
                                </div>
                                <div class="gov-card-body">
                                    <div class="special-status-tiles">
                                        <!-- Senior Citizen Tile -->
                                        <div class="status-summary-tile" id="seniorSummaryTile">
                                            <div class="status-tile-icon" id="seniorTileIcon">
                                                <i class="fas fa-person-cane"></i>
                                            </div>
                                            <div class="status-tile-info">
                                                <div class="status-tile-header">
                                                    <span class="status-tile-label">Senior Citizen (RA 9994)</span>
                                                    <span class="gov-pill-badge pill-neutral" id="sumSeniorBadge">No</span>
                                                </div>
                                                <p class="status-tile-desc" id="seniorTileDesc">Under 60 years old. Not classified under Senior Citizen privileges.</p>
                                            </div>
                                        </div>

                                        <!-- PWD Tile -->
                                        <div class="status-summary-tile" id="pwdSummaryTile">
                                            <div class="status-tile-icon" id="pwdTileIcon">
                                                <i class="fas fa-wheelchair"></i>
                                            </div>
                                            <div class="status-tile-info">
                                                <div class="status-tile-header">
                                                    <span class="status-tile-label">Person with Disability (RA 10754)</span>
                                                    <span class="gov-pill-badge pill-neutral" id="sumPwdBadge">No</span>
                                                </div>
                                                <p class="status-tile-desc" id="pwdTileDesc">Regular resident record without disability classification.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="gov-card-note" style="margin-top: 10px;">
                                        <i class="fas fa-award"></i>
                                        <span>Special category benefits are verified by CSWDO desk officers upon presentation of supporting documents.</span>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /cards-layout-container -->

                        <!-- Official Citizen Attestation Box -->
                        <div class="citizen-declaration-box">
                            <label class="declaration-label">
                                <input type="checkbox" id="confirmAccuracy" checked required>
                                <span>
                                    <strong>Official Citizen Certification:</strong> I hereby certify under penalty of perjury that all information provided across these Five Information Cards is true, complete, and legally correct. I consent to CSWDO processing my demographic record in accordance with the <em>Data Privacy Act of 2012 (RA 10173)</em>.
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Hidden legacy block to guarantee zero JS reference errors -->
                    <div class="summary-block" style="display: none !important;">
                        <span id="sumSenior">No</span>
                        <span id="sumPwd">No</span>
                    </div>

                </fieldset>

                <!-- STEPPER NAVIGATION BUTTONS -->
                <div class="step-actions">
                    <button type="button" class="btn-action prev" id="prevBtn" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button type="button" class="btn-action next <?php echo !$is_100_complete ? 'guided-btn-glow-emerald' : ''; ?>" id="nextBtn">
                        <?php if (!$is_100_complete): ?>
                            <span class="guided-pointer-pill" style="top: -12px; right: 12px;"><i class="fas fa-hand-point-right"></i> Next Step</span>
                        <?php endif; ?>
                        Next Step <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <?php if($verification_status == 'verified'): ?>
                    <button type="submit" name="update_profile_info" class="btn-action next <?php echo !$is_100_complete ? 'guided-btn-glow-emerald' : ''; ?>" id="submitBtn" style="display: none;">
                        <?php if (!$is_100_complete): ?>
                            <span class="guided-pointer-pill" style="top: -12px; right: 12px;"><i class="fas fa-check"></i> Save &amp; Finish</span>
                        <?php endif; ?>
                        <i class="fas fa-save"></i> Save Profile Details
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn-action next" id="submitBtn" style="display: none;" disabled>
                        <i class="fas fa-lock"></i> Verification Required
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

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

// ========== STEPPER SYSTEM & 5 INFORMATION CARDS LOGIC ==========
let currentStep = 1;
const totalSteps = 5;

// Check URL parameters for direct step navigation (e.g. ?step=5 or ?review=1)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('step') === '5' || urlParams.get('review') === '1') {
    currentStep = 5;
}

const profileForm = document.getElementById('profileForm');
if (profileForm) {
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    const stepAlert = document.getElementById('stepAlert');
    const stepAlertText = document.getElementById('stepAlertText');

    function showAlert(message) {
        stepAlertText.textContent = message;
        stepAlert.style.display = 'flex';
        window.scrollTo({ top: stepAlert.offsetTop - 100, behavior: 'smooth' });
    }

    function hideAlert() {
        stepAlert.style.display = 'none';
    }

    function updateStepperUI() {
        hideAlert();
        document.querySelectorAll('.step-item').forEach((item, index) => {
            const stepNum = index + 1;
            item.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                item.classList.add('active');
            } else if (stepNum < currentStep) {
                item.classList.add('completed');
            }
        });

        document.querySelectorAll('.step-line').forEach((line, index) => {
            const lineNum = index + 1;
            line.classList.remove('filled');
            if (lineNum < currentStep) {
                line.classList.add('filled');
            }
        });

        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.remove('active');
        });
        const activeContent = document.querySelector(`.step-content[data-fieldset="${currentStep}"]`);
        if (activeContent) {
            activeContent.classList.add('active');
        }

        // Control buttons display
        if (currentStep === 1) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
        } else if (currentStep === totalSteps) {
            prevBtn.style.display = 'inline-flex';
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
            buildSummary();
        } else {
            prevBtn.style.display = 'inline-flex';
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
        }
    }

    // Step validation checks
    function validateCurrentStep() {
        if (currentStep === 1) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const gender = document.getElementById('gender').value;
            const birthDate = document.getElementById('birth_date').value;
            const civilStatus = document.getElementById('civil_status').value;
            const contactNumber = document.getElementById('contact_number').value.trim();

            if (!firstName) {
                showAlert("Please enter your First Name.");
                return false;
            }
            if (!lastName) {
                showAlert("Please enter your Last Name.");
                return false;
            }
            if (!gender) {
                showAlert("Please select your Gender.");
                return false;
            }
            if (!birthDate) {
                showAlert("Please enter your Birth Date.");
                return false;
            }
            if (!civilStatus) {
                showAlert("Please select your Civil Status.");
                return false;
            }
            if (!contactNumber) {
                showAlert("Please enter your Contact Number.");
                return false;
            }
            return true;
        }

        if (currentStep === 2) {
            const street = document.getElementById('street').value.trim();
            const barangay = document.getElementById('barangay').value.trim();
            const city = document.getElementById('city').value.trim();
            const province = document.getElementById('province').value.trim();

            if (!street) {
                showAlert("Please enter your Street/Block/Lot.");
                return false;
            }
            if (!barangay) {
                showAlert("Please enter your Barangay.");
                return false;
            }
            if (!city) {
                showAlert("Please enter your City/Municipality.");
                return false;
            }
            if (!province) {
                showAlert("Please enter your Province.");
                return false;
            }
            return true;
        }

        return true;
    }

    // Direct Step Navigation function for buttons, cards, and stepper circles
    window.goToStep = function(stepNum) {
        stepNum = parseInt(stepNum, 10);
        if (stepNum >= 1 && stepNum <= totalSteps) {
            if (stepNum > currentStep && !validateCurrentStep()) {
                return;
            }
            currentStep = stepNum;
            updateStepperUI();
            const stepperEl = document.querySelector('.verification-stepper');
            if (stepperEl) {
                window.scrollTo({ top: stepperEl.offsetTop - 40, behavior: 'smooth' });
            }
        }
    };

    // Attach click listeners to stepper items
    document.querySelectorAll('.step-item').forEach((item, index) => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', () => {
            window.goToStep(index + 1);
        });
    });

    nextBtn.addEventListener('click', () => {
        if (validateCurrentStep()) {
            currentStep++;
            updateStepperUI();
        }
    });

    prevBtn.addEventListener('click', () => {
        currentStep--;
        updateStepperUI();
    });

    // ========== SPECIAL CATEGORIES INTERACTIVE SYNC ==========
    window.toggleCategoryCheckbox = function(id) {
        const cb = document.getElementById(id);
        if (cb) {
            if (id === 'senior_citizen' && !cb.checked) {
                const bDateVal = document.getElementById('birth_date')?.value;
                if (bDateVal) {
                    const b = new Date(bDateVal);
                    const today = new Date();
                    let a = today.getFullYear() - b.getFullYear();
                    const m = today.getMonth() - b.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < b.getDate())) a--;
                    if (a < 60) {
                        alert('Ayon sa RA 9994, ang Senior Citizen status ay para lamang sa mga may edad 60 pataas. Kasalukuyang edad: ' + (a >= 0 ? a : 0) + ' taong gulang.');
                        return;
                    }
                }
            }
            cb.checked = !cb.checked;
            window.syncCategoryCard(id);
        }
    };

    window.syncCategoryCard = function(id) {
        const cb = document.getElementById(id);
        if (!cb) return;

        const isSenior = (id === 'senior_citizen');
        const card = isSenior ? document.getElementById('cardSeniorSelect') : document.getElementById('cardPwdSelect');
        const pill = isSenior ? document.getElementById('seniorStatusPill') : document.getElementById('pwdStatusPill');

        if (card) {
            if (cb.checked) {
                card.classList.add('is-selected');
                if (pill) {
                    pill.textContent = isSenior ? 'Auto-Detected (Age 60+)' : 'Registered';
                    pill.className = 'sc-status-pill pill-active';
                }
            } else {
                card.classList.remove('is-selected');
                if (pill) {
                    pill.textContent = 'Not Claimed';
                    pill.className = 'sc-status-pill';
                }
            }
        }
        buildSummary();
    };

    // Auto-detect Senior Citizen eligibility whenever Birth Date is selected
    function checkBirthDateSenior() {
        const bdateInput = document.getElementById('birth_date');
        const seniorCb = document.getElementById('senior_citizen');
        const card = document.getElementById('cardSeniorSelect');
        const pill = document.getElementById('seniorStatusPill');
        if (!bdateInput || !seniorCb) return;

        const val = bdateInput.value;
        if (!val) return;

        const bDate = new Date(val);
        const today = new Date();
        let age = today.getFullYear() - bDate.getFullYear();
        const m = today.getMonth() - bDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < bDate.getDate())) {
            age--;
        }

        if (age >= 60) {
            seniorCb.checked = true;
            if (card) card.classList.add('is-selected');
            if (pill) {
                pill.textContent = 'Auto-Detected (' + age + ' yrs old)';
                pill.className = 'sc-status-pill pill-active';
            }
        } else {
            seniorCb.checked = false;
            if (card) card.classList.remove('is-selected');
            if (pill) {
                pill.textContent = 'Not Eligible (' + (age >= 0 ? age : 0) + ' yrs old)';
                pill.className = 'sc-status-pill';
            }
        }
        buildSummary();
    }

    const birthDateInput = document.getElementById('birth_date');
    if (birthDateInput) {
        birthDateInput.addEventListener('change', checkBirthDateSenior);
        if (birthDateInput.value) {
            checkBirthDateSenior();
        }
    }

    window.focusSpecialCategorySelector = function() {
        const selector = document.querySelector('.special-category-cards');
        if (selector) {
            selector.scrollIntoView({ behavior: 'smooth', block: 'center' });
            selector.classList.add('pulse-highlight');
            setTimeout(() => selector.classList.remove('pulse-highlight'), 1600);
        }
    };

    // ========== BUILD FIVE CLEAN INFORMATION CARDS SUMMARY ==========
    function buildSummary() {
        const firstName = document.getElementById('first_name') ? document.getElementById('first_name').value.trim() : '';
        const middleName = document.getElementById('middle_name') ? document.getElementById('middle_name').value.trim() : '';
        const lastName = document.getElementById('last_name') ? document.getElementById('last_name').value.trim() : '';
        const suffix = document.getElementById('suffix') ? document.getElementById('suffix').value : '';
        const gender = document.getElementById('gender') ? document.getElementById('gender').value : '';
        const birthDate = document.getElementById('birth_date') ? document.getElementById('birth_date').value : '';
        const civilStatus = document.getElementById('civil_status') ? document.getElementById('civil_status').value : '';
        const contactNumber = document.getElementById('contact_number') ? document.getElementById('contact_number').value.trim() : '';
        const email = document.getElementById('email') ? document.getElementById('email').value.trim() : '';

        const street = document.getElementById('street') ? document.getElementById('street').value.trim() : '';
        const barangay = document.getElementById('barangay') ? document.getElementById('barangay').value.trim() : '';
        const city = document.getElementById('city') ? document.getElementById('city').value.trim() : '';
        const province = document.getElementById('province') ? document.getElementById('province').value.trim() : '';
        const postalCode = document.getElementById('postal_code') ? document.getElementById('postal_code').value.trim() : '';
        const householdNumber = document.getElementById('household_number') ? document.getElementById('household_number').value.trim() : '';

        const occupation = document.getElementById('occupation') ? document.getElementById('occupation').value.trim() : '';
        const monthlyIncome = document.getElementById('monthly_income') ? document.getElementById('monthly_income').value.trim() : '';
        const educationalAttainment = document.getElementById('educational_attainment') ? document.getElementById('educational_attainment').value : '';

        const emergName = document.getElementById('emergency_contact_name') ? document.getElementById('emergency_contact_name').value.trim() : '';
        const emergContact = document.getElementById('emergency_contact_number') ? document.getElementById('emergency_contact_number').value.trim() : '';
        const relationship = document.getElementById('relationship') ? document.getElementById('relationship').value.trim() : '';

        const seniorEl = document.getElementById('senior_citizen');
        const pwdEl = document.getElementById('pwd');
        const seniorChecked = seniorEl ? seniorEl.checked : false;
        const pwdChecked = pwdEl ? pwdEl.checked : false;

        // 1. Build Full Legal Name
        let fullName = '';
        if (lastName || firstName) {
            fullName = `${lastName}, ${firstName}`;
            if (middleName) fullName += ` ${middleName}`;
            if (suffix) fullName += ` ${suffix}`;
        }

        // 2. Build Complete Address
        let fullAddress = street ? `${street}` : '';
        if (barangay) fullAddress += fullAddress ? `, ${barangay}` : barangay;
        if (city) fullAddress += fullAddress ? `, ${city}` : city;
        if (province) fullAddress += fullAddress ? `, ${province}` : province;
        if (postalCode) fullAddress += ` ${postalCode}`;

        // 3. Compute Age from Birth Date
        let ageStr = '';
        let calcAge = null;
        if (birthDate) {
            const bdate = new Date(birthDate);
            const today = new Date();
            if (!isNaN(bdate.getTime())) {
                calcAge = today.getFullYear() - bdate.getFullYear();
                const m = today.getMonth() - bdate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < bdate.getDate())) {
                    calcAge--;
                }
                if (calcAge >= 0) {
                    ageStr = `(${calcAge} years old)`;
                }
            }
        }

        // CARD 1: Personal Details
        if (document.getElementById('sumFullName')) document.getElementById('sumFullName').textContent = fullName || 'Not Specified';
        if (document.getElementById('sumGender')) {
            const gEl = document.getElementById('sumGender');
            gEl.textContent = gender || 'Not Specified';
            gEl.className = 'gov-pill-badge ' + (gender === 'Male' ? 'pill-male' : (gender === 'Female' ? 'pill-female' : 'pill-neutral'));
        }
        if (document.getElementById('sumBirthDate')) document.getElementById('sumBirthDate').textContent = birthDate || '-';
        if (document.getElementById('sumAgeBadge')) document.getElementById('sumAgeBadge').textContent = ageStr;
        if (document.getElementById('sumCivilStatus')) {
            const csEl = document.getElementById('sumCivilStatus');
            csEl.textContent = civilStatus || 'Not Specified';
            csEl.className = 'gov-pill-badge pill-neutral';
        }
        if (document.getElementById('sumContact')) document.getElementById('sumContact').textContent = contactNumber || '-';
        if (document.getElementById('sumEmail') && email) document.getElementById('sumEmail').textContent = email;

        // CARD 2: Address Details
        if (document.getElementById('sumAddress')) document.getElementById('sumAddress').textContent = fullAddress || 'Not Specified';
        if (document.getElementById('sumHousehold')) document.getElementById('sumHousehold').textContent = householdNumber || 'Not Specified';
        if (document.getElementById('sumPostal')) document.getElementById('sumPostal').textContent = postalCode || '3132';

        // CARD 3: Economic Info
        if (document.getElementById('sumOccupation')) {
            const occEl = document.getElementById('sumOccupation');
            occEl.textContent = occupation || 'Unemployed / None';
            occEl.className = 'gov-pill-badge ' + (occupation ? 'pill-info' : 'pill-neutral');
        }
        if (document.getElementById('sumIncome')) {
            const numIncome = parseFloat(monthlyIncome) || 0;
            document.getElementById('sumIncome').textContent = `₱${numIncome.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }
        if (document.getElementById('sumEducation')) {
            const eduEl = document.getElementById('sumEducation');
            eduEl.textContent = educationalAttainment || 'Not Specified';
            eduEl.className = 'gov-pill-badge pill-neutral';
        }

        // CARD 4: Emergency Info
        if (document.getElementById('sumEmergName')) document.getElementById('sumEmergName').textContent = emergName || 'Not Specified';
        if (document.getElementById('sumRelationship')) {
            const relEl = document.getElementById('sumRelationship');
            relEl.textContent = relationship || 'Not Specified';
            relEl.className = 'gov-pill-badge pill-maroon';
        }
        if (document.getElementById('sumEmergContact')) document.getElementById('sumEmergContact').textContent = emergContact || 'Not Specified';

        // CARD 5: Special Categories
        const seniorBadge = document.getElementById('sumSeniorBadge');
        const pwdBadge = document.getElementById('sumPwdBadge');
        const seniorTile = document.getElementById('seniorSummaryTile');
        const pwdTile = document.getElementById('pwdSummaryTile');
        const seniorDesc = document.getElementById('seniorTileDesc');
        const pwdDesc = document.getElementById('pwdTileDesc');

        if (seniorBadge) {
            if (seniorChecked) {
                seniorBadge.textContent = 'Yes (Qualified)';
                seniorBadge.className = 'gov-pill-badge pill-success';
                if (seniorTile) seniorTile.className = 'status-summary-tile is-active';
                if (seniorDesc) seniorDesc.textContent = 'Enrolled for OSCA social welfare benefits & Republic Act 9994 privileges.';
            } else {
                seniorBadge.textContent = 'No';
                seniorBadge.className = 'gov-pill-badge pill-neutral';
                if (seniorTile) seniorTile.className = 'status-summary-tile';
                if (seniorDesc) seniorDesc.textContent = (calcAge !== null && calcAge < 60) ? `Resident is ${calcAge} yrs old (under 60 threshold required by RA 9994).` : 'Regular citizen record (Non-Senior Citizen status).';
            }
        }

        if (pwdBadge) {
            if (pwdChecked) {
                pwdBadge.textContent = 'Yes (Registered)';
                pwdBadge.className = 'gov-pill-badge pill-purple';
                if (pwdTile) pwdTile.className = 'status-summary-tile is-active';
                if (pwdDesc) pwdDesc.textContent = 'Eligible for Republic Act 10754 PWD benefits, discounts, & priority services.';
            } else {
                pwdBadge.textContent = 'No';
                pwdBadge.className = 'gov-pill-badge pill-neutral';
                if (pwdTile) pwdTile.className = 'status-summary-tile';
                if (pwdDesc) pwdDesc.textContent = 'Standard resident record with no declared physical or mental disability.';
            }
        }

        // Keep legacy fallback IDs updated just in case
        if (document.getElementById('sumSenior')) {
            document.getElementById('sumSenior').textContent = seniorChecked ? 'Yes' : 'No';
        }
        if (document.getElementById('sumPwd')) {
            document.getElementById('sumPwd').textContent = pwdChecked ? 'Yes' : 'No';
        }
    }

    // Submit Validation Check & Legal Attestation check
    profileForm.addEventListener('submit', function(e) {
        if (!validateCurrentStep()) {
            e.preventDefault();
            return false;
        }
        const confirmCheckbox = document.getElementById('confirmAccuracy');
        if (confirmCheckbox && !confirmCheckbox.checked) {
            e.preventDefault();
            showAlert("Please check the official certification box to confirm that your information is true and accurate.");
            confirmCheckbox.focus();
            return false;
        }
        return true;
    });

    // Start with current step and trigger buildSummary
    updateStepperUI();
    buildSummary();
}

// ========== SAVE SUCCESS MODAL FUNCTIONS ==========
function closeSaveSuccessModal() {
    const modal = document.getElementById('saveSuccessModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function handleSaveModalBackdrop(e) {
    if (e.target && e.target.id === 'saveSuccessModal') {
        closeSaveSuccessModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSaveSuccessModal();
    }
});

<?php if ($show_save_success_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('saveSuccessModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
});
<?php endif; ?>
</script>

<!-- Save Success & Proceed to Upload Picture Modal -->
<div class="save-success-modal-backdrop <?php echo $show_save_success_modal ? 'active' : ''; ?>" id="saveSuccessModal" onclick="handleSaveModalBackdrop(event)">
    <div class="save-success-modal-card" role="dialog" aria-modal="true" aria-labelledby="saveSuccessTitle">
        <button type="button" class="save-modal-close-btn" onclick="closeSaveSuccessModal()" aria-label="Close dialog">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="save-modal-badge-wrapper">
            <div class="save-modal-badge-pulse"></div>
            <div class="save-modal-icon-circle">
                <i class="fas fa-check"></i>
            </div>
        </div>

        <div class="save-modal-header">
            <div class="save-modal-tag"><i class="fas fa-shield-alt"></i> Profile Information Updated</div>
            <h3 id="saveSuccessTitle" class="save-modal-title">Successfully Saved!</h3>
            <p class="save-modal-desc">
                Your resident profile details have been saved successfully! Proceed to upload your profile picture to complete your profile verification.
            </p>
        </div>

        <div class="save-modal-actions">
            <a href="profile.php?upload_photo=1" class="save-modal-btn-primary">
                <i class="fas fa-camera"></i>
                <span>Proceed to Upload Profile Picture</span>
                <i class="fas fa-arrow-right"></i>
            </a>
            <div class="save-modal-secondary-group">
                <a href="<?php echo (isStaff() || isAdmin()) ? 'profile.php' : 'dashboard.php'; ?>" class="save-modal-btn-outline">
                    <i class="fas fa-th-large"></i>
                    <span>Go to Dashboard</span>
                </a>
                <button type="button" class="save-modal-btn-ghost" onclick="closeSaveSuccessModal()">
                    Review Info
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

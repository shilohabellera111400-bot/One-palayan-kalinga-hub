
<?php
// update-profile-info.php
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

$error = '';
$success = '';

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
$verification_status = $user_verify['verification_status'] ?? 'pending';

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
                // Update existing resident
                $query = "UPDATE residents SET 
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
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
            } else {
                // Generate a unique resident number
                $year = date('Y');
                $resident_number = null;
                $attempts = 0;
                $max_attempts = 10;
                
                while ($resident_number === null && $attempts < $max_attempts) {
                    $attempts++;
                    
                    // Get the count for the current year
                    $count_query = "SELECT COUNT(*) as count FROM residents WHERE YEAR(created_at) = :year";
                    $count_stmt = $db->prepare($count_query);
                    $count_stmt->bindParam(':year', $year);
                    $count_stmt->execute();
                    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                    $count = $count_result['count'] + $attempts;
                    
                    $candidate_number = 'RES-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
                    
                    // Check if this resident number already exists
                    $check_number_query = "SELECT id FROM residents WHERE resident_number = :resident_number";
                    $check_number_stmt = $db->prepare($check_number_query);
                    $check_number_stmt->bindParam(':resident_number', $candidate_number);
                    $check_number_stmt->execute();
                    
                    if ($check_number_stmt->rowCount() == 0) {
                        $resident_number = $candidate_number;
                    }
                }
                
                // If we couldn't generate a unique number after max attempts
                if ($resident_number === null) {
                    // Use timestamp-based fallback
                    $resident_number = 'RES-' . date('Ymd-His') . '-' . uniqid();
                }
                
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
                
                $success = "Your profile information has been saved successfully!";
                
                // Refresh resident data
                $check_stmt->execute();
                $existing_resident = $check_stmt->fetch(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="css/portal-layout.css">
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
        .status-verified { background: var(--color-success); color: white; }
        .status-pending { background: var(--color-warning); color: white; }
        .status-rejected { background: var(--color-danger); color: white; }
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

        /* ========== SUMMARY BLOCK ========== */
        .summary-block {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        .summary-grid-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 14px;
            border-bottom: 1.5px solid var(--border-color);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-grid-title i {
            color: var(--maroon-light);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-grid:last-of-type {
            margin-bottom: 0;
        }
        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .summary-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .summary-val {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.88rem;
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
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-user-edit"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Integrated Service Portal</span>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if (!empty($avatar_url)): ?>
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
            
            <?php include __DIR__ . '/includes/usersidenav.php'; ?>
            
            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-edit" style="color: var(--maroon-light); margin-right: 10px;"></i> Complete Your Profile</h1>
                        <p>Fill out your complete information to help us serve you better</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

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
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
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
                    <div class="form-section">
                        <h3><i class="fas fa-tags"></i> Special Categories</h3>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="senior_citizen" id="senior_citizen" value="1" <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'checked' : ''; ?> >
                                Senior Citizen
                            </label>
                            <label>
                                <input type="checkbox" name="pwd" id="pwd" value="1" <?php echo ($resident['pwd'] ?? 0) == 1 ? 'checked' : ''; ?> >
                                Person with Disability (PWD)
                            </label>
                        </div>
                    </div>

                    <!-- Summary Grid Block -->
                    <div class="summary-block">
                        <div class="summary-grid-title"><i class="fas fa-user"></i> Personal Details</div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-label">Full Name</span>
                                <span class="summary-val" id="sumFullName">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Gender</span>
                                <span class="summary-val" id="sumGender">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Birth Date</span>
                                <span class="summary-val" id="sumBirthDate">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Civil Status</span>
                                <span class="summary-val" id="sumCivilStatus">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Contact Number</span>
                                <span class="summary-val" id="sumContact">-</span>
                            </div>
                        </div>

                        <div class="summary-grid-title" style="margin-top: 20px;"><i class="fas fa-map-marker-alt"></i> Address Details</div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-label">Complete Address</span>
                                <span class="summary-val" id="sumAddress">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Household Number</span>
                                <span class="summary-val" id="sumHousehold">-</span>
                            </div>
                        </div>

                        <div class="summary-grid-title" style="margin-top: 20px;"><i class="fas fa-briefcase"></i> Economic Info</div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-label">Occupation</span>
                                <span class="summary-val" id="sumOccupation">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Monthly Income</span>
                                <span class="summary-val" id="sumIncome">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Educational Attainment</span>
                                <span class="summary-val" id="sumEducation">-</span>
                            </div>
                        </div>

                        <div class="summary-grid-title" style="margin-top: 20px;"><i class="fas fa-phone-alt"></i> Emergency Info</div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-label">Emergency Contact</span>
                                <span class="summary-val" id="sumEmergName">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Contact Number</span>
                                <span class="summary-val" id="sumEmergContact">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Relationship</span>
                                <span class="summary-val" id="sumRelationship">-</span>
                            </div>
                        </div>

                        <div class="summary-grid-title" style="margin-top: 20px;"><i class="fas fa-tags"></i> Special Categories</div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <span class="summary-label">Senior Citizen</span>
                                <span class="summary-val" id="sumSenior">-</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Person with Disability (PWD)</span>
                                <span class="summary-val" id="sumPwd">-</span>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- STEPPER NAVIGATION BUTTONS -->
                <div class="step-actions">
                    <button type="button" class="btn-action prev" id="prevBtn" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button type="button" class="btn-action next" id="nextBtn">
                        Next Step <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <?php if($verification_status == 'verified'): ?>
                    <button type="submit" name="update_profile_info" class="btn-action next" id="submitBtn" style="display: none;">
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

// ========== STEPPER SYSTEM ==========
let currentStep = 1;
const totalSteps = 5;

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
        document.querySelector(`.step-content[data-fieldset="${currentStep}"]`).classList.add('active');

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

    // ========== BUILD SUMMARY BLOCK ==========
    function buildSummary() {
        const firstName = document.getElementById('first_name').value.trim();
        const middleName = document.getElementById('middle_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const suffix = document.getElementById('suffix').value;
        const gender = document.getElementById('gender').value;
        const birthDate = document.getElementById('birth_date').value;
        const civilStatus = document.getElementById('civil_status').value;
        const contactNumber = document.getElementById('contact_number').value.trim();

        const street = document.getElementById('street').value.trim();
        const barangay = document.getElementById('barangay').value.trim();
        const city = document.getElementById('city').value.trim();
        const province = document.getElementById('province').value.trim();
        const postalCode = document.getElementById('postal_code').value.trim();
        const householdNumber = document.getElementById('household_number').value.trim();

        const occupation = document.getElementById('occupation').value.trim();
        const monthlyIncome = document.getElementById('monthly_income').value.trim();
        const educationalAttainment = document.getElementById('educational_attainment').value;

        const emergName = document.getElementById('emergency_contact_name').value.trim();
        const emergContact = document.getElementById('emergency_contact_number').value.trim();
        const relationship = document.getElementById('relationship').value.trim();

        const seniorChecked = document.getElementById('senior_citizen').checked;
        const pwdChecked = document.getElementById('pwd').checked;

        // Build full name representation
        let fullName = `${lastName}, ${firstName}`;
        if (middleName) fullName += ` ${middleName}`;
        if (suffix) fullName += ` ${suffix}`;

        // Build complete address representation
        let fullAddress = `${street}, ${barangay}`;
        if (city) fullAddress += `, ${city}`;
        if (province) fullAddress += `, ${province}`;
        if (postalCode) fullAddress += ` ${postalCode}`;

        document.getElementById('sumFullName').textContent = fullName;
        document.getElementById('sumGender').textContent = gender || '-';
        document.getElementById('sumBirthDate').textContent = birthDate || '-';
        document.getElementById('sumCivilStatus').textContent = civilStatus || '-';
        document.getElementById('sumContact').textContent = contactNumber || '-';

        document.getElementById('sumAddress').textContent = fullAddress;
        document.getElementById('sumHousehold').textContent = householdNumber || 'N/A';

        document.getElementById('sumOccupation').textContent = occupation || 'N/A';
        document.getElementById('sumIncome').textContent = monthlyIncome ? `₱${parseFloat(monthlyIncome).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : '₱0.00';
        document.getElementById('sumEducation').textContent = educationalAttainment || 'N/A';

        document.getElementById('sumEmergName').textContent = emergName || 'N/A';
        document.getElementById('sumEmergContact').textContent = emergContact || 'N/A';
        document.getElementById('sumRelationship').textContent = relationship || 'N/A';

        document.getElementById('sumSenior').textContent = seniorChecked ? 'Yes' : 'No';
        document.getElementById('sumPwd').textContent = pwdChecked ? 'Yes' : 'No';
    }

    // Submit Validation Check
    profileForm.addEventListener('submit', function(e) {
        if (!validateCurrentStep()) {
            e.preventDefault();
            return false;
        }
        return true;
    });

    // Start Step 1
    updateStepperUI();
}
</script>
</body>
</html>

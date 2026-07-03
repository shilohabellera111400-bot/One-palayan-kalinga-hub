
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
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
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
            transform: scale(1.05);
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

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
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
        .alert-info {
            background: rgba(33, 150, 243, 0.12);
            color: var(--color-info);
            border-left-color: var(--color-info);
        }
        .alert-warning {
            background: rgba(255, 152, 0, 0.12);
            color: var(--color-warning);
            border-left-color: var(--color-warning);
        }
        .alert i { font-size: 1.2rem; }

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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-user-edit"></i></div>
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
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="update-profile-info.php" class="active"><i class="fas fa-edit"></i> Complete Profile</a></li>
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
                <h1><i class="fas fa-edit" style="color: var(--maroon-light); margin-right: 10px;"></i> Complete Your Profile</h1>
                <p>Fill out your complete information to help us serve you better</p>
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
        <div class="profile-form-container <?php echo ($verification_status != 'verified') ? 'form-disabled' : ''; ?>">
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
            
            <?php if(!$existing_resident && $verification_status == 'verified'): ?>
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

            <form method="POST" action="">
                <!-- Personal Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($resident['first_name'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required-star">*</span></label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($resident['last_name'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($resident['middle_name'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <select name="suffix" <?php echo ($verification_status != 'verified') ? 'disabled' : ''; ?>>
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
                            <select name="gender" <?php echo ($verification_status != 'verified') ? 'disabled' : 'required'; ?>>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($resident['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($resident['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($resident['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Birth Date <span class="required-star">*</span></label>
                            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($resident['birth_date'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Civil Status <span class="required-star">*</span></label>
                            <select name="civil_status" <?php echo ($verification_status != 'verified') ? 'disabled' : 'required'; ?>>
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo ($resident['civil_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($resident['civil_status'] ?? '') == 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo ($resident['civil_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Divorced" <?php echo ($resident['civil_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Separated" <?php echo ($resident['civil_status'] ?? '') == 'Separated' ? 'selected' : ''; ?>>Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?php echo htmlspecialchars($resident['contact_number'] ?? ''); ?>" placeholder="09123456789" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($resident['email'] ?? $user['email']); ?>" readonly class="disabled-field">
                        <small>Email cannot be changed</small>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Street/Block/Lot <span class="required-star">*</span></label>
                            <input type="text" name="street" value="<?php echo htmlspecialchars($resident['street'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Barangay <span class="required-star">*</span></label>
                            <input type="text" name="barangay" value="<?php echo htmlspecialchars($resident['barangay'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City/Municipality <span class="required-star">*</span></label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($resident['city'] ?? 'Palayan City'); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                        <div class="form-group">
                            <label>Province <span class="required-star">*</span></label>
                            <input type="text" name="province" value="<?php echo htmlspecialchars($resident['province'] ?? 'Nueva Ecija'); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : 'required'; ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input type="text" name="postal_code" value="<?php echo htmlspecialchars($resident['postal_code'] ?? '3132'); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Household Number</label>
                            <input type="text" name="household_number" value="<?php echo htmlspecialchars($resident['household_number'] ?? ''); ?>" placeholder="Optional" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                    </div>
                </div>

                <!-- Employment & Economic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Employment & Economic Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?php echo htmlspecialchars($resident['occupation'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Monthly Income (₱)</label>
                            <input type="number" name="monthly_income" step="0.01" value="<?php echo htmlspecialchars($resident['monthly_income'] ?? 0); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Educational Attainment</label>
                        <select name="educational_attainment" <?php echo ($verification_status != 'verified') ? 'disabled' : ''; ?>>
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

                <!-- Emergency Contact -->
                <div class="form-section">
                    <h3><i class="fas fa-phone-alt"></i> Emergency Contact</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($resident['emergency_contact_name'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" value="<?php echo htmlspecialchars($resident['emergency_contact_number'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Relationship to Emergency Contact</label>
                        <input type="text" name="relationship" value="<?php echo htmlspecialchars($resident['relationship'] ?? ''); ?>" <?php echo ($verification_status != 'verified') ? 'readonly class="disabled-field"' : ''; ?>>
                    </div>
                </div>

                <!-- Special Categories -->
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Special Categories</h3>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="senior_citizen" value="1" <?php echo ($resident['senior_citizen'] ?? 0) == 1 ? 'checked' : ''; ?> <?php echo ($verification_status != 'verified') ? 'disabled' : ''; ?>>
                            Senior Citizen
                        </label>
                        <label>
                            <input type="checkbox" name="pwd" value="1" <?php echo ($resident['pwd'] ?? 0) == 1 ? 'checked' : ''; ?> <?php echo ($verification_status != 'verified') ? 'disabled' : ''; ?>>
                            Person with Disability (PWD)
                        </label>
                    </div>
                </div>

                <?php if($verification_status == 'verified'): ?>
                <button type="submit" name="update_profile_info" class="btn-primary">
                    <i class="fas fa-save"></i> Save Profile Information
                </button>
                <?php else: ?>
                <button type="button" class="btn-primary" disabled>
                    <i class="fas fa-lock"></i> Complete Verification First
                </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

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
</script>
</body>
</html>
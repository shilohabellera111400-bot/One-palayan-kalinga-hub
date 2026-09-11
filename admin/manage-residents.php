<?php
// admin/manage-residents.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle resident creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_resident'])) {
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
        $error = "Please fill in all required fields";
    } else {
        // Generate resident ID (format: RES-YYYY-XXXX)
        $year = date('Y');
        $count_query = "SELECT COUNT(*) as count FROM residents WHERE YEAR(created_at) = $year";
        $count_result = $db->query($count_query);
        $count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
        $resident_number = 'RES-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        
        // Check if email already exists (if provided)
        if (!empty($email)) {
            $check_email = "SELECT id FROM residents WHERE email = :email";
            $check_stmt = $db->prepare($check_email);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            if ($check_stmt->rowCount() > 0) {
                $error = "Email already exists in the system";
            }
        }
        
        if (empty($error)) {
            $query = "INSERT INTO residents (
                resident_number, first_name, last_name, middle_name, suffix, gender, 
                birth_date, civil_status, contact_number, email, street, barangay, 
                city, province, postal_code, household_number, 
                senior_citizen, pwd, occupation, monthly_income, educational_attainment, 
                emergency_contact_name, emergency_contact_number, relationship, is_active
            ) VALUES (
                :resident_number, :first_name, :last_name, :middle_name, :suffix, :gender,
                :birth_date, :civil_status, :contact_number, :email, :street, :barangay,
                :city, :province, :postal_code, :household_number,
                :senior_citizen, :pwd, :occupation, :monthly_income, :educational_attainment,
                :emergency_contact_name, :emergency_contact_number, :relationship, 1
            )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':resident_number', $resident_number);
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
                $success = "Resident registered successfully! Resident ID: " . $resident_number;
            } else {
                $error = "Failed to register resident";
            }
        }
    }
}

// Handle resident update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_resident'])) {
    $resident_id = sanitize($_POST['resident_id']);
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
              WHERE id = :resident_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':resident_id', $resident_id);
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
        $success = "Resident information updated successfully!";
    } else {
        $error = "Failed to update resident information";
    }
}

// Handle resident status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $resident_id = sanitize($_GET['id']);
    
    $query = "SELECT is_active FROM residents WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $resident_id);
    $stmt->execute();
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resident) {
        $new_status = $resident['is_active'] == 1 ? 0 : 1;
        $update = "UPDATE residents SET is_active = :status WHERE id = :id";
        $update_stmt = $db->prepare($update);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':id', $resident_id);
        $update_stmt->execute();
        
        $success = "Resident status updated!";
    }
}

// Handle resident deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $resident_id = sanitize($_GET['id']);
    
    // Check if resident has any appointments or requests
    $check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)";
    $check_stmt = $db->prepare($check_appointments);
    $check_stmt->bindParam(':id', $resident_id);
    $check_stmt->execute();
    $has_appointments = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $check_aics = "SELECT COUNT(*) as count FROM aics_requests WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)";
    $check_aics_stmt = $db->prepare($check_aics);
    $check_aics_stmt->bindParam(':id', $resident_id);
    $check_aics_stmt->execute();
    $has_aics = $check_aics_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $check_housing = "SELECT COUNT(*) as count FROM housing_beneficiaries WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)";
    $check_housing_stmt = $db->prepare($check_housing);
    $check_housing_stmt->bindParam(':id', $resident_id);
    $check_housing_stmt->execute();
    $has_housing = $check_housing_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($has_appointments > 0 || $has_aics > 0 || $has_housing > 0) {
        $error = "Cannot delete resident with existing records. You can deactivate instead.";
    } else {
        // First delete the user account if exists
        $get_user = "SELECT user_id FROM residents WHERE id = :id";
        $get_user_stmt = $db->prepare($get_user);
        $get_user_stmt->bindParam(':id', $resident_id);
        $get_user_stmt->execute();
        $resident_data = $get_user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resident_data && !empty($resident_data['user_id'])) {
            $delete_user = "DELETE FROM users WHERE user_id = :user_id";
            $delete_user_stmt = $db->prepare($delete_user);
            $delete_user_stmt->bindParam(':user_id', $resident_data['user_id']);
            $delete_user_stmt->execute();
        }
        
        $delete = "DELETE FROM residents WHERE id = :id";
        $delete_stmt = $db->prepare($delete);
        $delete_stmt->bindParam(':id', $resident_id);
        
        if ($delete_stmt->execute()) {
            $success = "Resident deleted successfully!";
        } else {
            $error = "Failed to delete resident";
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters for count
$count_query = "SELECT COUNT(*) as total FROM residents WHERE 1=1";
$count_params = [];

if (!empty($search)) {
    $count_query .= " AND (first_name LIKE :search OR last_name LIKE :search OR resident_number LIKE :search OR contact_number LIKE :search)";
    $count_params[':search'] = "%$search%";
}

if (!empty($barangay_filter)) {
    $count_query .= " AND barangay = :barangay";
    $count_params[':barangay'] = $barangay_filter;
}

if ($status_filter !== '') {
    $count_query .= " AND is_active = :status";
    $count_params[':status'] = $status_filter;
}

$count_stmt = $db->prepare($count_query);
foreach ($count_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Build query with filters and pagination - JOIN with users to get profile_picture
$query = "SELECT r.*, u.profile_picture 
          FROM residents r
          LEFT JOIN users u ON r.user_id = u.user_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (r.first_name LIKE :search OR r.last_name LIKE :search OR r.resident_number LIKE :search OR r.contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($barangay_filter)) {
    $query .= " AND r.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

if ($status_filter !== '') {
    $query .= " AND r.is_active = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY r.created_at DESC LIMIT :offset, :per_page";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique barangays for filter (without pagination)
$barangays_query = "SELECT DISTINCT barangay FROM residents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay";
$barangays = $db->query($barangays_query)->fetchAll(PDO::FETCH_ASSOC);

// Get resident for editing if requested
$edit_resident = null;
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_id = sanitize($_GET['id']);
    $edit_query = "SELECT * FROM residents WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $edit_id);
    $edit_stmt->execute();
    $edit_resident = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all residents for stats (without pagination)
$all_residents_query = "SELECT * FROM residents";
$all_residents = $db->query($all_residents_query)->fetchAll(PDO::FETCH_ASSOC);
$total_residents = count($all_residents);
$active_residents = count(array_filter($all_residents, function($r) { return $r['is_active'] == 1; }));
$senior_count = count(array_filter($all_residents, function($r) { return $r['senior_citizen'] == 1; }));
$pwd_count = count(array_filter($all_residents, function($r) { return $r['pwd'] == 1; }));

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - Admin</title>
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
            
            /* Light theme defaults */
            --bg-primary: #E8ECF2;
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
            --border-color: #C8D0DC;
            
            /* Enhanced Shadows */
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --search-bg: #FFFFFF;
            --stat-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
        }

        /* ========== DARK THEME ========== */
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
            
            /* Enhanced Shadows - Dark Mode */
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --search-bg: #1a2332;
            --stat-bg: #1a2332;
            --filter-bg: #1a2332;
            --table-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
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

        /* ========== LAYOUT ========== */
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
        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }
        .logo-image {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: rgba(255,255,255,0.10);
            padding: 4px;
            flex-shrink: 0;
        }
        .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            color: var(--maroon-light);
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .logo-text {
            flex: 1;
            min-width: 0;
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

        .sidebar-menu {
            list-style: none;
            padding: 16px 14px;
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
            font-size: 0.85rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 20px; font-size: 1rem; color: rgba(255,255,255,0.4); }
        .sidebar-menu li a:hover { background: var(--bg-sidebar-hover); color: var(--text-sidebar-active); }
        .sidebar-menu li a:hover i { color: rgba(255,255,255,0.8); }
        .sidebar-menu li a.active {
            background: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }
        .sidebar-menu li a.active i { color: var(--text-sidebar-active); }
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 12px 0;
            padding-top: 10px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
            padding-left: 14px;
        }

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
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(255,255,255,0.04);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.10); color: var(--text-sidebar-active); }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }
        .sidebar-footer .avatar {
            width: 38px;
            height: 38px;
            background: var(--maroon-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.8rem;
        }
        .sidebar-footer .user-details h4 {
            color: var(--text-sidebar-active);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .sidebar-footer .user-details p {
            color: rgba(255,255,255,0.4);
            font-size: 0.7rem;
        }

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
        .search-box {
            display: flex;
            align-items: center;
            background: var(--search-bg);
            border-radius: 50px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            gap: 10px;
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .search-box input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: transparent;
            min-width: 180px;
            color: var(--text-primary);
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { color: var(--text-muted); }

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
            color: #4CAF50;
            border-left-color: #4CAF50;
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
            border-left-color: #EF5350;
        }
        .alert i { font-size: 1.2rem; }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--stat-bg);
            border-radius: 20px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--maroon), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 20px;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }
        .stat-card:hover::after {
            opacity: 0.03;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.active { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-icon.senior { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .stat-icon.pwd { background: rgba(156, 39, 176, 0.12); color: #9C27B0; }

        .stat-info {
            position: relative;
            z-index: 1;
        }
        .stat-info h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            transition: color 0.3s ease;
        }
        .stat-info p {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== QUICK ACTIONS BAR ========== */
        .quick-actions-bar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .resident-info h4 {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .resident-info .count {
            font-size: 1.6rem;
            font-weight: 800;
        }
        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-btn {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
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
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: var(--border-color);
        }

        /* ========== TABLE ========== */
        .table-container {
            background: var(--table-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead {
            background: var(--badge-bg);
            border-bottom: 2px solid var(--border-color);
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: var(--badge-bg);
        }

        /* Resident Cell with Avatar */
        .resident-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .resident-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--border-color);
        }
        .resident-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resident-avatar .initials {
            font-weight: 700;
            font-size: 0.7rem;
            color: var(--text-primary);
        }
        .resident-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .resident-id {
            font-weight: 600;
            color: var(--maroon-light);
            font-size: 0.75rem;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.active {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        .status-badge.inactive {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }

        /* Category Badges */
        .category-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.6rem;
            font-weight: 600;
            margin: 2px;
        }
        .category-badge.senior {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .category-badge.pwd {
            background: rgba(156, 39, 176, 0.12);
            color: #9C27B0;
        }

        /* Action Buttons */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-edit {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .btn-edit:hover {
            background: #2196F3;
            color: white;
        }
        .btn-toggle {
            background: rgba(255, 152, 0, 0.12);
            color: #FF9800;
        }
        .btn-toggle:hover {
            background: #FF9800;
            color: white;
        }
        .btn-delete {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
        }
        .btn-delete:hover {
            background: #EF5350;
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
        }
        .no-data i {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .no-data p {
            color: var(--text-muted);
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .pagination .page-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-right: 12px;
        }
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
        }
        .pagination a:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .pagination .active {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination .ellipsis {
            border: none;
            background: transparent;
            cursor: default;
        }

        /* ========== MODAL ========== */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(6px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            border-radius: 24px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--badge-bg);
            border-radius: 24px 24px 0 0;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i {
            color: var(--maroon-light);
        }
        .modal-header .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .modal-header .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 25px;
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
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .form-group label .required {
            color: #EF5350;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-group .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--maroon);
            cursor: pointer;
        }
        .modal-footer {
            padding: 16px 25px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--badge-bg);
            border-radius: 0 0 24px 24px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-actions { flex-wrap: wrap; }
            .form-row { grid-template-columns: 1fr; }
            .data-table { display: block; overflow-x: auto; }
            .quick-actions-bar { flex-direction: column; text-align: center; }
            .quick-actions { justify-content: center; }
            .modal-content { width: 95%; padding: 0; }
            .modal-body { padding: 16px; }
            .pagination .page-info { width: 100%; text-align: center; margin-right: 0; margin-bottom: 8px; }
            .resident-avatar { width: 28px; height: 28px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.7rem; }
            .btn-sm { font-size: 0.6rem; padding: 3px 8px; }
            .quick-btn { font-size: 0.7rem; padding: 8px 14px; }
            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
            .resident-cell { gap: 8px; }
            .resident-avatar { width: 24px; height: 24px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <!-- LOGO IMAGE - Replace the src path with your actual logo file path -->
                <img src="../images/logo.png" alt="Palayan City Logo" class="logo-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-icon" style="display: none;"><i class="fas fa-crown"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Admin Panel</span>
                </div>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar Manager</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php" class="active"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
            <li><a href="manage-users.php"><i class="fas fa-users-cog"></i> System Users</a></li>
            <li class="menu-divider">System</li>
            <li><a href="audit-logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo $initials; ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-user-friends" style="color: var(--maroon-light); margin-right: 10px;"></i> Manage Residents</h1>
                <p>View and manage all registered residents</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search residents..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="audit-logs.php" class="top-icon"><i class="fas fa-bell"></i></a>
                <a href="settings.php" class="top-icon"><i class="fas fa-cog"></i></a>
            </div>
        </div>
        
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
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-residents.php'">
                <div class="stat-icon total"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_residents; ?></h3>
                    <p>Total Residents</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-residents.php?status=1'">
                <div class="stat-icon active"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $active_residents; ?></h3>
                    <p>Active Residents</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-residents.php?search=&barangay=&status='">
                <div class="stat-icon senior"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $senior_count; ?></h3>
                    <p>Senior Citizens</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-residents.php?search=&barangay=&status='">
                <div class="stat-icon pwd"><i class="fas fa-wheelchair"></i></div>
                <div class="stat-info">
                    <h3><?php echo $pwd_count; ?></h3>
                    <p>PWDs</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Bar -->
        <div class="quick-actions-bar">
            <div class="resident-info">
                <h4>Total Registered Residents</h4>
                <div class="count"><?php echo $total_residents; ?></div>
            </div>
            <div class="quick-actions">
                <button onclick="openModal()" class="quick-btn">
                    <i class="fas fa-user-plus"></i> Add New Resident
                </button>
                <a href="export-residents.php" class="quick-btn">
                    <i class="fas fa-download"></i> Export Data
                </a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Name, ID, or contact..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-map-marker-alt"></i> Barangay</label>
                        <select name="barangay">
                            <option value="">All Barangays</option>
                            <?php foreach($barangays as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['barangay']); ?>" <?php echo $barangay_filter == $b['barangay'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['barangay']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter == '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <?php if($search || $barangay_filter || $status_filter): ?>
                        <a href="manage-residents.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Residents Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Resident #</th>
                        <th>Full Name</th>
                        <th>Barangay</th>
                        <th>Contact</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Categories</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($residents)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="no-data">
                                    <i class="fas fa-users"></i>
                                    <p>No residents found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($residents as $resident): 
                            $age = date_diff(date_create($resident['birth_date']), date_create('today'))->y;
                            $categories = [];
                            if($resident['senior_citizen']) $categories[] = ['label' => 'Senior', 'class' => 'senior'];
                            if($resident['pwd']) $categories[] = ['label' => 'PWD', 'class' => 'pwd'];
                            
                            $resident_initials = strtoupper(substr($resident['first_name'], 0, 1) . substr($resident['last_name'], 0, 1));
                            $profile_pic = !empty($resident['profile_picture']) && file_exists(__DIR__ . '/../' . $resident['profile_picture']) 
                                ? '../' . $resident['profile_picture'] 
                                : '';
                        ?>
                        <tr>
                            <td><span class="resident-id"><?php echo htmlspecialchars($resident['resident_number']); ?></span></td>
                            <td>
                                <div class="resident-cell">
                                    <div class="resident-avatar">
                                        <?php if(!empty($profile_pic)): ?>
                                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
                                        <?php else: ?>
                                            <span class="initials"><?php echo $resident_initials; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="resident-name"><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($resident['barangay'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($resident['contact_number'] ?: 'N/A'); ?></td>
                            <td><?php echo $age; ?> yrs</td>
                            <td>
                                <span class="status-badge <?php echo $resident['is_active'] == 1 ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-<?php echo $resident['is_active'] == 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $resident['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if(!empty($categories)): ?>
                                    <?php foreach($categories as $cat): ?>
                                        <span class="category-badge <?php echo $cat['class']; ?>"><?php echo $cat['label']; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.7rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($resident)); ?>)" class="btn-sm btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?toggle_status=1&id=<?php echo $resident['id']; ?>" class="btn-sm btn-toggle" onclick="return confirm('Toggle resident status?')">
                                        <i class="fas fa-toggle-<?php echo $resident['is_active'] == 1 ? 'off' : 'on'; ?>"></i>
                                        <?php echo $resident['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $resident['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this resident? This action cannot be undone if no transactions exist.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?></span>
                
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($barangay_filter) ? '&barangay=' . urlencode($barangay_filter) : ''; ?><?php echo $status_filter !== '' ? '&status=' . $status_filter : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                $query_string = !empty($search) ? '&search=' . urlencode($search) : '';
                $query_string .= !empty($barangay_filter) ? '&barangay=' . urlencode($barangay_filter) : '';
                $query_string .= $status_filter !== '' ? '&status=' . $status_filter : '';
                
                if ($start_page > 1) {
                    echo '<a href="?page=1' . $query_string . '">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a href="?page=' . $i . $query_string . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . $query_string . '">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Resident Modal -->
<div id="residentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Resident</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" required placeholder="Enter first name">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Suffix</label>
                        <select name="suffix">
                            <option value="">None</option>
                            <option>Jr.</option>
                            <option>Sr.</option>
                            <option>III</option>
                            <option>IV</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birth Date <span class="required">*</span></label>
                        <input type="date" name="birth_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Civil Status <span class="required">*</span></label>
                        <select name="civil_status" required>
                            <option value="">Select Status</option>
                            <option>Single</option>
                            <option>Married</option>
                            <option>Widowed</option>
                            <option>Divorced</option>
                            <option>Separated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" placeholder="09123456789">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="resident@email.com">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Street/Block/Lot <span class="required">*</span></label>
                        <input type="text" name="street" required placeholder="Street address">
                    </div>
                    <div class="form-group">
                        <label>Barangay <span class="required">*</span></label>
                        <input type="text" name="barangay" required placeholder="Barangay name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality <span class="required">*</span></label>
                        <input type="text" name="city" required placeholder="City name">
                    </div>
                    <div class="form-group">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" name="province" required placeholder="Province name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" placeholder="Postal code">
                    </div>
                    <div class="form-group">
                        <label>Household Number</label>
                        <input type="text" name="household_number" placeholder="Optional">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Occupation</label>
                        <input type="text" name="occupation" placeholder="Occupation">
                    </div>
                    <div class="form-group">
                        <label>Monthly Income</label>
                        <input type="number" name="monthly_income" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Educational Attainment</label>
                    <select name="educational_attainment">
                        <option value="">Select</option>
                        <option>No Formal Education</option>
                        <option>Elementary Level</option>
                        <option>Elementary Graduate</option>
                        <option>High School Level</option>
                        <option>High School Graduate</option>
                        <option>College Level</option>
                        <option>College Graduate</option>
                        <option>Post Graduate</option>
                        <option>Vocational</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" placeholder="Contact person">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Number</label>
                        <input type="text" name="emergency_contact_number" placeholder="Contact number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship to Emergency Contact</label>
                    <input type="text" name="relationship" placeholder="e.g., Spouse, Parent">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="senior_citizen"> Senior Citizen
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="pwd"> Person with Disability (PWD)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="create_resident" class="btn-primary">
                    <i class="fas fa-save"></i> Register Resident
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Resident Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Resident</h2>
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <div class="modal-body">
                <input type="hidden" name="resident_id" id="edit_resident_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name">
                    </div>
                    <div class="form-group">
                        <label>Suffix</label>
                        <select name="suffix" id="edit_suffix">
                            <option value="">None</option>
                            <option>Jr.</option>
                            <option>Sr.</option>
                            <option>III</option>
                            <option>IV</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select name="gender" id="edit_gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birth Date <span class="required">*</span></label>
                        <input type="date" name="birth_date" id="edit_birth_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Civil Status <span class="required">*</span></label>
                        <select name="civil_status" id="edit_civil_status" required>
                            <option value="">Select Status</option>
                            <option>Single</option>
                            <option>Married</option>
                            <option>Widowed</option>
                            <option>Divorced</option>
                            <option>Separated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" id="edit_contact_number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Street/Block/Lot <span class="required">*</span></label>
                        <input type="text" name="street" id="edit_street" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay <span class="required">*</span></label>
                        <input type="text" name="barangay" id="edit_barangay" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality <span class="required">*</span></label>
                        <input type="text" name="city" id="edit_city" required>
                    </div>
                    <div class="form-group">
                        <label>Province <span class="required">*</span></label>
                        <input type="text" name="province" id="edit_province" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" id="edit_postal_code">
                    </div>
                    <div class="form-group">
                        <label>Household Number</label>
                        <input type="text" name="household_number" id="edit_household_number">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Occupation</label>
                        <input type="text" name="occupation" id="edit_occupation">
                    </div>
                    <div class="form-group">
                        <label>Monthly Income</label>
                        <input type="number" name="monthly_income" id="edit_monthly_income" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Educational Attainment</label>
                    <select name="educational_attainment" id="edit_educational_attainment">
                        <option value="">Select</option>
                        <option>No Formal Education</option>
                        <option>Elementary Level</option>
                        <option>Elementary Graduate</option>
                        <option>High School Level</option>
                        <option>High School Graduate</option>
                        <option>College Level</option>
                        <option>College Graduate</option>
                        <option>Post Graduate</option>
                        <option>Vocational</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" id="edit_emergency_contact_name">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Number</label>
                        <input type="text" name="emergency_contact_number" id="edit_emergency_contact_number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship to Emergency Contact</label>
                    <input type="text" name="relationship" id="edit_relationship">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="senior_citizen" id="edit_senior_citizen"> Senior Citizen
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="pwd" id="edit_pwd"> Person with Disability (PWD)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="update_resident" class="btn-primary">
                    <i class="fas fa-save"></i> Update Resident
                </button>
            </div>
        </form>
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

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'manage-residents.php?search=' + encodeURIComponent(query);
        }
    }
});

// ========== MODAL FUNCTIONS ==========
function openModal() {
    document.getElementById('residentModal').classList.add('active');
}

function closeModal() {
    document.getElementById('residentModal').classList.remove('active');
}

function openEditModal(resident) {
    document.getElementById('edit_resident_id').value = resident.id;
    document.getElementById('edit_first_name').value = resident.first_name || '';
    document.getElementById('edit_last_name').value = resident.last_name || '';
    document.getElementById('edit_middle_name').value = resident.middle_name || '';
    document.getElementById('edit_suffix').value = resident.suffix || '';
    document.getElementById('edit_gender').value = resident.gender || '';
    document.getElementById('edit_birth_date').value = resident.birth_date || '';
    document.getElementById('edit_civil_status').value = resident.civil_status || '';
    document.getElementById('edit_contact_number').value = resident.contact_number || '';
    document.getElementById('edit_email').value = resident.email || '';
    document.getElementById('edit_street').value = resident.street || '';
    document.getElementById('edit_barangay').value = resident.barangay || '';
    document.getElementById('edit_city').value = resident.city || '';
    document.getElementById('edit_province').value = resident.province || '';
    document.getElementById('edit_postal_code').value = resident.postal_code || '';
    document.getElementById('edit_household_number').value = resident.household_number || '';
    document.getElementById('edit_occupation').value = resident.occupation || '';
    document.getElementById('edit_monthly_income').value = resident.monthly_income || 0;
    document.getElementById('edit_educational_attainment').value = resident.educational_attainment || '';
    document.getElementById('edit_emergency_contact_name').value = resident.emergency_contact_name || '';
    document.getElementById('edit_emergency_contact_number').value = resident.emergency_contact_number || '';
    document.getElementById('edit_relationship').value = resident.relationship || '';
    document.getElementById('edit_senior_citizen').checked = resident.senior_citizen == 1;
    document.getElementById('edit_pwd').checked = resident.pwd == 1;
    
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Close modals on outside click
window.onclick = function(event) {
    const residentModal = document.getElementById('residentModal');
    const editModal = document.getElementById('editModal');
    if (event.target == residentModal) {
        closeModal();
    }
    if (event.target == editModal) {
        closeEditModal();
    }
}
</script>
</body>
</html>





<?php
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

// Build query with filters
$query = "SELECT * FROM residents WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR resident_number LIKE :search OR contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($barangay_filter)) {
    $query .= " AND barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

if ($status_filter !== '') {
    $query .= " AND is_active = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique barangays for filter
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

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #E8ECF2;
            --gray-100: #F1F3F8;
            --gray-200: #E8ECF2;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
            line-height: 1.5;
        }

        /* ========== LAYOUT ========== */
        .app-container { display: flex; min-height: 100vh; background: var(--gray-50); }

        /* ========== SIDEBAR - MAROON THEME ========== */
        .sidebar {
            width: 280px;
            background: var(--maroon);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 4px; }

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .logo-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.6rem;
        }
        .logo-text h2 {
            color: white;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
        .logo-text p {
            color: rgba(255,255,255,0.65);
            font-size: 0.7rem;
            margin-top: 4px;
        }

        .admin-profile {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 16px;
        }
        .admin-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--tan), var(--light-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--maroon);
        }
        .admin-profile h4 {
            color: white;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .admin-profile p {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }
        .admin-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
            flex: 1;
        }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.25s;
        }
        .sidebar-menu li a i { width: 22px; font-size: 1.1rem; color: rgba(255,255,255,0.7); }
        .sidebar-menu li a:hover { background: rgba(255,255,255,0.12); color: white; }
        .sidebar-menu li a.active {
            background: var(--maroon-light);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .sidebar-menu li a.active i { color: white; }
        .menu-divider {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 16px 0;
            padding-top: 8px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            padding-left: 16px;
        }

        .sidebar-footer {
            padding: 20px 16px 30px 16px;
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            background: rgba(255,255,255,0.08);
            transition: all 0.25s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.18); color: white; }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
        }

        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 20px;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--maroon);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-card);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            background: var(--alabaster);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: var(--maroon);
        }
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--midnight);
        }
        .stat-info p {
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        /* Quick Actions Bar */
        .quick-actions-bar {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a5a66 100%);
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            color: white;
        }
        .resident-info h4 {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .resident-info .count {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .quick-actions {
            display: flex;
            gap: 12px;
        }
        .quick-btn {
            background: rgba(255,255,255,0.15);
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: var(--radius-card);
            padding: 20px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127,3,3,0.1);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
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
            background: var(--gray-300);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table thead {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }
        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.85rem;
        }
        .data-table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active {
            background: #e8f5e9;
            color: #43a047;
        }
        .status-inactive {
            background: #ffebee;
            color: #e53935;
        }
        .category-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            background: #e3f2fd;
            color: #1976d2;
            margin: 2px;
        }

        /* Action Buttons */
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            margin: 0 2px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        .btn-warning:hover {
            background: #f57c00;
            color: white;
        }
        .btn-danger {
            background: #ffebee;
            color: #e53935;
        }
        .btn-danger:hover {
            background: #e53935;
            color: white;
        }
        .btn-info {
            background: #e3f2fd;
            color: #1976d2;
        }
        .btn-info:hover {
            background: #1976d2;
            color: white;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        /* Modal */
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            font-size: 1.2rem;
            color: var(--maroon);
            margin: 0;
        }
        .modal-body {
            padding: 25px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-700);
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--maroon);
        }

        /* Responsive */
        @media (max-width: 1000px) {
            .sidebar { width: 250px; }
            .main-content { margin-left: 250px; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .data-table { display: block; overflow-x: auto; }
            .quick-actions-bar { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar - Maroon Theme -->
    <div class="sidebar">
        <div>
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-user-friends"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <p>Admin Panel</p>
                </div>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo $initials; ?>
                </div>
                <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p>Administrator</p>
                <span class="admin-badge">
                    <i class="fas fa-crown"></i> Admin
                </span>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-divider">User Management</li>
                <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
                <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                <li><a href="manage-residents.php" class="active"><i class="fas fa-user-friends"></i> Manage Residents</a></li>
                <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
                <li class="menu-divider">System</li>
                <li><a href="audit-logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> System Settings</a></li>
                <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
                <li class="menu-divider"></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > 
            <a href="manage-residents.php">Manage Residents</a>
        </div>
        
        <h1 style="margin-bottom: 24px; color: var(--midnight);"><i class="fas fa-user-friends"></i> Manage Residents</h1>
        
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
        <?php
        $total_residents = count($residents);
        $active_residents = count(array_filter($residents, function($r) { return $r['is_active'] == 1; }));
        $senior_count = count(array_filter($residents, function($r) { return $r['senior_citizen'] == 1; }));
        $pwd_count = count(array_filter($residents, function($r) { return $r['pwd'] == 1; }));
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_residents; ?></h3>
                    <p>Total Residents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $active_residents; ?></h3>
                    <p>Active Residents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $senior_count; ?></h3>
                    <p>Senior Citizens</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-wheelchair"></i></div>
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
                        <th>Birth Date</th>
                        <th>Age</th>
                        <th>Status</th>
                        <th>Categories</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($residents)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 60px;">
                                <i class="fas fa-users" style="font-size: 48px; color: var(--gray-200); margin-bottom: 15px; display: block;"></i>
                                <p style="color: var(--gray-600);">No residents found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($residents as $resident): 
                            $age = date_diff(date_create($resident['birth_date']), date_create('today'))->y;
                            $categories = [];
                            if($resident['senior_citizen']) $categories[] = 'Senior';
                            if($resident['pwd']) $categories[] = 'PWD';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($resident['resident_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($resident['barangay']); ?></td>
                            <td><?php echo htmlspecialchars($resident['contact_number'] ?: 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($resident['birth_date'])); ?></td>
                            <td><?php echo $age; ?> yrs</td>
                            <td>
                                <span class="status-badge status-<?php echo $resident['is_active'] == 1 ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-<?php echo $resident['is_active'] == 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $resident['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if(!empty($categories)): ?>
                                    <?php foreach($categories as $cat): ?>
                                        <span class="category-badge"><?php echo $cat; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=1&id=<?php echo $resident['id']; ?>" class="btn-sm btn-info" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?toggle_status=1&id=<?php echo $resident['id']; ?>" class="btn-sm btn-warning" onclick="return confirm('Toggle resident status?')">
                                    <i class="fas fa-toggle-<?php echo $resident['is_active'] == 1 ? 'off' : 'on'; ?>"></i>
                                    <?php echo $resident['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <a href="?delete=1&id=<?php echo $resident['id']; ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this resident? This action cannot be undone if no transactions exist.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Resident Modal -->
<div id="residentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Resident</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name">
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
                        <label>Gender *</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birth Date *</label>
                        <input type="date" name="birth_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Civil Status *</label>
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
                    <input type="email" name="email">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Street/Block/Lot *</label>
                        <input type="text" name="street" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay *</label>
                        <input type="text" name="barangay" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality *</label>
                        <input type="text" name="city" required>
                    </div>
                    <div class="form-group">
                        <label>Province *</label>
                        <input type="text" name="province" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code">
                    </div>
                    <div class="form-group">
                        <label>Household Number</label>
                        <input type="text" name="household_number" placeholder="Optional">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Occupation</label>
                        <input type="text" name="occupation">
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
                        <input type="text" name="emergency_contact_name">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Number</label>
                        <input type="text" name="emergency_contact_number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship to Emergency Contact</label>
                    <input type="text" name="relationship">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="senior_citizen"> Senior Citizen
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="pwd"> Person with Disability (PWD)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-header" style="border-top: 1px solid var(--gray-200); justify-content: flex-end; gap: 12px;">
                <button type="submit" name="create_resident" class="btn-primary">
                    <i class="fas fa-save"></i> Register Resident
                </button>
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Resident Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit Resident</h2>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <div class="modal-body">
                <input type="hidden" name="resident_id" id="edit_resident_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
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
                        <label>Gender *</label>
                        <select name="gender" id="edit_gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birth Date *</label>
                        <input type="date" name="birth_date" id="edit_birth_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Civil Status *</label>
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
                        <label>Street/Block/Lot *</label>
                        <input type="text" name="street" id="edit_street" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay *</label>
                        <input type="text" name="barangay" id="edit_barangay" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality *</label>
                        <input type="text" name="city" id="edit_city" required>
                    </div>
                    <div class="form-group">
                        <label>Province *</label>
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
                        <label>
                            <input type="checkbox" name="senior_citizen" id="edit_senior_citizen"> Senior Citizen
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="pwd" id="edit_pwd"> Person with Disability (PWD)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-header" style="border-top: 1px solid var(--gray-200); justify-content: flex-end; gap: 12px;">
                <button type="submit" name="update_resident" class="btn-primary">
                    <i class="fas fa-save"></i> Update Resident
                </button>
                <button type="button" onclick="closeEditModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('residentModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('residentModal').style.display = 'none';
    }
    
    function openEditModal(resident) {
        document.getElementById('edit_resident_id').value = resident.id;
        document.getElementById('edit_first_name').value = resident.first_name;
        document.getElementById('edit_last_name').value = resident.last_name;
        document.getElementById('edit_middle_name').value = resident.middle_name || '';
        document.getElementById('edit_suffix').value = resident.suffix || '';
        document.getElementById('edit_gender').value = resident.gender;
        document.getElementById('edit_birth_date').value = resident.birth_date;
        document.getElementById('edit_civil_status').value = resident.civil_status;
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
        
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == document.getElementById('residentModal')) {
            closeModal();
        }
        if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
    }
</script>
</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';

if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    $features_path = __DIR__ . '/../config/features.php';
    if (file_exists($features_path)) {
        require_once $features_path;
    }
}
$is_pwd_senior_enabled = defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR;

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$staff_id_query = "SELECT s.id, s.position, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? $staff_info['id'] : 0;

$error = '';
$success = '';

// Official Palayan City Barangays & Canonical Normalization Map
$palayan_official_barangays = [
    'Atate',
    'Aulo',
    'Bagong Buhay',
    'Baryo Militar',
    'Caballero',
    'Caimito',
    'Doña Josefa',
    'Ganaderia',
    'Imelda Valley',
    'Imelda Valley I',
    'Imelda Valley II',
    'Langka',
    'Malate',
    'Maligaya',
    'Manacnac',
    'Mapaet',
    'Marcos Village',
    'Popolon (Pagas)',
    'Santolan',
    'Sapang Buho',
    'Singalat'
];

$canonical_barangays = [
    'atate' => 'Atate',
    'aulo' => 'Aulo',
    'bagong buhay' => 'Bagong Buhay',
    'baryo militar' => 'Baryo Militar',
    'bo. militar' => 'Baryo Militar',
    'bo militar' => 'Baryo Militar',
    'militar' => 'Baryo Militar',
    'caballero' => 'Caballero',
    'caimito' => 'Caimito',
    'dona josefa' => 'Doña Josefa',
    'doña josefa' => 'Doña Josefa',
    'ganaderia' => 'Ganaderia',
    'imelda valley' => 'Imelda Valley',
    'imelda valley i' => 'Imelda Valley I',
    'imelda valley ii' => 'Imelda Valley II',
    'langka' => 'Langka',
    'malate' => 'Malate',
    'malate (poblacion)' => 'Malate',
    'malate poblacion' => 'Malate',
    'maligaya' => 'Maligaya',
    'manacnac' => 'Manacnac',
    'mapaet' => 'Mapaet',
    'mapait' => 'Mapaet',
    'marcos village' => 'Marcos Village',
    'popolon (pagas)' => 'Popolon (Pagas)',
    'popolon(pagas)' => 'Popolon (Pagas)',
    'popolon' => 'Popolon (Pagas)',
    'pagas' => 'Popolon (Pagas)',
    'santolan' => 'Santolan',
    'sapang Buho' => 'Sapang Buho',
    'sapang buho' => 'Sapang Buho',
    'singalat' => 'Singalat'
];

// Handle resident creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_resident'])) {
    csrf_verify();
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
    $raw_barangay = sanitize($_POST['barangay'] ?? '');
    $city = 'PALAYAN CITY';
    $province = 'Nueva Ecija';
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

    // Validate barangay against official Palayan City list
    $barangay_key = strtolower(trim($raw_barangay));
    if (empty($raw_barangay) || !isset($canonical_barangays[$barangay_key])) {
        $error = "Please select a valid official barangay of Palayan City.";
    } else {
        $barangay = $canonical_barangays[$barangay_key];
    }
    
    // Validate required fields
    if (empty($error)) {
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($birth_date) || 
            empty($civil_status) || empty($street) || empty($barangay)) {
            $error = "Please fill in all required fields";
        }
    }
    
    if (empty($error)) {
        // Generate resident ID (format: RES-YYYY-XXXX)
        $year = date('Y');
        $resident_number = generateResidentNumber($db, (int)$year);
        
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
    csrf_verify();
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
    $raw_barangay = sanitize($_POST['barangay'] ?? '');
    $city = 'PALAYAN CITY';
    $province = 'Nueva Ecija';
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

    // Validate barangay against official Palayan City list
    $barangay_key = strtolower(trim($raw_barangay));
    if (empty($raw_barangay) || !isset($canonical_barangays[$barangay_key])) {
        $error = "Please select a valid official barangay of Palayan City.";
    } else {
        $barangay = $canonical_barangays[$barangay_key];
    }
    
    if (empty($error)) {
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($birth_date) || 
            empty($civil_status) || empty($street) || empty($barangay)) {
            $error = "Please fill in all required fields";
        }
    }
    
    if (empty($error)) {
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

// Handle resident deletion (Restricted to Admin & Checks all modules)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    if (!isAdmin()) {
        $error = "Deletion of resident master records is restricted to System Administrators. Staff members may deactivate accounts instead.";
    } else {
        $resident_id = sanitize($_GET['id']);
        
        // Check if resident has any appointments, requests, or applications
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

        $check_senior = $db->prepare("SELECT COUNT(*) FROM senior_applications WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)");
        $check_senior->execute([':id' => $resident_id]);
        $has_senior = $check_senior->fetchColumn();

        $check_pwd = $db->prepare("SELECT COUNT(*) FROM pwd_applications WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)");
        $check_pwd->execute([':id' => $resident_id]);
        $has_pwd = $check_pwd->fetchColumn();

        $check_verif = $db->prepare("SELECT COUNT(*) FROM verification_requests WHERE user_id = (SELECT user_id FROM residents WHERE id = :id)");
        $check_verif->execute([':id' => $resident_id]);
        $has_verif = $check_verif->fetchColumn();
        
        if ($has_appointments > 0 || $has_aics > 0 || $has_housing > 0 || $has_senior > 0 || $has_pwd > 0 || $has_verif > 0) {
            $error = "Cannot delete resident with existing service records. You can deactivate instead.";
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
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query with filters
$query = "SELECT * FROM residents WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR CONCAT(first_name, ' ', last_name) LIKE :search OR resident_number LIKE :search OR contact_number LIKE :search)";
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

// Get unique barangays for filter, combining official Palayan City barangays and existing data
$db_barangays = $db->query("SELECT DISTINCT barangay FROM residents WHERE barangay IS NOT NULL AND barangay != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_filter_barangays = array_values(array_unique(array_merge($palayan_official_barangays, $db_barangays)));
natcasesort($all_filter_barangays);
$barangays = [];
foreach ($all_filter_barangays as $b_name) {
    $barangays[] = ['barangay' => $b_name];
}

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
$page_title = 'Manage Residents - CSWDO Staff Portal';
$active_nav = 'residents';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
/* Metric Cards (Compact & Minimalist - Part 2, Items 7 & 8) */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
.stat-card { background: var(--ref-card); border-radius: 16px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.03); border: 1px solid var(--ref-border); transition: transform 0.2s, box-shadow 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.06); border-color: var(--ref-maroon); }
.stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.stat-icon.total { background: #F1F5F9; color: #475569; }
.stat-icon.active { background: #DCFCE7; color: #16A34A; }
.stat-icon.senior { background: #FEF3C7; color: #D97706; }
.stat-icon.pwd { background: #E0E7FF; color: #4F46E5; }
.stat-info h3 { font-size: 1.45rem; font-weight: 800; color: var(--ref-text); line-height: 1.1; margin: 0 0 2px 0; }
.stat-info p { color: var(--ref-muted); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin: 0; }

/* Filter Bar */
.filter-bar { background: var(--ref-card); border-radius: 16px; padding: 16px 20px; margin-bottom: 22px; border: 1px solid var(--ref-border); box-shadow: var(--ref-shadow-sm); }
.filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr auto auto; gap: 12px; align-items: end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 0.72rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.filter-group input, .filter-group select { height: 38px; padding: 0 12px; border: 1px solid var(--ref-border); border-radius: 10px; font-size: 0.82rem; background: var(--ref-canvas); color: var(--ref-text); outline: none; transition: border-color 0.2s; }
.filter-group input:focus, .filter-group select:focus { border-color: var(--ref-maroon); background: var(--ref-card); }
/* Buttons */
.btn-action-primary {
    background: linear-gradient(135deg, #7F0303 0%, #9B0F0F 100%);
    color: #FFFFFF;
    border: none;
    padding: 9px 20px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.25);
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-action-primary:hover {
    background: linear-gradient(135deg, #6B0202 0%, #850A0A 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.35);
    color: #FFFFFF;
}

.btn-action-secondary {
    background: var(--ref-canvas, #E4EFE7);
    color: var(--ref-text, #192126);
    border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.12));
    padding: 8px 18px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-action-secondary:hover {
    background: #D5E4D9;
    border-color: rgba(127, 3, 3, 0.25);
    color: #7F0303;
    transform: translateY(-2px);
}

.btn-primary { background: var(--ref-maroon); color: white; border: none; height: 38px; padding: 0 18px; border-radius: 50px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-secondary { background: var(--ref-canvas); color: var(--ref-muted); border: 1px solid var(--ref-border); height: 38px; padding: 0 14px; border-radius: 50px; font-weight: 600; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }

/* Data Table */
.table-container { background: var(--ref-card); border-radius: 18px; overflow-x: auto; overflow-y: visible; padding-bottom: 35px; box-shadow: var(--ref-shadow-sm); border: 1px solid var(--ref-border); margin-bottom: 24px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: var(--ref-canvas); border-bottom: 1px solid var(--ref-border); }
.data-table th { padding: 11px 14px; text-align: left; font-size: 0.7rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.data-table td { padding: 12px 14px; border-bottom: 1px solid var(--ref-border); font-size: 0.82rem; color: var(--ref-text); vertical-align: middle; position: relative; }
.data-table tbody tr:hover { background: #FAF8F5; }
.data-table tbody tr:last-child td { border-bottom: none; }

.res-num-code {
    font-family: 'Courier Prime', monospace;
    font-weight: 700;
    color: #7F0303;
    letter-spacing: 0.04em;
}

/* Status & Tags */
.status-badge { padding: 3px 9px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
.status-active { background: #DCFCE7; color: #15803D; }
.status-inactive { background: #FEE2E2; color: #B91C1C; }
.category-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; margin: 2px; }
.category-badge.cat-senior { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.category-badge.cat-pwd { background: #EDE9FE; color: #6D28D9; border: 1px solid #DDD6FE; }
.category-badge.cat-general { background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE; }

/* Standardized Row Action Dropdown (Part 2, Item 5 & Dropup) */
.row-action-dropdown { position: relative; display: inline-block; }
.row-action-dropdown.dropup .row-action-menu {
    top: auto !important;
    bottom: calc(100% + 4px) !important;
}
.btn-row-action {
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    border-radius: 50px;
    padding: 5px 14px;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ref-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s;
}
.btn-row-action:hover, .row-action-dropdown.open .btn-row-action {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}
.row-action-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    min-width: 190px;
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.18);
    z-index: 1200;
    padding: 6px;
    text-align: left;
}
.row-action-dropdown.open .row-action-menu {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.action-menu-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--ref-text);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    width: 100%;
    transition: all 0.15s;
    box-sizing: border-box;
}
.action-menu-item:hover {
    background: var(--ref-canvas);
    color: var(--ref-maroon);
}
.action-menu-item.danger-item { color: #DC2626; }
.action-menu-item.danger-item:hover { background: #FEE2E2; color: #991B1B; }
.action-menu-divider { height: 1px; background: var(--ref-border); margin: 4px 0; }

/* Modals */
.modal { position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); display: none; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal-content { background: var(--ref-card); border-radius: var(--ref-radius-xl); width: 90%; max-width: 780px; max-height: 90vh; overflow-y: auto; border: 1px solid var(--ref-border); box-shadow: var(--ref-shadow-lg); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--ref-border); display: flex; justify-content: space-between; align-items: center; background: var(--ref-card-subtle); }
.modal-header h2 { font-size: 1.15rem; font-weight: 800; color: var(--ref-text); margin: 0; display: flex; align-items: center; gap: 8px; }
.modal-body { padding: 24px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 700; font-size: 0.78rem; color: var(--ref-text); text-transform: uppercase; letter-spacing: 0.04em; }
.form-group input, .form-group select { width: 100%; height: 38px; padding: 0 12px; border: 1px solid var(--ref-border); border-radius: 8px; font-size: 0.84rem; background: var(--ref-card); color: var(--ref-text); outline: none; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus { border-color: var(--ref-maroon); box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08); }

@media (max-width: 1000px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .filter-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .quick-actions-bar { flex-direction: column; gap: 12px; text-align: center; }
}
</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Residents Directory';
            $banner_subtitle = 'Manage registered Palayan City residents and family profiling';
            $banner_icon = 'fa-users';
            $banner_actions = [
                ['label' => 'Add Resident', 'url' => 'javascript:void(0);', 'onclick' => 'openModal()', 'icon' => 'fa-user-plus', 'type' => 'primary'],
                ['label' => 'Export CSV', 'url' => 'export-residents.php', 'icon' => 'fa-file-export', 'type' => 'secondary']
            ];
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <?php if($error): ?>
                <div class="alert alert-error" style="background: #FEE2E2; color: #991B1B; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; border-left: 4px solid #EF4444;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success" style="background: #F0FDF4; color: #166534; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; border-left: 4px solid #22C55E;">
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
                <div class="stat-icon total"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_residents; ?></h3>
                    <p>Total Residents</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3 style="color:#16A34A;"><?php echo $active_residents; ?></h3>
                    <p>Active Residents</p>
                </div>
            </div>
            <?php if (!empty($is_pwd_senior_enabled)): ?>
            <div class="stat-card">
                <div class="stat-icon senior"><i class="fas fa-person-cane"></i></div>
                <div class="stat-info">
                    <h3 style="color:#D97706;"><?php echo $senior_count; ?></h3>
                    <p>Senior Citizens</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pwd"><i class="fas fa-wheelchair"></i></div>
                <div class="stat-info">
                    <h3 style="color:#4F46E5;"><?php echo $pwd_count; ?></h3>
                    <p>PWD Sector</p>
                </div>
            </div>
            <?php endif; ?>
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
                            $age = !empty($resident['birth_date']) ? date_diff(date_create($resident['birth_date']), date_create('today'))->y : 'N/A';
                            $res_num = !empty($resident['resident_number']) ? $resident['resident_number'] : ('RES-' . str_pad($resident['id'], 5, '0', STR_PAD_LEFT));
                            $categories = [];
                            if (!empty($is_pwd_senior_enabled)) {
                                if (!empty($resident['senior_citizen'])) $categories[] = ['name' => 'Senior', 'class' => 'cat-senior'];
                                if (!empty($resident['pwd'])) $categories[] = ['name' => 'PWD', 'class' => 'cat-pwd'];
                            }
                        ?>
                        <tr>
                            <td><strong class="res-num-code"><?php echo htmlspecialchars($res_num); ?></strong></td>
                            <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($resident['barangay'] ?: 'Palayan City'); ?></td>
                            <td><?php echo htmlspecialchars($resident['contact_number'] ?: 'N/A'); ?></td>
                            <td><?php echo !empty($resident['birth_date']) ? date('M d, Y', strtotime($resident['birth_date'])) : 'N/A'; ?></td>
                            <td><?php echo $age !== 'N/A' ? ($age . ' yrs') : 'N/A'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $resident['is_active'] == 1 ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-<?php echo $resident['is_active'] == 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $resident['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if(!empty($categories)): ?>
                                    <?php foreach($categories as $cat): ?>
                                        <span class="category-badge <?php echo $cat['class']; ?>"><?php echo $cat['name']; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="category-badge cat-general">General Resident</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <div class="row-action-dropdown">
                                    <button type="button" class="btn-dot-action" onclick="toggleRowAction(event, this)" title="Actions" aria-label="Row Actions">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="row-action-menu">
                                        <button type="button" class="action-menu-item" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                            <i class="fas fa-id-badge" style="color: #059669;"></i> View Details
                                        </button>
                                        <button type="button" class="action-menu-item" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                            <i class="fas fa-user-edit" style="color: #2563EB;"></i> Edit Profile
                                        </button>
                                        <div class="action-menu-divider"></div>
                                        <a href="manage-aics.php?search=<?php echo urlencode($resident['first_name'] . ' ' . $resident['last_name']); ?>" class="action-menu-item" title="View AICS cases for this resident">
                                            <i class="fas fa-hand-holding-heart" style="color: var(--ref-maroon, #7F0303);"></i> AICS Casework
                                        </a>
                                        <a href="manage-appointments.php?search=<?php echo urlencode($resident['first_name'] . ' ' . $resident['last_name']); ?>" class="action-menu-item" title="View appointments for this resident">
                                            <i class="fas fa-calendar-check" style="color: #0D9488;"></i> Appointments
                                        </a>
                                        <div class="action-menu-divider"></div>
                                        <a href="?toggle_status=1&id=<?php echo $resident['id']; ?>" class="action-menu-item" onclick="return confirm('<?php echo $resident['is_active'] == 1 ? 'Deactivate this resident account?' : 'Activate this resident account?'; ?>')">
                                            <i class="fas fa-toggle-<?php echo $resident['is_active'] == 1 ? 'off' : 'on'; ?>" style="color: #D97706;"></i>
                                            <?php echo $resident['is_active'] == 1 ? 'Deactivate Account' : 'Activate Account'; ?>
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <div class="action-menu-divider"></div>
                                        <a href="?delete=1&id=<?php echo $resident['id']; ?>" class="action-menu-item danger-item" onclick="return confirm('Delete this resident? This action cannot be undone if no transactions exist.')">
                                            <i class="fas fa-trash-alt"></i> Delete Record
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> <!-- Close table-container -->
    </div> <!-- Close staff-big-card -->
</main>
</div> <!-- Close app-container -->

<!-- Add Resident Modal -->
<div id="residentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Resident</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
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
                        <input type="text" name="street" required placeholder="e.g. Saphire St., Purok 3">
                    </div>
                    <div class="form-group">
                        <label>Barangay *</label>
                        <select name="barangay" id="add_barangay" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($palayan_official_barangays as $bgy): ?>
                                <option value="<?php echo htmlspecialchars($bgy); ?>"><?php echo htmlspecialchars($bgy); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality *</label>
                        <input type="text" name="city" id="add_city" value="PALAYAN CITY" required readonly style="background-color: var(--ref-card-alt, #f8fafc); cursor: not-allowed;" title="Palayan City is the default jurisdiction">
                    </div>
                    <div class="form-group">
                        <label>Province *</label>
                        <input type="text" name="province" id="add_province" value="Nueva Ecija" required readonly style="background-color: var(--ref-card-alt, #f8fafc); cursor: not-allowed;">
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
                <?php if (!empty($is_pwd_senior_enabled)): ?>
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
                <?php endif; ?>
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
            <?php echo csrf_field(); ?>
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
                        <select name="barangay" id="edit_barangay" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($palayan_official_barangays as $bgy): ?>
                                <option value="<?php echo htmlspecialchars($bgy); ?>"><?php echo htmlspecialchars($bgy); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City/Municipality *</label>
                        <input type="text" name="city" id="edit_city" value="PALAYAN CITY" required readonly style="background-color: var(--ref-card-alt, #f8fafc); cursor: not-allowed;" title="Palayan City is the default jurisdiction">
                    </div>
                    <div class="form-group">
                        <label>Province *</label>
                        <input type="text" name="province" id="edit_province" value="Nueva Ecija" required readonly style="background-color: var(--ref-card-alt, #f8fafc); cursor: not-allowed;">
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
                <?php if (!empty($is_pwd_senior_enabled)): ?>
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
                <?php endif; ?>
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

<!-- View Resident Dossier Modal -->
<div id="viewModal" class="modal" style="display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div class="modal-content" style="max-width: 680px; width: 92%; max-height: 90vh; overflow-y: auto; border-radius: 18px; padding: 24px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--gray-200); padding-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(127, 3, 3, 0.1); color: var(--ref-maroon, #7F0303); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fas fa-id-card"></i>
                </div>
                <div>
                    <h3 id="view_resident_name" style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--ref-text);">Resident Profile</h3>
                    <p id="view_resident_number" style="margin: 2px 0 0; font-size: 0.8rem; color: var(--ref-muted); font-weight: 600;">RES-0000</p>
                </div>
            </div>
            <button type="button" onclick="closeViewModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--ref-muted);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 18px 0; display: flex; flex-direction: column; gap: 16px;">
            <!-- Status & Verification Row -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <span id="view_status_badge" class="badge">Active</span>
                <span id="view_verified_badge" class="badge">Verified</span>
            </div>

            <!-- Grid Details -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; background: var(--ref-canvas, #f8fafc); padding: 16px; border-radius: 12px; border: 1px solid var(--ref-border, #e2e8f0);">
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Contact Number</label>
                    <p id="view_contact_number" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Email Address</label>
                    <p id="view_email" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Barangay & Address</label>
                    <p id="view_address" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Gender & Civil Status</label>
                    <p id="view_demographics" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Birth Date & Age</label>
                    <p id="view_birth_age" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
                <div>
                    <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-muted);">Occupation & Income</label>
                    <p id="view_occupation_income" style="margin: 3px 0 0; font-weight: 600; font-size: 0.9rem;">-</p>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div style="background: rgba(127, 3, 3, 0.04); border-left: 4px solid var(--ref-maroon, #7F0303); padding: 12px 16px; border-radius: 8px;">
                <label style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--ref-maroon, #7F0303);">Emergency Contact</label>
                <p id="view_emergency_info" style="margin: 4px 0 0; font-size: 0.88rem; font-weight: 600;">-</p>
            </div>
        </div>
        <div class="modal-header" style="border-top: 1px solid var(--gray-200); padding-top: 14px; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeViewModal()" class="btn-secondary" style="border-radius: 50px;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
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
        
        // Populate Barangay dropdown with smart canonical matching
        const bgySelect = document.getElementById('edit_barangay');
        const residentBgy = (resident.barangay || '').trim();
        let matched = false;

        // Remove any previously appended temporary warning option
        const prevTemp = document.getElementById('temp_legacy_bgy');
        if (prevTemp) prevTemp.remove();

        if (residentBgy) {
            for (let i = 0; i < bgySelect.options.length; i++) {
                const optVal = bgySelect.options[i].value;
                if (optVal.toLowerCase() === residentBgy.toLowerCase() ||
                    optVal.replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === residentBgy.replace(/[^a-zA-Z0-9]/g, '').toLowerCase()) {
                    bgySelect.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                const invalidOpt = document.createElement('option');
                invalidOpt.id = 'temp_legacy_bgy';
                invalidOpt.value = residentBgy;
                invalidOpt.textContent = residentBgy + ' (Invalid - Please Select Palayan Barangay)';
                invalidOpt.style.color = '#DC2626';
                bgySelect.appendChild(invalidOpt);
                bgySelect.value = residentBgy;
            }
        } else {
            bgySelect.value = '';
        }

        document.getElementById('edit_city').value = 'PALAYAN CITY';
        document.getElementById('edit_province').value = 'Nueva Ecija';
        document.getElementById('edit_postal_code').value = resident.postal_code || '';
        document.getElementById('edit_household_number').value = resident.household_number || '';
        document.getElementById('edit_occupation').value = resident.occupation || '';
        document.getElementById('edit_monthly_income').value = resident.monthly_income || 0;
        document.getElementById('edit_educational_attainment').value = resident.educational_attainment || '';
        document.getElementById('edit_emergency_contact_name').value = resident.emergency_contact_name || '';
        document.getElementById('edit_emergency_contact_number').value = resident.emergency_contact_number || '';
        document.getElementById('edit_relationship').value = resident.relationship || '';
        if (document.getElementById('edit_senior_citizen')) {
            document.getElementById('edit_senior_citizen').checked = resident.senior_citizen == 1;
        }
        if (document.getElementById('edit_pwd')) {
            document.getElementById('edit_pwd').checked = resident.pwd == 1;
        }
        
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function openViewModal(resident) {
        document.getElementById('view_resident_name').textContent = (resident.first_name + ' ' + (resident.middle_name ? resident.middle_name + ' ' : '') + resident.last_name + (resident.suffix ? ' ' + resident.suffix : '')).trim();
        document.getElementById('view_resident_number').textContent = resident.resident_id_number || ('RES-' + String(resident.id).padStart(4, '0'));
        
        const statusBadge = document.getElementById('view_status_badge');
        statusBadge.textContent = resident.status ? resident.status.toUpperCase() : 'ACTIVE';
        statusBadge.className = 'badge ' + (resident.status === 'inactive' ? 'badge-danger' : 'badge-success');

        const verBadge = document.getElementById('view_verified_badge');
        const isVer = resident.verification_status === 'verified';
        verBadge.textContent = isVer ? 'VERIFIED RESIDENT' : 'PENDING VERIFICATION';
        verBadge.className = 'badge ' + (isVer ? 'badge-primary' : 'badge-warning');

        document.getElementById('view_contact_number').textContent = resident.contact_number || 'None provided';
        document.getElementById('view_email').textContent = resident.email || 'None provided';
        
        const address = [resident.street, resident.barangay, resident.city || 'Palayan City', resident.province || 'Nueva Ecija'].filter(Boolean).join(', ');
        document.getElementById('view_address').textContent = address || 'Palayan City';
        
        document.getElementById('view_demographics').textContent = (resident.gender || 'Not specified') + ' • ' + (resident.civil_status || 'Single');
        
        let ageText = resident.age ? resident.age + ' yrs old' : '';
        document.getElementById('view_birth_age').textContent = (resident.birth_date || 'N/A') + (ageText ? ' (' + ageText + ')' : '');
        
        const incomeFormatted = resident.monthly_income ? '₱' + Number(resident.monthly_income).toLocaleString() : '₱0';
        document.getElementById('view_occupation_income').textContent = (resident.occupation || 'N/A') + ' • ' + incomeFormatted + '/mo';
        
        if (resident.emergency_contact_name || resident.emergency_contact_number) {
            document.getElementById('view_emergency_info').textContent = (resident.emergency_contact_name || 'N/A') + ' (' + (resident.relationship || 'Contact') + ') - ' + (resident.emergency_contact_number || 'No number');
        } else {
            document.getElementById('view_emergency_info').textContent = 'No emergency contact on file';
        }
        
        document.getElementById('viewModal').style.display = 'flex';
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }
    
    function toggleRowAction(e, btn) {
        e.stopPropagation();
        const dropdown = btn.closest('.row-action-dropdown');
        if (!dropdown) return;
        const isOpen = dropdown.classList.contains('open') || dropdown.classList.contains('active');
        closeAllActionMenus();
        if (!isOpen) {
            const btnRect = btn.getBoundingClientRect();
            const spaceBelow = window.innerHeight - btnRect.bottom;
            if (spaceBelow < 220) {
                dropdown.classList.add('dropup');
            }
            dropdown.classList.add('open');
            dropdown.classList.add('active');
        }
    }

    function closeAllActionMenus() {
        document.querySelectorAll('.row-action-dropdown.open, .row-action-dropdown.active').forEach(dd => {
            dd.classList.remove('open', 'active', 'dropup');
        });
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('residentModal')) {
            closeModal();
        }
        if (event.target == document.getElementById('viewModal')) {
            closeViewModal();
        }
        if (event.target == document.getElementById('editModal')) {
            closeEditModal();
        }
        closeAllActionMenus();
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeEditModal();
            closeViewModal();
            closeAllActionMenus();
        }
    });
</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

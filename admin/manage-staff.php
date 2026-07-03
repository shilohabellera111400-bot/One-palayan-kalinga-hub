<?php
// admin/manage-staff.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Generate a new Employee ID and Staff Number for preview
$year = date('Y');
$count_query = "SELECT COUNT(*) as count FROM staff WHERE YEAR(created_at) = $year";
$count_result = $db->query($count_query);
$count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
$preview_employee_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
$preview_staff_number = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

// Handle staff creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_staff'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $middle_name = sanitize($_POST['middle_name'] ?? '');
    $contact_number = sanitize($_POST['contact_number']);
    $position = sanitize($_POST['position'] ?? 'Staff');
    $hire_date = sanitize($_POST['hire_date'] ?? date('Y-m-d'));
    
    // Get selected services (AICS, Housing, Senior, PWD)
    $assigned_services = isset($_POST['services']) ? $_POST['services'] : [];
    
    // Handle profile picture upload
    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture = 'uploads/profiles/' . $new_filename;
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $error = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF";
        }
    }
    
    // Validate
    if (empty($position)) {
        $error = "Position is required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif (empty($assigned_services)) {
        $error = "Please select at least one service to assign";
    } else {
        // Check if username or email exists
        $check_query = "SELECT user_id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username or email already exists";
        } else {
            // Generate employee ID (format: EMP-YYYY-XXXX)
            $year = date('Y');
            $count_query = "SELECT COUNT(*) as count FROM staff WHERE YEAR(created_at) = $year";
            $count_result = $db->query($count_query);
            $count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
            $employee_id = 'EMP-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Generate staff number (format: STF-YYYY-XXXX)
            $staff_number = 'STF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            $db->beginTransaction();
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, first_name, last_name, contact_number, role, status, profile_picture) 
                          VALUES (:username, :password, :email, :first_name, :last_name, :contact_number, 'staff', 'active', :profile_picture)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->bindParam(':profile_picture', $profile_picture);
                $stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Build position string from selected services
                $position_list = [];
                $service_map = [
                    'AICS' => 'AICS Staff',
                    'HOUSING' => 'Housing Staff',
                    'SENIOR' => 'Senior Citizen Staff',
                    'PWD' => 'PWD Staff'
                ];
                
                foreach ($assigned_services as $svc) {
                    if (isset($service_map[$svc])) {
                        $position_list[] = $service_map[$svc];
                    }
                }
                $position = !empty($position_list) ? implode(', ', $position_list) : 'Staff';
                
                $staff_query = "INSERT INTO staff (user_id, staff_number, first_name, last_name, middle_name, position, contact_number, specialization, employee_id, hire_date, is_active) 
                                VALUES (:user_id, :staff_number, :first_name, :last_name, :middle_name, :position, :contact_number, :specialization, :employee_id, :hire_date, 1)";
                $staff_stmt = $db->prepare($staff_query);
                $staff_stmt->bindParam(':user_id', $user_id);
                $staff_stmt->bindParam(':staff_number', $staff_number);
                $staff_stmt->bindParam(':first_name', $first_name);
                $staff_stmt->bindParam(':last_name', $last_name);
                $staff_stmt->bindParam(':middle_name', $middle_name);
                $staff_stmt->bindParam(':position', $position);
                $staff_stmt->bindParam(':contact_number', $contact_number);
                $specialization = implode(', ', $assigned_services);
                $staff_stmt->bindParam(':specialization', $specialization);
                $staff_stmt->bindParam(':employee_id', $employee_id);
                $staff_stmt->bindParam(':hire_date', $hire_date);
                $staff_stmt->execute();
                
                $staff_id = $db->lastInsertId();
                
                // Map form values to exact category names in database
                $category_mapping = [
                    'AICS' => 'AICS',
                    'HOUSING' => 'Housing',
                    'SENIOR' => 'Senior Citizen',
                    'PWD' => 'PWD'
                ];
                
                foreach ($assigned_services as $service_key) {
                    if (isset($category_mapping[$service_key])) {
                        $category_name = $category_mapping[$service_key];
                        
                        // Get category_id from service_categories
                        $cat_query = "SELECT category_id FROM service_categories WHERE category_name = :category_name AND is_active = 1 LIMIT 1";
                        $cat_stmt = $db->prepare($cat_query);
                        $cat_stmt->bindParam(':category_name', $category_name);
                        $cat_stmt->execute();
                        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($category) {
                            // Insert into staff_service_assignments
                            $assign_query = "INSERT INTO staff_service_assignments (staff_id, category_id, is_active, assigned_at) 
                                             VALUES (:staff_id, :category_id, 1, NOW())";
                            $assign_stmt = $db->prepare($assign_query);
                            $assign_stmt->bindParam(':staff_id', $staff_id);
                            $assign_stmt->bindParam(':category_id', $category['category_id']);
                            $assign_stmt->execute();
                        }
                    }
                }
                
                $db->commit();
                $success = "Staff account created successfully!<br>Employee ID: <strong>$employee_id</strong> | Staff Number: <strong>$staff_number</strong>";
                
                header("Location: manage-staff.php");
                exit();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to create staff: " . $e->getMessage();
            }
        }
    }
}

// Handle staff status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $staff_id = sanitize($_GET['id']);
    
    $query = "SELECT is_active FROM staff WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $staff_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        $new_status = $staff['is_active'] == 1 ? 0 : 1;
        $update = "UPDATE staff SET is_active = :status WHERE id = :id";
        $update_stmt = $db->prepare($update);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':id', $staff_id);
        $update_stmt->execute();
        
        $user_update = "UPDATE users SET status = :status WHERE user_id = (SELECT user_id FROM staff WHERE id = :id)";
        $user_stmt = $db->prepare($user_update);
        $user_status = $new_status == 1 ? 'active' : 'inactive';
        $user_stmt->bindParam(':status', $user_status);
        $user_stmt->bindParam(':id', $staff_id);
        $user_stmt->execute();
        
        $success = "Staff status updated!";
        header("Location: manage-staff.php");
        exit();
    }
}

// Handle staff deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $staff_id = sanitize($_GET['id']);
    
    try {
        $db->beginTransaction();
        
        $get_user = "SELECT user_id FROM staff WHERE id = :id";
        $get_stmt = $db->prepare($get_user);
        $get_stmt->bindParam(':id', $staff_id);
        $get_stmt->execute();
        $staff_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff_data) {
            $user_id = $staff_data['user_id'];
            
            // Delete profile picture if exists
            $get_pic = "SELECT profile_picture FROM users WHERE user_id = :user_id";
            $pic_stmt = $db->prepare($get_pic);
            $pic_stmt->bindParam(':user_id', $user_id);
            $pic_stmt->execute();
            $pic_data = $pic_stmt->fetch(PDO::FETCH_ASSOC);
            if ($pic_data && !empty($pic_data['profile_picture'])) {
                $file_path = __DIR__ . '/../' . $pic_data['profile_picture'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            $delete_assignments = "DELETE FROM staff_service_assignments WHERE staff_id = :id";
            $del_assign = $db->prepare($delete_assignments);
            $del_assign->bindParam(':id', $staff_id);
            $del_assign->execute();
            
            $delete_staff = "DELETE FROM staff WHERE id = :id";
            $del_stmt = $db->prepare($delete_staff);
            $del_stmt->bindParam(':id', $staff_id);
            $del_stmt->execute();
            
            $delete_audit_logs = "DELETE FROM audit_logs WHERE user_id = :user_id";
            $del_audit = $db->prepare($delete_audit_logs);
            $del_audit->bindParam(':user_id', $user_id);
            $del_audit->execute();
            
            $delete_notifications = "DELETE FROM notifications WHERE user_id = :user_id";
            $del_notif = $db->prepare($delete_notifications);
            $del_notif->bindParam(':user_id', $user_id);
            $del_notif->execute();
            
            $delete_verifications = "DELETE FROM verification_requests WHERE user_id = :user_id";
            $del_verif = $db->prepare($delete_verifications);
            $del_verif->bindParam(':user_id', $user_id);
            $del_verif->execute();
            
            $delete_user = "DELETE FROM users WHERE user_id = :user_id";
            $del_user = $db->prepare($delete_user);
            $del_user->bindParam(':user_id', $user_id);
            $del_user->execute();
            
            $db->commit();
            $success = "Staff account deleted successfully!";
            header("Location: manage-staff.php");
            exit();
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Failed to delete staff: " . $e->getMessage();
        error_log("Delete staff error: " . $e->getMessage());
    }
}

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM staff s JOIN users u ON s.user_id = u.user_id WHERE u.role = 'staff'";
$count_result = $db->query($count_query);
$total_records = $count_result->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get all staff with their assigned services with pagination
$query = "SELECT s.*, u.email, u.status as user_status, u.profile_picture 
          FROM staff s
          JOIN users u ON s.user_id = u.user_id
          WHERE u.role = 'staff'
          ORDER BY s.created_at DESC
          LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff assigned services for each staff
$staff_services = [];
foreach ($staff_list as $staff) {
    $serv_query = "SELECT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $serv_stmt = $db->prepare($serv_query);
    $serv_stmt->bindParam(':staff_id', $staff['id']);
    $serv_stmt->execute();
    $staff_services[$staff['id']] = $serv_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get all staff count for stats (without pagination)
$all_staff_query = "SELECT s.* FROM staff s JOIN users u ON s.user_id = u.user_id WHERE u.role = 'staff'";
$all_staff = $db->query($all_staff_query)->fetchAll(PDO::FETCH_ASSOC);
$staff_count = count($all_staff);
$active_count = count(array_filter($all_staff, function($s) { return $s['is_active'] == 1; }));
$inactive_count = count(array_filter($all_staff, function($s) { return $s['is_active'] == 0; }));

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Check if form should be shown (after error or first load)
$show_form = isset($_GET['show_form']) || isset($error) || isset($success);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - Admin</title>
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
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --search-bg: #FFFFFF;
            --form-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
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
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --search-bg: #1a2332;
            --form-bg: #1a2332;
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

        /* ========== STATS ROW ========== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-item {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-item .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-item .icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-item .icon.active { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-item .icon.inactive { background: rgba(244, 67, 54, 0.12); color: #EF5350; }
        .stat-item .info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-item .info p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ========== FORM CARD ========== */
        .form-card {
            background: var(--form-bg);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: none;
        }
        .form-card.show {
            display: block;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .form-card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--maroon);
        }
        .form-card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .form-card .card-header i {
            color: var(--maroon-light);
            font-size: 1.4rem;
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
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .form-group label .required {
            color: #EF5350;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group input[type="file"] {
            padding: 8px;
            border: 2px dashed var(--border-color);
            background: var(--bg-primary);
            cursor: pointer;
        }
        .form-group input[type="file"]:hover {
            border-color: var(--maroon-light);
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .form-group input[readonly] {
            background: var(--badge-bg);
            cursor: not-allowed;
            opacity: 0.8;
            border-color: var(--border-color);
        }

        /* Profile Picture Upload */
        .profile-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 10px 0;
        }
        .profile-upload .preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--badge-bg);
            border: 3px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .profile-upload .preview i {
            font-size: 2rem;
            color: var(--text-muted);
        }
        .profile-upload .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-upload .upload-info {
            flex: 1;
        }
        .profile-upload .upload-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .services-group {
            margin-bottom: 20px;
        }
        .services-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            transition: all 0.3s;
            cursor: pointer;
        }
        .checkbox-item:hover {
            border-color: var(--maroon-light);
            background: rgba(127, 3, 3, 0.04);
        }
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--maroon);
            flex-shrink: 0;
        }
        .checkbox-item label {
            font-size: 0.82rem;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 12px 32px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
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
            padding: 10px 24px;
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
            transform: translateY(-2px);
        }

        /* ========== TABLE CARD ========== */
        .table-card {
            background: var(--table-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .table-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .table-card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-card .card-header h2 i {
            color: var(--maroon-light);
        }
        .table-card .card-header .count-badge {
            background: var(--badge-bg);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .table-card .card-header .add-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--shadow-sm);
        }
        .table-card .card-header .add-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .table-card .card-header .add-btn.active {
            background: #EF5350;
        }
        .table-card .card-header .add-btn.active:hover {
            background: #C62828;
        }

        .table-container {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            background: var(--badge-bg);
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: var(--badge-bg);
        }

        .staff-avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .staff-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-avatar-small .initials {
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--text-primary);
        }
        .staff-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .staff-cell .staff-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .staff-cell .staff-id-small {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

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
        .status-badge.on-duty {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        .status-badge.off-duty {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }

        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .service-tag {
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 500;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon-light);
            border: 1px solid rgba(127, 3, 3, 0.12);
        }
        .service-tag.no-service {
            background: rgba(244, 67, 54, 0.08);
            color: #EF5350;
            border-color: rgba(244, 67, 54, 0.12);
        }

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
        .btn-edit {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .btn-edit:hover {
            background: #2196F3;
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .checkbox-group { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .form-card { padding: 20px; }
            .table-card { padding: 16px; }
            .data-table th, .data-table td { 
                padding: 8px 12px; 
                font-size: 0.75rem;
            }
            .checkbox-group { grid-template-columns: 1fr; }
            .profile-upload { flex-direction: column; align-items: center; text-align: center; }
            .pagination .page-info { width: 100%; text-align: center; margin-right: 0; margin-bottom: 8px; }
            .table-card .card-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .table-card .card-header .add-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .form-card { padding: 16px; }
            .table-card { padding: 12px; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 6px 8px; }
            .btn-sm { font-size: 0.6rem; padding: 3px 8px; }
            .service-tag { font-size: 0.55rem; padding: 1px 6px; }
            .staff-avatar-small { width: 28px; height: 28px; }
            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
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
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php" class="active"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php"><i class="fas fa-user-tag"></i> Staff Services</a></li>
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
                <h1><i class="fas fa-users" style="color: var(--maroon-light); margin-right: 10px;"></i> Employees</h1>
                <p>Manage staff accounts and profiles</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search records..." id="globalSearch">
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
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="icon total"><i class="fas fa-users"></i></div>
                <div class="info">
                    <h3><?php echo $staff_count; ?></h3>
                    <p>Total Employees</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon active"><i class="fas fa-user-check"></i></div>
                <div class="info">
                    <h3><?php echo $active_count; ?></h3>
                    <p>Active</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon inactive"><i class="fas fa-user-slash"></i></div>
                <div class="info">
                    <h3><?php echo $inactive_count; ?></h3>
                    <p>Inactive</p>
                </div>
            </div>
        </div>
        
        <!-- Staff List -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Employees</h2>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <span class="count-badge">Showing <?php echo $total_records; ?> records</span>
                    <button class="add-btn <?php echo $show_form ? 'active' : ''; ?>" id="toggleFormBtn" onclick="toggleForm()">
                        <i class="fas <?php echo $show_form ? 'fa-times' : 'fa-plus'; ?>"></i>
                        <?php echo $show_form ? 'Close Form' : 'Add Employee'; ?>
                    </button>
                </div>
            </div>
            
            <!-- Create Staff Form -->
            <div class="form-card <?php echo $show_form ? 'show' : ''; ?>" id="staffForm">
                <div class="card-header">
                    <i class="fas fa-user-plus"></i>
                    <h2>Add Employee Record</h2>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <!-- Profile Picture -->
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <div class="profile-upload">
                            <div class="preview" id="profilePreview">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="upload-info">
                                <input type="file" name="profile_picture" id="profilePicture" accept="image/*" onchange="previewImage(event)">
                                <p>Upload a profile picture (JPG, PNG, GIF - Max 5MB)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employee ID <span style="font-size: 0.7rem; color: var(--text-muted);">(Auto-generated)</span></label>
                            <input type="text" value="<?php echo $preview_employee_id; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Staff Number <span style="font-size: 0.7rem; color: var(--text-muted);">(Auto-generated)</span></label>
                            <input type="text" value="<?php echo $preview_staff_number; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" required placeholder="e.g. Juan">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" required placeholder="e.g. Dela Cruz">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" placeholder="e.g., 09123456789">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" required placeholder="Choose a username">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="staff@example.com">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" required placeholder="Min 8 characters">
                            <small>Minimum 8 characters with letters and numbers</small>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Re-enter password">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <input type="text" name="position" required placeholder="e.g., Social Worker">
                        </div>
                        <div class="form-group">
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <!-- Service Assignments -->
                    <div class="services-group">
                        <label>Assign Services <span class="required">*</span></label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" name="services[]" value="AICS" id="service_aics">
                                <label for="service_aics">🏥 AICS</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="services[]" value="HOUSING" id="service_housing">
                                <label for="service_housing">🏠 Housing</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="services[]" value="SENIOR" id="service_senior">
                                <label for="service_senior">👴 Senior Citizen</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="services[]" value="PWD" id="service_pwd">
                                <label for="service_pwd">♿ PWD</label>
                            </div>
                        </div>
                        <small>Select at least one service to assign to this staff member</small>
                    </div>
                    
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="create_staff" class="btn-primary">
                            <i class="fas fa-save"></i> Save Employee
                        </button>
                        <button type="button" onclick="toggleForm()" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($staff_list)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-data">
                                        <i class="fas fa-users"></i>
                                        <p>No employees found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($staff_list as $staff): 
                                $staff_initials = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));
                                $profile_pic = !empty($staff['profile_picture']) && file_exists(__DIR__ . '/../' . $staff['profile_picture']) 
                                    ? '../' . $staff['profile_picture'] 
                                    : '';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($staff['employee_id'] ?? 'N/A'); ?></strong></td>
                                <td>
                                    <div class="staff-cell">
                                        <div class="staff-avatar-small">
                                            <?php if(!empty($profile_pic)): ?>
                                                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
                                            <?php else: ?>
                                                <span class="initials"><?php echo $staff_initials; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="staff-name"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                                            <div class="staff-id-small"><?php echo htmlspecialchars($staff['staff_number'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($staff['position'] ?? 'Staff'); ?></td>
                                <td><?php echo htmlspecialchars($staff['contact_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td>
                                    <div class="service-tags">
                                        <?php 
                                        $services = isset($staff_services[$staff['id']]) ? $staff_services[$staff['id']] : [];
                                        if(!empty($services)):
                                            foreach($services as $service): 
                                        ?>
                                        <span class="service-tag">
                                            <?php 
                                            switch($service) {
                                                case 'AICS': echo '🏥 AICS'; break;
                                                case 'Housing': echo '🏠 Housing'; break;
                                                case 'Senior Citizen': echo '👴 Senior'; break;
                                                case 'PWD': echo '♿ PWD'; break;
                                                default: echo htmlspecialchars($service);
                                            }
                                            ?>
                                        </span>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <span class="service-tag no-service">None</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $staff['is_active'] == 1 ? 'on-duty' : 'off-duty'; ?>">
                                        <i class="fas fa-<?php echo $staff['is_active'] == 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $staff['is_active'] == 1 ? 'On Duty' : 'Off Duty'; ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                        <a href="?toggle_status=1&id=<?php echo $staff['id']; ?>" class="btn-sm btn-toggle">
                                            <i class="fas fa-toggle-<?php echo $staff['is_active'] == 1 ? 'off' : 'on'; ?>"></i>
                                            <?php echo $staff['is_active'] == 1 ? 'Off Duty' : 'On Duty'; ?>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $staff['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this staff account? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?></span>
                
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a href="?page=' . $i . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">
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

// ========== TOGGLE FORM ==========
function toggleForm() {
    const form = document.getElementById('staffForm');
    const btn = document.getElementById('toggleFormBtn');
    
    form.classList.toggle('show');
    
    if (form.classList.contains('show')) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-times"></i> Close Form';
        // Scroll to form
        setTimeout(() => {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Employee';
    }
}

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('.data-table tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
});

// ========== PROFILE PICTURE PREVIEW ==========
function previewImage(event) {
    const preview = document.getElementById('profilePreview');
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview">`;
        }
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `<i class="fas fa-user-circle"></i>`;
    }
}

// ========== SERVICE CHECKBOX HIGHLIGHT ==========
document.querySelectorAll('.checkbox-item').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            item.style.borderColor = 'var(--maroon-light)';
            item.style.background = 'rgba(127, 3, 3, 0.05)';
        } else {
            item.style.borderColor = 'var(--border-color)';
            item.style.background = 'var(--bg-primary)';
        }
    });
});

// Show form if there was an error (form already shown)
<?php if(isset($error) || isset($success)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Form is already shown via PHP condition
});
<?php endif; ?>
</script>
</body>
</html>
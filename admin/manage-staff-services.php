<?php
// admin/manage-staff-services.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get all staff for dropdown
$staff_query = "SELECT s.id, s.first_name, s.last_name, s.staff_number, s.employee_id, u.email 
                FROM staff s
                JOIN users u ON s.user_id = u.user_id
                WHERE u.role = 'staff' AND s.is_active = 1
                ORDER BY s.first_name ASC";
$staff_list = $db->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);

$selected_staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : (isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0);
$current_staff = null;
$assigned_services = [];
$all_services = ['AICS', 'Housing', 'Senior Citizen', 'PWD'];

if ($selected_staff_id > 0) {
    // Get staff info
    $staff_info_query = "SELECT s.*, u.email FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
    $staff_info_stmt = $db->prepare($staff_info_query);
    $staff_info_stmt->bindParam(':id', $selected_staff_id);
    $staff_info_stmt->execute();
    $current_staff = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get assigned services
    $assigned_query = "SELECT ssa.id, sc.category_id, sc.category_name 
                       FROM staff_service_assignments ssa
                       JOIN service_categories sc ON ssa.category_id = sc.category_id
                       WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
                       AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
    $assigned_stmt = $db->prepare($assigned_query);
    $assigned_stmt->bindParam(':staff_id', $selected_staff_id);
    $assigned_stmt->execute();
    $assigned_services = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
    $assigned_names = array_column($assigned_services, 'category_name');
}

// Handle service assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_service'])) {
    $staff_id = intval($_POST['staff_id']);
    $service_to_assign = $_POST['service_to_assign'] ?? '';
    
    if ($staff_id > 0 && !empty($service_to_assign)) {
        $service_mapping = [
            'AICS' => 'AICS',
            'HOUSING' => 'Housing', 
            'SENIOR' => 'Senior Citizen',
            'PWD' => 'PWD'
        ];
        
        $db_category_name = isset($service_mapping[$service_to_assign]) ? $service_mapping[$service_to_assign] : $service_to_assign;
        
        $cat_query = "SELECT category_id FROM service_categories WHERE category_name = :category_name AND is_active = 1 LIMIT 1";
        $cat_stmt = $db->prepare($cat_query);
        $cat_stmt->bindParam(':category_name', $db_category_name);
        $cat_stmt->execute();
        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $error = "Service category '$db_category_name' not found in database.";
        } else {
            $check_query = "SELECT id FROM staff_service_assignments 
                           WHERE staff_id = :staff_id AND category_id = :category_id AND is_active = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':staff_id', $staff_id);
            $check_stmt->bindParam(':category_id', $category['category_id']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                $insert_query = "INSERT INTO staff_service_assignments (staff_id, category_id, is_active, assigned_at) 
                                VALUES (:staff_id, :category_id, 1, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':staff_id', $staff_id);
                $insert_stmt->bindParam(':category_id', $category['category_id']);
                
                if ($insert_stmt->execute()) {
                    $success = "Service assigned successfully!";
                    header("Location: manage-staff-services.php?staff_id=" . $staff_id);
                    exit();
                } else {
                    $error = "Failed to assign service.";
                }
            } else {
                $error = "Service already assigned to this staff member.";
            }
        }
    } else {
        $error = "Please select a staff member and a service.";
    }
}

// Handle service removal
if (isset($_GET['remove_service']) && isset($_GET['id'])) {
    $assignment_id = intval($_GET['remove_service']);
    $staff_id = intval($_GET['staff_id'] ?? 0);
    
    $delete_query = "DELETE FROM staff_service_assignments WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $assignment_id);
    
    if ($delete_stmt->execute()) {
        $success = "Service removed successfully!";
        header("Location: manage-staff-services.php?staff_id=" . $staff_id);
        exit();
    } else {
        $error = "Failed to remove service.";
    }
}

// Handle granting all services
if (isset($_GET['grant_all']) && isset($_GET['staff_id'])) {
    $staff_id = intval($_GET['staff_id']);
    
    $service_mapping = [
        'AICS' => 'AICS',
        'HOUSING' => 'Housing',
        'SENIOR' => 'Senior Citizen',
        'PWD' => 'PWD'
    ];
    
    $granted = 0;
    foreach ($service_mapping as $key => $db_name) {
        $cat_query = "SELECT category_id FROM service_categories WHERE category_name = :category_name AND is_active = 1 LIMIT 1";
        $cat_stmt = $db->prepare($cat_query);
        $cat_stmt->bindParam(':category_name', $db_name);
        $cat_stmt->execute();
        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            $check_query = "SELECT id FROM staff_service_assignments 
                           WHERE staff_id = :staff_id AND category_id = :category_id AND is_active = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':staff_id', $staff_id);
            $check_stmt->bindParam(':category_id', $category['category_id']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                $insert_query = "INSERT INTO staff_service_assignments (staff_id, category_id, is_active, assigned_at) 
                                VALUES (:staff_id, :category_id, 1, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':staff_id', $staff_id);
                $insert_stmt->bindParam(':category_id', $category['category_id']);
                if ($insert_stmt->execute()) {
                    $granted++;
                }
            }
        }
    }
    
    if ($granted > 0) {
        $success = "All services granted successfully!";
    } else {
        $error = "No new services to grant.";
    }
    header("Location: manage-staff-services.php?staff_id=" . $staff_id);
    exit();
}

// Handle reset all services
if (isset($_GET['reset_all']) && isset($_GET['staff_id'])) {
    $staff_id = intval($_GET['staff_id']);
    
    $delete_query = "DELETE FROM staff_service_assignments WHERE staff_id = :staff_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':staff_id', $staff_id);
    
    if ($delete_stmt->execute()) {
        $success = "All services reset successfully!";
    } else {
        $error = "Failed to reset services.";
    }
    header("Location: manage-staff-services.php?staff_id=" . $staff_id);
    exit();
}

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
    <title>Staff Services - Admin</title>
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
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-aics: #7F0303;
            --color-housing: #0F414A;
            --color-senior: #2E7D32;
            --color-pwd: #6A1B9A;
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
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-aics: #C62828;
            --color-housing: #1565C0;
            --color-senior: #2E7D32;
            --color-pwd: #7B1FA2;
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

        /* ========== STAFF SELECTOR ========== */
        .staff-selector-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }
        .staff-selector-card .selector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .staff-selector-card .selector-header h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .staff-selector-card .selector-header h3 i {
            color: var(--maroon-light);
        }
        .staff-selector-card .selector-header .count-badge {
            background: var(--badge-bg);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .staff-item {
            padding: 10px 14px;
            border-radius: var(--radius-element);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-primary);
        }
        .staff-item:hover {
            border-color: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .staff-item.active {
            border-color: var(--maroon);
            background: rgba(127, 3, 3, 0.06);
            box-shadow: var(--shadow-sm);
        }
        .staff-item .staff-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--maroon-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .staff-item .staff-name {
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .staff-item .staff-id {
            font-size: 0.65rem;
            color: var(--text-muted);
        }
        .staff-item .check-mark {
            margin-left: auto;
            color: var(--maroon-light);
            font-size: 0.9rem;
        }

        /* ========== PERMISSIONS CARD ========== */
        .permissions-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }
        .permissions-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .permissions-card .card-header h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .permissions-card .card-header h3 i {
            color: var(--maroon-light);
        }
        .permissions-card .card-header .staff-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--badge-bg);
            padding: 6px 16px;
            border-radius: 50px;
        }
        .permissions-card .card-header .staff-badge .big-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--maroon-light);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .permissions-card .card-header .staff-badge .info .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .permissions-card .card-header .staff-badge .info .email {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .permission-group {
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .permission-group:hover {
            border-color: var(--maroon-light);
        }
        .permission-group .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .permission-group .group-header .group-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .permission-group .group-header .group-name .icon {
            font-size: 1.1rem;
        }
        .permission-group .group-header .status-badge {
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: 50px;
            font-weight: 600;
        }
        .permission-group .group-header .status-badge.assigned {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        .permission-group .group-header .status-badge.unassigned {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }

        .permission-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .perm-btn {
            padding: 4px 14px;
            border-radius: 50px;
            border: none;
            font-size: 0.65rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .perm-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        .perm-btn.read { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .perm-btn.read:hover { background: #2196F3; color: white; }
        .perm-btn.create { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .perm-btn.create:hover { background: #4CAF50; color: white; }
        .perm-btn.update { background: rgba(255, 152, 0, 0.12); color: #FF9800; }
        .perm-btn.update:hover { background: #FF9800; color: white; }
        .perm-btn.delete { background: rgba(244, 67, 54, 0.12); color: #EF5350; }
        .perm-btn.delete:hover { background: #EF5350; color: white; }
        .perm-btn.all { background: rgba(156, 39, 176, 0.12); color: #9C27B0; }
        .perm-btn.all:hover { background: #9C27B0; color: white; }
        .perm-btn.none { background: rgba(96, 125, 139, 0.12); color: #607D8B; }
        .perm-btn.none:hover { background: #607D8B; color: white; }

        .permission-group .perm-badge {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: block;
            margin-top: 6px;
        }

        /* Bulk Actions */
        .bulk-actions {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .btn-bulk {
            padding: 10px 24px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-bulk:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-bulk.grant-all {
            background: var(--maroon);
            color: white;
        }
        .btn-bulk.grant-all:hover {
            background: var(--maroon-light);
        }
        .btn-bulk.reset-all {
            background: rgba(244, 67, 54, 0.12);
            color: #EF5350;
        }
        .btn-bulk.reset-all:hover {
            background: #EF5350;
            color: white;
        }
        .btn-bulk.save {
            background: #4CAF50;
            color: white;
        }
        .btn-bulk.save:hover {
            background: #388E3C;
        }

        .no-staff-selected {
            text-align: center;
            padding: 60px 20px;
        }
        .no-staff-selected i {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .no-staff-selected p {
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .permissions-grid { grid-template-columns: 1fr; }
            .staff-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .staff-selector-card { padding: 16px; }
            .permissions-card { padding: 16px; }
            .staff-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
            .bulk-actions { justify-content: center; }
            .permission-group { padding: 12px 14px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .staff-grid { grid-template-columns: 1fr 1fr; }
            .staff-item { padding: 8px 10px; font-size: 0.75rem; }
            .permission-actions { gap: 4px; }
            .perm-btn { font-size: 0.55rem; padding: 3px 8px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-crown"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Admin Panel</span>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">User Management</li>
            <li><a href="manage-verifications.php"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
            <li><a href="manage-residents.php"><i class="fas fa-user-friends"></i> Residents</a></li>
            <li><a href="manage-staff-services.php" class="active"><i class="fas fa-user-tag"></i> Staff Services</a></li>
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
                <h1><i class="fas fa-user-tag" style="color: var(--maroon-light); margin-right: 10px;"></i> Staff Services</h1>
                <p>Manage service permissions for staff members</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search staff..." id="globalSearch">
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
        
        <!-- Staff Selector -->
        <div class="staff-selector-card">
            <div class="selector-header">
                <h3><i class="fas fa-users"></i> Select Staff Member</h3>
                <span class="count-badge"><?php echo count($staff_list); ?> staff members</span>
            </div>
            <div class="staff-grid" id="staffGrid">
                <?php foreach($staff_list as $staff): 
                    $staff_initials = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));
                    $is_active = $selected_staff_id == $staff['id'];
                ?>
                <a href="?staff_id=<?php echo $staff['id']; ?>" class="staff-item <?php echo $is_active ? 'active' : ''; ?>">
                    <div class="staff-avatar"><?php echo $staff_initials; ?></div>
                    <div>
                        <div class="staff-name"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></div>
                        <div class="staff-id"><?php echo htmlspecialchars($staff['staff_number']); ?></div>
                    </div>
                    <?php if($is_active): ?>
                        <div class="check-mark"><i class="fas fa-check-circle"></i></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Permissions -->
        <?php if($current_staff): 
            $staff_initials = strtoupper(substr($current_staff['first_name'], 0, 1) . substr($current_staff['last_name'], 0, 1));
            $service_icons = [
                'AICS' => '🏥',
                'Housing' => '🏠',
                'Senior Citizen' => '👴',
                'PWD' => '♿'
            ];
            $service_colors = [
                'AICS' => 'var(--color-aics)',
                'Housing' => 'var(--color-housing)',
                'Senior Citizen' => 'var(--color-senior)',
                'PWD' => 'var(--color-pwd)'
            ];
        ?>
        <div class="permissions-card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Service Permissions</h3>
                <div class="staff-badge">
                    <div class="big-avatar"><?php echo $staff_initials; ?></div>
                    <div class="info">
                        <div class="name"><?php echo htmlspecialchars($current_staff['first_name'] . ' ' . $current_staff['last_name']); ?></div>
                        <div class="email"><?php echo htmlspecialchars($current_staff['email']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="permissions-grid">
                <?php foreach($all_services as $service): 
                    $is_assigned = in_array($service, $assigned_names);
                    $icon = $service_icons[$service] ?? '📋';
                    $color = $service_colors[$service] ?? 'var(--text-muted)';
                ?>
                <div class="permission-group">
                    <div class="group-header">
                        <div class="group-name">
                            <span class="icon"><?php echo $icon; ?></span>
                            <?php echo htmlspecialchars($service); ?>
                        </div>
                        <span class="status-badge <?php echo $is_assigned ? 'assigned' : 'unassigned'; ?>">
                            <?php echo $is_assigned ? '✓ Assigned' : '✗ Unassigned'; ?>
                        </span>
                    </div>
                    <div class="permission-actions">
                        <?php if($is_assigned): ?>
                            <span class="perm-badge">All permissions granted</span>
                        <?php else: ?>
                            <span class="perm-badge">No permissions</span>
                        <?php endif; ?>
                    </div>
                    <div class="permission-actions" style="margin-top: 8px;">
                        <?php if($is_assigned): ?>
                            <a href="?remove_service=<?php echo $assigned_services[array_search($service, $assigned_names)]['id'] ?? ''; ?>&staff_id=<?php echo $selected_staff_id; ?>" 
                               class="perm-btn delete" onclick="return confirm('Remove this service from staff member?')">
                                <i class="fas fa-times"></i> Remove
                            </a>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="staff_id" value="<?php echo $selected_staff_id; ?>">
                                <input type="hidden" name="service_to_assign" value="<?php echo $service; ?>">
                                <button type="submit" name="assign_service" class="perm-btn create">
                                    <i class="fas fa-plus"></i> Assign
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bulk-actions">
                <a href="?grant_all=1&staff_id=<?php echo $selected_staff_id; ?>" class="btn-bulk grant-all" onclick="return confirm('Grant all services to this staff member?')">
                    <i class="fas fa-check-double"></i> Grant All
                </a>
                <a href="?reset_all=1&staff_id=<?php echo $selected_staff_id; ?>" class="btn-bulk reset-all" onclick="return confirm('Remove all services from this staff member?')">
                    <i class="fas fa-undo"></i> Reset All
                </a>
                <button class="btn-bulk save" onclick="alert('Changes saved successfully!')">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
        <?php elseif($selected_staff_id > 0 && empty($current_staff)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> Staff member not found.
            </div>
        <?php else: ?>
            <div class="permissions-card">
                <div class="no-staff-selected">
                    <i class="fas fa-user-tag"></i>
                    <p>Select a staff member from above to manage their service permissions</p>
                </div>
            </div>
        <?php endif; ?>
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
    const query = this.value.toLowerCase();
    const items = document.querySelectorAll('.staff-item');
    let visibleCount = 0;
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(query)) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
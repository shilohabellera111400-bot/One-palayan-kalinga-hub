<?php
// admin/manage-staff-services.php - Service Permissions & Staff Access Management
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Enhanced Staff Query with real display names, staff numbers, roles, and assigned counts
$staff_query = "SELECT s.id, s.first_name, s.last_name, s.staff_number, s.employee_id, s.position, s.department, s.profile_image, 
                       u.email, u.profile_picture,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(s.first_name,''), ' ', COALESCE(s.last_name,''))), ''), 
                                NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), 
                                'Staff Member') AS display_name,
                       COALESCE(NULLIF(s.staff_number, ''), NULLIF(s.employee_id, ''), CONCAT('STF-', LPAD(s.id, 4, '0'))) AS display_staff_number,
                       COALESCE(NULLIF(s.position, ''), NULLIF(s.department, ''), 'Staff') AS display_role,
                       COUNT(ssa.id) AS assigned_count
                FROM staff s
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN staff_service_assignments ssa ON ssa.staff_id = s.id AND ssa.is_active = 1
                WHERE u.role = 'staff' AND s.is_active = 1
                GROUP BY s.id
                ORDER BY display_name ASC";
$staff_list = $db->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);

// Select first staff member by default if none specified
$selected_staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : (isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0);
if ($selected_staff_id <= 0 && !empty($staff_list)) {
    $selected_staff_id = intval($staff_list[0]['id']);
}

$current_staff = null;
$assigned_services = [];
$assigned_names = [];

if ($selected_staff_id > 0) {
    // Get staff info
    $staff_info_query = "SELECT s.*, u.email, u.profile_picture AS user_pic,
                                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(s.first_name,''), ' ', COALESCE(s.last_name,''))), ''), 
                                         NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))), ''), 
                                         'Staff Member') AS display_name,
                                COALESCE(NULLIF(s.staff_number, ''), NULLIF(s.employee_id, ''), CONCAT('STF-', LPAD(s.id, 4, '0'))) AS display_staff_number,
                                COALESCE(NULLIF(s.position, ''), NULLIF(s.department, ''), 'Staff') AS display_role
                         FROM staff s 
                         JOIN users u ON s.user_id = u.user_id 
                         WHERE s.id = :id";
    $staff_info_stmt = $db->prepare($staff_info_query);
    $staff_info_stmt->bindParam(':id', $selected_staff_id);
    $staff_info_stmt->execute();
    $current_staff = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get assigned services
    $assigned_query = "SELECT ssa.id, sc.category_id, sc.category_name 
                       FROM staff_service_assignments ssa
                       JOIN service_categories sc ON ssa.category_id = sc.category_id
                       WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
                       AND sc.category_name IN ('AICS', 'Housing', 'Verification')";
    $assigned_stmt = $db->prepare($assigned_query);
    $assigned_stmt->bindParam(':staff_id', $selected_staff_id);
    $assigned_stmt->execute();
    $assigned_services = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
    $assigned_names = array_column($assigned_services, 'category_name');
}

// Service Metadata & Definitions
$service_definitions = [
    'AICS' => [
        'code' => 'AICS',
        'db_name' => 'AICS',
        'title' => 'AICS Assistance',
        'full_name' => 'Assistance to Individuals in Crisis Situation',
        'desc' => 'Medical, burial, educational, transportation, food, and financial cash relief support.',
        'icon' => 'fa-hand-holding-heart',
        'color' => '#8B1A1A',
        'bg_light' => 'rgba(139, 26, 26, 0.08)'
    ],
    'Housing' => [
        'code' => 'HOUSING',
        'db_name' => 'Housing',
        'title' => 'Housing Services',
        'full_name' => 'City Housing Programs & Relocation',
        'desc' => 'Government shelter applications, site inspection, and beneficiary allocation.',
        'icon' => 'fa-home',
        'color' => '#0F414A',
        'bg_light' => 'rgba(15, 65, 74, 0.08)'
    ],
    'Verification' => [
        'code' => 'VERIFICATION',
        'db_name' => 'Verification',
        'title' => 'Residency Verification',
        'full_name' => 'Resident Authentication & Identity Verification',
        'desc' => 'Biometric face verification review, PhilSys & valid ID checking, and resident approval.',
        'icon' => 'fa-user-check',
        'color' => '#059669',
        'bg_light' => 'rgba(5, 150, 105, 0.08)'
    ]
];

// Handle service assignment
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['assign_service'])) {
    $staff_id = intval($_POST['staff_id']);
    $service_to_assign = $_POST['service_to_assign'] ?? '';
    
    if ($staff_id > 0 && !empty($service_to_assign)) {
        $service_mapping = [
            'AICS' => 'AICS',
            'HOUSING' => 'Housing',
            'VERIFICATION' => 'Verification'
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
                    header("Location: manage-staff-services.php?staff_id=" . $staff_id . "&msg=assigned");
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
        header("Location: manage-staff-services.php?staff_id=" . $staff_id . "&msg=removed");
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
        'VERIFICATION' => 'Verification',
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
    
    header("Location: manage-staff-services.php?staff_id=" . $staff_id . "&msg=granted_all");
    exit();
}

// Handle reset all services
if (isset($_GET['reset_all']) && isset($_GET['staff_id'])) {
    $staff_id = intval($_GET['staff_id']);
    
    $delete_query = "DELETE FROM staff_service_assignments WHERE staff_id = :staff_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':staff_id', $staff_id);
    
    if ($delete_stmt->execute()) {
        header("Location: manage-staff-services.php?staff_id=" . $staff_id . "&msg=reset_all");
        exit();
    } else {
        $error = "Failed to reset services.";
    }
}

// Flash messages from redirects
if (isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'assigned': $success = "Service permission assigned successfully!"; break;
        case 'removed': $success = "Service permission removed successfully."; break;
        case 'granted_all': $success = "All service permissions granted successfully!"; break;
        case 'reset_all': $success = "All service permissions have been reset."; break;
    }
}

// Helpers for initials
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        return substr($initials, 0, 2) ?: 'ST';
    }
}

$page_title = 'Staff Services';
$active_sidebar = 'staff-services';
require_once __DIR__ . '/includes/admin-head.php';
?>
<style>
        :root {
            --maroon: #7F0303;
            --maroon-light: #9A1515;
            --maroon-dark: #5A0202;
            --gold: #D8BA98;
            --midnight: #0F414A;
            
            --bg-primary: var(--ref-canvas, #E4EFE7);
            --bg-card: #FFFFFF;
            --bg-card-hover: #F8FAFC;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.12);
            --bg-sidebar-active: rgba(255, 255, 255, 0.18);
            
            --text-primary: #1E293B;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --text-sidebar: rgba(255, 255, 255, 0.75);
            --text-sidebar-active: #FFFFFF;
            
            --border-color: #E2E8F0;
            --border-focus: #CBD5E1;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 4px 14px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            
            --radius-card: 16px;
            --radius-element: 10px;
            --radius-pill: 50px;
            
            --status-assigned-bg: #ECFDF5;
            --status-assigned-text: #059669;
            --status-assigned-border: #A7F3D0;
            
            --status-unassigned-bg: #FEF2F2;
            --status-unassigned-text: #DC2626;
            --status-unassigned-border: #FECACA;
        }

        [data-theme="dark"] {
            --bg-primary: var(--ref-canvas, #0C1417);
            --bg-card: #141B28;
            --bg-card-hover: #1A2334;
            --bg-sidebar: #0F1626;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.08);
            --bg-sidebar-active: rgba(255, 255, 255, 0.14);
            
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-muted: #94A3B8;
            --text-sidebar: rgba(255, 255, 255, 0.65);
            --text-sidebar-active: #FFFFFF;
            
            --border-color: #232E42;
            --border-focus: #334155;
            
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.35);
            --shadow-md: 0 6px 20px rgba(0, 0, 0, 0.45);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.55);
            
            --status-assigned-bg: rgba(16, 185, 129, 0.14);
            --status-assigned-text: #34D399;
            --status-assigned-border: rgba(16, 185, 129, 0.3);
            
            --status-unassigned-bg: rgba(239, 68, 68, 0.14);
            --status-unassigned-text: #F87171;
            --status-unassigned-border: rgba(239, 68, 68, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title h1 {
            font-size: 1.45rem;
            color: var(--text-primary);
            font-weight: 800;
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.84rem;
            margin-top: 2px;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-staff-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 6px 14px 6px 8px;
            border-radius: 50px;
            box-shadow: var(--shadow-sm);
        }
        .header-staff-chip .chip-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #FEE2E2;
            color: var(--maroon);
            font-size: 0.72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-staff-chip .chip-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .header-staff-chip .chip-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .header-staff-chip .chip-sub {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .theme-toggle, .top-icon {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .theme-toggle:hover, .top-icon:hover {
            color: var(--maroon);
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.86rem;
            box-shadow: var(--shadow-sm);
            animation: fadeInDown 0.3s ease;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* ========== TWO-COLUMN MANAGEMENT LAYOUT ========== */
        .management-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ========== LEFT PANEL: STAFF LIST ========== */
        .staff-panel-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .staff-panel-header {
            padding: 20px 20px 14px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .staff-panel-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .staff-panel-title-row h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .staff-count-pill {
            background: var(--bg-primary);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }

        .staff-search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .staff-search-icon {
            position: absolute;
            left: 12px;
            font-size: 0.85rem;
            color: var(--text-muted);
            pointer-events: none;
        }
        .staff-search-input {
            width: 100%;
            padding: 9px 36px 9px 34px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.82rem;
            outline: none;
            transition: all 0.2s ease;
        }
        .staff-search-input:focus {
            background: var(--bg-card);
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08);
        }
        .staff-search-clear {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.8rem;
            display: none;
        }

        .staff-list-container {
            padding: 12px;
            max-height: calc(100vh - 290px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .staff-list-container::-webkit-scrollbar { width: 4px; }
        .staff-list-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

        .staff-row {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            text-decoration: none;
            color: var(--text-secondary);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .staff-row:hover {
            border-color: #CBD5E1;
            background: var(--bg-card-hover);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        }
        
        /* Selected State - Subtle Red Border & Tint */
        .staff-row.active {
            border: 1.5px solid rgba(127, 3, 3, 0.38);
            background: rgba(127, 3, 3, 0.04);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.06);
        }
        [data-theme="dark"] .staff-row.active {
            border-color: rgba(220, 38, 38, 0.45);
            background: rgba(127, 3, 3, 0.16);
        }

        .staff-avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #F1F5F9;
            color: #475569;
            font-weight: 700;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .staff-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-row.active .staff-avatar-circle {
            background: #FEE2E2;
            color: var(--maroon);
            border-color: rgba(127, 3, 3, 0.25);
        }

        .staff-row-info {
            flex: 1;
            min-width: 0;
            margin-left: 11px;
            margin-right: 8px;
        }
        .staff-row-name {
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .staff-row.active .staff-row-name {
            color: var(--maroon);
        }
        [data-theme="dark"] .staff-row.active .staff-row-name {
            color: #F87171;
        }

        .staff-row-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }

        .staff-row-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        /* Role/Badge Pill - Inspired by Reference */
        .staff-role-pill {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 50px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
        }
        .staff-role-pill.admin { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .staff-role-pill.social { background: #ECFDF5; color: #047857; border-color: #A7F3D0; }
        .staff-role-pill.housing { background: #F0FDF4; color: #15803D; border-color: #BBF7D0; }
        .staff-role-pill.purple { background: #FAF5FF; color: #7E22CE; border-color: #E9D5FF; }

        .staff-selected-icon {
            color: var(--maroon);
            font-size: 0.85rem;
        }
        [data-theme="dark"] .staff-selected-icon {
            color: #F87171;
        }

        .staff-chevron-icon {
            color: var(--text-muted);
            font-size: 0.72rem;
            transition: transform 0.2s ease;
        }
        .staff-row:hover .staff-chevron-icon {
            transform: translateX(2px);
            color: var(--text-primary);
        }

        .staff-empty-message {
            text-align: center;
            padding: 30px 16px;
            color: var(--text-muted);
            font-size: 0.82rem;
            display: none;
        }
        .staff-empty-message i {
            font-size: 1.8rem;
            opacity: 0.3;
            margin-bottom: 8px;
            display: block;
        }

        /* ========== RIGHT PANEL: SERVICE PERMISSIONS ========== */
        .permissions-panel-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 28px 32px;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .panel-heading-row {
            margin-bottom: 20px;
        }
        .panel-heading-row h2 {
            font-size: 1.22rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .panel-heading-row p {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Selected Staff Profile Card */
        .selected-staff-profile-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .profile-card-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .profile-card-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #FEE2E2;
            color: var(--maroon);
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            flex-shrink: 0;
        }
        .profile-card-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-card-info h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .profile-meta-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 4px;
            flex-wrap: wrap;
            font-size: 0.76rem;
            color: var(--text-muted);
        }
        .profile-meta-row a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .profile-meta-row a:hover {
            color: var(--maroon);
        }

        .profile-stats-chip {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .assigned-summary-pill {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .assigned-summary-pill i {
            color: #059669;
        }

        /* Permission Cards Stack - Inspired by Reference */
        .service-cards-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .service-perm-card {
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 20px;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            transition: all 0.25s ease;
        }
        .service-perm-card:hover {
            border-color: #CBD5E1;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
        }
        
        /* Subtle green tint when assigned */
        .service-perm-card.assigned {
            border-color: rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.02);
        }
        [data-theme="dark"] .service-perm-card.assigned {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.25);
        }

        .service-perm-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }
        .service-perm-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        
        .service-perm-info {
            flex: 1;
            min-width: 0;
        }
        .service-perm-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .service-perm-title {
            font-size: 0.94rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .service-perm-title small {
            font-size: 0.74rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-left: 4px;
        }

        .service-status-pill {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .service-status-pill.status-assigned {
            background: var(--status-assigned-bg);
            color: var(--status-assigned-text);
            border: 1px solid var(--status-assigned-border);
        }
        .service-status-pill.status-unassigned {
            background: var(--status-unassigned-bg);
            color: var(--status-unassigned-text);
            border: 1px solid var(--status-unassigned-border);
        }

        .service-perm-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.35;
        }

        /* Permission Action Buttons */
        .service-perm-action {
            flex-shrink: 0;
        }
        .btn-perm-action {
            padding: 8px 18px;
            border-radius: var(--radius-element);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: 1px solid transparent;
        }
        
        .btn-perm-assign {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }
        .btn-perm-assign:hover {
            background: var(--maroon-light);
            box-shadow: 0 3px 10px rgba(127, 3, 3, 0.25);
            transform: translateY(-1px);
        }

        .btn-perm-remove {
            background: #FEF2F2;
            color: #DC2626;
            border-color: #FECACA;
        }
        .btn-perm-remove:hover {
            background: #DC2626;
            color: white;
            border-color: #DC2626;
            box-shadow: 0 3px 10px rgba(220, 38, 38, 0.2);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .btn-perm-remove {
            background: rgba(220, 38, 38, 0.12);
            color: #F87171;
            border-color: rgba(220, 38, 38, 0.3);
        }
        [data-theme="dark"] .btn-perm-remove:hover {
            background: #DC2626;
            color: white;
        }

        /* Bulk Actions Toolbar */
        .bulk-actions-toolbar {
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .toolbar-info {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .toolbar-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-toolbar {
            padding: 9px 20px;
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-grant-all {
            background: var(--maroon);
            color: white;
        }
        .btn-grant-all:hover {
            background: var(--maroon-light);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
            transform: translateY(-1px);
        }
        
        .btn-reset-all {
            background: transparent;
            color: #DC2626;
            border-color: #FECACA;
        }
        .btn-reset-all:hover {
            background: #FEF2F2;
            border-color: #DC2626;
            transform: translateY(-1px);
        }
        [data-theme="dark"] .btn-reset-all {
            border-color: rgba(220, 38, 38, 0.3);
            color: #F87171;
        }
        [data-theme="dark"] .btn-reset-all:hover {
            background: rgba(220, 38, 38, 0.15);
        }

        .btn-save-changes {
            background: #059669;
            color: white;
            border-color: #059669;
        }
        .btn-save-changes:hover {
            background: #047857;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            transform: translateY(-1px);
        }

        /* Empty / No Staff Selected State */
        .empty-selection-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-muted);
        }
        .empty-selection-state i {
            font-size: 3.5rem;
            opacity: 0.25;
            color: var(--maroon);
            margin-bottom: 16px;
            display: block;
        }
        .empty-selection-state h3 {
            font-size: 1.1rem;
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 6px;
        }
        .empty-selection-state p {
            font-size: 0.85rem;
            max-width: 380px;
            margin: 0 auto;
        }

        /* Popover Toast Notification */
        .action-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1E293B;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            z-index: 9999;
            border-left: 4px solid #059669;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .action-toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1100px) {
            .management-layout {
                grid-template-columns: 320px 1fr;
                gap: 18px;
            }
            .permissions-panel-card {
                padding: 22px 24px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px 18px;
            }
            .management-layout {
                grid-template-columns: 1fr;
            }
            .staff-list-container {
                max-height: 280px;
            }
        }

        @media (max-width: 640px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .top-bar-actions {
                justify-content: space-between;
            }
            .service-perm-card {
                flex-direction: column;
                align-items: stretch;
            }
            .service-perm-action {
                text-align: right;
                margin-top: 8px;
            }
            .service-perm-action .btn-perm-action {
                width: 100%;
                justify-content: center;
            }
            .bulk-actions-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar-buttons {
                flex-direction: column;
            }
            .btn-toolbar {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-user-tag"></i> Manage Staff Services</h1>
        <p>Assign department services and module permissions to city staff officers</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <?php if($current_staff): 
            $curr_initials = getInitials($current_staff['display_name']);
        ?>
        <div class="header-staff-chip" title="Currently editing permissions for <?php echo htmlspecialchars($current_staff['display_name']); ?>" style="display:flex;align-items:center;gap:10px;background:var(--dash-card-bg);padding:6px 14px;border-radius:50px;border:1px solid var(--dash-border);">
            <div class="chip-avatar" style="width:34px;height:34px;border-radius:50%;background:rgba(127,3,3,0.1);color:var(--dash-maroon);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.75rem;overflow:hidden;">
                <?php 
                $chip_pic = !empty($current_staff['profile_image']) ? $current_staff['profile_image'] : (!empty($current_staff['user_pic']) ? $current_staff['user_pic'] : '');
                if (!empty($chip_pic) && file_exists('../' . $chip_pic)): ?>
                    <img src="../<?php echo htmlspecialchars($chip_pic); ?>" alt="Avatar" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <?php echo $curr_initials; ?>
                <?php endif; ?>
            </div>
            <div class="chip-info" style="line-height:1.2;">
                <div style="font-weight:700;font-size:0.82rem;color:var(--dash-text-heading);"><?php echo htmlspecialchars($current_staff['display_name']); ?></div>
                <div style="font-size:0.72rem;color:var(--dash-text-muted);"><?php echo htmlspecialchars($current_staff['display_staff_number']); ?> • <?php echo count($assigned_services); ?>/4 Services</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

        <!-- Flash Feedback Alerts -->
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

        <!-- Two-Column Management Layout -->
        <div class="management-layout">
            
            <!-- LEFT PANEL: Staff Selection List -->
            <div class="staff-panel-card">
                <div class="staff-panel-header">
                    <div class="staff-panel-title-row">
                        <h3><i class="fas fa-users" style="color: var(--maroon);"></i> Staff Members</h3>
                        <span class="staff-count-pill" id="staffCountBadge"><?php echo count($staff_list); ?> Total</span>
                    </div>
                    <div class="staff-search-wrapper">
                        <i class="fas fa-search staff-search-icon"></i>
                        <input type="text" id="staffSearchInput" class="staff-search-input" placeholder="Search staff by name, ID, or role..." autocomplete="off">
                        <button type="button" id="clearSearchBtn" class="staff-search-clear"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <div class="staff-list-container" id="staffListContainer">
                    <?php foreach($staff_list as $staff): 
                        $s_initials = getInitials($staff['display_name']);
                        $is_active = ($selected_staff_id == $staff['id']);
                        $avatar_file = !empty($staff['profile_image']) ? $staff['profile_image'] : (!empty($staff['profile_picture']) ? $staff['profile_picture'] : '');
                        
                        // Badge color styling based on role
                        $role_lower = strtolower($staff['display_role']);
                        $role_badge_class = 'staff-role-pill';
                        if (strpos($role_lower, 'admin') !== false) {
                            $role_badge_class .= ' admin';
                        } elseif (strpos($role_lower, 'social') !== false || strpos($role_lower, 'worker') !== false) {
                            $role_badge_class .= ' social';
                        } elseif (strpos($role_lower, 'housing') !== false) {
                            $role_badge_class .= ' housing';
                        } else {
                            $role_badge_class .= ' purple';
                        }
                    ?>
                    <a href="?staff_id=<?php echo $staff['id']; ?>" 
                       class="staff-row <?php echo $is_active ? 'active' : ''; ?>"
                       data-name="<?php echo strtolower(htmlspecialchars($staff['display_name'])); ?>"
                       data-number="<?php echo strtolower(htmlspecialchars($staff['display_staff_number'])); ?>"
                       data-role="<?php echo strtolower(htmlspecialchars($staff['display_role'])); ?>"
                       data-email="<?php echo strtolower(htmlspecialchars($staff['email'])); ?>">
                        
                        <div class="staff-avatar-circle">
                            <?php if (!empty($avatar_file) && file_exists('../' . $avatar_file)): ?>
                                <img src="../<?php echo htmlspecialchars($avatar_file); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo $s_initials; ?>
                            <?php endif; ?>
                        </div>

                        <div class="staff-row-info">
                            <div class="staff-row-name">
                                <span><?php echo htmlspecialchars($staff['display_name']); ?></span>
                                <?php if($is_active): ?>
                                    <i class="fas fa-check-circle staff-selected-icon" title="Currently Selected"></i>
                                <?php endif; ?>
                            </div>
                            <div class="staff-row-sub">
                                <?php echo htmlspecialchars($staff['display_staff_number']); ?>
                            </div>
                        </div>

                        <div class="staff-row-actions">
                            <span class="<?php echo $role_badge_class; ?>" title="<?php echo htmlspecialchars($staff['display_role']); ?>">
                                <?php 
                                    // Shorten if overly long for clean layout
                                    $short_role = strlen($staff['display_role']) > 15 ? substr($staff['display_role'], 0, 13) . '..' : $staff['display_role'];
                                    echo htmlspecialchars($short_role);
                                ?>
                            </span>
                            <i class="fas fa-chevron-right staff-chevron-icon"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>

                    <div class="staff-empty-message" id="staffEmptyMessage">
                        <i class="fas fa-user-slash"></i>
                        No staff members matching your search
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Detailed Service Permission Controls -->
            <div class="permissions-panel-card">
                <?php if($current_staff): 
                    $curr_initials = getInitials($current_staff['display_name']);
                    $assigned_count = count($assigned_services);
                    $avatar_file = !empty($current_staff['profile_image']) ? $current_staff['profile_image'] : (!empty($current_staff['user_pic']) ? $current_staff['user_pic'] : '');
                ?>
                    <div class="panel-heading-row">
                        <h2><i class="fas fa-lock" style="color: var(--maroon);"></i> Service Permissions</h2>
                        <p>Determine which welfare and assistance programs this staff member is authorized to manage, review, and process.</p>
                    </div>

                    <!-- Selected Staff Profile Banner -->
                    <div class="selected-staff-profile-card">
                        <div class="profile-card-left">
                            <div class="profile-card-avatar">
                                <?php if (!empty($avatar_file) && file_exists('../' . $avatar_file)): ?>
                                    <img src="../<?php echo htmlspecialchars($avatar_file); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo $curr_initials; ?>
                                <?php endif; ?>
                            </div>
                            <div class="profile-card-info">
                                <h3>
                                    <?php echo htmlspecialchars($current_staff['display_name']); ?>
                                    <span class="staff-role-pill admin" style="font-size:0.65rem;">
                                        <?php echo htmlspecialchars($current_staff['display_role']); ?>
                                    </span>
                                </h3>
                                <div class="profile-meta-row">
                                    <span><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($current_staff['display_staff_number']); ?></span>
                                    <span><i class="fas fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($current_staff['email']); ?>"><?php echo htmlspecialchars($current_staff['email']); ?></a></span>
                                    <span><i class="fas fa-check-circle" style="color:#059669;"></i> Active Staff</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-stats-chip">
                            <div class="assigned-summary-pill">
                                <i class="fas fa-shield-alt"></i>
                                <span><?php echo $assigned_count; ?> of <?php echo count($service_definitions); ?> Services Authorized</span>
                            </div>
                        </div>
                    </div>

                    <!-- Compact Permission Cards Stack (AICS, Housing, Senior Citizen, PWD) -->
                    <div class="service-cards-stack">
                        <?php foreach($service_definitions as $key => $srv): 
                            $is_assigned = in_array($srv['db_name'], $assigned_names);
                            
                            // Find assignment ID for deletion
                            $assignment_id = 0;
                            if ($is_assigned) {
                                foreach($assigned_services as $asgn) {
                                    if ($asgn['category_name'] === $srv['db_name']) {
                                        $assignment_id = $asgn['id'];
                                        break;
                                    }
                                }
                            }
                        ?>
                        <div class="service-perm-card <?php echo $is_assigned ? 'assigned' : 'unassigned'; ?>">
                            <div class="service-perm-left">
                                <div class="service-perm-icon" style="background: <?php echo $srv['bg_light']; ?>; color: <?php echo $srv['color']; ?>;">
                                    <i class="fas <?php echo $srv['icon']; ?>"></i>
                                </div>
                                <div class="service-perm-info">
                                    <div class="service-perm-title-row">
                                        <h4 class="service-perm-title">
                                            <?php echo htmlspecialchars($srv['title']); ?>
                                            <small>(<?php echo htmlspecialchars($srv['full_name']); ?>)</small>
                                        </h4>
                                        <span class="service-status-pill <?php echo $is_assigned ? 'status-assigned' : 'status-unassigned'; ?>">
                                            <?php if($is_assigned): ?>
                                                <i class="fas fa-check-circle"></i> Assigned
                                            <?php else: ?>
                                                <i class="far fa-circle"></i> Unassigned
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <p class="service-perm-desc">
                                        <?php if($is_assigned): ?>
                                            <strong>All permissions granted.</strong> Authorized to review documents, approve applications, and manage service queues.
                                        <?php else: ?>
                                            <strong>No permissions.</strong> Staff member cannot view, process, or alter applications for this service.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="service-perm-action">
                                <?php if($is_assigned): ?>
                                    <a href="?remove_service=<?php echo $assignment_id; ?>&staff_id=<?php echo $selected_staff_id; ?>&id=<?php echo $assignment_id; ?>" 
                                       class="btn-perm-action btn-perm-remove" 
                                       onclick="return confirm('Remove <?php echo addslashes($srv['title']); ?> permission from <?php echo addslashes($current_staff['display_name']); ?>?');">
                                        <i class="fas fa-times"></i> Remove
                                    </a>
                                <?php else: ?>
                                    <form method="POST" style="display:inline; margin:0;">
                                        <input type="hidden" name="staff_id" value="<?php echo $selected_staff_id; ?>">
                                        <input type="hidden" name="service_to_assign" value="<?php echo $srv['code']; ?>">
                                        <button type="submit" name="assign_service" class="btn-perm-action btn-perm-assign">
                                            <i class="fas fa-plus"></i> Assign
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bottom Bulk Actions Toolbar -->
                    <div class="bulk-actions-toolbar">
                        <div class="toolbar-info">
                            <i class="fas fa-info-circle"></i> Changes to service permissions take effect immediately.
                        </div>
                        <div class="toolbar-buttons">
                            <a href="?grant_all=1&staff_id=<?php echo $selected_staff_id; ?>" 
                               class="btn-toolbar btn-grant-all" 
                               onclick="return confirm('Grant all 4 services to <?php echo addslashes($current_staff['display_name']); ?>?');">
                                <i class="fas fa-check-double"></i> Grant All
                            </a>
                            <a href="?reset_all=1&staff_id=<?php echo $selected_staff_id; ?>" 
                               class="btn-toolbar btn-reset-all" 
                               onclick="return confirm('Reset and remove all services from <?php echo addslashes($current_staff['display_name']); ?>?');">
                                <i class="fas fa-undo"></i> Reset All
                            </a>
                            <button type="button" class="btn-toolbar btn-save-changes" id="saveFeedbackBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>

                <?php elseif($selected_staff_id > 0 && empty($current_staff)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> Selected staff member could not be found.
                    </div>
                <?php else: ?>
                    <div class="empty-selection-state">
                        <i class="fas fa-user-tag"></i>
                        <h3>Select a Staff Member</h3>
                        <p>Choose a staff member from the list on the left to configure their service authorizations and permissions.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

<!-- Floating Toast Feedback -->
<div id="actionToast" class="action-toast">
    <i class="fas fa-check-circle" style="color: #34D399; font-size: 1.1rem;"></i>
    <span id="toastMessage">Service permissions synchronized successfully.</span>
</div>

<script>
// ========== LIVE STAFF SEARCH FILTERING ==========
const searchInput = document.getElementById('staffSearchInput');
const clearSearchBtn = document.getElementById('clearSearchBtn');
const staffRows = document.querySelectorAll('.staff-row');
const countBadge = document.getElementById('staffCountBadge');
const emptyMessage = document.getElementById('staffEmptyMessage');
const totalStaffCount = staffRows.length;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        let visibleCount = 0;

        if (clearSearchBtn) {
            clearSearchBtn.style.display = query.length > 0 ? 'block' : 'none';
        }

        staffRows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const number = row.getAttribute('data-number') || '';
            const role = row.getAttribute('data-role') || '';
            const email = row.getAttribute('data-email') || '';

            if (!query || name.includes(query) || number.includes(query) || role.includes(query) || email.includes(query)) {
                row.style.display = 'flex';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (countBadge) {
            countBadge.textContent = query ? `${visibleCount} of ${totalStaffCount}` : `${totalStaffCount} Total`;
        }

        if (emptyMessage) {
            emptyMessage.style.display = (visibleCount === 0 && query.length > 0) ? 'block' : 'none';
        }
    });

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
    }
}

// ========== TOAST FEEDBACK ==========
function showToast(message) {
    if (window.showProcessToast) {
        window.showProcessToast(message, false, 'Process Completed');
        return;
    }

    const toast = document.getElementById('actionToast');
    const toastMsg = document.getElementById('toastMessage');
    if (!toast) return;

    if (toastMsg && message) toastMsg.textContent = message;
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

const saveFeedbackBtn = document.getElementById('saveFeedbackBtn');
if (saveFeedbackBtn) {
    saveFeedbackBtn.addEventListener('click', function() {
        showToast('All service permissions are saved and synchronized.');
    });
}
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
<?php
// staff/manage-aics.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for access control
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories for this staff
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if staff has AICS access
$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);

// If staff doesn't have AICS access, redirect
if (!$has_aics_access) {
    $_SESSION['error'] = "You don't have permission to access AICS Management.";
    redirect('../staff-dashboard.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$base_query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture 
               FROM aics_requests a
               JOIN users u ON a.user_id = u.user_id";
$count_query = "SELECT COUNT(*) as total 
                FROM aics_requests a
                JOIN users u ON a.user_id = u.user_id";

$where = [];
$params = [];

if ($status_filter != 'all') {
    $where[] = "a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR a.purpose LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($where)) {
    $base_query .= " WHERE " . implode(" AND ", $where);
    $count_query .= " WHERE " . implode(" AND ", $where);
}

// Get total count for pagination
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get records with pagination
$query = $base_query . " ORDER BY a.submission_date DESC LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();

// Get all records for statistics (without pagination)
$all_stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
    FROM aics_requests";
$stats = $db->query($all_stats_query)->fetch(PDO::FETCH_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $aics_id = sanitize($_POST['aics_id']);
    $new_status = sanitize($_POST['new_status']);
    $amount_approved = sanitize($_POST['amount_approved'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $db->beginTransaction();
    
    try {
        $update_query = "UPDATE aics_requests SET 
                          status = :status,
                          amount_approved = :amount";
        
        if ($new_status == 'reviewing') {
            $update_query .= ", reviewed_by = :user_id, review_date = NOW()";
        } elseif ($new_status == 'approved') {
            $update_query .= ", approved_by = :user_id, approval_date = NOW()";
        } elseif ($new_status == 'released') {
            $update_query .= ", release_date = NOW()";
        } elseif ($new_status == 'rejected') {
            $update_query .= ", rejection_reason = :remarks";
        }
        
        $update_query .= " WHERE aics_id = :aics_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':amount', $amount_approved);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($new_status == 'rejected') {
            $update_stmt->bindParam(':remarks', $remarks);
        }
        
        $update_stmt->bindParam(':aics_id', $aics_id);
        $update_stmt->execute();
        
        // Get user details for notification
        $user_query = "SELECT u.email, u.first_name, u.last_name, a.* 
                       FROM aics_requests a
                       JOIN users u ON a.user_id = u.user_id
                       WHERE a.aics_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $aics_id);
        $user_stmt->execute();
        $aics_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'AICS Status Updated', 
                        CONCAT('Your AICS request status has been updated to: ', :status), 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $aics_details['user_id']);
        $notif_stmt->bindParam(':status', $new_status);
        $notif_stmt->execute();
        
        // Send email
        try {
            $mailer = new Mailer();
            $user_name = $aics_details['first_name'] . ' ' . $aics_details['last_name'];
            
            if ($new_status == 'approved') {
                $aics_details['amount_approved'] = $amount_approved;
            }
            if ($new_status == 'rejected') {
                $aics_details['rejection_reason'] = $remarks;
            }
            
            $email_result = $mailer->sendAICSUpdate(
                $aics_details['email'],
                $user_name,
                $aics_details
            );
            
            $email_status = $email_result['success'] ? "Email notification sent." : "Email failed: " . $email_result['message'];
            error_log($email_status);
        } catch (Exception $e) {
            $email_status = "Email error: " . $e->getMessage();
            error_log($email_status);
        }
        
        $db->commit();
        $success = "Status updated successfully! " . ($email_status ?? '');
        
        // Redirect to refresh with current page
        $redirect_url = "manage-aics.php?status=" . $status_filter . "&page=" . $page;
        if (!empty($search)) {
            $redirect_url .= "&search=" . urlencode($search);
        }
        header("Location: " . $redirect_url);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Get staff info for display
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage AICS - Staff Dashboard</title>
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
            
            --stat-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
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
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
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

        .staff-profile {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--divider);
            text-align: center;
        }
        .staff-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            background: var(--maroon-light);
            border: 3px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }
        .staff-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .staff-profile h4 {
            color: var(--text-sidebar-active);
            font-size: 1rem;
            font-weight: 600;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
            margin-top: 2px;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            margin-top: 6px;
            color: rgba(255,255,255,0.8);
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
        .menu-divider {
            border-top: 1px solid var(--divider);
            margin: 10px 0;
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
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--stat-bg);
            border-radius: var(--radius-card);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
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
            border-radius: var(--radius-card);
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
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-icon.submitted { background: rgba(255, 152, 0, 0.12); color: #FF9800; }
        .stat-icon.reviewing { background: rgba(33, 150, 243, 0.12); color: #2196F3; }
        .stat-icon.approved { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-icon.rejected { background: rgba(244, 67, 54, 0.12); color: #EF5350; }
        .stat-info {
            position: relative;
            z-index: 1;
        }
        .stat-info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .stat-info p {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        /* ========== FILTER BAR ========== */
        .filter-bar {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 18px 22px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
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
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-secondary {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        .applicant-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .applicant-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .applicant-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .applicant-avatar .initials {
            font-weight: 700;
            font-size: 0.65rem;
            color: var(--text-primary);
        }
        .applicant-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .applicant-contact {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.submitted {
            background: rgba(255, 152, 0, 0.15);
            color: #FF9800;
        }
        .status-badge.reviewing {
            background: rgba(33, 150, 243, 0.15);
            color: #2196F3;
        }
        .status-badge.approved {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: #EF5350;
        }
        .status-badge.released {
            background: rgba(156, 39, 176, 0.15);
            color: #9C27B0;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-update {
            background: rgba(255, 152, 0, 0.12);
            color: #FF9800;
        }
        .btn-update:hover {
            background: #FF9800;
            color: white;
        }
        .btn-view {
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon-light);
        }
        .btn-view:hover {
            background: var(--maroon);
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
            background: var(--card-bg);
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(4px);
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            width: 90%;
            max-width: 500px;
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
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--badge-bg);
            border-radius: var(--radius-card) var(--radius-card) 0 0;
        }
        .modal-header h2 {
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h2 i {
            color: var(--maroon-light);
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            line-height: 1;
        }
        .close-btn:hover {
            color: var(--maroon);
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-body .applicant-info {
            padding: 10px 14px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            margin-bottom: 16px;
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
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--badge-bg);
            border-radius: 0 0 var(--radius-card) var(--radius-card);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-actions { flex-direction: column; width: 100%; }
            .filter-actions button, .filter-actions a { width: 100%; justify-content: center; }
            .data-table { display: block; overflow-x: auto; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.75rem; }
            .applicant-cell { flex-direction: column; align-items: flex-start; }
            .action-buttons { flex-direction: column; align-items: center; }
            .action-buttons .btn-small { width: 100%; justify-content: center; }
            .modal-content { width: 95%; }
            .pagination .page-info { width: 100%; text-align: center; margin-right: 0; margin-bottom: 8px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 14px; }
            .stat-info h3 { font-size: 1.1rem; }
            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="logo-text">
                <h2>One Palayan-Kalinga Hub</h2>
                <span>Staff Panel</span>
            </div>
        </div>
        
        <div class="staff-profile">
            <div class="staff-avatar">
                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
            <p><?php echo htmlspecialchars($staff_info['position'] ?? 'Staff Member'); ?></p>
            <span class="staff-badge"><i class="fas fa-user-check"></i> Staff</span>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="menu-divider">Appointments</li>
            <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
            <li class="menu-divider">Services</li>
            <?php if($has_aics_access): ?>
            <li><a href="manage-aics.php" class="active"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
            <?php endif; ?>
            <?php if($has_housing_access): ?>
            <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
            <?php endif; ?>
            <?php if($has_senior_access): ?>
            <li><a href="manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
            <?php endif; ?>
            <?php if($has_pwd_access): ?>
            <li><a href="manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
            <?php endif; ?>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="menu-divider"></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-hand-holding-heart" style="color: var(--maroon-light); margin-right: 10px;"></i> Manage AICS</h1>
                <p>Review and manage Assistance to Individuals in Crisis Situation requests</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="reports.php" class="top-icon"><i class="fas fa-chart-bar"></i></a>
                <a href="../logout.php" class="top-icon"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-aics.php?status=all'">
                <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-aics.php?status=submitted'">
                <div class="stat-icon submitted"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['submitted']; ?></h3>
                    <p>Submitted</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-aics.php?status=reviewing'">
                <div class="stat-icon reviewing"><i class="fas fa-search"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['reviewing']; ?></h3>
                    <p>Reviewing</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-aics.php?status=approved'">
                <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-aics.php?status=rejected'">
                <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status" id="statusFilter">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="reviewing" <?php echo $status_filter == 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Name or purpose..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <?php if($status_filter != 'all' || !empty($search)): ?>
                    <a href="manage-aics.php" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $app_initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                            $app_pic = !empty($row['profile_picture']) && file_exists(__DIR__ . '/../' . $row['profile_picture']) 
                                ? '../' . $row['profile_picture'] 
                                : '';
                        ?>
                        <tr>
                            <td><strong>AICS-<?php echo str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div class="applicant-cell">
                                    <div class="applicant-avatar">
                                        <?php if(!empty($app_pic)): ?>
                                            <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Profile">
                                        <?php else: ?>
                                            <span class="initials"><?php echo $app_initials; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="applicant-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                        <div class="applicant-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['contact_number']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $row['request_type'])); ?></td>
                            <td>₱<?php echo number_format($row['amount_requested'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['submission_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $row['status'] == 'submitted' ? 'clock' : 
                                            ($row['status'] == 'reviewing' ? 'search' : 
                                            ($row['status'] == 'approved' ? 'check-circle' : 
                                            ($row['status'] == 'released' ? 'gift' : 'times-circle'))); 
                                    ?>"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-buttons" style="justify-content: center;">
                                    <button onclick='showUpdateModal(<?php echo json_encode($row); ?>)' class="btn-small btn-update">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                    <a href="view-aics.php?id=<?php echo $row['aics_id']; ?>" class="btn-small btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No AICS requests found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?></span>
                
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter != 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1' . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a href="?page=' . $i . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="' . $active . '">' . $i . '</a>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter != 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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

<!-- Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Request</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="aics_id" id="modal_aics_id">
                <div class="applicant-info">
                    <strong><i class="fas fa-user"></i> Applicant:</strong>
                    <span id="modal_applicant"></span>
                </div>
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleFields()">
                        <option value="reviewing">📋 Under Review</option>
                        <option value="approved">✅ Approve</option>
                        <option value="rejected">❌ Reject</option>
                        <option value="released">💰 Release Payment</option>
                    </select>
                </div>
                <div class="form-group" id="amount_field" style="display: none;">
                    <label>Approved Amount (₱)</label>
                    <input type="number" name="amount_approved" id="amount_approved" min="0" step="100" placeholder="Enter approved amount">
                </div>
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label>Rejection Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Please provide a reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary">Update</button>
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

// ========== MODAL FUNCTIONS ==========
function showUpdateModal(request) {
    document.getElementById('modal_aics_id').value = request.aics_id;
    document.getElementById('modal_applicant').textContent = request.first_name + ' ' + request.last_name;
    document.getElementById('updateModal').classList.add('active');
}

function closeModal() {
    document.getElementById('updateModal').classList.remove('active');
    document.getElementById('amount_field').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('amount_approved').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'reviewing';
}

function toggleFields() {
    const status = document.getElementById('modal_status').value;
    document.getElementById('amount_field').style.display = status === 'approved' ? 'block' : 'none';
    document.getElementById('remarks_field').style.display = status === 'rejected' ? 'block' : 'none';
}

function filterByStatus(status) {
    document.getElementById('statusFilter').value = status;
    document.getElementById('filterForm').submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('updateModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
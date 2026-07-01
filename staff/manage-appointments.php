<?php
// staff/manage-appointments.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isStaff() && !isAdmin()) {
    redirect('../dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get staff's ID from user_id
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = sanitize($_POST['status']);
    
    $update_query = "UPDATE appointments SET status = :status WHERE appointment_id = :appointment_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':appointment_id', $appointment_id);
    
    if ($update_stmt->execute()) {
        $success = "Appointment status updated successfully!";
    } else {
        $error = "Failed to update appointment status.";
    }
}

// Handle appointment deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    
    $delete_query = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':appointment_id', $appointment_id);
    
    if ($delete_stmt->execute()) {
        $success = "Appointment deleted successfully!";
    } else {
        $error = "Failed to delete appointment.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the base query with filters
$base_query = "SELECT a.*, u.first_name, u.last_name, u.email, u.contact_number 
               FROM appointments a
               JOIN users u ON a.user_id = u.user_id
               WHERE 1=1";
$count_query = "SELECT COUNT(*) as total 
                FROM appointments a
                JOIN users u ON a.user_id = u.user_id
                WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $base_query .= " AND a.status = :status";
    $count_query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_filter)) {
    $base_query .= " AND a.appointment_date = :date";
    $count_query .= " AND a.appointment_date = :date";
    $params[':date'] = $date_filter;
}

// Get total count for pagination
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get appointments with pagination
$query = $base_query . " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$filtered_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all appointments for statistics (without pagination)
$all_appointments_query = "SELECT a.* FROM appointments a WHERE 1=1";
$all_params = [];
if ($status_filter != 'all') {
    $all_appointments_query .= " AND a.status = :status";
    $all_params[':status'] = $status_filter;
}
if (!empty($date_filter)) {
    $all_appointments_query .= " AND a.appointment_date = :date";
    $all_params[':date'] = $date_filter;
}
$all_stmt = $db->prepare($all_appointments_query);
foreach ($all_params as $key => $value) {
    $all_stmt->bindValue($key, $value);
}
$all_stmt->execute();
$all_appointments = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Statistics
$total_appointments = count($all_appointments);
$status_counts = [
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'rescheduled' => 0
];
foreach ($all_appointments as $app) {
    if (isset($status_counts[strtolower($app['status'])])) {
        $status_counts[strtolower($app['status'])]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Staff Panel</title>
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
            
            --card-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-pending: #FF9800;
            --color-confirmed: #4CAF50;
            --color-completed: #2196F3;
            --color-cancelled: #EF5350;
            --color-rescheduled: #FF9800;
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
            
            --card-bg: #1a2332;
            --filter-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-pending: #FFA726;
            --color-confirmed: #66BB6A;
            --color-completed: #42A5F5;
            --color-cancelled: #EF5350;
            --color-rescheduled: #FFA726;
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
            position: relative;
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
            font-size: 0.85rem;
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
        .search-box {
            display: flex;
            align-items: center;
            background: var(--card-bg);
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
            background: var(--card-bg);
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
            background: var(--card-bg);
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

        /* ========== ASSIGNED SERVICES ========== */
        .assigned-services {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 12px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border: 1px solid var(--border-color);
        }
        .assigned-services .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .service-tag {
            background: var(--card-bg);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            color: var(--text-primary);
            font-weight: 500;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .service-tag.no-service {
            background: rgba(244, 67, 54, 0.1);
            color: #EF5350;
            border-color: rgba(244, 67, 54, 0.2);
        }

        /* ========== STATS ROW ========== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-item {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .stat-item .icon.total { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-item .icon.pending { background: rgba(255, 152, 0, 0.12); color: var(--color-pending); }
        .stat-item .icon.confirmed { background: rgba(76, 175, 80, 0.12); color: var(--color-confirmed); }
        .stat-item .icon.completed { background: rgba(33, 150, 243, 0.12); color: var(--color-completed); }
        .stat-item .icon.cancelled { background: rgba(244, 67, 54, 0.12); color: var(--color-cancelled); }
        .stat-item .info h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-item .info p {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        /* ========== FILTER SECTION ========== */
        .filter-section {
            background: var(--filter-bg);
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-form {
            display: flex;
            gap: 16px;
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
        .filter-group select, .filter-group input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
            min-width: 140px;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .btn-filter {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-filter:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-reset {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .btn-reset:hover {
            background: var(--border-color);
        }

        /* ========== TABLE CARD ========== */
        .table-card {
            background: var(--card-bg);
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
        .data-table .resident-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .data-table .resident-contact {
            font-size: 0.75rem;
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
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-pending);
        }
        .status-badge.confirmed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-confirmed);
        }
        .status-badge.completed {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-completed);
        }
        .status-badge.cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-cancelled);
        }
        .status-badge.rescheduled {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-rescheduled);
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
        .btn-edit {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }
        .btn-edit:hover {
            background: #2196F3;
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
        .no-data .sub-text {
            font-size: 0.8rem;
            margin-top: 8px;
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
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 28px 32px;
            max-width: 450px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-30px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-content h3 {
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-content h3 i {
            color: var(--maroon-light);
        }
        .modal-content label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .modal-content select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
            margin-bottom: 16px;
        }
        .modal-content select:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-buttons .btn-cancel {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
        }
        .modal-buttons .btn-cancel:hover {
            background: var(--border-color);
        }
        .modal-buttons .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
        }
        .modal-buttons .btn-submit:hover {
            background: var(--maroon-light);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-row { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-group select, .filter-group input { min-width: unset; }
            .table-card { padding: 16px; }
            .data-table th, .data-table td { padding: 8px 10px; font-size: 0.75rem; }
            .btn-sm { font-size: 0.6rem; padding: 3px 8px; }
            .modal-content { padding: 20px; }
            .pagination .page-info { width: 100%; text-align: center; margin-right: 0; margin-bottom: 8px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-item { padding: 10px 14px; }
            .stat-item .icon { width: 32px; height: 32px; font-size: 0.9rem; }
            .stat-item .info h3 { font-size: 0.9rem; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 6px 8px; }
            .service-tag { font-size: 0.65rem; padding: 3px 10px; }
            .pagination a, .pagination span { min-width: 30px; height: 30px; font-size: 0.7rem; padding: 0 8px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-calendar-check"></i></div>
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
            <li><a href="manage-appointments.php" class="active"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
            <li class="menu-divider">Services</li>
            <?php if(in_array('AICS', $assigned_categories)): ?>
            <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
            <?php endif; ?>
            <?php if(in_array('Housing', $assigned_categories)): ?>
            <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
            <?php endif; ?>
            <?php if(in_array('Senior Citizen', $assigned_categories)): ?>
            <li><a href="manage-senior.php"><i class="fas fa-user-graduate"></i> Senior Pension</a></li>
            <?php endif; ?>
            <?php if(in_array('PWD', $assigned_categories)): ?>
            <li><a href="manage-pwd.php"><i class="fas fa-wheelchair"></i> PWD Registration</a></li>
            <?php endif; ?>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li class="menu-divider"></li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-calendar-check" style="color: var(--maroon-light); margin-right: 10px;"></i> Manage Appointments</h1>
                <p>View and manage all appointment requests</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search appointments..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="reports.php" class="top-icon"><i class="fas fa-chart-bar"></i></a>
                <a href="../logout.php" class="top-icon"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
        
        <!-- Assigned Services -->
        <div class="assigned-services">
            <span class="label"><i class="fas fa-tasks"></i> Your Services:</span>
            <?php if(empty($assigned_categories)): ?>
                <span class="service-tag no-service">
                    <i class="fas fa-exclamation-circle"></i> No services assigned. Contact administrator.
                </span>
            <?php else: ?>
                <?php foreach($assigned_categories as $cat): ?>
                    <span class="service-tag">
                        <?php 
                        switch($cat) {
                            case 'AICS': echo '🏥 AICS'; break;
                            case 'Housing': echo '🏠 Housing'; break;
                            case 'Senior Citizen': echo '👴 Senior'; break;
                            case 'PWD': echo '♿ PWD'; break;
                            default: echo htmlspecialchars($cat);
                        }
                        ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
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
        <div class="stats-row">
            <div class="stat-item">
                <div class="icon total"><i class="fas fa-calendar-alt"></i></div>
                <div class="info">
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon pending"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h3><?php echo $status_counts['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon confirmed"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h3><?php echo $status_counts['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon completed"><i class="fas fa-check-double"></i></div>
                <div class="info">
                    <h3><?php echo $status_counts['completed']; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="icon cancelled"><i class="fas fa-times-circle"></i></div>
                <div class="info">
                    <h3><?php echo $status_counts['cancelled']; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="rescheduled" <?php echo $status_filter == 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-day"></i> Date</label>
                    <input type="date" name="date" value="<?php echo $date_filter; ?>">
                </div>
                <div class="filter-group" style="flex-direction: row; gap: 8px; align-items: center;">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                    <a href="manage-appointments.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Appointments Table -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Appointment List</h2>
                <span class="count-badge"><?php echo $total_records; ?> records</span>
            </div>
            
            <?php if(empty($filtered_appointments)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>No appointments found</p>
                    <p class="sub-text"><?php echo empty($assigned_categories) ? 'You have no services assigned. Please contact the administrator.' : 'Try adjusting your filters.'; ?></p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Resident</th>
                                <th>Contact</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($filtered_appointments as $appointment): ?>
                            <tr>
                                <td><strong>#<?php echo $appointment['appointment_id']; ?></strong></td>
                                <td>
                                    <div class="resident-name"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($appointment['contact_number'] ?? 'N/A'); ?></div>
                                    <div class="resident-contact"><?php echo htmlspecialchars($appointment['email'] ?? ''); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['purpose'] ?? 'General Inquiry'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($appointment['status']); ?>">
                                        <i class="fas fa-<?php 
                                            echo $appointment['status'] == 'pending' ? 'clock' : 
                                                ($appointment['status'] == 'confirmed' ? 'check-circle' : 
                                                ($appointment['status'] == 'completed' ? 'check-double' : 
                                                ($appointment['status'] == 'cancelled' ? 'times-circle' : 'sync-alt'))); 
                                        ?>"></i>
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                        <button class="btn-sm btn-edit" onclick="openStatusModal(<?php echo $appointment['appointment_id']; ?>, '<?php echo $appointment['status']; ?>')">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <a href="?delete=1&id=<?php echo $appointment['appointment_id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this appointment?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <span class="page-info">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?></span>
                    
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter != 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($date_filter) ? '&date=' . $date_filter : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?page=1' . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($date_filter) ? '&date=' . $date_filter : '') . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $page ? 'active' : '';
                        echo '<a href="?page=' . $i . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($date_filter) ? '&date=' . $date_filter : '') . '" class="' . $active . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="ellipsis">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . ($status_filter != 'all' ? '&status=' . $status_filter : '') . (!empty($date_filter) ? '&date=' . $date_filter : '') . '">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter != 'all' ? '&status=' . $status_filter : ''; ?><?php echo !empty($date_filter) ? '&date=' . $date_filter : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Update Appointment Status</h3>
        <form method="POST" action="">
            <input type="hidden" name="appointment_id" id="modal_appointment_id">
            <label>Select Status:</label>
            <select name="status" id="modal_status" required>
                <option value="pending">⏳ Pending</option>
                <option value="confirmed">✅ Confirmed</option>
                <option value="completed">✔️ Completed</option>
                <option value="cancelled">❌ Cancelled</option>
                <option value="rescheduled">🔄 Rescheduled</option>
            </select>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update_status" class="btn-submit">
                    <i class="fas fa-save"></i> Update Status
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
        const query = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('.data-table tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
});

// ========== MODAL FUNCTIONS ==========
function openStatusModal(appointmentId, currentStatus) {
    document.getElementById('modal_appointment_id').value = appointmentId;
    document.getElementById('modal_status').value = currentStatus.toLowerCase();
    document.getElementById('statusModal').classList.add('active');
}

function closeModal() {
    document.getElementById('statusModal').classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
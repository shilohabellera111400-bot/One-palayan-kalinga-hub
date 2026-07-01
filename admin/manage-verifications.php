<?php
// admin/manage-verifications.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle verification status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_verification'])) {
    $verification_id = sanitize($_POST['verification_id']);
    $status = sanitize($_POST['status']);
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
    
    $db->beginTransaction();
    
    try {
        // Get user_id from verification request
        $get_user = "SELECT user_id FROM verification_requests WHERE verification_id = :id";
        $get_stmt = $db->prepare($get_user);
        $get_stmt->bindParam(':id', $verification_id);
        $get_stmt->execute();
        $verification = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($verification) {
            // Update verification request
            $update_query = "UPDATE verification_requests SET 
                             status = :status, 
                             reviewed_by = :reviewed_by, 
                             review_date = NOW(),
                             rejection_reason = :reason
                             WHERE verification_id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':reviewed_by', $_SESSION['user_id']);
            $update_stmt->bindParam(':reason', $rejection_reason);
            $update_stmt->bindParam(':id', $verification_id);
            $update_stmt->execute();
            
            // Update user's verification status
            $user_update = "UPDATE users SET 
                            verification_status = :status,
                            verification_date = NOW(),
                            verified_by = :verified_by
                            WHERE user_id = :user_id";
            $user_stmt = $db->prepare($user_update);
            $user_stmt->bindParam(':status', $status);
            $user_stmt->bindParam(':verified_by', $_SESSION['user_id']);
            $user_stmt->bindParam(':user_id', $verification['user_id']);
            $user_stmt->execute();
            
            // Create notification for user
            $notif_title = $status == 'verified' ? 'Verification Approved' : 'Verification Rejected';
            $notif_message = $status == 'verified' 
                ? 'Your residency verification has been approved! You can now access all services.'
                : 'Your residency verification was rejected. Reason: ' . $rejection_reason;
            
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, :title, :message, 'verification')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $verification['user_id']);
            $notif_stmt->bindParam(':title', $notif_title);
            $notif_stmt->bindParam(':message', $notif_message);
            $notif_stmt->execute();
            
            $db->commit();
            $success = "Verification status updated successfully!";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT v.*, u.first_name, u.last_name, u.email, u.contact_number,
          s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          LEFT JOIN staff s ON v.reviewed_by = s.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $query .= " AND v.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY v.submitted_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM verification_requests";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

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
    <title>Manage Verifications - Admin</title>
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
            
            /* Enhanced Shadows */
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
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
        .stat-icon.pending { background: rgba(255, 152, 0, 0.12); color: #FF9800; }
        .stat-icon.verified { background: rgba(76, 175, 80, 0.12); color: #4CAF50; }
        .stat-icon.rejected { background: rgba(244, 67, 54, 0.12); color: #EF5350; }

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
        .filter-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 160px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-group select option {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
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
        .clear-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 50px;
            border: 1px solid transparent;
        }
        .clear-link:hover {
            color: var(--maroon-light);
            border-color: var(--border-color);
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
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        .requests-table th, .requests-table td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .requests-table th {
            background: var(--badge-bg);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .requests-table tr:hover {
            background: var(--badge-bg);
        }
        .requests-table td {
            font-size: 0.85rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .requests-table .user-cell {
            display: flex;
            flex-direction: column;
        }
        .requests-table .user-cell .name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .requests-table .user-cell .email {
            font-size: 0.75rem;
            color: var(--text-muted);
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
        .status-badge.pending { background: rgba(255, 152, 0, 0.15); color: #FF9800; }
        .status-badge.verified { background: rgba(76, 175, 80, 0.15); color: #4CAF50; }
        .status-badge.rejected { background: rgba(244, 67, 54, 0.15); color: #EF5350; }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-view, .btn-verify {
            padding: 6px 14px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-verify {
            background: var(--maroon);
            color: white;
        }
        .btn-verify:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
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

        /* ========== MODAL ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            backdrop-filter: blur(6px);
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            padding: 32px;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        @keyframes slideIn {
            from {
                transform: translateY(-30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        .modal-content h3 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-content h3 i {
            color: var(--maroon-light);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            font-family: inherit;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .modal-buttons .btn-submit {
            background: var(--maroon);
            color: white;
        }
        .modal-buttons .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .modal-buttons .btn-cancel {
            background: var(--badge-bg);
            color: var(--text-secondary);
        }
        .modal-buttons .btn-cancel:hover {
            background: var(--border-color);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; min-width: unset; }
            .requests-table { display: block; overflow-x: auto; }
            .requests-table th, .requests-table td { 
                padding: 10px 14px; 
                font-size: 0.75rem;
            }
            .action-group { flex-direction: column; }
            .btn-view, .btn-verify { width: 100%; justify-content: center; }
            .modal-content { padding: 24px; }
            .modal-buttons { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stat-card { padding: 14px 16px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .stat-info h3 { font-size: 1.2rem; }
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
            <li><a href="manage-verifications.php" class="active"><i class="fas fa-shield-alt"></i> Verifications</a></li>
            <li><a href="manage-staff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
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
                <h1><i class="fas fa-shield-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> Resident Verifications</h1>
                <p>Review and verify resident residency requests</p>
            </div>
            <div class="top-bar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search verifications..." id="globalSearch">
                </div>
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="audit-logs.php" class="top-icon"><i class="fas fa-bell"></i></a>
                <a href="settings.php" class="top-icon"><i class="fas fa-cog"></i></a>
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
            <div class="stat-card" onclick="window.location.href='manage-verifications.php?status=all'">
                <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-verifications.php?status=pending'">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending'] ?? 0); ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-verifications.php?status=verified'">
                <div class="stat-icon verified"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['verified'] ?? 0); ?></h3>
                    <p>Verified</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-verifications.php?status=rejected'">
                <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['rejected'] ?? 0); ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="search" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group" style="flex: 0.5; min-width: 120px;">
                    <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <?php if($status_filter != 'all' || !empty($search)): ?>
                    <a href="manage-verifications.php" class="clear-link"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Requests Table -->
        <div class="table-container">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>ID Type</th>
                        <th>Barangay</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($row['verification_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div class="user-cell">
                                    <span class="name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                    <span class="email"><?php echo htmlspecialchars($row['email']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['id_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['barangay_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['submitted_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $row['status'] == 'pending' ? 'clock' : 
                                            ($row['status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                                    ?>"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-group" style="justify-content: center;">
                                    <a href="view-verification.php?id=<?php echo $row['verification_id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if($row['status'] == 'pending'): ?>
                                    <button onclick="openModal(<?php echo $row['verification_id']; ?>)" class="btn-verify">
                                        <i class="fas fa-check-circle"></i> Verify
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No verification requests found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-gavel"></i> Verify Resident</h3>
        <form method="POST">
            <input type="hidden" name="verification_id" id="verification_id">
            <div class="form-group">
                <label>Verification Status</label>
                <select name="status" id="verify_status" required>
                    <option value="verified">✅ Approve - Verified Resident</option>
                    <option value="rejected">❌ Reject - Not Verified</option>
                </select>
            </div>
            <div class="form-group" id="reason_group" style="display: none;">
                <label>Rejection Reason <span style="color: #EF5350;">*</span></label>
                <textarea name="rejection_reason" id="rejection_reason" rows="3" placeholder="Please provide a reason for rejection..."></textarea>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="update_verification" class="btn-submit">Submit</button>
                <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

// Check for saved theme preference
const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

// Apply theme
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
            window.location.href = 'manage-verifications.php?search=' + encodeURIComponent(query) + '&status=<?php echo $status_filter; ?>';
        }
    }
});

// ========== MODAL ==========
function openModal(id) {
    document.getElementById('verification_id').value = id;
    document.getElementById('verifyModal').classList.add('active');
}

function closeModal() {
    document.getElementById('verifyModal').classList.remove('active');
    document.getElementById('reason_group').style.display = 'none';
    document.getElementById('rejection_reason').value = '';
    document.getElementById('verify_status').value = 'verified';
}

// Show/hide rejection reason
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('verify_status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const reasonGroup = document.getElementById('reason_group');
            if (this.value === 'rejected') {
                reasonGroup.style.display = 'block';
                document.getElementById('rejection_reason').required = true;
            } else {
                reasonGroup.style.display = 'none';
                document.getElementById('rejection_reason').required = false;
            }
        });
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('verifyModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
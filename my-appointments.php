<?php
// my-appointments.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = sanitize($_POST['appointment_id']);
    
    $check_query = "SELECT * FROM appointments 
                    WHERE appointment_id = :id AND user_id = :user_id 
                    AND status IN ('pending', 'confirmed')";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $appointment_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $update_query = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $appointment_id);
        
        if ($update_stmt->execute()) {
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'Appointment Cancelled', 
                            'Your appointment has been cancelled successfully.', 'appointment')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            $success = "Appointment cancelled successfully.";
        } else {
            $error = "Failed to cancel appointment.";
        }
    } else {
        $error = "Appointment not found or cannot be cancelled.";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

$query = "SELECT a.*, s.service_name, s.category_id,
          CASE 
              WHEN a.status = 'pending' THEN 1
              WHEN a.status = 'confirmed' THEN 2
              WHEN a.status = 'completed' THEN 3
              WHEN a.status = 'cancelled' THEN 4
              ELSE 5
          END as status_order
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          WHERE a.user_id = :user_id";

$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND a.appointment_date BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $date_from;
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming appointments
$upcoming_query = "SELECT a.*, s.service_name 
                   FROM appointments a
                   JOIN services s ON a.service_id = s.service_id
                   WHERE a.user_id = :user_id 
                   AND a.appointment_date >= CURDATE()
                   AND a.status IN ('pending', 'confirmed')
                   ORDER BY a.appointment_date ASC, a.appointment_time ASC
                   LIMIT 3";
$upcoming_stmt = $db->prepare($upcoming_query);
$upcoming_stmt->bindParam(':user_id', $_SESSION['user_id']);
$upcoming_stmt->execute();
$upcoming = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = '';
$avatar_url = '';
$user_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data) {
    $profile_picture = $user_data['profile_picture'] ?? '';
    $avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
        ? $profile_picture 
        : '';
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
        .alert i { font-size: 1.2rem; }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 14px 18px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid var(--midnight);
            border: 1px solid var(--border-color);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card.pending { border-left-color: var(--color-warning); }
        .stat-card.confirmed { border-left-color: var(--color-success); }
        .stat-card.completed { border-left-color: var(--color-info); }
        .stat-card.cancelled { border-left-color: var(--color-danger); }
        .stat-number {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ========== UPCOMING SECTION ========== */
        .upcoming-section {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            border-radius: var(--radius-card);
            padding: 18px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            color: white;
            box-shadow: var(--shadow-md);
        }
        .upcoming-section .upcoming-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .upcoming-section .upcoming-info h4 {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .upcoming-section .upcoming-info .badge {
            background: rgba(255,255,255,0.15);
            padding: 4px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .upcoming-section .upcoming-info .badge i {
            font-size: 0.7rem;
        }
        .upcoming-section .quick-actions {
            display: flex;
            gap: 10px;
        }
        .upcoming-section .quick-actions a {
            background: rgba(255,255,255,0.12);
            padding: 8px 18px;
            border-radius: 50px;
            text-decoration: none;
            color: white;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .upcoming-section .quick-actions a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        /* ========== ACTION BAR ========== */
        .action-bar {
            background: var(--card-bg);
            padding: 18px 22px;
            border-radius: var(--radius-element);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .book-btn {
            background: var(--maroon);
            color: white;
            padding: 10px 22px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .book-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-section select,
        .filter-section input {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.82rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        .filter-section select:focus,
        .filter-section input:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-section .sep {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .filter-btn {
            background: var(--maroon);
            color: white;
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
        .clear-btn {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 8px 18px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        .clear-btn:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        /* ========== TABLE ========== */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow-x: auto;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .appointments-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--badge-bg);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        .appointments-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .appointments-table tr:hover {
            background: var(--badge-bg);
        }
        .appointments-table .service-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .appointments-table .purpose-text {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.confirmed {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.completed {
            background: rgba(33, 150, 243, 0.15);
            color: var(--color-info);
        }
        .status-badge.cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }
        .btn-cancel {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            padding: 4px 12px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-cancel:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .empty-state .book-btn {
            display: inline-block;
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
            backdrop-filter: blur(4px);
        }
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--card-bg);
            padding: 28px 32px;
            border-radius: var(--radius-card);
            max-width: 420px;
            width: 90%;
            transform: translateY(-20px);
            transition: transform 0.3s;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .modal.show .modal-content {
            transform: translateY(0);
        }
        .modal-content h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .modal-content h3 i {
            color: var(--color-warning);
        }
        .modal-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        .modal-content .text-danger {
            color: var(--color-danger);
            font-size: 0.8rem;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .modal-actions .btn-cancel {
            padding: 10px 25px;
            font-size: 0.85rem;
        }
        .modal-actions .btn-view {
            padding: 10px 25px;
            font-size: 0.85rem;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .action-bar { flex-direction: column; align-items: stretch; }
            .filter-section { flex-direction: column; }
            .filter-section select, .filter-section input { width: 100%; }
            .filter-section .sep { display: none; }
            .filter-section .filter-btn, .filter-section .clear-btn { width: 100%; justify-content: center; }
            .upcoming-section { flex-direction: column; text-align: center; }
            .upcoming-section .upcoming-info { justify-content: center; }
            .upcoming-section .quick-actions { justify-content: center; }
            .table-container { padding: 12px 16px; }
            .appointments-table th, .appointments-table td { padding: 8px 10px; font-size: 0.75rem; }
            .action-buttons { flex-direction: column; align-items: center; }
            .action-buttons .btn-view, .action-buttons .btn-cancel { width: 100%; justify-content: center; }
            .modal-content { padding: 20px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 10px 14px; }
            .stat-number { font-size: 1.2rem; }
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
            <li><a href="update-profile-info.php"><i class="fas fa-edit"></i> Complete Profile</a></li>
            <li><a href="verify-resident.php"><i class="fas fa-shield-alt"></i> Verify Residency</a></li>
            <li><a href="appointments.php"><i class="fas fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="my-appointments.php" class="active"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
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
                <h1><i class="fas fa-calendar-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> My Appointments</h1>
                <p>View and manage all your scheduled appointments</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='my-appointments.php?status=all'">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card pending" onclick="window.location.href='my-appointments.php?status=pending'">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card confirmed" onclick="window.location.href='my-appointments.php?status=confirmed'">
                <div class="stat-number"><?php echo $stats['confirmed'] ?? 0; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card completed" onclick="window.location.href='my-appointments.php?status=completed'">
                <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card cancelled" onclick="window.location.href='my-appointments.php?status=cancelled'">
                <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>

        <!-- Upcoming Appointments Section -->
        <?php if(!empty($upcoming)): ?>
        <div class="upcoming-section">
            <div class="upcoming-info">
                <h4><i class="fas fa-calendar-check"></i> Upcoming Appointments</h4>
                <?php foreach($upcoming as $up): ?>
                <span class="badge">
                    <i class="fas fa-clock"></i>
                    <?php echo htmlspecialchars($up['service_name']); ?> - 
                    <?php echo date('M d, h:i A', strtotime($up['appointment_date'] . ' ' . $up['appointment_time'])); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="quick-actions">
                <a href="appointments.php"><i class="fas fa-plus"></i> Book New</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="action-bar">
            <a href="appointments.php" class="book-btn">
                <i class="fas fa-plus-circle"></i> Book New Appointment
            </a>
            <div class="filter-section">
                <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    <span class="sep">to</span>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    <?php if($status_filter != 'all' || $date_from != date('Y-m-01') || $date_to != date('Y-m-d')): ?>
                    <a href="my-appointments.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
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

        <!-- Appointments Table -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="table-container">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td>
                            <div class="service-name"><?php echo htmlspecialchars($app['service_name']); ?></div>
                            <?php if(!empty($app['purpose'])): ?>
                            <div class="purpose-text"><?php echo htmlspecialchars(substr($app['purpose'], 0, 50)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo date('h:i A', strtotime($app['appointment_time'])); ?></div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $app['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo $app['status'] == 'pending' ? 'clock' : 
                                        ($app['status'] == 'confirmed' ? 'check-circle' : 
                                        ($app['status'] == 'completed' ? 'check-double' : 'times-circle')); 
                                ?>"></i>
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div class="action-buttons" style="justify-content: center;">
                                <a href="view-appointment.php?id=<?php echo $app['appointment_id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if($app['status'] == 'pending' || $app['status'] == 'confirmed'): ?>
                                <button onclick="showCancelModal(<?php echo $app['appointment_id']; ?>)" class="btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Appointments Found</h3>
            <p>You don't have any appointments scheduled yet.</p>
            <a href="appointments.php" class="book-btn"><i class="fas fa-plus"></i> Book Your First Appointment</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h3>
        <p>Are you sure you want to cancel this appointment?</p>
        <p class="text-danger"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="appointment_id" id="cancel_appointment_id">
            <div class="modal-actions">
                <button type="button" onclick="closeModal()" class="btn-view">No, Keep It</button>
                <button type="submit" name="cancel_appointment" class="btn-cancel">Yes, Cancel</button>
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
function showCancelModal(id) {
    document.getElementById('cancel_appointment_id').value = id;
    document.getElementById('cancelModal').classList.add('show');
}

function closeModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('cancelModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 500);
    });
}, 5000);
</script>
</body>
</html>
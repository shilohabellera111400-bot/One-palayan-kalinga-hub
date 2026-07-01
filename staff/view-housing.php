<?php
// staff/view-housing.php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get housing application ID from URL
$beneficiary_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$beneficiary_id) {
    redirect('manage-housing.php');
}

// First, check what columns exist in users table
$check_columns = $db->query("SHOW COLUMNS FROM users");
$user_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $user_columns[] = $col['Field'];
}

// Build user fields dynamically
$user_fields = "u.first_name, u.last_name, u.email, u.contact_number";
if(in_array('middle_name', $user_columns)) {
    $user_fields .= ", u.middle_name";
}
if(in_array('street', $user_columns)) {
    $user_fields .= ", u.street";
}
if(in_array('barangay', $user_columns)) {
    $user_fields .= ", u.barangay";
}
if(in_array('city', $user_columns)) {
    $user_fields .= ", u.city";
}
if(in_array('province', $user_columns)) {
    $user_fields .= ", u.province";
}
if(in_array('postal_code', $user_columns)) {
    $user_fields .= ", u.postal_code";
}
if(in_array('birth_date', $user_columns)) {
    $user_fields .= ", u.birth_date";
}
if(in_array('profile_picture', $user_columns)) {
    $user_fields .= ", u.profile_picture";
}

// Fetch housing application details
$query = "SELECT h.*, 
          $user_fields,
          p.program_name, p.location, p.description as program_description, p.total_units,
          CONCAT(u.first_name, ' ', u.last_name) as full_name,
          s.first_name as staff_first_name, s.last_name as staff_last_name,
          s.position as staff_position
          FROM housing_beneficiaries h
          JOIN users u ON h.user_id = u.user_id
          JOIN housing_programs p ON h.program_id = p.program_id
          LEFT JOIN staff s ON h.approved_by = s.user_id
          WHERE h.beneficiary_id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $beneficiary_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('manage-housing.php?error=Application not found');
}

$application = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch documents for this application
$doc_query = "SELECT * FROM documents 
              WHERE user_id = :user_id 
              ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':user_id', $application['user_id']);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to clean document path
function cleanDocumentPath($path) {
    if (empty($path)) {
        return '';
    }
    $path = ltrim($path, '/');
    if (strpos($path, 'uploads/') === 0) {
        $path = substr($path, 8);
    }
    return '../uploads/' . $path;
}

// Calculate age from birth date (if birth_date exists)
$age = null;
if (isset($application['birth_date']) && !empty($application['birth_date'])) {
    $birthDate = new DateTime($application['birth_date']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// Get status color and icon
function getHousingStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#FF9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => 'rgba(255,152,0,0.12)'],
        'qualified' => ['color' => '#2196F3', 'icon' => 'fa-search', 'text' => 'Qualified', 'bg' => 'rgba(33,150,243,0.12)'],
        'approved' => ['color' => '#4CAF50', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => 'rgba(76,175,80,0.12)'],
        'rejected' => ['color' => '#EF5350', 'icon' => 'fa-times-circle', 'text' => 'Rejected', 'bg' => 'rgba(244,67,54,0.12)'],
        'released' => ['color' => '#9C27B0', 'icon' => 'fa-gift', 'text' => 'Released', 'bg' => 'rgba(156,39,176,0.12)']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}

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
$staff_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_stmt->execute();
$staff_data = $staff_stmt->fetch(PDO::FETCH_ASSOC);
if ($staff_data) {
    $profile_picture = $staff_data['profile_picture'] ?? '';
    $avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
        ? '../' . $profile_picture 
        : '';
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get applicant avatar
$app_pic = !empty($application['profile_picture']) && file_exists(__DIR__ . '/../' . $application['profile_picture']) 
    ? '../' . $application['profile_picture'] 
    : '';
$app_initials = strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Housing Application #<?php echo $beneficiary_id; ?> - Staff</title>
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
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
            --color-purple: #9C27B0;
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
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
            --color-purple: #AB47BC;
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
            margin-bottom: 24px;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            color: var(--text-secondary);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== APPLICATION HEADER ========== */
        .app-header {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .app-header .left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .app-header .left .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--badge-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--maroon-light);
        }
        .app-header .left .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .app-header .left .avatar .initials {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-primary);
        }
        .app-info h1 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .app-info h1 i {
            color: var(--maroon-light);
        }
        .app-info .meta {
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .app-info .meta strong {
            color: var(--text-primary);
        }

        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: var(--shadow-sm);
        }

        /* ========== INFO GRID ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 20px 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .info-card:hover {
            box-shadow: var(--shadow-hover);
        }
        .info-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        .info-card .card-header h3 {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 700;
        }
        .info-card .card-header i {
            color: var(--maroon-light);
            font-size: 1rem;
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row .label {
            width: 140px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.78rem;
            flex-shrink: 0;
        }
        .info-row .value {
            flex: 1;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .info-row .value .highlight {
            color: var(--text-primary);
            font-weight: 600;
        }
        .info-row .value .unit {
            color: var(--color-success);
            font-weight: 700;
        }

        /* ========== HOUSING NEED ========== */
        .housing-need {
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            padding: 16px 20px;
            margin-top: 8px;
            border-left: 3px solid var(--maroon-light);
        }
        .housing-need .content {
            color: var(--text-secondary);
            font-size: 0.82rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ========== DOCUMENTS ========== */
        .documents-list {
            display: grid;
            gap: 10px;
        }
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        .document-item:hover {
            background: var(--border-color);
            transform: translateX(4px);
        }
        .document-item .doc-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .document-item .doc-name i {
            color: var(--maroon-light);
            font-size: 1rem;
        }
        .btn-download {
            background: var(--maroon);
            color: white;
            padding: 4px 14px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-download:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
        }
        .empty-state i {
            font-size: 2rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            font-size: 0.82rem;
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
        .btn-print {
            background: var(--color-info);
            color: white;
        }
        .btn-print:hover {
            background: #1565C0;
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
            max-width: 550px;
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
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .modal-body .applicant-info span {
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .modal-body .applicant-info strong {
            color: var(--text-primary);
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
        .form-group small {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.7rem;
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
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .app-header { flex-direction: column; align-items: flex-start; }
            .app-header .left { width: 100%; }
            .info-row { flex-direction: column; }
            .info-row .label { width: 100%; margin-bottom: 4px; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-primary,
            .action-buttons .btn-secondary { width: 100%; justify-content: center; }
            .modal-content { width: 95%; }
            .modal-body .applicant-info { flex-direction: column; }
            .housing-need { padding: 12px 16px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .app-header { padding: 16px 20px; }
            .app-info h1 { font-size: 1rem; }
            .info-card { padding: 16px 18px; }
            .document-item { flex-direction: column; gap: 8px; }
            .document-item .btn-download { width: 100%; justify-content: center; }
        }
        @media print {
            .sidebar, .top-bar, .back-btn, .action-buttons, .theme-toggle { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
            .app-header { box-shadow: none; border: 1px solid #ddd; }
            .status-badge-large { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-home"></i></div>
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
            <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
            <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
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
                <h1><i class="fas fa-home" style="color: var(--maroon-light); margin-right: 10px;"></i> Housing Application Details</h1>
                <p>View and manage housing application</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="manage-housing.php" class="top-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <a href="manage-housing.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Housing Applications
        </a>

        <?php $statusInfo = getHousingStatusInfo($application['status']); ?>
        
        <!-- Application Header -->
        <div class="app-header">
            <div class="left">
                <div class="avatar">
                    <?php if(!empty($app_pic)): ?>
                        <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Applicant">
                    <?php else: ?>
                        <span class="initials"><?php echo $app_initials; ?></span>
                    <?php endif; ?>
                </div>
                <div class="app-info">
                    <h1>
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($application['program_name']); ?>
                        <span style="font-size: 0.7rem; background: var(--badge-bg); padding: 2px 12px; border-radius: 50px; font-weight: 500; color: var(--text-muted);">
                            #<?php echo $beneficiary_id; ?>
                        </span>
                    </h1>
                    <div class="meta">
                        <strong>Applicant:</strong> <?php echo htmlspecialchars($application['full_name']); ?>
                        <span style="margin: 0 8px;">•</span>
                        <strong>Applied:</strong> <?php echo date('M d, Y', strtotime($application['application_date'])); ?>
                    </div>
                </div>
            </div>
            <div>
                <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>20;">
                    <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                    <?php echo $statusInfo['text']; ?>
                </span>
            </div>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <!-- Applicant Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Applicant Information</h3>
                </div>
                <div class="info-row">
                    <span class="label">Full Name</span>
                    <span class="value"><span class="highlight"><?php echo htmlspecialchars($application['full_name']); ?></span></span>
                </div>
                <?php if(isset($application['middle_name']) && !empty($application['middle_name'])): ?>
                <div class="info-row">
                    <span class="label">Middle Name</span>
                    <span class="value"><?php echo htmlspecialchars($application['middle_name']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Contact Number</span>
                    <span class="value"><?php echo htmlspecialchars($application['contact_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email Address</span>
                    <span class="value"><?php echo htmlspecialchars($application['email']); ?></span>
                </div>
                <?php if($age): ?>
                <div class="info-row">
                    <span class="label">Age</span>
                    <span class="value"><?php echo $age; ?> years old</span>
                </div>
                <?php endif; ?>
                <?php if(isset($application['street']) && !empty($application['street'])): ?>
                <div class="info-row">
                    <span class="label">Address</span>
                    <span class="value">
                        <?php 
                        $address = [];
                        if(!empty($application['street'])) $address[] = $application['street'];
                        if(!empty($application['barangay'])) $address[] = $application['barangay'];
                        if(!empty($application['city'])) $address[] = $application['city'];
                        if(!empty($application['province'])) $address[] = $application['province'];
                        echo htmlspecialchars(implode(', ', $address));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Housing Program Details -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-building"></i>
                    <h3>Housing Program</h3>
                </div>
                <div class="info-row">
                    <span class="label">Program Name</span>
                    <span class="value"><span class="highlight"><?php echo htmlspecialchars($application['program_name']); ?></span></span>
                </div>
                <div class="info-row">
                    <span class="label">Location</span>
                    <span class="value"><?php echo htmlspecialchars($application['location']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Total Units</span>
                    <span class="value"><?php echo $application['total_units']; ?> units</span>
                </div>
                <?php if(!empty($application['unit_assigned'])): ?>
                <div class="info-row">
                    <span class="label">Unit Assigned</span>
                    <span class="value"><span class="unit"><?php echo htmlspecialchars($application['unit_assigned']); ?></span></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($application['program_description'])): ?>
                <div class="info-row">
                    <span class="label">Description</span>
                    <span class="value"><?php echo nl2br(htmlspecialchars($application['program_description'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Application Details -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-file-alt"></i>
                    <h3>Application Details</h3>
                </div>
                <div class="info-row">
                    <span class="label">Household Size</span>
                    <span class="value"><?php echo $application['household_composition']; ?> persons</span>
                </div>
                <div class="info-row">
                    <span class="label">Monthly Income</span>
                    <span class="value">₱<?php echo number_format($application['monthly_income'], 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Application Date</span>
                    <span class="value"><?php echo date('F d, Y', strtotime($application['application_date'])); ?></span>
                </div>
                <?php if(!empty($application['interview_date'])): ?>
                <div class="info-row">
                    <span class="label">Interview Date</span>
                    <span class="value"><?php echo date('F d, Y', strtotime($application['interview_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Processing Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Processing Information</h3>
                </div>
                <div class="info-row">
                    <span class="label">Current Status</span>
                    <span class="value">
                        <span style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 3px 14px; border-radius: 50px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </span>
                </div>
                <?php if(!empty($application['approved_by']) && !empty($application['staff_first_name'])): ?>
                <div class="info-row">
                    <span class="label">Processed By</span>
                    <span class="value"><?php echo htmlspecialchars($application['staff_first_name'] . ' ' . $application['staff_last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Position</span>
                    <span class="value"><?php echo htmlspecialchars($application['staff_position']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($application['approval_date'])): ?>
                <div class="info-row">
                    <span class="label">Approval Date</span>
                    <span class="value"><?php echo date('F d, Y h:i A', strtotime($application['approval_date'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Housing Need Assessment -->
        <?php if(!empty($application['housing_need_assessment'])): ?>
        <div class="info-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <i class="fas fa-clipboard-list"></i>
                <h3>Housing Need Assessment</h3>
            </div>
            <div class="housing-need">
                <div class="content">
                    <?php echo nl2br(htmlspecialchars($application['housing_need_assessment'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Supporting Documents -->
        <div class="info-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <i class="fas fa-paperclip"></i>
                <h3>Supporting Documents</h3>
            </div>
            <?php if(count($documents) > 0): ?>
                <div class="documents-list">
                    <?php foreach($documents as $doc): 
                        $doc_path = cleanDocumentPath($doc['document_path'] ?? $doc['file_path'] ?? '');
                        $doc_name = $doc['document_type'] ?? $doc['document_name'] ?? 'Document';
                        $file_ext = pathinfo($doc['document_path'] ?? $doc['file_name'] ?? '', PATHINFO_EXTENSION);
                        $icons = [
                            'pdf' => 'fa-file-pdf',
                            'jpg' => 'fa-file-image',
                            'jpeg' => 'fa-file-image',
                            'png' => 'fa-file-image',
                            'gif' => 'fa-file-image',
                            'doc' => 'fa-file-word',
                            'docx' => 'fa-file-word',
                            'xls' => 'fa-file-excel',
                            'xlsx' => 'fa-file-excel',
                            'txt' => 'fa-file-alt'
                        ];
                        $icon = $icons[strtolower($file_ext)] ?? 'fa-file';
                    ?>
                    <div class="document-item">
                        <div class="doc-name">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc_name))); ?></span>
                        </div>
                        <a href="<?php echo $doc_path; ?>" class="btn-download" target="_blank">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No documents attached to this application</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="manage-housing.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <?php if($application['status'] != 'released' && $application['status'] != 'rejected'): ?>
            <button onclick="showUpdateModal()" class="btn-primary">
                <i class="fas fa-edit"></i> Update Status
            </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-primary btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Application Status</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="manage-housing.php">
            <div class="modal-body">
                <input type="hidden" name="beneficiary_id" value="<?php echo $beneficiary_id; ?>">
                
                <div class="applicant-info">
                    <span><strong>Applicant:</strong> <?php echo htmlspecialchars($application['full_name']); ?></span>
                    <span><strong>Program:</strong> <?php echo htmlspecialchars($application['program_name']); ?></span>
                </div>
                
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleHousingFields()">
                        <option value="qualified">📋 Mark as Qualified</option>
                        <option value="approved">✅ Approve & Assign Unit</option>
                        <option value="rejected">❌ Reject</option>
                        <option value="released">💰 Mark as Released</option>
                        <option value="pending">⏰ Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group" id="unit_field" style="display: none;">
                    <label>Unit Number / House Assignment</label>
                    <input type="text" name="unit_assigned" id="unit_assigned" placeholder="e.g., Block 1, Lot 5, Unit 203">
                    <small>The housing unit assigned to the beneficiary</small>
                </div>
                
                <div class="form-group" id="interview_field" style="display: none;">
                    <label>Interview / Site Visit Date</label>
                    <input type="date" name="interview_date" id="interview_date" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label>Rejection Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary">
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

// ========== MODAL FUNCTIONS ==========
function showUpdateModal() {
    document.getElementById('updateModal').classList.add('active');
}

function closeModal() {
    document.getElementById('updateModal').classList.remove('active');
    document.getElementById('unit_field').style.display = 'none';
    document.getElementById('interview_field').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('unit_assigned').value = '';
    document.getElementById('interview_date').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'qualified';
}

function toggleHousingFields() {
    const status = document.getElementById('modal_status').value;
    const unitField = document.getElementById('unit_field');
    const interviewField = document.getElementById('interview_field');
    const remarksField = document.getElementById('remarks_field');
    
    unitField.style.display = 'none';
    interviewField.style.display = 'none';
    remarksField.style.display = 'none';
    
    if (status === 'approved') {
        unitField.style.display = 'block';
        interviewField.style.display = 'block';
        document.getElementById('unit_assigned').required = true;
        document.getElementById('interview_date').required = true;
    } else if (status === 'qualified') {
        interviewField.style.display = 'block';
        document.getElementById('interview_date').required = true;
    } else if (status === 'rejected') {
        remarksField.style.display = 'block';
        document.getElementById('remarks').required = true;
    }
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
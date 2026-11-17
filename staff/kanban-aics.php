<?php
// staff/kanban-aics.php - Drag & Drop Kanban Board for Staff
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/aics-audit-helper.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Access check: Get assigned categories for this staff
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);

if (!$has_aics_access) {
    $_SESSION['error'] = "You do not have permission to manage AICS.";
    redirect('../staff-dashboard.php');
}

// Fetch staff details
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all AICS requests
$query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.profile_picture 
          FROM aics_requests a 
          JOIN users u ON a.user_id = u.user_id
          ORDER BY a.submission_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Perform batch 90-day frequency check
$user_ids = array_column($all_requests, 'user_id');
$audit_map = function_exists('batchCheckAicsFrequency') ? batchCheckAicsFrequency($db, $user_ids) : [];

// Map into 5 columns with audit flags attached
$columns = [
    'submitted' => [],
    'reviewing' => [],
    'approved'  => [],
    'released'  => [],
    'rejected'  => []
];

foreach($all_requests as &$req) {
    $uid = (int)$req['user_id'];
    $req['audit_flag'] = $audit_map[$uid] ?? null;
    if (array_key_exists($req['status'], $columns)) {
        $columns[$req['status']][] = $req;
    }
}
unset($req);

// Column configuration metadata
$column_config = [
    'submitted' => [
        'title' => 'Submitted',
        'icon'  => 'fa-clock',
        'color' => '#FF9800',
        'bg'    => 'rgba(255, 152, 0, 0.12)'
    ],
    'reviewing' => [
        'title' => 'Reviewing',
        'icon'  => 'fa-search',
        'color' => '#2196F3',
        'bg'    => 'rgba(33, 150, 243, 0.12)'
    ],
    'approved' => [
        'title' => 'Approved',
        'icon'  => 'fa-check-circle',
        'color' => '#4CAF50',
        'bg'    => 'rgba(76, 175, 80, 0.12)'
    ],
    'released' => [
        'title' => 'Released',
        'icon'  => 'fa-hand-holding-usd',
        'color' => '#9C27B0',
        'bg'    => 'rgba(156, 39, 176, 0.12)'
    ],
    'rejected' => [
        'title' => 'Disapproved',
        'icon'  => 'fa-times-circle',
        'color' => '#EF5350',
        'bg'    => 'rgba(244, 67, 54, 0.12)'
    ]
];

// Sidebar initials & avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Dark mode check
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<?php
$page_title = 'AICS Kanban Board - Staff Portal';
$active_nav = 'aics';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
    :root {
        --tan: var(--ref-gold);
        --maroon: var(--ref-maroon);
        --maroon-light: var(--ref-maroon-light);
        --maroon-dark: #5a0202;
        --midnight: #0F414A;
        --white: #FFFFFF;
        
        --bg-primary: var(--ref-canvas);
        --bg-secondary: var(--ref-card);
        --bg-card: var(--ref-card);
        --text-primary: var(--ref-text);
        --text-secondary: var(--ref-text);
        --text-muted: var(--ref-muted);
        --border-color: var(--ref-border);
        --badge-bg: var(--ref-card-subtle);
        
        --radius-card: 18px;
        --radius-element: 12px;
    }
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #C8D0DC;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 6px 24px rgba(15,65,74,0.12), 0 2px 8px rgba(0,0,0,0.07);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --stat-bg: #FFFFFF;
            --filter-bg: #FFFFFF;
            --table-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 18px;
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

        /* Sidebar */
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
            padding: 24px 20px 18px;
            border-bottom: 1px solid var(--divider);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 42px;
            height: 42px;
            background: rgba(255,255,255,0.10);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--tan);
            font-size: 1.2rem;
        }
        .logo-text h2 {
            color: var(--text-sidebar-active);
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .logo-text span {
            color: rgba(255,255,255,0.4);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .staff-profile {
            padding: 18px 20px;
            border-bottom: 1px solid var(--divider);
            text-align: center;
        }
        .staff-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
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
            font-size: 0.95rem;
            font-weight: 600;
        }
        .staff-profile p {
            color: rgba(255,255,255,0.6);
            font-size: 0.72rem;
            margin-top: 2px;
        }
        .staff-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 10px;
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
            gap: 12px;
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 28px 32px 40px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: background 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 14px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-view-toggle {
            background: var(--maroon);
            color: white;
            padding: 9px 18px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
        }
        .btn-view-toggle:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
        }

        /* Filter Controls */
        .kanban-toolbar {
            background: var(--bg-card);
            padding: 14px 20px;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            flex: 1;
            max-width: 380px;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border-radius: 50px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.82rem;
            outline: none;
            transition: all 0.3s;
        }
        .search-box input:focus {
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-pill {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-pill.active, .filter-pill:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        /* Kanban Grid Board */
        .kanban-container {
            flex: 1;
            overflow-x: auto;
            padding-bottom: 16px;
        }
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(5, minmax(270px, 1fr));
            gap: 18px;
            align-items: start;
            min-width: 1350px;
        }
        @media(max-width: 1550px) {
            .kanban-board {
                min-width: 1300px;
            }
        }
        
        .kanban-column {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 16px;
            min-height: 600px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
        }
        .kanban-column.drag-over {
            border-color: var(--maroon-light);
            background: rgba(127, 3, 3, 0.04);
            box-shadow: var(--shadow-md);
            transform: scale(1.01);
        }

        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .column-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .column-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        .column-header h3 {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .column-count {
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
        }

        .card-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-height: 480px;
        }
        .card-list.empty {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .empty-placeholder {
            text-align: center;
            color: var(--text-muted);
            opacity: 0.5;
            padding: 30px 10px;
            font-size: 0.78rem;
        }
        .empty-placeholder i {
            font-size: 1.8rem;
            margin-bottom: 8px;
            display: block;
        }

        /* Kanban Card */
        .kanban-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 14px;
            cursor: grab;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .kanban-card:active {
            cursor: grabbing;
        }
        .kanban-card.dragging {
            opacity: 0.4;
            transform: scale(0.95);
            border-style: dashed;
        }
        .kanban-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--maroon-light);
            transform: translateY(-2px);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .tracking-id {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--maroon-light);
            background: rgba(127, 3, 3, 0.08);
            padding: 2px 8px;
            border-radius: 6px;
        }
        .type-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 50px;
            background: var(--badge-bg);
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        .applicant-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mini-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--maroon-light);
            color: white;
            font-size: 0.62rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mini-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-purpose {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-bottom: 10px;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--text-muted);
            border-top: 1px dashed var(--border-color);
            padding-top: 10px;
            margin-top: 8px;
        }
        .card-amount {
            font-weight: 800;
            color: #2E7D32;
            font-size: 0.82rem;
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        .card-btn {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.2s;
            cursor: pointer;
            flex-shrink: 0;
        }
        .card-btn:hover {
            background: var(--maroon);
            color: white;
        }
        .card-btn.card-btn-delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.08);
        }
        .card-btn.card-btn-delete:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
            transform: scale(1.08);
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            background: var(--midnight);
            color: white;
            padding: 13px 22px;
            border-radius: 14px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 420px;
        }
        .toast.toast-success {
            background: #1b5e20 !important;
            border-left: 5px solid #4caf50;
        }
        .toast.toast-error {
            background: #b71c1c !important;
            border-left: 5px solid #ef5350;
        }
        .toast i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        @keyframes toastIn {
            from { transform: translateY(20px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* Confirmation Modal */
        .kanban-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .kanban-modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .kanban-modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            transform: scale(0.92) translateY(12px);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            color: var(--text-primary);
        }

        .kanban-modal-backdrop.active .kanban-modal-box {
            transform: scale(1) translateY(0);
        }

        .kanban-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--badge-bg);
            position: relative;
        }

        .kanban-modal-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        [data-theme="dark"] .kanban-modal-icon-wrap {
            background: rgba(229, 115, 115, 0.15);
            color: #ff8a80;
        }

        .kanban-modal-title-group h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }

        .kanban-modal-title-group p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin: 2px 0 0 0;
        }

        .kanban-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            color: var(--text-muted);
            cursor: pointer;
            margin-left: auto;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .kanban-modal-close:hover {
            background: rgba(0, 0, 0, 0.08);
            color: var(--text-primary);
        }

        .kanban-modal-body {
            padding: 24px;
        }

        .modal-question-text {
            font-size: 0.95rem;
            line-height: 1.55;
            color: var(--text-primary);
            margin: 0 0 16px 0;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.3px;
        }

        .status-chip.from {
            background: rgba(255, 152, 0, 0.15);
            color: #b45309;
        }

        .status-chip.to {
            background: rgba(76, 175, 80, 0.15);
            color: #2e7d32;
        }

        [data-theme="dark"] .status-chip.from {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        [data-theme="dark"] .status-chip.to {
            background: rgba(76, 175, 80, 0.25);
            color: #81c784;
        }

        /* Card Summary Preview inside Modal */
        .modal-card-summary {
            background: var(--badge-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .modal-card-summary .summary-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .summary-id {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--maroon);
            background: rgba(127, 3, 3, 0.08);
            padding: 2px 8px;
            border-radius: 6px;
        }

        [data-theme="dark"] .summary-id {
            color: #ff8a80;
            background: rgba(229, 115, 115, 0.15);
        }

        .summary-type {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: capitalize;
            color: var(--text-secondary);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 2px 8px;
            border-radius: 50px;
        }

        .summary-applicant {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .summary-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--maroon);
            color: white;
            font-size: 0.78rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .summary-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .summary-details {
            display: flex;
            flex-direction: column;
        }

        .summary-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .summary-meta {
            font-size: 0.78rem;
            color: #2E7D32;
            font-weight: 700;
        }

        /* Status Transition Visual Flow */
        .status-transition-visual {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px dashed var(--border-color);
        }

        .transition-step {
            flex: 1;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .transition-arrow {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-audit-note {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.73rem;
            color: var(--text-muted);
            line-height: 1.45;
            padding: 10px 14px;
            background: rgba(33, 150, 243, 0.08);
            border: 1px solid rgba(33, 150, 243, 0.18);
            border-radius: 10px;
        }

        .modal-audit-note i {
            color: #2196F3;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .kanban-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            background: var(--badge-bg);
        }

        .btn-modal-cancel {
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-modal-cancel:hover {
            background: var(--badge-bg);
            border-color: var(--text-muted);
        }

        .btn-modal-confirm {
            padding: 9px 24px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            background: var(--maroon);
            color: #ffffff;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.28);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-modal-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.38);
        }

        .btn-modal-confirm:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none !important;
        }

        .card-just-moved {
            animation: cardHighlight 1.3s ease;
        }

        @keyframes cardHighlight {
            0% { transform: scale(1.03); box-shadow: 0 0 0 3px var(--maroon); }
            50% { box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.4); }
            100% { transform: scale(1); box-shadow: none; }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
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

        <!-- Elevated Big Card Container -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "AICS Requests Management";
            $banner_subtitle = "Review, assess, and manage Assistance to Individuals in Crisis Situation intake cases";
            $banner_icon = "fa-hand-holding-heart";
            $banner_actions = [
                ["label" => "Table View", "url" => "manage-aics.php", "icon" => "fa-table", "type" => "secondary"],
                ["label" => "Kanban Board", "url" => "kanban-aics.php", "icon" => "fa-columns", "type" => "primary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

        <!-- Kanban Toolbar / Filter -->
        <div class="kanban-toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="kanbanSearch" placeholder="Filter applicant name, ID or type...">
            </div>
            <div class="filter-pills" id="typePills">
                <button class="filter-pill active" data-type="all"><i class="fas fa-th-large"></i> All Types</button>
                <button class="filter-pill" data-type="medical"><i class="fas fa-file-medical"></i> Medical</button>
                <button class="filter-pill" data-type="funeral"><i class="fas fa-cross"></i> Funeral</button>
                <button class="filter-pill" data-type="food"><i class="fas fa-utensils"></i> Food</button>
                <button class="filter-pill" data-type="transportation"><i class="fas fa-bus"></i> Travel</button>
                <button class="filter-pill" data-type="cash_relief"><i class="fas fa-hand-holding-usd"></i> Cash</button>
            </div>
        </div>

        <!-- Kanban Board Grid -->
        <div class="kanban-container">
            <div class="kanban-board">
                <?php foreach($column_config as $status_key => $config): ?>
                <div class="kanban-column" id="col-<?php echo $status_key; ?>" data-status="<?php echo $status_key; ?>">
                    <div class="column-header">
                        <div class="column-header-title">
                            <div class="column-icon" style="background: <?php echo $config['bg']; ?>; color: <?php echo $config['color']; ?>;">
                                <i class="fas <?php echo $config['icon']; ?>"></i>
                            </div>
                            <h3><?php echo $config['title']; ?></h3>
                        </div>
                        <span class="column-count" id="count-<?php echo $status_key; ?>" style="background: <?php echo $config['color']; ?>;">
                            <?php echo count($columns[$status_key]); ?>
                        </span>
                    </div>

                    <div class="card-list" id="list-<?php echo $status_key; ?>">
                        <?php if(empty($columns[$status_key])): ?>
                            <div class="empty-placeholder">
                                <i class="fas fa-inbox"></i>
                                <span>Walang request</span>
                            </div>
                        <?php else: ?>
                            <?php foreach($columns[$status_key] as $req): 
                                $app_initials = strtoupper(substr($req['first_name'], 0, 1) . substr($req['last_name'], 0, 1));
                                $app_pic = !empty($req['profile_picture']) && file_exists(__DIR__ . '/../' . $req['profile_picture']) 
                                    ? '../' . $req['profile_picture'] 
                                    : '';
                                $type_clean = str_replace('_', ' ', $req['request_type']);
                                $app_full_name = trim($req['first_name'] . ' ' . $req['last_name']);
                                $tracking_code = 'AICS-' . str_pad($req['aics_id'], 6, '0', STR_PAD_LEFT);
                                $formatted_amount = '₱' . number_format($req['amount_requested']);
                            ?>
                                <div class="kanban-card" draggable="true" id="req-<?php echo $req['aics_id']; ?>" 
                                    data-id="<?php echo $req['aics_id']; ?>" 
                                    data-tracking="<?php echo htmlspecialchars($tracking_code); ?>"
                                    data-applicant="<?php echo htmlspecialchars($app_full_name); ?>"
                                    data-type="<?php echo strtolower($req['request_type']); ?>" 
                                    data-type-label="<?php echo htmlspecialchars(ucfirst($type_clean)); ?>"
                                    data-amount="<?php echo htmlspecialchars($formatted_amount); ?>"
                                    data-audit-flagged="<?php echo !empty($req['audit_flag']['is_flagged']) ? '1' : '0'; ?>"
                                    data-audit-days-ago="<?php echo $req['audit_flag']['days_ago'] ?? 0; ?>"
                                    data-audit-days-remaining="<?php echo $req['audit_flag']['days_remaining'] ?? 0; ?>"
                                    data-search="<?php echo strtolower($app_full_name . ' ' . $tracking_code . ' ' . $type_clean); ?>">
                                    <div class="card-top">
                                        <span class="tracking-id">AICS-<?php echo str_pad($req['aics_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        <div style="display: inline-flex; align-items: center; gap: 5px;">
                                            <?php if (!empty($req['audit_flag']['is_flagged'])): ?>
                                                <span class="kanban-audit-pill" title="Audit Notice: Received assistance <?php echo $req['audit_flag']['days_ago']; ?>d ago (<?php echo $req['audit_flag']['days_remaining']; ?>d cooling-off remaining)" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5; padding: 1px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; white-space: nowrap; line-height: 1.2;">
                                                    <i class="fas fa-shield-alt"></i> <?php echo $req['audit_flag']['days_ago']; ?>d Flag
                                                </span>
                                            <?php endif; ?>
                                            <span class="type-badge"><?php echo ucfirst($type_clean); ?></span>
                                        </div>
                                    </div>
                                    <div class="applicant-name">
                                        <div class="mini-avatar">
                                            <?php if(!empty($app_pic)): ?>
                                                <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo $app_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></span>
                                    </div>
                                    <p class="card-purpose"><?php echo htmlspecialchars($req['purpose'] ?? 'Assistance request'); ?></p>
                                    <div class="card-footer-meta">
                                        <span class="card-amount">₱<?php echo number_format($req['amount_requested']); ?></span>
                                        <div class="card-actions">
                                            <span style="font-size:0.68rem; margin-right:4px; opacity:0.7;"><i class="fas fa-calendar-alt"></i> <?php echo date('M d', strtotime($req['submission_date'])); ?></span>
                                            <a href="view-aics.php?id=<?php echo $req['aics_id']; ?>" class="card-btn" title="View details"><i class="fas fa-eye"></i></a>
                                            <button type="button" class="card-btn card-btn-delete" title="Delete application"
                                                data-id="<?php echo $req['aics_id']; ?>"
                                                data-tracking="<?php echo htmlspecialchars($tracking_code, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-applicant="<?php echo htmlspecialchars($app_full_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-type="<?php echo htmlspecialchars(ucfirst($type_clean), ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="handleDeleteClick(event, this)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<!-- Status Move Confirmation Modal -->
<div class="kanban-modal-backdrop" id="moveConfirmModal" aria-hidden="true" role="dialog" aria-labelledby="modalTitle">
    <div class="kanban-modal-box">
        <div class="kanban-modal-header">
            <div class="kanban-modal-icon-wrap">
                <i class="fas fa-arrows-split-up-and-left"></i>
            </div>
            <div class="kanban-modal-title-group">
                <h3 id="modalTitle">Confirm Status Change</h3>
                <p>Palayan-Kalinga Hub &bull; AICS Workflow Management</p>
            </div>
            <button type="button" class="kanban-modal-close" id="btnCloseMoveModal" aria-label="Close modal">&times;</button>
        </div>
        <div class="kanban-modal-body">
            <p class="modal-question-text" id="modalQuestionText">
                Are you sure you want to move this application from <span class="status-chip from" id="modalFromStatus">Submitted</span> to <span class="status-chip to" id="modalToStatus">Reviewing</span>?
            </p>

            <!-- Card Summary Preview -->
            <div class="modal-card-summary">
                <div class="summary-top">
                    <span class="summary-id" id="modalTrackingId">AICS-000000</span>
                    <span class="summary-type" id="modalAppType">Medical</span>
                </div>
                <div class="summary-applicant">
                    <div class="summary-avatar" id="modalAvatar">
                        JD
                    </div>
                    <div class="summary-details">
                        <span class="summary-name" id="modalApplicantName">Applicant Name</span>
                        <span class="summary-meta" id="modalAmount">₱0.00</span>
                    </div>
                </div>

                <!-- Visual Transition -->
                <div class="status-transition-visual">
                    <div class="transition-step" id="modalVisualFrom">
                        <i class="fas fa-clock"></i> <span>Submitted</span>
                    </div>
                    <div class="transition-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="transition-step" id="modalVisualTo">
                        <i class="fas fa-search"></i> <span>Reviewing</span>
                    </div>
                </div>
            <!-- 90-Day Policy Audit Warning Box (Shown when moving to Approved if flagged) -->
            <div id="modalAuditWarningBox" style="display: none; background: rgba(239, 68, 68, 0.08); border: 1.5px solid #EF4444; border-radius: 10px; padding: 12px 14px; margin-top: 14px; text-align: left;">
                <div style="color: #DC2626; font-weight: 700; font-size: 0.82rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-exclamation-triangle"></i> 90-Day Policy Audit Override Required
                </div>
                <p id="modalAuditWarningText" style="font-size: 0.76rem; color: var(--ref-text); margin: 0 0 8px 0; line-height: 1.45;">
                    Applicant received prior assistance within 90 days. Approval requires licensed Social Worker justification:
                </p>
                <label style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #DC2626; display: block; margin-bottom: 4px;">Social Worker Case Justification *</label>
                <textarea id="modalAuditJustification" rows="2" placeholder="e.g. Life-saving ongoing chemotherapy / dialysis / emergency house fire calamity exception assessed by Social Worker..." style="width: 100%; font-size: 0.78rem; padding: 6px 10px; border-radius: 6px; border: 1px solid var(--ref-border); background: var(--ref-canvas); color: var(--ref-text); box-sizing: border-box; resize: vertical;"></textarea>
            </div>

            <div class="modal-audit-note">
                <i class="fas fa-shield-alt"></i>
                <span>This action updates beneficiary tracking, logs the change to the staff audit trail, and sends automatic email/portal updates.</span>
            </div>
        </div>
        <div class="kanban-modal-footer">
            <button type="button" class="btn-modal-cancel" id="btnCancelMove">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-modal-confirm" id="btnConfirmMove">
                <i class="fas fa-check"></i> Confirm Move
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="kanban-modal-backdrop" id="deleteConfirmModal" aria-hidden="true" role="dialog" aria-labelledby="deleteModalTitle">
    <div class="kanban-modal-box">
        <div class="kanban-modal-header" style="background: rgba(220, 38, 38, 0.06); border-bottom: 1px solid rgba(220, 38, 38, 0.18);">
            <div class="kanban-modal-icon-wrap" style="background: rgba(220, 38, 38, 0.12); color: #dc2626;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="kanban-modal-title-group">
                <h3 id="deleteModalTitle" style="color: #dc2626;">Delete AICS Application</h3>
                <p>Palayan-Kalinga Hub &bull; Permanent Removal</p>
            </div>
            <button type="button" class="kanban-modal-close" id="btnCloseDeleteModal" aria-label="Close modal">&times;</button>
        </div>
        <div class="kanban-modal-body">
            <p class="modal-question-text" id="deleteModalQuestion">
                Are you sure you want to permanently delete application <span class="status-chip from" id="deleteModalTrackingId" style="background: rgba(220, 38, 38, 0.12); color: #dc2626;">AICS-000000</span> for <strong id="deleteModalApplicantName">Applicant</strong>?
            </p>

            <div class="modal-card-summary" style="margin-bottom: 14px; border-left: 4px solid #dc2626;">
                <div class="summary-top">
                    <span class="summary-id" id="deleteSummaryTracking" style="color: #dc2626; background: rgba(220, 38, 38, 0.1);">AICS-000000</span>
                    <span class="summary-type" id="deleteSummaryType">Medical</span>
                </div>
                <div style="font-size: 0.88rem; color: var(--text-primary); font-weight: 700; margin-top: 6px;" id="deleteSummaryApplicant">
                    Applicant Name
                </div>
            </div>

            <div class="modal-audit-note" style="background: rgba(220, 38, 38, 0.06); border-color: rgba(220, 38, 38, 0.2); color: var(--text-muted);">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i>
                <span><strong>Warning:</strong> This action cannot be undone. All attached documents, assessment notes, and request records will be permanently removed and logged in the audit trail.</span>
            </div>
        </div>
        <div class="kanban-modal-footer">
            <button type="button" class="btn-modal-cancel" id="btnCancelDelete">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-modal-confirm" id="btnConfirmDelete" style="background: #dc2626; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);">
                <i class="fas fa-trash-alt"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Status definitions matching column_config
    const statusConfig = {
        'submitted': {
            title: 'Submitted',
            icon: 'fa-clock',
            color: '#FF9800',
            bg: 'rgba(255, 152, 0, 0.14)',
            border: 'rgba(255, 152, 0, 0.35)'
        },
        'reviewing': {
            title: 'Reviewing',
            icon: 'fa-search',
            color: '#2196F3',
            bg: 'rgba(33, 150, 243, 0.14)',
            border: 'rgba(33, 150, 243, 0.35)'
        },
        'approved': {
            title: 'Approved',
            icon: 'fa-check-circle',
            color: '#4CAF50',
            bg: 'rgba(76, 175, 80, 0.14)',
            border: 'rgba(76, 175, 80, 0.35)'
        },
        'released': {
            title: 'Released',
            icon: 'fa-hand-holding-usd',
            color: '#9C27B0',
            bg: 'rgba(156, 39, 176, 0.14)',
            border: 'rgba(156, 39, 176, 0.35)'
        },
        'rejected': {
            title: 'Disapproved',
            icon: 'fa-times-circle',
            color: '#EF5350',
            bg: 'rgba(239, 83, 80, 0.14)',
            border: 'rgba(239, 83, 80, 0.35)'
        }
    };

    // Modal elements
    const moveConfirmModal = document.getElementById('moveConfirmModal');
    const btnCloseMoveModal = document.getElementById('btnCloseMoveModal');
    const btnCancelMove = document.getElementById('btnCancelMove');
    const btnConfirmMove = document.getElementById('btnConfirmMove');
    const modalFromStatus = document.getElementById('modalFromStatus');
    const modalToStatus = document.getElementById('modalToStatus');
    const modalTrackingId = document.getElementById('modalTrackingId');
    const modalAppType = document.getElementById('modalAppType');
    const modalApplicantName = document.getElementById('modalApplicantName');
    const modalAmount = document.getElementById('modalAmount');
    const modalAvatar = document.getElementById('modalAvatar');
    const modalVisualFrom = document.getElementById('modalVisualFrom');
    const modalVisualTo = document.getElementById('modalVisualTo');

    // Drag State
    let draggedCard = null;
    let sourceColumn = null;
    let pendingMove = null;
    let isSubmitting = false;

    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');

    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            sourceColumn = this.closest('.kanban-column');
            e.dataTransfer.setData('text/plain', this.id);
            e.dataTransfer.effectAllowed = 'move';
            this.classList.add('dragging');
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over'));
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        col.addEventListener('dragleave', function(e) {
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('drag-over');
            }
        });

        col.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.getElementById(cardId) || draggedCard;
            if (!card) return;

            const fromCol = card.closest('.kanban-column');
            const targetCol = this;
            const fromStatus = fromCol ? fromCol.getAttribute('data-status') : null;
            const targetStatus = targetCol.getAttribute('data-status');

            // If dropped in the same column, do nothing
            if (!fromStatus || fromStatus === targetStatus) {
                return;
            }

            // Open confirmation modal - DO NOT move the card yet
            openConfirmModal(card, fromCol, targetCol, fromStatus, targetStatus);
        });
    });

    function openConfirmModal(card, fromCol, targetCol, fromStatus, targetStatus) {
        pendingMove = {
            card: card,
            aicsId: card.getAttribute('data-id'),
            fromCol: fromCol,
            targetCol: targetCol,
            fromStatus: fromStatus,
            targetStatus: targetStatus,
            trackingId: card.getAttribute('data-tracking') || ('AICS-' + card.getAttribute('data-id')),
            applicantName: card.getAttribute('data-applicant') || 'Applicant',
            appType: card.getAttribute('data-type-label') || 'Assistance',
            amount: card.getAttribute('data-amount') || '₱0.00'
        };

        const fromCfg = statusConfig[fromStatus] || { title: fromStatus, icon: 'fa-circle', color: '#666', bg: '#eee', border: '#ccc' };
        const toCfg = statusConfig[targetStatus] || { title: targetStatus, icon: 'fa-circle', color: '#666', bg: '#eee', border: '#ccc' };

        // Populate question badges
        modalFromStatus.textContent = fromCfg.title;
        modalFromStatus.style.background = fromCfg.bg;
        modalFromStatus.style.color = fromCfg.color;

        modalToStatus.textContent = toCfg.title;
        modalToStatus.style.background = toCfg.bg;
        modalToStatus.style.color = toCfg.color;

        // Populate summary preview
        modalTrackingId.textContent = pendingMove.trackingId;
        modalAppType.textContent = pendingMove.appType;
        modalApplicantName.textContent = pendingMove.applicantName;
        modalAmount.textContent = pendingMove.amount;

        // Populate avatar
        const cardAvatar = card.querySelector('.mini-avatar');
        if (cardAvatar) {
            modalAvatar.innerHTML = cardAvatar.innerHTML;
        } else {
            modalAvatar.textContent = pendingMove.applicantName.charAt(0).toUpperCase();
        }

        // Populate visual transition flow
        modalVisualFrom.innerHTML = `<i class="fas ${fromCfg.icon}"></i> <span>${fromCfg.title}</span>`;
        modalVisualFrom.style.background = fromCfg.bg;
        modalVisualFrom.style.color = fromCfg.color;
        modalVisualFrom.style.border = `1px solid ${fromCfg.border}`;

        modalVisualTo.innerHTML = `<i class="fas ${toCfg.icon}"></i> <span>${toCfg.title}</span>`;
        modalVisualTo.style.background = toCfg.bg;
        modalVisualTo.style.color = toCfg.color;
        modalVisualTo.style.border = `1px solid ${toCfg.border}`;

        // Handle 90-Day Policy Audit Warning in Modal
        const isAuditFlagged = card.getAttribute('data-audit-flagged') === '1';
        const daysAgo = card.getAttribute('data-audit-days-ago') || '0';
        const daysRemaining = card.getAttribute('data-audit-days-remaining') || '0';

        const auditWarningBox = document.getElementById('modalAuditWarningBox');
        const auditText = document.getElementById('modalAuditWarningText');
        const auditInput = document.getElementById('modalAuditJustification');

        if (auditWarningBox) {
            if (targetStatus === 'approved' && isAuditFlagged) {
                auditWarningBox.style.display = 'block';
                if (auditText) {
                    auditText.innerHTML = `⚠️ <strong>COA/DSWD Audit Notice:</strong> This applicant received assistance <strong>${daysAgo} days ago</strong> (${daysRemaining} days remaining in 90-day cooling-off period). Approving this exception requires licensed Social Worker justification:`;
                }
                if (auditInput) {
                    auditInput.value = '';
                    setTimeout(() => auditInput.focus(), 150);
                }
            } else {
                auditWarningBox.style.display = 'none';
                if (auditInput) auditInput.value = '';
            }
        }

        // Reset button states
        btnConfirmMove.disabled = false;
        btnConfirmMove.innerHTML = '<i class="fas fa-check"></i> Confirm Move';
        btnCancelMove.disabled = false;

        // Show modal
        moveConfirmModal.classList.add('active');
        moveConfirmModal.setAttribute('aria-hidden', 'false');
        btnConfirmMove.focus();
    }

    function closeConfirmModal() {
        if (isSubmitting) return;
        moveConfirmModal.classList.remove('active');
        moveConfirmModal.setAttribute('aria-hidden', 'true');
        
        const auditWarningBox = document.getElementById('modalAuditWarningBox');
        const auditInput = document.getElementById('modalAuditJustification');
        if (auditWarningBox) auditWarningBox.style.display = 'none';
        if (auditInput) auditInput.value = '';

        pendingMove = null;
        draggedCard = null;
        sourceColumn = null;
    }

    // Modal close listeners
    btnCloseMoveModal.addEventListener('click', closeConfirmModal);
    btnCancelMove.addEventListener('click', closeConfirmModal);

    // Click outside to cancel
    moveConfirmModal.addEventListener('click', function(e) {
        if (e.target === moveConfirmModal) {
            closeConfirmModal();
        }
    });

    // Escape key to cancel
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && moveConfirmModal.classList.contains('active')) {
            closeConfirmModal();
        }
    });

    // Confirm Move Action
    btnConfirmMove.addEventListener('click', function() {
        if (!pendingMove || isSubmitting) return;

        const moveData = { ...pendingMove };
        const isCardAuditFlagged = moveData.card.getAttribute('data-audit-flagged') === '1';
        const auditInput = document.getElementById('modalAuditJustification');
        let auditOverride = '';

        if (moveData.targetStatus === 'approved' && isCardAuditFlagged && auditInput) {
            auditOverride = auditInput.value.trim();
            if (!auditOverride) {
                showToast('Please provide a Social Worker justification before approving this 90-day policy exception.', true);
                auditInput.focus();
                return;
            }
        }

        isSubmitting = true;
        btnConfirmMove.disabled = true;
        btnCancelMove.disabled = true;
        btnConfirmMove.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Moving...';

        fetch('update-aics-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                aics_id: moveData.aicsId,
                status: moveData.targetStatus,
                audit_override_reason: auditOverride
            })
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                const targetList = moveData.targetCol.querySelector('.card-list');
                
                // Remove empty placeholder from target list if present
                const emptyMsg = targetList.querySelector('.empty-placeholder');
                if (emptyMsg) {
                    emptyMsg.remove();
                }

                // Move the card to new column
                targetList.appendChild(moveData.card);

                // Add visual highlight effect on the card
                moveData.card.classList.add('card-just-moved');
                setTimeout(() => {
                    moveData.card.classList.remove('card-just-moved');
                }, 1400);

                // Update counters for all columns
                updateCounters();

                // Re-apply search/type filtering to reflect correct visibility
                filterCards();

                const targetTitle = (statusConfig[moveData.targetStatus] && statusConfig[moveData.targetStatus].title) || moveData.targetStatus.toUpperCase();
                showToast(`Application <strong>${moveData.trackingId}</strong> successfully moved to <strong>${targetTitle}</strong>!`, false);

                // Close modal
                isSubmitting = false;
                closeConfirmModal();
            } else {
                isSubmitting = false;
                const errorMsg = data.message || 'Unable to update status. Application remained in original column.';
                showToast(`Failed to move application: ${errorMsg}`, true);
                closeConfirmModal();
            }
        })
        .catch(err => {
            console.error("AJAX Error:", err);
            isSubmitting = false;
            showToast("Network error: Could not reach the server. Application status was not changed.", true);
            closeConfirmModal();
        });
    });

    function updateCounters() {
        columns.forEach(col => {
            const status = col.getAttribute('data-status');
            const list = col.querySelector('.card-list');
            const cardCount = list.querySelectorAll('.kanban-card').length;
            
            const countBadge = document.getElementById(`count-${status}`);
            if (countBadge) {
                countBadge.textContent = cardCount;
            }
            
            if (cardCount === 0 && !list.querySelector('.empty-placeholder')) {
                list.innerHTML = `
                    <div class="empty-placeholder">
                        <i class="fas fa-inbox"></i>
                        <span>Walang request</span>
                    </div>
                `;
            }
        });
    }

    function showToast(msg, isError = false) {
        if (window.showProcessToast) {
            window.showProcessToast(msg.replace(/<[^>]*>?/gm, ''), isError);
            return;
        }
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${isError ? 'toast-error' : 'toast-success'}`;
        toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> <div>${msg}</div>`;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(15px)';
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            setTimeout(() => toast.remove(), 4000);
        }, 4000);
    }

    // ========== DELETE MODAL WORKFLOW ==========
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const btnCloseDeleteModal = document.getElementById('btnCloseDeleteModal');
    const btnCancelDelete = document.getElementById('btnCancelDelete');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const deleteModalTrackingId = document.getElementById('deleteModalTrackingId');
    const deleteModalApplicantName = document.getElementById('deleteModalApplicantName');
    const deleteSummaryTracking = document.getElementById('deleteSummaryTracking');
    const deleteSummaryType = document.getElementById('deleteSummaryType');
    const deleteSummaryApplicant = document.getElementById('deleteSummaryApplicant');

    let pendingDeleteId = null;
    let pendingDeleteTracking = '';
    let isDeleting = false;

    window.handleDeleteClick = function(e, btn) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        const id = btn.getAttribute('data-id');
        const tracking = btn.getAttribute('data-tracking');
        const applicant = btn.getAttribute('data-applicant');
        const typeLabel = btn.getAttribute('data-type');
        window.openDeleteModal(e, id, tracking, applicant, typeLabel);
    };

    window.openDeleteModal = function(e, id, tracking, applicant, typeLabel) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        pendingDeleteId = id;
        pendingDeleteTracking = tracking;

        if (deleteModalTrackingId) deleteModalTrackingId.textContent = tracking;
        if (deleteModalApplicantName) deleteModalApplicantName.textContent = applicant;
        if (deleteSummaryTracking) deleteSummaryTracking.textContent = tracking;
        if (deleteSummaryType) deleteSummaryType.textContent = typeLabel || 'AICS';
        if (deleteSummaryApplicant) deleteSummaryApplicant.textContent = applicant;

        btnConfirmDelete.disabled = false;
        btnConfirmDelete.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
        btnCancelDelete.disabled = false;

        deleteConfirmModal.classList.add('active');
        deleteConfirmModal.setAttribute('aria-hidden', 'false');
        btnConfirmDelete.focus();
    };

    function closeDeleteModal() {
        if (isDeleting) return;
        deleteConfirmModal.classList.remove('active');
        deleteConfirmModal.setAttribute('aria-hidden', 'true');
        pendingDeleteId = null;
        pendingDeleteTracking = '';
    }

    if (btnCloseDeleteModal) btnCloseDeleteModal.addEventListener('click', closeDeleteModal);
    if (btnCancelDelete) btnCancelDelete.addEventListener('click', closeDeleteModal);

    deleteConfirmModal.addEventListener('click', function(e) {
        if (e.target === deleteConfirmModal) closeDeleteModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteConfirmModal.classList.contains('active')) {
            closeDeleteModal();
        }
    });

    btnConfirmDelete.addEventListener('click', function() {
        if (!pendingDeleteId || isDeleting) return;

        isDeleting = true;
        btnConfirmDelete.disabled = true;
        btnCancelDelete.disabled = true;
        btnConfirmDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        const targetId = pendingDeleteId;
        const targetTracking = pendingDeleteTracking;

        fetch('delete-aics-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ aics_id: targetId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById('req-' + targetId);
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.85)';
                    setTimeout(() => {
                        card.remove();
                        updateCounters();
                        filterCards();
                    }, 300);
                }
                showToast(`Application <strong>${targetTracking}</strong> has been deleted successfully!`, false);
                isDeleting = false;
                closeDeleteModal();
            } else {
                isDeleting = false;
                btnConfirmDelete.disabled = false;
                btnCancelDelete.disabled = false;
                btnConfirmDelete.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
                showToast(`Failed to delete application: ${data.message || 'Unknown error'}`, true);
            }
        })
        .catch(err => {
            console.error("Delete AJAX Error:", err);
            isDeleting = false;
            btnConfirmDelete.disabled = false;
            btnCancelDelete.disabled = false;
            btnConfirmDelete.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
            showToast("Network error: Could not reach the server.", true);
        });
    });

    // Client-side Live Filtering
    const searchInput = document.getElementById('kanbanSearch');
    const pills = document.querySelectorAll('.filter-pill');
    let activeType = 'all';

    function filterCards() {
        const query = (searchInput.value || '').toLowerCase().trim();
        const allCards = document.querySelectorAll('.kanban-card');
        
        allCards.forEach(card => {
            const cardSearch = card.getAttribute('data-search') || '';
            const cardType = card.getAttribute('data-type') || '';
            
            const matchesSearch = query === '' || cardSearch.includes(query);
            const matchesType = activeType === 'all' || cardType === activeType;
            
            if (matchesSearch && matchesType) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
    }

    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            pills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            activeType = this.getAttribute('data-type');
            filterCards();
        });
    });
});
</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

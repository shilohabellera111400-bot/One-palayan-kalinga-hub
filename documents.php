<?php
// documents.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

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

// Handle document upload
$upload_error = '';
$upload_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    $document_type = sanitize($_POST['document_type']);
    $appointment_id = !empty($_POST['appointment_id']) ? sanitize($_POST['appointment_id']) : null;
    $description = sanitize($_POST['description'] ?? '');
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploader = new FileUploader();
        
        try {
            $file_path = $uploader->upload($_FILES['document'], 'documents');
            
            $query = "INSERT INTO documents (user_id, appointment_id, document_type, document_path, description, verification_status) 
                      VALUES (:user_id, :appointment_id, :document_type, :path, :description, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':appointment_id', $appointment_id);
            $stmt->bindParam(':document_type', $document_type);
            $stmt->bindParam(':path', $file_path);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $upload_success = "Document uploaded successfully!";
            } else {
                $upload_error = "Failed to save document information.";
            }
        } catch (Exception $e) {
            $upload_error = $e->getMessage();
        }
    } else {
        $upload_error = "Please select a file to upload.";
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $document_id = sanitize($_GET['delete']);
    
    $check_query = "SELECT document_path FROM documents WHERE document_id = :id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $document_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $doc = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $uploader = new FileUploader();
        $uploader->deleteFile($doc['document_path']);
        
        $delete_query = "DELETE FROM documents WHERE document_id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $document_id);
        $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($delete_stmt->execute()) {
            $upload_success = "Document deleted successfully!";
        } else {
            $upload_error = "Failed to delete document.";
        }
    } else {
        $upload_error = "Document not found or access denied.";
    }
}

$query = "SELECT d.* FROM documents d WHERE d.user_id = :user_id ORDER BY d.upload_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$appointments_query = "SELECT a.appointment_id, s.service_name, a.appointment_date 
                       FROM appointments a JOIN services s ON a.service_id = s.service_id
                       WHERE a.user_id = :user_id ORDER BY a.appointment_date DESC";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':user_id', $_SESSION['user_id']);
$appointments_stmt->execute();
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>My Documents - One Palayan-Kalinga Hub</title>
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
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 16px 20px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .stat-card .stat-icon {
            font-size: 1.2rem;
            color: var(--maroon-light);
            margin-bottom: 4px;
        }

        /* ========== UPLOAD FORM ========== */
        .upload-section {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            display: none;
            transition: all 0.3s;
        }
        .upload-section.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .upload-section .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .upload-section .form-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .upload-section .form-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .form-group label .required {
            color: var(--color-danger);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
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
        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-cancel {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-cancel:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        /* ========== DOCUMENTS GRID ========== */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        .document-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .card-header {
            padding: 14px 18px;
            background: var(--badge-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header .doc-type {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header .doc-type i {
            color: var(--maroon-light);
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
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.verified {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }

        .card-body {
            padding: 16px 18px;
        }
        .card-body .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .card-body .detail-row i {
            color: var(--maroon-light);
            width: 18px;
            font-size: 0.8rem;
        }
        .card-body .detail-row .label {
            color: var(--text-muted);
            min-width: 80px;
        }
        .card-body .description-text {
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .card-footer {
            padding: 12px 18px;
            background: var(--badge-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 6px 16px;
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
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }
        .btn-delete {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            padding: 6px 16px;
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
        .btn-delete:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
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
        .empty-state .btn-upload {
            display: inline-flex;
            padding: 10px 24px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            align-items: center;
            gap: 8px;
        }
        .empty-state .btn-upload:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .documents-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .upload-section { padding: 18px 20px; }
            .form-actions { flex-direction: column; }
            .form-actions button { width: 100%; justify-content: center; }
            .documents-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .card-footer { flex-direction: column; }
            .btn-view, .btn-delete { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .upload-section { padding: 14px 16px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-folder-open"></i></div>
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
            <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
            <li><a href="aics.php"><i class="fas fa-hand-holding-heart"></i> Apply for AICS</a></li>
            <li><a href="aics-requests.php"><i class="fas fa-list-alt"></i> AICS Requests</a></li>
            <li><a href="housing-applications.php"><i class="fas fa-home"></i> Housing Applications</a></li>
            <li><a href="my-housing.php"><i class="fas fa-building"></i> My Housing</a></li>
            <li><a href="documents.php" class="active"><i class="fas fa-folder-open"></i> My Documents</a></li>
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
                <h1><i class="fas fa-file-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> My Documents</h1>
                <p>Upload and manage your important documents</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <button class="top-icon" onclick="toggleUploadForm()" title="Upload Document">
                    <i class="fas fa-upload"></i>
                </button>
                <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock" style="color: var(--color-warning);"></i></div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--color-success);"></i></div>
                <div class="stat-number"><?php echo $stats['verified'] ?? 0; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--color-danger);"></i></div>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Upload Form -->
        <div id="uploadForm" class="upload-section <?php echo isset($_GET['upload']) ? 'show' : ''; ?>">
            <div class="form-header">
                <i class="fas fa-upload"></i>
                <h2>Upload New Document</h2>
            </div>
            <?php if($upload_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $upload_error; ?>
                </div>
            <?php endif; ?>
            <?php if($upload_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $upload_success; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Document Type <span class="required">*</span></label>
                    <select name="document_type" required>
                        <option value="">Select document type</option>
                        <option value="valid_id">🪪 Valid ID</option>
                        <option value="barangay_certificate">📄 Barangay Certificate</option>
                        <option value="medical_certificate">🏥 Medical Certificate</option>
                        <option value="proof_of_income">💰 Proof of Income</option>
                        <option value="school_id">🎓 School ID</option>
                        <option value="enrollment_form">📋 Enrollment Form</option>
                        <option value="death_certificate">📜 Death Certificate</option>
                        <option value="other">📎 Other Document</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Add a brief description of this document..."></textarea>
                </div>
                <div class="form-group">
                    <label>Select File <span class="required">*</span></label>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small>Accepted formats: PDF, JPG, JPEG, PNG (Max 5MB)</small>
                </div>
                <div class="form-actions">
                    <button type="submit" name="upload_document" class="btn-submit">
                        <i class="fas fa-save"></i> Upload Document
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleUploadForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Documents Grid -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="documents-grid">
            <?php while($doc = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="document-card">
                <div class="card-header">
                    <span class="doc-type">
                        <i class="fas fa-file-alt"></i>
                        <?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?>
                    </span>
                    <span class="status-badge <?php echo $doc['verification_status']; ?>">
                        <i class="fas fa-<?php 
                            echo $doc['verification_status'] == 'pending' ? 'clock' : 
                                ($doc['verification_status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                        ?>"></i>
                        <?php echo ucfirst($doc['verification_status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <i class="fas fa-calendar"></i>
                        <span class="label">Uploaded:</span>
                        <span><?php echo date('M d, Y h:i A', strtotime($doc['upload_date'])); ?></span>
                    </div>
                    <?php if(!empty($doc['description'])): ?>
                    <div class="description-text">
                        <?php echo htmlspecialchars(substr($doc['description'], 0, 120)); ?>
                        <?php if(strlen($doc['description']) > 120): ?>...<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="<?php echo htmlspecialchars($doc['document_path']); ?>" target="_blank" class="btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php if($doc['verification_status'] == 'pending'): ?>
                    <a href="?delete=<?php echo $doc['document_id']; ?>" class="btn-delete" 
                       onclick="return confirm('Are you sure you want to delete this document? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-upload"></i>
            <h3>No Documents Found</h3>
            <p>You haven't uploaded any documents yet.</p>
            <button class="btn-upload" onclick="toggleUploadForm()">
                <i class="fas fa-upload"></i> Upload Your First Document
            </button>
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

// ========== UPLOAD FORM TOGGLE ==========
function toggleUploadForm() {
    const form = document.getElementById('uploadForm');
    form.classList.toggle('show');
    if (form.classList.contains('show')) {
        setTimeout(() => {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
}

// ========== AUTO-HIDE ALERTS ==========
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 500);
    });
}, 5000);
</script>
</body>
</html>
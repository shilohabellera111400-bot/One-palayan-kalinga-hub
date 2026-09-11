<?php
// staff/view-aics.php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get AICS ID from URL
$aics_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$aics_id) {
    redirect('manage-aics.php');
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
if(in_array('profile_picture', $user_columns)) {
    $user_fields .= ", u.profile_picture";
}

// Fetch main AICS request details
$query = "SELECT a.*, 
          $user_fields,
          CONCAT(u.first_name, ' ', u.last_name) as full_name
          FROM aics_requests a
          JOIN users u ON a.user_id = u.user_id
          WHERE a.aics_id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $aics_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    header('Location: manage-aics.php?error=Request not found');
    exit;
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch specific details based on request type
$specific_details = null;
$detail_table = '';
$detail_columns = [];

// Check which detail tables exist
$tables_check = $db->query("SHOW TABLES");
$existing_tables = [];
while($table = $tables_check->fetch(PDO::FETCH_NUM)) {
    $existing_tables[] = $table[0];
}

switch($request['request_type']) {
    case 'cash_relief':
        if(in_array('aics_cash_relief_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_cash_relief_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Cash Relief Details';
        }
        break;
        
    case 'educational':
        if(in_array('aics_educational_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_educational_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Educational Assistance Details';
        }
        break;
        
    case 'food':
        if(in_array('aics_food_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_food_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Food Assistance Details';
        }
        break;
        
    case 'funeral':
        if(in_array('aics_funeral_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_funeral_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Funeral Assistance Details';
        }
        break;
        
    case 'material':
        if(in_array('aics_material_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_material_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Material Assistance Details';
        }
        break;
        
    case 'medical':
        if(in_array('aics_medical_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_medical_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Medical Assistance Details';
        }
        break;
        
    case 'transportation':
        if(in_array('aics_transportation_details', $existing_tables)) {
            $detail_query = "SELECT * FROM aics_transportation_details WHERE aics_id = :id";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':id', $aics_id);
            $detail_stmt->execute();
            $specific_details = $detail_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_table = 'Transportation Assistance Details';
        }
        break;
}

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $comment_text = sanitize($_POST['comment_text'] ?? '');
    
    if (!empty($comment_text)) {
        $user_id = $_SESSION['user_id'];
        $attachment_path = null;
        
        // Handle optional attachment upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = $_FILES['attachment']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = uniqid('comment_', true) . '_' . time() . '.' . $file_ext;
                $upload_dir = __DIR__ . '/../uploads/aics/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $dest_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $attachment_path = 'uploads/aics/' . $new_filename;
                    
                    // Also record this as a supporting document for the application!
                    $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_name, document_path) 
                                  VALUES (:aics_id, 'supporting_document', :doc_name, :doc_path)";
                    $doc_stmt = $db->prepare($doc_query);
                    $doc_stmt->bindValue(':aics_id', $aics_id);
                    $doc_stmt->bindValue(':doc_name', 'Comment Attachment: ' . $file_name);
                    $doc_stmt->bindValue(':doc_path', $attachment_path);
                    $doc_stmt->execute();
                }
            }
        }
        
        // Insert comment
        $insert_query = "INSERT INTO aics_comments (aics_id, user_id, comment_text, attachment_path) 
                         VALUES (:aics_id, :user_id, :comment_text, :attachment_path)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindValue(':aics_id', $aics_id);
        $insert_stmt->bindValue(':user_id', $user_id);
        $insert_stmt->bindValue(':comment_text', $comment_text);
        $insert_stmt->bindValue(':attachment_path', $attachment_path);
        
        if ($insert_stmt->execute()) {
            // Also notify the applicant if the staff commented!
            if (isStaff() || isAdmin()) {
                $notif_msg = "A CSWDO staff left a comment on your AICS Application AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
                $notif_query = "INSERT INTO notifications (user_id, title, message) VALUES (:user_id, 'New Application Comment', :message)";
                $notif_stmt = $db->prepare($notif_query);
                $notif_stmt->bindValue(':user_id', $request['user_id']);
                $notif_stmt->bindValue(':message', $notif_msg);
                $notif_stmt->execute();
            }
            
            // Redirect to refresh and avoid form re-submission
            header("Location: view-aics.php?id=" . $aics_id . "&success=Comment posted!");
            exit;
        }
    }
}

// Fetch supporting documents
$docs_query = "SELECT * FROM aics_documents WHERE aics_id = :id ORDER BY upload_date DESC";
$docs_stmt = $db->prepare($docs_query);
$docs_stmt->bindParam(':id', $aics_id);
$docs_stmt->execute();
$documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch comments for this request
$comments_query = "SELECT c.*, u.first_name, u.last_name, u.role 
                   FROM aics_comments c 
                   JOIN users u ON c.user_id = u.user_id 
                   WHERE c.aics_id = :id 
                   ORDER BY c.created_at ASC";
$comments_stmt = $db->prepare($comments_query);
$comments_stmt->bindParam(':id', $aics_id);
$comments_stmt->execute();
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to clean document path
function cleanDocumentPath($path) {
    if (empty($path)) {
        return '';
    }
    
    $path = ltrim($path, '/');
    
    if (strpos($path, 'uploads/aics/') !== false) {
        $parts = explode('uploads/aics/', $path);
        $filename = end($parts);
    } elseif (strpos($path, 'aics/') !== false) {
        $parts = explode('aics/', $path);
        $filename = end($parts);
    } else {
        $filename = basename($path);
    }
    
    $filename = ltrim($filename, '/');
    return '../uploads/aics/' . $filename;
}

// Get staff who processed this request
$staff_info = [];
if ($request['reviewed_by']) {
    $staff_query = "SELECT first_name, last_name FROM staff WHERE user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $request['reviewed_by']);
    $staff_stmt->execute();
    $staff_info['reviewer'] = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

if ($request['approved_by']) {
    $staff_query = "SELECT first_name, last_name FROM staff WHERE user_id = :user_id";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->bindParam(':user_id', $request['approved_by']);
    $staff_stmt->execute();
    $staff_info['approver'] = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get status color and icon
function getStatusInfo($status) {
    $statuses = [
        'submitted' => ['color' => '#FF9800', 'icon' => 'fa-clock', 'text' => 'Submitted', 'bg' => 'rgba(255,152,0,0.12)'],
        'reviewing' => ['color' => '#2196F3', 'icon' => 'fa-search', 'text' => 'Under Review', 'bg' => 'rgba(33,150,243,0.12)'],
        'approved' => ['color' => '#4CAF50', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => 'rgba(76,175,80,0.12)'],
        'rejected' => ['color' => '#EF5350', 'icon' => 'fa-times-circle', 'text' => 'Rejected', 'bg' => 'rgba(244,67,54,0.12)'],
        'released' => ['color' => '#9C27B0', 'icon' => 'fa-gift', 'text' => 'Released', 'bg' => 'rgba(156,39,176,0.12)']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}

// Helper function to format field names for display
function formatFieldName($field) {
    $mapping = [
        'emergency_type' => 'Type of Emergency',
        'affected_members' => 'Affected Family Members',
        'incident_date' => 'Incident Date',
        'incident_report_no' => 'Incident Report Number',
        'police_report_no' => 'Police Report Number',
        'student_name' => 'Student Name',
        'school_name' => 'School Name',
        'grade_level' => 'Grade/Year Level',
        'school_year' => 'School Year',
        'tuition_fee' => 'Tuition Fee',
        'enrollment_status' => 'Enrollment Status',
        'household_size' => 'Household Size',
        'monthly_income' => 'Monthly Income',
        'special_needs' => 'Special Needs / Dietary Restrictions',
        'assistance_type' => 'Assistance Type',
        'deceased_name' => 'Name of Deceased',
        'relationship' => 'Relationship to Deceased',
        'funeral_home' => 'Funeral Home',
        'death_certificate_no' => 'Death Certificate Number',
        'funeral_contract_no' => 'Funeral Contract Number',
        'transfer_permit_no' => 'Transfer Permit Number',
        'client_type' => 'Client Type',
        'group_name' => 'Group/Organization Name',
        'number_of_members' => 'Number of Members',
        'materials_requested' => 'Materials Requested',
        'estimated_value' => 'Estimated Value',
        'hospital_name' => 'Hospital Name',
        'doctor_name' => 'Doctor Name',
        'destination' => 'Destination',
        'travel_date' => 'Travel Date',
        'number_of_persons' => 'Number of Persons',
        'estimated_fare' => 'Estimated Fare',
        'reason_type' => 'Reason for Travel',
        'purpose' => 'Purpose / Explanation',
        'food_pack_type' => 'Food Pack Type'
    ];
    return $mapping[$field] ?? ucwords(str_replace('_', ' ', $field));
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
$app_pic = !empty($request['profile_picture']) && file_exists(__DIR__ . '/../' . $request['profile_picture']) 
    ? '../' . $request['profile_picture'] 
    : '';
$app_initials = strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View AICS Request #<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?> - Staff</title>
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
            
            --bg-primary: #E8ECF2;
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
            --border-color: #C8D0DC;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 6px 24px rgba(15,65,74,0.12), 0 2px 8px rgba(0,0,0,0.07);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
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

        /* ========== TIMELINE STYLES ========== */
        .timeline {
            position: relative;
            padding-left: 32px;
            margin: 15px 0 5px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: var(--border-color);
            transition: background 0.3s ease;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 24px;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-icon {
            position: absolute;
            left: -32px;
            top: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            color: var(--text-muted);
            transition: all 0.3s ease;
            z-index: 2;
        }
        .timeline-item.success .timeline-icon {
            border-color: var(--color-success);
            color: var(--color-success);
            background: rgba(76, 175, 80, 0.08);
        }
        .timeline-item.active .timeline-icon {
            border-color: var(--color-warning);
            color: var(--color-warning);
            background: rgba(255, 152, 0, 0.08);
            animation: pulse-border 2s infinite;
        }
        .timeline-item.danger .timeline-icon {
            border-color: var(--color-danger);
            color: var(--color-danger);
            background: rgba(239, 83, 80, 0.08);
        }
        .timeline-content {
            padding-left: 8px;
        }
        .timeline-content h4 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .timeline-content p {
            margin: 3px 0 0;
            font-size: 0.76rem;
            color: var(--text-secondary);
        }
        .timeline-content .time {
            display: inline-block;
            margin-top: 4px;
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(255, 152, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
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

        /* ========== REQUEST HEADER ========== */
        .request-header {
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
        .request-header .left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .request-header .left .avatar {
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
        .request-header .left .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .request-header .left .avatar .initials {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-primary);
        }
        .request-info h1 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .request-info h1 i {
            color: var(--maroon-light);
        }
        .request-info .tracking {
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .request-info .tracking strong {
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
        .info-row .value .amount {
            color: var(--color-success);
            font-weight: 700;
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
        .email-preview {
            background: var(--badge-bg);
            padding: 12px 16px;
            border-radius: var(--radius-element);
            margin-top: 12px;
            border-left: 3px solid var(--maroon-light);
        }
        .email-preview h4 {
            font-size: 0.8rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .email-preview p {
            font-size: 0.75rem;
            color: var(--text-muted);
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
            .request-header { flex-direction: column; align-items: flex-start; }
            .request-header .left { width: 100%; }
            .info-row { flex-direction: column; }
            .info-row .label { width: 100%; margin-bottom: 4px; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-primary,
            .action-buttons .btn-secondary { width: 100%; justify-content: center; }
            .modal-content { width: 95%; }
            .modal-body .applicant-info { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .request-header { padding: 16px 20px; }
            .request-info h1 { font-size: 1rem; }
            .info-card { padding: 16px 18px; }
            .document-item { flex-direction: column; gap: 8px; }
            .document-item .btn-download { width: 100%; justify-content: center; }
        }
        @media print {
            .sidebar, .top-bar, .back-btn, .action-buttons, .theme-toggle { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
            .info-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
            .request-header { box-shadow: none; border: 1px solid #ddd; }
            .status-badge-large { border: 1px solid #ccc; }
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
                <h1><i class="fas fa-file-alt" style="color: var(--maroon-light); margin-right: 10px;"></i> AICS Request Details</h1>
                <p>View and manage assistance request</p>
            </div>
            <div class="top-bar-actions">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
                <a href="manage-aics.php" class="top-icon"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <a href="manage-aics.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to AICS Requests
        </a>

        <?php $statusInfo = getStatusInfo($request['status']); ?>
        
        <!-- Request Header -->
        <div class="request-header">
            <div class="left">
                <div class="avatar">
                    <?php if(!empty($app_pic)): ?>
                        <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Applicant">
                    <?php else: ?>
                        <span class="initials"><?php echo $app_initials; ?></span>
                    <?php endif; ?>
                </div>
                <div class="request-info">
                    <h1>
                        <i class="fas fa-tag"></i>
                        <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?> Assistance
                        <span style="font-size: 0.7rem; background: var(--badge-bg); padding: 2px 12px; border-radius: 50px; font-weight: 500; color: var(--text-muted);">
                            <?php echo ucfirst($request['request_type']); ?>
                        </span>
                    </h1>
                    <div class="tracking">
                        <strong>Tracking Number:</strong> AICS-<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?>
                        <span style="margin: 0 8px;">•</span>
                        <strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($request['submission_date'])); ?>
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
                    <span class="value"><span class="highlight"><?php echo htmlspecialchars($request['full_name']); ?></span></span>
                </div>
                <?php if(isset($request['middle_name']) && !empty($request['middle_name'])): ?>
                <div class="info-row">
                    <span class="label">Middle Name</span>
                    <span class="value"><?php echo htmlspecialchars($request['middle_name']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Contact Number</span>
                    <span class="value"><?php echo htmlspecialchars($request['contact_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($request['email']); ?></span>
                </div>
                <?php if(isset($request['street']) && !empty($request['street'])): ?>
                <div class="info-row">
                    <span class="label">Address</span>
                    <span class="value">
                        <?php 
                        $address = [];
                        if(!empty($request['street'])) $address[] = $request['street'];
                        if(!empty($request['barangay'])) $address[] = $request['barangay'];
                        if(!empty($request['city'])) $address[] = $request['city'];
                        if(!empty($request['province'])) $address[] = $request['province'];
                        echo htmlspecialchars(implode(', ', $address));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Request Summary -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-file-alt"></i>
                    <h3>Request Summary</h3>
                </div>
                <div class="info-row">
                    <span class="label">Request Type</span>
                    <span class="value"><?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Amount Requested</span>
                    <span class="value"><span class="amount">₱<?php echo number_format($request['amount_requested'], 2); ?></span></span>
                </div>
                <?php if($request['amount_approved'] > 0): ?>
                <div class="info-row">
                    <span class="label">Amount Approved</span>
                    <span class="value"><span class="amount">₱<?php echo number_format($request['amount_approved'], 2); ?></span></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label">Submission Date</span>
                    <span class="value"><?php echo date('F d, Y h:i A', strtotime($request['submission_date'])); ?></span>
                </div>
            </div>
            
            <!-- Specific Details based on request type -->
            <?php if($specific_details && !empty($specific_details)): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3><?php echo $detail_table; ?></h3>
                </div>
                <?php foreach($specific_details as $key => $value): ?>
                    <?php if($key != 'id' && $key != 'aics_id' && !empty($value) && $value !== null): ?>
                    <div class="info-row">
                        <span class="label"><?php echo formatFieldName($key); ?></span>
                        <span class="value">
                            <?php 
                            if($key == 'amount_requested' || $key == 'estimated_value' || $key == 'tuition_fee' || $key == 'monthly_income' || $key == 'estimated_fare') {
                                echo '₱' . number_format($value, 2);
                            } elseif($key == 'travel_date' || $key == 'incident_date') {
                                echo date('F d, Y', strtotime($value));
                            } else {
                                echo nl2br(htmlspecialchars($value));
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Processing Information & Timeline -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3>Application Timeline & Status</h3>
                </div>
                
                <div class="timeline">
                    <!-- Step 1: Submission -->
                    <div class="timeline-item success">
                        <div class="timeline-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Application Submitted</h4>
                            <p>Submitted successfully by the applicant.</p>
                            <span class="time"><?php echo date('F d, Y h:i A', strtotime($request['submission_date'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Step 2: Review -->
                    <?php if($request['reviewed_by'] && isset($staff_info['reviewer'])): ?>
                    <div class="timeline-item success">
                        <div class="timeline-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Under Review</h4>
                            <p>Reviewed by: <strong><?php echo htmlspecialchars($staff_info['reviewer']['first_name'] . ' ' . $staff_info['reviewer']['last_name']); ?></strong></p>
                            <span class="time"><?php echo date('F d, Y h:i A', strtotime($request['review_date'])); ?></span>
                        </div>
                    </div>
                    <?php elseif($request['status'] == 'submitted'): ?>
                    <div class="timeline-item active">
                        <div class="timeline-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Awaiting Review</h4>
                            <p>Pending staff assignment and document review.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Step 3: Approval / Rejection -->
                    <?php if($request['status'] == 'rejected'): ?>
                    <div class="timeline-item danger">
                        <div class="timeline-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Application Rejected</h4>
                            <p style="color: var(--color-danger); font-weight: 500;">Reason: <?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?></p>
                            <?php if($request['review_date']): ?>
                            <span class="time"><?php echo date('F d, Y h:i A', strtotime($request['review_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif($request['approved_by'] && isset($staff_info['approver'])): ?>
                    <div class="timeline-item success">
                        <div class="timeline-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Approved</h4>
                            <p>Approved by: <strong><?php echo htmlspecialchars($staff_info['approver']['first_name'] . ' ' . $staff_info['approver']['last_name']); ?></strong></p>
                            <?php if ($request['amount_approved'] > 0): ?>
                            <p>Approved Amount: <strong>₱<?php echo number_format($request['amount_approved'], 2); ?></strong></p>
                            <?php endif; ?>
                            <span class="time"><?php echo date('F d, Y h:i A', strtotime($request['approval_date'])); ?></span>
                        </div>
                    </div>
                    <?php elseif($request['status'] == 'reviewing'): ?>
                    <div class="timeline-item active">
                        <div class="timeline-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Processing Application</h4>
                            <p>Evaluating eligibility for financial assistance allocation.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Step 4: Released -->
                    <?php if($request['status'] == 'released' || $request['release_date']): ?>
                    <div class="timeline-item success">
                        <div class="timeline-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Funds Released</h4>
                            <p>Financial assistance has been released to the beneficiary.</p>
                            <span class="time"><?php echo date('F d, Y h:i A', strtotime($request['release_date'])); ?></span>
                        </div>
                    </div>
                    <?php elseif($request['status'] == 'approved'): ?>
                    <div class="timeline-item active">
                        <div class="timeline-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Awaiting Fund Release</h4>
                            <p>Approved funds are being prepared for release.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Supporting Documents -->
            <div class="info-card">
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
                        <p>No documents uploaded</p>
                    </div>
                <?php endif; ?>
        </div>

        <!-- Comments & Internal Notes -->
        <div class="info-card" style="margin-top: 24px; grid-column: 1 / -1;">
            <div class="card-header">
                <i class="fas fa-comments"></i>
                <h3>Application Comments & Chat Thread</h3>
            </div>
            
            <div class="comments-thread" style="max-height: 400px; overflow-y: auto; padding: 15px; background: var(--bg-primary); border-radius: var(--radius-element); margin-bottom: 20px; display: flex; flex-direction: column; gap: 14px; border: 1px solid var(--border-color);">
                <?php if (count($comments) > 0): ?>
                    <?php foreach ($comments as $comment): 
                        $is_staff = $comment['role'] === 'staff' || $comment['role'] === 'admin';
                        $bubble_align = $is_staff ? 'align-self: flex-end; background: rgba(127, 3, 3, 0.08); border-left: 3px solid var(--maroon);' : 'align-self: flex-start; background: var(--bg-card); border-left: 3px solid var(--midnight);';
                    ?>
                        <div class="comment-bubble" style="max-width: 75%; padding: 12px 16px; border-radius: 12px; box-shadow: var(--shadow-sm); <?php echo $bubble_align; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 5px; font-size: 0.75rem;">
                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?> (<?php echo ucfirst($comment['role']); ?>)</strong>
                                <span style="color: var(--text-muted);"><?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($comment['comment_text']); ?></div>
                            
                            <?php if (!empty($comment['attachment_path'])): 
                                $att_path = cleanDocumentPath($comment['attachment_path']);
                                $att_ext = pathinfo($comment['attachment_path'], PATHINFO_EXTENSION);
                                $att_is_img = in_array(strtolower($att_ext), ['jpg', 'jpeg', 'png', 'gif']);
                            ?>
                                <div class="comment-attachment" style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border-color);">
                                    <?php if ($att_is_img): ?>
                                        <a href="<?php echo $att_path; ?>" target="_blank">
                                            <img src="<?php echo $att_path; ?>" alt="Attachment" style="max-width: 100%; max-height: 150px; border-radius: 6px; box-shadow: var(--shadow-sm); margin-top: 5px; display: block;">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo $att_path; ?>" target="_blank" style="font-size: 0.78rem; font-weight: 600; color: var(--maroon-light); display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                                            <i class="fas fa-file-pdf"></i> View Attached PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px 0; color: var(--text-muted);">
                        <i class="fas fa-comments" style="font-size: 2.5rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p style="font-size: 0.85rem;">No comments posted yet. Start the conversation below!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <form action="view-aics.php?id=<?php echo $aics_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_comment">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 5px;">Post a Comment / Instructions</label>
                    <textarea name="comment_text" rows="3" placeholder="Write instructions, requests for clarifications, or updates here..." required style="width: 100%; padding: 12px; border-radius: var(--radius-element); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-family: inherit; resize: vertical; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--maroon-light)'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
                </div>
                
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
                    <div class="file-upload-group" style="display: flex; align-items: center; gap: 10px;">
                        <label for="comment_attachment" style="cursor: pointer; display: inline-flex; align-items: center; gap: 8px; padding: 8px 15px; border-radius: 30px; border: 1px solid var(--border-color); background: var(--bg-card); font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); transition: all 0.25s;">
                            <i class="fas fa-paperclip" style="color: var(--maroon-light);"></i> Attach File (Image/PDF)
                        </label>
                        <input type="file" id="comment_attachment" name="attachment" style="display: none;" onchange="document.getElementById('file_chosen_label').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                        <span id="file_chosen_label" style="font-size: 0.75rem; color: var(--text-muted);">No file chosen</span>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="manage-aics.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <?php if($request['status'] != 'released' && $request['status'] != 'rejected'): ?>
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
            <h2><i class="fas fa-edit"></i> Update Status</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="manage-aics.php">
            <div class="modal-body">
                <input type="hidden" name="aics_id" value="<?php echo $aics_id; ?>">
                
                <div class="applicant-info">
                    <span><strong>Applicant:</strong> <?php echo htmlspecialchars($request['full_name']); ?></span>
                    <span><strong>Tracking:</strong> AICS-<?php echo str_pad($aics_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleFields()">
                        <option value="reviewing">🔍 Under Review</option>
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
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Enter reason for rejection"></textarea>
                </div>
                
                <div class="email-preview" id="emailPreview" style="display: none;">
                    <h4><i class="fas fa-envelope"></i> Email Preview</h4>
                    <p id="emailMessage"></p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary">
                    <i class="fas fa-save"></i> Update & Notify
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
    document.getElementById('amount_field').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('emailPreview').style.display = 'none';
    document.getElementById('amount_approved').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'reviewing';
}

function toggleFields() {
    const status = document.getElementById('modal_status').value;
    const amountField = document.getElementById('amount_field');
    const remarksField = document.getElementById('remarks_field');
    const emailPreview = document.getElementById('emailPreview');
    const emailMessage = document.getElementById('emailMessage');
    
    amountField.style.display = 'none';
    remarksField.style.display = 'none';
    emailPreview.style.display = 'block';
    
    if (status === 'approved') {
        amountField.style.display = 'block';
        document.getElementById('amount_approved').required = true;
        emailMessage.textContent = '✅ Email will be sent notifying the applicant of APPROVAL.';
    } else if (status === 'rejected') {
        remarksField.style.display = 'block';
        document.getElementById('remarks').required = true;
        emailMessage.textContent = '❌ Email will be sent notifying the applicant of REJECTION.';
    } else if (status === 'reviewing') {
        emailMessage.textContent = '🔍 Email will be sent notifying the applicant that their request is UNDER REVIEW.';
    } else if (status === 'released') {
        emailMessage.textContent = '💰 Email will be sent notifying the applicant that their payment has been RELEASED.';
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

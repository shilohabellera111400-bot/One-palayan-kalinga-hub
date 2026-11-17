<?php
// view-aics.php - Resident-facing AICS application details view
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Redirect staff/admin to their dedicated canonical staff controller
if (isStaff() || isAdmin()) {
    $id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
    redirect('staff/view-aics.php' . ($id ? '?id=' . $id : ''));
}

$database = new Database();
$db = $database->getConnection();

$aics_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;
if (!$aics_id) {
    redirect('my-aics.php');
}

// Check what columns exist in users table dynamically
$check_columns = $db->query("SHOW COLUMNS FROM users");
$user_columns = [];
while($col = $check_columns->fetch(PDO::FETCH_ASSOC)) {
    $user_columns[] = $col['Field'];
}

// Build user fields dynamically
$user_fields = "u.first_name, u.last_name, u.email";
if(in_array('contact_number', $user_columns)) {
    $user_fields .= ", u.contact_number";
} elseif(in_array('phone', $user_columns)) {
    $user_fields .= ", u.phone as contact_number";
}
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

// Fetch request details strictly verifying user_id ownership
$query = "SELECT a.*, 
          $user_fields,
          CONCAT(u.first_name, ' ', u.last_name) as full_name,
          s.first_name as staff_first_name, s.last_name as staff_last_name, s.position as staff_position
          FROM aics_requests a
          JOIN users u ON a.user_id = u.user_id
          LEFT JOIN staff s ON a.approved_by = s.user_id
          WHERE a.aics_id = :id AND a.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $aics_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('my-aics.php?error=Application not found');
}

$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Check which detail tables exist
$tables_check = $db->query("SHOW TABLES");
$existing_tables = [];
while($row = $tables_check->fetch(PDO::FETCH_NUM)) {
    $existing_tables[] = $row[0];
}

// Fetch specific details based on request type
$specific_details = null;
$detail_title = '';

switch($request['request_type']) {
    case 'medical':
        if(in_array('aics_medical_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_medical_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Medical Assistance Information';
        }
        break;

    case 'funeral':
    case 'burial':
        if(in_array('aics_funeral_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_funeral_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Funeral / Burial Assistance Information';
        }
        break;

    case 'educational':
        if(in_array('aics_educational_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_educational_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Educational Assistance Information';
        }
        break;

    case 'transportation':
        if(in_array('aics_transportation_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_transportation_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Transportation Assistance Information';
        }
        break;

    case 'cash_relief':
        if(in_array('aics_cash_relief_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_cash_relief_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Cash Relief Information';
        }
        break;

    case 'food':
        if(in_array('aics_food_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_food_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Food Assistance Information';
        }
        break;

    case 'material':
        if(in_array('aics_material_details', $existing_tables)) {
            $det_stmt = $db->prepare("SELECT * FROM aics_material_details WHERE aics_id = :id");
            $det_stmt->bindParam(':id', $aics_id);
            $det_stmt->execute();
            $specific_details = $det_stmt->fetch(PDO::FETCH_ASSOC);
            $detail_title = 'Material Assistance Information';
        }
        break;
}

// Fetch documents specifically linked to this AICS request
$documents = [];
try {
    $aics_doc_stmt = $db->prepare("SELECT * FROM aics_documents WHERE aics_id = :id ORDER BY aics_doc_id DESC");
    $aics_doc_stmt->execute([':id' => $aics_id]);
    $documents = $aics_doc_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (empty($documents)) {
    try {
        $doc_query = "SELECT * FROM documents 
                      WHERE user_id = :user_id AND reference_id = :ref_id 
                      ORDER BY upload_date DESC";
        $doc_stmt = $db->prepare($doc_query);
        $doc_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $doc_stmt->bindParam(':ref_id', $aics_id);
        $doc_stmt->execute();
        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fallback for legacy documents
if (empty($documents)) {
    try {
        $legacy_doc_query = "SELECT * FROM documents 
                             WHERE user_id = :user_id 
                             AND (reference_id IS NULL OR reference_id = 0)
                             AND (document_path LIKE '%aics%' OR document_type LIKE '%Medical%' OR document_type LIKE '%Certificate%' OR document_type LIKE '%Hospital%')
                             ORDER BY upload_date DESC";
        $legacy_stmt = $db->prepare($legacy_doc_query);
        $legacy_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $legacy_stmt->execute();
        $documents = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Helper function to clean document path
function cleanDocumentPath($path) {
    if (empty($path)) return '';
    $path = str_replace('\\', '/', trim($path));
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }
    if (strpos($path, '/uploads/') === 0) {
        return ltrim($path, '/');
    }
    if (file_exists(__DIR__ . '/uploads/aics/' . basename($path))) {
        return 'uploads/aics/' . basename($path);
    }
    return 'uploads/' . basename($path);
}

// Status styling info
function getAicsStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#f39c12', 'icon' => 'fa-clock', 'text' => 'Pending Review', 'bg' => '#fff8e1'],
        'under_review' => ['color' => '#1976d2', 'icon' => 'fa-search', 'text' => 'Under Review', 'bg' => '#e3f2fd'],
        'approved' => ['color' => '#2e7d32', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => '#e8f5e9'],
        'rejected' => ['color' => '#c62828', 'icon' => 'fa-times-circle', 'text' => 'Disapproved', 'bg' => '#ffebee'],
        'disbursed' => ['color' => '#6a1b9a', 'icon' => 'fa-hand-holding-dollar', 'text' => 'Assistance Disbursed', 'bg' => '#f3e5f5']
    ];
    return $statuses[$status] ?? ['color' => '#616161', 'icon' => 'fa-info-circle', 'text' => ucfirst(str_replace('_', ' ', $status)), 'bg' => '#f5f5f5'];
}

$statusInfo = getAicsStatusInfo($request['status']);
$tracking_no = 'AICS-' . str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT);
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AICS Details - <?php echo $tracking_no; ?> - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        .breadcrumb {
            margin-bottom: 20px;
            color: var(--text-muted, #71818B);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .breadcrumb a {
            color: #7F0303;
            text-decoration: none;
            font-weight: 600;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .app-header {
            background: var(--card-bg, #ffffff);
            border-radius: var(--radius-card, 16px);
            padding: 24px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.06));
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .app-title h1 {
            color: var(--text-primary, #191D1E);
            margin: 0 0 6px 0;
            font-size: 1.45rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .tracking-chip {
            background: #003366;
            color: #ffffff;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 50px;
            font-family: monospace;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .category-chip {
            background: rgba(127, 3, 3, 0.1);
            color: #7F0303;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* 4-Step Progress Tracker */
        /* Enhanced Glowing Lifecycle Tracker */
        .stepper-card {
            background: var(--card-bg, #ffffff);
            border-radius: 20px;
            padding: 26px 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(127, 3, 3, 0.08), 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(127, 3, 3, 0.15);
            position: relative;
            overflow: hidden;
        }
        .stepper-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #7F0303, #B91C1C, #EF4444, #7F0303);
            background-size: 200% 100%;
            animation: gradient-flow 4s ease infinite;
        }
        @keyframes gradient-flow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .stepper-header {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary, #191D1E);
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .stepper-header .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stepper-header .header-left i {
            color: #7F0303;
            font-size: 1.15rem;
        }
        .stepper-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .pulse-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 10px #10B981;
            display: inline-block;
            animation: pulse-dot 1.8s infinite;
        }
        @keyframes pulse-dot {
            0% { transform: scale(0.9); opacity: 0.8; box-shadow: 0 0 4px #10B981; }
            50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 12px #10B981; }
            100% { transform: scale(0.9); opacity: 0.8; box-shadow: 0 0 4px #10B981; }
        }

        .stepper-wrap {
            position: relative;
            margin: 15px 0 10px;
        }
        .stepper-track-bg {
            position: absolute;
            top: 24px;
            left: 8%;
            right: 8%;
            height: 4px;
            background: #E2E8F0;
            border-radius: 10px;
            z-index: 1;
        }
        .stepper-track-fill {
            position: absolute;
            top: 24px;
            left: 8%;
            height: 4px;
            background: linear-gradient(90deg, #10B981, #7F0303);
            border-radius: 10px;
            z-index: 1;
            box-shadow: 0 0 14px rgba(127, 3, 3, 0.5);
            transition: width 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stepper-track-fill.completed {
            background: linear-gradient(90deg, #059669, #10B981);
            box-shadow: 0 0 14px rgba(16, 185, 129, 0.55);
        }
        .stepper-track-fill.rejected {
            background: linear-gradient(90deg, #DC2626, #991B1B);
            box-shadow: 0 0 14px rgba(220, 38, 38, 0.55);
        }

        .stepper {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }
        .step {
            position: relative;
            text-align: center;
            flex: 1;
            padding: 0 10px;
            transition: all 0.3s ease;
        }
        .step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #ffffff;
            border: 3px solid #CBD5E1;
            color: #64748B;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            font-size: 1.05rem;
            font-weight: 800;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 2;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .step.active .step-circle {
            background: linear-gradient(135deg, #7F0303 0%, #B91C1C 100%);
            border-color: #ffffff;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.22), 0 0 25px rgba(220, 38, 38, 0.7);
            transform: scale(1.08);
            animation: pulse-node-glow 2s infinite ease-in-out;
        }
        @keyframes pulse-node-glow {
            0%, 100% {
                box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.22), 0 0 18px rgba(185, 28, 28, 0.55);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(127, 3, 3, 0.1), 0 0 32px rgba(220, 38, 38, 0.85);
            }
        }
        .step.completed .step-circle {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            border-color: #ffffff;
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25), 0 0 16px rgba(16, 185, 129, 0.45);
        }
        .step.rejected .step-circle {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            border-color: #ffffff;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.25), 0 0 18px rgba(220, 38, 38, 0.55);
        }
        .step-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-secondary, #4A5568);
            margin-bottom: 5px;
            transition: color 0.3s;
        }
        .step.active .step-title {
            color: #7F0303;
            font-weight: 800;
        }
        .step.completed .step-title {
            color: #059669;
        }
        .step-info-badge {
            display: inline-block;
            font-size: 0.74rem;
            line-height: 1.35;
            color: var(--text-muted, #71818B);
            padding: 5px 12px;
            border-radius: 8px;
            background: var(--card-subtle, #F8FAFC);
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s;
            max-width: 95%;
        }
        .step.active .step-info-badge {
            background: rgba(127, 3, 3, 0.08);
            border-color: rgba(127, 3, 3, 0.25);
            color: #7F0303;
            font-weight: 700;
            box-shadow: 0 0 12px rgba(127, 3, 3, 0.15);
        }
        .step.completed .step-info-badge {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.25);
            color: #059669;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .stepper-track-bg, .stepper-track-fill { display: none; }
            .stepper { flex-direction: column; gap: 20px; align-items: flex-start; }
            .step { display: flex; align-items: center; gap: 14px; text-align: left; padding: 0; width: 100%; }
            .step-circle { margin: 0; flex-shrink: 0; }
            .info-grid { grid-template-columns: 1fr !important; gap: 16px; }
            .info-card { padding: 18px 16px; }
            .info-row { flex-direction: column; gap: 2px; }
            .info-label { width: 100%; }
            .documents-section { padding: 18px 16px; }
            .documents-list { grid-template-columns: 1fr; }
        }

        /* Detail Cards Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .info-card {
            background: var(--card-bg, #ffffff);
            border-radius: var(--radius-card, 16px);
            padding: 22px 26px;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.06));
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .info-card h3 {
            color: #7F0303;
            margin: 0 0 16px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            font-size: 0.9rem;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 160px;
            font-weight: 600;
            color: var(--text-muted, #71818B);
            flex-shrink: 0;
        }
        .info-value {
            color: var(--text-primary, #191D1E);
            font-weight: 500;
            flex-grow: 1;
            word-break: break-word;
        }

        /* Documents Section */
        .documents-section {
            background: var(--card-bg, #ffffff);
            border-radius: var(--radius-card, 16px);
            padding: 22px 26px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.06));
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .documents-section h3 {
            color: #7F0303;
            margin: 0 0 16px 0;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
        }
        .documents-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }
        .document-item {
            background: var(--card-subtle, #F8FAFC);
            border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .document-item:hover {
            border-color: #7F0303;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .doc-icon {
            font-size: 1.6rem;
            color: #7F0303;
            flex-shrink: 0;
        }
        .doc-info {
            flex-grow: 1;
            min-width: 0;
        }
        .doc-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary, #191D1E);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .doc-meta {
            font-size: 0.72rem;
            color: var(--text-muted, #71818B);
            margin-top: 2px;
        }
        .doc-action {
            color: #003366;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 6px;
            background: rgba(0, 51, 102, 0.08);
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .doc-action:hover {
            background: rgba(0, 51, 102, 0.16);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-primary-action {
            background: #7F0303;
            color: #ffffff;
        }
        .btn-primary-action:hover {
            background: #600202;
            transform: translateY(-1px);
        }
        .btn-secondary-action {
            background: var(--card-bg, #ffffff);
            color: var(--text-primary, #191D1E);
            border: 1px solid var(--border-color, #E2E8F0);
        }
        .btn-secondary-action:hover {
            background: #F8FAFC;
        }

        @media print {
            .portal-sidebar, .portal-header, .breadcrumb, .action-buttons, .theme-toggle {
                display: none !important;
            }
            .app-container, .main-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .stepper-card, .info-card, .documents-section {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }

        .btn-aics-dots,
        .aics-dots-menu {
            display: none !important;
        }

        @media (max-width: 768px) {
            .stepper { flex-direction: column; gap: 16px; align-items: flex-start; }
            .stepper::before { display: none; }
            .step { display: flex; align-items: center; gap: 14px; text-align: left; }
            .step-circle { margin: 0; }
            .info-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; gap: 4px; }
            .info-label { width: 100%; }

            /* Action Buttons Mobile (3-Dots Collapse) */
            .action-buttons {
                position: relative;
                justify-content: flex-start;
                gap: 8px;
            }
            .btn-secondary-action {
                display: none !important; /* Collapsed into 3 dots on mobile */
            }
            .btn-primary-action {
                padding: 7px 16px !important;
                font-size: 0.78rem !important;
            }
            .btn-aics-dots {
                display: inline-flex !important;
                width: 34px !important;
                height: 34px !important;
                border-radius: 50% !important;
                border: 1.5px solid var(--border-color) !important;
                background: var(--card-bg, #ffffff) !important;
                color: var(--text-primary) !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                font-size: 0.85rem !important;
            }
            .btn-aics-dots:hover {
                border-color: #7F0303 !important;
                color: #7F0303 !important;
            }
            .aics-dots-menu {
                display: none;
                position: absolute;
                bottom: calc(100% + 8px);
                left: 0;
                background: var(--card-bg, #ffffff);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.18);
                padding: 6px;
                min-width: 175px;
                z-index: 105;
                flex-direction: column;
                gap: 4px;
            }
            .aics-dots-menu.open {
                display: flex !important;
            }
            .aics-menu-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border-radius: 8px;
                background: transparent;
                border: none;
                color: var(--text-primary);
                font-size: 0.78rem;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                width: 100%;
                text-align: left;
            }
            .aics-menu-item:hover {
                background: rgba(127, 3, 3, 0.08);
                color: #7F0303;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Resident Portal Sidebar -->
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <!-- Main Content Canvas -->
        <div class="main-content">
            <!-- Unified Resident Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <a href="my-aics.php">My AICS Applications</a>
                <span>/</span>
                <span><?php echo $tracking_no; ?></span>
            </div>

            <!-- Application Header Card -->
            <div class="app-header">
                <div class="app-title">
                    <h1>
                        <i class="fas fa-hand-holding-heart" style="color: #7F0303;"></i>
                        <?php echo htmlspecialchars($request['full_name']); ?>
                        <span class="tracking-chip"><?php echo $tracking_no; ?></span>
                        <span class="category-chip"><?php echo htmlspecialchars($request['request_type']); ?></span>
                    </h1>
                    <div style="font-size: 0.85rem; color: var(--text-muted, #71818B); margin-top: 4px;">
                        Date Filed: <strong><?php 
                            $date_filed = $request['submission_date'] ?? $request['created_at'] ?? $request['application_date'] ?? null;
                            echo $date_filed ? date('F d, Y', strtotime($date_filed)) : 'N/A';
                        ?></strong>
                    </div>
                </div>
                <div>
                    <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>40;">
                        <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                        <?php echo $statusInfo['text']; ?>
                    </span>
                </div>
            </div>

            <!-- Progress Tracker (Enhanced Glowing 4-Stage Lifecycle Tracker) -->
            <div class="stepper-card">
                <div class="stepper-header">
                    <div class="header-left">
                        <i class="fas fa-route"></i>
                        <span>Application Lifecycle Tracker</span>
                    </div>
                    <span class="stepper-live-pill">
                        <span class="pulse-indicator"></span>
                        Live Status: <?php echo htmlspecialchars($statusInfo['text']); ?>
                    </span>
                </div>

                <?php
                    $st = strtolower($request['status'] ?? 'pending');
                    $is_rej = in_array($st, ['rejected', 'disapproved', 'declined']);
                    
                    // 4-Stage Lifecycle Tracker:
                    // 1: Document Review
                    // 2: Staff Interview ("Please proceed to office for staff interview")
                    // 3: SCSR & Budget ("Submission to City Budget Office")
                    // 4: Assistance Release ("Fund Disbursement")
                    $c_step = 1;
                    if (in_array($st, ['pending', 'submitted'])) $c_step = 1;
                    if (in_array($st, ['under_review', 'reviewing', 'for_review', 'interview', 'qualified'])) $c_step = 2;
                    if (in_array($st, ['approved', 'scheduled'])) $c_step = 3;
                    if (in_array($st, ['disbursed', 'released', 'completed', 'awarded'])) $c_step = 4;
                    if ($is_rej) $c_step = 1;

                    // Progress percentage for track bar
                    $line_pct = 12;
                    if ($c_step === 2) $line_pct = 42;
                    if ($c_step === 3) $line_pct = 74;
                    if ($c_step >= 4) $line_pct = 100;
                    if ($is_rej) $line_pct = 15;
                ?>

                <div class="stepper-wrap">
                    <div class="stepper-track-bg"></div>
                    <div class="stepper-track-fill <?php echo $is_rej ? 'rejected' : ($c_step >= 4 ? 'completed' : ''); ?>" style="width: <?php echo $line_pct; ?>%;"></div>

                    <div class="stepper">
                        <!-- Step 1: Document Review -->
                        <div class="step <?php echo $is_rej ? 'rejected' : ($c_step >= 1 ? ($c_step === 1 ? 'active' : 'completed') : ''); ?>">
                            <div class="step-circle">
                                <?php if ($is_rej): ?>
                                    <i class="fas fa-times"></i>
                                <?php elseif ($c_step > 1): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    1
                                <?php endif; ?>
                            </div>
                            <div class="step-title">1. Document Review</div>
                            <span class="step-info-badge">Initial Document Screening</span>
                        </div>

                        <!-- Step 2: Staff Interview -->
                        <div class="step <?php echo $c_step >= 2 ? ($c_step === 2 ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle">
                                <?php echo $c_step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?>
                            </div>
                            <div class="step-title">2. Staff Interview</div>
                            <span class="step-info-badge"><i class="fas fa-person-walking"></i> Please proceed to office for staff interview</span>
                        </div>

                        <!-- Step 3: SCSR & Budget -->
                        <div class="step <?php echo $c_step >= 3 ? ($c_step === 3 ? 'active' : 'completed') : ''; ?>">
                            <div class="step-circle">
                                <?php echo $c_step > 3 ? '<i class="fas fa-check"></i>' : '3'; ?>
                            </div>
                            <div class="step-title">3. SCSR &amp; Budget</div>
                            <span class="step-info-badge"><i class="fas fa-landmark"></i> Submission to City Budget Office</span>
                        </div>

                        <!-- Step 4: Assistance Release -->
                        <div class="step <?php echo $c_step >= 4 ? 'completed' : ''; ?>">
                            <div class="step-circle">
                                <?php echo $c_step >= 4 ? '<i class="fas fa-check"></i>' : '4'; ?>
                            </div>
                            <div class="step-title">4. Assistance Release</div>
                            <span class="step-info-badge"><i class="fas fa-hand-holding-dollar"></i> Fund Disbursement</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Cards Grid -->
            <div class="info-grid">
                <!-- Beneficiary Information -->
                <div class="info-card">
                    <h3><i class="fas fa-user-circle"></i> Beneficiary Information</h3>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($request['full_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($request['contact_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($request['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Barangay</span>
                        <span class="info-value"><?php echo htmlspecialchars($request['barangay'] ?? 'Palayan City'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Complete Address</span>
                        <span class="info-value">
                            <?php 
                                $addr_parts = array_filter([$request['street'] ?? '', $request['barangay'] ?? '', $request['city'] ?? '', $request['province'] ?? '']);
                                echo htmlspecialchars(implode(', ', $addr_parts) ?: 'Palayan City, Nueva Ecija');
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Assistance Request Overview -->
                <div class="info-card">
                    <h3><i class="fas fa-hand-holding-dollar"></i> Assistance Overview</h3>
                    <div class="info-row">
                        <span class="info-label">Tracking Number</span>
                        <span class="info-value"><strong style="color:#003366;"><?php echo $tracking_no; ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Assistance Type</span>
                        <span class="info-value" style="text-transform: capitalize; font-weight:700; color:#7F0303;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $request['request_type'])); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount Requested</span>
                        <span class="info-value">
                            ₱<?php echo number_format($request['amount_requested'] ?? 0, 2); ?>
                        </span>
                    </div>
                    <?php if (isset($request['amount_approved']) && $request['amount_approved'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Amount Approved</span>
                        <span class="info-value" style="color: #2e7d32; font-weight: 700;">
                            ₱<?php echo number_format($request['amount_approved'], 2); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Date Submitted</span>
                        <span class="info-value"><?php 
                            $date_sub = $request['submission_date'] ?? $request['created_at'] ?? null;
                            echo $date_sub ? date('F d, Y h:i A', strtotime($date_sub)) : 'N/A';
                        ?></span>
                    </div>
                    <?php 
                    $disp_purpose = !empty($request['purpose']) ? $request['purpose'] : ($specific_details['purpose'] ?? '');
                    if (!empty($disp_purpose)): 
                    ?>
                    <div class="info-row">
                        <span class="info-label">Purpose / Need</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($disp_purpose)); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Category-Specific Information -->
                <?php if ($specific_details): ?>
                <div class="info-card">
                    <h3><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($detail_title); ?></h3>
                    <?php foreach ($specific_details as $col => $val): ?>
                        <?php 
                            if (in_array($col, ['id', 'aics_id', 'cash_relief_id', 'medical_id', 'funeral_id', 'educational_id', 'transportation_id', 'food_id', 'material_id', 'created_at', 'updated_at'])) continue;
                            if ($val === '' || $val === null) continue;
                            // If purpose is already shown above, skip repeating it here unless desired
                            if ($col === 'purpose' && !empty($disp_purpose)) continue;
                            $label = ucwords(str_replace(['_', 'id', 'no'], [' ', 'ID', 'No.'], $col));
                        ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo htmlspecialchars($label); ?></span>
                            <span class="info-value">
                                <?php 
                                    if (strpos($col, 'amount') !== false || strpos($col, 'cost') !== false || strpos($col, 'fee') !== false || strpos($col, 'fare') !== false || strpos($col, 'value') !== false || strpos($col, 'income') !== false) {
                                        echo '₱' . number_format((float)$val, 2);
                                    } elseif (strpos($col, 'date') !== false) {
                                        echo date('F d, Y', strtotime($val));
                                    } else {
                                        echo nl2br(htmlspecialchars(ucfirst(str_replace('_', ' ', $val))));
                                    }
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Evaluation & Approval Status -->
                <div class="info-card">
                    <h3><i class="fas fa-stamp"></i> CSWDO Assessment Status</h3>
                    <div class="info-row">
                        <span class="info-label">Current Status</span>
                        <span class="info-value">
                            <span class="status-badge-large" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; padding: 4px 12px; font-size: 0.8rem;">
                                <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                <?php echo $statusInfo['text']; ?>
                            </span>
                        </span>
                    </div>
                    <?php if (!empty($request['staff_first_name'])): ?>
                    <div class="info-row">
                        <span class="info-label">Assigned Caseworker</span>
                        <span class="info-value"><?php echo htmlspecialchars($request['staff_first_name'] . ' ' . $request['staff_last_name']); ?> (<?php echo htmlspecialchars($request['staff_position'] ?? 'CSWDO Staff'); ?>)</span>
                    </div>
                    <?php endif; ?>
                    <?php 
                    $decision_date = $request['approval_date'] ?? $request['approved_at'] ?? $request['review_date'] ?? null;
                    if (!empty($decision_date)): 
                    ?>
                    <div class="info-row">
                        <span class="info-label">Decision Date</span>
                        <span class="info-value"><?php echo date('F d, Y h:i A', strtotime($decision_date)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['remarks'])): ?>
                    <div class="info-row">
                        <span class="info-label">Caseworker Remarks</span>
                        <span class="info-value" style="color: <?php echo $request['status'] === 'rejected' ? '#c62828' : 'inherit'; ?>;">
                            <?php echo nl2br(htmlspecialchars($request['remarks'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Uploaded Documents Section -->
            <div class="documents-section">
                <h3><i class="fas fa-folder-open"></i> Uploaded Supporting Documents</h3>
                <?php if (!empty($documents)): ?>
                <div class="documents-list">
                    <?php foreach ($documents as $doc): ?>
                    <?php 
                        $doc_path = cleanDocumentPath($doc['document_path'] ?? '');
                        $ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                        $is_img = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                        $is_pdf = ($ext === 'pdf');
                        $icon = $is_img ? 'fa-file-image' : ($is_pdf ? 'fa-file-pdf' : 'fa-file-lines');
                        $doc_title = !empty($doc['document_name']) ? $doc['document_name'] : (!empty($doc['document_type']) ? ucwords(str_replace('_', ' ', $doc['document_type'])) : basename($doc_path));
                    ?>
                    <div class="document-item">
                        <i class="fas <?php echo $icon; ?> doc-icon"></i>
                        <div class="doc-info">
                            <div class="doc-name"><?php echo htmlspecialchars($doc_title); ?></div>
                            <div class="doc-meta">Uploaded: <?php echo !empty($doc['upload_date']) ? date('M d, Y h:i A', strtotime($doc['upload_date'])) : 'On File'; ?></div>
                        </div>
                        <?php if (!empty($doc_path) && file_exists(__DIR__ . '/' . $doc_path)): ?>
                            <a href="<?php echo htmlspecialchars($doc_path); ?>" target="_blank" class="doc-action">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php else: ?>
                            <span style="font-size: 0.72rem; color: #999;">On File</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted, #71818B); font-size: 0.9rem;">
                    <i class="fas fa-file-circle-question" style="font-size: 2rem; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                    No uploaded document attachments found for this request.
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="my-aics.php" class="btn-action btn-secondary-action">
                    <i class="fas fa-arrow-left"></i> Back to My Applications
                </a>
                <a href="track-aics.php?tracking=<?php echo urlencode($tracking_no); ?>" class="btn-action btn-secondary-action">
                    <i class="fas fa-location-crosshairs"></i> Live Tracker
                </a>
                <button type="button" onclick="window.print()" class="btn-action btn-primary-action">
                    <i class="fas fa-print"></i> Print Details
                </button>
                <!-- 3-Dots for mobile -->
                <button type="button" class="btn-aics-dots" id="aicsDotsBtn" onclick="toggleAicsMenu(event)" aria-label="More options">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="aics-dots-menu" id="aicsDotsMenu">
                    <a href="track-aics.php?tracking=<?php echo urlencode($tracking_no); ?>" class="aics-menu-item">
                        <i class="fas fa-location-crosshairs"></i> Live Tracker
                    </a>
                    <a href="my-aics.php" class="aics-menu-item">
                        <i class="fas fa-arrow-left"></i> My Applications
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
    <script src="js/main.js"></script>
    <script>
    function toggleAicsMenu(e) {
        if (e) e.stopPropagation();
        const m = document.getElementById('aicsDotsMenu');
        if (m) m.classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const m = document.getElementById('aicsDotsMenu');
        const b = document.getElementById('aicsDotsBtn');
        if (m && b && !m.contains(e.target) && !b.contains(e.target)) {
            m.classList.remove('open');
        }
    });
    </script>
</body>
</html>
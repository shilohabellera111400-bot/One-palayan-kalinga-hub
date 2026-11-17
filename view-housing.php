<?php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Get housing application ID from URL
$application_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$application_id) {
    // If no specific ID provided, automatically find the resident's most recent housing application
    $find_stmt = $db->prepare("SELECT beneficiary_id FROM housing_beneficiaries WHERE user_id = :uid ORDER BY application_date DESC, beneficiary_id DESC LIMIT 1");
    $find_stmt->execute([':uid' => $_SESSION['user_id']]);
    $latest = $find_stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest && !empty($latest['beneficiary_id'])) {
        $application_id = $latest['beneficiary_id'];
    } else {
        redirect('my-housing.php');
    }
}

$portal_current_page = 'view-housing.php';

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

// Fetch housing application details for the logged-in user
$query = "SELECT h.*, 
          p.program_name, p.location, p.description as program_description, p.total_units,
          $user_fields,
          s.first_name as staff_first_name, s.last_name as staff_last_name, s.position as staff_position
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          LEFT JOIN staff s ON h.approved_by = s.user_id
          WHERE h.beneficiary_id = :id AND h.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $application_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('my-housing.php?error=Application not found');
}

$application = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch documents specifically linked to this housing application
$doc_query = "SELECT * FROM documents 
              WHERE user_id = :user_id AND reference_id = :ref_id 
              ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':user_id', $_SESSION['user_id']);
$doc_stmt->bindParam(':ref_id', $application_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback for legacy applications created before reference_id was set
if (empty($documents)) {
    $legacy_doc_query = "SELECT * FROM documents 
                         WHERE user_id = :user_id 
                         AND (reference_id IS NULL OR reference_id = 0)
                         AND (document_path LIKE '%housing%' 
                              OR document_type IN ('Letter of Intent', 'Proof of Land Ownership', 'Affidavit of Consent', 'House / Lot Photo', 'Tax Declaration', 'Lot Sketch Plan', 'Barangay Clearance', 'Valid ID', 'Proof of Billing', 'Additional Document'))
                         ORDER BY upload_date DESC";
    $legacy_stmt = $db->prepare($legacy_doc_query);
    $legacy_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $legacy_stmt->execute();
    $documents = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to clean document path preserving subfolders (e.g. uploads/housing/...)
function cleanDocumentPath($path) {
    if (empty($path)) {
        return '';
    }
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (file_exists(__DIR__ . '/' . $clean)) {
        return $clean;
    }
    if (strpos($clean, 'uploads/') !== 0 && file_exists(__DIR__ . '/uploads/' . $clean)) {
        return 'uploads/' . $clean;
    }
    if (strpos($clean, 'uploads/housing/') !== 0 && file_exists(__DIR__ . '/uploads/housing/' . basename($clean))) {
        return 'uploads/housing/' . basename($clean);
    }
    return $clean;
}

// Standardized Housing Tracking Number Helper
function getHousingTrackingNumber($beneficiary_id, $application_date = null) {
    $year = $application_date ? date('Y', strtotime($application_date)) : date('Y');
    return 'HSG-' . $year . '-' . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);
}

$tracking_number = getHousingTrackingNumber($application_id, $application['application_date'] ?? null);

// Fetch linked appointment if scheduled
$linked_appt_query = "SELECT appointment_id, reference_number, appointment_date, appointment_time, window_assignment, status, service_type, purpose 
                      FROM appointments 
                      WHERE user_id = :uid 
                        AND (purpose LIKE :tno OR notes LIKE :tno_note) 
                        AND status NOT IN ('cancelled', 'rejected') 
                      ORDER BY appointment_id DESC LIMIT 1";
$linked_appt_stmt = $db->prepare($linked_appt_query);
$linked_appt_stmt->execute([
    ':uid' => $application['user_id'],
    ':tno' => '%' . $tracking_number . '%',
    ':tno_note' => '%' . $tracking_number . '%'
]);
$linked_appointment = $linked_appt_stmt->fetch(PDO::FETCH_ASSOC);

// Get status color and icon
function getHousingStatusInfo($status) {
    $statuses = [
        'pending' => ['color' => '#ff9800', 'icon' => 'fa-clock', 'text' => 'Pending', 'bg' => '#fff3e0'],
        'qualified' => ['color' => '#2196F3', 'icon' => 'fa-search', 'text' => 'Qualified', 'bg' => '#e3f2fd'],
        'approved' => ['color' => '#4CAF50', 'icon' => 'fa-check-circle', 'text' => 'Approved', 'bg' => '#e8f5e9'],
        'rejected' => ['color' => '#f44336', 'icon' => 'fa-times-circle', 'text' => 'Disapproved', 'bg' => '#ffebee'],
        'released' => ['color' => '#9C27B0', 'icon' => 'fa-gift', 'text' => 'Released', 'bg' => '#f3e5f5']
    ];
    return $statuses[$status] ?? ['color' => '#999', 'icon' => 'fa-question-circle', 'text' => ucfirst($status), 'bg' => '#f5f5f5'];
}

// Get user details for profile picture
$user_info_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_info_stmt = $db->prepare($user_info_query);
$user_info_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_info_stmt->execute();
$user_p = $user_info_stmt->fetch(PDO::FETCH_ASSOC);

// Profile picture path
$profile_picture = $user_p['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture
    : '';

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check if dark mode is enabled
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Housing Application - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        :root {
            --ease-spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        .view-housing-wrapper {
            max-width: 1240px;
            margin: 0 auto;
            padding: 16px 20px 100px;
        }

        /* Modern Breadcrumb Bar */
        .case-breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .case-breadcrumb-nav a {
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            font-weight: 500;
        }
        .case-breadcrumb-nav a:hover {
            color: var(--maroon);
        }
        .case-breadcrumb-nav .sep {
            font-size: 0.65rem;
            opacity: 0.45;
        }
        .case-breadcrumb-nav .curr {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Executive Hero Showcase Card */
        .case-hero-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, #E2E8F0);
            border-radius: 20px;
            padding: 28px 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            position: relative;
            overflow: hidden;
        }
        .case-hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--maroon) 0%, #a01515 50%, #0F414A 100%);
        }
        .case-hero-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            flex-wrap: wrap;
        }
        .case-hero-left {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            flex: 1;
            min-width: 320px;
        }
        .case-hero-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.1) 0%, rgba(127, 3, 3, 0.04) 100%);
            border: 1.5px solid rgba(127, 3, 3, 0.18);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.08);
        }
        .case-hero-info {
            flex: 1;
        }
        .case-hero-info h1 {
            margin: 0 0 6px;
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            line-height: 1.25;
        }
        .case-hero-info p {
            margin: 0 0 14px;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.45;
        }
        .case-chips-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .tracking-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(127, 3, 3, 0.08);
            border: 1.5px solid rgba(127, 3, 3, 0.22);
            color: var(--maroon);
            padding: 5px 12px;
            border-radius: 30px;
            font-family: 'Courier Prime', monospace, monospace;
            font-weight: 800;
            font-size: 0.88rem;
            letter-spacing: 0.5px;
        }
        .btn-copy-tracking {
            background: none;
            border: none;
            color: var(--maroon);
            cursor: pointer;
            padding: 2px;
            font-size: 0.8rem;
            opacity: 0.8;
            transition: opacity 0.2s, transform 0.2s;
            display: inline-flex;
            align-items: center;
        }
        .btn-copy-tracking:hover {
            opacity: 1;
            transform: scale(1.15);
        }
        .type-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .type-chip.insite {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.25);
        }
        .type-chip.relocation {
            background: rgba(15, 65, 74, 0.1);
            color: #0F414A;
            border: 1px solid rgba(15, 65, 74, 0.25);
        }
        .meta-chip {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .case-hero-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 14px;
        }
        @media (max-width: 768px) {
            .case-hero-right {
                align-items: flex-start;
                width: 100%;
            }
        }
        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
            box-shadow: 0 0 0 0 currentColor;
            animation: pulseEffect 2s infinite;
        }
        @keyframes pulseEffect {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(255, 152, 0, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
        }
        .hero-action-btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-hero-primary {
            background: var(--maroon);
            color: white;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s var(--ease-spring);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
            border: none;
        }
        .btn-hero-primary:hover {
            background: #9b0f0f;
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.35);
        }
        .btn-hero-secondary {
            background: var(--bg-primary, #F8FAFC);
            border: 1.5px solid var(--border-color, #E2E8F0);
            color: var(--text-primary);
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
        }
        .btn-hero-secondary:hover {
            border-color: var(--maroon);
            color: var(--maroon);
            transform: translateY(-2px);
        }

        /* 5-Stage Stepper Roadmap */
        .stepper-roadmap-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, #E2E8F0);
            border-radius: 20px;
            padding: 26px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
        }
        .stepper-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            flex-wrap: wrap;
            gap: 12px;
        }
        .stepper-header-row h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stepper-header-row h3 i {
            color: var(--maroon);
        }
        .stage-badge-pill {
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            border: 1px solid rgba(127, 3, 3, 0.2);
        }
        .stepper-track-wrap {
            position: relative;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
        }
        .stepper-line-bg {
            position: absolute;
            top: 32px;
            left: 5%;
            right: 5%;
            height: 4px;
            background: #E2E8F0;
            border-radius: 10px;
            z-index: 1;
        }
        .stepper-line-fill {
            position: absolute;
            top: 32px;
            left: 5%;
            height: 4px;
            background: linear-gradient(90deg, #2e7d32 0%, var(--maroon) 100%);
            border-radius: 10px;
            z-index: 2;
            transition: width 0.6s ease;
        }
        .stage-node {
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
        }
        .node-bubble {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--bg-card, #ffffff);
            border: 3px solid #CBD5E1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748B;
            font-size: 1.05rem;
            margin-bottom: 10px;
            transition: all 0.3s var(--ease-spring);
        }
        .stage-node.completed .node-bubble {
            background: #2e7d32;
            border-color: #2e7d32;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        .stage-node.active .node-bubble {
            background: var(--maroon);
            border-color: var(--maroon);
            color: #ffffff;
            transform: scale(1.12);
            box-shadow: 0 0 0 6px rgba(127, 3, 3, 0.18), 0 4px 12px rgba(127, 3, 3, 0.3);
        }
        .node-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94A3B8;
            margin-bottom: 2px;
        }
        .node-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
            margin-bottom: 2px;
        }
        .node-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
        }
        .stage-node.active .node-title {
            color: var(--maroon);
        }
        .stage-node.completed .node-title {
            color: #2e7d32;
        }

        .stepper-stage-banner {
            margin-top: 22px;
            padding: 14px 18px;
            background: rgba(127, 3, 3, 0.035);
            border: 1.5px dashed rgba(127, 3, 3, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        .stepper-stage-banner i {
            font-size: 1.3rem;
            color: var(--maroon);
            flex-shrink: 0;
        }

        @media (max-width: 800px) {
            .stepper-track-wrap {
                flex-direction: column;
                gap: 18px;
            }
            .stepper-line-bg, .stepper-line-fill {
                display: none;
            }
            .stage-node {
                flex-direction: row;
                align-items: center;
                text-align: left;
                gap: 14px;
            }
            .node-bubble {
                margin-bottom: 0;
                flex-shrink: 0;
            }
        }

        /* 2-Column Asymmetric Layout */
        .case-dossier-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: flex-start;
        }
        @media (max-width: 1040px) {
            .case-dossier-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Card System */
        .dossier-card {
            background: var(--bg-card, #ffffff);
            border: 1.5px solid var(--border-color, #E2E8F0);
            border-radius: 20px;
            padding: 26px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
        }
        .dossier-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            flex-wrap: wrap;
            gap: 10px;
        }
        .dossier-card-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dossier-card-title i {
            color: var(--maroon);
            font-size: 1.15rem;
        }

        /* Key-Value Pair Grid */
        .keyval-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 18px;
        }
        @media (max-width: 640px) {
            .keyval-grid {
                grid-template-columns: 1fr;
            }
        }
        .keyval-item {
            background: var(--bg-primary, #F8FAFC);
            border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .keyval-item.full-span {
            grid-column: 1 / -1;
        }
        .keyval-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .keyval-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            word-break: break-word;
        }

        /* Stated Need Callout Box */
        .need-assessment-callout {
            margin-top: 18px;
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(127, 3, 3, 0.04) 0%, rgba(15, 65, 74, 0.04) 100%);
            border-left: 4px solid var(--maroon);
            border-radius: 12px;
        }
        .need-callout-header {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--maroon);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .need-callout-body {
            font-size: 0.92rem;
            color: var(--text-primary);
            line-height: 1.6;
            font-style: italic;
        }

        /* Unit Allocation Banner */
        .allocation-success-banner {
            background: rgba(46, 125, 50, 0.08);
            border: 1.5px solid rgba(46, 125, 50, 0.3);
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #2e7d32;
        }
        .allocation-success-banner i {
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        /* Modern Document Cards Grid */
        .doc-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }
        .doc-card-item {
            background: var(--bg-primary, #F8FAFC);
            border: 1.5px solid var(--border-color, #E2E8F0);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.2s ease;
        }
        .doc-card-item:hover {
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .doc-card-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .doc-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .doc-card-icon.pdf {
            background: rgba(220, 38, 38, 0.1);
            color: #DC2626;
        }
        .doc-card-icon.image {
            background: rgba(37, 99, 235, 0.1);
            color: #2563EB;
        }
        .doc-card-icon.other {
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
        }
        .doc-card-details {
            min-width: 0;
        }
        .doc-card-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .doc-card-status {
            font-size: 0.72rem;
            color: #2e7d32;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .btn-doc-view {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--maroon);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .btn-doc-view:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }

        /* Sidebar Cards */
        .sidebar-user-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }
        .sidebar-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--midnight);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 800;
            overflow: hidden;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .sidebar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar-user-name {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 2px 0;
        }
        .verified-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: #2e7d32;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .sidebar-contact-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .sidebar-contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-contact-item i {
            color: var(--maroon);
            width: 16px;
            text-align: center;
        }

        /* Caseworker Box */
        .caseworker-badge-box {
            background: var(--bg-primary, #F8FAFC);
            border: 1px solid var(--border-color, #E2E8F0);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .caseworker-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .caseworker-info h5 {
            margin: 0 0 2px 0;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .caseworker-info p {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Linked Appointment Widget */
        .linked-appt-card {
            background: linear-gradient(135deg, rgba(46, 125, 50, 0.06) 0%, rgba(46, 125, 50, 0.02) 100%);
            border: 1.5px solid rgba(46, 125, 50, 0.25);
            border-radius: 14px;
            padding: 16px;
            margin-top: 14px;
        }
        .linked-appt-card h5 {
            margin: 0 0 8px 0;
            font-size: 0.82rem;
            font-weight: 800;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .linked-appt-details {
            font-size: 0.78rem;
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* Bottom Floating Sticky Dock */
        .case-floating-dock {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1.5px solid rgba(0, 0, 0, 0.08);
            border-radius: 50px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            z-index: 100;
            transition: all 0.3s ease;
        }
        body[data-theme="dark"] .case-floating-dock {
            background: rgba(30, 41, 59, 0.94);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .btn-dock-back {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }
        .btn-dock-back:hover {
            color: var(--maroon);
        }
        .btn-dock-claim {
            background: var(--maroon);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }
        .btn-dock-claim:hover {
            background: #9b0f0f;
            transform: translateY(-2px);
            color: white;
        }
        .btn-dock-print {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-dock-print:hover {
            border-color: var(--maroon);
            color: var(--maroon);
        }

        /* Toast notification */
        .case-toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .case-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* 3-Dots Action Menus (Strictly Hidden on Desktop) */
        .btn-dock-more,
        .dock-more-menu,
        .btn-hero-dots,
        .hero-dots-menu {
            display: none !important;
        }

        @media (max-width: 768px) {
            .case-hero-right {
                align-items: flex-start !important;
                width: 100% !important;
                gap: 8px !important;
            }
            .hero-action-btns {
                position: relative;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                width: 100% !important;
            }
            .btn-hero-primary {
                padding: 6px 14px !important;
                font-size: 0.76rem !important;
            }
            .btn-hero-secondary {
                display: none !important; /* Collapsed into 3-dots menu on mobile */
            }
            .btn-hero-dots {
                display: inline-flex !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 50% !important;
                border: 1.5px solid var(--border-color) !important;
                background: var(--bg-card) !important;
                color: var(--text-primary) !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                font-size: 0.85rem !important;
                transition: all 0.2s ease !important;
            }
            .btn-hero-dots:hover {
                border-color: var(--maroon) !important;
                color: var(--maroon) !important;
            }
            .hero-dots-menu {
                display: none;
                position: absolute;
                top: calc(100% + 6px);
                left: 0;
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.18);
                padding: 6px;
                min-width: 155px;
                z-index: 105;
                flex-direction: column;
                gap: 4px;
            }
            .hero-dots-menu.open {
                display: flex !important;
            }
            .hero-menu-item {
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
                transition: background 0.2s ease;
            }
            .hero-menu-item:hover {
                background: rgba(127, 3, 3, 0.08);
                color: var(--maroon);
            }

            /* Floating Action Dock (Mobile Mode) */
            .case-floating-dock {
                bottom: 18px !important;
                padding: 6px 12px !important;
                gap: 8px !important;
                width: auto !important;
                max-width: 90% !important;
            }
            .btn-dock-back,
            .btn-dock-print {
                display: none !important; /* Collapsed into 3-dots menu on mobile */
            }
            .btn-dock-claim {
                padding: 7px 16px !important;
                font-size: 0.76rem !important;
            }
            .btn-dock-more {
                display: inline-flex !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 50% !important;
                border: 1.5px solid var(--border-color) !important;
                background: var(--bg-card) !important;
                color: var(--text-primary) !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                font-size: 0.85rem !important;
                transition: all 0.2s ease !important;
            }
            .btn-dock-more:hover {
                border-color: var(--maroon) !important;
                color: var(--maroon) !important;
            }
            .dock-more-menu {
                display: none;
                position: absolute;
                bottom: calc(100% + 10px);
                right: 0;
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.22);
                padding: 6px;
                min-width: 175px;
                z-index: 110;
                flex-direction: column;
                gap: 4px;
            }
            .dock-more-menu.open {
                display: flex !important;
            }
            .dock-menu-item {
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
                transition: background 0.2s ease;
            }
            .dock-menu-item:hover {
                background: rgba(127, 3, 3, 0.08);
                color: var(--maroon);
            }

            /* Dossier header & titles smaller */
            .case-hero-card {
                padding: 16px !important;
                border-radius: 16px !important;
            }
            .case-hero-left h1 {
                font-size: 1.05rem !important;
            }
            .dossier-card {
                padding: 14px 16px !important;
                border-radius: 16px !important;
            }
            .dossier-card-title {
                font-size: 0.92rem !important;
            }
            .case-breadcrumb-nav {
                font-size: 0.72rem !important;
                margin-bottom: 12px !important;
            }
        }

        @media print {
            .case-floating-dock, .case-breadcrumb-nav, .portal-header, .portal-sidebar, .btn-hero-primary, .btn-hero-secondary { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .view-housing-wrapper { max-width: 100% !important; padding: 0 !important; }
            .case-dossier-layout { grid-template-columns: 1fr !important; }
            .dossier-card { break-inside: avoid; border: 1px solid #ddd !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

                        <div class="view-housing-wrapper">
                <!-- Breadcrumbs & Quick Back -->
                <nav class="case-breadcrumb-nav" aria-label="Breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                    <span class="sep"><i class="fas fa-chevron-right"></i></span>
                    <a href="my-housing.php">My Housing Applications</a>
                    <span class="sep"><i class="fas fa-chevron-right"></i></span>
                    <span class="curr">Dossier #<?php echo htmlspecialchars($tracking_number); ?></span>
                </nav>

                <?php if (isset($_SESSION['success'])): ?>
                    <div style="background: rgba(76, 175, 80, 0.12); border-left: 4px solid #10b981; color: #065f46; padding: 14px 20px; border-radius: 12px; margin-bottom: 22px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <i class="fas fa-check-circle" style="font-size: 1.2rem; color: #10b981;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid #ef4444; color: #991b1b; padding: 14px 20px; border-radius: 12px; margin-bottom: 22px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <i class="fas fa-exclamation-circle" style="font-size: 1.2rem; color: #ef4444;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                    </div>
                <?php endif; ?>

                <?php $statusInfo = getHousingStatusInfo($application['status']); ?>

                <!-- Executive Hero Card -->
                <div class="case-hero-card">
                    <div class="case-hero-main">
                        <div class="case-hero-left">
                            <div class="case-hero-icon">
                                <i class="fas fa-landmark"></i>
                            </div>
                            <div class="case-hero-info">
                                <h1><?php echo htmlspecialchars($application['program_name'] ?? 'Palayan City Housing Beneficiary Program'); ?></h1>
                                <p>Official Beneficiary Application Dossier &bull; City Social Welfare and Development Office</p>
                                
                                <div class="case-chips-row">
                                    <div class="tracking-chip" title="Click icon to copy tracking number">
                                        <i class="fas fa-barcode"></i>
                                        <span><?php echo htmlspecialchars($tracking_number); ?></span>
                                        <button type="button" class="btn-copy-tracking" onclick="copyTracking('<?php echo htmlspecialchars($tracking_number); ?>')" title="Copy Tracking Number">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <span class="meta-chip">(Docket #<?php echo $application_id; ?>)</span>
                                    
                                    <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                                        <span class="type-chip insite">
                                            <i class="fas fa-house-chimney-user"></i> In-Site Housing Assistance
                                        </span>
                                    <?php else: ?>
                                        <span class="type-chip relocation">
                                            <i class="fas fa-truck-ramp-box"></i> Resettlement Housing Project
                                        </span>
                                    <?php endif; ?>

                                    <span class="meta-chip">
                                        <i class="far fa-calendar-alt"></i> Filed <?php echo date('M d, Y', strtotime($application['application_date'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="case-hero-right">
                            <div class="live-status-pill" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1.5px solid <?php echo $statusInfo['color']; ?>;">
                                <span class="pulse-dot"></span>
                                <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                <span><?php echo $statusInfo['text']; ?></span>
                            </div>
                            <div class="hero-action-btns">
                                <a href="print-claim-stub.php?type=housing&id=<?php echo $application_id; ?>" target="_blank" class="btn-hero-primary">
                                    <i class="fas fa-receipt"></i> Official Claim Stub
                                </a>
                                <button type="button" onclick="window.print()" class="btn-hero-secondary">
                                    <i class="fas fa-print"></i> Print Dossier
                                </button>
                                <!-- 3-Dots Action Button & Menu (Mobile Only) -->
                                <button type="button" class="btn-hero-dots" id="heroDotsToggle" aria-label="More options" aria-expanded="false" onclick="toggleHeroMenu(event)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="hero-dots-menu" id="heroDotsMenu">
                                    <button type="button" onclick="window.print(); closeHeroMenu();" class="hero-menu-item">
                                        <i class="fas fa-print"></i> Print Dossier
                                    </button>
                                    <a href="my-housing.php" class="hero-menu-item">
                                        <i class="fas fa-arrow-left"></i> My Applications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php 
                    $steps = [
                        1 => [
                            'title' => 'Intake & Submission',
                            'desc' => date('M d, Y', strtotime($application['application_date'])),
                            'icon' => 'fa-file-signature'
                        ],
                        2 => [
                            'title' => 'Document Screening',
                            'desc' => in_array($application['status'], ['qualified', 'approved', 'released']) ? 'Documents Verified' : 'Under Review',
                            'icon' => 'fa-clipboard-check'
                        ],
                        3 => [
                            'title' => (($application['housing_type'] ?? '') === 'in_site') ? 'Site Lot Inspection' : 'Family Assessment',
                            'desc' => !empty($application['interview_date']) ? date('M d, Y', strtotime($application['interview_date'])) : (in_array($application['status'], ['qualified', 'approved', 'released']) ? 'Assessment Completed' : 'Pending Scheduling'),
                            'icon' => (($application['housing_type'] ?? '') === 'in_site') ? 'fa-map-location-dot' : 'fa-people-roof'
                        ],
                        4 => [
                            'title' => 'Allocation & Approval',
                            'desc' => !empty($application['approval_date']) ? date('M d, Y', strtotime($application['approval_date'])) : (in_array($application['status'], ['approved', 'released']) ? 'Approved' : 'Pending Board Clearance'),
                            'icon' => 'fa-certificate'
                        ],
                        5 => [
                            'title' => (($application['housing_type'] ?? '') === 'in_site') ? 'Grant Voucher Release' : 'Unit Turnover',
                            'desc' => ($application['status'] === 'released') ? 'Turnover Completed' : (!empty($application['move_in_date']) ? date('M d, Y', strtotime($application['move_in_date'])) : 'Final Stage'),
                            'icon' => 'fa-key'
                        ]
                    ];

                    $current_step = 1;
                    if ($application['status'] === 'pending') $current_step = 2;
                    elseif ($application['status'] === 'qualified') $current_step = 3;
                    elseif ($application['status'] === 'approved') $current_step = 4;
                    elseif ($application['status'] === 'released') $current_step = 5;

                    $progress_pct = (($current_step - 1) / 4) * 90;
                ?>

                <!-- Stepper Roadmap Card -->
                <div class="stepper-roadmap-card">
                    <div class="stepper-header-row">
                        <h3><i class="fas fa-route"></i> Application Processing Journey</h3>
                        <span class="stage-badge-pill">Stage <?php echo min(5, max(1, $current_step)); ?> of 5 &bull; <?php echo htmlspecialchars($steps[$current_step]['title'] ?? ''); ?></span>
                    </div>

                    <?php if ($application['status'] === 'rejected'): ?>
                        <div style="background: rgba(244, 67, 54, 0.08); border: 1.5px solid #ef4444; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; color: #b91c1c;">
                            <i class="fas fa-circle-xmark" style="font-size: 1.8rem; flex-shrink: 0;"></i>
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 800;">Application Inactive / Needs Attention</h4>
                                <p style="margin: 0; font-size: 0.85rem; line-height: 1.45;">This housing application was not approved or requires revision. Please check the remarks or visit the CSWDO Housing Division at Palayan City Hall.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="stepper-track-wrap">
                            <div class="stepper-line-bg"></div>
                            <div class="stepper-line-fill" style="width: <?php echo $progress_pct; ?>%;"></div>

                            <?php foreach ($steps as $snum => $sdata): 
                                $is_completed = ($snum < $current_step) || ($application['status'] === 'released');
                                $is_active = ($snum === $current_step) && ($application['status'] !== 'released');
                                $status_class = $is_completed ? 'completed' : ($is_active ? 'active' : '');
                            ?>
                            <div class="stage-node <?php echo $status_class; ?>">
                                <div class="node-bubble">
                                    <?php if ($is_completed): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <i class="fas <?php echo $sdata['icon']; ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="node-label">Stage 0<?php echo $snum; ?></span>
                                <span class="node-title"><?php echo htmlspecialchars($sdata['title']); ?></span>
                                <span class="node-meta"><?php echo htmlspecialchars($sdata['desc']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="stepper-stage-banner">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Active Guidance (Stage <?php echo $current_step; ?>: <?php echo htmlspecialchars($steps[$current_step]['title']); ?>):</strong>
                                <?php 
                                if ($current_step == 2) {
                                    echo "Your application documents and land ownership records are currently being screened by CSWDO evaluation officers. No further action is required at this time.";
                                } elseif ($current_step == 3) {
                                    echo "Your dossier has been qualified. Municipal evaluators are coordinating the on-site verification schedule. Please keep your contact phone active.";
                                } elseif ($current_step == 4) {
                                    echo "Technical validation passed. Your application is before the Palayan Local Housing Board for formal award allocation.";
                                } elseif ($current_step == 5) {
                                    echo "Congratulations! Your housing grant voucher or unit key turnover is ready for official release.";
                                } else {
                                    echo "Application received and registered in the Palayan City Social Welfare portal.";
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2-Column Asymmetric Case Dossier -->
                <div class="case-dossier-layout">
                    <!-- Left Main Column (Detailed Technical & Socio-Economic Dossier) -->
                    <div class="dossier-main-col">
                        <!-- Housing Program & Technical Specifications -->
                        <div class="dossier-card">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-layer-group"></i> Property & Technical Specifications</h3>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--maroon);">CSWDO-HUR-SPEC</span>
                            </div>

                            <div class="keyval-grid">
                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="fas fa-hand-holding-heart"></i> Assistance Scheme</span>
                                    <span class="keyval-value">
                                        <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                                            <span style="color: #2e7d32;"><i class="fas fa-house-chimney-user"></i> In-Site Housing</span>
                                        <?php else: ?>
                                            <span style="color: #0F414A;"><i class="fas fa-truck-ramp-box"></i> Relocation Housing</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="fas fa-certificate"></i> Program Track</span>
                                    <span class="keyval-value"><?php echo htmlspecialchars($application['program_name'] ?? 'Housing Assistance'); ?></span>
                                </div>

                                <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                                    <div class="keyval-item full-span">
                                        <span class="keyval-label"><i class="fas fa-map-pin"></i> Property Location</span>
                                        <span class="keyval-value" style="color: #2e7d32; font-size: 1rem;">
                                            <i class="fas fa-location-dot"></i> <?php echo htmlspecialchars(!empty($application['property_location']) ? $application['property_location'] : ($application['location'] ?? 'Palayan City')); ?>
                                        </span>
                                    </div>

                                    <?php if(!empty($application['land_ownership_status'])): ?>
                                    <div class="keyval-item">
                                        <span class="keyval-label"><i class="fas fa-stamp"></i> Land Tenure / Right</span>
                                        <span class="keyval-value"><?php echo htmlspecialchars(html_entity_decode($application['land_ownership_status'], ENT_QUOTES, 'UTF-8')); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if(!empty($application['lot_area'])): ?>
                                    <div class="keyval-item">
                                        <span class="keyval-label"><i class="fas fa-vector-square"></i> Lot Area</span>
                                        <span class="keyval-value"><?php echo htmlspecialchars($application['lot_area']); ?> sq.m</span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if(!empty($application['construction_type'])): ?>
                                    <div class="keyval-item full-span">
                                        <span class="keyval-label"><i class="fas fa-hammer"></i> Construction Scope</span>
                                        <span class="keyval-value"><?php echo htmlspecialchars($application['construction_type']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <div class="keyval-item full-span">
                                        <span class="keyval-label"><i class="fas fa-map-location-dot"></i> Project Resettlement Location</span>
                                        <span class="keyval-value"><?php echo htmlspecialchars($application['location']); ?></span>
                                    </div>
                                    <div class="keyval-item">
                                        <span class="keyval-label"><i class="fas fa-city"></i> Total Capacity Units</span>
                                        <span class="keyval-value"><?php echo $application['total_units']; ?> Units</span>
                                    </div>
                                <?php endif; ?>

                                <?php if(!empty($application['program_description'])): ?>
                                <div class="keyval-item full-span">
                                    <span class="keyval-label"><i class="fas fa-info-circle"></i> Program Scope & Eligibility</span>
                                    <span class="keyval-value" style="font-weight: 500; font-size: 0.88rem; line-height: 1.5; color: var(--text-muted);">
                                        <?php echo nl2br(htmlspecialchars($application['program_description'])); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($application['unit_assigned']) || !empty($application['move_in_date'])): ?>
                            <div class="allocation-success-banner">
                                <i class="fas fa-award"></i>
                                <div>
                                    <strong style="display: block; font-size: 0.95rem;">Official Award / Allocation Reference: <?php echo htmlspecialchars($application['unit_assigned'] ?? 'Confirmed'); ?></strong>
                                    <span style="font-size: 0.82rem; opacity: 0.9;">
                                        <?php if(!empty($application['move_in_date'])): ?>
                                            Scheduled Key / Voucher Turnover: <strong><?php echo date('F d, Y', strtotime($application['move_in_date'])); ?></strong>
                                        <?php else: ?>
                                            Unit clearance is validated and ready for next processing milestone.
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Stated Need Assessment Callout -->
                            <?php if(!empty($application['housing_need_assessment'])): ?>
                            <div class="need-assessment-callout">
                                <div class="need-callout-header">
                                    <i class="fas fa-comment-dots"></i> Applicant's Stated Housing Need & Justification
                                </div>
                                <div class="need-callout-body">
                                    &ldquo;<?php echo nl2br(htmlspecialchars($application['housing_need_assessment'])); ?>&rdquo;
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Socio-Economic & Beneficiary Profile -->
                        <div class="dossier-card">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-users-viewfinder"></i> Socio-Economic & Family Profile</h3>
                                <span style="font-size: 0.78rem; font-weight: 700; color: #2e7d32;"><i class="fas fa-shield-check"></i> CSWDO Intake Record</span>
                            </div>

                            <div class="keyval-grid">
                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="fas fa-people-group"></i> Household Size</span>
                                    <span class="keyval-value"><?php echo htmlspecialchars($application['household_composition'] ?? '1'); ?> Member(s)</span>
                                </div>

                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="fas fa-coins"></i> Declared Monthly Income</span>
                                    <span class="keyval-value" style="color: var(--maroon);">PHP <?php echo number_format((float)($application['monthly_income'] ?? 0), 2); ?></span>
                                </div>

                                <div class="keyval-item full-span">
                                    <span class="keyval-label"><i class="fas fa-house-user"></i> Permanent Registered Address</span>
                                    <span class="keyval-value">
                                        <?php 
                                        $address = [];
                                        if(isset($application['street']) && !empty($application['street'])) $address[] = $application['street'];
                                        if(isset($application['barangay']) && !empty($application['barangay'])) $address[] = $application['barangay'];
                                        if(isset($application['city']) && !empty($application['city'])) $address[] = $application['city'];
                                        if(isset($application['province']) && !empty($application['province'])) $address[] = $application['province'];
                                        echo htmlspecialchars(!empty($address) ? implode(', ', $address) : ($application['address'] ?? 'Palayan City, Nueva Ecija'));
                                        ?>
                                    </span>
                                </div>

                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="far fa-calendar-check"></i> Application Filing Date</span>
                                    <span class="keyval-value"><?php echo date('F d, Y', strtotime($application['application_date'])); ?></span>
                                </div>

                                <div class="keyval-item">
                                    <span class="keyval-label"><i class="fas fa-calendar-day"></i> <?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Lot Inspection Date' : 'Interview Date'; ?></span>
                                    <span class="keyval-value">
                                        <?php echo !empty($application['interview_date']) ? date('F d, Y', strtotime($application['interview_date'])) : '<span style="color: var(--text-muted); font-weight: normal;">Pending Scheduling</span>'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Supporting Documents Grid -->
                        <div class="dossier-card">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-folder-open"></i> Attached Supporting Documents</h3>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted);"><?php echo count($documents); ?> Attached File(s)</span>
                            </div>

                            <?php if(count($documents) > 0): ?>
                                <div class="doc-cards-grid">
                                    <?php foreach($documents as $doc): ?>
                                    <?php 
                                    $doc_path = cleanDocumentPath($doc['document_path'] ?? $doc['file_path'] ?? '');
                                    $doc_name = $doc['document_type'] ?? $doc['document_name'] ?? 'Document';
                                    $file_ext = strtolower(pathinfo($doc['document_path'] ?? $doc['file_name'] ?? '', PATHINFO_EXTENSION));
                                    $icon_type = in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'image' : ($file_ext === 'pdf' ? 'pdf' : 'other');
                                    ?>
                                    <div class="doc-card-item">
                                        <div class="doc-card-left">
                                            <div class="doc-card-icon <?php echo $icon_type; ?>">
                                                <i class="fas <?php echo $file_ext === 'pdf' ? 'fa-file-pdf' : (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'fa-file-image' : 'fa-file-lines'); ?>"></i>
                                            </div>
                                            <div class="doc-card-details">
                                                <div class="doc-card-title" title="<?php echo htmlspecialchars($doc_name); ?>">
                                                    <?php echo htmlspecialchars($doc_name); ?>
                                                </div>
                                                <div class="doc-card-status">
                                                    <i class="fas fa-circle-check"></i> On File &bull; <?php echo strtoupper($file_ext ?: 'FILE'); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($doc_path); ?>" class="btn-doc-view" target="_blank" title="View Document">
                                            <i class="fas fa-arrow-up-right-from-square"></i> View
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 32px 20px; background: var(--bg-primary); border-radius: 14px; border: 1px dashed var(--border-color);">
                                    <i class="fas fa-folder-open" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 8px;"></i>
                                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">No supporting documents attached to this dossier.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Sidebar Column (Sticky Case Admin & Applicant Profile) -->
                    <div class="dossier-sidebar-col">
                        <!-- Case Administration Desk Card -->
                        <div class="dossier-card">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-clipboard-check"></i> Case Desk</h3>
                            </div>

                            <div class="sidebar-contact-list">
                                <div class="sidebar-contact-item">
                                    <i class="fas fa-fingerprint"></i>
                                    <div>
                                        <strong style="display: block; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">Tracking Docket</strong>
                                        <span style="font-weight: 700; color: var(--maroon); font-family: monospace;"><?php echo htmlspecialchars($tracking_number); ?></span>
                                    </div>
                                </div>

                                <div class="sidebar-contact-item">
                                    <i class="fas fa-door-open"></i>
                                    <div>
                                        <strong style="display: block; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">Service Window</strong>
                                        <span style="font-weight: 700; color: var(--text-primary);">Window 2 - Housing & Resettlement</span>
                                    </div>
                                </div>

                                <div class="sidebar-contact-item">
                                    <i class="fas fa-building-columns"></i>
                                    <div>
                                        <strong style="display: block; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">CSWDO Division</strong>
                                        <span>Palayan City Social Welfare</span>
                                    </div>
                                </div>

                                <?php if(!empty($application['approved_by']) && !empty($application['staff_first_name'])): ?>
                                <div class="caseworker-badge-box" style="margin-top: 10px;">
                                    <div class="caseworker-icon">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="caseworker-info">
                                        <h5><?php echo htmlspecialchars($application['staff_first_name'] . ' ' . $application['staff_last_name']); ?></h5>
                                        <p><?php echo htmlspecialchars($application['staff_position'] ?? 'Casework Officer'); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($application['approval_date'])): ?>
                                <div class="sidebar-contact-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <div>
                                        <strong style="display: block; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">Cleared On</strong>
                                        <span style="font-weight: 600; color: #2e7d32;"><?php echo date('M d, Y h:i A', strtotime($application['approval_date'])); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($linked_appointment)): ?>
                            <div class="linked-appt-card">
                                <h5><i class="fas fa-calendar-check"></i> Linked Desk Appointment</h5>
                                <div class="linked-appt-details">
                                    <a href="my-appointments.php" style="color: #2e7d32; font-weight: 800; text-decoration: underline; display: block; margin-bottom: 4px;">
                                        Ref #<?php echo htmlspecialchars($linked_appointment['reference_number']); ?>
                                    </a>
                                    <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($linked_appointment['appointment_date'])); ?></div>
                                    <div><strong>Window:</strong> <?php echo htmlspecialchars($linked_appointment['window_assignment'] ?? 'Window 2'); ?></div>
                                    <div style="margin-top: 4px;"><span style="background: rgba(46, 125, 50, 0.15); color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-weight: 700; text-transform: uppercase; font-size: 0.7rem;"><?php echo htmlspecialchars($linked_appointment['status']); ?></span></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Registered Citizen Applicant Card -->
                        <div class="dossier-card">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-id-card"></i> Citizen Applicant</h3>
                            </div>

                            <div class="sidebar-user-header">
                                <div class="sidebar-avatar">
                                    <?php if (!empty($avatar_url)): ?>
                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <span><?php echo $initials ?: 'MP'; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="sidebar-user-name"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h4>
                                    <span class="verified-badge"><i class="fas fa-shield-check"></i> Verified Resident</span>
                                </div>
                            </div>

                            <div class="sidebar-contact-list">
                                <div class="sidebar-contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($application['contact_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="sidebar-contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span style="word-break: break-all;"><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="sidebar-contact-item">
                                    <i class="fas fa-location-dot"></i>
                                    <span><?php echo htmlspecialchars(!empty($application['barangay']) ? 'Brgy. ' . $application['barangay'] : (!empty($application['address']) ? $application['address'] : 'Palayan City Resident')); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Case Help & Inquiries Card -->
                        <div class="dossier-card" style="background: linear-gradient(135deg, rgba(127, 3, 3, 0.03) 0%, rgba(15, 65, 74, 0.03) 100%);">
                            <div class="dossier-card-header">
                                <h3 class="dossier-card-title"><i class="fas fa-headset"></i> Case Inquiry</h3>
                            </div>
                            <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0 0 14px 0; line-height: 1.5;">
                                Need assistance with your housing documents or status verification? Connect directly with the CSWDO Helpdesk.
                            </p>
                            <a href="helpdesk.php" style="background: var(--bg-card); border: 1.5px solid var(--border-color); color: var(--maroon); text-decoration: none; padding: 10px 16px; border-radius: 12px; font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;">
                                <i class="fas fa-paper-plane"></i> Submit Inquiry Ticket
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Elevated Floating Action Dock -->
                <div class="case-floating-dock">
                    <a href="my-housing.php" class="btn-dock-back">
                        <i class="fas fa-arrow-left"></i> My Applications
                    </a>
                    <button type="button" onclick="window.print()" class="btn-dock-print">
                        <i class="fas fa-print"></i> Print Dossier
                    </button>
                    <a href="print-claim-stub.php?type=housing&id=<?php echo $application_id; ?>" target="_blank" class="btn-dock-claim">
                        <i class="fas fa-receipt"></i> Official Claim Stub
                    </a>
                    <!-- 3-Dots Action Button & Dropdown (Mobile Only) -->
                    <button type="button" class="btn-dock-more" id="dockMoreToggle" aria-label="More actions" aria-expanded="false" onclick="toggleDockMenu(event)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dock-more-menu" id="dockMoreMenu">
                        <button type="button" onclick="window.print(); closeDockMenu();" class="dock-menu-item">
                            <i class="fas fa-print"></i> Print Dossier
                        </button>
                        <a href="my-housing.php" class="dock-menu-item">
                            <i class="fas fa-arrow-left"></i> My Applications
                        </a>
                    </div>
                </div>

                <!-- Toast notification for copy tracking -->
                <div id="caseToast" class="case-toast">
                    <i class="fas fa-check-circle" style="color: #4ade80; font-size: 1.1rem;"></i>
                    <span>Tracking Number copied to clipboard!</span>
                </div>
            </div> <!-- /.view-housing-wrapper -->
        </div> <!-- /.main-content -->
<script>
        // ========== 3-DOTS ACTION MENUS TOGGLE (MOBILE ONLY) ==========
        function toggleHeroMenu(e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById('heroDotsMenu');
            const btn = document.getElementById('heroDotsToggle');
            if (menu) {
                const isOpen = menu.classList.toggle('open');
                if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        }
        function closeHeroMenu() {
            const menu = document.getElementById('heroDotsMenu');
            if (menu) menu.classList.remove('open');
        }

        function toggleDockMenu(e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById('dockMoreMenu');
            const btn = document.getElementById('dockMoreToggle');
            if (menu) {
                const isOpen = menu.classList.toggle('open');
                if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        }
        function closeDockMenu() {
            const menu = document.getElementById('dockMoreMenu');
            if (menu) menu.classList.remove('open');
        }

        document.addEventListener('click', function(e) {
            const heroMenu = document.getElementById('heroDotsMenu');
            const heroBtn = document.getElementById('heroDotsToggle');
            if (heroMenu && heroBtn && !heroMenu.contains(e.target) && !heroBtn.contains(e.target)) {
                heroMenu.classList.remove('open');
            }

            const dockMenu = document.getElementById('dockMoreMenu');
            const dockBtn = document.getElementById('dockMoreToggle');
            if (dockMenu && dockBtn && !dockMenu.contains(e.target) && !dockBtn.contains(e.target)) {
                dockMenu.classList.remove('open');
            }
        });

        // ========== COPY TRACKING NUMBER HELPER ==========
        function copyTracking(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(showToast);
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast();
                } catch (err) {
                    console.error('Copy failed', err);
                }
                document.body.removeChild(textArea);
            }
        }

        function showToast() {
            if (window.showProcessToast) {
                window.showProcessToast('Tracking code copied to clipboard!', false, 'Copied');
                return;
            }

            const toast = document.getElementById('caseToast');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2600);
            }
        }

        // ========== MOBILE SIDEBAR TOGGLE ==========
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebarClose = document.getElementById('sidebarClose');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
        if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

        // ========== THEME TOGGLE ==========
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        const currentTheme = localStorage.getItem('theme') || 
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = themeToggle ? themeToggle.querySelector('i') : null;
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
            document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
        }

        setTheme(currentTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const current = html.getAttribute('data-theme');
                const newTheme = current === 'dark' ? 'light' : 'dark';
                setTheme(newTheme);
                
                themeToggle.style.transform = 'scale(0.9)';
                setTimeout(() => { themeToggle.style.transform = ''; }, 200);
            });
        }

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });
    </script>

    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

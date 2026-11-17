<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$appointment_id) {
    redirect('manage-appointments.php');
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

// Fetch appointment details
$query = "SELECT a.*, s.service_name, s.description as service_description, s.duration_minutes, sc.category_name,
          $user_fields
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          LEFT JOIN service_categories sc ON s.category_id = sc.category_id
          JOIN users u ON a.user_id = u.user_id
          WHERE a.appointment_id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $appointment_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    redirect('manage-appointments.php?error=Appointment not found');
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Safely ensure middle_name exists with fallback to residents demographics
if (!isset($appointment['middle_name']) || empty($appointment['middle_name'])) {
    $appointment['middle_name'] = '';
    if (!empty($appointment['user_id'])) {
        try {
            $res_chk = $db->prepare("SELECT middle_name FROM residents WHERE user_id = :uid LIMIT 1");
            $res_chk->execute([':uid' => $appointment['user_id']]);
            $res_m = $res_chk->fetchColumn();
            if (!empty($res_m)) {
                $appointment['middle_name'] = $res_m;
            }
        } catch (Exception $e) {}
    }
}

// Category RBAC Check: Ensure staff cannot access appointments outside their assigned services
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$current_staff_id = $staff_info_data ? $staff_info_data['id'] : 0;

$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $current_staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$cat_map = ['Social Assistance' => 'AICS', 'AICS' => 'AICS', 'Housing' => 'Housing', 'Senior Citizen' => 'Senior Citizen', 'PWD' => 'PWD'];
$appt_cat = $cat_map[$appointment['category_name'] ?? ''] ?? ($appointment['category_name'] ?? '');
if (!isAdmin()) {
    if (empty($assigned_categories)) {
        $_SESSION['error'] = "Access denied. You have no service categories assigned.";
        redirect('manage-appointments.php');
    } elseif (!empty($appt_cat) && !in_array($appt_cat, $assigned_categories)) {
        $_SESSION['error'] = "Access denied. You do not have permission to view {$appt_cat} appointments.";
        redirect('manage-appointments.php');
    }
}

// Get document attachments for this appointment
$doc_query = "
    SELECT document_id, document_name, file_path, file_size, uploaded_at as upload_date 
    FROM appointment_documents 
    WHERE appointment_id = :id 
    UNION ALL 
    SELECT document_id, document_type as document_name, document_path as file_path, NULL as file_size, upload_date 
    FROM documents 
    WHERE appointment_id = :id2 
    ORDER BY upload_date DESC
";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->execute([':id' => $appointment_id, ':id2' => $appointment_id]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status audit trail
$hist_stmt = $db->prepare("
    SELECT h.*, h.new_status as status, h.remarks as notes, CONCAT(u.first_name, ' ', u.last_name) as action_by_name 
    FROM appointment_status_history h 
    LEFT JOIN users u ON h.performed_by = u.user_id 
    WHERE h.appointment_id = :id 
    ORDER BY h.created_at ASC
");
$hist_stmt->execute([':id' => $appointment_id]);
$status_history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff who processed this appointment (if any)
$staff_info = [];
$processed_by_id = $appointment['processed_by'] ?? ($appointment['completed_by'] ?? ($appointment['checked_in_by'] ?? ($appointment['started_by'] ?? ($appointment['cancelled_by'] ?? ($appointment['staff_id'] ?? null)))));
if (!empty($processed_by_id)) {
    $staff_query = "SELECT COALESCE(s.first_name, u.first_name) as first_name,
                           COALESCE(s.last_name, u.last_name) as last_name,
                           COALESCE(s.position, 'Intake Officer') as position
                    FROM users u
                    LEFT JOIN staff s ON u.user_id = s.user_id
                    WHERE u.user_id = :uid OR s.id = :sid
                    LIMIT 1";
    $staff_stmt = $db->prepare($staff_query);
    $staff_stmt->execute([':uid' => $processed_by_id, ':sid' => $processed_by_id]);
    $staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Comprehensive appointment status info mapping for all official workflow stages
function getAppointmentStatusInfo($status) {
    $statuses = [
        'pending'           => ['color' => '#B45309', 'icon' => 'fa-hourglass-start', 'text' => 'Pending Review', 'bg' => '#FEF3C7'],
        'for_review'        => ['color' => '#0369A1', 'icon' => 'fa-magnifying-glass', 'text' => 'For Review', 'bg' => '#E0F2FE'],
        'confirmed'         => ['color' => '#15803D', 'icon' => 'fa-calendar-check', 'text' => 'Confirmed & Slotted', 'bg' => '#DCFCE7'],
        'checked_in'        => ['color' => '#1D4ED8', 'icon' => 'fa-location-dot', 'text' => 'Checked-In (On-Site)', 'bg' => '#DBEAFE'],
        'in_progress'       => ['color' => '#7E22CE', 'icon' => 'fa-play', 'text' => 'In Progress (Assessment)', 'bg' => '#F3E8FF'],
        'completed'         => ['color' => '#047857', 'icon' => 'fa-circle-check', 'text' => 'Completed & Released', 'bg' => '#D1FAE5'],
        'rescheduled'       => ['color' => '#A16207', 'icon' => 'fa-arrows-rotate', 'text' => 'Rescheduled', 'bg' => '#FEF9C3'],
        'needs_information' => ['color' => '#C2410C', 'icon' => 'fa-file-circle-question', 'text' => 'Needs Information', 'bg' => '#FFEDD5'],
        'rejected'          => ['color' => '#B91C1C', 'icon' => 'fa-xmark', 'text' => 'Disapproved', 'bg' => '#FEE2E2'],
        'no_show'           => ['color' => '#475569', 'icon' => 'fa-user-slash', 'text' => 'No-Show', 'bg' => '#F1F5F9'],
        'cancelled'         => ['color' => '#991B1B', 'icon' => 'fa-ban', 'text' => 'Cancelled', 'bg' => '#FEE2E2']
    ];
    $key = strtolower(trim($status ?? 'pending'));
    return $statuses[$key] ?? ['color' => '#475569', 'icon' => 'fa-calendar-check', 'text' => ucwords(str_replace('_', ' ', $key)), 'bg' => '#F1F5F9'];
}
?>
<?php
$page_title = 'View Appointment #' . $appointment_id . ' - Staff Portal';
$active_nav = 'appointments';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
    /* ========== VIEW APPOINTMENT ENHANCED STYLING ========== */
    .view-appt-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-top: 10px;
    }

    /* KPI Highlights Bar */
    .appt-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .appt-kpi-card {
        background: var(--ref-card);
        border: 1px solid var(--ref-border);
        border-radius: 16px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.04);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .appt-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(0,0,0,0.08);
    }
    .appt-kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .appt-kpi-body {
        flex: 1;
        min-width: 0;
    }
    .appt-kpi-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--ref-muted);
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .appt-kpi-value {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--ref-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Workflow Pipeline Stepper */
    .workflow-stepper-card {
        background: var(--ref-card);
        border: 1px solid var(--ref-border);
        border-radius: 18px;
        padding: 22px 26px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    }
    .stepper-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .stepper-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--ref-text);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .stepper-track {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        gap: 8px;
    }
    .stepper-track::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 40px;
        right: 40px;
        height: 3px;
        background: var(--ref-border);
        z-index: 1;
    }
    .step-item {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        flex: 1;
    }
    .step-circle {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--ref-card);
        border: 3px solid var(--ref-border);
        color: var(--ref-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        font-weight: 800;
        margin-bottom: 8px;
        transition: all 0.3s;
    }
    .step-item.completed .step-circle {
        background: #059669;
        border-color: #059669;
        color: #FFFFFF;
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15);
    }
    .step-item.active .step-circle {
        background: var(--ref-maroon);
        border-color: var(--ref-maroon);
        color: #FFFFFF;
        box-shadow: 0 0 0 5px rgba(127, 3, 3, 0.2);
        animation: pulseCircle 2s infinite;
    }
    .step-label {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--ref-muted);
    }
    .step-item.completed .step-label,
    .step-item.active .step-label {
        color: var(--ref-text);
    }
    .step-desc {
        font-size: 0.68rem;
        color: var(--ref-muted);
        margin-top: 2px;
    }

    @keyframes pulseCircle {
        0%, 100% { box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.2); }
        50% { box-shadow: 0 0 0 8px rgba(127, 3, 3, 0.05); }
    }

    /* Main 2-Column Grid */
    .view-grid-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 992px) {
        .view-grid-two-col {
            grid-template-columns: 1fr;
        }
        .stepper-track {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        .stepper-track::before {
            display: none;
        }
        .step-item {
            flex-direction: row;
            text-align: left;
            gap: 14px;
        }
        .step-circle {
            margin-bottom: 0;
        }
    }

    .detail-card {
        background: var(--ref-card);
        border: 1px solid var(--ref-border);
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    .detail-card:last-child {
        margin-bottom: 0;
    }
    .card-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 14px;
        margin-bottom: 16px;
        border-bottom: 1px solid var(--ref-border);
    }
    .card-header-title {
        font-size: 1rem;
        font-weight: 800;
        color: var(--ref-text);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-header-title i {
        color: var(--ref-maroon);
        font-size: 1.1rem;
    }

    /* User Profile Hero inside Card */
    .client-hero-box {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        border-radius: 14px;
        background: var(--ref-card-subtle);
        border: 1px solid var(--ref-border);
        margin-bottom: 18px;
    }
    .client-avatar-circle {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--ref-maroon), #A31212);
        color: #FFFFFF;
        font-size: 1.4rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
    }
    .client-hero-meta h3 {
        margin: 0 0 4px 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--ref-text);
    }
    .client-hero-meta p {
        margin: 0;
        font-size: 0.78rem;
        color: var(--ref-muted);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Field Rows */
    .field-row {
        display: flex;
        align-items: flex-start;
        padding: 11px 0;
        border-bottom: 1px solid var(--ref-card-subtle);
    }
    .field-row:last-child {
        border-bottom: none;
    }
    .field-label {
        width: 140px;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--ref-muted);
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .field-value {
        flex: 1;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--ref-text);
        word-break: break-word;
    }
    .field-value a {
        color: var(--ref-maroon);
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .field-value a:hover {
        opacity: 0.8;
        text-decoration: underline;
    }

    /* Purpose Callout Box */
    .purpose-callout {
        background: var(--ref-card-subtle);
        border-left: 4px solid var(--ref-maroon);
        border-radius: 0 12px 12px 0;
        padding: 14px 16px;
        font-size: 0.84rem;
        color: var(--ref-text);
        line-height: 1.5;
        font-style: italic;
        margin-top: 6px;
    }

    /* Documents List */
    .doc-item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-radius: 12px;
        background: var(--ref-card-subtle);
        border: 1px solid var(--ref-border);
        margin-bottom: 10px;
        transition: background 0.2s;
    }
    .doc-item-row:hover {
        background: var(--ref-canvas);
    }
    .doc-item-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    .doc-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(127, 3, 3, 0.1);
        color: var(--ref-maroon);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .doc-item-title {
        font-size: 0.84rem;
        font-weight: 700;
        color: var(--ref-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .doc-item-date {
        font-size: 0.7rem;
        color: var(--ref-muted);
    }

    /* Audit Timeline */
    .status-timeline {
        position: relative;
        padding-left: 28px;
        margin-top: 8px;
    }
    .status-timeline::before {
        content: '';
        position: absolute;
        top: 6px;
        bottom: 6px;
        left: 10px;
        width: 2px;
        background: var(--ref-border);
    }
    .timeline-node {
        position: relative;
        padding-bottom: 18px;
    }
    .timeline-node:last-child {
        padding-bottom: 0;
    }
    .timeline-pin {
        position: absolute;
        left: -28px;
        top: 2px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--ref-card);
        border: 3px solid var(--ref-maroon);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.55rem;
        color: var(--ref-maroon);
    }
    .timeline-body {
        background: var(--ref-card-subtle);
        border: 1px solid var(--ref-border);
        border-radius: 12px;
        padding: 10px 14px;
    }
    .timeline-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
        flex-wrap: wrap;
        gap: 6px;
    }
    .timeline-author {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--ref-text);
    }
    .timeline-time {
        font-size: 0.7rem;
        color: var(--ref-muted);
    }
    .timeline-notes {
        font-size: 0.78rem;
        color: var(--ref-muted);
        line-height: 1.4;
    }

    /* Bottom Action Bar */
    .view-actions-bar {
        background: var(--ref-card);
        border: 1px solid var(--ref-border);
        border-radius: 18px;
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    }
    .btn-act-primary {
        background: var(--ref-maroon);
        color: #FFFFFF;
        border: none;
        border-radius: 10px;
        padding: 10px 22px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.2s, background 0.2s;
        box-shadow: 0 3px 10px rgba(127, 3, 3, 0.25);
    }
    .btn-act-primary:hover {
        background: var(--ref-maroon-light);
        transform: translateY(-2px);
    }
    .btn-act-secondary {
        background: var(--ref-card-subtle);
        color: var(--ref-text);
        border: 1px solid var(--ref-border);
        border-radius: 10px;
        padding: 10px 20px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-act-secondary:hover {
        background: var(--ref-border);
    }

    /* Modal Styling */
    .appt-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .appt-modal-backdrop.active {
        display: flex;
    }
    .appt-modal-dialog {
        background: var(--ref-card);
        border-radius: 20px;
        max-width: 520px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 26px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 1px solid var(--ref-border);
        animation: modalSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes modalSlide {
        0% { opacity: 0; transform: translateY(-20px) scale(0.96); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media print {
        .sidebar, .navbar, .portal-header-bar, .view-actions-bar, .portal-banner-wrap, .btn-act-primary, .btn-act-secondary {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        .detail-card {
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }
    }
</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Container -->
        <div class="staff-big-card">
            <?php
            $ref_code = $appointment['reference_number'] ?: ('PAL-SOC-' . $appointment_id);
            $banner_title = "Appointment #" . $appointment_id;
            $banner_subtitle = "Ref: " . htmlspecialchars($ref_code) . " • Client: " . htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']) . " • Service: " . htmlspecialchars($appointment['service_name'] ?? 'General Welfare');
            $banner_icon = "fa-calendar-check";
            $banner_actions = [
                ["label" => "Back to Appointments", "url" => "manage-appointments.php", "icon" => "fa-arrow-left", "type" => "secondary"],
                ["label" => "Print Official Slip", "url" => "../appointment-slip.php?ref=" . urlencode($ref_code), "icon" => "fa-print", "type" => "primary", "target" => "_blank"]
            ];
            if (strtolower($appointment['status']) !== 'completed' && strtolower($appointment['status']) !== 'cancelled') {
                $banner_actions[] = [
                    "label" => "Update Status",
                    "url" => "#",
                    "icon" => "fa-edit",
                    "type" => "accent",
                    "onclick" => "showUpdateModal(); return false;"
                ];
            }
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <?php if (!empty($_SESSION['success'])): ?>
                <div style="background: rgba(16,185,129,0.12); border: 1.5px solid #10B981; border-radius: 14px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; color: #047857;">
                    <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.9rem;">
                        <i class="fas fa-circle-check" style="font-size: 1.25rem;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#047857;cursor:pointer;font-size:1.2rem;line-height:1;">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['error'])): ?>
                <div style="background: rgba(239,68,68,0.12); border: 1.5px solid #EF4444; border-radius: 14px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; color: #DC2626;">
                    <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.9rem;">
                        <i class="fas fa-circle-exclamation" style="font-size: 1.25rem;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:#DC2626;cursor:pointer;font-size:1.2rem;line-height:1;">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php 
            $statusInfo = getAppointmentStatusInfo($appointment['status']); 
            $st_clean = strtolower($appointment['status'] ?? 'pending');
            $stage_num = 1;
            if (in_array($st_clean, ['pending', 'for_review', 'needs_information'])) {
                $stage_num = 1;
            } elseif (in_array($st_clean, ['confirmed', 'rescheduled'])) {
                $stage_num = 2;
            } elseif (in_array($st_clean, ['checked_in', 'in_progress'])) {
                $stage_num = 3;
            } elseif ($st_clean === 'completed') {
                $stage_num = 4;
            } else {
                $stage_num = 0; // cancelled / rejected / no_show
            }
            ?>

            <div class="view-appt-container">
                <!-- Overview KPI Highlights Grid -->
                <div class="appt-kpi-grid">
                    <div class="appt-kpi-card">
                        <div class="appt-kpi-icon" style="background: rgba(127, 3, 3, 0.1); color: var(--ref-maroon);">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <div class="appt-kpi-body">
                            <div class="appt-kpi-label">Reference Number</div>
                            <div class="appt-kpi-value" title="<?php echo htmlspecialchars($ref_code); ?>">
                                <?php echo htmlspecialchars($ref_code); ?>
                            </div>
                        </div>
                    </div>

                    <div class="appt-kpi-card">
                        <div class="appt-kpi-icon" style="background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>;">
                            <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                        </div>
                        <div class="appt-kpi-body">
                            <div class="appt-kpi-label">Current Workflow Status</div>
                            <div class="appt-kpi-value" style="color: <?php echo $statusInfo['color']; ?>; font-size: 0.95rem;">
                                <?php echo htmlspecialchars($statusInfo['text']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="appt-kpi-card">
                        <div class="appt-kpi-icon" style="background: rgba(37, 99, 235, 0.1); color: #2563EB;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="appt-kpi-body">
                            <div class="appt-kpi-label">Scheduled Date &amp; Slot</div>
                            <div class="appt-kpi-value" style="font-size: 0.92rem;">
                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?> • <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="appt-kpi-card">
                        <div class="appt-kpi-icon" style="background: rgba(5, 150, 105, 0.1); color: #059669;">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="appt-kpi-body">
                            <div class="appt-kpi-label">Service &amp; Category</div>
                            <div class="appt-kpi-value" style="font-size: 0.88rem;">
                                <?php echo htmlspecialchars($appointment['category_name'] ?: 'Social Welfare'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workflow Stepper Pipeline -->
                <div class="workflow-stepper-card">
                    <div class="stepper-header">
                        <div class="stepper-title">
                            <i class="fas fa-arrows-split-up-and-left" style="color: var(--ref-maroon);"></i>
                            <span>Citizen Appointment Lifecycle</span>
                        </div>
                        <div>
                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 50px; font-size: 0.76rem; font-weight: 700; background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['color']; ?>33;">
                                <i class="fas <?php echo $statusInfo['icon']; ?>"></i> <?php echo htmlspecialchars($statusInfo['text']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($stage_num === 0): ?>
                        <div style="background: rgba(220, 38, 38, 0.08); border: 1px dashed rgba(220, 38, 38, 0.35); border-radius: 12px; padding: 14px 18px; color: #DC2626; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.85rem;">
                            <i class="fas fa-circle-xmark" style="font-size: 1.2rem;"></i>
                            <span>This appointment was marked as <strong><?php echo strtoupper($st_clean); ?></strong> and removed from the active operational queue.</span>
                        </div>
                    <?php else: ?>
                        <div class="stepper-track">
                            <div class="step-item <?php echo ($stage_num > 1) ? 'completed' : ($stage_num === 1 ? 'active' : ''); ?>">
                                <div class="step-circle">
                                    <?php echo ($stage_num > 1) ? '<i class="fas fa-check"></i>' : '1'; ?>
                                </div>
                                <div class="step-label">Intake &amp; Review</div>
                                <div class="step-desc">Booked by citizen</div>
                            </div>

                            <div class="step-item <?php echo ($stage_num > 2) ? 'completed' : ($stage_num === 2 ? 'active' : ''); ?>">
                                <div class="step-circle">
                                    <?php echo ($stage_num > 2) ? '<i class="fas fa-check"></i>' : '2'; ?>
                                </div>
                                <div class="step-label">Scheduled &amp; Confirmed</div>
                                <div class="step-desc">Slot verified</div>
                            </div>

                            <div class="step-item <?php echo ($stage_num > 3) ? 'completed' : ($stage_num === 3 ? 'active' : ''); ?>">
                                <div class="step-circle">
                                    <?php echo ($stage_num > 3) ? '<i class="fas fa-check"></i>' : '3'; ?>
                                </div>
                                <div class="step-label">Checked-In / On-Site</div>
                                <div class="step-desc">Arrived at CSWDO</div>
                            </div>

                            <div class="step-item <?php echo ($stage_num === 4) ? 'completed' : ''; ?>">
                                <div class="step-circle">
                                    <?php echo ($stage_num === 4) ? '<i class="fas fa-check"></i>' : '4'; ?>
                                </div>
                                <div class="step-label">Completed &amp; Released</div>
                                <div class="step-desc">Assistance issued</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Main 2-Column Grid -->
                <div class="view-grid-two-col">
                    <!-- Column 1: Client Profile & Supporting Documents -->
                    <div>
                        <!-- Client Information Card -->
                        <div class="detail-card">
                            <div class="card-header-row">
                                <div class="card-header-title">
                                    <i class="fas fa-user-circle"></i>
                                    <span>Client Profile &amp; Residency</span>
                                </div>
                                <span style="font-size: 0.72rem; font-weight: 700; color: #059669; background: rgba(5,150,105,0.1); padding: 3px 10px; border-radius: 50px;">
                                    <i class="fas fa-shield-check"></i> Verified Resident
                                </span>
                            </div>

                            <div class="client-hero-box">
                                <div class="client-avatar-circle">
                                    <?php echo htmlspecialchars($initials ?: strtoupper(substr($appointment['first_name'], 0, 1))); ?>
                                </div>
                                <div class="client-hero-meta">
                                    <?php 
                                    $middle_str = !empty($appointment['middle_name']) ? (' ' . trim($appointment['middle_name'])) : '';
                                    $full_client_name = trim(($appointment['first_name'] ?? '') . $middle_str . ' ' . ($appointment['last_name'] ?? ''));
                                    ?>
                                    <h3><?php echo htmlspecialchars($full_client_name ?: 'Resident Client'); ?></h3>
                                    <p>
                                        <span><i class="fas fa-id-badge"></i> User ID #<?php echo $appointment['user_id']; ?></span>
                                        <span>•</span>
                                        <span><i class="fas fa-map-marker-alt"></i> Palayan City Citizen</span>
                                    </p>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-phone"></i> Contact No:</div>
                                <div class="field-value">
                                    <a href="tel:<?php echo htmlspecialchars($appointment['contact_number']); ?>">
                                        <?php echo htmlspecialchars($appointment['contact_number'] ?: 'Not provided'); ?>
                                    </a>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-envelope"></i> Email Address:</div>
                                <div class="field-value">
                                    <a href="mailto:<?php echo htmlspecialchars($appointment['email']); ?>">
                                        <?php echo htmlspecialchars($appointment['email']); ?>
                                    </a>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-house-user"></i> Address:</div>
                                <div class="field-value">
                                    <?php 
                                    $address = [];
                                    if(!empty($appointment['street'])) $address[] = $appointment['street'];
                                    if(!empty($appointment['barangay'])) $address[] = 'Brgy. ' . $appointment['barangay'];
                                    if(!empty($appointment['city'])) $address[] = $appointment['city'];
                                    if(!empty($appointment['province'])) $address[] = $appointment['province'];
                                    echo htmlspecialchars(!empty($address) ? implode(', ', $address) : 'Palayan City, Nueva Ecija');
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Supporting Documents Card -->
                        <div class="detail-card">
                            <div class="card-header-row">
                                <div class="card-header-title">
                                    <i class="fas fa-paperclip"></i>
                                    <span>Uploaded Supporting Documents</span>
                                </div>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--ref-muted); background: var(--ref-card-subtle); padding: 3px 10px; border-radius: 50px;">
                                    <?php echo count($documents); ?> Attached
                                </span>
                            </div>

                            <?php if (!empty($documents)): ?>
                                <?php foreach($documents as $doc): ?>
                                    <?php 
                                        $filePath = $doc['file_path'] ?? ($doc['document_path'] ?? '');
                                        $docName = $doc['document_name'] ?? ($doc['document_type'] ?? 'Attached Document');
                                        $ext = strtolower(pathinfo($filePath ?: $docName, PATHINFO_EXTENSION));
                                        $iconClass = ($ext === 'pdf') ? 'fa-file-pdf' : (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? 'fa-file-image' : 'fa-file-lines');
                                        
                                        if (!empty($filePath)) {
                                            if (strpos($filePath, 'http') === 0 || strpos($filePath, '/') === 0) {
                                                $docUrl = $filePath;
                                            } elseif (strpos($filePath, 'uploads/') === 0) {
                                                $docUrl = '../' . $filePath;
                                            } else {
                                                $docUrl = '../uploads/' . $filePath;
                                            }
                                        } else {
                                            $docUrl = '#';
                                        }
                                    ?>
                                    <div class="doc-item-row">
                                        <div class="doc-item-info">
                                            <div class="doc-item-icon">
                                                <i class="fas <?php echo $iconClass; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="doc-item-title"><?php echo htmlspecialchars($docName); ?></div>
                                                <div class="doc-item-date">
                                                    <?php echo !empty($doc['upload_date']) ? date('M d, Y h:i A', strtotime($doc['upload_date'])) : 'Uploaded with booking'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($docUrl); ?>" target="_blank" class="btn-act-secondary" style="padding: 6px 14px; font-size: 0.75rem;">
                                            <i class="fas fa-arrow-up-right-from-square"></i> View
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 28px 16px; color: var(--ref-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 2rem; opacity: 0.5; margin-bottom: 8px; display: block;"></i>
                                    <p style="margin: 0; font-size: 0.82rem;">No supporting documents attached to this booking.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Column 2: Service Details & Processing History -->
                    <div>
                        <!-- Service & Appointment Details Card -->
                        <div class="detail-card">
                            <div class="card-header-row">
                                <div class="card-header-title">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Service &amp; Booking Specifications</span>
                                </div>
                                <span style="font-size: 0.72rem; font-weight: 700; color: #2563EB; background: rgba(37,99,235,0.1); padding: 3px 10px; border-radius: 50px;">
                                    <?php echo htmlspecialchars($appointment['category_name'] ?: 'Social Services'); ?>
                                </span>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-hand-holding-medical"></i> Service:</div>
                                <div class="field-value">
                                    <strong style="color: var(--ref-maroon); font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($appointment['service_name']); ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-calendar-day"></i> Schedule:</div>
                                <div class="field-value">
                                    <?php echo date('l, F d, Y', strtotime($appointment['appointment_date'])); ?>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-clock"></i> Time Window:</div>
                                <div class="field-value">
                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                    <span style="font-size: 0.74rem; color: var(--ref-muted); margin-left: 6px;">(Est. <?php echo $appointment['duration_minutes'] ?? '30'; ?> mins)</span>
                                </div>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-comment-dots"></i> Reason / Purpose:</div>
                                <div class="field-value">
                                    <?php if (!empty($appointment['purpose'])): ?>
                                        <div class="purpose-callout">
                                            "<?php echo nl2br(htmlspecialchars($appointment['purpose'])); ?>"
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--ref-muted); font-style: italic;">No specific reason provided by client.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($appointment['service_description'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-info-circle"></i> Service Overview:</div>
                                <div class="field-value" style="font-size: 0.8rem; color: var(--ref-muted); line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars($appointment['service_description'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Processing Information & Audit Trail Card -->
                        <div class="detail-card">
                            <div class="card-header-row">
                                <div class="card-header-title">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>Processing &amp; Audit Trail</span>
                                </div>
                                <span style="font-size: 0.72rem; font-weight: 700; color: var(--ref-muted);">
                                    Audit ID #<?php echo $appointment_id; ?>
                                </span>
                            </div>

                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-clock-rotate-left"></i> Booked On:</div>
                                <div class="field-value"><?php echo date('F d, Y h:i A', strtotime($appointment['created_at'])); ?></div>
                            </div>

                            <?php if (!empty($staff_info)): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-user-tie"></i> Handled By:</div>
                                <div class="field-value">
                                    <strong><?php echo htmlspecialchars($staff_info['first_name'] . ' ' . $staff_info['last_name']); ?></strong>
                                    <span style="font-size: 0.72rem; color: var(--ref-muted); margin-left: 6px;">(<?php echo htmlspecialchars($staff_info['position']); ?>)</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($appointment['checked_in_at'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-location-dot"></i> Checked-In At:</div>
                                <div class="field-value"><?php echo date('F d, Y h:i A', strtotime($appointment['checked_in_at'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($appointment['started_at'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-play"></i> Assessment Started:</div>
                                <div class="field-value"><?php echo date('F d, Y h:i A', strtotime($appointment['started_at'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($appointment['completed_at'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-circle-check"></i> Completed At:</div>
                                <div class="field-value" style="color: #059669; font-weight: 700;">
                                    <?php echo date('F d, Y h:i A', strtotime($appointment['completed_at'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($appointment['rejection_reason'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-triangle-exclamation"></i> Disapproval Reason:</div>
                                <div class="field-value" style="color: #DC2626; font-weight: 700;">
                                    <?php echo htmlspecialchars($appointment['rejection_reason']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($appointment['notes'])): ?>
                            <div class="field-row">
                                <div class="field-label"><i class="fas fa-note-sticky"></i> Staff Remarks:</div>
                                <div class="field-value"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></div>
                            </div>
                            <?php endif; ?>

                            <!-- Historical Audit Trail Timeline -->
                            <?php if (!empty($status_history)): ?>
                                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--ref-border);">
                                    <div style="font-size: 0.78rem; font-weight: 800; color: var(--ref-text); text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-history" style="color: var(--ref-maroon);"></i> Status History Timeline
                                    </div>
                                    <div class="status-timeline">
                                        <?php foreach ($status_history as $hist): ?>
                                            <?php $histStatus = getAppointmentStatusInfo($hist['status'] ?? $hist['new_status']); ?>
                                            <div class="timeline-node">
                                                <div class="timeline-pin">
                                                    <i class="fas <?php echo $histStatus['icon']; ?>"></i>
                                                </div>
                                                <div class="timeline-body">
                                                    <div class="timeline-top">
                                                        <span style="font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 50px; background: <?php echo $histStatus['bg']; ?>; color: <?php echo $histStatus['color']; ?>;">
                                                            <?php echo htmlspecialchars($histStatus['text']); ?>
                                                        </span>
                                                        <span class="timeline-time">
                                                            <?php echo date('M d, Y h:i A', strtotime($hist['created_at'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-author">
                                                        By: <?php echo htmlspecialchars($hist['action_by_name'] ?: 'CSWDO Staff'); ?>
                                                    </div>
                                                    <?php if (!empty($hist['notes'])): ?>
                                                        <div class="timeline-notes">
                                                            <?php echo htmlspecialchars($hist['notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Bar -->
                <div class="view-actions-bar">
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <a href="manage-appointments.php" class="btn-act-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Appointments
                        </a>
                        <a href="../appointment-slip.php?ref=<?php echo urlencode($ref_code); ?>" target="_blank" class="btn-act-secondary">
                            <i class="fas fa-print"></i> Print Slip
                        </a>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php if (strtolower($appointment['status']) === 'completed'): ?>
                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: rgba(5,150,105,0.12); color: #059669; border: 1.5px solid #059669; border-radius: 12px; font-weight: 800; font-size: 0.85rem;">
                                <i class="fas fa-lock"></i> Transaction Completed &amp; Locked
                            </div>
                        <?php elseif (strtolower($appointment['status']) === 'cancelled'): ?>
                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: rgba(220,38,38,0.12); color: #DC2626; border: 1.5px solid #DC2626; border-radius: 12px; font-weight: 800; font-size: 0.85rem;">
                                <i class="fas fa-ban"></i> Appointment Cancelled
                            </div>
                        <?php else: ?>
                            <button type="button" onclick="showUpdateModal()" class="btn-act-primary">
                                <i class="fas fa-edit"></i> Update Workflow Status
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<?php if (strtolower($appointment['status']) !== 'completed' && strtolower($appointment['status']) !== 'cancelled'): ?>
<!-- Enhanced Update Status Modal -->
<div id="updateModal" class="appt-modal-backdrop">
    <div class="appt-modal-dialog">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 14px; margin-bottom: 18px; border-bottom: 1px solid var(--ref-border);">
            <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--ref-text); margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-arrows-split-up-and-left" style="color: var(--ref-maroon);"></i>
                <span>Update Workflow Status</span>
            </h2>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--ref-muted); cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <form method="POST" action="manage-appointments.php" onsubmit="return validateViewApptModal(event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
            <input type="hidden" name="return_to" value="view-appointment.php?id=<?php echo $appointment_id; ?>">

            <!-- Quick Client Info Box -->
            <div style="background: var(--ref-card-subtle); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; border: 1px solid var(--ref-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.82rem;">
                    <span style="color: var(--ref-muted);">Citizen:</span>
                    <strong style="color: var(--ref-text);"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem;">
                    <span style="color: var(--ref-muted);">Service:</span>
                    <span style="color: var(--ref-maroon); font-weight: 700;"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-size: 0.76rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase;">New Process Status *</label>
                <select name="status" id="modal_status" required onchange="handleModalStatusChange(this.value)" style="width: 100%; padding: 10px 12px; border: 1px solid var(--ref-border); border-radius: 10px; background: var(--ref-canvas); color: var(--ref-text); font-weight: 700; font-size: 0.88rem; font-family: inherit;">
                    <optgroup label="Stage 1: Intake &amp; Assessment">
                        <option value="pending" <?php echo ($st_clean === 'pending') ? 'selected' : ''; ?>>⏳ Pending (Submitted)</option>
                        <option value="for_review" <?php echo ($st_clean === 'for_review') ? 'selected' : ''; ?>>🔍 For Review (Staff Assigned)</option>
                        <option value="needs_information" <?php echo ($st_clean === 'needs_information') ? 'selected' : ''; ?>>📝 Needs Information (Missing Documents)</option>
                    </optgroup>
                    <optgroup label="Stage 2: Scheduling &amp; Slotted">
                        <option value="confirmed" <?php echo ($st_clean === 'confirmed') ? 'selected' : ''; ?>>✅ Confirmed (Schedule Approved)</option>
                        <option value="rescheduled" <?php echo ($st_clean === 'rescheduled') ? 'selected' : ''; ?>>🔄 Rescheduled (Change Date / Time)</option>
                    </optgroup>
                    <optgroup label="Stage 3: On-Site Presence &amp; Serving">
                        <option value="checked_in" <?php echo ($st_clean === 'checked_in') ? 'selected' : ''; ?>>📍 Citizen Checked-In (Arrived On-Site)</option>
                        <option value="in_progress" <?php echo ($st_clean === 'in_progress') ? 'selected' : ''; ?>>▶️ In Progress (Interview / Assessment)</option>
                    </optgroup>
                    <optgroup label="Stage 4: Resolution &amp; Closeout">
                        <option value="completed">🎉 Completed (Resolved &amp; Released)</option>
                        <option value="rejected" <?php echo ($st_clean === 'rejected') ? 'selected' : ''; ?>>❌ Disapproved (Not Qualified / Invalid)</option>
                        <option value="no_show" <?php echo ($st_clean === 'no_show') ? 'selected' : ''; ?>>🚫 No-Show (Did Not Arrive)</option>
                        <option value="cancelled" <?php echo ($st_clean === 'cancelled') ? 'selected' : ''; ?>>🛑 Cancel Appointment</option>
                    </optgroup>
                </select>
            </div>

            <!-- Reschedule Fields (Shown if Rescheduled) -->
            <div id="rescheduleFields" style="display: <?php echo ($st_clean === 'rescheduled') ? 'block' : 'none'; ?>; margin-bottom: 16px; padding: 12px; background: var(--ref-card-subtle); border-radius: 12px; border: 1px dashed var(--ref-border);">
                <div style="font-size: 0.74rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; margin-bottom: 8px;">Select New Slot</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-size: 0.72rem; color: var(--ref-muted); margin-bottom: 4px;">New Date</label>
                        <input type="date" name="new_date" value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--ref-border); background: var(--ref-card); color: var(--ref-text);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.72rem; color: var(--ref-muted); margin-bottom: 4px;">New Time</label>
                        <input type="time" name="new_time" value="<?php echo htmlspecialchars($appointment['appointment_time']); ?>" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--ref-border); background: var(--ref-card); color: var(--ref-text);">
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="modal_notes" style="display: block; margin-bottom: 6px; font-size: 0.76rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase;">Staff Notes / Action Summary</label>
                <textarea name="status_notes" id="modal_notes" rows="3" 
                          placeholder="Optional notes for citizen SMS / notification &amp; audit history..."
                          style="width: 100%; padding: 10px 12px; border: 1px solid var(--ref-border); border-radius: 10px; background: var(--ref-canvas); color: var(--ref-text); font-family: inherit; font-size: 0.85rem;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 14px; border-top: 1px solid var(--ref-border);">
                <button type="button" onclick="closeModal()" class="btn-act-secondary">
                    Cancel
                </button>
                <button type="submit" name="update_status" class="btn-act-primary">
                    <i class="fas fa-save"></i> Save Status &amp; Notify
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function handleModalStatusChange(val) {
    const reschedBox = document.getElementById('rescheduleFields');
    if (reschedBox) {
        reschedBox.style.display = (val === 'rescheduled') ? 'block' : 'none';
    }
}

function validateViewApptModal(event) {
    const status = document.getElementById('modal_status').value;
    if (status === 'completed') {
        return confirm("CRITICAL WORKFLOW ACTION:\n\nAre you sure you want to mark this appointment as COMPLETED?\n\nOnce marked as Completed, this appointment transaction will be permanently locked against all further modifications.");
    }
    if (status === 'cancelled') {
        return confirm("Are you sure you want to CANCEL this appointment booking?");
    }
    return true;
}

function showUpdateModal() {
    const modal = document.getElementById('updateModal');
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal() {
    const modal = document.getElementById('updateModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('updateModal');
    if (event.target === modal) {
        closeModal();
    }
});

window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

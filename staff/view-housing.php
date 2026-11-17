<?php
// staff/view-housing.php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Access check: Ensure staff has Housing category permission
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

if (!in_array('Housing', $assigned_categories) && !isAdmin()) {
    $_SESSION['error'] = "You do not have permission to access or manage Housing applications.";
    redirect('../staff-dashboard.php');
}

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

// Fetch documents specifically linked to this housing application
$doc_query = "SELECT * FROM documents 
              WHERE user_id = :user_id AND reference_id = :ref_id 
              ORDER BY upload_date DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':user_id', $application['user_id']);
$doc_stmt->bindParam(':ref_id', $beneficiary_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback for legacy applications created before reference_id was set
if (empty($documents)) {
    $legacy_doc_query = "SELECT * FROM documents 
                         WHERE user_id = :user_id 
                         AND (reference_id IS NULL OR reference_id = 0)
                         AND (document_path LIKE '%housing%' 
                              OR document_type IN ('Letter of Intent', 'Proof of Land Ownership', 'Affidavit of Consent', 'House / Lot Photo', 'Tax Declaration', 'Lot Sketch Plan', 'Barangay Clearance', 'Valid ID'))
                         ORDER BY upload_date DESC";
    $legacy_stmt = $db->prepare($legacy_doc_query);
    $legacy_stmt->bindParam(':user_id', $application['user_id']);
    $legacy_stmt->execute();
    $documents = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$tracking_number = "HSG-" . date('Y', strtotime($application['application_date'])) . "-" . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);

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
        'rejected' => ['color' => '#EF5350', 'icon' => 'fa-times-circle', 'text' => 'Disapproved', 'bg' => 'rgba(244,67,54,0.12)'],
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
<?php
$page_title = 'View Housing Application #' . $beneficiary_id . ' - Staff Portal';
$active_nav = 'housing';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
/* Page Specific Details & Modal Styling */
.app-header { background: var(--ref-card); border-radius: 20px; padding: 20px 24px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: var(--ref-shadow-sm); border: 1px solid var(--ref-border); }
.app-header .left { display: flex; align-items: center; gap: 16px; }
.app-header .left .avatar { width: 52px; height: 52px; border-radius: 50%; background: var(--ref-canvas); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; border: 2px solid var(--ref-maroon); }
.app-header .left .avatar img { width: 100%; height: 100%; object-fit: cover; }
.app-header .left .avatar .initials { font-weight: 800; font-size: 1.1rem; color: var(--ref-maroon); }
.app-info h1 { color: var(--ref-text); font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.app-info h1 i { color: var(--ref-maroon); }
.app-meta { display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.76rem; color: var(--ref-muted); margin-top: 4px; }
.app-meta span { display: inline-flex; align-items: center; gap: 5px; }
.status-badge-large { padding: 6px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 20px; }
.info-card { background: var(--ref-card); border-radius: 20px; padding: 20px 22px; box-shadow: var(--ref-shadow-sm); border: 1px solid var(--ref-border); }
.info-card .card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--ref-border); }
.info-card .card-header h3 { color: var(--ref-text); font-size: 0.92rem; font-weight: 700; }
.info-card .card-header i { color: var(--ref-maroon); font-size: 1rem; }
.info-row { display: flex; padding: 8px 0; border-bottom: 1px solid var(--ref-border); font-size: 0.82rem; }
.info-row:last-child { border-bottom: none; }
.info-row .label { width: 140px; font-weight: 600; color: var(--ref-muted); flex-shrink: 0; }
.info-row .value { flex: 1; color: var(--ref-text); font-weight: 500; }
.info-row .value .highlight { color: var(--ref-text); font-weight: 700; }
.housing-need { background: var(--ref-canvas); border-radius: 14px; padding: 14px 18px; margin-top: 8px; border-left: 3px solid var(--ref-maroon); }
.housing-need .content { color: var(--ref-text); font-size: 0.82rem; line-height: 1.6; white-space: pre-wrap; }
.documents-list { display: grid; gap: 10px; }
.document-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--ref-canvas); border-radius: 12px; transition: all 0.2s; border: 1px solid var(--ref-border); }
.document-item:hover { background: var(--ref-card); border-color: rgba(127, 3, 3, 0.3); }
.document-item .doc-name { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; color: var(--ref-text); font-weight: 600; }
.document-item .doc-name i { color: var(--ref-maroon); font-size: 1rem; }
.btn-download { background: var(--ref-maroon); color: white; padding: 5px 14px; border-radius: 50px; text-decoration: none; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
.action-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; flex-wrap: wrap; }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); display: none; justify-content: center; align-items: center; z-index: 2000; backdrop-filter: blur(4px); }
.modal.active { display: flex; }
.modal-content { background: var(--ref-card); border-radius: 24px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: var(--ref-shadow-lg); border: 1px solid var(--ref-border); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--ref-border); display: flex; justify-content: space-between; align-items: center; background: var(--ref-card-subtle); }
.modal-header h2 { font-size: 1.1rem; font-weight: 800; color: var(--ref-text); display: flex; align-items: center; gap: 10px; margin: 0; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--ref-muted); line-height: 1; }
.modal-body { padding: 22px 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--ref-border); display: flex; justify-content: flex-end; gap: 12px; background: var(--ref-card-subtle); }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 700; color: var(--ref-text); font-size: 0.8rem; }
.form-group small { display: block; margin-top: 4px; font-size: 0.72rem; color: var(--ref-muted); }
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

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <div class="staff-subheader">
                <div class="staff-subheader-title">
                    <h1><i class="fas fa-home"></i> Housing Application Details</h1>
                    <p>Application #<?php echo $beneficiary_id; ?> • Beneficiary Information</p>
                </div>
                <div class="staff-subheader-actions">
                    <a href="manage-housing.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Housing Applications</a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div style="background: rgba(76, 175, 80, 0.1); border-left: 4px solid #4CAF50; color: #2e7d32; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div style="background: rgba(244, 67, 54, 0.1); border-left: 4px solid #f44336; color: #c62828; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

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
                        <span style="font-family: monospace; font-size: 0.72rem; background: var(--ref-card-subtle, #f5f5f5); padding: 3px 12px; border-radius: 50px; font-weight: 800; color: var(--ref-maroon, #7F0303); border: 1px solid var(--ref-border, #ddd);">
                            <i class="fas fa-barcode"></i> <?php echo 'HSG-' . date('Y', strtotime($application['application_date'] ?? 'now')) . '-' . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT); ?>
                        </span>
                        <span style="font-size: 0.7rem; color: var(--ref-muted);">(ID #<?php echo $beneficiary_id; ?>)</span>
                        <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                            <span style="font-size: 0.72rem; background: rgba(46, 125, 50, 0.12); color: #2e7d32; border: 1px solid rgba(46, 125, 50, 0.25); padding: 3px 12px; border-radius: 50px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-house-chimney-user"></i> In-Site Housing (Sariling Lupa)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 0.72rem; background: rgba(15, 65, 74, 0.12); color: #0F414A; border: 1px solid rgba(15, 65, 74, 0.25); padding: 3px 12px; border-radius: 50px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-truck-ramp-box"></i> Relocation Housing
                            </span>
                        <?php endif; ?>
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
            
            <!-- Housing Assistance Pathway & Program Details -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-building"></i>
                    <h3>Housing Assistance Details</h3>
                </div>
                <div class="info-row">
                    <span class="label">Assistance Pathway</span>
                    <span class="value">
                        <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                            <strong style="color: #2e7d32;"><i class="fas fa-house-chimney-user"></i> In-Site Housing</strong>
                            <small style="display: block; color: var(--ref-muted); font-size: 0.72rem;">Construction on applicant's own property without relocation</small>
                        <?php else: ?>
                            <strong style="color: #0F414A;"><i class="fas fa-truck-ramp-box"></i> Relocation Housing</strong>
                            <small style="display: block; color: var(--ref-muted); font-size: 0.72rem;">Resettlement to designated community housing project</small>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Program Name</span>
                    <span class="value"><span class="highlight"><?php echo htmlspecialchars($application['program_name']); ?></span></span>
                </div>
                <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                    <div class="info-row">
                        <span class="label">Property Address</span>
                        <span class="value"><strong style="color: #2e7d32;"><?php echo htmlspecialchars($application['property_location'] ?: $application['location']); ?></strong></span>
                    </div>
                    <?php if(!empty($application['land_ownership_status'])): ?>
                    <div class="info-row">
                        <span class="label">Land Tenure</span>
                        <span class="value"><?php echo htmlspecialchars($application['land_ownership_status']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($application['lot_area'])): ?>
                    <div class="info-row">
                        <span class="label">Lot Area</span>
                        <span class="value"><?php echo htmlspecialchars($application['lot_area']); ?> sq.m</span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($application['construction_type'])): ?>
                    <div class="info-row">
                        <span class="label">Construction Scope</span>
                        <span class="value"><?php echo htmlspecialchars($application['construction_type']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="info-row">
                        <span class="label">Location</span>
                        <span class="value"><?php echo htmlspecialchars($application['location']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Units</span>
                        <span class="value"><?php echo $application['total_units']; ?> units</span>
                    </div>
                <?php endif; ?>
                <?php if(!empty($application['unit_assigned'])): ?>
                <div class="info-row">
                    <span class="label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Lot/Grant Ref' : 'Unit Assigned'; ?></span>
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
                    <span class="label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Lot Inspection Date' : 'Interview Date'; ?></span>
                    <span class="value"><?php echo date('F d, Y', strtotime($application['interview_date'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($application['move_in_date'])): ?>
                <div class="info-row">
                    <span class="label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Grant Release Date' : 'Turnover / Move-in'; ?></span>
                    <span class="value"><strong style="color: #9C27B0;"><i class="fas fa-calendar-check"></i> <?php echo date('F d, Y', strtotime($application['move_in_date'])); ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($linked_appointment)): ?>
                <div class="info-row">
                    <span class="label">Linked Appointment</span>
                    <span class="value">
                        <a href="view-appointment.php?id=<?php echo $linked_appointment['appointment_id']; ?>" style="color: #0F414A; font-weight: 700; text-decoration: underline;">
                            <i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars($linked_appointment['reference_number']); ?>
                        </a>
                        <span style="display: block; font-size: 0.75rem; color: var(--ref-muted); margin-top: 2px;">
                            <?php echo date('M d, Y', strtotime($linked_appointment['appointment_date'])); ?> &bull; <?php echo htmlspecialchars($linked_appointment['window_assignment'] ?? 'Window 2'); ?> &bull; <span style="text-transform: capitalize; color: #2e7d32; font-weight: 600;"><?php echo htmlspecialchars($linked_appointment['status']); ?></span>
                        </span>
                    </span>
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
            <a href="../print-claim-stub.php?type=housing&id=<?php echo $beneficiary_id; ?>" target="_blank" class="btn-primary" style="background: var(--ref-maroon, #7F0303);">
                <i class="fas fa-receipt"></i> Official Claim Stub
            </a>
            <button onclick="window.print()" class="btn-primary btn-print">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    </div>
</main>
</div>

<!-- Update Status Modal -->
<!-- Update Status Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Application Status</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="manage-housing.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="beneficiary_id" value="<?php echo $beneficiary_id; ?>">
            <input type="hidden" name="return_to" value="view">
            
            <div class="modal-body">
                <div class="applicant-info" style="display: flex; flex-direction: column; gap: 6px; background: var(--badge-bg); padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <div><strong>Applicant:</strong> <?php echo htmlspecialchars($application['full_name']); ?></div>
                    <div><strong>Program:</strong> <?php echo htmlspecialchars($application['program_name']); ?></div>
                    <div>
                        <strong>Assistance Pathway:</strong>
                        <?php if(($application['housing_type'] ?? 'relocation') === 'in_site'): ?>
                            <span style="color:#2e7d32; font-weight:700;"><i class="fas fa-house-chimney-user"></i> In-Site Housing (Sariling Lupa)</span>
                        <?php else: ?>
                            <span style="color:#0F414A; font-weight:700;"><i class="fas fa-truck-ramp-box"></i> Relocation Housing</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleHousingFields()">
                        <option value="qualified">📋 Mark as Qualified</option>
                        <option value="approved">✅ Approve & Assign <?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Grant / Voucher' : 'Unit'; ?></option>
                        <option value="rejected">❌ Disapprove</option>
                        <option value="released">💰 Mark as Released</option>
                        <option value="pending">⏰ Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group" id="unit_field" style="display: none;">
                    <label id="unit_assigned_label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Core Shelter Grant / Material Voucher Code' : 'Unit Number / House Assignment'; ?></label>
                    <input type="text" name="unit_assigned" id="unit_assigned" 
                           placeholder="<?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'e.g., Voucher #INSITE-2026-0042 (Core Shelter Package)' : 'e.g., Block 1, Lot 5, Unit 203'; ?>">
                    <small id="unit_assigned_small"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'The construction grant voucher or materials allocation reference' : 'The housing unit assigned to the beneficiary'; ?></small>
                </div>
                
                <div class="form-group" id="interview_field" style="display: none;">
                    <label id="interview_date_label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Site Verification / Field Inspection Date' : 'Interview / Family Assessment Date'; ?></label>
                    <input type="date" name="interview_date" id="interview_date" min="<?php echo date('Y-m-d'); ?>">
                    <small id="interview_date_small"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Schedule for on-site lot and construction inspection' : 'Schedule for caseworker interview and assessment'; ?></small>
                </div>

                <div class="form-group" id="move_in_field" style="display: none;">
                    <label id="move_in_date_label"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Grant Voucher Release / Turn-Over Date' : 'Move-in / Unit Turn-Over Date'; ?></label>
                    <input type="date" name="move_in_date" id="move_in_date">
                    <small id="move_in_date_small"><?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'Official date for release of building grant package' : 'Official move-in date for assigned housing unit'; ?></small>
                </div>

                <div class="form-group" id="assessment_field" style="display: none;">
                    <label for="assessment_notes" id="assessment_notes_label">Caseworker Assessment / Clearance Notes</label>
                    <textarea name="assessment_notes" id="assessment_notes" rows="2" 
                              placeholder="<?php echo ($application['housing_type'] ?? 'relocation') === 'in_site' ? 'e.g., Land ownership documents verified, lot inspected and cleared for construction grant...' : 'e.g., Family eligibility confirmed for relocation allocation...'; ?>"></textarea>
                    <small>Caseworker notes to record permanently on this application</small>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label>Disapproval Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Enter reason for disapproval..."></textarea>
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

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
<script>
const currentAppData = <?php echo json_encode($application); ?>;

// ========== MODAL FUNCTIONS ==========
function showUpdateModal() {
    if (currentAppData) {
        document.getElementById('modal_status').value = currentAppData.status || 'qualified';
        document.getElementById('unit_assigned').value = currentAppData.unit_assigned || '';
        document.getElementById('interview_date').value = currentAppData.interview_date || '';
        document.getElementById('move_in_date').value = currentAppData.move_in_date || (currentAppData.status === 'released' ? '<?php echo date('Y-m-d'); ?>' : '');
    }
    if (document.getElementById('assessment_notes')) document.getElementById('assessment_notes').value = '';
    if (document.getElementById('remarks')) document.getElementById('remarks').value = '';

    document.getElementById('updateModal').classList.add('active');
    toggleHousingFields();
}

function closeModal() {
    document.getElementById('updateModal').classList.remove('active');
    document.getElementById('unit_field').style.display = 'none';
    document.getElementById('interview_field').style.display = 'none';
    if (document.getElementById('move_in_field')) document.getElementById('move_in_field').style.display = 'none';
    const assessmentField = document.getElementById('assessment_field');
    if (assessmentField) assessmentField.style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    document.getElementById('unit_assigned').value = '';
    document.getElementById('interview_date').value = '';
    if (document.getElementById('move_in_date')) document.getElementById('move_in_date').value = '';
    if (document.getElementById('assessment_notes')) document.getElementById('assessment_notes').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'qualified';
}

function toggleHousingFields() {
    const status = document.getElementById('modal_status').value;
    const unitField = document.getElementById('unit_field');
    const interviewField = document.getElementById('interview_field');
    const moveInField = document.getElementById('move_in_field');
    const assessmentField = document.getElementById('assessment_field');
    const remarksField = document.getElementById('remarks_field');
    
    unitField.style.display = 'none';
    interviewField.style.display = 'none';
    if (moveInField) moveInField.style.display = 'none';
    if (assessmentField) assessmentField.style.display = 'none';
    remarksField.style.display = 'none';
    
    document.getElementById('unit_assigned').required = false;
    document.getElementById('interview_date').required = false;
    if (document.getElementById('move_in_date')) document.getElementById('move_in_date').required = false;
    document.getElementById('remarks').required = false;
    
    if (status === 'approved') {
        unitField.style.display = 'block';
        interviewField.style.display = 'block';
        if (moveInField) moveInField.style.display = 'block';
        if (assessmentField) assessmentField.style.display = 'block';
        document.getElementById('unit_assigned').required = true;
    } else if (status === 'released') {
        unitField.style.display = 'block';
        if (moveInField) {
            moveInField.style.display = 'block';
            document.getElementById('move_in_date').required = true;
            if (!document.getElementById('move_in_date').value) {
                document.getElementById('move_in_date').value = '<?php echo date('Y-m-d'); ?>';
            }
        }
        if (assessmentField) assessmentField.style.display = 'block';
    } else if (status === 'qualified') {
        interviewField.style.display = 'block';
        if (assessmentField) assessmentField.style.display = 'block';
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

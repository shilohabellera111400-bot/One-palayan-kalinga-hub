<?php
// staff/manage-senior.php - Senior Citizen Services & Applications Management
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for sidebar display & access check
$staff_id_query = "SELECT s.id, s.position, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? $staff_info['id'] : 0;

// Get assigned service categories
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Access check flags
$has_aics_access = in_array('AICS', $assigned_categories) || isAdmin();
$has_housing_access = in_array('Housing', $assigned_categories) || isAdmin();
$has_senior_access = in_array('Senior Citizen', $assigned_categories) || isAdmin();
$has_pwd_access = in_array('PWD', $assigned_categories) || isAdmin();

// RBAC Guard: Ensure staff cannot perform actions outside their assigned responsibilities
if (!$has_senior_access) {
    $_SESSION['error'] = "You do not have permission to access or manage Senior Citizen services.";
    redirect('../staff-dashboard.php');
}

$success_msg = '';
$error_msg = '';

// Handle Application Review Decision POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_senior_application'])) {
    csrf_verify();
    $app_id = (int)$_POST['application_id'];
    $new_status = sanitize($_POST['status']);
    $osca_id = sanitize($_POST['osca_id_number'] ?? '');
    $booklet_num = sanitize($_POST['booklet_number'] ?? '');
    $remarks = sanitize($_POST['remarks'] ?? '');
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
    $admin_override_reason = trim(sanitize($_POST['admin_override_reason'] ?? ''));

    $app_stmt = $db->prepare("SELECT a.*, u.user_id, u.first_name, u.last_name FROM senior_applications a JOIN users u ON a.user_id = u.user_id WHERE a.application_id = :id");
    $app_stmt->bindParam(':id', $app_id);
    $app_stmt->execute();
    $app = $app_stmt->fetch(PDO::FETCH_ASSOC);

    if ($app) {
        $current_status = strtolower($app['status'] ?? '');
        $allowed_statuses = ['reviewing', 'approved', 'released', 'rejected'];

        if (!in_array($new_status, $allowed_statuses)) {
            $error_msg = "Invalid status transition selected.";
        }
        // Business Rule 1: Step 5 (Release) backward transition to Step 2 requires valid administrative reason
        elseif ($current_status === 'released' && $new_status === 'reviewing' && (empty($admin_override_reason) || strlen($admin_override_reason) < 10)) {
            $error_msg = "Moving an application backward from Step 5 (Release) to Step 2 (Review) requires a valid administrative justification (minimum 10 characters).";
        }
        // Business Rule 2: Applications that have already reached releasing stage cannot be rejected through ordinary staff actions
        elseif ($current_status === 'released' && $new_status === 'rejected') {
            $error_msg = "Applications that have already reached Step 5 (Release) cannot be rejected through ordinary staff actions.";
        }
        elseif ($current_status === 'approved' && $new_status === 'rejected' && (empty($admin_override_reason) || strlen($admin_override_reason) < 10)) {
            $error_msg = "Applications that have already reached the releasing stage (Step 4: Approved) cannot be rejected through ordinary staff actions without a valid administrative revocation reason (minimum 10 characters).";
        }
        // Business Rule 3: Releasing credentials requires OSCA ID and Booklet Numbers
        elseif ($new_status === 'released' && (empty($osca_id) || empty($booklet_num))) {
            $error_msg = "Both OSCA ID Control Number and Purchase Booklet Number are required before releasing credentials.";
        }
        // Business Rule 4: Rejection requires a mandatory specific reason
        elseif ($new_status === 'rejected' && empty($rejection_reason)) {
            $error_msg = "A specific rejection reason is required when disqualifying an application.";
        }
        // Business Rule 5: Step 4 (Approved) returning to Step 2 (Review) requires explanatory remarks
        elseif ($current_status === 'approved' && $new_status === 'reviewing' && empty($remarks)) {
            $error_msg = "Please provide remarks explaining why the approved application is being returned to Step 2 (Review).";
        }
        else {
            // If administrative override was utilized, record in audit log and append note
            if (!empty($admin_override_reason)) {
                $remarks = ($remarks ? $remarks . " | " : "") . "[ADMIN OVERRIDE: " . $admin_override_reason . "]";
                try {
                    $audit_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:uid, 'senior_admin_override', 'senior_applications', :details)");
                    $audit_stmt->execute([
                        ':uid' => $_SESSION['user_id'],
                        ':details' => "Administrative override for App #{$app_id} ({$app['tracking_number']}) from {$current_status} to {$new_status}. Reason: {$admin_override_reason}"
                    ]);
                } catch (\Throwable $e) {}
            }

            $up = $db->prepare("UPDATE senior_applications SET status = :status, osca_id_number = :osca, booklet_number = :booklet, remarks = :remarks, rejection_reason = :rej, reviewed_by = :staff_id, updated_at = NOW() WHERE application_id = :id");
            $up->bindParam(':status', $new_status);
            $up->bindParam(':osca', $osca_id);
            $up->bindParam(':booklet', $booklet_num);
            $up->bindParam(':remarks', $remarks);
            $up->bindParam(':rej', $rejection_reason);
            $up->bindParam(':staff_id', $_SESSION['user_id']);
            $up->bindParam(':id', $app_id);
            $up->execute();

            // If approved or released, automatically verify resident as senior citizen
            if ($new_status === 'approved' || $new_status === 'released') {
                $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")->execute([':uid' => $app['user_id']]);
            }

            // Send notification to applicant
            $status_label = ($new_status === 'approved') ? 'Naaprubahan (Para sa pirma ng Punong Lungsod)' : (($new_status === 'released') ? 'Nai-release ang ID & Booklet' : ($new_status === 'reviewing' ? 'Kasalukuyang Sinusuri' : 'Tinanggihan'));
            $notif_title = "Senior Citizen Charter Service: " . ucfirst($new_status);
            $notif_msg = "Ang iyong aplikasyon para sa Senior Citizen ID & Purchase Booklet ({$app['tracking_number']}) ay na-update sa: {$status_label}.";
            if (!empty($osca_id)) {
                $notif_msg .= " OSCA ID: {$osca_id}.";
            }
            if (!empty($booklet_num)) {
                $notif_msg .= " Purchase Booklet No.: {$booklet_num}.";
            }
            if ($new_status === 'rejected' && !empty($rejection_reason)) {
                $notif_msg .= " Dahilan: {$rejection_reason}.";
            }
            $n_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'system')");
            $n_stmt->execute([':uid' => $app['user_id'], ':title' => $notif_title, ':msg' => $notif_msg]);

            $success_msg = "Aplikasyon ({$app['tracking_number']}) matagumpay na na-update!";
        }
    } else {
        $error_msg = "Application record not found.";
    }
}

// Handle Direct Senior Status Update for Resident
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_senior_status'])) {
    csrf_verify();
    $target_user_id = sanitize($_POST['user_id']);
    $senior_status = sanitize($_POST['senior_status']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $check_res = $db->prepare("SELECT id FROM residents WHERE user_id = :uid");
    $check_res->bindParam(':uid', $target_user_id);
    $check_res->execute();
    $res_data = $check_res->fetch(PDO::FETCH_ASSOC);
    
    if ($res_data) {
        $update_res = $db->prepare("UPDATE residents SET senior_citizen = :sc WHERE user_id = :uid");
        $update_res->bindParam(':sc', $senior_status);
        $update_res->bindParam(':uid', $target_user_id);
        $update_res->execute();
    } else {
        $insert_res = $db->prepare("INSERT INTO residents (user_id, senior_citizen, is_active) VALUES (:uid, :sc, 1)");
        $insert_res->bindParam(':sc', $senior_status);
        $insert_res->bindParam(':uid', $target_user_id);
        $insert_res->execute();
    }
    
    $status_label = ($senior_status == 1) ? 'Verified Senior Citizen' : 'Pending Verification';
    $notif_msg = "Your Senior Citizen status has been updated to: " . $status_label;
    if (!empty($remarks)) $notif_msg .= ". Note: " . $remarks;
    
    $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, 'Senior Citizen Status Update', :msg, 'system')");
    $notif_stmt->execute([':uid' => $target_user_id, ':msg' => $notif_msg]);
    
    $success_msg = "Resident status updated successfully!";
}

// Active Tab
$active_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'applications';

// Application Filters & Query
$app_status_filter = isset($_GET['app_status']) ? sanitize($_GET['app_status']) : 'all';
$app_type_filter   = isset($_GET['app_type']) ? sanitize($_GET['app_type']) : 'all';
$app_search        = isset($_GET['app_search']) ? trim(sanitize($_GET['app_search'])) : '';
$app_date          = isset($_GET['app_date']) ? sanitize($_GET['app_date']) : '';

$apps_query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture,
                      r.barangay, r.birth_date, r.resident_number
               FROM senior_applications a
               JOIN users u ON a.user_id = u.user_id
               LEFT JOIN residents r ON a.user_id = r.user_id
               WHERE 1=1";
$app_params = [];

if ($app_status_filter !== 'all' && !empty($app_status_filter)) {
    $apps_query .= " AND a.status = :st";
    $app_params[':st'] = $app_status_filter;
}
if ($app_type_filter !== 'all' && !empty($app_type_filter)) {
    $apps_query .= " AND a.application_type = :atype";
    $app_params[':atype'] = $app_type_filter;
}
if (!empty($app_search)) {
    $apps_query .= " AND (a.tracking_number LIKE :s 
                      OR u.first_name LIKE :s 
                      OR u.last_name LIKE :s 
                      OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s 
                      OR u.contact_number LIKE :s 
                      OR u.email LIKE :s 
                      OR a.osca_id_number LIKE :s 
                      OR a.booklet_number LIKE :s)";
    $app_params[':s'] = "%$app_search%";
}
if (!empty($app_date)) {
    $apps_query .= " AND DATE(a.created_at) = :adate";
    $app_params[':adate'] = $app_date;
}

$apps_query .= " ORDER BY a.created_at DESC";
$apps_stmt = $db->prepare($apps_query);
$apps_stmt->execute($app_params);
$applications = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

// Masterlist Filter & Query
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : 'all';
$search = isset($_GET['search']) ? trim(sanitize($_GET['search'])) : '';

$reg_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.profile_picture,
                     r.id as resident_id, r.resident_number, r.birth_date, r.gender, r.barangay, r.senior_citizen, r.emergency_contact_name, r.emergency_contact_number
              FROM residents r
              JOIN users u ON r.user_id = u.user_id
              WHERE r.senior_citizen = 1";
$reg_params = [];
if ($barangay_filter !== 'all' && !empty($barangay_filter)) {
    $reg_query .= " AND r.barangay = :bgy";
    $reg_params[':bgy'] = $barangay_filter;
}
if (!empty($search)) {
    $reg_query .= " AND (u.first_name LIKE :s OR u.last_name LIKE :s OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s OR u.email LIKE :s OR r.barangay LIKE :s OR r.resident_number LIKE :s OR u.contact_number LIKE :s)";
    $reg_params[':s'] = "%$search%";
}
$reg_query .= " ORDER BY u.last_name ASC";
$reg_stmt = $db->prepare($reg_query);
$reg_stmt->execute($reg_params);
$registered_seniors = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$app_metrics = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM senior_applications")->fetch(PDO::FETCH_ASSOC);

$total_registered_seniors = $db->query("SELECT COUNT(*) FROM residents WHERE senior_citizen = 1")->fetchColumn();
$barangays = $db->query("SELECT DISTINCT barangay FROM residents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);

// Staff user details & initials helper
if (!function_exists('getSeniorInitials')) {
    function getSeniorInitials($name) {
        $parts = explode(' ', trim($name));
        $in = '';
        foreach ($parts as $p) {
            if (!empty($p)) $in .= strtoupper(substr($p, 0, 1));
        }
        return substr($in, 0, 2) ?: 'SC';
    }
}

$page_title = 'Senior Citizen Services & Applications - CSWDO Staff Portal';
$active_nav = 'senior';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--ref-card); border: 1px solid var(--ref-border); border-radius: var(--ref-radius-lg); padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; text-decoration: none; color: inherit; box-shadow: var(--ref-shadow-sm); transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; cursor: pointer; }
.stat-card:hover { transform: translateY(-2px); border-color: #CBD5E1; box-shadow: var(--ref-shadow-md); }
.stat-card.active-metric { border-color: var(--ref-maroon); background: rgba(127, 3, 3, 0.05); box-shadow: 0 4px 12px rgba(127, 3, 3, 0.08); }
.stat-content { display: flex; flex-direction: column; gap: 3px; }
.stat-micro-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ref-muted); }
.stat-value { font-size: 1.85rem; font-weight: 800; color: var(--ref-text); line-height: 1.1; letter-spacing: -0.02em; }
.stat-hint { font-size: 0.74rem; color: var(--ref-muted); display: flex; align-items: center; gap: 4px; margin-top: 2px; }
.stat-icon-container { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; transition: transform 0.2s ease; }
.stat-card:hover .stat-icon-container { transform: scale(1.05); }
.stat-icon-container.pending { background: #FEF3C7; color: #D97706; }
.stat-icon-container.approved { background: #DCFCE7; color: #15803D; }
.stat-icon-container.released { background: #CCFBF1; color: #0F766E; }
.stat-icon-container.total { background: #FEE2E2; color: var(--ref-maroon); }

.pipeline-tabs-wrapper { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--ref-border); margin-bottom: 20px; }
.pipeline-tabs { display: flex; gap: 8px; }
.tab-link { padding: 10px 18px; font-size: 0.88rem; font-weight: 600; color: var(--ref-muted); text-decoration: none; border-bottom: 2.5px solid transparent; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; position: relative; bottom: -1px; }
.tab-link:hover { color: var(--ref-text); }
.tab-link.active { color: var(--ref-maroon); border-bottom-color: var(--ref-maroon); font-weight: 700; }
.tab-count-badge { background: var(--ref-canvas); color: var(--ref-muted); font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; transition: all 0.2s; }
.tab-link.active .tab-count-badge { background: rgba(127, 3, 3, 0.1); color: var(--ref-maroon); }

.filter-toolbar-card { background: var(--ref-card); border: 1px solid var(--ref-border); border-radius: var(--ref-radius-lg); padding: 16px 20px; margin-bottom: 22px; box-shadow: var(--ref-shadow-sm); }
.filter-toolbar-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; }
.filter-item { display: flex; flex-direction: column; gap: 5px; }
.filter-item.grow { flex: 1 1 240px; min-width: 200px; }
.filter-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ref-muted); display: flex; align-items: center; gap: 5px; }
.input-wrapper { position: relative; display: flex; align-items: center; }
.input-icon { position: absolute; left: 12px; font-size: 0.82rem; color: var(--ref-muted); pointer-events: none; }
.filter-control { width: 100%; height: 40px; padding: 0 12px 0 34px; border: 1px solid var(--ref-border); border-radius: 10px; background: var(--ref-card-subtle); color: var(--ref-text); font-family: inherit; font-size: 0.84rem; outline: none; transition: all 0.2s ease; box-sizing: border-box; }
.filter-control.no-icon { padding-left: 12px; }
.filter-control:focus { border-color: var(--ref-maroon); background: var(--ref-card); box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08); }
.filter-clear-btn { position: absolute; right: 10px; background: none; border: none; color: var(--ref-muted); cursor: pointer; font-size: 0.78rem; padding: 4px; text-decoration: none; }
.filter-clear-btn:hover { color: var(--ref-text); }
.filter-button-group { display: flex; align-items: center; gap: 10px; height: 40px; }
.btn-apply-filter { background: var(--ref-maroon); color: white; border: 1px solid var(--ref-maroon); border-radius: 10px; height: 40px; padding: 0 18px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all 0.2s ease; box-shadow: var(--ref-shadow-sm); }
.btn-apply-filter:hover { background: #8E1616; transform: translateY(-1px); }
.btn-reset-filter { background: var(--ref-card-subtle); color: var(--ref-muted); border: 1px solid var(--ref-border); border-radius: 10px; height: 40px; padding: 0 14px; font-size: 0.84rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
.btn-reset-filter:hover { color: var(--ref-maroon); border-color: rgba(127, 3, 3, 0.2); }

.table-card { background: var(--ref-card); border: 1px solid var(--ref-border); border-radius: var(--ref-radius-lg); box-shadow: var(--ref-shadow-sm); overflow: hidden; margin-bottom: 24px; }
.table-header-meta { padding: 14px 20px; border-bottom: 1px solid var(--ref-border); display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; color: var(--ref-muted); background: var(--ref-card-subtle); }
.meta-counter { font-weight: 600; color: var(--ref-text); }
.table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.senior-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem; min-width: 1060px; }
.senior-table thead th { background: var(--ref-card-subtle); color: var(--ref-muted); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1px solid var(--ref-border); white-space: nowrap; }
.senior-table tbody tr { border-bottom: 1px solid var(--ref-border); transition: background 0.15s ease; }
.senior-table tbody tr:hover { background: rgba(127, 3, 3, 0.02); }
.senior-table tbody tr:last-child { border-bottom: none; }
.senior-table tbody td { padding: 14px 16px; vertical-align: middle; color: var(--ref-text-subtle); }

.tracking-chip { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.78rem; font-weight: 700; color: var(--ref-text); background: var(--ref-card-subtle); border: 1px solid var(--ref-border); padding: 4px 8px; border-radius: 6px; display: inline-block; white-space: nowrap; }
.applicant-cell { display: flex; align-items: center; gap: 12px; min-width: 180px; }
.applicant-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--ref-maroon), #4A0202); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; border: 1.5px solid rgba(255, 255, 255, 0.15); overflow: hidden; }
.applicant-avatar img { width: 100%; height: 100%; object-fit: cover; }
.applicant-info { display: flex; flex-direction: column; gap: 2px; }
.applicant-name { font-weight: 700; color: var(--ref-text); font-size: 0.88rem; line-height: 1.25; }
.applicant-sub { font-size: 0.76rem; color: var(--ref-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.age-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
.age-badge.senior-qualified { background: #DCFCE7; color: #15803D; border: 1px solid rgba(21, 128, 61, 0.2); }
.age-badge.underage { background: #FEE2E2; color: #B91C1C; border: 1px solid rgba(185, 28, 28, 0.2); }
.app-type-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; color: var(--ref-text); }
.proofs-container { display: flex; gap: 6px; flex-wrap: wrap; max-width: 220px; }
.proof-chip { padding: 3px 8px; border-radius: 6px; font-size: 0.73rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.18s ease; background: var(--ref-card-subtle); color: var(--ref-text-subtle); border: 1px solid var(--ref-border); }
.proof-chip:hover { border-color: var(--ref-maroon); color: var(--ref-maroon); background: rgba(127, 3, 3, 0.05); transform: translateY(-1px); }
.proof-none { font-size: 0.75rem; color: var(--ref-muted); font-style: italic; }

.status-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.74rem; font-weight: 700; white-space: nowrap; }
.status-chip .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.status-chip.submitted { background: #E0F2FE; color: #0369A1; border: 1px solid rgba(3, 105, 161, 0.2); }
.status-chip.submitted .status-dot { background: #0284C7; }
.status-chip.reviewing { background: #FEF3C7; color: #B45309; border: 1px solid rgba(180, 83, 9, 0.2); }
.status-chip.reviewing .status-dot { background: #F59E0B; }
.status-chip.approved { background: #DCFCE7; color: #15803D; border: 1px solid rgba(21, 128, 61, 0.2); }
.status-chip.approved .status-dot { background: #16A34A; }
.status-chip.released { background: #CCFBF1; color: #0F766E; border: 1px solid rgba(15, 118, 110, 0.2); }
.status-chip.released .status-dot { background: #14B8A6; }
.status-chip.rejected { background: #FEE2E2; color: #B91C1C; border: 1px solid rgba(185, 28, 28, 0.2); }
.status-chip.rejected .status-dot { background: #EF4444; }

.status-identifiers { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; }
.identifier-tag { font-size: 0.72rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.identifier-tag.osca { color: #15803D; }
.identifier-tag.booklet { color: #0284C7; }

.btn-table-action { padding: 6px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--ref-border); background: var(--ref-card); color: var(--ref-text); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; box-shadow: var(--ref-shadow-sm); }
.btn-table-action:hover { border-color: var(--ref-maroon); background: var(--ref-maroon); color: #FFFFFF; transform: translateY(-1px); }
.table-empty-state { text-align: center; padding: 50px 20px; color: var(--ref-muted); }
.table-empty-state i { font-size: 2.5rem; opacity: 0.3; color: var(--ref-maroon); margin-bottom: 12px; display: block; }
.table-empty-state h4 { font-size: 0.96rem; font-weight: 700; color: var(--ref-text); margin-bottom: 4px; }
.table-empty-state p { font-size: 0.82rem; max-width: 320px; margin: 0 auto; }

.alert-custom { border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-left: 4px solid; box-shadow: var(--ref-shadow-sm); }
.alert-custom.alert-success { background: #F0FDF4; color: #166534; border-left-color: #22C55E; }
.alert-custom.alert-error { background: #FEF2F2; color: #991B1B; border-left-color: #EF4444; }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; backdrop-filter: blur(4px); padding: 20px; }
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal-box { background: var(--ref-card); padding: 26px 28px; border-radius: var(--ref-radius-xl); max-width: 520px; width: 100%; box-shadow: var(--ref-shadow-lg); border: 1px solid var(--ref-border); transform: translateY(-15px) scale(0.98); transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); box-sizing: border-box; }
.modal-overlay.active .modal-box { transform: translateY(0) scale(1); }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--ref-border); }
.modal-header h3 { font-size: 1.15rem; font-weight: 700; color: var(--ref-text); display: flex; align-items: center; gap: 8px; margin: 0; }
.modal-close-btn { background: none; border: none; color: var(--ref-muted); cursor: pointer; font-size: 1.1rem; padding: 4px; transition: color 0.2s; }
.modal-close-btn:hover { color: var(--ref-text); }
.applicant-summary-card { background: var(--ref-card-subtle); border: 1px solid var(--ref-border); border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; display: flex; flex-direction: column; gap: 4px; }
.modal-form-group { margin-bottom: 16px; }
.modal-form-label { display: block; font-size: 0.78rem; font-weight: 700; color: var(--ref-text); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
.modal-form-control { width: 100%; padding: 9px 12px; border: 1.5px solid var(--ref-border); border-radius: 10px; background: var(--ref-card); color: var(--ref-text); font-family: inherit; font-size: 0.85rem; outline: none; transition: all 0.2s ease; box-sizing: border-box; }
.modal-form-control:focus { border-color: var(--ref-maroon); box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08); }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--ref-border); }

@media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr; }
    .filter-toolbar-form { flex-direction: column; align-items: stretch; }
    .filter-item.grow, .filter-button-group { width: 100%; }
    .btn-apply-filter, .btn-reset-filter { flex: 1; justify-content: center; }
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

        <!-- Elevated Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Senior Citizens Registry (OSCA)';
            $banner_subtitle = 'Process OSCA ID issuances, purchase booklets, social pensions, and manage the official registry';
            $banner_icon = 'fa-person-cane';
            // Banner action buttons removed as requested
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <!-- Notification Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-custom alert-success" id="successAlert">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                    <button type="button" onclick="document.getElementById('successAlert').remove();" style="background:none; border:none; cursor:pointer; color:inherit; font-size:1rem;"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-custom alert-error" id="errorAlert">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                    <button type="button" onclick="document.getElementById('errorAlert').remove();" style="background:none; border:none; cursor:pointer; color:inherit; font-size:1rem;"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Metric Stat Cards (Responsive 4-Column Grid) -->
            <div class="stats-grid">
                <a href="manage-senior.php?tab=applications&app_status=submitted" 
                   class="stat-card <?php echo ($active_tab === 'applications' && $app_status_filter === 'submitted') ? 'active-metric' : ''; ?>" 
                   title="Filter by Pending Applications">
                    <div class="stat-content">
                        <span class="stat-micro-label">Pending Review</span>
                        <span class="stat-value"><?php echo (int)($app_metrics['pending'] ?? 0); ?></span>
                        <span class="stat-hint"><i class="fas fa-clock"></i> Needs Verification</span>
                    </div>
                    <div class="stat-icon-container pending">
                        <i class="fas fa-inbox"></i>
                    </div>
                </a>

                <a href="manage-senior.php?tab=applications&app_status=approved" 
                   class="stat-card <?php echo ($active_tab === 'applications' && $app_status_filter === 'approved') ? 'active-metric' : ''; ?>" 
                   title="Filter by Approved Applications">
                    <div class="stat-content">
                        <span class="stat-micro-label">Approved Applications</span>
                        <span class="stat-value"><?php echo (int)($app_metrics['approved'] ?? 0); ?></span>
                        <span class="stat-hint"><i class="fas fa-signature"></i> For Mayor's Signature</span>
                    </div>
                    <div class="stat-icon-container approved">
                        <i class="fas fa-user-check"></i>
                    </div>
                </a>

                <a href="manage-senior.php?tab=applications&app_status=released" 
                   class="stat-card <?php echo ($active_tab === 'applications' && $app_status_filter === 'released') ? 'active-metric' : ''; ?>" 
                   title="Filter by Released ID/Booklets">
                    <div class="stat-content">
                        <span class="stat-micro-label">Released ID / Booklet</span>
                        <span class="stat-value"><?php echo (int)($app_metrics['released'] ?? 0); ?></span>
                        <span class="stat-hint"><i class="fas fa-hand-holding-box"></i> Handed Over</span>
                    </div>
                    <div class="stat-icon-container released">
                        <i class="fas fa-id-card"></i>
                    </div>
                </a>

                <a href="manage-senior.php?tab=masterlist" 
                   class="stat-card <?php echo ($active_tab === 'masterlist') ? 'active-metric' : ''; ?>" 
                   title="View Verified Senior Citizens Registry">
                    <div class="stat-content">
                        <span class="stat-micro-label">Verified Registry</span>
                        <span class="stat-value"><?php echo (int)$total_registered_seniors; ?></span>
                        <span class="stat-hint"><i class="fas fa-users"></i> Masterlist Total</span>
                    </div>
                    <div class="stat-icon-container total">
                        <i class="fas fa-address-book"></i>
                    </div>
                </a>
            </div>

            <!-- Pipeline Tabs -->
            <div class="pipeline-tabs-wrapper">
                <div class="pipeline-tabs">
                    <a href="manage-senior.php?tab=applications" class="tab-link <?php echo $active_tab === 'applications' ? 'active' : ''; ?>">
                        <i class="fas fa-folder-tree"></i> 
                        <span>Incoming Applications</span>
                        <span class="tab-count-badge"><?php echo (int)($app_metrics['total'] ?? 0); ?></span>
                    </a>
                    <a href="manage-senior.php?tab=masterlist" class="tab-link <?php echo $active_tab === 'masterlist' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i> 
                        <span>Verified Senior Registry</span>
                        <span class="tab-count-badge"><?php echo (int)$total_registered_seniors; ?></span>
                    </a>
                </div>
            </div>

            <?php if ($active_tab === 'applications'): ?>
            <!-- TAB 1: APPLICATIONS WORKFLOW -->
            
            <!-- Filter Bar Toolbar -->
            <div class="filter-toolbar-card">
                <form method="GET" class="filter-toolbar-form">
                    <input type="hidden" name="tab" value="applications">

                    <!-- Search Input -->
                    <div class="filter-item grow">
                        <label class="filter-label"><i class="fas fa-search"></i> Search Applicant / Tracking</label>
                        <div class="input-wrapper">
                            <i class="fas fa-magnifying-glass input-icon"></i>
                            <input type="text" name="app_search" class="filter-control" 
                                   placeholder="Search name, tracking #, OSCA ID, phone..." 
                                   value="<?php echo htmlspecialchars($app_search); ?>">
                            <?php if (!empty($app_search)): ?>
                                <a href="manage-senior.php?tab=applications<?php echo $app_status_filter !== 'all' ? '&app_status='.urlencode($app_status_filter) : ''; ?>" class="filter-clear-btn" title="Clear search"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Dropdown -->
                    <div class="filter-item">
                        <label class="filter-label"><i class="fas fa-tag"></i> Status</label>
                        <select name="app_status" class="filter-control no-icon" style="min-width: 170px;">
                            <option value="all" <?php echo $app_status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="submitted" <?php echo $app_status_filter === 'submitted' ? 'selected' : ''; ?>>Pending / Submitted</option>
                            <option value="reviewing" <?php echo $app_status_filter === 'reviewing' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="approved" <?php echo $app_status_filter === 'approved' ? 'selected' : ''; ?>>Approved (For Signature)</option>
                            <option value="released" <?php echo $app_status_filter === 'released' ? 'selected' : ''; ?>>Released ID & Booklet</option>
                            <option value="rejected" <?php echo $app_status_filter === 'rejected' ? 'selected' : ''; ?>>Did Not Qualify / Rejected</option>
                        </select>
                    </div>

                    <!-- Application Type Dropdown -->
                    <div class="filter-item">
                        <label class="filter-label"><i class="fas fa-layer-group"></i> Type</label>
                        <select name="app_type" class="filter-control no-icon" style="min-width: 180px;">
                            <option value="all" <?php echo $app_type_filter === 'all' ? 'selected' : ''; ?>>All Services</option>
                            <option value="id_issuance" <?php echo $app_type_filter === 'id_issuance' ? 'selected' : ''; ?>>OSCA ID Issuance</option>
                            <option value="social_pension" <?php echo $app_type_filter === 'social_pension' ? 'selected' : ''; ?>>Social Pension</option>
                            <option value="purchase_booklet" <?php echo $app_type_filter === 'purchase_booklet' ? 'selected' : ''; ?>>Purchase Booklet</option>
                            <option value="financial_aid" <?php echo $app_type_filter === 'financial_aid' ? 'selected' : ''; ?>>Financial / Medical Aid</option>
                        </select>
                    </div>

                    <!-- Date Filter -->
                    <div class="filter-item">
                        <label class="filter-label"><i class="fas fa-calendar-day"></i> Submission Date</label>
                        <div class="input-wrapper">
                            <i class="fas fa-calendar input-icon"></i>
                            <input type="date" name="app_date" class="filter-control" value="<?php echo htmlspecialchars($app_date); ?>" style="min-width: 155px;">
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="filter-button-group">
                        <button type="submit" class="btn-apply-filter">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <?php if ($app_status_filter !== 'all' || $app_type_filter !== 'all' || !empty($app_search) || !empty($app_date)): ?>
                            <a href="manage-senior.php?tab=applications" class="btn-reset-filter" title="Reset all filters">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Applications Data Table Card -->
            <div class="table-card">
                <div class="table-header-meta">
                    <div>
                        <span>Showing <span class="meta-counter"><?php echo count($applications); ?></span> Senior Applications</span>
                        <?php if ($app_status_filter !== 'all' || !empty($app_search) || !empty($app_date)): ?>
                            <span style="margin-left: 8px; font-weight: 500;">(Filtered)</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span>Sorted by: <strong>Latest Submission</strong></span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Tracking #</th>
                                <th style="min-width: 220px;">Applicant</th>
                                <th style="width: 90px; text-align: center;">Age</th>
                                <th style="min-width: 190px;">Application Type</th>
                                <th style="min-width: 190px;">Attached Proofs</th>
                                <th style="min-width: 170px;">Status</th>
                                <th style="width: 120px;">Submitted</th>
                                <th style="width: 110px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="table-empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <h4>No senior applications found</h4>
                                        <p>Try adjusting your search query, status filters, or submission date.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                            <?php 
                                $age_num = 0;
                                $age_display = 'N/A';
                                if (!empty($app['birth_date'])) {
                                    $birth = new DateTime($app['birth_date']);
                                    $today = new DateTime();
                                    $diff = $today->diff($birth);
                                    $age_num = $diff->y;
                                    $age_display = $age_num . ' yrs';
                                }

                                $type_label = 'Senior Citizen OSCA ID';
                                $type_icon = 'fa-id-card';
                                $type_color = '#0284C7';
                                if ($app['application_type'] === 'social_pension') {
                                    $type_label = 'Social Pension Application';
                                    $type_icon = 'fa-hand-holding-dollar';
                                    $type_color = '#059669';
                                } elseif ($app['application_type'] === 'purchase_booklet') {
                                    $type_label = 'Purchase Booklet Request';
                                    $type_icon = 'fa-book-medical';
                                    $type_color = '#7C3AED';
                                } elseif ($app['application_type'] === 'financial_aid') {
                                    $type_label = 'Financial / Medical Aid';
                                    $type_icon = 'fa-coins';
                                    $type_color = '#D97706';
                                }

                                $full_name = trim($app['first_name'] . ' ' . $app['last_name']);
                                $app_initials = getSeniorInitials($full_name ?: 'Applicant');
                                $user_pic = !empty($app['profile_picture']) ? $app['profile_picture'] : '';
                            ?>
                            <tr>
                                <!-- 1. Tracking # -->
                                <td>
                                    <span class="tracking-chip"><?php echo htmlspecialchars($app['tracking_number']); ?></span>
                                </td>

                                <!-- 2. Applicant -->
                                <td>
                                    <div class="applicant-cell">
                                        <div class="applicant-avatar">
                                            <?php if (!empty($user_pic) && file_exists('../' . $user_pic)): ?>
                                                <img src="../<?php echo htmlspecialchars($user_pic); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo $app_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="applicant-info">
                                            <span class="applicant-name"><?php echo htmlspecialchars($full_name); ?></span>
                                            <div class="applicant-sub">
                                                <span><i class="fas fa-map-marker-alt" style="color: var(--maroon);"></i> <?php echo htmlspecialchars($app['barangay'] ?? 'Palayan City'); ?></span>
                                                <?php if (!empty($app['contact_number'])): ?>
                                                    <span>•</span>
                                                    <span><?php echo htmlspecialchars($app['contact_number']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 3. Age -->
                                <td style="text-align: center;">
                                    <?php if ($age_num > 0): ?>
                                        <span class="age-badge <?php echo ($age_num >= 60) ? 'senior-qualified' : 'underage'; ?>" 
                                              title="<?php echo ($age_num >= 60) ? 'Eligible Senior Citizen (60+ years old)' : 'Does not meet 60+ minimum age requirement'; ?>">
                                            <i class="fas <?php echo ($age_num >= 60) ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                                            <?php echo $age_display; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="proof-none">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 4. Application Type -->
                                <td>
                                    <span class="app-type-chip">
                                        <i class="fas <?php echo $type_icon; ?>" style="color: <?php echo $type_color; ?>;"></i>
                                        <span><?php echo htmlspecialchars($type_label); ?></span>
                                    </span>
                                </td>

                                <!-- 5. Attached Proofs -->
                                <td>
                                    <div class="proofs-container">
                                        <?php $has_proofs = false; ?>
                                        <?php if (!empty($app['valid_id_path'])): $has_proofs = true; ?>
                                            <a href="../<?php echo htmlspecialchars($app['valid_id_path']); ?>" target="_blank" class="proof-chip" title="View Gov ID Document">
                                                <i class="fas fa-id-card" style="color:#0284C7;"></i> Gov ID
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($app['id_picture_path'])): $has_proofs = true; ?>
                                            <a href="../<?php echo htmlspecialchars($app['id_picture_path']); ?>" target="_blank" class="proof-chip" title="View 2x2 / ID Photo">
                                                <i class="fas fa-portrait" style="color:#059669;"></i> Photo
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($app['barangay_indigency_path'])): $has_proofs = true; ?>
                                            <a href="../<?php echo htmlspecialchars($app['barangay_indigency_path']); ?>" target="_blank" class="proof-chip" title="View Barangay Indigency / Certificate">
                                                <i class="fas fa-file-lines" style="color:#D97706;"></i> Indigency
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$has_proofs): ?>
                                            <span class="proof-none">No uploads</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- 6. Status & Identifiers -->
                                <td>
                                    <?php 
                                        $st = $app['status'];
                                        $st_title = ucfirst($st);
                                        if ($st === 'submitted') $st_title = 'Pending Review';
                                        elseif ($st === 'reviewing') $st_title = 'Under Review';
                                        elseif ($st === 'approved') $st_title = 'Approved';
                                        elseif ($st === 'released') $st_title = 'Released ID';
                                        elseif ($st === 'rejected') $st_title = 'Rejected';
                                    ?>
                                    <span class="status-chip <?php echo htmlspecialchars($st); ?>">
                                        <span class="status-dot"></span>
                                        <?php echo $st_title; ?>
                                    </span>
                                    
                                    <?php if (!empty($app['osca_id_number']) || !empty($app['booklet_number'])): ?>
                                        <div class="status-identifiers">
                                            <?php if (!empty($app['osca_id_number'])): ?>
                                                <span class="identifier-tag osca" title="OSCA Control ID">
                                                    <i class="fas fa-id-card"></i> OSCA: <?php echo htmlspecialchars($app['osca_id_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($app['booklet_number'])): ?>
                                                <span class="identifier-tag booklet" title="Purchase Booklet No.">
                                                    <i class="fas fa-book-medical"></i> BK: <?php echo htmlspecialchars($app['booklet_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 7. Date -->
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <span style="font-weight: 600; color: var(--text-primary); font-size: 0.82rem;">
                                            <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                        </span>
                                        <span style="font-size: 0.74rem; color: var(--text-muted);">
                                            <?php echo date('h:i A', strtotime($app['created_at'])); ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- 8. Action -->
                                <td style="text-align: right;">
                                    <button type="button" class="btn-table-action" onclick='openAppModal(<?php echo json_encode($app); ?>)'>
                                        <i class="fas fa-gavel" style="color: var(--maroon);"></i>
                                        <span>Decision</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: ?>
            <!-- TAB 2: VERIFIED SENIOR MASTERLIST -->
            
            <!-- Masterlist Filter Toolbar -->
            <div class="filter-toolbar-card">
                <form method="GET" class="filter-toolbar-form">
                    <input type="hidden" name="tab" value="masterlist">

                    <div class="filter-item grow">
                        <label class="filter-label"><i class="fas fa-search"></i> Search Registry</label>
                        <div class="input-wrapper">
                            <i class="fas fa-magnifying-glass input-icon"></i>
                            <input type="text" name="search" class="filter-control" 
                                   placeholder="Search by resident name, resident ID, or email..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <?php if (!empty($search)): ?>
                                <a href="manage-senior.php?tab=masterlist<?php echo $barangay_filter !== 'all' ? '&barangay='.urlencode($barangay_filter) : ''; ?>" class="filter-clear-btn" title="Clear search"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="filter-item">
                        <label class="filter-label"><i class="fas fa-map-marker-alt"></i> Barangay</label>
                        <select name="barangay" class="filter-control no-icon" style="min-width: 190px;">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays as $bgy): ?>
                                <option value="<?php echo htmlspecialchars($bgy); ?>" <?php echo $barangay_filter === $bgy ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bgy); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-button-group">
                        <button type="submit" class="btn-apply-filter">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($barangay_filter !== 'all' || !empty($search)): ?>
                            <a href="manage-senior.php?tab=masterlist" class="btn-reset-filter" title="Reset filters">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Masterlist Table Card -->
            <div class="table-card">
                <div class="table-header-meta">
                    <div>
                        <span>Showing <span class="meta-counter"><?php echo count($registered_seniors); ?></span> Official Verified Senior Citizens</span>
                    </div>
                    <div>
                        <span>Location: <strong>Palayan City, Nueva Ecija</strong></span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th style="width: 130px;">Resident ID</th>
                                <th style="min-width: 240px;">Senior Citizen</th>
                                <th style="width: 100px; text-align: center;">Age</th>
                                <th style="min-width: 150px;">Barangay</th>
                                <th style="min-width: 140px;">Contact</th>
                                <th style="min-width: 200px;">Emergency Contact</th>
                                <th style="width: 130px; text-align: right;">Registry Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registered_seniors)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="table-empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <h4>No senior citizen records found</h4>
                                        <p>No verified senior citizens match the specified barangay or keyword search.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($registered_seniors as $row): ?>
                            <?php 
                                $age_display = 'N/A';
                                if (!empty($row['birth_date'])) {
                                    $birth = new DateTime($row['birth_date']);
                                    $today = new DateTime();
                                    $age_display = $today->diff($birth)->y . ' yrs';
                                }
                                $s_name = trim($row['first_name'] . ' ' . $row['last_name']);
                                $s_initials = getSeniorInitials($s_name ?: 'Senior');
                                $user_pic = !empty($row['profile_picture']) ? $row['profile_picture'] : '';
                            ?>
                            <tr>
                                <td>
                                    <span class="tracking-chip"><?php echo htmlspecialchars($row['resident_number'] ?? 'RES-ID'); ?></span>
                                </td>
                                <td>
                                    <div class="applicant-cell">
                                        <div class="applicant-avatar">
                                            <?php if (!empty($user_pic) && file_exists('../' . $user_pic)): ?>
                                                <img src="../<?php echo htmlspecialchars($user_pic); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo $s_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="applicant-info">
                                            <span class="applicant-name"><?php echo htmlspecialchars($s_name); ?></span>
                                            <span class="applicant-sub"><?php echo htmlspecialchars($row['email'] ?? 'No email listed'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="age-badge senior-qualified">
                                        <i class="fas fa-check"></i> <?php echo $age_display; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:5px; font-weight:600; color:var(--text-primary);">
                                        <i class="fas fa-map-marker-alt" style="color:var(--maroon);"></i>
                                        <?php echo htmlspecialchars($row['barangay'] ?? 'Palayan City'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.82rem; color:var(--text-secondary);">
                                        <?php echo htmlspecialchars($row['contact_number'] ?? 'None'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; flex-direction:column; gap:2px;">
                                        <span style="font-weight:600; color:var(--text-primary); font-size:0.82rem;">
                                            <?php echo htmlspecialchars($row['emergency_contact_name'] ?? 'Not specified'); ?>
                                        </span>
                                        <?php if (!empty($row['emergency_contact_number'])): ?>
                                            <span style="font-size:0.75rem; color:var(--text-muted);">
                                                <i class="fas fa-phone-alt" style="font-size:0.68rem;"></i> <?php echo htmlspecialchars($row['emergency_contact_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <span class="status-chip approved">
                                        <span class="status-dot"></span>
                                        Verified Senior
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

    <!-- Application Decision Modal -->
    <div class="modal-overlay" id="appDecisionModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-gavel" style="color: var(--maroon);"></i> Process Senior Application</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('appDecisionModal')">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_senior_application" value="1">
                <input type="hidden" name="application_id" id="decisionAppId">
                
                <!-- Resident Summary Card -->
                <div class="applicant-summary-card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color:var(--text-primary);" id="decisionAppName">Applicant</span>
                        <span class="tracking-chip" id="decisionTrackingNum">TRACKING</span>
                    </div>
                    <div style="font-size:0.78rem; color:var(--text-muted);" id="decisionAppDetails"></div>
                </div>

                <!-- Current Stage Notification Banner -->
                <div id="seniorStageBanner" style="margin-bottom: 14px; display: none;"></div>

                <div class="modal-form-group">
                    <label class="modal-form-label">Review Decision / Next Step *</label>
                    <select name="status" id="decisionStatus" required onchange="handleDecisionStatus(this.value)" class="modal-form-control">
                        <option value="reviewing">Step 2: Reviewing Documents & Requirements</option>
                        <option value="approved">Step 4: Approved & Endorsed to CMO for Mayor Signature</option>
                        <option value="released">Step 5: Released SC ID & Purchase Booklet</option>
                        <option value="rejected">Rejected / Disqualified (Lacks residency or <60 yo)</option>
                    </select>
                </div>

                <div id="oscaInputGroup">
                    <div class="modal-form-group">
                        <label class="modal-form-label">OSCA ID Control Number</label>
                        <input type="text" name="osca_id_number" id="decisionOscaId" placeholder="e.g. OSCA-2026-0042" class="modal-form-control">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Purchase Booklet Control No. (Medicine & Grocery)</label>
                        <input type="text" name="booklet_number" id="decisionBooklet" placeholder="e.g. BK-2026-0089" class="modal-form-control">
                    </div>
                </div>

                <!-- Administrative Override / Reversion Reason Field -->
                <div class="modal-form-group" style="display: none;" id="adminOverrideGroup">
                    <label class="modal-form-label" style="color: #B45309;">
                        <i class="fas fa-shield-alt"></i> Administrative Justification / Reason *
                    </label>
                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-bottom: 4px;" id="adminOverrideHint">
                        A valid administrative reason (minimum 10 characters) is required for this action.
                    </div>
                    <textarea name="admin_override_reason" id="decisionAdminReason" rows="2" placeholder="State official administrative justification..." class="modal-form-control"></textarea>
                </div>

                <div class="modal-form-group" style="display: none;" id="rejectionGroup">
                    <label class="modal-form-label" style="color: #DC2626;">Disqualification / Rejection Reason *</label>
                    <textarea name="rejection_reason" id="decisionRejection" rows="2" placeholder="Halimbawa: Hindi umabot sa 60 taong gulang o kulang ang patunay ng residency..." class="modal-form-control"></textarea>
                </div>

                <div class="modal-form-group">
                    <label class="modal-form-label">Social Worker Remarks / Office Notes</label>
                    <textarea name="remarks" id="decisionRemarks" rows="2" placeholder="Karagdagang tala ukol sa dokumento o schedule..." class="modal-form-control"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-reset-filter" onclick="closeModal('appDecisionModal')">Cancel</button>
                    <button type="submit" class="btn-apply-filter" onclick="return validateSeniorDecision(event)">
                        <i class="fas fa-check"></i> Save Decision
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let currentAppStatus = '';

    function openAppModal(app) {
        currentAppStatus = (app.status || '').toLowerCase();
        document.getElementById('decisionAppId').value = app.application_id;
        document.getElementById('decisionAppName').textContent = `${app.first_name} ${app.last_name}`;
        document.getElementById('decisionTrackingNum').textContent = app.tracking_number;
        
        const typeClean = (app.application_type || '').replace(/_/g, ' ').toUpperCase();
        document.getElementById('decisionAppDetails').textContent = `Type: ${typeClean} • Filed: ${app.created_at}`;
        
        document.getElementById('decisionOscaId').value = app.osca_id_number || '';
        document.getElementById('decisionBooklet').value = app.booklet_number || '';
        document.getElementById('decisionRemarks').value = app.remarks || '';
        document.getElementById('decisionRejection').value = app.rejection_reason || '';
        if (document.getElementById('decisionAdminReason')) {
            document.getElementById('decisionAdminReason').value = '';
        }

        const banner = document.getElementById('seniorStageBanner');
        const statusSelect = document.getElementById('decisionStatus');
        const optReviewing = statusSelect.querySelector('option[value="reviewing"]');
        const optApproved = statusSelect.querySelector('option[value="approved"]');
        const optReleased = statusSelect.querySelector('option[value="released"]');
        const optRejected = statusSelect.querySelector('option[value="rejected"]');

        // Reset option accessibility
        optReviewing.disabled = false;
        optApproved.disabled = false;
        optReleased.disabled = false;
        optRejected.disabled = false;
        optRejected.style.display = 'block';

        if (currentAppStatus === 'released') {
            banner.style.display = 'block';
            banner.innerHTML = '<div style="background:rgba(20,184,166,0.12); color:#0F766E; border:1px solid rgba(20,184,166,0.3); border-radius:10px; padding:10px 14px; font-size:0.82rem; font-weight:600;"><i class="fas fa-lock"></i> <strong>Step 5 (Released):</strong> Government ID & Booklet are released. Reversion to Step 2 (Review) requires an administrative justification. Rejection through ordinary actions is prohibited.</div>';
            // Workflow rule: Applications that have reached releasing stage cannot be rejected
            optRejected.disabled = true;
            optRejected.style.display = 'none';
            optApproved.disabled = true;
            statusSelect.value = 'released';
        } else if (currentAppStatus === 'approved') {
            banner.style.display = 'block';
            banner.innerHTML = '<div style="background:rgba(22,163,74,0.12); color:#15803D; border:1px solid rgba(22,163,74,0.3); border-radius:10px; padding:10px 14px; font-size:0.82rem; font-weight:600;"><i class="fas fa-check-circle"></i> <strong>Step 4 (Approved - Releasing Stage):</strong> Ready for Step 5 release. Rejection requires administrative revocation justification.</div>';
            statusSelect.value = 'approved';
        } else if (currentAppStatus === 'submitted') {
            banner.style.display = 'block';
            banner.innerHTML = '<div style="background:rgba(245,158,11,0.12); color:#B45309; border:1px solid rgba(245,158,11,0.3); border-radius:10px; padding:10px 14px; font-size:0.82rem; font-weight:600;"><i class="fas fa-clock"></i> <strong>Step 1 (Submitted):</strong> Verify documents and advance to Step 2 (Review).</div>';
            optReleased.disabled = true; // Cannot jump directly from Step 1 to Step 5
            statusSelect.value = 'reviewing';
        } else {
            banner.style.display = 'none';
            statusSelect.value = currentAppStatus || 'reviewing';
        }
        
        handleDecisionStatus(statusSelect.value);
        document.getElementById('appDecisionModal').classList.add('active');
    }

    function handleDecisionStatus(status) {
        const rejGroup = document.getElementById('rejectionGroup');
        const rejInput = document.getElementById('decisionRejection');
        const oscaGroup = document.getElementById('oscaInputGroup');
        const adminGroup = document.getElementById('adminOverrideGroup');
        const adminInput = document.getElementById('decisionAdminReason');
        const adminHint = document.getElementById('adminOverrideHint');

        // Rejection field visibility
        if (status === 'rejected') {
            rejGroup.style.display = 'block';
            rejInput.required = true;
        } else {
            rejGroup.style.display = 'none';
            rejInput.required = false;
        }

        // OSCA ID & Booklet input requirements
        if (status === 'released') {
            oscaGroup.style.display = 'block';
            document.getElementById('decisionOscaId').required = true;
            document.getElementById('decisionBooklet').required = true;
        } else if (status === 'approved') {
            oscaGroup.style.display = 'block';
            document.getElementById('decisionOscaId').required = false;
            document.getElementById('decisionBooklet').required = false;
        } else {
            document.getElementById('decisionOscaId').required = false;
            document.getElementById('decisionBooklet').required = false;
        }

        // Administrative override requirement based on workflow rules
        if (currentAppStatus === 'released' && status === 'reviewing') {
            adminGroup.style.display = 'block';
            adminInput.required = true;
            adminHint.textContent = 'Administrative reason required: state justification for rolling back officially released credentials to Step 2.';
        } else if (currentAppStatus === 'approved' && status === 'rejected') {
            adminGroup.style.display = 'block';
            adminInput.required = true;
            adminHint.textContent = 'Administrative revocation reason required: applications in releasing stage cannot be rejected through ordinary actions.';
        } else {
            adminGroup.style.display = 'none';
            adminInput.required = false;
        }
    }

    function validateSeniorDecision(e) {
        const status = document.getElementById('decisionStatus').value;
        const adminInput = document.getElementById('decisionAdminReason');

        if (currentAppStatus === 'released' && status === 'reviewing') {
            if (!adminInput || adminInput.value.trim().length < 10) {
                alert('A valid administrative reason (minimum 10 characters) is required to move an application backward from Step 5 (Release) to Step 2 (Review).');
                if (adminInput) adminInput.focus();
                e.preventDefault();
                return false;
            }
            if (!confirm('Warning: You are about to administratively roll back an officially RELEASED Senior Citizen application back to Step 2 (Review). Are you sure you want to proceed with this rollback?')) {
                e.preventDefault();
                return false;
            }
        } else if (currentAppStatus === 'approved' && status === 'rejected') {
            if (!adminInput || adminInput.value.trim().length < 10) {
                alert('A valid administrative revocation reason (minimum 10 characters) is required to reject an application that has already reached the releasing stage.');
                if (adminInput) adminInput.focus();
                e.preventDefault();
                return false;
            }
            if (!confirm('Are you sure you want to administratively revoke and reject this approved application?')) {
                e.preventDefault();
                return false;
            }
        } else if (status === 'released') {
            const osca = document.getElementById('decisionOscaId').value.trim();
            const bk = document.getElementById('decisionBooklet').value.trim();
            if (!osca || !bk) {
                alert('Both OSCA ID Control Number and Purchase Booklet Number are required before releasing credentials.');
                e.preventDefault();
                return false;
            }
            if (!confirm(`Confirm releasing Senior Citizen ID (${osca}) & Purchase Booklet (${bk}) to this applicant?`)) {
                e.preventDefault();
                return false;
            }
        } else if (status === 'rejected') {
            const reason = document.getElementById('decisionRejection').value.trim();
            if (!reason) {
                alert('Please provide a specific disqualification reason.');
                e.preventDefault();
                return false;
            }
            if (!confirm('Are you sure you want to disqualify/reject this application? An official notice will be sent to the resident.')) {
                e.preventDefault();
                return false;
            }
        }
        return true;
    }

    function closeModal(id) { 
        document.getElementById(id).classList.remove('active'); 
    }

    // Close modal on Escape or clicking outside
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('appDecisionModal');
        }
    });
    document.getElementById('appDecisionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal('appDecisionModal');
        }
    });
    </script>
    <?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>
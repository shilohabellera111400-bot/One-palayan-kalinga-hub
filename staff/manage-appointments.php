<?php
// staff/manage-appointments.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    $features_path = __DIR__ . '/../config/features.php';
    if (file_exists($features_path)) {
        require_once $features_path;
    }
}
$is_pwd_senior_enabled = defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR;

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isStaff() && !isAdmin()) {
    redirect('../dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get staff's ID from user_id
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $new_status = sanitize($_POST['status'] ?? ($_POST['new_status'] ?? ''));
    $status_notes = sanitize($_POST['status_notes'] ?? ($_POST['notes'] ?? ''));
    $window_assignment = sanitize($_POST['window_assignment'] ?? '');
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
    $new_date = sanitize($_POST['new_date'] ?? '');
    $new_time = sanitize($_POST['new_time'] ?? '');

    // Fetch appointment record
    $fetch_appt = $db->prepare("SELECT a.*, s.service_name, s.category_id, sc.category_name, u.user_id, u.first_name, u.last_name, u.email 
                                FROM appointments a 
                                LEFT JOIN services s ON a.service_id = s.service_id 
                                LEFT JOIN service_categories sc ON s.category_id = sc.category_id
                                JOIN users u ON a.user_id = u.user_id 
                                WHERE a.appointment_id = :id");
    $fetch_appt->bindParam(':id', $appointment_id);
    $fetch_appt->execute();
    $appt_info = $fetch_appt->fetch(PDO::FETCH_ASSOC);

    if ($appt_info) {
        // RBAC check: Ensure staff cannot perform actions outside their assigned responsibilities
        $cat_map = [
            'Social Assistance' => 'AICS',
            'AICS' => 'AICS',
            'Housing' => 'Housing',
            'Senior Citizen' => 'Senior Citizen',
            'PWD' => 'PWD'
        ];
        $appt_category = $appt_info['category_name'] ?? '';
        $effective_cat = $cat_map[$appt_category] ?? $appt_category;

        if (!isAdmin() && !empty($effective_cat) && !in_array($effective_cat, $assigned_categories)) {
            $error = "Access denied. You do not have permission to manage {$effective_cat} appointments.";
        } else {
            $current_status = strtolower($appt_info['status']);
        $valid_statuses = ['pending', 'for_review', 'confirmed', 'checked_in', 'in_progress', 'completed', 'rescheduled', 'rejected', 'no_show', 'cancelled', 'needs_information'];

        // Workflow Validation Rule 1: Once marked as Completed, staff can no longer modify status or details
        if ($current_status === 'completed') {
            $error = "This appointment has already been completed. Once completed, appointment records are permanently locked against modifications.";
        } elseif ($current_status === 'cancelled' && $new_status !== 'cancelled') {
            $error = "Cancelled appointments cannot be modified.";
        } elseif (!in_array($new_status, $valid_statuses)) {
            $error = "Invalid status selected: " . htmlspecialchars($new_status);
        } elseif ($new_status === 'rejected' && empty($rejection_reason) && empty($status_notes)) {
            $error = "A disapproval reason is required when disapproving an appointment.";
        } elseif ($new_status === 'rescheduled' && (empty($new_date) || empty($new_time))) {
            $error = "A new date and time are required when rescheduling an appointment.";
        } else {
            $update_sql = "UPDATE appointments SET status = :status";
            $update_params = [':status' => $new_status, ':id' => $appointment_id];

            if (!empty($window_assignment)) {
                $update_sql .= ", window_assignment = :window";
                $update_params[':window'] = $window_assignment;
            }
            if ($new_status === 'checked_in') {
                $update_sql .= ", checked_in_at = NOW(), checked_in_by = :staff_uid";
                $update_params[':staff_uid'] = $_SESSION['user_id'];
            } elseif ($new_status === 'in_progress') {
                $update_sql .= ", started_at = NOW(), started_by = :staff_uid";
                $update_params[':staff_uid'] = $_SESSION['user_id'];
            } elseif ($new_status === 'completed') {
                $update_sql .= ", completed_at = NOW(), completed_by = :staff_uid, service_outcome = :outcome";
                $update_params[':staff_uid'] = $_SESSION['user_id'];
                $update_params[':outcome'] = $status_notes ?: 'Service completed successfully.';
            } elseif ($new_status === 'cancelled') {
                $update_sql .= ", cancelled_at = NOW(), cancelled_by = :staff_uid";
                $update_params[':staff_uid'] = $_SESSION['user_id'];
            } elseif ($new_status === 'rejected') {
                $update_sql .= ", rejection_reason = :rejection";
                $update_params[':rejection'] = $rejection_reason ?: $status_notes;
            } elseif ($new_status === 'rescheduled' && !empty($new_date) && !empty($new_time)) {
                $update_sql .= ", appointment_date = :new_date, appointment_time = :new_time, reschedule_reason = :resched_reason";
                $update_params[':new_date'] = $new_date;
                $update_params[':new_time'] = $new_time;
                $update_params[':resched_reason'] = $status_notes ?: 'Rescheduled by staff.';
            }

            $update_sql .= " WHERE appointment_id = :id";
            $update_stmt = $db->prepare($update_sql);
            
            if ($update_stmt->execute($update_params)) {
                $success = "Appointment status successfully updated to: " . strtoupper($new_status);
                $audit_note = $status_notes ?: ("Status updated to " . strtoupper($new_status) . ($window_assignment ? " at " . $window_assignment : ""));
                if ($new_status === 'rejected' && !empty($rejection_reason)) {
                    $audit_note .= " Reason: " . $rejection_reason;
                }
                logAppointmentHistory($db, $appointment_id, $new_status, $audit_note, $_SESSION['user_id']);
                notifyAppointmentUpdate($db, $appointment_id, $new_status, $audit_note, $_SESSION['user_id']);

                if (!empty($_POST['return_to'])) {
                    $_SESSION['success'] = $success;
                    redirect($_POST['return_to']);
                }
            } else {
                $error = "Failed to update appointment status.";
            }
        }
        }
    } else {
        $error = "Appointment record not found.";
    }
}

// Handle appointment deletion (prevent deleting completed appointments & enforce RBAC)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    
    $chk_del = $db->prepare("SELECT a.status, sc.category_name 
                             FROM appointments a 
                             LEFT JOIN services s ON a.service_id = s.service_id 
                             LEFT JOIN service_categories sc ON s.category_id = sc.category_id 
                             WHERE a.appointment_id = :id");
    $chk_del->execute([':id' => $appointment_id]);
    $del_info = $chk_del->fetch(PDO::FETCH_ASSOC);

    if (!$del_info) {
        $error = "Appointment record not found.";
    } elseif (strtolower($del_info['status']) === 'completed') {
        $error = "Completed appointments cannot be deleted to preserve transaction audit records.";
    } else {
        $cat_map = ['Social Assistance' => 'AICS', 'AICS' => 'AICS', 'Housing' => 'Housing', 'Senior Citizen' => 'Senior Citizen', 'PWD' => 'PWD'];
        $del_cat = $cat_map[$del_info['category_name'] ?? ''] ?? ($del_info['category_name'] ?? '');
        if (!isAdmin() && !empty($del_cat) && !in_array($del_cat, $assigned_categories)) {
            $error = "Access denied. You do not have permission to delete {$del_cat} appointments.";
        } else {
            $delete_query = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':appointment_id', $appointment_id);
            
            if ($delete_stmt->execute()) {
                $success = "Appointment deleted successfully!";
            } else {
                $error = "Failed to delete appointment.";
            }
        }
    }
}

// Fetch all available active services for dropdown filter
$services_list_stmt = $db->query("SELECT service_id, service_name FROM services WHERE is_active = 1 ORDER BY service_name ASC");
$all_services = $services_list_stmt ? $services_list_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$service_filter = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$window_filter = isset($_GET['window']) ? sanitize($_GET['window']) : 'all';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$search_query = isset($_GET['search']) ? trim(sanitize($_GET['search'])) : '';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build the base query with filters
$base_query = "SELECT a.*, s.service_name, u.first_name, u.last_name, u.email, u.contact_number, r.barangay
               FROM appointments a
               LEFT JOIN services s ON a.service_id = s.service_id
               JOIN users u ON a.user_id = u.user_id
               LEFT JOIN residents r ON u.user_id = r.user_id
               WHERE 1=1";
$count_query = "SELECT COUNT(*) as total 
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.service_id
                JOIN users u ON a.user_id = u.user_id
                LEFT JOIN residents r ON u.user_id = r.user_id
                WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    if ($status_filter === 'today') {
        $base_query .= " AND a.appointment_date = CURDATE()";
        $count_query .= " AND a.appointment_date = CURDATE()";
    } elseif ($status_filter === 'pending_review') {
        $base_query .= " AND a.status IN ('pending', 'for_review')";
        $count_query .= " AND a.status IN ('pending', 'for_review')";
    } elseif ($status_filter === 'active_visit') {
        $base_query .= " AND a.status IN ('checked_in', 'in_progress')";
        $count_query .= " AND a.status IN ('checked_in', 'in_progress')";
    } elseif ($status_filter === 'cancelled_rejected') {
        $base_query .= " AND a.status IN ('cancelled', 'rejected', 'no_show')";
        $count_query .= " AND a.status IN ('cancelled', 'rejected', 'no_show')";
    } else {
        $base_query .= " AND a.status = :status";
        $count_query .= " AND a.status = :status";
        $params[':status'] = $status_filter;
    }
}

if (!empty($date_filter)) {
    $base_query .= " AND a.appointment_date = :date";
    $count_query .= " AND a.appointment_date = :date";
    $params[':date'] = $date_filter;
}

if ($service_filter > 0) {
    $base_query .= " AND a.service_id = :service_id";
    $count_query .= " AND a.service_id = :service_id";
    $params[':service_id'] = $service_filter;
}

if ($window_filter !== 'all') {
    if ($window_filter === 'unassigned') {
        $base_query .= " AND (a.window_assignment IS NULL OR a.window_assignment = '')";
        $count_query .= " AND (a.window_assignment IS NULL OR a.window_assignment = '')";
    } else {
        $base_query .= " AND a.window_assignment LIKE :window";
        $count_query .= " AND a.window_assignment LIKE :window";
        $params[':window'] = '%' . $window_filter . '%';
    }
}

if (!empty($search_query)) {
    $base_query .= " AND (a.reference_number LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search OR s.service_name LIKE :search)";
    $count_query .= " AND (a.reference_number LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search OR s.service_name LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

// Build query parameter string for pagination links
$pagination_params = http_build_query(array_filter([
    'status' => $status_filter !== 'all' ? $status_filter : null,
    'service_id' => $service_filter > 0 ? $service_filter : null,
    'window' => $window_filter !== 'all' ? $window_filter : null,
    'date' => !empty($date_filter) ? $date_filter : null,
    'search' => !empty($search_query) ? $search_query : null,
]));
$page_link_prefix = '?' . ($pagination_params ? $pagination_params . '&' : '') . 'page=';

// Get total count for pagination
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get appointments with pagination
$query = $base_query . " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$filtered_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff info for display
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_visits,
    SUM(CASE WHEN status IN ('pending', 'for_review') THEN 1 ELSE 0 END) as pending_review,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status IN ('checked_in', 'in_progress') THEN 1 ELSE 0 END) as active_visit,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status IN ('cancelled', 'rejected', 'no_show') THEN 1 ELSE 0 END) as cancelled
    FROM appointments";
$stats_res = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<?php
$page_title = 'Manage Appointments - Staff Portal';
$active_nav = 'appointments';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
.assigned-services { background: var(--ref-card-subtle); border-radius: 16px; padding: 12px 18px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; border: 1px solid var(--ref-border); }
.assigned-services .label { font-size: 0.78rem; font-weight: 700; color: var(--ref-muted); display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
.service-tag { background: var(--ref-card); padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; color: var(--ref-text); font-weight: 600; border: 1px solid var(--ref-border); display: inline-flex; align-items: center; gap: 5px; }
.service-tag.no-service { background: rgba(239, 68, 68, 0.1); color: #DC2626; border-color: rgba(239, 68, 68, 0.2); }
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
.stat-item { background: var(--ref-card); border-radius: 16px; padding: 12px 16px; display: flex; align-items: center; gap: 12px; box-shadow: var(--ref-shadow-sm); border: 1px solid var(--ref-border); transition: all 0.2s; }
.stat-item:hover { transform: translateY(-2px); box-shadow: var(--ref-shadow-md); }
.stat-item .icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.stat-item .icon.total { background: rgba(127, 3, 3, 0.1); color: var(--ref-maroon); }
.stat-item .icon.pending { background: rgba(245, 158, 11, 0.12); color: #D97706; }
.stat-item .icon.confirmed { background: rgba(16, 185, 129, 0.12); color: #059669; }
.stat-item .icon.completed { background: rgba(59, 130, 246, 0.12); color: #2563EB; }
.stat-item .icon.cancelled { background: rgba(239, 68, 68, 0.12); color: #DC2626; }
.stat-item .info h3 { font-size: 1.15rem; font-weight: 800; color: var(--ref-text); line-height: 1.1; }
.stat-item .info p { font-size: 0.68rem; color: var(--ref-muted); font-weight: 600; text-transform: uppercase; margin-top: 2px; }
.filter-section { background: var(--ref-card); border-radius: 18px; padding: 16px 20px; margin-bottom: 18px; border: 1px solid var(--ref-border); }
.filter-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 0.68rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid var(--ref-border); border-radius: 10px; font-size: 0.82rem; background: var(--ref-canvas); color: var(--ref-text); font-family: inherit; }
.btn-filter { background: var(--ref-maroon); color: #FFFFFF; padding: 8px 18px; border: none; border-radius: 50px; cursor: pointer; font-weight: 700; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; text-decoration: none; }
.btn-filter:hover { background: var(--ref-maroon-light); color: #FFFFFF; }
.btn-reset { background: var(--ref-card-subtle); color: var(--ref-text); padding: 8px 18px; border: 1px solid var(--ref-border); border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.table-card { background: var(--ref-card); border-radius: 20px; padding: 18px 20px; border: 1px solid var(--ref-border); box-shadow: var(--ref-shadow-sm); }
.table-card .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--ref-border); }
.table-card .card-header h2 { color: var(--ref-text); font-size: 1.05rem; font-weight: 800; display: flex; align-items: center; gap: 8px; margin: 0; }
.table-card .card-header h2 i { color: var(--ref-maroon); }
.table-card .card-header .count-badge { background: var(--ref-canvas); padding: 4px 12px; border-radius: 50px; font-size: 0.72rem; color: var(--ref-muted); font-weight: 700; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 50px; font-size: 0.68rem; font-weight: 700; }
.btn-sm { display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 8px; text-decoration: none; font-size: 0.72rem; font-weight: 600; border: none; cursor: pointer; }
.btn-edit { background: #2563EB; color: #FFFFFF; }
.btn-edit:hover { background: #1D4ED8; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
.modal.active { display: flex; }
.modal-content { background: var(--ref-card); border-radius: 24px; padding: 24px 28px; max-width: 520px; width: 90%; box-shadow: var(--ref-shadow-lg); border: 1px solid var(--ref-border); }
.modal-content h3 { color: var(--ref-text); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-size: 1.1rem; font-weight: 800; }
.modal-content label { display: block; margin-bottom: 6px; font-weight: 700; color: var(--ref-text); font-size: 0.8rem; }
.modal-content select, .modal-content input, .modal-content textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--ref-border); border-radius: 10px; font-size: 0.82rem; background: var(--ref-canvas); color: var(--ref-text); font-family: inherit; }
.modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
.modal-buttons .btn-cancel { background: var(--ref-card-subtle); color: var(--ref-text); padding: 8px 18px; border: 1px solid var(--ref-border); border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 0.82rem; }
.modal-buttons .btn-submit { background: var(--ref-maroon); color: #FFFFFF; padding: 8px 20px; border: none; border-radius: 50px; cursor: pointer; font-weight: 700; font-size: 0.82rem; }

/* Management Hamburger Button & Compact Dropdown (Part 2, Item 3) */
.staff-mgmt-menu-wrap { position: relative; }
.btn-mgmt-hamburger {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    color: var(--ref-text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    cursor: pointer;
    box-shadow: var(--ref-shadow-sm);
    transition: all 0.18s ease;
}
.btn-mgmt-hamburger:hover, .btn-mgmt-hamburger.active {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}
.mgmt-dropdown-panel {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 250px;
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 18px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.18);
    z-index: 1000;
    padding: 8px;
    animation: modalPopIn 0.18s ease-out;
}
.mgmt-dropdown-panel.active { display: block; }
.mgmt-panel-header {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--ref-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 6px 12px 8px;
    border-bottom: 1px solid var(--ref-border);
    margin-bottom: 4px;
}
.mgmt-menu-opt {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 12px;
    text-decoration: none;
    color: var(--ref-text);
    transition: all 0.16s ease;
}
.mgmt-menu-opt:hover {
    background: var(--ref-canvas);
    color: var(--ref-maroon);
    transform: translateX(2px);
}
.mgmt-opt-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.icon-qr { background: #E0E7FF; color: #4338CA; }
.icon-capacity { background: #FEF3C7; color: #D97706; }
.icon-settings { background: #DCFCE7; color: #15803D; }
.mgmt-opt-text strong { display: block; font-size: 0.82rem; }
.mgmt-opt-text small { display: block; font-size: 0.68rem; color: var(--ref-muted); }

/* Standardized Action Dropdown in Rows (Part 2, Item 5) */
.appointments-table td { position: relative; }
.row-action-dropdown { position: relative; display: inline-block; }
.btn-row-action {
    background: var(--ref-canvas);
    border: 1px solid var(--ref-border);
    border-radius: 50px;
    padding: 5px 14px;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ref-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.18s;
}
.btn-row-action:hover, .row-action-dropdown.open .btn-row-action {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}
.row-action-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    min-width: 200px;
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.18);
    z-index: 1200;
    padding: 6px;
    text-align: left;
}
.row-action-dropdown.open .row-action-menu {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.action-menu-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--ref-text);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    width: 100%;
    transition: all 0.15s;
}
.action-menu-item:hover {
    background: var(--ref-canvas);
    color: var(--ref-maroon);
}
.action-menu-item.danger-item { color: #DC2626; }
.action-menu-item.danger-item:hover { background: #FEE2E2; color: #991B1B; }
.action-menu-divider { height: 1px; background: var(--ref-border); margin: 4px 0; }
</style>
</head>
<body>
<div class="app-container">
        <!-- Sidebar Navigation -->
        <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
        <!-- Simplified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Container -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Manage Appointments';
            $banner_subtitle = 'Review, approve, reschedule, check-in, and manage citizen visit requests';
            $banner_icon = 'fa-calendar-check';
            $banner_actions = [
                ["label" => "QR Live Scanner", "url" => "qr-scanner.php", "icon" => "fa-qrcode", "type" => "primary"]
            ];
            include __DIR__ . '/../includes/portal-banner.php';
            ?>
        
        <!-- Assigned Services -->
        <div class="assigned-services">
            <span class="label"><i class="fas fa-tasks"></i> Your Services:</span>
            <?php if(empty($assigned_categories)): ?>
                <span class="service-tag no-service">
                    <i class="fas fa-exclamation-circle"></i> No services assigned. Contact administrator.
                </span>
            <?php else: ?>
                <?php foreach($assigned_categories as $cat): 
                    if (empty($is_pwd_senior_enabled) && in_array($cat, ['Senior Citizen', 'PWD', 'Senior'])) continue;
                ?>
                    <span class="service-tag">
                        <?php 
                        switch($cat) {
                            case 'AICS': echo '🏥 AICS'; break;
                            case 'Housing': echo '🏠 Housing'; break;
                            case 'Senior Citizen': echo '👴 Senior'; break;
                            case 'PWD': echo '♿ PWD'; break;
                            default: echo htmlspecialchars($cat);
                        }
                        ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Row -->
        <div class="stats-row" style="grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));">
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=all'" style="cursor:pointer; <?php echo $status_filter=='all'?'border:2px solid var(--maroon);':''; ?>">
                <div class="icon total"><i class="fas fa-calendar-alt"></i></div>
                <div class="info">
                    <h3><?php echo (int)($stats_res['total'] ?? 0); ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=today'" style="cursor:pointer; border-left:4px solid #10B981; <?php echo $status_filter=='today'?'border:2px solid #10B981;':''; ?>">
                <div class="icon" style="background:rgba(16,185,129,0.15);color:#059669;"><i class="fas fa-calendar-day"></i></div>
                <div class="info">
                    <h3 style="color:#059669;"><?php echo (int)($stats_res['today_visits'] ?? 0); ?></h3>
                    <p>Today's Visits</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=pending_review'" style="cursor:pointer; <?php echo $status_filter=='pending_review'?'border:2px solid var(--color-warning);':''; ?>">
                <div class="icon pending"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h3><?php echo (int)($stats_res['pending_review'] ?? 0); ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=confirmed'" style="cursor:pointer; <?php echo $status_filter=='confirmed'?'border:2px solid var(--color-success);':''; ?>">
                <div class="icon confirmed"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h3><?php echo (int)($stats_res['confirmed'] ?? 0); ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=active_visit'" style="cursor:pointer; border-left:4px solid #8B5CF6; <?php echo $status_filter=='active_visit'?'border:2px solid #8B5CF6;':''; ?>">
                <div class="icon" style="background:rgba(139,92,246,0.15);color:#7C3AED;"><i class="fas fa-user-clock"></i></div>
                <div class="info">
                    <h3 style="color:#7C3AED;"><?php echo (int)($stats_res['active_visit'] ?? 0); ?></h3>
                    <p>In-Office Active</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=completed'" style="cursor:pointer; <?php echo $status_filter=='completed'?'border:2px solid var(--color-info);':''; ?>">
                <div class="icon completed"><i class="fas fa-check-double"></i></div>
                <div class="info">
                    <h3><?php echo (int)($stats_res['completed'] ?? 0); ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-item" onclick="window.location.href='manage-appointments.php?status=cancelled_rejected'" style="cursor:pointer; <?php echo $status_filter=='cancelled_rejected'?'border:2px solid var(--color-danger);':''; ?>">
                <div class="icon cancelled"><i class="fas fa-times-circle"></i></div>
                <div class="info">
                    <h3><?php echo (int)($stats_res['cancelled'] ?? 0); ?></h3>
                    <p>Cancelled / Other</p>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--ref-border, rgba(0,0,0,0.05));">
                <div class="kanban-view-pills" style="display: inline-flex; background: var(--ref-card-subtle, #f0f6f2); padding: 4px; border-radius: 50px; border: 1px solid var(--ref-border, #e2e8f0); gap: 4px;">
                    <a href="manage-appointments.php" class="kanban-view-pill active" style="padding: 7px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: #FFFFFF; background: var(--ref-maroon, #7F0303); text-decoration: none; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 2px 8px rgba(127,3,3,0.35);">
                        <i class="fas fa-table-list"></i> Table List
                    </a>
                    <a href="kanban-appointments.php" class="kanban-view-pill" style="padding: 7px 18px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: var(--ref-muted, #71818B); text-decoration: none; display: inline-flex; align-items: center; gap: 7px;">
                        <i class="fas fa-columns"></i> Kanban Board
                    </a>
                </div>
            </div>
            <form method="GET" action="" class="filter-form" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="today" <?php echo $status_filter == 'today' ? 'selected' : ''; ?>>Today's Visits</option>
                        <option value="pending_review" <?php echo $status_filter == 'pending_review' ? 'selected' : ''; ?>>Pending (All Review)</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending (Submitted)</option>
                        <option value="for_review" <?php echo $status_filter == 'for_review' ? 'selected' : ''; ?>>For Review (Assigned)</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="active_visit" <?php echo $status_filter == 'active_visit' ? 'selected' : ''; ?>>In-Office Active (Checked-in / In-Progress)</option>
                        <option value="checked_in" <?php echo $status_filter == 'checked_in' ? 'selected' : ''; ?>>Checked-In (On-Site)</option>
                        <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed (Locked)</option>
                        <option value="rescheduled" <?php echo $status_filter == 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                        <option value="needs_information" <?php echo $status_filter == 'needs_information' ? 'selected' : ''; ?>>Needs Information</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no_show" <?php echo $status_filter == 'no_show' ? 'selected' : ''; ?>>No-Show</option>
                        <option value="cancelled_rejected" <?php echo $status_filter == 'cancelled_rejected' ? 'selected' : ''; ?>>Cancelled / Disapproved</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-hand-holding-heart"></i> Service Program</label>
                    <select name="service_id">
                        <option value="0" <?php echo $service_filter === 0 ? 'selected' : ''; ?>>All Services &amp; Programs</option>
                        <?php foreach($all_services as $svc): ?>
                            <option value="<?php echo $svc['service_id']; ?>" <?php echo $service_filter === (int)$svc['service_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($svc['service_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-door-open"></i> Window Lane</label>
                    <select name="window">
                        <option value="all" <?php echo $window_filter === 'all' ? 'selected' : ''; ?>>All Windows</option>
                        <option value="Window 1" <?php echo $window_filter === 'Window 1' ? 'selected' : ''; ?>>Window 1 (AICS / Crisis)</option>
                        <option value="Window 2" <?php echo $window_filter === 'Window 2' ? 'selected' : ''; ?>>Window 2 (Housing)</option>
                        <option value="Window 3" <?php echo $window_filter === 'Window 3' ? 'selected' : ''; ?>>Window 3 (OSCA Senior)</option>
                        <option value="Window 4" <?php echo $window_filter === 'Window 4' ? 'selected' : ''; ?>>Window 4 (PDAO Disability)</option>
                        <option value="Window 5" <?php echo $window_filter === 'Window 5' ? 'selected' : ''; ?>>Window 5 (Release / Inquiry)</option>
                        <option value="unassigned" <?php echo $window_filter === 'unassigned' ? 'selected' : ''; ?>>Unassigned Window</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-day"></i> Filter Date</label>
                    <input type="date" name="date" value="<?php echo $date_filter; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search Reference or Resident</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="E.g. PAL-SOC or Juan">
                </div>
                <div class="filter-group" style="flex-direction: row; gap: 8px; align-items: center;">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                    <a href="manage-appointments.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Appointments Table -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Appointment Records</h2>
                <span class="count-badge"><?php echo $total_records; ?> records</span>
            </div>
            
            <?php if(empty($filtered_appointments)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>No appointments found</p>
                    <p class="sub-text"><?php echo empty($assigned_categories) ? 'You have no services assigned. Please contact the administrator.' : 'Try adjusting your filters or search query.'; ?></p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference #</th>
                                <th>Resident Client</th>
                                <th>Service & Office</th>
                                <th>Schedule & Window</th>
                                <th>Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($filtered_appointments as $idx => $appointment): ?>
                            <?php 
                                $refNum = !empty($appointment['reference_number']) ? $appointment['reference_number'] : ('PAL-SOC-' . date('Y', strtotime($appointment['created_at'])) . '-' . str_pad($appointment['appointment_id'], 6, '0', STR_PAD_LEFT));
                                $is_latest = ($page === 1 && $idx === 0);
                            ?>
                            <tr class="<?php echo $is_latest ? 'row-latest-entry' : ''; ?>">
                                <td>
                                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:4px;">
                                        <span style="font-family:'Courier Prime',monospace; font-weight:700; color:var(--maroon); font-size:0.86rem; letter-spacing:0.04em;">
                                            <?php echo htmlspecialchars($refNum); ?>
                                        </span>
                                        <?php if ($is_latest): ?>
                                            <span class="badge-latest-tag"><i class="fas fa-sparkles"></i> LATEST</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                                        #<?php echo $appointment['appointment_id']; ?> • Booked <?php echo date('M d, Y', strtotime($appointment['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="resident-name" style="font-weight:700; color:var(--text-primary); font-size:0.88rem;">
                                        <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                    </div>
                                    <div style="font-size:0.73rem; color:var(--text-muted); margin-top:2px;">
                                        <i class="fas fa-map-marker-alt" style="color:var(--maroon);"></i> <?php echo htmlspecialchars($appointment['barangay'] ?: 'Palayan City'); ?> • <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['contact_number'] ?: 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:var(--text-primary);"><?php echo htmlspecialchars($appointment['service_name'] ?: 'CSWDO Social Service'); ?></div>
                                    <div style="font-size:0.73rem; color:var(--text-muted); margin-top:2px;">
                                        <i class="fas fa-building" style="color:var(--maroon); font-size:0.7rem;"></i> <?php echo htmlspecialchars($appointment['office_department'] ?: 'CSWDO'); ?>
                                    </div>
                                    <?php if(!empty($appointment['purpose'])): ?>
                                    <div style="font-size:0.7rem; color:var(--text-muted); font-style:italic; margin-top:2px;">
                                        "<?php echo htmlspecialchars(substr($appointment['purpose'], 0, 45)) . (strlen($appointment['purpose']) > 45 ? '...' : ''); ?>"
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong></div>
                                    <div style="font-size:0.74rem; color:var(--text-muted); margin-top:2px;">
                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                    <?php if(!empty($appointment['window_assignment'])): ?>
                                    <div style="font-size:0.72rem; color:var(--maroon); font-weight:700; margin-top:3px;">
                                        <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($appointment['window_assignment']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo getAppointmentBadge($appointment['status']); ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="row-action-dropdown">
                                        <button type="button" class="btn-dot-action" onclick="toggleRowAction(event, this)" title="Actions" aria-label="Row Actions">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="row-action-menu">
                                            <?php 
                                            $is_completed = (strtolower($appointment['status']) === 'completed'); 
                                            $is_cancelled = (strtolower($appointment['status']) === 'cancelled'); 
                                            ?>
                                            <?php if ($is_completed): ?>
                                                <div class="action-menu-item" style="color:#059669; cursor:default; font-weight:700; background:rgba(16,185,129,0.08); border-radius:6px;">
                                                    <i class="fas fa-lock" style="color:#059669;"></i> Completed (Locked)
                                                </div>
                                            <?php elseif ($is_cancelled): ?>
                                                <div class="action-menu-item" style="color:#DC2626; cursor:default; font-weight:700; background:rgba(239,68,68,0.08); border-radius:6px;">
                                                    <i class="fas fa-ban" style="color:#DC2626;"></i> Cancelled
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="action-menu-item" onclick="openStatusModal(<?php echo $appointment['appointment_id']; ?>, '<?php echo htmlspecialchars($appointment['status']); ?>', '<?php echo htmlspecialchars(addslashes($appointment['window_assignment'] ?? '')); ?>')">
                                                    <i class="fas fa-edit" style="color:var(--ref-maroon);"></i> Process &amp; Status
                                                </button>
                                            <?php endif; ?>
                                            <a href="../appointment-slip.php?ref=<?php echo urlencode($refNum); ?>" target="_blank" class="action-menu-item">
                                                <i class="fas fa-print" style="color:#0284C7;"></i> Official Slip
                                            </a>
                                            <a href="view-appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="action-menu-item">
                                                <i class="fas fa-eye" style="color:#059669;"></i> View Details
                                            </a>
                                            <?php if (!$is_completed): ?>
                                                <div class="action-menu-divider"></div>
                                                <a href="?delete=1&id=<?php echo $appointment['appointment_id']; ?>" class="action-menu-item danger-item" onclick="return confirm('Permanently delete this appointment record?')">
                                                    <i class="fas fa-trash-alt"></i> Delete Record
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <span class="page-info">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total)</span>
                    
                    <div class="pagination-links">
                        <?php if($page > 1): ?>
                            <a href="<?php echo $page_link_prefix . ($page - 1); ?>" title="Previous Page">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled" title="No Previous Page"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="' . $page_link_prefix . '1">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="ellipsis">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = $i == $page ? 'active' : '';
                            echo '<a href="' . $page_link_prefix . $i . '" class="' . $active . '">' . $i . '</a>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="ellipsis">...</span>';
                            }
                            echo '<a href="' . $page_link_prefix . $total_pages . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="<?php echo $page_link_prefix . ($page + 1); ?>" title="Next Page">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled" title="No Next Page"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>

<!-- Enhanced Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3><i class="fas fa-edit"></i> Process Appointment & Update Status</h3>
        <form method="POST" action="" onsubmit="return validateApptModal(event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="appointment_id" id="modal_appointment_id">
            
            <label style="margin-bottom: 6px; font-weight: 700;">Workflow State Transition:</label>
            <select name="status" id="modal_status" onchange="handleModalStatusChange(this.value)" required style="margin-bottom: 12px;">
                <option value="pending">⏳ Pending (Submitted)</option>
                <option value="for_review">🔍 For Review (Staff Assigned)</option>
                <option value="confirmed">✅ Confirmed (Schedule Approved)</option>
                <option value="checked_in">📍 Checked-In (Citizen Arrived On-Site)</option>
                <option value="in_progress">▶️ In Progress (Interview / Assessment)</option>
                <option value="completed">🎉 Completed (Resolved & Released)</option>
                <option value="rescheduled">🔄 Rescheduled (Change Date / Time)</option>
                <option value="needs_information">📝 Needs Information (Missing Documents)</option>
                <option value="rejected">❌ Disapproved (Not Qualified / Invalid)</option>
                <option value="no_show">🚫 No-Show (Did Not Arrive)</option>
                <option value="cancelled">🛑 Cancelled</option>
            </select>

            <!-- Window Assignment Field (Visible for confirmed / checked_in) -->
            <div id="windowFieldWrap" style="margin-bottom: 12px;">
                <label style="display:block;margin-bottom:4px;font-weight:700;font-size:0.82rem;color:var(--text-secondary);">
                    <i class="fas fa-door-open" style="color:var(--maroon);"></i> Counter Window Assignment:
                </label>
                <select name="window_assignment" id="modal_window_assignment">
                    <option value="">-- Auto-Assign / Not Assigned --</option>
                    <option value="Window 1 (AICS / Crisis Desk)">Window 1 (AICS / Crisis Desk)</option>
                    <option value="Window 2 (Housing & Resettlement)">Window 2 (Housing & Resettlement)</option>
                    <option value="Window 3 (OSCA Senior Services)">Window 3 (OSCA Senior Services)</option>
                    <option value="Window 4 (PDAO Disability Affairs)">Window 4 (PDAO Disability Affairs)</option>
                    <option value="Window 5 (General Inquiry & Document Release)">Window 5 (General Inquiry & Document Release)</option>
                </select>
            </div>

            <!-- Reschedule Fields -->
            <div id="rescheduleFieldWrap" style="display: none; margin-bottom: 12px; background: var(--ref-card-subtle); padding: 12px; border-radius: 12px; border: 1px solid var(--ref-border);">
                <label style="font-weight: 700; font-size: 0.82rem; color: var(--ref-maroon); display: block; margin-bottom: 6px;">
                    <i class="fas fa-calendar-alt"></i> Set New Appointment Schedule:
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted);">Date:</span>
                        <input type="date" name="new_date" min="<?php echo date('Y-m-d'); ?>" style="margin-top: 2px;">
                    </div>
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted);">Time Slot:</span>
                        <select name="new_time" style="margin-top: 2px;">
                            <option value="08:00:00">8:00 AM</option>
                            <option value="08:30:00">8:30 AM</option>
                            <option value="09:00:00">9:00 AM</option>
                            <option value="09:30:00">9:30 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="10:30:00">10:30 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="13:30:00">1:30 PM</option>
                            <option value="14:00:00">2:00 PM</option>
                            <option value="14:30:00">2:30 PM</option>
                            <option value="15:00:00">3:00 PM</option>
                            <option value="15:30:00">3:30 PM</option>
                            <option value="16:00:00">4:00 PM</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Rejection Reason Field -->
            <div id="rejectionFieldWrap" style="display: none; margin-bottom: 12px;">
                <label style="display:block;margin-bottom:4px;font-weight:700;font-size:0.82rem;color:#DC2626;">
                    <i class="fas fa-exclamation-circle"></i> Reason for Disapproval (Mandatory, emailed to resident):
                </label>
                <textarea name="rejection_reason" rows="2" placeholder="Explain why the visit request was disapproved..."></textarea>
            </div>

            <!-- General Status Notes -->
            <div style="margin-bottom: 12px;">
                <label style="display:block;margin-bottom:4px;font-weight:700;font-size:0.82rem;color:var(--text-secondary);">
                    Internal Officer Assessment / Citizen Instructions:
                </label>
                <textarea name="status_notes" rows="2" placeholder="Notes for resident notification and audit log history..."></textarea>
            </div>

            <div class="modal-buttons" style="margin-top: 16px;">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update_status" class="btn-submit">
                    <i class="fas fa-save"></i> Save & Notify Citizen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic modal field switcher
function handleModalStatusChange(val) {
    const windowWrap = document.getElementById('windowFieldWrap');
    const reschedWrap = document.getElementById('rescheduleFieldWrap');
    const rejectWrap = document.getElementById('rejectionFieldWrap');

    if (val === 'confirmed' || val === 'checked_in') {
        windowWrap.style.display = 'block';
    } else {
        windowWrap.style.display = 'block'; // Keep available
    }

    if (val === 'rescheduled') {
        reschedWrap.style.display = 'block';
    } else {
        reschedWrap.style.display = 'none';
    }

    if (val === 'rejected') {
        rejectWrap.style.display = 'block';
    } else {
        rejectWrap.style.display = 'none';
    }
}

// Workflow Confirmation & Validation for Appointment Modal
function validateApptModal(event) {
    const status = document.getElementById('modal_status').value;
    const notes = document.querySelector('textarea[name="status_notes"]')?.value.trim() || '';
    const rejectionReason = document.querySelector('textarea[name="rejection_reason"]')?.value.trim() || '';

    if (status === 'completed') {
        return confirm("CRITICAL WORKFLOW ACTION:\n\nAre you sure you want to mark this appointment as COMPLETED?\n\nOnce marked as Completed, this appointment transaction is permanently locked and can no longer be edited, rescheduled, or deleted.");
    }
    if (status === 'rejected') {
        if (!rejectionReason && !notes) {
            alert('A disapproval reason is required when disapproving an appointment.');
            return false;
        }
        return confirm("Are you sure you want to DISAPPROVE this appointment?\n\nA formal disapproval notice with the stated reason will be sent to the resident.");
    }
    if (status === 'cancelled') {
        return confirm("Are you sure you want to CANCEL this appointment?\n\nThis will cancel the booking and notify the citizen.");
    }
    if (status === 'rescheduled') {
        const dateInput = document.querySelector('input[name="new_date"]')?.value;
        const timeInput = document.querySelector('select[name="new_time"]')?.value;
        if (!dateInput || !timeInput) {
            alert('Please select both a new appointment date and time slot.');
            return false;
        }
        return confirm("Confirm rescheduling this appointment to " + dateInput + " at " + timeInput + "?\n\nThe resident will receive a schedule update notification.");
    }
    return true;
}

// ========== MODAL FUNCTIONS ==========
function openStatusModal(appointmentId, currentStatus, windowAssignment = '') {
    if (String(currentStatus).toLowerCase() === 'completed') {
        alert('This appointment has already been completed. Once completed, appointment records are permanently locked against modifications.');
        return;
    }
    if (String(currentStatus).toLowerCase() === 'cancelled') {
        alert('This appointment has been cancelled and cannot be modified.');
        return;
    }
    document.getElementById('modal_appointment_id').value = appointmentId;
    document.getElementById('modal_status').value = currentStatus.toLowerCase();
    if (document.getElementById('modal_window_assignment')) {
        document.getElementById('modal_window_assignment').value = windowAssignment || '';
    }
    handleModalStatusChange(currentStatus.toLowerCase());
    document.getElementById('statusModal').classList.add('active');
}

function closeModal() {
    document.getElementById('statusModal').classList.remove('active');
}

// ========== MANAGEMENT MENU & ROW ACTIONS ==========
function toggleMgmtMenu(e) {
    e.stopPropagation();
    const panel = document.getElementById('mgmtDropdownPanel');
    const btn = document.getElementById('mgmtHamburgerBtn');
    if (!panel) return;
    const isOpen = panel.classList.contains('active');
    closeAllActionMenus();
    if (!isOpen) {
        panel.classList.add('active');
        if (btn) btn.classList.add('active');
    } else {
        panel.classList.remove('active');
        if (btn) btn.classList.remove('active');
    }
}

function toggleRowAction(e, btn) {
    e.stopPropagation();
    const dropdown = btn.closest('.row-action-dropdown');
    if (!dropdown) return;
    const isOpen = dropdown.classList.contains('open');
    closeAllActionMenus();
    if (!isOpen) {
        dropdown.classList.add('open');
    }
}

function closeAllActionMenus() {
    const mgmtPanel = document.getElementById('mgmtDropdownPanel');
    const mgmtBtn = document.getElementById('mgmtHamburgerBtn');
    if (mgmtPanel) mgmtPanel.classList.remove('active');
    if (mgmtBtn) mgmtBtn.classList.remove('active');
    document.querySelectorAll('.row-action-dropdown.open').forEach(dd => dd.classList.remove('open'));
}

window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        closeModal();
    }
    closeAllActionMenus();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeAllActionMenus();
    }
});

</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>

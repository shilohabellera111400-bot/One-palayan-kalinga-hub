<?php
// staff/manage-housing.php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for sidebar display
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
               AND sc.category_name IN ('AICS', 'Housing', 'Senior Citizen', 'PWD', 'Verification')";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check access flags
$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);
$has_verif_access = in_array('Verification', $assigned_categories) || in_array('Residency Verification', $assigned_categories);

// If staff doesn't have housing access, redirect
if (!$has_housing_access) {
    $_SESSION['error'] = "You don't have permission to access Housing Management.";
    redirect('../staff-dashboard.php');
}

// Get filter parameters
$program_filter = isset($_GET['program']) ? sanitize($_GET['program']) : 'all';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $beneficiary_id = sanitize($_POST['beneficiary_id'] ?? '');
    $return_to = sanitize($_POST['return_to'] ?? '');
    
    // Verify CSRF token
    $csrf_passed = false;
    try {
        csrf_verify(function() {
            throw new Exception("CSRF failed");
        });
        $csrf_passed = true;
    } catch (\Throwable $e) {
        $csrf_passed = false;
    }

    if (!$csrf_passed) {
        $err = "Security token mismatch or expired. Please refresh and try again.";
        if ($return_to === 'view' && !empty($beneficiary_id)) {
            $_SESSION['error'] = $err;
            redirect("view-housing.php?id=" . $beneficiary_id);
        } else {
            $error = $err;
        }
    } else {
        $new_status = sanitize($_POST['new_status'] ?? '');
        $unit_assigned = sanitize($_POST['unit_assigned'] ?? '');
        $interview_date = sanitize($_POST['interview_date'] ?? '');
        $move_in_date = sanitize($_POST['move_in_date'] ?? '');
        $remarks = sanitize($_POST['remarks'] ?? '');
        $assessment_notes = sanitize($_POST['assessment_notes'] ?? '');

        // Fetch current application & program details
        $curr_stmt = $db->prepare("SELECT h.*, p.program_name, p.housing_type as prog_type, p.total_units, u.first_name, u.last_name, u.email 
                                   FROM housing_beneficiaries h 
                                   JOIN housing_programs p ON h.program_id = p.program_id 
                                   JOIN users u ON h.user_id = u.user_id 
                                   WHERE h.beneficiary_id = :id");
        $curr_stmt->execute([':id' => $beneficiary_id]);
        $current_app = $curr_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_app) {
            $err = "Housing application record not found.";
            if ($return_to === 'view' && !empty($beneficiary_id)) {
                $_SESSION['error'] = $err;
                redirect("view-housing.php?id=" . $beneficiary_id);
            } else {
                $error = $err;
            }
        } else {
            $is_insite = (($current_app['housing_type'] ?? '') === 'in_site');
            $unit_label = $is_insite ? 'Grant / Voucher Reference' : 'Unit Assignment';

            // Guard: Released/awarded housing allocations cannot be reverted
            if (strtolower($current_app['status'] ?? '') === 'released' && $new_status !== 'released') {
                $err = "This housing allocation has already been released (turned over / awarded). Completed allocations cannot be reverted.";
                if ($return_to === 'view' && !empty($beneficiary_id)) {
                    $_SESSION['error'] = $err;
                    redirect("view-housing.php?id=" . $beneficiary_id);
                } else {
                    $error = $err;
                }
            } else {
                // Guard 1: Duplicate Unit / Voucher Assignment Check on Approval
                $has_conflict = false;
            if ($new_status === 'approved' && !empty($unit_assigned)) {
                $dup_stmt = $db->prepare("SELECT h.beneficiary_id, u.first_name, u.last_name 
                                          FROM housing_beneficiaries h 
                                          JOIN users u ON h.user_id = u.user_id 
                                          WHERE h.program_id = :pid 
                                            AND LOWER(TRIM(h.unit_assigned)) = LOWER(TRIM(:unit)) 
                                            AND h.status IN ('approved', 'released') 
                                            AND h.beneficiary_id != :id");
                $dup_stmt->execute([
                    ':pid' => $current_app['program_id'],
                    ':unit' => $unit_assigned,
                    ':id' => $beneficiary_id
                ]);
                $dup = $dup_stmt->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    $has_conflict = true;
                    $err = "Conflict: {$unit_label} '" . htmlspecialchars($unit_assigned) . "' is already assigned to {$dup['first_name']} {$dup['last_name']} (Application #{$dup['beneficiary_id']}).";
                    if ($return_to === 'view' && !empty($beneficiary_id)) {
                        $_SESSION['error'] = $err;
                        redirect("view-housing.php?id=" . $beneficiary_id);
                    } else {
                        $error = $err;
                    }
                }
            }

            // Guard 2: Program Capacity Check on Approval
            if (!$has_conflict && $new_status === 'approved' && !empty($current_app['total_units']) && (int)$current_app['total_units'] > 0) {
                $occ_stmt = $db->prepare("SELECT COUNT(*) FROM housing_beneficiaries 
                                          WHERE program_id = :pid 
                                            AND status IN ('approved', 'released') 
                                            AND beneficiary_id != :id");
                $occ_stmt->execute([
                    ':pid' => $current_app['program_id'],
                    ':id' => $beneficiary_id
                ]);
                $occupied = (int)$occ_stmt->fetchColumn();
                if ($occupied >= (int)$current_app['total_units']) {
                    $has_conflict = true;
                    $cap_term = $is_insite ? 'grant allocations' : 'housing units';
                    $err = "Capacity limit reached for {$current_app['program_name']}. All {$current_app['total_units']} {$cap_term} are already allocated ({$occupied} active beneficiaries).";
                    if ($return_to === 'view' && !empty($beneficiary_id)) {
                        $_SESSION['error'] = $err;
                        redirect("view-housing.php?id=" . $beneficiary_id);
                    } else {
                        $error = $err;
                    }
                }
            }

            if (!$has_conflict) {
                // Determine effective move-in and interview dates
                $final_move_in = !empty($move_in_date) ? $move_in_date : ($new_status === 'released' ? ($current_app['move_in_date'] ?: date('Y-m-d')) : $current_app['move_in_date']);
                $final_interview_date = !empty($interview_date) ? $interview_date : $current_app['interview_date'];
                $final_unit = !empty($unit_assigned) ? $unit_assigned : $current_app['unit_assigned'];

                $update_query = "UPDATE housing_beneficiaries SET 
                                  status = :status,
                                  unit_assigned = :unit,
                                  interview_date = :interview_date,
                                  move_in_date = :move_in_date";
                
                if ($new_status == 'approved') {
                    $update_query .= ", approved_by = :user_id, approval_date = IFNULL(approval_date, NOW())";
                    if (!empty($assessment_notes)) {
                        $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nApproval Clearance: ', :notes)";
                    }
                } elseif ($new_status == 'qualified') {
                    if (!empty($assessment_notes)) {
                        $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nQualification Assessment: ', :notes)";
                    }
                } elseif ($new_status == 'rejected') {
                    $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nRejection Reason: ', :remarks)";
                }
                
                $update_query .= " WHERE beneficiary_id = :id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':status', $new_status);
                $update_stmt->bindParam(':unit', $final_unit);
                $update_stmt->bindParam(':interview_date', $final_interview_date);
                $update_stmt->bindParam(':move_in_date', $final_move_in);
                
                if ($new_status == 'approved') {
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    if (!empty($assessment_notes)) {
                        $update_stmt->bindParam(':notes', $assessment_notes);
                    }
                } elseif ($new_status == 'qualified') {
                    if (!empty($assessment_notes)) {
                        $update_stmt->bindParam(':notes', $assessment_notes);
                    }
                } elseif ($new_status == 'rejected') {
                    $update_stmt->bindParam(':remarks', $remarks);
                }
                
                $update_stmt->bindParam(':id', $beneficiary_id);
                
                if ($update_stmt->execute()) {
                    $app_year = !empty($current_app['application_date']) ? date('Y', strtotime($current_app['application_date'])) : date('Y');
                    $tracking_no = "HSG-" . $app_year . "-" . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);

                    // Feature 3: Auto Link Schedule to Appointments Table
                    if (!empty($interview_date)) {
                        try {
                            require_once __DIR__ . '/../includes/appointment-helper.php';

                            // Determine service_id for appointment
                            $appt_service_id = 4;
                            if ($is_insite) {
                                $svc_stmt = $db->query("SELECT service_id FROM services WHERE service_code = 'HOUSING_INSITE' OR service_name LIKE '%In-Site%' LIMIT 1");
                                $svc_row = $svc_stmt ? $svc_stmt->fetch(PDO::FETCH_ASSOC) : null;
                                $appt_service_id = $svc_row ? (int)$svc_row['service_id'] : 17;
                            } elseif (stripos($current_app['program_name'], 'San Lorenzo') !== false) {
                                $appt_service_id = 5;
                            } else {
                                $appt_service_id = 4;
                            }

                            // Check if appointment already exists for this housing tracking number
                            $appt_check = $db->prepare("SELECT appointment_id FROM appointments 
                                                        WHERE user_id = :uid 
                                                          AND (purpose LIKE :tno OR notes LIKE :tno_note) 
                                                          AND status NOT IN ('cancelled', 'rejected') 
                                                        ORDER BY appointment_id DESC LIMIT 1");
                            $appt_check->execute([
                                ':uid' => $current_app['user_id'],
                                ':tno' => '%' . $tracking_no . '%',
                                ':tno_note' => '%' . $tracking_no . '%'
                            ]);
                            $existing_appt = $appt_check->fetch(PDO::FETCH_ASSOC);

                            $stype = $is_insite ? 'In-Site Housing Lot Inspection' : 'Housing Applicant Interview & Assessment';
                            $staff_display_name = $_SESSION['full_name'] ?? 'Housing Caseworker';

                            if ($existing_appt) {
                                $appt_up = $db->prepare("UPDATE appointments SET 
                                                           appointment_date = :adate, 
                                                           appointment_time = '09:00:00',
                                                           service_type = :stype,
                                                           status = 'confirmed',
                                                           notes = CONCAT(IFNULL(notes, ''), '\nRescheduled to ', :adate_str, ' by Housing Caseworker (', :staff_name, ')') 
                                                         WHERE appointment_id = :aid");
                                $appt_up->execute([
                                    ':adate' => $interview_date,
                                    ':stype' => $stype,
                                    ':adate_str' => date('M d, Y', strtotime($interview_date)),
                                    ':staff_name' => $staff_display_name,
                                    ':aid' => $existing_appt['appointment_id']
                                ]);
                                if (function_exists('logAppointmentHistory')) {
                                    logAppointmentHistory($db, (int)$existing_appt['appointment_id'], 'rescheduled', 'Housing appointment rescheduled to ' . $interview_date, (int)$_SESSION['user_id']);
                                }
                            } else {
                                $ref_number = generateAppointmentRefNumber($db);
                                $qr_token = generateQrToken();
                                $purpose = ($is_insite ? 'On-Site Lot Verification & Field Inspection' : 'Housing Intake Interview & Family Assessment') . " for Application [{$tracking_no}]";
                                $anotes = "Auto-scheduled for Housing Application {$tracking_no} ({$current_app['program_name']}) by Caseworker: {$staff_display_name}";
                                
                                $appt_ins = $db->prepare("INSERT INTO appointments 
                                    (user_id, service_id, office_department, window_assignment, reference_number, qr_token, 
                                     appointment_date, appointment_time, service_type, status, purpose, notes) 
                                    VALUES (:uid, :sid, 'Housing & Resettlement Division / CSWDO', 'Window 2 - Housing & Resettlement', :ref, :qr,
                                            :adate, '09:00:00', :stype, 'confirmed', :purpose, :notes)");
                                $appt_ins->execute([
                                    ':uid' => $current_app['user_id'],
                                    ':sid' => $appt_service_id,
                                    ':ref' => $ref_number,
                                    ':qr'  => $qr_token,
                                    ':adate' => $interview_date,
                                    ':stype' => $stype,
                                    ':purpose' => $purpose,
                                    ':notes' => $anotes
                                ]);
                                $new_aid = (int)$db->lastInsertId();
                                if (function_exists('logAppointmentHistory')) {
                                    logAppointmentHistory($db, $new_aid, 'confirmed', 'Auto-created and confirmed from Housing Caseworker scheduling', (int)$_SESSION['user_id']);
                                }
                            }
                        } catch (\Throwable $e) {
                            error_log("Housing appointment linkage error: " . $e->getMessage());
                        }
                    }

                    // Create resident in-app notification
                    if (!empty($current_app['user_id'])) {
                        $notif_text = "Your housing application ({$tracking_no}) status has been updated to: " . ucfirst($new_status) . ".";
                        if (!empty($interview_date)) {
                            $notif_text .= " A schedule is confirmed for " . date('M d, Y', strtotime($interview_date)) . " at 09:00 AM (Window 2 - Housing Division). Check My Appointments for your QR slip.";
                        }
                        if ($new_status === 'approved' && !empty($final_unit)) {
                            $notif_text .= " Assigned " . ($is_insite ? "Grant Voucher: " : "Unit: ") . $final_unit . ".";
                        }
                        if ($new_status === 'released' && !empty($final_move_in)) {
                            $notif_text .= " Official " . ($is_insite ? "Grant Release Date: " : "Move-in Date: ") . date('M d, Y', strtotime($final_move_in)) . ".";
                        }
                        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                        VALUES (:user_id, 'Housing Application Updated', :msg, 'housing')";
                        $notif_stmt = $db->prepare($notif_query);
                        $notif_stmt->execute([':user_id' => $current_app['user_id'], ':msg' => $notif_text]);
                    }
                    
                    // Dispatch email & SMS notification
                    try {
                        $info_query = "SELECT h.*, p.program_name, u.email, u.contact_number, CONCAT(u.first_name, ' ', u.last_name) as full_name 
                                       FROM housing_beneficiaries h 
                                       JOIN housing_programs p ON h.program_id = p.program_id 
                                       JOIN users u ON h.user_id = u.user_id 
                                       WHERE h.beneficiary_id = :id";
                        $info_stmt = $db->prepare($info_query);
                        $info_stmt->bindParam(':id', $beneficiary_id);
                        $info_stmt->execute();
                        $hinfo = $info_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($hinfo) {
                            // Email
                            if (!empty($hinfo['email'])) {
                                require_once __DIR__ . '/../includes/Mailer.php';
                                $mailer = new Mailer();
                                $mailer->sendHousingUpdate($hinfo['email'], $hinfo['full_name'], $hinfo);
                            }

                            // SMS via TextBee
                            $phone = $hinfo['contact_number'] ?? '';
                            if (!empty($phone)) {
                                try {
                                    require_once __DIR__ . '/../includes/SMSGateway.php';
                                    $smsGateway = new SMSGateway();
                                    $smsGateway->sendHousingUpdate($phone, $hinfo['full_name'], $hinfo);
                                } catch (\Throwable $smsErr) {
                                    error_log("Housing modal update SMS error: " . $smsErr->getMessage());
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log("Housing notification error: " . $e->getMessage());
                    }
                    
                    if ($return_to === 'view') {
                        $_SESSION['success'] = "Application status updated successfully!";
                        redirect("view-housing.php?id=" . $beneficiary_id);
                    } else {
                        $success = "Application status updated successfully!";
                    }
                } else {
                    $err = "Failed to update application status.";
                    if ($return_to === 'view') {
                        $_SESSION['error'] = $err;
                        redirect("view-housing.php?id=" . $beneficiary_id);
                    } else {
                        $error = $err;
                    }
                }
            }
            }
        }
    }
}

// Get all housing programs for filter
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->query($programs_query);

// Build main query
$query = "SELECT h.*, p.program_name, p.location, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          WHERE 1=1";

$params = [];

if ($program_filter != 'all') {
    $query .= " AND h.program_id = :program";
    $params[':program'] = $program_filter;
}

if ($status_filter != 'all') {
    $query .= " AND h.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter != 'all') {
    $query .= " AND h.housing_type = :housing_type";
    $params[':housing_type'] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY h.application_date DESC";

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) FROM housing_beneficiaries h
                JOIN housing_programs p ON h.program_id = p.program_id
                JOIN users u ON h.user_id = u.user_id
                WHERE 1=1";
if ($program_filter != 'all') {
    $count_query .= " AND h.program_id = :program";
}
if ($status_filter != 'all') {
    $count_query .= " AND h.status = :status";
}
if ($type_filter != 'all') {
    $count_query .= " AND h.housing_type = :housing_type";
}
if (!empty($search)) {
    $count_query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search)";
}
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$query .= " LIMIT :offset, :per_page";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
    FROM housing_beneficiaries";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get staff info for display
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
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

$page_title = 'Manage Housing - Staff Portal';
$active_nav = 'housing';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); display: none; justify-content: center; align-items: center; z-index: 2000; backdrop-filter: blur(4px); }
.modal.active { display: flex; }
.modal-content { background: var(--ref-card); border-radius: 24px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: var(--ref-shadow-lg); border: 1px solid var(--ref-border); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--ref-border); display: flex; justify-content: space-between; align-items: center; background: var(--ref-card-subtle); }
.modal-header h2 { font-size: 1.1rem; font-weight: 800; color: var(--ref-text); display: flex; align-items: center; gap: 10px; margin: 0; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--ref-muted); line-height: 1; }
.modal-body { padding: 22px 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--ref-border); display: flex; justify-content: flex-end; gap: 12px; background: var(--ref-card-subtle); }
.info-card-mini { background: var(--ref-canvas); border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; border: 1px solid var(--ref-border); }
.info-row-mini { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 4px; }
.info-label-mini { color: var(--ref-muted); font-weight: 600; }
.info-value-mini { color: var(--ref-text); font-weight: 700; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 700; color: var(--ref-text); font-size: 0.8rem; }
.form-group small { display: block; margin-top: 4px; font-size: 0.72rem; color: var(--ref-muted); }
.applicant-info { display: flex; align-items: center; gap: 10px; }
.applicant-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--ref-canvas); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-weight: 700; font-size: 0.72rem; color: var(--ref-maroon); border: 1px solid var(--ref-border); }
.applicant-avatar img { width: 100%; height: 100%; object-fit: cover; }
.applicant-name { font-weight: 700; color: var(--ref-text); }
.applicant-contact { font-size: 0.72rem; color: var(--ref-muted); }
.action-buttons-group { display: flex; gap: 6px; }
.btn-icon { padding: 5px 12px; border-radius: 50px; font-size: 0.72rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; transition: all 0.2s; }
.btn-update { background: rgba(245, 158, 11, 0.12); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.25); }
.btn-update:hover { background: #D97706; color: #FFFFFF; }

/* Quick Stats Bar & Clean Export/Report Buttons - Part 2, Item 5 */
.quick-stats-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--ref-card);
    border-radius: 18px;
    padding: 14px 20px;
    margin-bottom: 18px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
    flex-wrap: wrap;
    gap: 12px;
}
.program-info h4 {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--ref-text);
    margin: 0;
}
.program-info .count {
    font-size: 0.74rem;
    color: var(--ref-muted);
    font-weight: 600;
    margin-top: 2px;
}
.quick-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.quick-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid var(--ref-border);
    background: var(--ref-card);
    color: var(--ref-text);
    cursor: pointer;
}
.quick-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.quick-btn.export-btn {
    background: #FEF7DF;
    color: #92400E;
    border-color: rgba(245, 158, 11, 0.35);
}
.quick-btn.export-btn:hover {
    background: #FDE68A;
    color: #78350F;
}
.quick-btn.reports-btn {
    background: var(--ref-canvas);
    color: var(--ref-text);
}
.quick-btn.reports-btn:hover {
    background: var(--ref-card);
    color: var(--ref-maroon);
    border-color: rgba(127, 3, 3, 0.3);
}

.filter-bar { background: var(--ref-card); border-radius: 20px; padding: 16px 20px; margin-bottom: 18px; border: 1px solid var(--ref-border); box-shadow: var(--ref-shadow-sm); }
.filter-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.2fr auto; gap: 14px; align-items: end; }
@media (max-width: 900px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .filter-grid { grid-template-columns: 1fr; } }

/* Process Flow Pipeline Ribbon */
.flow-pipeline-bar {
    display: flex;
    align-items: center;
    background: var(--ref-card, #FFFFFF);
    border: 1px solid var(--ref-border, #E2E8F0);
    border-radius: 18px;
    padding: 12px 18px;
    margin-bottom: 20px;
    box-shadow: var(--ref-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
    overflow-x: auto;
    gap: 12px;
}
.flow-step-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 12px;
    background: var(--ref-card-subtle, #F1F5F9);
    flex: 1;
    min-width: 175px;
    border: 1px solid transparent;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}
.flow-step-item:hover {
    border-color: var(--ref-border);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.flow-step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    color: white;
    flex-shrink: 0;
}
.flow-step-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}
.flow-step-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ref-text);
}
.flow-step-desc {
    font-size: 0.68rem;
    color: var(--ref-muted);
    margin-top: 2px;
}
.flow-arrow-sep {
    color: var(--ref-muted);
    opacity: 0.45;
    font-size: 0.85rem;
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
            $banner_title = "Housing Applications & Beneficiaries";
            $banner_subtitle = "Review, verify, and process socialized housing applications and resettlement pipelines";
            $banner_icon = "fa-home";
            $banner_actions = [
                ["label" => "Table View", "url" => "manage-housing.php", "icon" => "fa-table", "type" => "secondary"],
                ["label" => "Kanban Board", "url" => "kanban-housing.php", "icon" => "fa-columns", "type" => "primary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

            <!-- Process Flow Pipeline Ribbon -->
            <div class="flow-pipeline-bar">
                <a href="manage-housing.php?status=pending" class="flow-step-item" title="Filter by Pending applications">
                    <div class="flow-step-num" style="background: #FF9800;">1</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Screening (<?php echo $stats['pending']; ?>)</span>
                        <span class="flow-step-desc">Application &amp; Residency Verification</span>
                    </div>
                </a>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <a href="manage-housing.php?status=qualified" class="flow-step-item" title="Filter by Qualified applications">
                    <div class="flow-step-num" style="background: #2196F3;">2</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Assessment (<?php echo $stats['qualified']; ?>)</span>
                        <span class="flow-step-desc">Caseworker Intake &amp; Field Check</span>
                    </div>
                </a>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <a href="manage-housing.php?status=approved" class="flow-step-item" title="Filter by Approved applications">
                    <div class="flow-step-num" style="background: #4CAF50;">3</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Allocated (<?php echo $stats['approved']; ?>)</span>
                        <span class="flow-step-desc">Clearance Granted &amp; Lot Assigned</span>
                    </div>
                </a>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <a href="manage-housing.php?status=released" class="flow-step-item" title="Filter by Released applications">
                    <div class="flow-step-num" style="background: #9C27B0;">4</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Turnover (<?php echo $stats['released']; ?>)</span>
                        <span class="flow-step-desc">Grant Disbursed or Keys Handed</span>
                    </div>
                </a>
            </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=all'">
                <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=pending'">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=qualified'">
                <div class="stat-icon qualified"><i class="fas fa-search"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['qualified']; ?></h3>
                    <p>Qualified</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=approved'">
                <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='manage-housing.php?status=released'">
                <div class="stat-icon released"><i class="fas fa-gift"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['released']; ?></h3>
                    <p>Released</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats Bar -->
        <div class="quick-stats-bar">
            <div class="program-info">
                <h4>Active Housing Programs</h4>
                <div class="count"><?php echo $programs_stmt->rowCount(); ?> Programs (Relocation &amp; In-Site)</div>
            </div>
            <div class="quick-actions">
                <a href="reports.php?category=housing" class="quick-btn reports-btn" title="View Housing Sector Reports">
                    <i class="fas fa-chart-line"></i> View Reports
                </a>
                <a href="export-housing.php?program=<?php echo urlencode($program_filter); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&search=<?php echo urlencode($search); ?>" class="quick-btn export-btn" title="Export Filtered Applications to CSV">
                    <i class="fas fa-file-export"></i> Export Data
                </a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Program</label>
                        <select name="program" id="programFilter">
                            <option value="all">All Programs</option>
                            <?php 
                            $programs_stmt = $db->query("SELECT * FROM housing_programs WHERE status = 'active'");
                            while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                            <option value="<?php echo $program['program_id']; ?>" 
                                <?php echo $program_filter == $program['program_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><i class="fas fa-layer-group"></i> Pathway Type</label>
                        <select name="type" id="typeFilter">
                            <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="relocation" <?php echo $type_filter == 'relocation' ? 'selected' : ''; ?>>Relocation Housing</option>
                            <option value="in_site" <?php echo $type_filter == 'in_site' ? 'selected' : ''; ?>>In-Site Housing</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status" id="statusFilter">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="qualified" <?php echo $status_filter == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved</option>
                            <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Name or contact..." 
                                value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <?php if($program_filter != 'all' || $status_filter != 'all' || $type_filter != 'all' || !empty($search)): ?>
                        <a href="manage-housing.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Program / Pathway</th>
                        <th>Household</th>
                        <th>Income</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php 
                        $hsg_idx = 0;
                        while($app = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            $is_latest = ($page === 1 && $hsg_idx === 0);
                            $hsg_idx++;
                            $app_initials = strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1));
                            $app_pic = !empty($app['profile_picture']) && file_exists(__DIR__ . '/../' . $app['profile_picture']) 
                                ? '../' . $app['profile_picture'] 
                                : '';
                        ?>
                        <tr class="<?php echo $is_latest ? 'row-latest-entry' : ''; ?>">
                            <td>
                                <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                    <strong style="color: var(--maroon-light);">#<?php echo $app['beneficiary_id']; ?></strong>
                                    <?php if ($is_latest): ?>
                                        <span class="badge-latest-tag"><i class="fas fa-sparkles"></i> LATEST</span>
                                    <?php endif; ?>
                                </div>
                                <?php $tracking_no = 'HSG-' . date('Y', strtotime($app['application_date'] ?? 'now')) . '-' . str_pad($app['beneficiary_id'], 5, '0', STR_PAD_LEFT); ?>
                                <div style="font-family: monospace; font-size: 0.68rem; font-weight: 700; color: var(--ref-muted); margin-top: 2px;">
                                    <?php echo htmlspecialchars($tracking_no); ?>
                                </div>
                            </td>
                            <td>
                                <div class="applicant-info">
                                    <div class="applicant-avatar">
                                        <?php if(!empty($app_pic)): ?>
                                            <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Profile">
                                        <?php else: ?>
                                            <span class="initials"><?php echo $app_initials; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="applicant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                        <div class="applicant-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($app['contact_number']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--ref-text);"><?php echo htmlspecialchars($app['program_name']); ?></div>
                                <div style="margin-top: 4px;">
                                    <?php if(($app['housing_type'] ?? 'relocation') === 'in_site'): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; background: rgba(46, 125, 50, 0.12); color: #2e7d32; border: 1px solid rgba(46, 125, 50, 0.25);">
                                            <i class="fas fa-house-chimney-user"></i> In-Site
                                        </span>
                                        <span style="font-size: 0.72rem; color: var(--ref-muted); display: block; margin-top: 2px;">
                                            <i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars($app['property_location'] ?: $app['location']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; background: rgba(15, 65, 74, 0.12); color: #0F414A; border: 1px solid rgba(15, 65, 74, 0.25);">
                                            <i class="fas fa-truck-ramp-box"></i> Relocation
                                        </span>
                                        <span style="font-size: 0.72rem; color: var(--ref-muted); display: block; margin-top: 2px;">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($app['location']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $app['household_composition']; ?></td>
                            <td>₱<?php echo number_format($app['monthly_income'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $app['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $app['status'] == 'pending' ? 'clock' : 
                                            ($app['status'] == 'qualified' ? 'search' : 
                                            ($app['status'] == 'approved' ? 'check-circle' : 
                                            ($app['status'] == 'released' ? 'gift' : 'times-circle'))); 
                                    ?>"></i>
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: center; position: relative;">
                                <div class="row-action-dropdown">
                                    <button type="button" class="btn-dot-action" onclick="toggleRowAction(event, this)" title="Actions" aria-label="Actions">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="row-action-menu">
                                        <?php if ($app['status'] === 'released'): ?>
                                            <div class="action-menu-item" style="color:#059669; cursor:default; font-weight:700; background:rgba(16,185,129,0.08); border-radius:6px;">
                                                <i class="fas fa-lock" style="color:#059669;"></i> Released (Locked)
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="action-menu-item" onclick="showUpdateModal(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                <i class="fas fa-edit" style="color: var(--ref-maroon);"></i> Update Status
                                            </button>
                                        <?php endif; ?>
                                        <a href="view-housing.php?id=<?php echo $app['beneficiary_id']; ?>" class="action-menu-item">
                                            <i class="fas fa-eye" style="color: #2563EB;"></i> View Details
                                        </a>
                                        <a href="../print-housing-slip.php?id=<?php echo $app['beneficiary_id']; ?>" target="_blank" class="action-menu-item">
                                            <i class="fas fa-print" style="color: #059669;"></i> Print Slip
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No housing applications found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): 
                $hsg_params = $_GET;
            ?>
            <div class="pagination">
                <span class="page-info">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total)</span>
                <div class="pagination-links">
                    <?php if ($page > 1): 
                        $hsg_params['page'] = $page - 1;
                    ?>
                        <a href="?<?php echo http_build_query($hsg_params); ?>" title="Previous Page">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled" title="No Previous Page"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        $hsg_params['page'] = 1;
                        echo '<a href="?' . http_build_query($hsg_params) . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="ellipsis">...</span>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $page ? 'active' : '';
                        $hsg_params['page'] = $i;
                        echo '<a href="?' . http_build_query($hsg_params) . '" class="' . $active . '">' . $i . '</a>';
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="ellipsis">...</span>';
                        }
                        $hsg_params['page'] = $total_pages;
                        echo '<a href="?' . http_build_query($hsg_params) . '">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($page < $total_pages): 
                        $hsg_params['page'] = $page + 1;
                    ?>
                        <a href="?<?php echo http_build_query($hsg_params); ?>" title="Next Page">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled" title="No Next Page"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<!-- Update Status Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Housing Application</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" onsubmit="return validateHousingModal(event)">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <input type="hidden" name="beneficiary_id" id="modal_beneficiary_id">
                
                <div class="info-card-mini">
                    <div class="info-row-mini">
                        <span class="info-label-mini">Applicant:</span>
                        <span class="info-value-mini" id="modal_applicant"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Program:</span>
                        <span class="info-value-mini" id="modal_program"></span>
                    </div>
                    <div class="info-row-mini">
                        <span class="info-label-mini">Assistance Pathway:</span>
                        <span class="info-value-mini" id="modal_pathway"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleHousingFields()">
                        <option value="qualified">📋 Mark as Qualified</option>
                        <option value="approved">✅ Approve & Assign Unit / Grant</option>
                        <option value="rejected">❌ Disapprove</option>
                        <option value="released">💰 Mark as Released</option>
                        <option value="pending">⏰ Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group" id="unit_field" style="display: none;">
                    <label for="unit_assigned" id="unit_assigned_label">Unit Number / House Assignment</label>
                    <input type="text" name="unit_assigned" id="unit_assigned" 
                           placeholder="e.g., Block 1, Lot 5, Unit 203 or Grant Ref Code">
                    <small id="unit_assigned_small">The housing unit assigned or lot grant reference</small>
                </div>
                
                <div class="form-group" id="interview_field" style="display: none;">
                    <label for="interview_date" id="interview_date_label">Interview / Site Visit Date</label>
                    <input type="date" name="interview_date" id="interview_date"
                           min="<?php echo date('Y-m-d'); ?>">
                    <small id="interview_date_small">Schedule for interview or on-site lot inspection</small>
                </div>

                <div class="form-group" id="move_in_field" style="display: none;">
                    <label for="move_in_date" id="move_in_date_label">Move-in / Turn-Over Date</label>
                    <input type="date" name="move_in_date" id="move_in_date">
                    <small id="move_in_date_small">Official date for release or move-in</small>
                </div>

                <div class="form-group" id="assessment_field" style="display: none;">
                    <label for="assessment_notes" id="assessment_notes_label">Caseworker Assessment / Clearance Notes</label>
                    <textarea name="assessment_notes" id="assessment_notes" rows="2" 
                              placeholder="e.g., Land ownership documents verified, lot inspected and cleared for construction..."></textarea>
                    <small id="assessment_notes_small">Caseworker notes to record on this application</small>
                </div>
                
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label for="remarks">Disapproval Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" 
                              placeholder="Enter reason for disapproval..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="update_status" class="btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
<script>

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
            window.location.href = 'manage-housing.php?search=' + encodeURIComponent(query);
        }
    }
});

// ========== MODAL FUNCTIONS ==========
function validateHousingModal(e) {
    const status = document.getElementById('modal_status').value;
    if (status === 'released') {
        if (!confirm('Confirm marking this housing application as RELEASED (Turned Over / Awarded)? Once released, the beneficiary allocation is finalized.')) {
            e.preventDefault();
            return false;
        }
    } else if (status === 'rejected') {
        const remarks = document.getElementById('remarks').value.trim();
        if (!remarks) {
            alert('Please enter a disapproval reason.');
            document.getElementById('remarks').focus();
            e.preventDefault();
            return false;
        }
        if (!confirm('Are you sure you want to DISAPPROVE this housing application?')) {
            e.preventDefault();
            return false;
        }
    }
    return true;
}

function showUpdateModal(application) {
    if (application.status === 'released') {
        alert('This housing allocation has already been released (awarded / turned over). Completed allocations cannot be modified.');
        return;
    }
    document.getElementById('modal_beneficiary_id').value = application.beneficiary_id;
    document.getElementById('modal_applicant').textContent = 
        application.first_name + ' ' + application.last_name;
    document.getElementById('modal_program').textContent = application.program_name;
    
    const isInsite = (application.housing_type === 'in_site');
    const pathwayEl = document.getElementById('modal_pathway');
    if (pathwayEl) {
        pathwayEl.innerHTML = isInsite 
            ? '<span style="color:#2e7d32; font-weight:700;"><i class="fas fa-house-chimney-user"></i> In-Site Housing (Sariling Lupa)</span>' 
            : '<span style="color:#0F414A; font-weight:700;"><i class="fas fa-truck-ramp-box"></i> Relocation Housing</span>';
    }
    const unitLabel = document.getElementById('unit_assigned_label');
    const unitSmall = document.getElementById('unit_assigned_small');
    if (unitLabel && unitSmall) {
        if (isInsite) {
            unitLabel.textContent = 'Lot Inspection Ref / Building Grant Code';
            unitSmall.textContent = 'On-site lot validation reference or municipal building grant code';
        } else {
            unitLabel.textContent = 'Unit Number / House Assignment';
            unitSmall.textContent = 'The housing unit assigned to the beneficiary';
        }
    }
    const interviewLabel = document.getElementById('interview_date_label');
    const interviewSmall = document.getElementById('interview_date_small');
    if (interviewLabel && interviewSmall) {
        interviewLabel.textContent = isInsite ? 'Site Verification / Field Inspection Date' : 'Interview / Family Assessment Date';
        interviewSmall.textContent = isInsite ? 'Schedule for on-site lot and construction inspection' : 'Schedule for caseworker interview and assessment';
    }
    const moveInLabel = document.getElementById('move_in_date_label');
    const moveInSmall = document.getElementById('move_in_date_small');
    if (moveInLabel && moveInSmall) {
        moveInLabel.textContent = isInsite ? 'Grant Voucher Release / Turn-Over Date' : 'Move-in / Unit Turn-Over Date';
        moveInSmall.textContent = isInsite ? 'Official date for release of building grant package' : 'Official move-in date for assigned housing unit';
    }

    // Pre-populate application values
    document.getElementById('modal_status').value = application.status || 'qualified';
    document.getElementById('unit_assigned').value = application.unit_assigned || '';
    document.getElementById('interview_date').value = application.interview_date || '';
    document.getElementById('move_in_date').value = application.move_in_date || (application.status === 'released' ? '<?php echo date('Y-m-d'); ?>' : '');
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
    if (document.getElementById('assessment_field')) document.getElementById('assessment_field').style.display = 'none';
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

function filterByStatus(status) {
    document.getElementById('statusFilter').value = status;
    document.getElementById('filterForm').submit();
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('updateModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Auto-filter on dropdowns and debounced search
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    if (!form) return;

    ['statusFilter', 'typeFilter', 'programFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => form.submit());
        }
    });

    const searchInput = form.querySelector('input[name="search"]');
    if (searchInput) {
        let debounceTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                form.submit();
            }, 500);
        });
    }
});

// Three-Dot Row Action Dropdown Toggle (Part 2, Item 2)
function toggleRowAction(event, button) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    const dropdown = button.closest('.row-action-dropdown');
    const wasActive = dropdown.classList.contains('active');
    document.querySelectorAll('.row-action-dropdown.active').forEach(d => d.classList.remove('active'));
    if (!wasActive) {
        dropdown.classList.add('active');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.row-action-dropdown')) {
        document.querySelectorAll('.row-action-dropdown.active').forEach(d => d.classList.remove('active'));
    }
});
</script>
</body>
</html>

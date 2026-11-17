<?php
// staff/update-housing-status-ajax.php - AJAX endpoint to adjust and update Housing application status
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if staff has Housing service category permission
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

if (!in_array('Housing', $assigned_categories)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to manage Housing applications.']);
    exit;
}

// Support both JSON input and standard form POST
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];

$beneficiary_id = intval($json_data['beneficiary_id'] ?? $_POST['beneficiary_id'] ?? 0);
$status = sanitize($json_data['status'] ?? $_POST['status'] ?? '');
$unit_assigned = sanitize($json_data['unit_assigned'] ?? $_POST['unit_assigned'] ?? '');
$interview_date = sanitize($json_data['interview_date'] ?? $_POST['interview_date'] ?? '');
$move_in_date = sanitize($json_data['move_in_date'] ?? $_POST['move_in_date'] ?? '');
$remarks = sanitize($json_data['remarks'] ?? $_POST['remarks'] ?? '');
$assessment_notes = sanitize($json_data['assessment_notes'] ?? $_POST['assessment_notes'] ?? '');

$allowed_statuses = ['pending', 'qualified', 'approved', 'rejected', 'released'];

if ($beneficiary_id <= 0 || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing application parameters.']);
    exit;
}

try {
    // Fetch current application record & program details
    $curr_stmt = $db->prepare("SELECT h.*, p.program_name, p.housing_type as prog_type, p.total_units, u.first_name, u.last_name, u.email 
                               FROM housing_beneficiaries h 
                               JOIN housing_programs p ON h.program_id = p.program_id 
                               JOIN users u ON h.user_id = u.user_id 
                               WHERE h.beneficiary_id = :id");
    $curr_stmt->execute([':id' => $beneficiary_id]);
    $current_app = $curr_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_app) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Housing application record not found.']);
        exit;
    }

    // Rule: Released/awarded housing allocations cannot be reverted
    if (strtolower($current_app['status'] ?? '') === 'released' && $status !== 'released') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This housing allocation has already been released (turned over / awarded). Completed allocations cannot be reverted.'
        ]);
        exit;
    }

    $is_insite = (($current_app['housing_type'] ?? '') === 'in_site');
    $unit_label = $is_insite ? 'Grant / Voucher Reference' : 'Unit Assignment';

    // Guard 1: Duplicate Unit / Voucher Assignment Check on Approval
    if ($status === 'approved' && !empty($unit_assigned)) {
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
            http_response_code(409);
            echo json_encode([
                'success' => false, 
                'message' => "Conflict: {$unit_label} '" . htmlspecialchars($unit_assigned) . "' is already assigned to {$dup['first_name']} {$dup['last_name']} (Application #{$dup['beneficiary_id']})."
            ]);
            exit;
        }
    }

    // Guard 2: Program Capacity Check on Approval
    if ($status === 'approved' && !empty($current_app['total_units']) && (int)$current_app['total_units'] > 0) {
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
            $cap_term = $is_insite ? 'grant allocations' : 'housing units';
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Capacity limit reached for {$current_app['program_name']}. All {$current_app['total_units']} {$cap_term} are already allocated ({$occupied} active beneficiaries)."
            ]);
            exit;
        }
    }

    $db->beginTransaction();

    // Determine final values
    $final_unit = !empty($unit_assigned) ? $unit_assigned : $current_app['unit_assigned'];
    $final_interview_date = !empty($interview_date) ? $interview_date : $current_app['interview_date'];
    $final_move_in = !empty($move_in_date) ? $move_in_date : ($status === 'released' ? ($current_app['move_in_date'] ?: date('Y-m-d')) : $current_app['move_in_date']);

    // Build update query
    $update_query = "UPDATE housing_beneficiaries SET 
                      status = :status,
                      unit_assigned = :unit,
                      interview_date = :interview_date,
                      move_in_date = :move_in_date";
    
    if ($status === 'approved') {
        $update_query .= ", approved_by = :user_id, approval_date = IFNULL(approval_date, NOW())";
        if (!empty($assessment_notes)) {
            $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nApproval Clearance: ', :notes)";
        }
    } elseif ($status === 'qualified') {
        if (!empty($assessment_notes)) {
            $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nQualification Assessment: ', :notes)";
        }
    } elseif ($status === 'rejected') {
        if (!empty($remarks)) {
            $update_query .= ", housing_need_assessment = CONCAT(IFNULL(housing_need_assessment, ''), '\nRejection Reason: ', :remarks)";
        }
    }
    
    $update_query .= " WHERE beneficiary_id = :id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $status);
    $update_stmt->bindParam(':unit', $final_unit);
    $update_stmt->bindParam(':interview_date', $final_interview_date);
    $update_stmt->bindParam(':move_in_date', $final_move_in);
    
    if ($status === 'approved') {
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        if (!empty($assessment_notes)) {
            $update_stmt->bindParam(':notes', $assessment_notes);
        }
    } elseif ($status === 'qualified') {
        if (!empty($assessment_notes)) {
            $update_stmt->bindParam(':notes', $assessment_notes);
        }
    } elseif ($status === 'rejected') {
        if (!empty($remarks)) {
            $update_stmt->bindParam(':remarks', $remarks);
        }
    }
    
    $update_stmt->bindParam(':id', $beneficiary_id);
    $update_stmt->execute();

    $app_year = !empty($current_app['application_date']) ? date('Y', strtotime($current_app['application_date'])) : date('Y');
    $tracking_no = "HSG-" . $app_year . "-" . str_pad($beneficiary_id, 5, '0', STR_PAD_LEFT);

    // Auto Link Schedule to Appointments Table
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

    // Resident In-App Notification
    if (!empty($current_app['user_id'])) {
        $notif_text = "Your housing application ({$tracking_no}) status has been updated to: " . ucfirst($status) . ".";
        if (!empty($interview_date)) {
            $notif_text .= " A schedule is confirmed for " . date('M d, Y', strtotime($interview_date)) . " at 09:00 AM (Window 2 - Housing Division). Check My Appointments for your QR slip.";
        }
        if ($status === 'approved' && !empty($final_unit)) {
            $notif_text .= " Assigned " . ($is_insite ? "Grant Voucher: " : "Unit: ") . $final_unit . ".";
        }
        if ($status === 'released' && !empty($final_move_in)) {
            $notif_text .= " Official " . ($is_insite ? "Grant Release Date: " : "Move-in Date: ") . date('M d, Y', strtotime($final_move_in)) . ".";
        }

        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Housing Application Updated', :msg, 'housing')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $current_app['user_id']);
        $notif_stmt->bindParam(':msg', $notif_text);
        $notif_stmt->execute();
    }

    // Resident Email & SMS Notification
    try {
        $full_info_stmt = $db->prepare("SELECT h.*, p.program_name, p.location, u.first_name, u.last_name, u.email, u.contact_number,
                                        CONCAT(u.first_name, ' ', u.last_name) as full_name
                                        FROM housing_beneficiaries h
                                        JOIN housing_programs p ON h.program_id = p.program_id
                                        JOIN users u ON h.user_id = u.user_id
                                        WHERE h.beneficiary_id = :id");
        $full_info_stmt->execute([':id' => $beneficiary_id]);
        $hinfo = $full_info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hinfo) {
            // Dispatch Email
            if (!empty($hinfo['email'])) {
                require_once __DIR__ . '/../includes/Mailer.php';
                $mailer = new Mailer();
                if (method_exists($mailer, 'sendHousingUpdate')) {
                    $mailer->sendHousingUpdate($hinfo['email'], $hinfo['full_name'], $hinfo);
                }
            }

            // Dispatch SMS via TextBee
            $phone = $hinfo['contact_number'] ?? '';
            if (!empty($phone)) {
                try {
                    require_once __DIR__ . '/../includes/SMSGateway.php';
                    $smsGateway = new SMSGateway();
                    $smsGateway->sendHousingUpdate($phone, $hinfo['full_name'], $hinfo);
                } catch (\Throwable $smsErr) {
                    error_log("Housing AJAX update SMS error: " . $smsErr->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("Housing notification error: " . $e->getMessage());
    }

    // Record Audit Trail
    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) 
                  VALUES (:user_id, 'update_housing_status', 'housing_beneficiaries', :details)";
    $log_stmt = $db->prepare($log_query);
    $log_details = "Updated Housing Application $tracking_no status to " . strtoupper($status);
    if (!empty($final_unit)) {
        $log_details .= " [Unit/Ref: " . $final_unit . "]";
    }
    if (!empty($final_interview_date)) {
        $log_details .= " [Interview: " . $final_interview_date . "]";
    }
    if (!empty($final_move_in)) {
        $log_details .= " [Move-in: " . $final_move_in . "]";
    }
    if (!empty($remarks)) {
        $log_details .= " [Remarks: " . $remarks . "]";
    }
    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $log_stmt->bindParam(':details', $log_details);
    $log_stmt->execute();

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Application {$tracking_no} successfully updated to " . ucfirst($status) . ".",
        'beneficiary_id' => $beneficiary_id,
        'status' => $status,
        'tracking_no' => $tracking_no,
        'unit_assigned' => $final_unit,
        'interview_date' => $final_interview_date,
        'move_in_date' => $final_move_in
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

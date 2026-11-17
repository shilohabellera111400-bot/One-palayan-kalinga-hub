<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if staff has AICS service category permission
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

if (!isAdmin() && !in_array('AICS', $assigned_categories)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to manage AICS applications.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$aics_id = intval($data['aics_id'] ?? 0);
$status = sanitize($data['status'] ?? '');
$audit_override_reason = sanitize($data['audit_override_reason'] ?? '');

$allowed_statuses = ['submitted', 'reviewing', 'approved', 'rejected', 'released'];

if ($aics_id <= 0 || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Validation: Assistance that has already been released/disbursed is permanently locked
    $chk_stmt = $db->prepare("SELECT status FROM aics_requests WHERE aics_id = :id");
    $chk_stmt->execute([':id' => $aics_id]);
    $current_aics_status = $chk_stmt->fetchColumn();

    if ($current_aics_status && strtolower($current_aics_status) === 'released') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This AICS assistance has already been released and disbursed. Completed transactions cannot be modified.']);
        exit;
    }

    // Direct release without approval is forbidden
    if ($status === 'released' && strtolower($current_aics_status ?? '') !== 'approved') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assistance must be reviewed and approved with allocated funds before it can be marked as released.']);
        exit;
    }

    // Rejecting an approved application requires administrative justification
    if ($status === 'rejected' && strtolower($current_aics_status ?? '') === 'approved') {
        if (empty($audit_override_reason) || strlen($audit_override_reason) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Revoking or disapproving an already approved assistance requires an administrative justification of at least 10 characters.']);
            exit;
        }
    }

    // Build update query
    $query = "UPDATE aics_requests SET status = :status";
    
    if ($status === 'reviewing') {
        $query .= ", reviewed_by = :staff_id, review_date = NOW()";
    } elseif ($status === 'approved') {
        $query .= ", approved_by = :staff_id, approval_date = NOW()";
        if (!empty($audit_override_reason)) {
            $query .= ", audit_override_reason = :audit_override, is_frequency_exception = 1";
        }
    } elseif ($status === 'released') {
        $query .= ", release_date = NOW()";
    }
    
    $query .= " WHERE aics_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $aics_id);
    
    if ($status === 'reviewing' || $status === 'approved') {
        $stmt->bindParam(':staff_id', $_SESSION['user_id']);
    }
    if ($status === 'approved' && !empty($audit_override_reason)) {
        $stmt->bindParam(':audit_override', $audit_override_reason);
    }
    
    if ($stmt->execute()) {
        // Fetch request details for user notification and email
        $req_query = "SELECT a.*, u.email, u.first_name, u.last_name, u.contact_number 
                      FROM aics_requests a 
                      JOIN users u ON a.user_id = u.user_id 
                      WHERE a.aics_id = :id";
        $req_stmt = $db->prepare($req_query);
        $req_stmt->bindParam(':id', $aics_id);
        $req_stmt->execute();
        $request_info = $req_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request_info) {
            $user_id = $request_info['user_id'];
            $type_label = strtoupper(str_replace('_', ' ', $request_info['request_type']));
            
            $notif_msg = "Ang inyong AICS $type_label request ay may bagong status: " . strtoupper($status) . ".";
            if ($status === 'reviewing') {
                $notif_msg = "Ang inyong AICS $type_label request ay nasa status na ngayon na UNDER REVIEW. Mangyaring pumunta sa CSWDO office sa Window 1, 2, o 3 para sa inyong interview at assessment. Dalhin ang inyong orihinal na mga dokumento.";
            } elseif ($status === 'approved') {
                $notif_msg = "Ang inyong AICS $type_label request ay APPROVED na at naipasa na sa City Budget Office para sa voucher processing. Mangyaring maghintay ng tawag o text para sa pag-release.";
            }

            // Create user notification
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'AICS Status Updated', :msg, 'aics')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $user_id);
            $notif_stmt->bindParam(':msg', $notif_msg);
            $notif_stmt->execute();
            
            // Construct the necessary fields for notifications
            $user_name = $request_info['first_name'] . ' ' . $request_info['last_name'];
            $aics_data = [
                'aics_id' => $aics_id,
                'status' => $status,
                'request_type' => $request_info['request_type'],
                'amount_requested' => $request_info['amount_requested'],
                'amount_approved' => $request_info['amount_approved'],
                'rejection_reason' => $request_info['rejection_reason'],
                'user_id' => $user_id
            ];

            // Send email notification
            try {
                require_once __DIR__ . '/../includes/Mailer.php';
                $mailer = new Mailer();
                if (!empty($request_info['email'])) {
                    $mailer->sendAICSUpdate($request_info['email'], $user_name, $aics_data);
                }
            } catch (Exception $e) {
                error_log("Kanban status update email failed: " . $e->getMessage());
            }

            // Send SMS notification via TextBee
            $phone = $request_info['contact_number'] ?? '';
            if (!empty($phone)) {
                try {
                    require_once __DIR__ . '/../includes/SMSGateway.php';
                    $smsGateway = new SMSGateway();
                    $smsGateway->sendAICSUpdate($phone, $user_name, $aics_data);
                } catch (Throwable $e) {
                    error_log("Kanban status update SMS failed: " . $e->getMessage());
                }
            }
        }
        
        // Log audit trail
        $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'update_aics_status', 'aics_requests', :details)";
        $log_stmt = $db->prepare($log_query);
        $log_details = "Updated AICS ID $aics_id status to $status via Kanban Board";
        if (!empty($audit_override_reason)) {
            $log_details .= " [90-Day Policy Override Justification: " . $audit_override_reason . "]";
        }
        $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $log_stmt->bindParam(':details', $log_details);
        $log_stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

$aics_id = intval($data['aics_id'] ?? 0);
$status = sanitize($data['status'] ?? '');

$allowed_statuses = ['submitted', 'reviewing', 'approved', 'rejected', 'released'];

if ($aics_id <= 0 || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Update request status
    $query = "UPDATE aics_requests SET status = :status WHERE aics_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $aics_id);
    
    if ($stmt->execute()) {
        // Fetch request details for user notification and email
        $req_query = "SELECT a.*, u.email, u.first_name, u.last_name 
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
            
            // Send email notification
            try {
                require_once __DIR__ . '/../includes/Mailer.php';
                $mailer = new Mailer();
                $user_name = $request_info['first_name'] . ' ' . $request_info['last_name'];
                
                // Construct the necessary fields for sendAICSUpdate
                $aics_data = [
                    'aics_id' => $aics_id,
                    'status' => $status,
                    'request_type' => $request_info['request_type'],
                    'amount_requested' => $request_info['amount_requested'],
                    'amount_approved' => $request_info['amount_approved'],
                    'rejection_reason' => $request_info['rejection_reason'],
                    'user_id' => $user_id
                ];
                
                $mailer->sendAICSUpdate($request_info['email'], $user_name, $aics_data);
            } catch (Exception $e) {
                error_log("Kanban status update email failed: " . $e->getMessage());
            }
        }
        
        // Log audit trail
        $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'update_aics_status', 'aics_requests', :details)";
        $log_stmt = $db->prepare($log_query);
        $log_details = "Updated AICS ID $aics_id status to $status via Kanban Board";
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

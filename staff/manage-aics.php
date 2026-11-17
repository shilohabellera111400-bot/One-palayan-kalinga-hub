<?php
// staff/manage-aics.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/aics-audit-helper.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for access control
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

// Get assigned service categories for this staff
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if staff has AICS access
$has_aics_access = in_array('AICS', $assigned_categories);
$has_housing_access = in_array('Housing', $assigned_categories);
$has_senior_access = in_array('Senior Citizen', $assigned_categories);
$has_pwd_access = in_array('PWD', $assigned_categories);
$has_verif_access = in_array('Verification', $assigned_categories) || in_array('Residency Verification', $assigned_categories);

// If staff doesn't have AICS access, redirect
if (!$has_aics_access) {
    $_SESSION['error'] = "You don't have permission to access AICS Management.";
    redirect('../staff-dashboard.php');
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
$min_amount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? floatval($_GET['min_amount']) : '';
$max_amount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? floatval($_GET['max_amount']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$base_query = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture 
               FROM aics_requests a
               JOIN users u ON a.user_id = u.user_id";
$count_query = "SELECT COUNT(*) as total 
                FROM aics_requests a
                JOIN users u ON a.user_id = u.user_id";

$where = [];
$params = [];

if ($status_filter != 'all') {
    $where[] = "a.status = :status";
    $params[':status'] = $status_filter;
}

if ($type_filter != 'all') {
    $where[] = "a.request_type = :type";
    $params[':type'] = $type_filter;
}

if (!empty($start_date)) {
    $where[] = "a.submission_date >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    $where[] = "a.submission_date <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
}

if ($min_amount !== '') {
    $where[] = "a.amount_requested >= :min_amount";
    $params[':min_amount'] = $min_amount;
}

if ($max_amount !== '') {
    $where[] = "a.amount_requested <= :max_amount";
    $params[':max_amount'] = $max_amount;
}

if (!empty($search)) {
    $where[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR a.purpose LIKE :search OR a.aics_id = :search_id)";
    $params[':search'] = "%$search%";
    
    // Attempt to extract numeric ID in case they search 'AICS-000012'
    $clean_search = preg_replace('/[^0-9]/', '', $search);
    $search_id = !empty($clean_search) ? intval($clean_search) : 0;
    $params[':search_id'] = $search_id;
}

if (!empty($where)) {
    $base_query .= " WHERE " . implode(" AND ", $where);
    $count_query .= " WHERE " . implode(" AND ", $where);
}

// CSV Export Hook
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    if (ob_get_level()) {
        ob_clean();
    }
    
    $export_query = $base_query . " ORDER BY a.submission_date DESC";
    $export_stmt = $db->prepare($export_query);
    foreach ($params as $key => $value) {
        $export_stmt->bindValue($key, $value);
    }
    $export_stmt->execute();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=aics_requests_report_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    fputcsv($output, ['AICS ID', 'Applicant Name', 'Email', 'Contact Number', 'Type of Assistance', 'Amount Requested', 'Amount Approved', 'Status', 'Date Submitted', 'Purpose']);
    
    while ($row = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            'AICS-' . str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT),
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['contact_number'],
            ucfirst(str_replace('_', ' ', $row['request_type'])),
            $row['amount_requested'],
            $row['amount_approved'],
            ucfirst($row['status']),
            $row['submission_date'],
            $row['purpose']
        ]);
    }
    
    fclose($output);
    exit();
}

// Get total count for pagination
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get records with pagination
$query = $base_query . " ORDER BY a.submission_date DESC LIMIT :offset, :per_page";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();

// Helper to render table rows for both standard and AJAX requests
function renderAicsRows($statement, $db_instance = null) {
    if (!$db_instance) {
        global $db;
        $db_instance = $db;
    }
    if ($statement && $statement->rowCount() > 0) {
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $user_ids = array_column($rows, 'user_id');
        $audit_map = ($db_instance && function_exists('batchCheckAicsFrequency')) ? batchCheckAicsFrequency($db_instance, $user_ids) : [];

        foreach ($rows as $index => $row) {
            $is_latest = ($index === 0 && (!isset($_GET['page']) || intval($_GET['page']) <= 1));
            $uid = (int)($row['user_id'] ?? 0);
            $audit_info = $audit_map[$uid] ?? null;
            $row['audit_flag'] = $audit_info;

            $app_initials = strtoupper(substr($row['first_name'] ?? '', 0, 1) . substr($row['last_name'] ?? '', 0, 1));
            $app_pic = !empty($row['profile_picture']) && file_exists(__DIR__ . '/../' . $row['profile_picture']) 
                ? '../' . $row['profile_picture'] 
                : '';
            $status = $row['status'] ?? 'submitted';
            $status_icon = $status == 'submitted' ? 'clock' : 
                          ($status == 'reviewing' ? 'search' : 
                          ($status == 'approved' ? 'check-circle' : 
                          ($status == 'released' ? 'gift' : 'times-circle')));
            $json_row = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
            ?>
            <tr class="<?php echo $is_latest ? 'row-latest-entry' : ''; ?>">
                <td>
                    <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                        <strong>AICS-<?php echo str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                        <?php if ($is_latest): ?>
                            <span class="badge-latest-tag"><i class="fas fa-sparkles"></i> LATEST</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="applicant-cell">
                        <div class="applicant-avatar">
                            <?php if(!empty($app_pic)): ?>
                                <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Profile">
                            <?php else: ?>
                                <span class="initials"><?php echo $app_initials; ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="applicant-name" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <span><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></span>
                                <?php if (!empty($audit_info['badge_html'])): ?>
                                    <?php echo $audit_info['badge_html']; ?>
                                <?php endif; ?>
                            </div>
                            <div class="applicant-contact"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo ucfirst(str_replace('_', ' ', $row['request_type'] ?? '')); ?></td>
                <td>₱<?php echo number_format($row['amount_requested'] ?? 0, 2); ?></td>
                <td><?php echo !empty($row['submission_date']) ? date('M d, Y', strtotime($row['submission_date'])) : '-'; ?></td>
                <td>
                    <span class="status-badge <?php echo htmlspecialchars($status); ?>">
                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                        <?php echo ucfirst($status); ?>
                    </span>
                </td>
                <td style="text-align: center; white-space: nowrap; width: 1%; position: relative;">
                    <div class="row-action-dropdown">
                        <button type="button" class="btn-dot-action" onclick="toggleRowAction(event, this)" title="Actions" aria-label="Row Actions">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="row-action-menu">
                            <a href="view-aics.php?id=<?php echo $row['aics_id']; ?>" class="action-menu-item" title="View details">
                                <i class="fas fa-eye" style="color: #2563EB;"></i> View Details
                            </a>
                            <button type="button" onclick='showUpdateModal(<?php echo $json_row; ?>)' class="action-menu-item" title="Update status and details">
                                <i class="fas fa-edit" style="color: var(--ref-maroon);"></i> Update Status
                            </button>
                            <a href="../print-claim-stub.php?ref=AICS-<?php echo str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT); ?>" target="_blank" class="action-menu-item" title="Print claim stub">
                                <i class="fas fa-print" style="color: #059669;"></i> Print Stub
                            </a>
                            <div class="action-menu-divider"></div>
                            <button type="button" onclick='confirmDeleteAics(<?php echo $row['aics_id']; ?>, "AICS-<?php echo str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT); ?>", "<?php echo htmlspecialchars(addslashes(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?>")' class="action-menu-item danger-item" title="Delete application">
                                <i class="fas fa-trash-alt"></i> Delete Record
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr>
            <td colspan="7">
                <div class="no-data" style="text-align: center; padding: 40px 20px; color: var(--ref-muted);">
                    <i class="fas fa-inbox fa-3x" style="opacity: 0.35; margin-bottom: 12px; display: block;"></i>
                    <p style="font-weight: 600; font-size: 0.95rem; margin: 0;">No matching AICS requests found</p>
                    <p style="font-size: 0.8rem; margin: 4px 0 0; opacity: 0.7;">Try adjusting your filters or search keywords</p>
                </div>
            </td>
        </tr>
        <?php
    }
}

// Helper to render pagination controls for both standard and AJAX requests
function renderAicsPagination($page, $total_pages, $total_records, $per_page, $offset) {
    if ($total_pages <= 1) return;
    $page_params = $_GET;
    unset($page_params['ajax']);
    ?>
    <div class="pagination">
        <span class="page-info">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total)</span>
        
        <div class="pagination-links">
            <?php if($page > 1): 
                $page_params['page'] = $page - 1;
            ?>
                <a href="?<?php echo http_build_query($page_params); ?>" data-page="<?php echo $page - 1; ?>" title="Previous Page">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled" title="No Previous Page"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                $page_params['page'] = 1;
                echo '<a href="?' . http_build_query($page_params) . '" data-page="1">1</a>';
                if ($start_page > 2) {
                    echo '<span class="ellipsis">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i == $page ? 'active' : '';
                $page_params['page'] = $i;
                echo '<a href="?' . http_build_query($page_params) . '" class="' . $active . '" data-page="' . $i . '">' . $i . '</a>';
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="ellipsis">...</span>';
                }
                $page_params['page'] = $total_pages;
                echo '<a href="?' . http_build_query($page_params) . '" data-page="' . $total_pages . '">' . $total_pages . '</a>';
            }
            ?>
            
            <?php if($page < $total_pages): 
                $page_params['page'] = $page + 1;
            ?>
                <a href="?<?php echo http_build_query($page_params); ?>" data-page="<?php echo $page + 1; ?>" title="Next Page">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled" title="No Next Page"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Handle AJAX live auto-filtering request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    ob_start();
    renderAicsRows($stmt);
    $tbody_html = ob_get_clean();
    
    ob_start();
    renderAicsPagination($page, $total_pages, $total_records, $per_page, $offset);
    $pagination_html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'tbody' => $tbody_html,
        'pagination' => $pagination_html,
        'total_records' => (int)$total_records,
        'page' => (int)$page,
        'total_pages' => (int)$total_pages
    ]);
    exit();
}

// Get all records for statistics (without pagination)
$all_stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
    SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
    SUM(amount_approved) as total_approved,
    SUM(CASE WHEN request_type = 'medical' THEN 1 ELSE 0 END) as type_medical,
    SUM(CASE WHEN request_type = 'funeral' THEN 1 ELSE 0 END) as type_funeral,
    SUM(CASE WHEN request_type = 'educational' THEN 1 ELSE 0 END) as type_educational,
    SUM(CASE WHEN request_type = 'cash_relief' THEN 1 ELSE 0 END) as type_cash_relief,
    SUM(CASE WHEN request_type = 'food' THEN 1 ELSE 0 END) as type_food,
    SUM(CASE WHEN request_type = 'transportation' THEN 1 ELSE 0 END) as type_transportation,
    SUM(CASE WHEN request_type = 'material' THEN 1 ELSE 0 END) as type_material
    FROM aics_requests";
$stats = $db->query($all_stats_query)->fetch(PDO::FETCH_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    csrf_verify();
    $aics_id = sanitize($_POST['aics_id']);
    
    // Check if assistance has already been released
    $chk_st = $db->prepare("SELECT status FROM aics_requests WHERE aics_id = :id");
    $chk_st->execute([':id' => $aics_id]);
    $curr_st = $chk_st->fetchColumn();
    if ($curr_st && strtolower($curr_st) === 'released') {
        $_SESSION['error'] = "Assistance has already been released and disbursed. Completed transactions cannot be modified.";
        redirect('manage-aics.php');
    }
    $new_status = sanitize($_POST['new_status']);
    $amount_approved = sanitize($_POST['amount_approved'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $audit_override_reason = sanitize($_POST['audit_override_reason'] ?? '');
    
    $db->beginTransaction();
    
    try {
        $update_query = "UPDATE aics_requests SET 
                          status = :status,
                          amount_approved = :amount";
        
        if ($new_status == 'reviewing') {
            $update_query .= ", reviewed_by = :user_id, review_date = NOW()";
        } elseif ($new_status == 'approved') {
            $update_query .= ", approved_by = :user_id, approval_date = NOW()";
            if (!empty($audit_override_reason)) {
                $update_query .= ", audit_override_reason = :audit_override, is_frequency_exception = 1";
            }
        } elseif ($new_status == 'released') {
            $update_query .= ", release_date = NOW()";
        } elseif ($new_status == 'rejected') {
            $update_query .= ", rejection_reason = :remarks";
        }
        
        $update_query .= " WHERE aics_id = :aics_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':amount', $amount_approved);
        
        if ($new_status == 'reviewing' || $new_status == 'approved') {
            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        }

        if ($new_status == 'approved' && !empty($audit_override_reason)) {
            $update_stmt->bindParam(':audit_override', $audit_override_reason);
        }
        
        if ($new_status == 'rejected') {
            $update_stmt->bindParam(':remarks', $remarks);
        }
        
        $update_stmt->bindParam(':aics_id', $aics_id);
        $update_stmt->execute();
        
        // Get user details for notification
        $user_query = "SELECT u.email, u.first_name, u.last_name, u.contact_number, a.* 
                       FROM aics_requests a
                       JOIN users u ON a.user_id = u.user_id
                       WHERE a.aics_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $aics_id);
        $user_stmt->execute();
        $aics_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $type_label = strtoupper(str_replace('_', ' ', $aics_details['request_type']));
        $notif_msg = "Your AICS $type_label request status has been updated to: " . strtoupper($new_status);
        if ($new_status === 'reviewing') {
            $notif_msg = "Your AICS $type_label application is now UNDER REVIEW. Please visit the CSWDO Office at Window 1, 2, or 3 for your interview and assessment. Make sure to bring your original supporting documents.";
        } elseif ($new_status === 'approved') {
            $notif_msg = "Your AICS $type_label application has been APPROVED and forwarded to the City Budget Office for voucher processing. Please wait for a text or call notification.";
        }

        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'AICS Status Updated', :msg, 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $aics_details['user_id']);
        $notif_stmt->bindParam(':msg', $notif_msg);
        $notif_stmt->execute();
        
        $user_name = $aics_details['first_name'] . ' ' . $aics_details['last_name'];
        if ($new_status == 'approved') {
            $aics_details['amount_approved'] = $amount_approved;
        }
        if ($new_status == 'rejected') {
            $aics_details['rejection_reason'] = $remarks;
        }

        // Send email
        try {
            $mailer = new Mailer();
            if (!empty($aics_details['email'])) {
                $email_result = $mailer->sendAICSUpdate(
                    $aics_details['email'],
                    $user_name,
                    $aics_details
                );
                $email_status = $email_result['success'] ? "Email notification sent." : "Email failed: " . $email_result['message'];
                error_log($email_status);
            }
        } catch (Exception $e) {
            $email_status = "Email error: " . $e->getMessage();
            error_log($email_status);
        }

        // Send SMS notification via TextBee
        $phone = $aics_details['contact_number'] ?? ($aics_details['phone'] ?? '');
        if (!empty($phone)) {
            try {
                require_once __DIR__ . '/../includes/SMSGateway.php';
                $smsGateway = new SMSGateway();
                $smsGateway->sendAICSUpdate($phone, $user_name, $aics_details);
            } catch (Throwable $e) {
                error_log("AICS modal status update SMS failed: " . $e->getMessage());
            }
        }

        // Record entry in audit_logs for COA compliance
        $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'update_aics_status', 'aics_requests', :details)";
        $log_stmt = $db->prepare($log_query);
        $log_details = "Updated AICS ID $aics_id status to $new_status (Amount: ₱" . number_format($amount_approved, 2) . ")";
        if (!empty($audit_override_reason)) {
            $log_details .= " [90-Day Policy Override Justification: " . $audit_override_reason . "]";
        }
        $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $log_stmt->bindParam(':details', $log_details);
        $log_stmt->execute();
        
        $db->commit();
        $success = "Status updated successfully! " . ($email_status ?? '');
        
        // Redirect to refresh with current page
        $redirect_url = "manage-aics.php?status=" . $status_filter . "&page=" . $page;
        if (!empty($search)) {
            $redirect_url .= "&search=" . urlencode($search);
        }
        header("Location: " . $redirect_url);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Handle request deletion via fallback POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_aics'])) {
    csrf_verify();
    $del_id = intval($_POST['aics_id'] ?? 0);
    if ($del_id > 0) {
        // Prevent deleting released AICS records
        $chk_st = $db->prepare("SELECT status FROM aics_requests WHERE aics_id = :id");
        $chk_st->execute([':id' => $del_id]);
        $curr_st = $chk_st->fetchColumn();
        if ($curr_st && strtolower($curr_st) === 'released') {
            $_SESSION['error'] = "Disbursed/released assistance records cannot be deleted to preserve financial audit history.";
            redirect('manage-aics.php');
        }
        try {
            $db->beginTransaction();
            // Delete physical documents
            $doc_stmt = $db->prepare("SELECT document_path FROM aics_documents WHERE aics_id = :id");
            $doc_stmt->execute([':id' => $del_id]);
            foreach ($doc_stmt->fetchAll(PDO::FETCH_COLUMN) as $dp) {
                if (!empty($dp)) {
                    $fp = __DIR__ . '/../' . ltrim($dp, '/\\');
                    if (file_exists($fp) && is_file($fp)) @unlink($fp);
                }
            }
            $comm_stmt = $db->prepare("SELECT attachment_path FROM aics_comments WHERE aics_id = :id");
            $comm_stmt->execute([':id' => $del_id]);
            foreach ($comm_stmt->fetchAll(PDO::FETCH_COLUMN) as $ap) {
                if (!empty($ap)) {
                    $fp = __DIR__ . '/../' . ltrim($ap, '/\\');
                    if (file_exists($fp) && is_file($fp)) @unlink($fp);
                }
            }
            $db->prepare("DELETE FROM aics_comments WHERE aics_id = :id")->execute([':id' => $del_id]);
            $db->prepare("DELETE FROM aics_requests WHERE aics_id = :id")->execute([':id' => $del_id]);

            $audit_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'delete_aics_request', 'aics_requests', :details)");
            $audit_stmt->execute([':user_id' => $_SESSION['user_id'], ':details' => "Deleted AICS request ID #$del_id"]);

            $db->commit();
            $success = "Application AICS-" . str_pad($del_id, 6, '0', STR_PAD_LEFT) . " has been permanently deleted.";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Failed to delete request: " . $e->getMessage();
        }
    }
}

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
?>
<?php
$page_title = 'Manage AICS - Staff Portal';
$active_nav = 'aics';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
.applicant-cell { display: flex; align-items: center; gap: 10px; }
.applicant-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--ref-canvas); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-weight: 700; font-size: 0.7rem; color: var(--ref-maroon); border: 1px solid var(--ref-border); }
.applicant-avatar img { width: 100%; height: 100%; object-fit: cover; }
.applicant-name { font-weight: 700; color: var(--ref-text); }
.action-buttons { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; flex-wrap: nowrap !important; white-space: nowrap !important; }
.btn-small { padding: 6px 11px; font-size: 0.74rem; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s ease; font-weight: 600; white-space: nowrap !important; flex-shrink: 0 !important; line-height: 1.2; }
.btn-view { background: #2563EB; color: #FFFFFF; }
.btn-view:hover { background: #1D4ED8; color: #FFFFFF; transform: translateY(-1px); }
.btn-update { background: var(--ref-maroon, #7F0303); color: #FFFFFF; }
.btn-update:hover { background: var(--ref-maroon-light, #9E1B1B); color: #FFFFFF; transform: translateY(-1px); }
.btn-edit { background: var(--ref-maroon, #7F0303); color: #FFFFFF; }
.btn-edit:hover { background: var(--ref-maroon-light, #9E1B1B); color: #FFFFFF; transform: translateY(-1px); }
.btn-delete { background: #DC2626; color: #FFFFFF; }
.btn-delete:hover { background: #B91C1C; color: #FFFFFF; transform: translateY(-1px); }
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 100000; display: flex; flex-direction: column; gap: 10px; }
.toast { background: #1E293B; color: #FFFFFF; padding: 12px 20px; border-radius: 12px; font-size: 0.84rem; font-weight: 600; box-shadow: 0 10px 25px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 10px; animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.toast.toast-success { background: #1B5E20 !important; border-left: 4px solid #4CAF50; }
.toast.toast-error { background: #B71C1C !important; border-left: 4px solid #EF5350; }
@keyframes toastIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); display: none; justify-content: center; align-items: center; z-index: 2000; backdrop-filter: blur(4px); }
.modal.active { display: flex; }
.modal-content { background: var(--ref-card); border-radius: 24px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: var(--ref-shadow-lg); border: 1px solid var(--ref-border); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid var(--ref-border); display: flex; justify-content: space-between; align-items: center; background: var(--ref-card-subtle); }
.modal-header h2 { font-size: 1.1rem; font-weight: 800; color: var(--ref-text); display: flex; align-items: center; gap: 10px; margin: 0; }
.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--ref-muted); line-height: 1; }
.modal-body { padding: 22px 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--ref-border); display: flex; justify-content: flex-end; gap: 12px; background: var(--ref-card-subtle); }
.filter-card { background: var(--ref-card); border-radius: 20px; padding: 16px 20px; margin-bottom: 18px; border: 1px solid var(--ref-border); }
.filter-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 5px; }
.filter-group label { font-size: 0.68rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid var(--ref-border); border-radius: 10px; font-size: 0.82rem; background: var(--ref-canvas); color: var(--ref-text); font-family: inherit; }
.filter-actions { display: flex; gap: 8px; align-items: center; }
.amount-badge { font-weight: 800; color: var(--ref-maroon); font-size: 0.85rem; }
.applicant-info { padding: 10px 14px; background: var(--ref-canvas); border-radius: 12px; margin-bottom: 16px; font-size: 0.85rem; color: var(--ref-text); }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 700; color: var(--ref-text); font-size: 0.82rem; }
.form-group select, .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ref-border); border-radius: 12px; font-size: 0.85rem; background: var(--ref-canvas); color: var(--ref-text); font-family: inherit; }
.table-container { position: relative; border-radius: var(--radius-card, 16px); overflow: hidden; }
.table-loading-bar { position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--ref-maroon, #7F0303) 0%, #D4AF37 50%, var(--ref-maroon, #7F0303) 100%); background-size: 200% 100%; animation: tableLoadingAnim 1.2s infinite linear; display: none; z-index: 15; }
@keyframes tableLoadingAnim { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.table-container.is-loading tbody { opacity: 0.38; pointer-events: none; transition: opacity 0.2s ease; }
.table-container.is-loading .table-loading-bar { display: block; }
/* Compact Stats Cards in Manage AICS */
.stats-grid {
    display: grid !important;
    grid-template-columns: repeat(6, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
}
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr) !important; }
}
@media (max-width: 640px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
.stat-card {
    padding: 10px 12px !important;
    border-radius: 14px !important;
    gap: 10px !important;
    min-height: unset !important;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--ref-shadow-md); }
.stat-card.active-status { border: 2px solid var(--ref-maroon, #7F0303) !important; box-shadow: 0 4px 14px rgba(127, 3, 3, 0.18) !important; transform: translateY(-2px); }
.stat-card .stat-icon {
    width: 34px !important;
    height: 34px !important;
    border-radius: 10px !important;
    font-size: 0.95rem !important;
    flex-shrink: 0 !important;
}
.stat-card .stat-info {
    min-width: 0 !important;
    overflow: hidden !important;
}
.stat-card .stat-info h3 {
    font-size: 1.15rem !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
    margin: 0 0 2px 0 !important;
    white-space: nowrap !important;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-card .stat-info p {
    font-size: 0.65rem !important;
    font-weight: 700 !important;
    color: var(--ref-muted) !important;
    letter-spacing: 0.03em !important;
    text-transform: uppercase !important;
    margin: 0 !important;
    white-space: nowrap !important;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-card:last-child .stat-info h3 {
    font-size: 1.02rem !important;
    letter-spacing: -0.02em;
}
.type-pill { transition: all 0.2s ease; cursor: pointer; user-select: none; }
.type-pill:hover { transform: translateY(-2px); filter: brightness(1.05); }
.type-pill.active-type { font-weight: 700; transform: translateY(-1px); }
#searchInput:focus { outline: none; border-color: var(--ref-maroon, #7F0303); box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12); }
#clearSearchBtn:hover { color: var(--ref-maroon, #7F0303) !important; }
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
            $banner_title = "AICS Requests Management";
            $banner_subtitle = "Review, assess, and manage Assistance to Individuals in Crisis Situation intake cases";
            $banner_icon = "fa-hand-holding-heart";
            $banner_actions = [
                ["label" => "Table View", "url" => "manage-aics.php", "icon" => "fa-table", "type" => "secondary"],
                ["label" => "Kanban Board", "url" => "kanban-aics.php", "icon" => "fa-columns", "type" => "primary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

        
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card <?php echo $status_filter == 'all' ? 'active-status' : ''; ?>" data-status="all" title="Filter by All Requests">
                <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card <?php echo $status_filter == 'submitted' ? 'active-status' : ''; ?>" data-status="submitted" title="Filter by Submitted Requests">
                <div class="stat-icon submitted"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['submitted']; ?></h3>
                    <p>Submitted</p>
                </div>
            </div>
            <div class="stat-card <?php echo $status_filter == 'reviewing' ? 'active-status' : ''; ?>" data-status="reviewing" title="Filter by Under Review Requests">
                <div class="stat-icon reviewing"><i class="fas fa-search"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['reviewing']; ?></h3>
                    <p>Reviewing</p>
                </div>
            </div>
            <div class="stat-card <?php echo $status_filter == 'approved' ? 'active-status' : ''; ?>" data-status="approved" title="Filter by Approved Requests">
                <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card <?php echo $status_filter == 'released' ? 'active-status' : ''; ?>" data-status="released" title="Filter by Released Requests">
                <div class="stat-icon approved" style="background: rgba(76,175,80,0.15); color: #2E7D32;"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['released']; ?></h3>
                    <p>Released</p>
                </div>
            </div>
            <div class="stat-card" style="cursor: default;">
                <div class="stat-icon disbursed"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3>₱<?php echo number_format($stats['total_approved'] ?? 0, 2); ?></h3>
                    <p>Total Disbursed</p>
                </div>
            </div>
        </div>
        
        <!-- Category Breakdown -->
        <div class="category-breakdown" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; background: var(--bg-card); padding: 15px; border-radius: var(--radius-card); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); width: 100%; margin-bottom: 5px;"><i class="fas fa-chart-pie"></i> Assistance Type Summary (Click to Filter)</div>
            <div class="type-pill <?php echo $type_filter == 'all' ? 'active-type' : ''; ?>" data-type="all" data-color="var(--maroon-light)" style="display: flex; align-items: center; gap: 8px; background: var(--badge-bg); padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; border: 1px solid var(--border-color); <?php echo $type_filter == 'all' ? 'border-color: var(--maroon-light); background: rgba(127,3,3,0.08);' : ''; ?>">
                <i class="fas fa-list-ul" style="color: var(--maroon-light);"></i>
                <span style="color: var(--text-primary);">All Categories</span>
                <span style="background: var(--maroon-light); color: white; padding: 1px 7px; border-radius: 50px; font-size: 0.65rem; font-weight: 700;"><?php echo $stats['total']; ?></span>
            </div>
            <?php 
            $types_meta = [
                'medical'        => ['label' => 'Medical', 'icon' => 'fa-file-medical', 'color' => '#2196F3'],
                'funeral'        => ['label' => 'Burial/Funeral', 'icon' => 'fa-cross', 'color' => '#7F0303'],
                'food'           => ['label' => 'Food', 'icon' => 'fa-utensils', 'color' => '#4CAF50'],
                'transportation' => ['label' => 'Transportation', 'icon' => 'fa-bus', 'color' => '#00BCD4'],
                'cash_relief'    => ['label' => 'Cash Relief', 'icon' => 'fa-hand-holding-usd', 'color' => '#9C27B0']
            ];
            foreach ($types_meta as $key => $meta):
                $count_val = $stats['type_' . $key] ?? 0;
            ?>
            <div class="type-pill <?php echo $type_filter == $key ? 'active-type' : ''; ?>" data-type="<?php echo $key; ?>" data-color="<?php echo $meta['color']; ?>" style="display: flex; align-items: center; gap: 8px; background: var(--badge-bg); padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; border: 1px solid var(--border-color); <?php echo $type_filter == $key ? 'border-color: ' . $meta['color'] . '; background: ' . $meta['color'] . '18;' : ''; ?>">
                <i class="fas <?php echo $meta['icon']; ?>" style="color: <?php echo $meta['color']; ?>;"></i>
                <span style="color: var(--text-primary);"><?php echo $meta['label']; ?></span>
                <span style="background: <?php echo $meta['color']; ?>; color: white; padding: 1px 7px; border-radius: 50px; font-size: 0.65rem; font-weight: 700;"><?php echo $count_val; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label for="statusFilter"><i class="fas fa-filter"></i> Status</label>
                    <select name="status" id="statusFilter">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="reviewing" <?php echo $status_filter == 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved</option>
                        <option value="released" <?php echo $status_filter == 'released' ? 'selected' : ''; ?>>Released</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="typeFilter"><i class="fas fa-tag"></i> Type</label>
                    <select name="type" id="typeFilter">
                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="medical" <?php echo $type_filter == 'medical' ? 'selected' : ''; ?>>Medical</option>
                        <option value="funeral" <?php echo $type_filter == 'funeral' ? 'selected' : ''; ?>>Funeral</option>
                        <option value="food" <?php echo $type_filter == 'food' ? 'selected' : ''; ?>>Food</option>
                        <option value="transportation" <?php echo $type_filter == 'transportation' ? 'selected' : ''; ?>>Transportation</option>
                        <option value="cash_relief" <?php echo $type_filter == 'cash_relief' ? 'selected' : ''; ?>>Cash Relief</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="startDateFilter"><i class="fas fa-calendar-alt"></i> Start Date</label>
                    <input type="date" name="start_date" id="startDateFilter" style="padding: 8px 12px; border-radius: var(--radius-element); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label for="endDateFilter"><i class="fas fa-calendar-alt"></i> End Date</label>
                    <input type="date" name="end_date" id="endDateFilter" style="padding: 8px 12px; border-radius: var(--radius-element); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group">
                    <label for="minAmountFilter"><i class="fas fa-dollar-sign"></i> Min (₱)</label>
                    <input type="number" name="min_amount" id="minAmountFilter" placeholder="Min" style="width: 100px; padding: 8px 12px; border-radius: var(--radius-element); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);" value="<?php echo htmlspecialchars($min_amount); ?>">
                </div>
                <div class="filter-group">
                    <label for="maxAmountFilter"><i class="fas fa-dollar-sign"></i> Max (₱)</label>
                    <input type="number" name="max_amount" id="maxAmountFilter" placeholder="Max" style="width: 100px; padding: 8px 12px; border-radius: var(--radius-element); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);" value="<?php echo htmlspecialchars($max_amount); ?>">
                </div>
                <div class="filter-group">
                    <label for="searchInput">
                        <i class="fas fa-search"></i> Search
                        <span id="searchSpinner" style="display: none; font-size: 0.72rem; color: var(--ref-maroon, #7F0303); margin-left: 5px;">
                            <i class="fas fa-circle-notch fa-spin"></i> Filtering...
                        </span>
                    </label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="text" name="search" id="searchInput" placeholder="Name, ID or purpose..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding-right: 28px;">
                        <button type="button" id="clearSearchBtn" title="Clear search text" style="position: absolute; right: 8px; background: none; border: none; color: var(--ref-muted); cursor: pointer; display: <?php echo !empty($search) ? 'block' : 'none'; ?>; padding: 0; line-height: 1;">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-actions" style="margin-top: 15px; width: 100%; display: flex; gap: 10px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                    <button type="submit" id="applyFiltersBtn" class="btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; font-weight: 700; border-radius: 50px;"><i class="fas fa-search"></i> Apply Filters</button>
                    <button type="submit" name="export" value="csv" class="btn-export-csv" style="background: #059669; color: #FFFFFF; border: none; border-radius: 50px; padding: 9px 20px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <a href="manage-aics.php" id="clearFiltersBtn" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 50px; font-weight: 600; <?php echo ($status_filter != 'all' || $type_filter != 'all' || !empty($search) || !empty($start_date) || !empty($end_date) || $min_amount !== '' || $max_amount !== '') ? '' : 'display: none;'; ?>"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container" id="tableContainer">
            <div class="table-loading-bar" id="tableLoadingBar"></div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th style="text-align: center; white-space: nowrap; width: 1%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="aicsTableBody">
                    <?php renderAicsRows($stmt); ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div id="paginationWrapper">
                <?php renderAicsPagination($page, $total_pages, $total_records, $per_page, $offset); ?>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Request</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateAicsModal(event)">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <input type="hidden" name="aics_id" id="modal_aics_id">
                <div class="applicant-info">
                    <strong><i class="fas fa-user"></i> Applicant:</strong>
                    <span id="modal_applicant"></span>
                </div>
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" id="modal_status" required onchange="toggleFields()">
                        <option value="reviewing">📋 Under Review</option>
                        <option value="approved">✅ Approve</option>
                        <option value="rejected">❌ Disapprove</option>
                        <option value="released">💰 Release Payment</option>
                    </select>
                </div>
                <div class="form-group" id="amount_field" style="display: none;">
                    <label>Approved Amount (₱)</label>
                    <input type="number" name="amount_approved" id="amount_approved" min="0" step="100" placeholder="Enter approved amount">
                </div>
                <div class="form-group" id="remarks_field" style="display: none;">
                    <label>Disapproval Reason</label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Please provide a reason for disapproval..."></textarea>
                </div>

                <!-- 90-Day Policy Audit Override Field (Shown only when approving a flagged request) -->
                <div class="form-group" id="manage_audit_override_field" style="display: none;">
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1.5px solid #EF4444; border-radius: 8px; padding: 12px; margin-top: 10px;">
                        <div style="font-weight: 700; color: #DC2626; font-size: 0.8rem; margin-bottom: 4px;">
                            <i class="fas fa-exclamation-triangle"></i> 90-Day Policy Audit Override Required
                        </div>
                        <p id="manage_audit_override_desc" style="font-size: 0.75rem; color: var(--ref-text); margin: 0 0 8px 0; line-height: 1.4;">
                            Applicant received prior assistance within 90 days. To approve per COA & DSWD guidelines, please record the Social Worker justification:
                        </p>
                        <label style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; color: #DC2626; display: block; margin-bottom: 4px;">Social Worker Case Justification *</label>
                        <textarea name="audit_override_reason" id="manage_audit_override_reason" rows="2" placeholder="e.g. Life-saving chemotherapy / dialysis / house fire calamity exception assessed by Social Worker..." style="width: 100%; font-size: 0.78rem; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color);"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button type="submit" name="update_status" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header" style="background: rgba(220, 38, 38, 0.08); border-bottom: 1px solid rgba(220, 38, 38, 0.2);">
            <h2 style="color: #DC2626; font-size: 1.05rem;">
                <i class="fas fa-trash-alt"></i> Delete AICS Application
            </h2>
            <button type="button" class="close-btn" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 22px 24px;">
            <p style="font-size: 0.92rem; line-height: 1.5; color: var(--ref-text); margin: 0 0 14px 0;">
                Are you sure you want to permanently delete application <strong id="delete_tracking_code" style="color: #DC2626;"></strong> for <strong id="delete_applicant_name"></strong>?
            </p>
            <div style="background: rgba(220, 38, 38, 0.06); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: 12px; padding: 12px 14px; font-size: 0.78rem; color: var(--ref-muted); display: flex; gap: 10px; align-items: flex-start;">
                <i class="fas fa-exclamation-triangle" style="color: #DC2626; margin-top: 2px;"></i>
                <span>This action is irreversible. All associated uploaded documents and assessment logs will be permanently deleted from the system and recorded in the audit log.</span>
            </div>
        </div>
        <div class="modal-footer" style="gap: 10px;">
            <button type="button" onclick="closeDeleteModal()" class="btn-secondary" id="btnCancelDelete">Cancel</button>
            <button type="button" onclick="executeDeleteAics()" class="btn-primary" id="btnConfirmDeleteTable" style="background: #DC2626; border-color: #B91C1C;">
                <i class="fas fa-trash-alt"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
// ========== MODAL FUNCTIONS ==========
let currentModalAuditFlag = null;

function showUpdateModal(request) {
    document.getElementById('modal_aics_id').value = request.aics_id;
    document.getElementById('modal_applicant').textContent = (request.first_name || '') + ' ' + (request.last_name || '');
    currentModalAuditFlag = request.audit_flag || null;
    
    const desc = document.getElementById('manage_audit_override_desc');
    if (desc && currentModalAuditFlag && currentModalAuditFlag.is_flagged) {
        desc.innerHTML = 'Applicant received prior assistance <strong>' + currentModalAuditFlag.days_ago + ' days ago</strong> (' + currentModalAuditFlag.days_remaining + ' days remaining in cooling-off period). Record Social Worker justification:';
    }
    
    document.getElementById('updateModal').classList.add('active');
    toggleFields();
}

function closeModal() {
    document.getElementById('updateModal').classList.remove('active');
    document.getElementById('amount_field').style.display = 'none';
    document.getElementById('remarks_field').style.display = 'none';
    
    const auditField = document.getElementById('manage_audit_override_field');
    const auditReason = document.getElementById('manage_audit_override_reason');
    if (auditField) auditField.style.display = 'none';
    if (auditReason) {
        auditReason.required = false;
        auditReason.value = '';
    }
    currentModalAuditFlag = null;

    document.getElementById('amount_approved').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('modal_status').value = 'reviewing';
}

function toggleFields() {
    const status = document.getElementById('modal_status').value;
    document.getElementById('amount_field').style.display = status === 'approved' ? 'block' : 'none';
    document.getElementById('remarks_field').style.display = status === 'rejected' ? 'block' : 'none';
    
    const auditField = document.getElementById('manage_audit_override_field');
    const auditReason = document.getElementById('manage_audit_override_reason');
    if (auditField) {
        if (status === 'approved' && currentModalAuditFlag && currentModalAuditFlag.is_flagged) {
            auditField.style.display = 'block';
            if (auditReason) auditReason.required = true;
        } else {
            auditField.style.display = 'none';
            if (auditReason) auditReason.required = false;
        }
    }
}

function validateAicsModal(e) {
    const status = document.getElementById('modal_status').value;
    if (status === 'released') {
        if (!confirm('Confirm releasing and disbursing this AICS assistance to the resident? Once marked as Released, this transaction is finalized.')) {
            e.preventDefault();
            return false;
        }
    } else if (status === 'rejected') {
        const remarks = document.getElementById('remarks').value.trim();
        if (!remarks) {
            alert('Please provide a reason for disapproving this AICS request.');
            document.getElementById('remarks').focus();
            e.preventDefault();
            return false;
        }
        if (!confirm('Are you sure you want to DISAPPROVE this AICS application?')) {
            e.preventDefault();
            return false;
        }
    } else if (status === 'approved') {
        const amt = document.getElementById('amount_approved').value;
        if (!amt || parseFloat(amt) <= 0) {
            alert('Please specify a valid approved amount (₱).');
            document.getElementById('amount_approved').focus();
            e.preventDefault();
            return false;
        }
        if (!confirm('Confirm approving this AICS application with amount ₱' + parseFloat(amt).toLocaleString() + '?')) {
            e.preventDefault();
            return false;
        }
    }
    return true;
}

function filterByStatus(status) {
    const statusSelect = document.getElementById('statusFilter');
    if (statusSelect) {
        statusSelect.value = status;
        if (typeof performAicsFilter === 'function') {
            performAicsFilter(1);
        } else {
            document.getElementById('filterForm').submit();
        }
    }
}

// ========== DELETE FUNCTIONS ==========
let pendingDeleteId = null;
let pendingDeleteTracking = '';
let isDeleting = false;

function confirmDeleteAics(id, tracking, applicant) {
    pendingDeleteId = id;
    pendingDeleteTracking = tracking;
    const trackingElem = document.getElementById('delete_tracking_code');
    const applicantElem = document.getElementById('delete_applicant_name');
    if (trackingElem) trackingElem.textContent = tracking;
    if (applicantElem) applicantElem.textContent = applicant;
    
    const btn = document.getElementById('btnConfirmDeleteTable');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
    }
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.add('active');
}

function closeDeleteModal() {
    if (isDeleting) return;
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.remove('active');
    pendingDeleteId = null;
    pendingDeleteTracking = '';
}

function executeDeleteAics() {
    if (!pendingDeleteId || isDeleting) return;
    isDeleting = true;
    const btn = document.getElementById('btnConfirmDeleteTable');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }

    const targetId = pendingDeleteId;
    const targetTracking = pendingDeleteTracking;

    fetch('delete-aics-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aics_id: targetId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            showManageToast(`Application <strong>${targetTracking}</strong> has been deleted successfully!`, false);
            if (typeof performAicsFilter === 'function') {
                performAicsFilter(1);
            } else {
                window.location.reload();
            }
        } else {
            showManageToast(`Failed to delete application: ${data.message || 'Unknown error'}`, true);
        }
    })
    .catch(err => {
        console.error("Delete Error:", err);
        showManageToast("Network error: Could not reach server to delete application.", true);
    })
    .finally(() => {
        isDeleting = false;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
        }
    });
}

function showManageToast(msg, isError = false) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${isError ? 'toast-error' : 'toast-success'}`;
    toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> <div>${msg}</div>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(15px)';
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        setTimeout(() => toast.remove(), 350);
    }, 4000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const updateModal = document.getElementById('updateModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == updateModal) {
        closeModal();
    }
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const updateModal = document.getElementById('updateModal');
        const deleteModal = document.getElementById('deleteModal');
        if (updateModal && updateModal.classList.contains('active')) closeModal();
        if (deleteModal && deleteModal.classList.contains('active')) closeDeleteModal();
    }
});

// Three-Dot Row Action Dropdown Toggle (Part 2, Item 2 - Smart Dropup Positioning)
function toggleRowAction(event, button) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    const dropdown = button.closest('.row-action-dropdown');
    const wasActive = dropdown.classList.contains('active') || dropdown.classList.contains('open');
    document.querySelectorAll('.row-action-dropdown.active, .row-action-dropdown.open').forEach(d => {
        d.classList.remove('active', 'open', 'dropup');
    });
    if (!wasActive) {
        const btnRect = button.getBoundingClientRect();
        const spaceBelow = window.innerHeight - btnRect.bottom;
        if (spaceBelow < 220) {
            dropdown.classList.add('dropup');
        }
        dropdown.classList.add('active');
        dropdown.classList.add('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.row-action-dropdown')) {
        document.querySelectorAll('.row-action-dropdown.active, .row-action-dropdown.open').forEach(d => {
            d.classList.remove('active', 'open', 'dropup');
        });
    }
});

// ========== LIVE AUTO-FILTERING & SEARCH ENGINE ==========
let performAicsFilter = null;

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (!filterForm) return;

    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const startDateFilter = document.getElementById('startDateFilter');
    const endDateFilter = document.getElementById('endDateFilter');
    const minAmountFilter = document.getElementById('minAmountFilter');
    const maxAmountFilter = document.getElementById('maxAmountFilter');
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const searchSpinner = document.getElementById('searchSpinner');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const tableContainer = document.getElementById('tableContainer');
    const tableBody = document.getElementById('aicsTableBody');
    const paginationWrapper = document.getElementById('paginationWrapper');

    let debounceTimer = null;
    let activeController = null;

    function syncActiveIndicators() {
        const currentStatus = statusFilter ? statusFilter.value : 'all';
        const currentType = typeFilter ? typeFilter.value : 'all';

        // Update Stat Cards active highlighting
        document.querySelectorAll('.stat-card[data-status]').forEach(card => {
            if (card.dataset.status === currentStatus) {
                card.classList.add('active-status');
            } else {
                card.classList.remove('active-status');
            }
        });

        // Update Type Pills active highlighting
        document.querySelectorAll('.type-pill[data-type]').forEach(pill => {
            const pillType = pill.dataset.type;
            const metaColor = pill.dataset.color || 'var(--ref-maroon, #7F0303)';
            if (pillType === currentType) {
                pill.classList.add('active-type');
                pill.style.borderColor = metaColor;
                pill.style.background = pillType === 'all' ? 'rgba(127,3,3,0.08)' : (metaColor + '18');
            } else {
                pill.classList.remove('active-type');
                pill.style.borderColor = 'var(--border-color, var(--ref-border))';
                pill.style.background = 'var(--badge-bg, var(--ref-canvas))';
            }
        });

        // Determine if any filter is currently applied
        const hasActiveFilter = (
            (statusFilter && statusFilter.value !== 'all') ||
            (typeFilter && typeFilter.value !== 'all') ||
            (startDateFilter && startDateFilter.value) ||
            (endDateFilter && endDateFilter.value) ||
            (minAmountFilter && minAmountFilter.value !== '') ||
            (maxAmountFilter && maxAmountFilter.value !== '') ||
            (searchInput && searchInput.value.trim() !== '')
        );

        if (clearFiltersBtn) {
            clearFiltersBtn.style.display = hasActiveFilter ? 'inline-flex' : 'none';
        }

        if (clearSearchBtn && searchInput) {
            clearSearchBtn.style.display = searchInput.value.trim() !== '' ? 'block' : 'none';
        }
    }

    performAicsFilter = function(page = 1) {
        syncActiveIndicators();

        const params = new URLSearchParams();
        if (statusFilter && statusFilter.value !== 'all') params.set('status', statusFilter.value);
        if (typeFilter && typeFilter.value !== 'all') params.set('type', typeFilter.value);
        if (startDateFilter && startDateFilter.value) params.set('start_date', startDateFilter.value);
        if (endDateFilter && endDateFilter.value) params.set('end_date', endDateFilter.value);
        if (minAmountFilter && minAmountFilter.value !== '') params.set('min_amount', minAmountFilter.value);
        if (maxAmountFilter && maxAmountFilter.value !== '') params.set('max_amount', maxAmountFilter.value);
        if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if (page > 1) params.set('page', page);

        // Update browser URL without full page refresh (keeps bookmarks and page refresh accurate)
        const cleanQuery = params.toString();
        const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '');
        window.history.replaceState({ page: page }, '', cleanUrl);

        // Prepare AJAX request
        params.set('ajax', '1');

        if (activeController) {
            activeController.abort();
        }
        activeController = new AbortController();

        if (tableContainer) tableContainer.classList.add('is-loading');
        if (searchSpinner) searchSpinner.style.display = 'inline-block';

        fetch('manage-aics.php?' + params.toString(), {
            signal: activeController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (tableBody) tableBody.innerHTML = data.tbody;
                if (paginationWrapper) paginationWrapper.innerHTML = data.pagination;
            }
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Filter fetch error:', err);
            }
        })
        .finally(() => {
            if (tableContainer) tableContainer.classList.remove('is-loading');
            if (searchSpinner) searchSpinner.style.display = 'none';
        });
    };

    // 1. Auto-filter on Status change (Immediate)
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            clearTimeout(debounceTimer);
            performAicsFilter(1);
        });
    }

    // 2. Auto-filter on Type change (Immediate)
    if (typeFilter) {
        typeFilter.addEventListener('change', function() {
            clearTimeout(debounceTimer);
            performAicsFilter(1);
        });
    }

    // 3. Auto-filter on Date changes (Immediate)
    if (startDateFilter) {
        startDateFilter.addEventListener('change', function() {
            clearTimeout(debounceTimer);
            performAicsFilter(1);
        });
    }
    if (endDateFilter) {
        endDateFilter.addEventListener('change', function() {
            clearTimeout(debounceTimer);
            performAicsFilter(1);
        });
    }

    // 4. Auto-search on Search input (Smooth 300ms Debounce)
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (clearSearchBtn) {
                clearSearchBtn.style.display = this.value.trim() !== '' ? 'block' : 'none';
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                performAicsFilter(1);
            }, 300);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                performAicsFilter(1);
            }
        });
    }

    // 5. Quick-clear search input button
    if (clearSearchBtn && searchInput) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';
            clearTimeout(debounceTimer);
            performAicsFilter(1);
            searchInput.focus();
        });
    }

    // 6. Auto-filter on Amount inputs (400ms Debounce)
    if (minAmountFilter) {
        minAmountFilter.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => performAicsFilter(1), 400);
        });
    }
    if (maxAmountFilter) {
        maxAmountFilter.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => performAicsFilter(1), 400);
        });
    }

    // 7. Form submission intercept (prevents page jump unless Export CSV is clicked)
    filterForm.addEventListener('submit', function(e) {
        const submitter = e.submitter;
        if (submitter && submitter.name === 'export') {
            return; // Let standard CSV export run
        }
        e.preventDefault();
        clearTimeout(debounceTimer);
        performAicsFilter(1);
    });

    // 8. Clear all filters button
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (statusFilter) statusFilter.value = 'all';
            if (typeFilter) typeFilter.value = 'all';
            if (startDateFilter) startDateFilter.value = '';
            if (endDateFilter) endDateFilter.value = '';
            if (minAmountFilter) minAmountFilter.value = '';
            if (maxAmountFilter) maxAmountFilter.value = '';
            if (searchInput) searchInput.value = '';
            if (clearSearchBtn) clearSearchBtn.style.display = 'none';
            clearTimeout(debounceTimer);
            performAicsFilter(1);
        });
    }

    // 9. Stat Cards click to filter without page reload
    document.querySelectorAll('.stat-card[data-status]').forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            const targetStatus = this.dataset.status;
            if (statusFilter) {
                statusFilter.value = targetStatus;
                clearTimeout(debounceTimer);
                performAicsFilter(1);
            }
        });
    });

    // 10. Type breakdown pills click to filter without page reload
    document.querySelectorAll('.type-pill[data-type]').forEach(pill => {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            const targetType = this.dataset.type;
            if (typeFilter) {
                typeFilter.value = targetType;
                clearTimeout(debounceTimer);
                performAicsFilter(1);
            }
        });
    });

    // 11. Pagination clicks handled via smooth AJAX delegation
    if (tableContainer) {
        tableContainer.addEventListener('click', function(e) {
            const pageLink = e.target.closest('a[data-page]');
            if (pageLink) {
                e.preventDefault();
                const pageNum = parseInt(pageLink.dataset.page, 10);
                if (pageNum) {
                    performAicsFilter(pageNum);
                    tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }

    // 12. Handle browser Back/Forward buttons smoothly
    window.addEventListener('popstate', function(e) {
        const urlParams = new URLSearchParams(window.location.search);
        if (statusFilter) statusFilter.value = urlParams.get('status') || 'all';
        if (typeFilter) typeFilter.value = urlParams.get('type') || 'all';
        if (startDateFilter) startDateFilter.value = urlParams.get('start_date') || '';
        if (endDateFilter) endDateFilter.value = urlParams.get('end_date') || '';
        if (minAmountFilter) minAmountFilter.value = urlParams.get('min_amount') || '';
        if (maxAmountFilter) maxAmountFilter.value = urlParams.get('max_amount') || '';
        if (searchInput) searchInput.value = urlParams.get('search') || '';
        const page = parseInt(urlParams.get('page') || '1', 10);
        performAicsFilter(page);
    });

    // Initial sync
    syncActiveIndicators();
});
</script>
<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>

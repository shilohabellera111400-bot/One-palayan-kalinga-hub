<?php
// admin/manage-verifications.php - Resident Residency Verifications Management
require_once __DIR__ . '/../config/database.php';

if (!isAdmin() && !isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle verification status update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_verification'])) {
    $verification_id = sanitize($_POST['verification_id']);
    $status = sanitize($_POST['status']);
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
    
    $db->beginTransaction();
    
    try {
        // Get user details from verification request
        $get_user = "SELECT v.user_id, u.first_name, u.last_name, u.email, u.contact_number 
                     FROM verification_requests v 
                     JOIN users u ON v.user_id = u.user_id 
                     WHERE v.verification_id = :id";
        $get_stmt = $db->prepare($get_user);
        $get_stmt->bindParam(':id', $verification_id);
        $get_stmt->execute();
        $verification = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($verification) {
            // Update verification request
            $update_query = "UPDATE verification_requests SET 
                             status = :status, 
                             reviewed_by = :reviewed_by, 
                             review_date = NOW(),
                             rejection_reason = :reason
                             WHERE verification_id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':reviewed_by', $_SESSION['user_id']);
            $update_stmt->bindParam(':reason', $rejection_reason);
            $update_stmt->bindParam(':id', $verification_id);
            $update_stmt->execute();
            
            // Update user's verification status
            $user_update = "UPDATE users SET 
                            verification_status = :status,
                            verification_date = NOW(),
                            verified_by = :verified_by
                            WHERE user_id = :user_id";
            $user_stmt = $db->prepare($user_update);
            $user_stmt->bindParam(':status', $status);
            $user_stmt->bindParam(':verified_by', $_SESSION['user_id']);
            $user_stmt->bindParam(':user_id', $verification['user_id']);
            $user_stmt->execute();

            // If approved, check if resident is 60+ and auto-tag as senior_citizen = 1 under RA 9994, and ensure resident_number exists
            if ($status === 'verified') {
                $check_age_stmt = $db->prepare("SELECT id, resident_number, birth_date, senior_citizen FROM residents WHERE user_id = :uid LIMIT 1");
                $check_age_stmt->execute([':uid' => $verification['user_id']]);
                $res_row = $check_age_stmt->fetch(PDO::FETCH_ASSOC);

                if ($res_row) {
                    // Ensure resident number is assigned
                    if (empty($res_row['resident_number'])) {
                        $new_res_num = generateResidentNumber($db);
                        $db->prepare("UPDATE residents SET resident_number = :rn WHERE id = :rid")
                           ->execute([':rn' => $new_res_num, ':rid' => $res_row['id']]);
                    }

                    // Senior citizen check
                    if (!empty($res_row['birth_date']) && $res_row['birth_date'] !== '0000-00-00') {
                        $birth = new DateTime($res_row['birth_date']);
                        $now = new DateTime();
                        $age = $now->diff($birth)->y;
                        if ($age >= 60 && ($res_row['senior_citizen'] ?? 0) != 1) {
                            $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")
                               ->execute([':uid' => $verification['user_id']]);
                        }
                    }
                }
            }
            
            // Create notification for user
            $notif_title = $status == 'verified' ? 'Verification Approved' : 'Verification Disapproved';
            $notif_message = $status == 'verified' 
                ? 'Your residency verification has been approved! You can now access all services.'
                : 'Your residency verification was disapproved. Reason: ' . $rejection_reason;
            
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, :title, :message, 'verification')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $verification['user_id']);
            $notif_stmt->bindParam(':title', $notif_title);
            $notif_stmt->bindParam(':message', $notif_message);
            $notif_stmt->execute();
            
            $db->commit();

            // Send automated verification email notification
            $mail_sent = false;
            try {
                require_once __DIR__ . '/../includes/Mailer.php';
                $mailer = new Mailer();
                $recipient_email = $verification['email'] ?? '';
                $recipient_name = trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? ''));
                if (empty($recipient_name)) {
                    $recipient_name = 'Resident';
                }

                if (!empty($recipient_email)) {
                    if ($status === 'verified') {
                        $send_res = $mailer->sendVerificationApprovedEmail($recipient_email, $recipient_name, $verification['user_id']);
                        $mail_sent = !empty($send_res['success']);
                    } elseif ($status === 'rejected') {
                        $send_res = $mailer->sendVerificationRejectedEmail($recipient_email, $recipient_name, $rejection_reason, $verification['user_id']);
                        $mail_sent = !empty($send_res['success']);
                    }
                }
            } catch (Exception $mailEx) {
                error_log("Failed to send verification notification email: " . $mailEx->getMessage());
            }

            // Send automated verification SMS notification via TextBee
            $sms_sent = false;
            $phone = $verification['contact_number'] ?? '';
            if (!empty($phone)) {
                try {
                    require_once __DIR__ . '/../includes/SMSGateway.php';
                    $smsGateway = new SMSGateway();
                    $recipient_name = trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? ''));
                    if (empty($recipient_name)) {
                        $recipient_name = 'Resident';
                    }

                    if ($status === 'verified') {
                        $sms_res = $smsGateway->sendAccountVerifiedSMS($phone, $recipient_name, (int)$verification['user_id']);
                        $sms_sent = !empty($sms_res['success']);
                    } elseif ($status === 'rejected') {
                        $sms_res = $smsGateway->sendVerificationRejectedSMS($phone, $recipient_name, $rejection_reason, (int)$verification['user_id']);
                        $sms_sent = !empty($sms_res['success']);
                    }
                } catch (\Throwable $smsEx) {
                    error_log("Failed to send verification SMS notification: " . $smsEx->getMessage());
                }
            }

            $notif_feedback = [];
            if ($mail_sent) $notif_feedback[] = "Email sent";
            if ($sms_sent) $notif_feedback[] = "SMS sent via TextBee";
            $feedback_str = !empty($notif_feedback) ? " (" . implode(" & ", $notif_feedback) . ")." : "";

            $success = "Verification status successfully updated for Request #" . str_pad($verification_id, 4, '0', STR_PAD_LEFT) . "!" . $feedback_str;
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Unable to update verification status. Please try again: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT v.*, u.first_name, u.last_name, u.email, u.contact_number, u.profile_picture,
          s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          LEFT JOIN staff s ON v.reviewed_by = s.user_id
          WHERE 1=1";

$params = [];

if ($status_filter != 'all') {
    $query .= " AND v.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR v.verification_id LIKE :search_id)";
    $params[':search'] = "%$search%";
    $params[':search_id'] = "%$search%";
}

$query .= " ORDER BY v.submitted_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM verification_requests";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Helper for initials
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        return substr($initials, 0, 2) ?: 'ST';
    }
}

$page_title = 'Resident Verifications';
$active_nav = 'verifications';
require_once __DIR__ . '/includes/admin-head.php';
?>
    <style>
        :root {
            --maroon: #7F0303;
            --maroon-light: #9A1515;
            --maroon-dark: #5A0202;
            --gold: #D8BA98;
            --midnight: #0F414A;
            
            --bg-primary: var(--ref-canvas, #E4EFE7);
            --bg-card: #FFFFFF;
            --bg-card-hover: #F8FAFC;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.12);
            --bg-sidebar-active: rgba(255, 255, 255, 0.18);
            
            --text-primary: #1E293B;
            --text-secondary: #475569;
            --text-muted: #64748B;
            --text-sidebar: rgba(255, 255, 255, 0.75);
            --text-sidebar-active: #FFFFFF;
            
            --border-color: #E2E8F0;
            --border-focus: #CBD5E1;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 4px 14px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            
            --radius-card: 16px;
            --radius-element: 10px;
            --radius-pill: 50px;
            
            --status-pending-bg: #FFFBEB;
            --status-pending-text: #B45309;
            --status-pending-border: #FDE68A;
            
            --status-verified-bg: #ECFDF5;
            --status-verified-text: #059669;
            --status-verified-border: #A7F3D0;
            
            --status-rejected-bg: #FEF2F2;
            --status-rejected-text: #DC2626;
            --status-rejected-border: #FECACA;
        }

        [data-theme="dark"] {
            --bg-primary: var(--ref-canvas, #0C1417);
            --bg-card: #141B28;
            --bg-card-hover: #1A2334;
            --bg-sidebar: #0F1626;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.08);
            --bg-sidebar-active: rgba(255, 255, 255, 0.14);
            
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-muted: #94A3B8;
            --text-sidebar: rgba(255, 255, 255, 0.65);
            --text-sidebar-active: #FFFFFF;
            
            --border-color: #232E42;
            --border-focus: #334155;
            
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.35);
            --shadow-md: 0 6px 20px rgba(0, 0, 0, 0.45);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.55);
            
            --status-pending-bg: rgba(245, 158, 11, 0.14);
            --status-pending-text: #FBBF24;
            --status-pending-border: rgba(245, 158, 11, 0.3);
            
            --status-verified-bg: rgba(16, 185, 129, 0.14);
            --status-verified-text: #34D399;
            --status-verified-border: rgba(16, 185, 129, 0.3);
            
            --status-rejected-bg: rgba(239, 68, 68, 0.14);
            --status-rejected-text: #F87171;
            --status-rejected-border: rgba(239, 68, 68, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title h1 {
            font-size: 1.45rem;
            color: var(--text-primary);
            font-weight: 800;
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.84rem;
            margin-top: 2px;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-search-box {
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 7px 16px;
            gap: 10px;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .header-search-box:focus-within {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08);
        }
        .header-search-box input {
            border: none;
            outline: none;
            font-size: 0.82rem;
            background: transparent;
            color: var(--text-primary);
            width: 170px;
        }
        .header-search-box i {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .theme-toggle, .top-icon {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .theme-toggle:hover, .top-icon:hover {
            color: var(--maroon);
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== COMPACT ALERT NOTIFICATIONS (NO EMPTY SPACE) ========== */
        .alert-container {
            margin-bottom: 20px;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
            animation: fadeInDown 0.25s ease;
            position: relative;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            border-left: 4px solid #059669;
        }
        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
            border-left: 4px solid #DC2626;
        }
        .alert-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .alert-message {
            flex: 1;
        }
        .alert-close-btn {
            background: none;
            border: none;
            color: inherit;
            opacity: 0.55;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 4px;
            transition: opacity 0.2s;
        }
        .alert-close-btn:hover {
            opacity: 1;
        }

        /* ========== STATISTICS CARDS ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid var(--border-color);
            transition: all 0.25s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: #CBD5E1;
        }
        .stat-card.active-filter {
            border: 2px solid var(--maroon, #7F0303) !important;
            background: #FFFFFF !important;
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.12) !important;
        }
        [data-theme="dark"] .stat-card.active-filter {
            border: 2px solid #EF4444 !important;
            background: var(--bg-card, #1a2332) !important;
            box-shadow: 0 6px 18px rgba(239, 68, 68, 0.18) !important;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .stat-number {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
            margin-top: 4px;
        }

        .stat-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .stat-icon-box.total {
            background: #FEE2E2;
            color: var(--maroon);
        }
        .stat-icon-box.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        .stat-icon-box.verified {
            background: #D1FAE5;
            color: #059669;
        }
        .stat-icon-box.rejected {
            background: #FEE2E2;
            color: #DC2626;
        }

        [data-theme="dark"] .stat-icon-box.total { background: rgba(127, 3, 3, 0.25); color: #F87171; }
        [data-theme="dark"] .stat-icon-box.pending { background: rgba(217, 119, 6, 0.2); color: #FBBF24; }
        [data-theme="dark"] .stat-icon-box.verified { background: rgba(5, 150, 105, 0.2); color: #34D399; }
        [data-theme="dark"] .stat-icon-box.rejected { background: rgba(220, 38, 38, 0.2); color: #F87171; }

        /* ========== COMPACT FILTER TOOLBAR ========== */
        .filter-toolbar-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 16px 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .filter-toolbar-form {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-item label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-item.status-item {
            min-width: 170px;
        }
        .filter-item.search-item {
            flex: 1;
            min-width: 220px;
        }

        .select-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .filter-select {
            width: 100%;
            height: 40px;
            padding: 0 34px 0 14px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.84rem;
            font-weight: 500;
            appearance: none;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .filter-select:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08);
            background: var(--bg-card);
        }
        .select-arrow {
            position: absolute;
            right: 12px;
            font-size: 0.72rem;
            color: var(--text-muted);
            pointer-events: none;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            font-size: 0.82rem;
            color: var(--text-muted);
            pointer-events: none;
        }
        .filter-input {
            width: 100%;
            height: 40px;
            padding: 0 34px 0 36px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.84rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .filter-input:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08);
            background: var(--bg-card);
        }
        .input-clear-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.78rem;
            padding: 4px;
            text-decoration: none;
        }
        .input-clear-btn:hover {
            color: var(--text-primary);
        }

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 40px;
        }
        .btn-filter-apply {
            background: var(--maroon);
            color: white;
            border: 1px solid var(--maroon);
            border-radius: var(--radius-element);
            padding: 0 20px;
            height: 40px;
            font-weight: 600;
            font-size: 0.84rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: var(--shadow-sm);
        }
        .btn-filter-apply:hover {
            background: var(--maroon-light);
            border-color: var(--maroon-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-filter-clear {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: var(--radius-element);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-filter-clear:hover {
            color: var(--maroon);
            background: var(--bg-primary);
        }

        /* ========== MODERN DATA TABLE ========== */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .table-toolbar-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: var(--bg-card);
        }
        .table-header-count {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .active-filter-tag {
            background: var(--bg-primary);
            color: var(--text-muted);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid var(--border-color);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .table-responsive::-webkit-scrollbar { height: 6px; }
        .table-responsive::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

        .verification-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .verification-table th {
            background: var(--bg-primary);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .verification-table td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.84rem;
            color: var(--text-secondary);
            transition: background 0.15s ease;
        }
        .verification-table tbody tr:hover td {
            background: var(--bg-card-hover);
        }

        .id-badge {
            font-family: monospace;
            font-weight: 700;
            font-size: 0.78rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            display: inline-block;
        }

        .applicant-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .applicant-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #F1F5F9;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            overflow: hidden;
        }
        .applicant-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .applicant-info {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }
        .applicant-name {
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .applicant-email {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .id-type-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--text-secondary);
            background: var(--bg-primary);
            padding: 3px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }
        .id-type-pill i {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .location-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--text-primary);
            white-space: nowrap;
        }
        .location-info i {
            color: var(--maroon);
            font-size: 0.75rem;
        }

        .date-cell {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            white-space: nowrap;
        }
        .date-main {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .date-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        /* Pill Status Badges */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-pill.pending {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
            border: 1px solid var(--status-pending-border);
        }
        .status-pill.verified {
            background: var(--status-verified-bg);
            color: var(--status-verified-text);
            border: 1px solid var(--status-verified-border);
        }
        .status-pill.rejected {
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
            border: 1px solid var(--status-rejected-border);
        }

        /* Action Buttons */
        .actions-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .btn-table-action {
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-table-view {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-color: var(--border-color);
        }
        .btn-table-view:hover {
            background: var(--border-color);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .btn-table-verify {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
        }
        .btn-table-verify:hover {
            background: var(--maroon-light);
            box-shadow: 0 3px 8px rgba(127, 3, 3, 0.22);
            transform: translateY(-1px);
        }

        .btn-table-edit {
            background: transparent;
            color: var(--text-muted);
            border-color: var(--border-color);
        }
        .btn-table-edit:hover {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }

        /* Table Empty State */
        .table-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .table-empty-state i {
            font-size: 3rem;
            opacity: 0.25;
            color: var(--maroon);
            margin-bottom: 12px;
            display: block;
        }
        .table-empty-state h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .table-empty-state p {
            font-size: 0.82rem;
            max-width: 320px;
            margin: 0 auto 16px;
        }

        /* ========== MODERN VERIFY MODAL ========== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            backdrop-filter: blur(4px);
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--bg-card);
            padding: 28px 30px;
            border-radius: 20px;
            max-width: 480px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            transform: translateY(-20px) scale(0.97);
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal.active .modal-content {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h3 i {
            color: var(--maroon);
        }
        .modal-close-icon {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.2s;
        }
        .modal-close-icon:hover {
            color: var(--text-primary);
        }

        .modal-applicant-summary {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
        }
        .modal-applicant-summary .summary-name {
            font-weight: 700;
            color: var(--text-primary);
        }
        .modal-applicant-summary .summary-id {
            font-family: monospace;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.84rem;
            font-family: inherit;
            background: var(--bg-primary);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group select:focus, .form-group textarea:focus {
            border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.08);
            background: var(--bg-card);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 85px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }
        .modal-buttons button {
            flex: 1;
            height: 42px;
            border: none;
            border-radius: var(--radius-element);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }
        .modal-buttons .btn-submit {
            background: var(--maroon);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        .modal-buttons .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .modal-buttons .btn-cancel {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }
        .modal-buttons .btn-cancel:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px 18px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .top-bar-actions {
                justify-content: space-between;
            }
            .header-search-box {
                flex: 1;
            }
            .header-search-box input {
                width: 100%;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .filter-toolbar-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-item {
                width: 100%;
            }
            .filter-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .btn-filter-apply {
                flex: 1;
                justify-content: center;
            }
        }

        @media (max-width: 500px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stat-number {
                font-size: 1.6rem;
            }
            .modal-content {
                padding: 20px;
            }
            .modal-buttons {
                flex-direction: column;
            }
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-shield-alt" style="color: var(--ref-maroon);"></i> Resident Verifications</h1>
        <p>Review and manage resident residency verification requests</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <span class="status-badge-pill verified">
            <i class="fas fa-shield-check"></i>
            <span><?php echo number_format($stats['pending'] ?? 0); ?> Pending Review</span>
        </span>
    </div>
</div>

        <!-- Alert Notification Banners (Only displayed when message exists) -->
        <?php if(!empty($success)): ?>
            <div class="alert-container">
                <div class="alert alert-success" id="alertSuccessBanner">
                    <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="alert-message"><?php echo htmlspecialchars($success); ?></div>
                    <button type="button" class="alert-close-btn" onclick="document.getElementById('alertSuccessBanner').remove();" title="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert-container">
                <div class="alert alert-error" id="alertErrorBanner">
                    <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="alert-message"><?php echo htmlspecialchars($error); ?></div>
                    <button type="button" class="alert-close-btn" onclick="document.getElementById('alertErrorBanner').remove();" title="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="manage-verifications.php?status=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
               class="stat-card <?php echo $status_filter == 'all' ? 'active-filter' : ''; ?>" title="View all verification requests">
                <div class="stat-info">
                    <span class="stat-label">Total Requests</span>
                    <span class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></span>
                </div>
                <div class="stat-icon-box total">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </a>

            <a href="manage-verifications.php?status=pending<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
               class="stat-card <?php echo $status_filter == 'pending' ? 'active-filter' : ''; ?>" title="View pending requests awaiting review">
                <div class="stat-info">
                    <span class="stat-label">Pending</span>
                    <span class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></span>
                </div>
                <div class="stat-icon-box pending">
                    <i class="fas fa-clock"></i>
                </div>
            </a>

            <a href="manage-verifications.php?status=verified<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
               class="stat-card <?php echo $status_filter == 'verified' ? 'active-filter' : ''; ?>" title="View verified residency accounts">
                <div class="stat-info">
                    <span class="stat-label">Verified</span>
                    <span class="stat-number"><?php echo number_format($stats['verified'] ?? 0); ?></span>
                </div>
                <div class="stat-icon-box verified">
                    <i class="fas fa-check-circle"></i>
                </div>
            </a>

            <a href="manage-verifications.php?status=rejected<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
               class="stat-card <?php echo $status_filter == 'rejected' ? 'active-filter' : ''; ?>" title="View disapproved requests">
                <div class="stat-info">
                    <span class="stat-label">Disapproved</span>
                    <span class="stat-number"><?php echo number_format($stats['rejected'] ?? 0); ?></span>
                </div>
                <div class="stat-icon-box rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
            </a>
        </div>

        <!-- Filter and Search Toolbar -->
        <div class="filter-toolbar-card">
            <form method="GET" class="filter-toolbar-form">
                <div class="filter-item status-item">
                    <label for="statusFilter"><i class="fas fa-filter"></i> Status</label>
                    <div class="select-wrapper">
                        <select name="status" id="statusFilter" class="filter-select">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                            <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified Only</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Disapproved Only</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <div class="filter-item search-item">
                    <label for="searchResident"><i class="fas fa-search"></i> Search Resident</label>
                    <div class="input-with-icon">
                        <i class="fas fa-search input-icon"></i>
                        <input type="text" name="search" id="searchResident" class="filter-input" 
                               placeholder="Search by name, email, or request ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <?php if(!empty($search)): ?>
                            <a href="manage-verifications.php?status=<?php echo urlencode($status_filter); ?>" class="input-clear-btn" title="Clear search"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter-apply">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <?php if($status_filter != 'pending' || !empty($search)): ?>
                        <a href="manage-verifications.php" class="btn-filter-clear">
                            <i class="fas fa-redo"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Verification Data Table -->
        <div class="table-card">
            <div class="table-toolbar-header">
                <div class="table-header-count">
                    <span>Showing Requests</span>
                    <span class="active-filter-tag">
                        <?php 
                            if ($status_filter === 'all') echo 'All Status';
                            elseif ($status_filter === 'pending') echo 'Pending Review';
                            elseif ($status_filter === 'verified') echo 'Verified';
                            elseif ($status_filter === 'rejected') echo 'Disapproved';
                        ?>
                    </span>
                    <?php if(!empty($search)): ?>
                        <span class="active-filter-tag" style="background: rgba(127,3,3,0.06); color: var(--maroon);">
                            "<?php echo htmlspecialchars($search); ?>"
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="verification-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th>Applicant</th>
                            <th>ID Type</th>
                            <th>Barangay</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 28px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($stmt->rowCount() > 0): ?>
                            <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                $applicant_name = trim($row['first_name'] . ' ' . $row['last_name']);
                                $app_initials = getInitials($applicant_name ?: 'Applicant');
                                $user_pic = !empty($row['profile_picture']) ? $row['profile_picture'] : '';
                            ?>
                            <tr>
                                <td>
                                    <span class="id-badge">#<?php echo str_pad($row['verification_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                </td>
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
                                            <span class="applicant-name"><?php echo htmlspecialchars($applicant_name); ?></span>
                                            <span class="applicant-email"><?php echo htmlspecialchars($row['email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="id-type-pill">
                                        <i class="fas fa-id-card"></i>
                                        <?php echo htmlspecialchars(html_entity_decode($row['id_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="location-info">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($row['barangay_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <span class="date-main"><?php echo date('M d, Y', strtotime($row['submitted_date'])); ?></span>
                                        <span class="date-sub"><?php echo date('h:i A', strtotime($row['submitted_date'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'pending'): ?>
                                        <span class="status-pill pending">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php elseif($row['status'] == 'verified'): ?>
                                        <span class="status-pill verified">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill rejected">
                                            <i class="fas fa-times-circle"></i> Disapproved
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="actions-cell" style="justify-content: flex-end;">
                                        <a href="view-verification.php?id=<?php echo $row['verification_id']; ?>" 
                                           class="btn-table-action btn-table-view" title="View document and resident details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if($row['status'] == 'pending'): ?>
                                            <button type="button" 
                                                    onclick="openModal(<?php echo $row['verification_id']; ?>, '<?php echo addslashes($applicant_name); ?>', 'pending')" 
                                                    class="btn-table-action btn-table-verify" title="Review and verify this applicant">
                                                <i class="fas fa-check-circle"></i> Verify
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    onclick="openModal(<?php echo $row['verification_id']; ?>, '<?php echo addslashes($applicant_name); ?>', '<?php echo $row['status']; ?>')" 
                                                    class="btn-table-action btn-table-edit" title="Re-evaluate or change status">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="table-empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Verification Requests Found</h4>
                                        <p>There are no residency requests matching your current filter criteria.</p>
                                        <?php if($status_filter != 'pending' || !empty($search)): ?>
                                            <a href="manage-verifications.php" class="btn-filter-apply" style="display:inline-flex; text-decoration:none; line-height:40px;">
                                                <i class="fas fa-redo"></i> Reset Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Modern Verify Modal -->
<div id="verifyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-shield-alt"></i> Verify Resident Application</h3>
            <button type="button" class="modal-close-icon" onclick="closeModal()" title="Close"><i class="fas fa-times"></i></button>
        </div>

        <div class="modal-applicant-summary" id="modalApplicantBox">
            <span class="summary-name" id="modalApplicantName">Applicant Name</span>
            <span class="summary-id" id="modalRequestId">#0000</span>
        </div>

        <form method="POST">
            <input type="hidden" name="verification_id" id="verification_id">
            
            <div class="form-group">
                <label for="verify_status">Verification Decision</label>
                <select name="status" id="verify_status" required>
                    <option value="verified">✓ Approve - Verified Resident</option>
                    <option value="rejected">✕ Disapproved - Not Verified</option>
                </select>
            </div>

            <div class="form-group" id="reason_group" style="display: none;">
                <label for="rejection_reason">Disapproval Reason <span style="color: #DC2626;">*</span></label>
                <textarea name="rejection_reason" id="rejection_reason" rows="3" placeholder="State reason for disapproving residency request (e.g. invalid document, blurry photo, out of jurisdiction)..."></textarea>
            </div>

            <div class="modal-buttons">
                <button type="submit" name="update_verification" class="btn-submit">
                    <i class="fas fa-check"></i> Submit Decision
                </button>
                <button type="button" onclick="closeModal()" class="btn-cancel">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>

// ========== QUICK SEARCH VIA ENTER KEY ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim();
        window.location.href = 'manage-verifications.php?search=' + encodeURIComponent(query) + '&status=<?php echo urlencode($status_filter); ?>';
    }
});

// ========== MODAL CONTROLS ==========
function openModal(id, applicantName = '', currentStatus = 'pending') {
    document.getElementById('verification_id').value = id;
    
    const nameElem = document.getElementById('modalApplicantName');
    const idElem = document.getElementById('modalRequestId');
    if (nameElem && applicantName) nameElem.textContent = applicantName;
    if (idElem) idElem.textContent = '#' + String(id).padStart(4, '0');

    const statusSelect = document.getElementById('verify_status');
    if (statusSelect) {
        statusSelect.value = (currentStatus === 'rejected') ? 'rejected' : 'verified';
    }

    const reasonGroup = document.getElementById('reason_group');
    const reasonInput = document.getElementById('rejection_reason');
    if (currentStatus === 'rejected' && reasonGroup) {
        reasonGroup.style.display = 'block';
        if (reasonInput) reasonInput.required = true;
    } else if (reasonGroup) {
        reasonGroup.style.display = 'none';
        if (reasonInput) reasonInput.required = false;
    }

    const modal = document.getElementById('verifyModal');
    if (modal) modal.classList.add('active');
}

function closeModal() {
    const modal = document.getElementById('verifyModal');
    if (modal) modal.classList.remove('active');
    
    const reasonGroup = document.getElementById('reason_group');
    const reasonInput = document.getElementById('rejection_reason');
    if (reasonGroup) reasonGroup.style.display = 'none';
    if (reasonInput) {
        reasonInput.value = '';
        reasonInput.required = false;
    }
    
    const statusSelect = document.getElementById('verify_status');
    if (statusSelect) statusSelect.value = 'verified';
}

// Dynamic Toggle Rejection Reason
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('verify_status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            const reasonGroup = document.getElementById('reason_group');
            const reasonInput = document.getElementById('rejection_reason');
            if (this.value === 'rejected') {
                if (reasonGroup) reasonGroup.style.display = 'block';
                if (reasonInput) {
                    reasonInput.required = true;
                    reasonInput.focus();
                }
            } else {
                if (reasonGroup) reasonGroup.style.display = 'none';
                if (reasonInput) {
                    reasonInput.required = false;
                    reasonInput.value = '';
                }
            }
        });
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('verifyModal');
    if (event.target === modal) {
        closeModal();
    }
};

// Close modal on Escape key
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
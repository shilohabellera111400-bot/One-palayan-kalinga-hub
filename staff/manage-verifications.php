<?php
// staff/manage-verifications.php - Residency Verifications Hub for Staff Portal
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/SMSGateway.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff details and service category assignments
$staff_id_query = "SELECT s.id, s.position, u.profile_picture 
                   FROM staff s 
                   JOIN users u ON s.user_id = u.user_id 
                   WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? (int)$staff_info['id'] : 0;

$assigned_categories = [];
if ($staff_id > 0) {
    $cat_query = "SELECT DISTINCT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->bindParam(':staff_id', $staff_id);
    $cat_stmt->execute();
    $assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Access flag check
$has_verif_access = isAdmin() || empty($assigned_categories) || in_array('Verification', $assigned_categories) || in_array('Residency Verification', $assigned_categories);
if (!$has_verif_access) {
    $_SESSION['error'] = "You do not have access permission to manage Residency Verifications.";
    redirect('../staff-dashboard.php');
}

$error = '';
$success = '';

// Handle verification status update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_verification'])) {
    $verification_id = sanitize($_POST['verification_id'] ?? '');
    $status = sanitize($_POST['status'] ?? '');
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');

    if (!in_array($status, ['verified', 'rejected'])) {
        $error = "Invalid verification status selected.";
    } elseif ($status === 'rejected' && empty(trim($rejection_reason))) {
        $error = "Please specify a reason for rejecting the residency verification.";
    } else {
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
                        if (empty($res_row['resident_number']) && function_exists('generateResidentNumber')) {
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

                // In-app resident notification
                $notif_title = ($status === 'verified') ? 'Residency Verification Approved' : 'Residency Verification Needs Revision';
                $notif_message = ($status === 'verified')
                    ? 'Mabuhay! Ang inyong residency verification ay naaprubahan na. Maaari na kayong mag-access ng lahat ng online services at appointment booking sa One Palayan-Kalinga Hub.'
                    : 'Paunawa: Ang inyong residency verification ay hindi naaprubahan. Dahilan: ' . $rejection_reason . '. Maaari kayong magsumite muli ng tamang dokumento.';

                $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES (:user_id, :title, :message, 'verification')";
                $notif_stmt = $db->prepare($notif_query);
                $notif_stmt->bindParam(':user_id', $verification['user_id']);
                $notif_stmt->bindParam(':title', $notif_title);
                $notif_stmt->bindParam(':message', $notif_message);
                $notif_stmt->execute();

                $db->commit();

                // Send email notification
                $mail_sent = false;
                try {
                    $mailer = new Mailer();
                    $recipient_email = $verification['email'] ?? '';
                    $recipient_name = trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? ''));
                    if (empty($recipient_name)) {
                        $recipient_name = 'Resident';
                    }

                    if (!empty($recipient_email)) {
                        if ($status === 'verified' && method_exists($mailer, 'sendVerificationApprovedEmail')) {
                            $send_res = $mailer->sendVerificationApprovedEmail($recipient_email, $recipient_name, (int)$verification['user_id']);
                            $mail_sent = !empty($send_res['success']);
                        } elseif ($status === 'rejected' && method_exists($mailer, 'sendVerificationRejectedEmail')) {
                            $send_res = $mailer->sendVerificationRejectedEmail($recipient_email, $recipient_name, $rejection_reason, (int)$verification['user_id']);
                            $mail_sent = !empty($send_res['success']);
                        }
                    }
                } catch (\Throwable $mailEx) {
                    error_log("Failed to send verification notification email: " . $mailEx->getMessage());
                }

                // Send SMS notification via TextBee
                $sms_sent = false;
                $phone = $verification['contact_number'] ?? '';
                if (!empty($phone)) {
                    try {
                        $smsGateway = new SMSGateway();
                        $recipient_name = trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? ''));
                        if (empty($recipient_name)) {
                            $recipient_name = 'Resident';
                        }

                        if ($status === 'verified' && method_exists($smsGateway, 'sendAccountVerifiedSMS')) {
                            $sms_res = $smsGateway->sendAccountVerifiedSMS($phone, $recipient_name, (int)$verification['user_id']);
                            $sms_sent = !empty($sms_res['success']);
                        } elseif ($status === 'rejected' && method_exists($smsGateway, 'sendVerificationRejectedSMS')) {
                            $sms_res = $smsGateway->sendVerificationRejectedSMS($phone, $recipient_name, $rejection_reason, (int)$verification['user_id']);
                            $sms_sent = !empty($sms_res['success']);
                        }
                    } catch (\Throwable $smsEx) {
                        error_log("Failed to send verification SMS notification: " . $smsEx->getMessage());
                    }
                }

                $feedback_arr = [];
                if ($mail_sent) $feedback_arr[] = "Email sent";
                if ($sms_sent) $feedback_arr[] = "SMS sent via TextBee";
                $feedback_str = !empty($feedback_arr) ? " (" . implode(" & ", $feedback_arr) . ")." : ".";

                $success = "Verification status updated successfully for Request #" . str_pad($verification_id, 4, '0', STR_PAD_LEFT) . "!" . $feedback_str;
            } else {
                $db->rollBack();
                $error = "Verification request not found.";
            }
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Unable to update verification status: " . $e->getMessage();
        }
    }
}

// Filters & Query
$status_filter = sanitize($_GET['status'] ?? 'pending');
$search = trim(sanitize($_GET['search'] ?? ''));

// Statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'verified' => 0,
    'rejected' => 0
];
try {
    $stats_row = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM verification_requests")->fetch(PDO::FETCH_ASSOC);
    if ($stats_row) {
        $stats['total'] = (int)($stats_row['total'] ?? 0);
        $stats['pending'] = (int)($stats_row['pending'] ?? 0);
        $stats['verified'] = (int)($stats_row['verified'] ?? 0);
        $stats['rejected'] = (int)($stats_row['rejected'] ?? 0);
    }
} catch (\Throwable $e) {}

// Build list query
$query = "SELECT v.*, u.first_name, u.last_name, u.email, u.contact_number, u.profile_picture,
                 s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          LEFT JOIN staff s ON v.reviewed_by = s.user_id
          WHERE 1=1";

$params = [];
if ($status_filter !== 'all') {
    $query .= " AND v.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR v.verification_id LIKE :search_id OR v.barangay_name LIKE :search_bgy)";
    $params[':search'] = "%$search%";
    $params[':search_id'] = "%$search%";
    $params[':search_bgy'] = "%$search%";
}

$query .= " ORDER BY v.submitted_date DESC";
$stmt = $db->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for initials
if (!function_exists('getStaffInitials')) {
    function getStaffInitials($name) {
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

$page_title = 'Residency Verifications - Staff Operations';
$active_nav = 'verifications';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
/* Verification Staff Hub Specific Styling */
.verif-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.verif-stat-card {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    border: 1.5px solid #E2E8F0;
    transition: all 0.22s ease;
    cursor: pointer;
    text-decoration: none;
}

[data-theme="dark"] .verif-stat-card {
    background: #141B28;
    border-color: #232E42;
}

.verif-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

/* Active filter card keeps card background white and uses border highlight */
.verif-stat-card.active-filter {
    border: 2.5px solid var(--ref-maroon, #7F0303) !important;
    background: #FFFFFF !important;
    box-shadow: 0 6px 18px rgba(127, 3, 3, 0.12) !important;
}

[data-theme="dark"] .verif-stat-card.active-filter {
    border: 2.5px solid #EF4444 !important;
    background: #141B28 !important;
    box-shadow: 0 6px 18px rgba(239, 68, 68, 0.18) !important;
}

.verif-stat-info {
    display: flex;
    flex-direction: column;
}

.verif-stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-muted, #64748B);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.verif-stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--ref-text, #1E293B);
    line-height: 1.15;
    margin-top: 4px;
}

.verif-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.verif-stat-icon.total { background: #FEE2E2; color: #7F0303; }
.verif-stat-icon.pending { background: #FEF3C7; color: #D97706; }
.verif-stat-icon.verified { background: #D1FAE5; color: #059669; }
.verif-stat-icon.rejected { background: #FEE2E2; color: #DC2626; }

[data-theme="dark"] .verif-stat-icon.total { background: rgba(127, 3, 3, 0.25); color: #F87171; }
[data-theme="dark"] .verif-stat-icon.pending { background: rgba(217, 119, 6, 0.2); color: #FBBF24; }
[data-theme="dark"] .verif-stat-icon.verified { background: rgba(5, 150, 105, 0.2); color: #34D399; }
[data-theme="dark"] .verif-stat-icon.rejected { background: rgba(220, 38, 38, 0.2); color: #F87171; }

/* Filter & Search Bar */
.verif-toolbar {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 16px 22px;
    margin-bottom: 22px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    border: 1px solid #E2E8F0;
}

[data-theme="dark"] .verif-toolbar {
    background: #141B28;
    border-color: #232E42;
}

.verif-toolbar-form {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
}

.verif-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.verif-form-group label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ref-muted, #64748B);
    display: flex;
    align-items: center;
    gap: 6px;
}

.verif-select, .verif-input {
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #1E293B;
    font-size: 0.86rem;
    outline: none;
    transition: all 0.2s;
}

[data-theme="dark"] .verif-select, [data-theme="dark"] .verif-input {
    background: #0F1626;
    border-color: #2A364F;
    color: #F1F5F9;
}

.verif-select:focus, .verif-input:focus {
    border-color: #7F0303;
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
    background: #FFFFFF;
}

[data-theme="dark"] .verif-select:focus, [data-theme="dark"] .verif-input:focus {
    background: #141B28;
    border-color: #EF4444;
}

.verif-btn-apply {
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    background: #7F0303;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.verif-btn-apply:hover {
    background: #9A1515;
    box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
}

.verif-btn-clear {
    height: 42px;
    padding: 0 16px;
    border-radius: 10px;
    background: transparent;
    color: #64748B;
    border: 1px solid #CBD5E1;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.verif-btn-clear:hover {
    background: #F1F5F9;
    color: #1E293B;
}

/* Data Table Container */
.verif-table-card {
    background: #FFFFFF;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    border: 1px solid #E2E8F0;
}

[data-theme="dark"] .verif-table-card {
    background: #141B28;
    border-color: #232E42;
}

.verif-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.86rem;
}

.verif-table th {
    background: #F8FAFC;
    padding: 14px 18px;
    font-weight: 700;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748B;
    border-bottom: 1.5px solid #E2E8F0;
}

[data-theme="dark"] .verif-table th {
    background: #0F1626;
    border-color: #232E42;
    color: #94A3B8;
}

.verif-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
}

[data-theme="dark"] .verif-table td {
    border-bottom-color: #1E293B;
}

.verif-table tr:hover td {
    background: rgba(127, 3, 3, 0.02);
}

[data-theme="dark"] .verif-table tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}

/* Subtle pastel highlight for the latest submitted request */
.verif-row-latest td {
    background: #FFF9ED !important;
}

[data-theme="dark"] .verif-row-latest td {
    background: rgba(217, 119, 6, 0.08) !important;
}

.latest-pill-tag {
    display: inline-block;
    background: #FEF3C7;
    color: #B45309;
    font-size: 0.62rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 6px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

/* Applicant Cell */
.applicant-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.applicant-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #FEF2F2;
    color: #7F0303;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.78rem;
    overflow: hidden;
    flex-shrink: 0;
    border: 1.5px solid #FCA5A5;
}

.applicant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.applicant-name {
    font-weight: 700;
    color: #1E293B;
    line-height: 1.25;
}

[data-theme="dark"] .applicant-name { color: #F1F5F9; }

.applicant-meta {
    font-size: 0.74rem;
    color: #64748B;
    line-height: 1.2;
}

/* Badges & Pills */
.pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
}

.pill-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.pill-verified { background: #D1FAE5; color: #047857; border: 1px solid #A7F3D0; }
.pill-rejected { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }

[data-theme="dark"] .pill-pending { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
[data-theme="dark"] .pill-verified { background: rgba(16, 185, 129, 0.15); color: #34D399; border-color: rgba(16, 185, 129, 0.3); }
[data-theme="dark"] .pill-rejected { background: rgba(239, 68, 68, 0.15); color: #F87171; border-color: rgba(239, 68, 68, 0.3); }

.pill-id-type {
    background: #F1F5F9;
    color: #334155;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

[data-theme="dark"] .pill-id-type {
    background: #1E293B;
    color: #CBD5E1;
}

/* Action Buttons */
.btn-action-view {
    background: #F1F5F9;
    color: #334155;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.btn-action-view:hover {
    background: #E2E8F0;
    color: #0F172A;
}

[data-theme="dark"] .btn-action-view {
    background: #1E293B;
    color: #CBD5E1;
}

.btn-action-verify {
    background: #7F0303;
    color: #FFFFFF;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.btn-action-verify:hover {
    background: #9A1515;
    box-shadow: 0 2px 8px rgba(127, 3, 3, 0.25);
}

.btn-action-edit {
    background: rgba(217, 119, 6, 0.12);
    color: #B45309;
    border: 1px solid rgba(217, 119, 6, 0.25);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.btn-action-edit:hover {
    background: #B45309;
    color: #FFFFFF;
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2500;
    padding: 16px;
}

.modal-overlay.active {
    display: flex;
}

.modal-box {
    background: #FFFFFF;
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    border: 1px solid #E2E8F0;
    overflow: hidden;
}

[data-theme="dark"] .modal-box {
    background: #141B28;
    border-color: #232E42;
}

.modal-box-header {
    padding: 18px 24px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #F8FAFC;
}

[data-theme="dark"] .modal-box-header {
    background: #0F1626;
    border-color: #232E42;
}

.modal-box-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #7F0303;
    display: flex;
    align-items: center;
    gap: 8px;
}

[data-theme="dark"] .modal-box-header h3 { color: #F87171; }

.modal-close-btn {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #64748B;
    cursor: pointer;
    line-height: 1;
}

.modal-box-body {
    padding: 22px 24px;
}

.modal-resident-pill {
    background: #F1F5F9;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 18px;
    border: 1px solid #E2E8F0;
}

[data-theme="dark"] .modal-resident-pill {
    background: #0F1626;
    border-color: #232E42;
}

.modal-box-footer {
    padding: 16px 24px;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #F8FAFC;
}

[data-theme="dark"] .modal-box-footer {
    background: #0F1626;
    border-color: #232E42;
}

/* Empty State */
.table-empty-box {
    padding: 50px 20px;
    text-align: center;
    color: #64748B;
}

.table-empty-box i {
    font-size: 2.8rem;
    color: #CBD5E1;
    margin-bottom: 14px;
}

.table-empty-box h4 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1E293B;
    margin-bottom: 6px;
}

[data-theme="dark"] .table-empty-box h4 { color: #F1F5F9; }
</style>
</head>
<body>
<div class="app-container">
    <!-- Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Staff Header -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Staff Big Card Canvas -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "Residency Verifications Management";
            $banner_subtitle = "Review and verify resident identity, proofs of residency, and validate service entitlements";
            $banner_icon = "fa-shield-alt";
            $banner_actions = [
                ["label" => "Pending Review", "url" => "manage-verifications.php?status=pending", "icon" => "fa-clock", "type" => "primary"],
                ["label" => "All Requests", "url" => "manage-verifications.php?status=all", "icon" => "fa-list", "type" => "secondary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

            <!-- Success / Error Alerts -->
            <?php if (!empty($success)): ?>
                <div style="background: #D1FAE5; border: 1.5px solid #10B981; color: #065F46; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-weight: 600;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 1.2rem; color: #059669;"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#065F46; cursor:pointer; font-size:1.1rem;"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #FEE2E2; border: 1.5px solid #EF4444; color: #991B1B; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; font-weight: 600;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 1.2rem; color: #DC2626;"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#991B1B; cursor:pointer; font-size:1.1rem;"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- KPI Statistics Cards -->
            <div class="verif-stats-grid">
                <a href="manage-verifications.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="verif-stat-card <?php echo ($status_filter === 'all') ? 'active-filter' : ''; ?>" title="View all verification requests">
                    <div class="verif-stat-info">
                        <span class="verif-stat-label">Total Requests</span>
                        <span class="verif-stat-number"><?php echo number_format($stats['total']); ?></span>
                    </div>
                    <div class="verif-stat-icon total"><i class="fas fa-file-invoice"></i></div>
                </a>

                <a href="manage-verifications.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="verif-stat-card <?php echo ($status_filter === 'pending') ? 'active-filter' : ''; ?>" title="View pending requests awaiting review">
                    <div class="verif-stat-info">
                        <span class="verif-stat-label">Pending Review</span>
                        <span class="verif-stat-number"><?php echo number_format($stats['pending']); ?></span>
                    </div>
                    <div class="verif-stat-icon pending"><i class="fas fa-clock"></i></div>
                </a>

                <a href="manage-verifications.php?status=verified<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="verif-stat-card <?php echo ($status_filter === 'verified') ? 'active-filter' : ''; ?>" title="View verified residency accounts">
                    <div class="verif-stat-info">
                        <span class="verif-stat-label">Verified</span>
                        <span class="verif-stat-number"><?php echo number_format($stats['verified']); ?></span>
                    </div>
                    <div class="verif-stat-icon verified"><i class="fas fa-check-circle"></i></div>
                </a>

                <a href="manage-verifications.php?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="verif-stat-card <?php echo ($status_filter === 'rejected') ? 'active-filter' : ''; ?>" title="View rejected requests">
                    <div class="verif-stat-info">
                        <span class="verif-stat-label">Rejected</span>
                        <span class="verif-stat-number"><?php echo number_format($stats['rejected']); ?></span>
                    </div>
                    <div class="verif-stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                </a>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="verif-toolbar">
                <form method="GET" class="verif-toolbar-form">
                    <div class="verif-form-group" style="min-width: 170px;">
                        <label for="statusSelect"><i class="fas fa-filter"></i> Filter Status</label>
                        <select name="status" id="statusSelect" class="verif-select">
                            <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending Only</option>
                            <option value="verified" <?php echo ($status_filter === 'verified') ? 'selected' : ''; ?>>Verified Only</option>
                            <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected Only</option>
                        </select>
                    </div>

                    <div class="verif-form-group" style="flex: 1; min-width: 240px;">
                        <label for="searchQuery"><i class="fas fa-search"></i> Search Resident</label>
                        <input type="text" name="search" id="searchQuery" class="verif-input" 
                               placeholder="Search by resident name, email, request ID, or barangay..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="verif-btn-apply"><i class="fas fa-filter"></i> Filter</button>
                        <?php if ($status_filter !== 'pending' || !empty($search)): ?>
                            <a href="manage-verifications.php" class="verif-btn-clear"><i class="fas fa-redo"></i> Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Data Table Card -->
            <div class="verif-table-card">
                <div style="overflow-x: auto;">
                    <table class="verif-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Request</th>
                                <th>Resident Applicant</th>
                                <th>ID Type</th>
                                <th>Barangay</th>
                                <th>Submitted Date</th>
                                <th>Status</th>
                                <th style="text-align: right; padding-right: 24px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $idx => $row): 
                                    $applicant_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                    $initials = getStaffInitials($applicant_name ?: 'Resident');
                                    $avatar = $row['profile_picture'] ?? '';
                                    $is_latest = ($idx === 0);
                                ?>
                                <tr class="<?php echo $is_latest ? 'verif-row-latest' : ''; ?>">
                                    <td>
                                        <span style="font-weight: 800; color: #7F0303;">#<?php echo str_pad($row['verification_id'], 4, '0', STR_PAD_LEFT); ?></span>
                                        <?php if ($is_latest): ?>
                                            <span class="latest-pill-tag">Latest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="applicant-cell">
                                            <div class="applicant-avatar">
                                                <?php if (!empty($avatar) && file_exists(__DIR__ . '/../' . $avatar)): ?>
                                                    <img src="../<?php echo htmlspecialchars($avatar); ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?php echo $initials; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="applicant-name"><?php echo htmlspecialchars($applicant_name); ?></div>
                                                <div class="applicant-meta"><?php echo htmlspecialchars($row['email'] ?? $row['contact_number'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="pill-id-type">
                                            <i class="fas fa-id-card"></i>
                                            <?php echo htmlspecialchars(html_entity_decode($row['id_type'] ?? 'Valid ID')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--ref-text);">
                                            <i class="fas fa-map-marker-alt" style="color: #7F0303; margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($row['barangay_name'] ?? 'Palayan City'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="line-height: 1.25;">
                                            <div style="font-weight: 700; color: var(--ref-text);"><?php echo date('M d, Y', strtotime($row['submitted_date'])); ?></div>
                                            <div style="font-size: 0.72rem; color: #64748B;"><?php echo date('h:i A', strtotime($row['submitted_date'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <span class="pill-badge pill-pending"><i class="fas fa-clock"></i> Pending</span>
                                        <?php elseif ($row['status'] === 'verified'): ?>
                                            <span class="pill-badge pill-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php else: ?>
                                            <span class="pill-badge pill-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; padding-right: 20px;">
                                        <div style="display: inline-flex; align-items: center; gap: 8px;">
                                            <a href="view-verification.php?id=<?php echo $row['verification_id']; ?>" class="btn-action-view" title="Inspect resident ID and document uploads">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <button type="button" onclick="openVerifyModal(<?php echo $row['verification_id']; ?>, '<?php echo addslashes($applicant_name); ?>', 'pending')" class="btn-action-verify" title="Review and approve residency">
                                                    <i class="fas fa-check-circle"></i> Verify
                                                </button>
                                            <?php else: ?>
                                                <button type="button" onclick="openVerifyModal(<?php echo $row['verification_id']; ?>, '<?php echo addslashes($applicant_name); ?>', '<?php echo $row['status']; ?>')" class="btn-action-edit" title="Change verification decision">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="table-empty-box">
                                            <i class="fas fa-shield-alt"></i>
                                            <h4>No Verification Requests Found</h4>
                                            <p>No residency verification requests match your current filters.</p>
                                            <?php if ($status_filter !== 'pending' || !empty($search)): ?>
                                                <div style="margin-top: 14px;">
                                                    <a href="manage-verifications.php" class="verif-btn-apply" style="text-decoration:none;"><i class="fas fa-redo"></i> Reset Filters</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Dialog for Staff Review Decision -->
<div id="staffVerifyModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-box-header">
            <h3><i class="fas fa-shield-alt"></i> Verify Resident Residency</h3>
            <button type="button" class="modal-close-btn" onclick="closeVerifyModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-box-body">
                <input type="hidden" name="verification_id" id="modal_verification_id">
                
                <div class="modal-resident-pill">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748B; font-weight: 700;">Resident Applicant</div>
                    <div id="modal_applicant_name" style="font-weight: 800; font-size: 1.05rem; color: #1E293B;">Applicant Name</div>
                    <div id="modal_request_id" style="font-size: 0.8rem; color: #7F0303; font-weight: 700;">#0000</div>
                </div>

                <div class="verif-form-group" style="margin-bottom: 16px;">
                    <label for="modal_verify_status" style="font-weight: 700; color: #1E293B;">Verification Decision *</label>
                    <select name="status" id="modal_verify_status" class="verif-select" required>
                        <option value="verified">✓ Approve - Verified Resident</option>
                        <option value="rejected">✕ Reject - Not Verified / Incomplete</option>
                    </select>
                </div>

                <div class="verif-form-group" id="modal_rejection_group" style="display: none;">
                    <label for="modal_rejection_reason" style="font-weight: 700; color: #DC2626;">Rejection Reason *</label>
                    <textarea name="rejection_reason" id="modal_rejection_reason" rows="3" class="verif-input" style="height: auto; padding: 10px;" placeholder="State clear reason for resident (e.g. invalid document, blurry photo, out of Palayan jurisdiction)..."></textarea>
                </div>
            </div>
            <div class="modal-box-footer">
                <button type="button" onclick="closeVerifyModal()" class="verif-btn-clear">Cancel</button>
                <button type="submit" name="update_verification" class="verif-btn-apply"><i class="fas fa-check"></i> Submit Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVerifyModal(id, name, status) {
    document.getElementById('modal_verification_id').value = id;
    document.getElementById('modal_applicant_name').textContent = name || 'Resident Applicant';
    document.getElementById('modal_request_id').textContent = '#' + String(id).padStart(4, '0');
    
    const statusSelect = document.getElementById('modal_verify_status');
    const rejGroup = document.getElementById('modal_rejection_group');
    const rejInput = document.getElementById('modal_rejection_reason');
    
    statusSelect.value = (status === 'rejected') ? 'rejected' : 'verified';
    if (status === 'rejected') {
        rejGroup.style.display = 'block';
        rejInput.required = true;
    } else {
        rejGroup.style.display = 'none';
        rejInput.required = false;
    }
    
    document.getElementById('staffVerifyModal').classList.add('active');
}

function closeVerifyModal() {
    document.getElementById('staffVerifyModal').classList.remove('active');
    document.getElementById('modal_rejection_reason').value = '';
}

document.getElementById('modal_verify_status')?.addEventListener('change', function() {
    const rejGroup = document.getElementById('modal_rejection_group');
    const rejInput = document.getElementById('modal_rejection_reason');
    if (this.value === 'rejected') {
        rejGroup.style.display = 'block';
        rejInput.required = true;
        rejInput.focus();
    } else {
        rejGroup.style.display = 'none';
        rejInput.required = false;
    }
});

// Close modal on outside click
window.addEventListener('click', function(e) {
    const modal = document.getElementById('staffVerifyModal');
    if (e.target === modal) {
        closeVerifyModal();
    }
});

// Close modal on Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVerifyModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>

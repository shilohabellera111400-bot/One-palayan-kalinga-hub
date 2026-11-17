<?php
// staff/view-verification.php - Staff Resident Verification Details & Document Inspector
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/SMSGateway.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$verification_id = intval($_GET['id'] ?? 0);
if ($verification_id <= 0) {
    redirect('manage-verifications.php');
}

// Fetch staff assignments for access checking
$staff_id_query = "SELECT s.id, s.position FROM staff s WHERE s.user_id = :uid";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->execute([':uid' => $_SESSION['user_id']]);
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? (int)$staff_info['id'] : 0;

$assigned_categories = [];
if ($staff_id > 0) {
    $cat_query = "SELECT DISTINCT sc.category_name 
                   FROM staff_service_assignments ssa
                   JOIN service_categories sc ON ssa.category_id = sc.category_id
                   WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute([':staff_id' => $staff_id]);
    $assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$has_verif_access = isAdmin() || empty($assigned_categories) || in_array('Verification', $assigned_categories) || in_array('Residency Verification', $assigned_categories);
if (!$has_verif_access) {
    $_SESSION['error'] = "You do not have access permission to view Residency Verifications.";
    redirect('../staff-dashboard.php');
}

$error = '';
$success = '';

// Handle quick decision from view page
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_verification'])) {
    $status = sanitize($_POST['status'] ?? '');
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');

    if (!in_array($status, ['verified', 'rejected'])) {
        $error = "Invalid verification status selected.";
    } elseif ($status === 'rejected' && empty(trim($rejection_reason))) {
        $error = "Please specify a reason for rejecting the residency verification.";
    } else {
        $db->beginTransaction();
        try {
            $get_user = "SELECT v.user_id, u.first_name, u.last_name, u.email, u.contact_number 
                         FROM verification_requests v 
                         JOIN users u ON v.user_id = u.user_id 
                         WHERE v.verification_id = :id";
            $get_stmt = $db->prepare($get_user);
            $get_stmt->execute([':id' => $verification_id]);
            $verif_data = $get_stmt->fetch(PDO::FETCH_ASSOC);

            if ($verif_data) {
                $update_query = "UPDATE verification_requests SET 
                                 status = :status, 
                                 reviewed_by = :reviewed_by, 
                                 review_date = NOW(),
                                 rejection_reason = :reason
                                 WHERE verification_id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    ':status' => $status,
                    ':reviewed_by' => $_SESSION['user_id'],
                    ':reason' => $rejection_reason,
                    ':id' => $verification_id
                ]);

                $user_stmt = $db->prepare("UPDATE users SET verification_status = :status, verification_date = NOW(), verified_by = :vby WHERE user_id = :uid");
                $user_stmt->execute([
                    ':status' => $status,
                    ':vby' => $_SESSION['user_id'],
                    ':uid' => $verif_data['user_id']
                ]);

                if ($status === 'verified') {
                    $check_age_stmt = $db->prepare("SELECT id, resident_number, birth_date, senior_citizen FROM residents WHERE user_id = :uid LIMIT 1");
                    $check_age_stmt->execute([':uid' => $verif_data['user_id']]);
                    $res_row = $check_age_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($res_row) {
                        if (empty($res_row['resident_number']) && function_exists('generateResidentNumber')) {
                            $new_res_num = generateResidentNumber($db);
                            $db->prepare("UPDATE residents SET resident_number = :rn WHERE id = :rid")
                               ->execute([':rn' => $new_res_num, ':rid' => $res_row['id']]);
                        }
                        if (!empty($res_row['birth_date']) && $res_row['birth_date'] !== '0000-00-00') {
                            $birth = new DateTime($res_row['birth_date']);
                            $now = new DateTime();
                            $age = $now->diff($birth)->y;
                            if ($age >= 60 && ($res_row['senior_citizen'] ?? 0) != 1) {
                                $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")
                                   ->execute([':uid' => $verif_data['user_id']]);
                            }
                        }
                    }
                }

                $notif_title = ($status === 'verified') ? 'Residency Verification Approved' : 'Residency Verification Needs Revision';
                $notif_message = ($status === 'verified')
                    ? 'Mabuhay! Ang inyong residency verification ay naaprubahan na. Maaari na kayong mag-access ng lahat ng online services sa One Palayan-Kalinga Hub.'
                    : 'Paunawa: Ang inyong residency verification ay hindi naaprubahan. Dahilan: ' . $rejection_reason . '. Maaari kayong magsumite muli ng tamang dokumento.';

                $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'verification')")
                   ->execute([
                       ':uid' => $verif_data['user_id'],
                       ':title' => $notif_title,
                       ':msg' => $notif_message
                   ]);

                $db->commit();

                // Dispatch notifications
                try {
                    $mailer = new Mailer();
                    $rname = trim(($verif_data['first_name'] ?? '') . ' ' . ($verif_data['last_name'] ?? '')) ?: 'Resident';
                    if (!empty($verif_data['email'])) {
                        if ($status === 'verified' && method_exists($mailer, 'sendVerificationApprovedEmail')) {
                            $mailer->sendVerificationApprovedEmail($verif_data['email'], $rname, (int)$verif_data['user_id']);
                        } elseif ($status === 'rejected' && method_exists($mailer, 'sendVerificationRejectedEmail')) {
                            $mailer->sendVerificationRejectedEmail($verif_data['email'], $rname, $rejection_reason, (int)$verif_data['user_id']);
                        }
                    }
                } catch (\Throwable $e) {}

                if (!empty($verif_data['contact_number'])) {
                    try {
                        $smsGateway = new SMSGateway();
                        $rname = trim(($verif_data['first_name'] ?? '') . ' ' . ($verif_data['last_name'] ?? '')) ?: 'Resident';
                        if ($status === 'verified' && method_exists($smsGateway, 'sendAccountVerifiedSMS')) {
                            $smsGateway->sendAccountVerifiedSMS($verif_data['contact_number'], $rname, (int)$verif_data['user_id']);
                        } elseif ($status === 'rejected' && method_exists($smsGateway, 'sendVerificationRejectedSMS')) {
                            $smsGateway->sendVerificationRejectedSMS($verif_data['contact_number'], $rname, $rejection_reason, (int)$verif_data['user_id']);
                        }
                    } catch (\Throwable $e) {}
                }

                $success = "Verification request #" . str_pad($verification_id, 4, '0', STR_PAD_LEFT) . " has been successfully updated!";
            }
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Failed to update verification: " . $e->getMessage();
        }
    }
}

// Fetch verification record
$query = "SELECT v.*, u.first_name, u.last_name, u.email, u.contact_number, u.address, u.profile_picture,
                 s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          LEFT JOIN staff s ON v.reviewed_by = s.user_id
          WHERE v.verification_id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $verification_id]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$verification) {
    redirect('manage-verifications.php?error=Record+not+found');
}

$applicant_name = trim(($verification['first_name'] ?? '') . ' ' . ($verification['last_name'] ?? ''));

$page_title = 'Verification #' . str_pad($verification_id, 4, '0', STR_PAD_LEFT) . ' - Staff Portal';
$active_nav = 'verifications';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
.verif-detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 22px;
}
@media (max-width: 900px) {
    .verif-detail-grid { grid-template-columns: 1fr; }
}

.verif-card-box {
    background: #FFFFFF;
    border-radius: 18px;
    padding: 22px 26px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    border: 1px solid #E2E8F0;
    margin-bottom: 22px;
}
[data-theme="dark"] .verif-card-box {
    background: #141B28;
    border-color: #232E42;
}

.verif-card-box-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid #F1F5F9;
    font-size: 1rem;
    font-weight: 800;
    color: #7F0303;
}
[data-theme="dark"] .verif-card-box-header {
    border-bottom-color: #232E42;
    color: #F87171;
}

.verif-info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #F8FAFC;
    font-size: 0.86rem;
}
[data-theme="dark"] .verif-info-row { border-bottom-color: #1E293B; }
.verif-info-label {
    width: 140px;
    font-weight: 700;
    color: #64748B;
    flex-shrink: 0;
}
.verif-info-value {
    flex: 1;
    font-weight: 600;
    color: #1E293B;
}
[data-theme="dark"] .verif-info-value { color: #F1F5F9; }

/* Attached Docs Grid */
.docs-preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-top: 14px;
}

.doc-preview-item {
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
[data-theme="dark"] .doc-preview-item {
    background: #0F1626;
    border-color: #232E42;
}

.doc-preview-title {
    padding: 10px 14px;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
    background: #F1F5F9;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 6px;
}
[data-theme="dark"] .doc-preview-title {
    background: #141B28;
    border-color: #232E42;
    color: #94A3B8;
}

.doc-preview-body {
    height: 180px;
    position: relative;
    cursor: pointer;
    overflow: hidden;
    background: #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.doc-preview-body img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.25s ease;
}
.doc-preview-body:hover img {
    transform: scale(1.05);
}

.doc-preview-actions {
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #FFFFFF;
    border-top: 1px solid #E2E8F0;
}
[data-theme="dark"] .doc-preview-actions {
    background: #0F1626;
    border-color: #232E42;
}

.btn-doc-link {
    font-size: 0.74rem;
    font-weight: 700;
    color: #7F0303;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-doc-link:hover { text-decoration: underline; }

/* Lightbox Modal */
.lightbox-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(5px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 3000;
    padding: 20px;
}
.lightbox-modal.active { display: flex; }
.lightbox-container {
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.lightbox-container img {
    max-width: 100%;
    max-height: 80vh;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}
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

        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "Resident Verification #" . str_pad($verification['verification_id'], 4, '0', STR_PAD_LEFT);
            $banner_subtitle = "Review documents, evaluate residency eligibility, and record official verification decision";
            $banner_icon = "fa-shield-alt";
            $banner_actions = [
                ["label" => "Back to Verifications", "url" => "manage-verifications.php", "icon" => "fa-arrow-left", "type" => "secondary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

            <!-- Success / Error Alerts -->
            <?php if (!empty($success)): ?>
                <div style="background: #D1FAE5; border: 1.5px solid #10B981; color: #065F46; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-check-circle" style="color: #059669; margin-right: 8px;"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div style="background: #FEE2E2; border: 1.5px solid #EF4444; color: #991B1B; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-exclamation-circle" style="color: #DC2626; margin-right: 8px;"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="verif-detail-grid">
                <!-- Left Column -->
                <div>
                    <!-- Applicant Card -->
                    <div class="verif-card-box">
                        <div class="verif-card-box-header">
                            <i class="fas fa-user-circle"></i>
                            <span>Resident Information</span>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">Applicant Name</div>
                            <div class="verif-info-value" style="font-size: 1rem; color: #7F0303;"><?php echo htmlspecialchars($applicant_name); ?></div>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">Email Address</div>
                            <div class="verif-info-value"><?php echo htmlspecialchars($verification['email']); ?></div>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">Contact Number</div>
                            <div class="verif-info-value"><?php echo htmlspecialchars($verification['contact_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">Barangay</div>
                            <div class="verif-info-value"><?php echo htmlspecialchars($verification['barangay_name']); ?></div>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">Street / Address</div>
                            <div class="verif-info-value"><?php echo nl2br(htmlspecialchars($verification['complete_address'] ?? $verification['address'] ?? 'N/A')); ?></div>
                        </div>
                        <div class="verif-info-row">
                            <div class="verif-info-label">ID Type &amp; Number</div>
                            <div class="verif-info-value"><?php echo htmlspecialchars($verification['id_type']); ?> &mdash; <?php echo htmlspecialchars($verification['id_number']); ?></div>
                        </div>
                    </div>

                    <!-- Submitted Documents Preview Card -->
                    <div class="verif-card-box">
                        <div class="verif-card-box-header">
                            <i class="fas fa-images"></i>
                            <span>Attached Verification Documents</span>
                        </div>
                        <p style="font-size: 0.8rem; color: #64748B; margin-bottom: 12px;">Click any document image below to inspect in high resolution.</p>

                        <div class="docs-preview-grid">
                            <!-- Valid ID Front -->
                            <?php if (!empty($verification['id_file_path'])): ?>
                            <div class="doc-preview-item">
                                <div class="doc-preview-title"><i class="fas fa-id-card"></i> Valid ID Front</div>
                                <div class="doc-preview-body" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['id_file_path']); ?>')">
                                    <img src="../uploads/verification/<?php echo basename($verification['id_file_path']); ?>" alt="Valid ID Front" onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="doc-preview-actions">
                                    <button type="button" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['id_file_path']); ?>')" class="btn-doc-link"><i class="fas fa-search-plus"></i> Zoom</button>
                                    <a href="../uploads/verification/<?php echo basename($verification['id_file_path']); ?>" target="_blank" download class="btn-doc-link"><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Valid ID Back -->
                            <?php if (!empty($verification['id_back_file_path'])): ?>
                            <div class="doc-preview-item">
                                <div class="doc-preview-title"><i class="fas fa-id-card-alt"></i> Valid ID Back</div>
                                <div class="doc-preview-body" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>')">
                                    <img src="../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>" alt="Valid ID Back" onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="doc-preview-actions">
                                    <button type="button" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>')" class="btn-doc-link"><i class="fas fa-search-plus"></i> Zoom</button>
                                    <a href="../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>" target="_blank" download class="btn-doc-link"><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Proof of Residency -->
                            <?php if (!empty($verification['proof_of_residency_path'])): ?>
                            <div class="doc-preview-item">
                                <div class="doc-preview-title"><i class="fas fa-file-contract"></i> Proof of Residency</div>
                                <div class="doc-preview-body" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>')">
                                    <img src="../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>" alt="Proof of Residency" onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="doc-preview-actions">
                                    <button type="button" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>')" class="btn-doc-link"><i class="fas fa-search-plus"></i> Zoom</button>
                                    <a href="../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>" target="_blank" download class="btn-doc-link"><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Barangay Certificate -->
                            <?php if (!empty($verification['barangay_certificate_path'])): ?>
                            <div class="doc-preview-item">
                                <div class="doc-preview-title"><i class="fas fa-certificate"></i> Barangay Certificate</div>
                                <div class="doc-preview-body" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>')">
                                    <img src="../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>" alt="Barangay Certificate" onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="doc-preview-actions">
                                    <button type="button" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>')" class="btn-doc-link"><i class="fas fa-search-plus"></i> Zoom</button>
                                    <a href="../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>" target="_blank" download class="btn-doc-link"><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 2x2 or Selfie Photo -->
                            <?php if (!empty($verification['selfie_path'])): ?>
                            <div class="doc-preview-item">
                                <div class="doc-preview-title"><i class="fas fa-portrait"></i> Selfie / Photo</div>
                                <div class="doc-preview-body" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['selfie_path']); ?>')">
                                    <img src="../uploads/verification/<?php echo basename($verification['selfie_path']); ?>" alt="Selfie Photo" onerror="this.src='../images/logo.png'">
                                </div>
                                <div class="doc-preview-actions">
                                    <button type="button" onclick="openLightbox('../uploads/verification/<?php echo basename($verification['selfie_path']); ?>')" class="btn-doc-link"><i class="fas fa-search-plus"></i> Zoom</button>
                                    <a href="../uploads/verification/<?php echo basename($verification['selfie_path']); ?>" target="_blank" download class="btn-doc-link"><i class="fas fa-download"></i> Download</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Status & Action Panel -->
                <div>
                    <!-- Decision Box -->
                    <div class="verif-card-box">
                        <div class="verif-card-box-header">
                            <i class="fas fa-gavel"></i>
                            <span>Caseworker Action</span>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <span style="font-size: 0.72rem; text-transform: uppercase; color: #64748B; font-weight: 700; display: block; margin-bottom: 4px;">Current Status</span>
                            <?php if ($verification['status'] === 'pending'): ?>
                                <span style="background: #FEF3C7; color: #B45309; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-clock"></i> Pending Review
                                </span>
                            <?php elseif ($verification['status'] === 'verified'): ?>
                                <span style="background: #D1FAE5; color: #047857; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-check-circle"></i> Verified Resident
                                </span>
                            <?php else: ?>
                                <span style="background: #FEE2E2; color: #B91C1C; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($verification['rejection_reason'])): ?>
                            <div style="background: #FEF2F2; border-left: 4px solid #DC2626; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 0.82rem; color: #991B1B;">
                                <strong>Rejection Reason:</strong><br>
                                <?php echo htmlspecialchars($verification['rejection_reason']); ?>
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 0.76rem; color: #64748B; margin-bottom: 18px; line-height: 1.4;">
                            <div><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($verification['submitted_date'])); ?></div>
                            <?php if (!empty($verification['review_date'])): ?>
                                <div><strong>Reviewed:</strong> <?php echo date('M d, Y h:i A', strtotime($verification['review_date'])); ?></div>
                                <?php if (!empty($verification['staff_first_name'])): ?>
                                    <div><strong>Reviewed By:</strong> <?php echo htmlspecialchars($verification['staff_first_name'] . ' ' . $verification['staff_last_name']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Update Status Form -->
                        <form method="POST">
                            <div style="margin-bottom: 14px;">
                                <label for="verif_status_select" style="display: block; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; color: #475569;">Change Decision</label>
                                <select name="status" id="verif_status_select" style="width: 100%; height: 42px; border-radius: 8px; border: 1.5px solid #CBD5E1; padding: 0 12px; font-weight: 700; font-size: 0.86rem;" required>
                                    <option value="verified" <?php echo ($verification['status'] === 'verified') ? 'selected' : ''; ?>>✓ Approve - Verified Resident</option>
                                    <option value="rejected" <?php echo ($verification['status'] === 'rejected') ? 'selected' : ''; ?>>✕ Reject - Incomplete / Disapproved</option>
                                </select>
                            </div>

                            <div id="rejection_box" style="<?php echo ($verification['status'] === 'rejected') ? 'display:block;' : 'display:none;'; ?> margin-bottom: 16px;">
                                <label for="rejection_reason_input" style="display: block; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; color: #DC2626;">Rejection Reason *</label>
                                <textarea name="rejection_reason" id="rejection_reason_input" rows="3" style="width: 100%; border-radius: 8px; border: 1.5px solid #FCA5A5; padding: 10px; font-size: 0.84rem;" placeholder="State reason for rejection..."><?php echo htmlspecialchars($verification['rejection_reason'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" name="update_verification" style="width: 100%; height: 44px; border-radius: 8px; background: #7F0303; color: #FFFFFF; font-weight: 700; font-size: 0.9rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="fas fa-save"></i> Save Decision
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Lightbox Modal -->
<div id="imageLightbox" class="lightbox-modal" onclick="closeLightbox()">
    <div class="lightbox-container" onclick="event.stopPropagation()">
        <img id="lightboxImg" src="" alt="Enlarged Document">
        <div style="margin-top: 12px;">
            <button type="button" onclick="closeLightbox()" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 18px; border-radius: 50px; cursor: pointer; font-weight: 700;">
                <i class="fas fa-times"></i> Close Inspector
            </button>
        </div>
    </div>
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imageLightbox').classList.add('active');
}
function closeLightbox() {
    document.getElementById('imageLightbox').classList.remove('active');
}
document.getElementById('verif_status_select')?.addEventListener('change', function() {
    const rejBox = document.getElementById('rejection_box');
    const rejInp = document.getElementById('rejection_reason_input');
    if (this.value === 'rejected') {
        rejBox.style.display = 'block';
        rejInp.required = true;
        rejInp.focus();
    } else {
        rejBox.style.display = 'none';
        rejInp.required = false;
    }
});
</script>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>

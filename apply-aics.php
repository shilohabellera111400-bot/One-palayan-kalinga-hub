<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';
require_once __DIR__ . '/includes/rate-limit.php';
require_once __DIR__ . '/includes/aics-audit-helper.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rate limit: max 3 AICS submissions per 10 minutes
    checkRateLimit((int)$_SESSION['user_id'], 'aics_submit', 3, 10);
    csrf_verify();
    $request_type = sanitize($_POST['request_type']);
    $amount_requested = sanitize($_POST['amount_requested']);
    $purpose = sanitize($_POST['purpose']);

    // Validate inputs
    if (empty($request_type) || empty($amount_requested) || empty($purpose)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($amount_requested) || $amount_requested < 100) {
        $error = "Please enter a valid amount (minimum ₱100).";
    } else {
        // Check for active pending request
        $check_query = "SELECT aics_id FROM aics_requests 
                        WHERE user_id = :user_id AND request_type = :type 
                        AND status IN ('submitted', 'reviewing', 'approved')
                        ORDER BY submission_date DESC LIMIT 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $check_stmt->bindParam(':type', $request_type);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $error = "You already have an active/pending application for this type of assistance.";
        } else {
            // Start transaction
            $db->beginTransaction();

            try {
                $is_exception = isset($_POST['is_emergency_exception']) ? 1 : 0;
                // Insert AICS request
                $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, purpose, status, is_frequency_exception) 
                          VALUES (:user_id, :request_type, :amount, :purpose, 'submitted', :is_exception)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':request_type', $request_type);
                $stmt->bindParam(':amount', $amount_requested);
                $stmt->bindParam(':purpose', $purpose);
                $stmt->bindParam(':is_exception', $is_exception, PDO::PARAM_INT);
                $stmt->execute();

                $aics_id = $db->lastInsertId();

                // Handle document uploads
                if (!empty($_FILES['documents']['name'][0])) {
                    $uploader = new FileUploader();
                    $files = $_FILES['documents'];

                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];

                            $file_path = $uploader->upload($file, 'aics');

                            // Save document reference
                            $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_path) 
                                      VALUES (:aics_id, 'supporting_document', :path)";
                            $doc_stmt = $db->prepare($doc_query);
                            $doc_stmt->bindParam(':aics_id', $aics_id);
                            $doc_stmt->bindParam(':path', $file_path);
                            $doc_stmt->execute();
                        }
                    }
                }

                // Create notification
                $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
                $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'AICS Application Submitted', 
                            'Your AICS application has been submitted. Tracking number: " . $tracking . "', 'aics')";
                $notif_stmt = $db->prepare($notif_query);
                $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $notif_stmt->execute();

                $db->commit();

                $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
                $success = "AICS application submitted successfully! Your tracking number is: <strong>" . $tracking . "</strong>";
                $submission_success = true;
                $success_tracking_no = $tracking;
                $success_service_type = 'AICS';

                // Send email confirmation
                try {
                    require_once __DIR__ . '/includes/Mailer.php';
                    $mailer = new Mailer();
                    $user_query = "SELECT email, first_name, last_name, contact_number FROM users WHERE user_id = :user_id";
                    $user_stmt = $db->prepare($user_query);
                    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $user_stmt->execute();
                    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user_info) {
                        $user_name = trim($user_info['first_name'] . ' ' . $user_info['last_name']);
                        $aics_data = [
                            'aics_id' => $aics_id,
                            'request_type' => $request_type,
                            'user_id' => $_SESSION['user_id']
                        ];

                        if (!empty($user_info['email'])) {
                            $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
                        }

                        if (!empty($user_info['contact_number'])) {
                            try {
                                require_once __DIR__ . '/includes/SMSGateway.php';
                                $smsGateway = new SMSGateway();
                                $smsGateway->sendAICSConfirmation($user_info['contact_number'], $user_name, $aics_data);
                            } catch (Throwable $sms_err) {
                                error_log("AICS application SMS confirmation failed: " . $sms_err->getMessage());
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("AICS apply general submission email confirmation failed: " . $e->getMessage());
                }

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Application failed: " . $e->getMessage();
            }
        }
    }
}

// Get user's recent AICS requests for display
$recent_query = "SELECT * FROM aics_requests 
                 WHERE user_id = :user_id 
                 ORDER BY submission_date DESC 
                 LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bindParam(':user_id', $_SESSION['user_id']);
$recent_stmt->execute();

// Check 90-day assistance frequency status for applicant
$resident_audit = checkAicsFrequency($db, (int)$_SESSION['user_id']);

// Fetch resident profile for Section 1: Applicant Details
$res_query = "SELECT r.*, u.email, u.first_name, u.last_name, u.contact_number as u_contact, u.verification_status 
              FROM users u 
              LEFT JOIN residents r ON u.user_id = r.user_id 
              WHERE u.user_id = :uid";
$res_stmt = $db->prepare($res_query);
$res_stmt->execute([':uid' => $_SESSION['user_id']]);
$resident_profile = $res_stmt->fetch(PDO::FETCH_ASSOC);

$pre_type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for AICS - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Apply for Crisis Assistance (AICS)';
            $banner_subtitle = 'Submit online intake forms and requirements for urgent financial and medical relief';
            $banner_icon = 'fa-file-medical';
            $banner_actions = [
                ['label' => 'My AICS Requests', 'url' => 'aics-requests.php', 'icon' => 'fa-list-check', 'type' => 'primary'],
                ['label' => 'AICS Programs', 'url' => 'aics.php', 'icon' => 'fa-hand-holding-heart', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!empty($resident_audit['is_flagged'])): ?>
                <!-- 90-Day Policy Advisory Alert -->
                <div class="audit-frequency-advisory" style="background: rgba(245, 158, 11, 0.08); border: 1.5px solid #F59E0B; border-radius: 14px; padding: 18px 22px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.03);">
                    <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(245, 158, 11, 0.2); color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                        <i class="fas fa-history"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
                            <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: #B45309; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-info-circle"></i> Paalala sa 90-Day Assistance Frequency Policy
                            </h4>
                            <span style="font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: rgba(245, 158, 11, 0.2); color: #B45309;">
                                Nabigyan <?php echo $resident_audit['days_elapsed']; ?> Araw ang Nakalipas
                            </span>
                        </div>
                        <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: var(--ref-text, #1F2937); line-height: 1.55;">
                            Ayon sa talaan ng CSWDO, kayo ay nabigyan ng <strong><?php echo ucwords(str_replace('_', ' ', $resident_audit['recent_assistance']['request_type'])); ?> Assistance</strong> noong <strong><?php echo date('F d, Y', strtotime($resident_audit['recent_assistance']['effective_date'])); ?></strong>.
                            Ayon sa alituntunin ng CSWDO at DSWD (MC 15 S. 2022), ang regular na tulong pinansyal ay may pamantayang pagitan na <strong>90 araw</strong> (may natitira pang <strong><?php echo $resident_audit['days_remaining']; ?> araw</strong> bago ang susunod na iskedyul sa <strong><?php echo $resident_audit['eligible_date']; ?></strong>).
                        </p>
                        <div style="background: rgba(255, 255, 255, 0.7); border-left: 3px solid #D97706; padding: 10px 14px; border-radius: 6px; font-size: 0.78rem; color: var(--ref-muted, #4B5563); line-height: 1.5;">
                            <strong>Kailangan ba ng tulong para sa patuloy na medikal na emerhensiya o sakuna?</strong><br>
                            Kung ang inyong aplikasyon ay para sa <strong>patuloy at kritikal na gamutan</strong> (hal. hemodialysis, chemotherapy, agarang operasyon) o kalamidad, <em>maaari pa rin po kayong magsumite</em>. Mangyaring ipaliwanag ito nang maayos sa inyong Purpose at ilakip ang pinakabagong Clinical Abstract/Medical Certificate upang ma-assess ng Social Worker para sa policy exception.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="application-form">
                <form method="POST" enctype="multipart/form-data" id="aicsForm" onsubmit="return validateForm()">
                    <?php echo csrf_field(); ?>

                    <!-- Group 1: Applicant Details -->
                    <div class="form-grouped-card">
                        <div class="group-header">
                            <div class="group-header-left">
                                <span class="group-step-num">1</span>
                                <div>
                                    <h3 class="group-title">Applicant Profile &amp; Residency Details</h3>
                                    <p class="group-desc">Verified resident records retrieved from Palayan City Civil Registry</p>
                                </div>
                            </div>
                            <span class="badge-status-pill verified">
                                <i class="fas fa-shield-alt"></i> Verified Resident
                            </span>
                        </div>
                        <div class="applicant-details-grid">
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-id-card"></i> Resident ID</span>
                                <span class="field-val"><?php echo htmlspecialchars($resident_profile['resident_number'] ?? ('RES-' . str_pad($_SESSION['user_id'], 4, '0', STR_PAD_LEFT))); ?></span>
                            </div>
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-user"></i> Full Legal Name</span>
                                <span class="field-val"><?php echo htmlspecialchars(trim(($resident_profile['first_name'] ?? $_SESSION['full_name']) . ' ' . ($resident_profile['last_name'] ?? ''))); ?></span>
                            </div>
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-map-marker-alt"></i> Barangay Jurisdiction</span>
                                <span class="field-val"><?php echo htmlspecialchars($resident_profile['barangay'] ?? 'Palayan City'); ?></span>
                            </div>
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-phone"></i> Registered Mobile</span>
                                <span class="field-val"><?php echo htmlspecialchars($resident_profile['contact_number'] ?? ($resident_profile['u_contact'] ?? 'N/A')); ?></span>
                            </div>
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-tags"></i> Sectoral Classification</span>
                                <div class="sectoral-badges-wrap">
                                    <?php if (!empty($resident_profile['senior_citizen'])): ?>
                                        <span class="badge-tag-pastel tag-senior"><i class="fas fa-user-clock"></i> Senior Citizen</span>
                                    <?php endif; ?>
                                    <?php if (!empty($resident_profile['pwd'])): ?>
                                        <span class="badge-tag-pastel tag-pwd"><i class="fas fa-wheelchair"></i> PWD Beneficiary</span>
                                    <?php endif; ?>
                                    <?php if (empty($resident_profile['senior_citizen']) && empty($resident_profile['pwd'])): ?>
                                        <span class="badge-tag-pastel tag-gen"><i class="fas fa-user"></i> General Resident</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="applicant-field">
                                <span class="field-lbl"><i class="fas fa-envelope"></i> Email Address</span>
                                <span class="field-val"><?php echo htmlspecialchars($resident_profile['email'] ?? 'Not registered'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: Assistance Category & Amount -->
                    <div class="form-grouped-card">
                        <div class="group-header">
                            <div class="group-header-left">
                                <span class="group-step-num">2</span>
                                <div>
                                    <h3 class="group-title">Assistance Category &amp; Claim Details</h3>
                                    <p class="group-desc">Select the specific crisis assistance category and specify the required relief</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="request_type" style="font-weight: 700; font-size: 0.85rem; color: var(--ref-text);">Assistance Category *</label>
                            <select id="request_type" name="request_type" required onchange="showDynamicRequirements()" class="ref-input-styled">
                                <option value="">-- Choose Type of Crisis Assistance --</option>
                                <option value="medical" <?php echo ($pre_type === 'medical') ? 'selected' : ''; ?>>🏥 Medical Assistance (Hospital bills, diagnostics, prescription)</option>
                                <option value="funeral" <?php echo ($pre_type === 'funeral') ? 'selected' : ''; ?>>⚰️ Burial / Funeral Assistance (Expenses for deceased relative)</option>
                                <option value="cash_relief" <?php echo ($pre_type === 'cash_relief') ? 'selected' : ''; ?>>💵 Financial Assistance (Emergency Cash Relief)</option>
                                <option value="food" <?php echo ($pre_type === 'food') ? 'selected' : ''; ?>>🍲 Food Assistance (Subsistence relief in crisis)</option>
                                <option value="transportation" <?php echo ($pre_type === 'transportation') ? 'selected' : ''; ?>>🚌 Transportation Assistance (Stranded fare, medical travel)</option>
                            </select>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label for="amount_requested" style="font-weight: 700; font-size: 0.85rem; color: var(--ref-text);">Estimated Amount Requested (₱) *</label>
                                <div class="currency-input-wrap">
                                    <span class="currency-prefix">₱</span>
                                    <input type="number" id="amount_requested" name="amount_requested" min="100" max="100000"
                                        step="100" placeholder="e.g. 5000" required class="ref-input-styled with-prefix">
                                </div>
                                <small style="color: var(--ref-muted); font-size: 0.75rem; margin-top: 4px;">Standard CSWDO assessment applies (Min: ₱100 &bull; Max: ₱100,000)</small>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 700; font-size: 0.85rem; color: var(--ref-text);">Processing Standard</label>
                                <div class="processing-hint-box">
                                    <i class="fas fa-stopwatch" style="color: #059669;"></i>
                                    <span>3-5 Working Days evaluation &amp; social worker assessment</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 14px;">
                            <label for="purpose" style="font-weight: 700; font-size: 0.85rem; color: var(--ref-text);">Statement of Crisis / Purpose *</label>
                            <textarea id="purpose" name="purpose" rows="4" class="ref-input-styled"
                                placeholder="Describe the crisis situation, emergency condition, and why financial/material assistance is urgently required..."
                                required></textarea>
                        </div>
                    </div>

                    <!-- Group 3: Documentary Requirements -->
                    <div class="form-grouped-card">
                        <div class="group-header">
                            <div class="group-header-left">
                                <span class="group-step-num">3</span>
                                <div>
                                    <h3 class="group-title">Documentary Requirements &amp; Attachments</h3>
                                    <p class="group-desc">Upload supporting documents corresponding to the selected category</p>
                                </div>
                            </div>
                        </div>

                        <!-- Requirements Checklist Dynamic Box -->
                        <div class="requirements-checklist-card" id="requirementsChecklistBox">
                            <h4>📋 Requirements Checklist</h4>
                            <p style="color: var(--ref-muted); font-size: 0.85rem; margin: 0;">Please select an Assistance Category above to see specific documentary requirements.</p>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label for="documents" style="font-weight: 700; font-size: 0.85rem; color: var(--ref-text);">Upload File Attachments</label>
                            <div class="upload-dropzone">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <p class="upload-title">Click or drag files here to attach</p>
                                <span class="upload-hint">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 5MB each)</span>
                                <input type="file" id="documents" name="documents[]" multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileList()" class="file-input-hidden">
                            </div>
                            <div id="file-list" class="file-list-chips"></div>
                        </div>
                    </div>

                    <!-- Group 4: Declarations & Agreement -->
                    <div class="form-grouped-card">
                        <div class="group-header">
                            <div class="group-header-left">
                                <span class="group-step-num">4</span>
                                <div>
                                    <h3 class="group-title">Applicant Declarations &amp; Certification</h3>
                                    <p class="group-desc">Read and verify the statements below before final submission</p>
                                </div>
                            </div>
                        </div>

                        <div class="declarations-list">
                            <label class="declaration-item">
                                <input type="checkbox" required name="confirm_truth" value="1" class="ref-checkbox">
                                <span>I declare under the penalty of perjury that all information provided in this application is true, correct, and submitted in good faith for government assistance.</span>
                            </label>
                            <label class="declaration-item">
                                <input type="checkbox" required name="confirm_consent" value="1" class="ref-checkbox">
                                <span>I authorize the City Social Welfare and Development Office (CSWDO) of Palayan City to verify and validate the submitted documentary requirements.</span>
                            </label>
                            <?php if (!empty($resident_audit['is_flagged'])): ?>
                            <label class="declaration-item emergency-exception-box">
                                <input type="checkbox" name="is_emergency_exception" value="1" required class="ref-checkbox" style="accent-color: #DC2626;">
                                <span style="color: #991B1B; font-weight: 600;">
                                    <strong>Emergency Exception Request:</strong> Pinatutunayan ko na ang kahilingang ito ay para sa isang <strong>patuloy na kritikal na medikal na gamutan o agarang emerhensiya</strong> alinsunod sa DSWD emergency exception guidelines.
                                </span>
                            </label>
                            <?php endif; ?>
                        </div>

                        <div class="form-submit-actions">
                            <button type="submit" class="btn-submit-aics">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                            <a href="aics.php" class="btn-cancel-aics">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Applications -->
            <?php if ($recent_stmt->rowCount() > 0): ?>
                <div class="recent-section">
                    <h3>Your Recent Applications</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $recent_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td>AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo ucfirst($request['request_type']); ?></td>
                                    <td>₱<?php echo number_format($request['amount_requested'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="track-aics.php?tracking=AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?>"
                                            class="btn-small">Track</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateFileList() {
            const files = document.getElementById('documents').files;
            const fileList = document.getElementById('file-list');

            if (files.length > 0) {
                let html = '<h4>Selected Files:</h4><ul>';
                for (let i = 0; i < files.length; i++) {
                    html += `<li>${files[i].name} (${(files[i].size / 1024).toFixed(2)} KB)</li>`;
                }
                html += '</ul>';
                fileList.innerHTML = html;
            } else {
                fileList.innerHTML = '';
            }
        }

        function validateForm() {
            const requestType = document.getElementById('request_type').value;
            const amount = document.getElementById('amount_requested').value;
            const purpose = document.getElementById('purpose').value;

            if (!requestType || !amount || !purpose) {
                alert('Please fill in all required fields.');
                return false;
            }

            if (amount < 100) {
                alert('Amount must be at least ₱100.');
                return false;
            }

            return confirm('Are you sure you want to submit this application?');
        }

        function showDynamicRequirements() {
            const requestType = document.getElementById('request_type').value;
            const listBox = document.getElementById('requirementsChecklistBox');
            
            const requirements = {
                funeral: `
                    <h4>📋 Burial Assistance Requirements Checklist</h4>
                    <p style="margin-bottom: 10px; font-weight: 600; color: #ff6600;">Fee Summary: None (Free of Charge)</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong>Certified True Copy of relative's registered death certificate</strong><br><small>Secure from: Client / City Civil Registrar’s Office</small></li>
                        <li><strong>Original Barangay Certificate of Residency and Indigency</strong><br><small>Secure from: Barangay Hall of the Community where the client resides</small></li>
                        <li><strong>Photocopy of Valid ID of the client</strong><br><small>Secure from: Client</small></li>
                        <li><strong>Original Funeral Contract (Unpaid)</strong><br><small>Secure from: Funeral Parlour where funeral service was acquired</small></li>
                        <li><strong>Written letter of request</strong><br><small>Secure from: Client</small></li>
                    </ul>
                `,
                food: `
                    <h4>📋 Food Assistance Requirements Checklist</h4>
                    <p style="margin-bottom: 10px; font-weight: 600; color: #ff6600;">Fee Summary: None (Free of Charge)</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong>Original Barangay Certificate of Residency and Indigency</strong><br><small>Secure from: Barangay where the client resides</small></li>
                        <li><strong>Photocopy of Valid ID of the client</strong><br><small>Secure from: Client</small></li>
                        <li><strong>Request letter</strong><br><small>Secure from: Client</small></li>
                    </ul>
                `,
                transportation: `
                    <h4>📋 Transportation Assistance Requirements Checklist</h4>
                    <p style="margin-bottom: 10px; font-weight: 600; color: #ff6600;">Fee Summary: None (Free of Charge)</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong>Original Barangay Certificate of Residency and Indigency</strong><br><small>Secure from: Barangay where the client resides</small></li>
                        <li><strong>Photocopy of Valid ID of the client</strong><br><small>Secure from: Client</small></li>
                        <li><strong>Request letter</strong><br><small>Secure from: Client</small></li>
                    </ul>
                `,
                medical: `
                    <h4>📋 Medical Assistance Requirements Checklist</h4>
                    <p style="margin-bottom: 10px; font-weight: 600; color: #ff6600;">Fee Summary: None (Free of Charge)</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong>Original Barangay Certificate of Residency and Indigency</strong><br><small>Secure from: Barangay where the client resides</small></li>
                        <li><strong>Photocopy of Valid ID of the client</strong><br><small>Secure from: Client</small></li>
                        <li><strong>Doctor’s prescription (for medicines or laboratory request)</strong><br><small>Secure from: Attending Physician</small></li>
                        <li><strong>Medical Abstract / Medical Certificate</strong><br><small>Secure from: Attending Physician</small></li>
                        <li><strong>Hospital Bill (for payment of hospital bill)</strong><br><small>Secure from: Hospital where the client was admitted</small></li>
                        <li><strong>Request letter</strong><br><small>Secure from: Client</small></li>
                    </ul>
                `,
                cash_relief: `
                    <h4>📋 Financial Assistance (Cash Relief) Requirements Checklist</h4>
                    <p style="margin-bottom: 10px; font-weight: 600; color: #ff6600;">Fee Summary: None (Free of Charge)</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li><strong>Original Barangay Certificate of Residency and Indigency</strong><br><small>Secure from: Barangay where the client resides</small></li>
                        <li><strong>Photocopy of Valid ID of the client</strong><br><small>Secure from: Client</small></li>
                        <li><strong>Doctor’s prescription (for medicines or laboratory request)</strong><br><small>Secure from: Attending Physician</small></li>
                        <li><strong>Medical Abstract / Medical Certificate</strong><br><small>Secure from: Attending Physician</small></li>
                        <li><strong>Hospital Bill (for payment of hospital bill)</strong><br><small>Secure from: Hospital where the client was admitted</small></li>
                        <li><strong>Request letter</strong><br><small>Secure from: Client</small></li>
                    </ul>
                `
            };
            
            if (requirements[requestType]) {
                listBox.innerHTML = requirements[requestType];
            } else {
                listBox.innerHTML = `
                    <h4>📋 Requirements Checklist</h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Please select a Type of Assistance above to view the required supporting documents.</p>
                `;
            }
        }

        document.addEventListener('DOMContentLoaded', showDynamicRequirements);
    </script>

    <style>
        .application-form {
            display: flex;
            flex-direction: column;
            gap: 22px;
            margin-bottom: 30px;
        }

        .form-grouped-card {
            background: var(--ref-card, #FFFFFF);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            transition: border-color 0.2s ease;
        }

        .form-grouped-card:focus-within {
            border-color: rgba(127, 3, 3, 0.25);
        }

        .group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            padding-bottom: 14px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .group-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .group-step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #7F0303;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.86rem;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .group-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--ref-text, #191D1E);
            margin: 0;
        }

        .group-desc {
            font-size: 0.74rem;
            color: var(--ref-muted, #71818B);
            margin: 2px 0 0;
        }

        /* Group 1: Applicant Details Grid */
        .applicant-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .applicant-field {
            background: var(--ref-canvas, #E4EFE7);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.06));
        }

        .field-lbl {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--ref-muted, #71818B);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .field-val {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ref-text, #191D1E);
            word-break: break-word;
        }

        .badge-status-pill.verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            font-size: 0.74rem;
            font-weight: 800;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .sectoral-badges-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .badge-tag-pastel {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .tag-senior { background: #FEF3C7; color: #B45309; }
        .tag-pwd { background: #EDE9FE; color: #6D28D9; }
        .tag-gen { background: #E0E7FF; color: #3730A3; }

        /* Group 2: Form Inputs */
        .form-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .ref-input-styled {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.12));
            background: var(--ref-canvas, #E4EFE7);
            color: var(--ref-text, #191D1E);
            font-family: inherit;
            font-size: 0.88rem;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .ref-input-styled:focus {
            outline: none;
            border-color: #7F0303;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
        }

        .currency-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .currency-prefix {
            position: absolute;
            left: 14px;
            font-weight: 800;
            color: #7F0303;
            font-size: 1rem;
            pointer-events: none;
        }

        .ref-input-styled.with-prefix {
            padding-left: 32px;
            font-weight: 700;
        }

        .processing-hint-box {
            background: rgba(16, 185, 129, 0.08);
            border: 1px dashed #10B981;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #065F46;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Group 3: Dynamic Checklist */
        .requirements-checklist-card {
            background: rgba(127, 3, 3, 0.04);
            border-left: 4px solid #7F0303;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .requirements-checklist-card h4 {
            margin: 0 0 8px;
            font-size: 0.92rem;
            font-weight: 800;
            color: #7F0303;
        }

        .upload-dropzone {
            border: 2px dashed rgba(127, 3, 3, 0.25);
            background: var(--ref-canvas, #E4EFE7);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .upload-dropzone:hover {
            border-color: #7F0303;
            background: rgba(127, 3, 3, 0.02);
        }

        .upload-icon {
            font-size: 2rem;
            color: #7F0303;
            margin-bottom: 8px;
        }

        .upload-title {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--ref-text, #191D1E);
            margin: 0 0 4px;
        }

        .upload-hint {
            font-size: 0.72rem;
            color: var(--ref-muted, #71818B);
        }

        .file-input-hidden {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .file-list-chips {
            margin-top: 12px;
        }

        /* Group 4: Declarations */
        .declarations-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 22px;
        }

        .declaration-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: var(--ref-canvas, #E4EFE7);
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.82rem;
            color: var(--ref-text, #191D1E);
            line-height: 1.45;
            cursor: pointer;
        }

        .ref-checkbox {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: #7F0303;
            flex-shrink: 0;
        }

        .form-submit-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-submit-aics {
            background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%);
            color: #FFFFFF;
            border: none;
            padding: 12px 28px;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3);
            transition: all 0.2s ease;
        }

        .btn-submit-aics:hover {
            background: linear-gradient(135deg, #6B0202 0%, #850A0A 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.4);
        }

        .btn-cancel-aics {
            background: var(--ref-canvas, #E4EFE7);
            color: var(--ref-muted, #71818B);
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.1));
            padding: 11px 22px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-cancel-aics:hover {
            color: var(--ref-text, #191D1E);
            background: #D9E6DC;
        }

        .recent-section {
            background: var(--ref-card, #FFFFFF);
            padding: 24px;
            border-radius: 20px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
        }

        .recent-section h3 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--ref-text);
            margin: 0 0 16px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECDD3;
        }

        .alert-success {
            background: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        @media (max-width: 768px) {
            .form-row-2col {
                grid-template-columns: 1fr;
            }
            .form-submit-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-submit-aics, .btn-cancel-aics {
                justify-content: center;
            }
        }
    </style>

    <script src="js/main.js"></script>
    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
    <!-- Succession Modal -->
    <?php include __DIR__ . '/includes/submission-success-modal.php'; ?>
</body>

</html>
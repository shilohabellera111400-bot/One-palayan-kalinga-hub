<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

$check_query = "SELECT r.* FROM aics_requests r 
                WHERE r.user_id = :user_id AND r.request_type = 'material' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    csrf_verify();
    $client_type      = sanitize($_POST['client_type']);
    $amount_requested = sanitize($_POST['amount_requested'] ?? 0);
    $purpose          = sanitize($_POST['purpose']);
    $materials_needed = sanitize($_POST['materials_needed']);
    $group_name       = sanitize($_POST['group_name'] ?? '');
    $number_of_members= sanitize($_POST['number_of_members'] ?? 1);
    $estimated_value  = sanitize($_POST['amount_requested'] ?? 0);
    
    $db->beginTransaction();
    
    try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'material', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_material_details 
                        (aics_id, client_type, group_name, number_of_members, materials_requested, estimated_value, purpose) 
                        VALUES (:aics_id, :client_type, :group_name, :members, :materials, :estimated, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':client_type', $client_type);
        $detail_stmt->bindParam(':group_name', $group_name);
        $detail_stmt->bindParam(':members', $number_of_members);
        $detail_stmt->bindParam(':materials', $materials_needed);
        $detail_stmt->bindParam(':estimated', $estimated_value);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'general_intake' => 'General Intake Sheet (GIS)',
            'material_sheet' => 'Material Distribution Sheet (MDS)',
            'case_summary'   => 'Case Summary'
        ];
        
        foreach ($document_fields as $field => $doc_name) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = [
                    'name'     => $_FILES[$field]['name'],
                    'type'     => $_FILES[$field]['type'],
                    'tmp_name' => $_FILES[$field]['tmp_name'],
                    'error'    => $_FILES[$field]['error'],
                    'size'     => $_FILES[$field]['size']
                ];
                $file_path = $uploader->upload($file, 'aics');
                $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_name, document_path) 
                              VALUES (:aics_id, :doc_type, :doc_name, :path)";
                $doc_stmt = $db->prepare($doc_query);
                $doc_stmt->bindParam(':aics_id', $aics_id);
                $doc_stmt->bindParam(':doc_type', $field);
                $doc_stmt->bindParam(':doc_name', $doc_name);
                $doc_stmt->bindParam(':path', $file_path);
                $doc_stmt->execute();
            }
        }
        
        $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Material Assistance Submitted', 
                        'Your application has been submitted. Tracking #: $tracking', 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        
        $db->commit();
        $success = "Application submitted successfully! Your tracking number is: <strong>$tracking</strong>";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Application failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Material Assistance under AICS – non-food items, hygiene kits, sleeping materials, and other essential supplies.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css">
    <style>
        :root {
            --accent: #6a1b9a;
            --accent-light: #7b1fa2;
            --accent-dark: #4a148c;
            --accent-rgb: 106, 27, 154;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
        .aics-hero { background: linear-gradient(135deg, #4a148c 0%, #6a1b9a 60%, #0F414A 100%); }
    </style>
</head>
<body>

    <!-- Unified Navigation Bar -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <nav class="aics-topnav">
        <div class="aics-topnav-inner">
            <div class="aics-breadcrumb">
                <a href="services.php">Services</a>
                <span class="sep"><i class="fas fa-chevron-right"></i></span>
                <a href="aics.php">AICS</a>
                <span class="sep"><i class="fas fa-chevron-right"></i></span>
                <span class="current">Material Assistance</span>
            </div>
            <a href="aics.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to AICS</a>
        </div>
    </nav>

    <div class="aics-hero">
        <div class="aics-hero-inner">
            <div class="aics-hero-badge"><i class="fas fa-shield-alt"></i> AICS Program</div>
            <div class="aics-hero-icon"><i class="fas fa-boxes"></i></div>
            <h1>Material Assistance</h1>
            <p>Non-food items, hygiene kits, sleeping materials, and other essential supplies for individuals and families in need.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>In-Kind</strong><span>Assistance Type</span></div>
                <div class="hero-stat"><strong>3–5 Days</strong><span>Processing Time</span></div>
                <div class="hero-stat"><strong>Free</strong><span>Application</span></div>
            </div>
        </div>
    </div>

    <div class="aics-alert-wrapper">
        <?php if($error): ?>
        <div class="aics-alert error">
            <div class="aics-alert-icon"><i class="fas fa-times"></i></div>
            <div class="aics-alert-body"><strong>Application Failed</strong><p><?php echo $error; ?></p></div>
        </div>
        <?php endif; ?>
        <?php if($success): ?>
        <div class="aics-alert success">
            <div class="aics-alert-icon"><i class="fas fa-check"></i></div>
            <div class="aics-alert-body"><strong>Application Submitted!</strong><p><?php echo $success; ?></p></div>
        </div>
        <?php endif; ?>
        <?php if($pending_app && $pending_app['status'] != 'rejected'): ?>
        <div class="pending-banner">
            <div class="pending-banner-icon"><i class="fas fa-clock"></i></div>
            <div class="pending-banner-content">
                <strong>You have a pending application</strong>
                <p>Status: <strong><?php echo ucfirst($pending_app['status']); ?></strong> — Please wait for staff review.</p>
            </div>
            <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="pending-banner-action">
                <i class="fas fa-search"></i> Track Application
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="aics-content">

        <aside class="req-panel">
            <div class="req-panel-header">
                <h2><i class="fas fa-clipboard-list"></i> Mga Kinakailangan</h2>
                <p>Material Assistance para sa mga indibidwal at grupo na nangangailangan ng non-food items.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-user"></i></span>
                        Para sa Indibidwal na Kliyente
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> General Intake Sheet (GIS)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Material Distribution Sheet (MDS)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-users"></i></span>
                        Para sa Grupo na Kliyente
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> General Intake Sheet (GIS)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Case Summary</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Material Distribution Sheet (MDS)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-box"></i></span>
                        Karaniwang Materyales na Ibinibigay
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Hygiene Kits (sabon, shampoo, toothpaste)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Sleeping Materials (banig, kumot, unan)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Clothing (damit para sa pamilya)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> School Supplies (para sa mga estudyante)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Other essential non-food items</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Ang material assistance ay ibinibigay batay sa assessment ng social worker at availability ng supplies sa bodega.</p>
                </div>
            </div>
        </aside>

        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-boxes"></i></span>
                    Apply for Material Assistance
                </h2>
                <p>Fill in all required fields and list down the materials you need.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Client Information</span>
                    </div>
                    <div class="form-step">
                        <span class="step-num">2</span>
                        <span class="step-label">Upload Documents</span>
                    </div>
                    <div class="form-step">
                        <span class="step-num">3</span>
                        <span class="step-label">Submit & Track</span>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="applicationForm">
                    <?php echo csrf_field(); ?>

                    <!-- STEP 1: Details -->
                    <div class="wizard-step" data-step="1">
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Client Information</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-users"></i></span>
                                Client Type <span class="required-star">*</span>
                            </label>
                            <select name="client_type" id="clientType" required>
                                <option value="">— Select client type —</option>
                                <option value="individual">Individual Client (Indibidwal)</option>
                                <option value="group">Group Client (Grupo)</option>
                            </select>
                        </div>

                        <div id="groupFields" style="display:none;">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>
                                        <span class="field-icon"><i class="fas fa-users"></i></span>
                                        Group / Organization Name
                                    </label>
                                    <input type="text" name="group_name" placeholder="Enter group or organization name">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <span class="field-icon"><i class="fas fa-user-friends"></i></span>
                                        Number of Members
                                    </label>
                                    <input type="number" name="number_of_members" min="2" max="100" placeholder="2">
                                </div>
                            </div>
                        </div>

                        <div class="form-section-title"><i class="fas fa-box"></i> Materials Requested</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-clipboard-list"></i></span>
                                Materials Needed <span class="required-star">*</span>
                            </label>
                            <textarea name="materials_needed" rows="4" required placeholder="Ilista ang mga materyales / items na kailangan ninyo (e.g., 2 banig, 3 kumot, hygiene kit, atbp.)..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                Estimated Value (₱)
                            </label>
                            <input type="number" name="amount_requested" min="0" step="100" placeholder="0.00 (optional estimate)">
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Estimated value of materials requested (optional)</div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Reason for Assistance <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="4" required placeholder="Ipaliwanag kung bakit kailangan ninyo ng material assistance..."></textarea>
                        </div>
                    </div>

                    <!-- STEP 2: Document Uploads -->
                    <div class="wizard-step" data-step="2">
                        <div class="upload-section">
                            <div class="form-section-title"><i class="fas fa-upload"></i> Upload Required Documents</div>

                            <div class="upload-grid">
                                <div class="upload-zone" id="zone-general_intake">
                                    <input type="file" name="general_intake" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-general_intake')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta"><strong>General Intake Sheet (GIS)</strong><span>GIS form from CSWDO</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-material_sheet">
                                    <input type="file" name="material_sheet" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-material_sheet')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-table"></i></div>
                                        <div class="upload-zone-meta"><strong>Material Distribution Sheet (MDS)</strong><span>MDS form from CSWDO</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field full-width" id="zone-case_summary">
                                    <input type="file" name="case_summary" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-case_summary')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta"><strong>Case Summary</strong><span>Required for Group Client applications</span></div>
                                        <span class="upload-required-badge">Required for Groups</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Review and Submit -->
                    <div class="wizard-step" data-step="3">
                        <div class="form-section-title"><i class="fas fa-check-circle"></i> Review Your Application</div>
                        
                        <div class="summary-intro">
                            <i class="fas fa-shield-alt"></i>
                            <div class="summary-intro-text">
                                <strong>Paki-rebyu ang iyong mga detalye</strong>
                                <p>Tiyaking tama ang lahat ng impormasyon bago isumite ang aplikasyon upang maiwasan ang anumang pagkaantala.</p>
                            </div>
                        </div>

                        <div id="summary-preview-table"></div>

                        <div class="summary-confirmation">
                            <label class="checkbox-container">
                                <input type="checkbox" id="declaration-check" required>
                                <span>Ako ay nagpapatunay na ang lahat ng nakasulat sa aplikasyong ito ay totoo at tama sa abot ng aking kaalaman.</span>
                            </label>
                        </div>

                        <div class="submit-section">
                            <button type="submit" name="submit_application" class="btn-submit" id="submitBtn" disabled>
                                <span class="btn-icon"><i class="fas fa-paper-plane"></i></span>
                                Submit Material Assistance Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="form-panel-body">
                <div class="already-pending">
                    <div class="already-pending-icon"><i class="fas fa-clock"></i></div>
                    <h3>Application Under Review</h3>
                    <p>You already have a pending Material Assistance application. Please wait for the staff to review your request before submitting another one.</p>
                    <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="btn-track">
                        <i class="fas fa-search"></i> Track Your Application
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <footer class="aics-footer">
        <div class="aics-footer-inner">
            <div class="footer-brand"><i class="fas fa-hands-helping"></i> Palayan-Kalinga Hub — AICS Division</div>
            <div class="footer-links">
                <a href="aics.php">AICS Home</a>
                <a href="track-aics.php">Track Application</a>
                <a href="contact.php">Contact Us</a>
            </div>
        </div>
        <div class="footer-note">Assistance to Individuals in Crisis Situation (AICS) — City Social Welfare and Development Office (CSWDO)</div>
    </footer>

    <script>
        document.getElementById('clientType')?.addEventListener('change', function() {
            const type = this.value;
            const groupFields    = document.getElementById('groupFields');
            const caseSummary    = document.getElementById('zone-case_summary');
            const groupNameInput = document.querySelector('input[name="group_name"]');
            const membersInput   = document.querySelector('input[name="number_of_members"]');
            const caseInput      = document.querySelector('input[name="case_summary"]');

            if (type === 'group') {
                groupFields.style.display = 'block';
                caseSummary.classList.add('visible');
                if (groupNameInput) groupNameInput.required = true;
                if (membersInput)   membersInput.required   = true;
                if (caseInput)      caseInput.required      = true;
            } else {
                groupFields.style.display = 'none';
                caseSummary.classList.remove('visible');
                if (groupNameInput) groupNameInput.required = false;
                if (membersInput)   membersInput.required   = false;
                if (caseInput)      caseInput.required      = false;
            }
        });

        function markUploaded(input, zoneId) {
            const zone = document.getElementById(zoneId);
            if (input.files && input.files.length > 0) {
                zone.classList.add('uploaded');
                const meta = zone.querySelector('.upload-zone-meta span');
                if (meta) meta.textContent = '✓ ' + input.files[0].name;
            } else {
                zone.classList.remove('uploaded');
            }
        }

        document.querySelectorAll('.upload-zone').forEach(zone => {
            zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
            zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
            zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
        });

        // Toggle submit button based on declaration check
        const declCheck = document.getElementById('declaration-check');
        const submitBtn = document.getElementById('submitBtn');
        if (declCheck && submitBtn) {
            declCheck.addEventListener('change', function() {
                submitBtn.disabled = !this.checked;
            });
        }
    </script>

    <!-- Include Wizard Logic JS -->
    <script src="js/aics-wizard.js"></script>

</body>
</html>
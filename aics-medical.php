<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Check if user has pending application
$check_query = "SELECT * FROM aics_requests 
                WHERE user_id = :user_id AND request_type = 'medical' 
                AND status IN ('submitted', 'reviewing', 'approved')
                ORDER BY submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    csrf_verify();
    
    // Check if user has active/pending application
    if ($pending_app && $pending_app['status'] !== 'rejected') {
        $error = "You already have an active application. Please wait for it to be resolved.";
    } 
    // Server-side validation for required files
    elseif (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
        $error = "A valid ID is required.";
    } elseif (!isset($_FILES['medical_certificate']) || $_FILES['medical_certificate']['error'] !== UPLOAD_ERR_OK) {
        $error = "A medical certificate is required.";
    } else {
        $assistance_type = sanitize($_POST['assistance_type']);
        $amount_requested = sanitize($_POST['amount_requested']);
        $purpose = sanitize($_POST['purpose']);
        $hospital_name = sanitize($_POST['hospital_name'] ?? '');
        $doctor_name = sanitize($_POST['doctor_name'] ?? '');
        
        $db->beginTransaction();
        
        try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, purpose, status, submission_date) 
                  VALUES (:user_id, 'medical', :amount, :purpose, 'submitted', NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();

        $detail_query = "INSERT INTO aics_medical_details (aics_id, assistance_type, hospital_name, doctor_name) 
                         VALUES (:aics_id, :assistance_type, :hospital_name, :doctor_name)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':assistance_type', $assistance_type);
        $detail_stmt->bindParam(':hospital_name', $hospital_name);
        $detail_stmt->bindParam(':doctor_name', $doctor_name);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'            => 'Valid ID',
            'medical_certificate' => 'Medical Certificate',
            'hospital_bill'       => 'Hospital Bill',
            'prescription'        => 'Prescription',
            'laboratory_request'  => 'Laboratory Request',
            'quotation'           => 'Quotation'
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
                $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_path) 
                              VALUES (:aics_id, :doc_type, :path)";
                $doc_stmt = $db->prepare($doc_query);
                $doc_stmt->bindParam(':aics_id', $aics_id);
                $doc_stmt->bindParam(':doc_type', $doc_name);
                $doc_stmt->bindParam(':path', $file_path);
                $doc_stmt->execute();
            }
        }
        
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'AICS Application Submitted', 
                        'Your Medical Assistance application has been submitted successfully. Tracking #: AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT) . "', 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        
        $db->commit();
        $success = "Application submitted successfully! Your tracking number is: <strong>AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT) . "</strong>";
        
        // Send email confirmation
        try {
            require_once __DIR__ . '/includes/Mailer.php';
            $mailer = new Mailer();
            $user_query = "SELECT email, first_name, last_name FROM users WHERE user_id = :user_id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $user_stmt->execute();
            $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_info && !empty($user_info['email'])) {
                $user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
                $aics_data = [
                    'aics_id' => $aics_id,
                    'request_type' => 'medical',
                    'user_id' => $_SESSION['user_id']
                ];
                $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
            }
        } catch (Exception $e) {
            error_log("AICS medical submission email confirmation failed: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Application failed: " . $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Medical Assistance under AICS – financial support for hospital bills, medicines, laboratory tests, and medical procedures.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css">
    <style>
        :root {
            --accent: #7F0303;
            --accent-light: #9a1515;
            --accent-dark: #5a0202;
            --accent-rgb: 127, 3, 3;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
    </style>
</head>
<body>

    <!-- Unified Navigation Bar -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Top Navigation -->
    <nav class="aics-topnav">
        <div class="aics-topnav-inner">
            <div class="aics-breadcrumb">
                <a href="services.php">Services</a>
                <span class="sep"><i class="fas fa-chevron-right"></i></span>
                <a href="aics.php">AICS</a>
                <span class="sep"><i class="fas fa-chevron-right"></i></span>
                <span class="current">Medical Assistance</span>
            </div>
            <a href="aics.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to AICS
            </a>
        </div>
    </nav>

    <!-- Hero Header -->
    <div class="aics-hero">
        <div class="aics-hero-inner">
            <div class="aics-hero-badge">
                <i class="fas fa-shield-alt"></i> AICS Program
            </div>
            <div class="aics-hero-icon">
                <i class="fas fa-hospital-user"></i>
            </div>
            <h1>Medical Assistance</h1>
            <p>Financial support for hospital bills, medicines, laboratory tests, and medical procedures for individuals and families in need.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>₱100K</strong><span>Max Assistance</span></div>
                <div class="hero-stat"><strong>3–5 Days</strong><span>Processing Time</span></div>
                <div class="hero-stat"><strong>Free</strong><span>Application</span></div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <div class="aics-alert-wrapper">
        <?php if($error): ?>
        <div class="aics-alert error">
            <div class="aics-alert-icon"><i class="fas fa-times"></i></div>
            <div class="aics-alert-body">
                <strong>Application Failed</strong>
                <p><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="aics-alert success">
            <div class="aics-alert-icon"><i class="fas fa-check"></i></div>
            <div class="aics-alert-body">
                <strong>Application Submitted!</strong>
                <p><?php echo $success; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($pending_app && $pending_app['status'] != 'rejected'): ?>
        <div class="pending-banner">
            <div class="pending-banner-icon"><i class="fas fa-clock"></i></div>
            <div class="pending-banner-content">
                <strong>You have a pending application</strong>
                <p>Status: <strong><?php echo ucfirst($pending_app['status']); ?></strong> — Please wait for the staff to review your request before submitting another.</p>
            </div>
            <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="pending-banner-action">
                <i class="fas fa-search"></i> Track Application
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="aics-content">

        <!-- Requirements Sidebar -->
        <aside class="req-panel">
            <div class="req-panel-header">
                <h2><i class="fas fa-clipboard-list"></i> Requirements</h2>
                <p>Prepare the following documents before applying to speed up processing.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        General Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID of the applicant or authorized representative</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Authorization Letter (if not applying in person)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-hospital"></i></span>
                        Para sa Hospital Bill
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Medical Certificate / Clinical Abstract / Discharge Summary</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Hospital Bill or Statement of Account</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Certificate of Balance and Promissory Note</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Social Case Study Report or Case Summary</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-pills"></i></span>
                        Para sa Gamot / Assistive Device
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Medical Certificate / Clinical Abstract</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Prescription with date and physician's signature</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Quotation (if amount exceeds ₱10,000)</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Siguraduhing kumpleto ang mga dokumento bago mag-apply upang mapabilis ang proseso ng inyong aplikasyon.</p>
                </div>
            </div>
        </aside>

        <!-- Application Form -->
        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-file-medical"></i></span>
                    Apply for Medical Assistance
                </h2>
                <p>Fill in the form below completely and upload the required documents.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Application Details</span>
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
                    <div class="wizard-step active" data-step="1">
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Application Information</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-tag"></i></span>
                                Type of Medical Assistance <span class="required-star">*</span>
                            </label>
                            <select name="assistance_type" required id="assistanceType" onchange="toggleMedicalFields()">
                                <option value="">— Select type of assistance —</option>
                                <option value="hospital_bill">Hospital Bill Payment</option>
                                <option value="medicine">Medicine / Assistive Device</option>
                                <option value="laboratory">Laboratory / Medical Procedure</option>
                            </select>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-hospital"></i></span>
                                    Hospital / Clinic Name
                                </label>
                                <input type="text" name="hospital_name" placeholder="e.g., Philippine General Hospital">
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-user-md"></i></span>
                                    Attending Physician
                                </label>
                                <input type="text" name="doctor_name" placeholder="e.g., Dr. Juan Dela Cruz">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                Amount Requested (₱) <span class="required-star">*</span>
                            </label>
                            <input type="number" name="amount_requested" min="100" max="100000" step="100" required placeholder="0.00">
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Maximum of ₱100,000 subject to assessment and approval</div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Purpose / Detailed Explanation <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="5" required placeholder="Describe your medical condition, current situation, and why you need this assistance..."></textarea>
                        </div>
                    </div>

                    <!-- STEP 2: Document Uploads -->
                    <div class="wizard-step" data-step="2">
                        <div class="upload-section">
                            <div class="form-section-title"><i class="fas fa-upload"></i> Upload Required Documents</div>

                            <div class="upload-grid">
                                <div class="upload-zone" id="zone-valid_id">
                                    <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-valid_id')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-id-card"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Valid ID</strong>
                                            <span>Government-issued ID</span>
                                        </div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
                                </div>

                                <div class="upload-zone" id="zone-medical_certificate">
                                    <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-medical_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-medical"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Medical Certificate</strong>
                                            <span>Clinical Abstract / Discharge Summary</span>
                                        </div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-hospital_bill">
                                    <input type="file" name="hospital_bill" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-hospital_bill')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-invoice"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Hospital Bill</strong>
                                            <span>Statement of Account / Certificate of Balance</span>
                                        </div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-prescription">
                                    <input type="file" name="prescription" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-prescription')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-prescription-bottle-alt"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Prescription</strong>
                                            <span>With date and physician's signature</span>
                                        </div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-laboratory_request">
                                    <input type="file" name="laboratory_request" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-laboratory_request')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-flask"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Laboratory Request</strong>
                                            <span>Lab request or results</span>
                                        </div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-quotation">
                                    <input type="file" name="quotation" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-quotation')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-receipt"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Quotation</strong>
                                            <span>For amounts exceeding ₱10,000</span>
                                        </div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer">
                                        <i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB
                                    </div>
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
                                Submit Medical Assistance Application
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
                    <p>You already have a pending Medical Assistance application. Please wait for the staff to review your request before submitting another one.</p>
                    <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="btn-track">
                        <i class="fas fa-search"></i> Track Your Application
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Footer -->
    <footer class="aics-footer">
        <div class="aics-footer-inner">
            <div class="footer-brand">
                <i class="fas fa-hands-helping"></i>
                Palayan-Kalinga Hub — AICS Division
            </div>
            <div class="footer-links">
                <a href="aics.php">AICS Home</a>
                <a href="track-aics.php">Track Application</a>
                <a href="contact.php">Contact Us</a>
            </div>
        </div>
        <div class="footer-note">
            Assistance to Individuals in Crisis Situation (AICS) — City Social Welfare and Development Office (CSWDO)
        </div>
    </footer>

    <script>
        function toggleMedicalFields() {
            const type = document.getElementById('assistanceType').value;
            const allDynamic = document.querySelectorAll('.upload-zone.dynamic-field');
            allDynamic.forEach(el => { el.classList.remove('visible'); });

            if (type === 'hospital_bill') {
                document.getElementById('zone-hospital_bill').classList.add('visible');
            } else if (type === 'medicine') {
                document.getElementById('zone-prescription').classList.add('visible');
                document.getElementById('zone-quotation').classList.add('visible');
            } else if (type === 'laboratory') {
                document.getElementById('zone-laboratory_request').classList.add('visible');
            }
        }

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

        // Drag & drop visual feedback
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
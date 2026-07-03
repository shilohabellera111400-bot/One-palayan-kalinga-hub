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
                WHERE r.user_id = :user_id AND r.request_type = 'cash_relief' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    csrf_verify();
    $amount_requested   = sanitize($_POST['amount_requested']);
    $purpose            = sanitize($_POST['purpose']);
    $emergency_type     = sanitize($_POST['emergency_type']);
    $affected_members   = sanitize($_POST['affected_members'] ?? 1);
    $incident_date      = sanitize($_POST['incident_date'] ?? '');
    $incident_report_no = sanitize($_POST['incident_report_no'] ?? '');
    $police_report_no   = sanitize($_POST['police_report_no'] ?? '');
    
    $db->beginTransaction();
    
    try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'cash_relief', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_cash_relief_details 
                        (aics_id, emergency_type, affected_members, incident_date, incident_report_no, police_report_no) 
                        VALUES (:aics_id, :emergency_type, :affected_members, :incident_date, :incident_report_no, :police_report_no)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':emergency_type', $emergency_type);
        $detail_stmt->bindParam(':affected_members', $affected_members);
        $detail_stmt->bindParam(':incident_date', $incident_date);
        $detail_stmt->bindParam(':incident_report_no', $incident_report_no);
        $detail_stmt->bindParam(':police_report_no', $police_report_no);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'              => 'Valid ID',
            'barangay_certificate'  => 'Barangay Certificate',
            'incident_report'       => 'Incident Report',
            'medical_certificate'   => 'Medical Certificate',
            'police_report'         => 'Police Report'
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
                        VALUES (:user_id, 'Cash Relief Submitted', 
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
    <title>Cash Relief Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Cash Relief Assistance under AICS – financial support for individuals and families experiencing crisis situations.">
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
                <span class="current">Cash Relief</span>
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
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h1>Cash Relief Assistance</h1>
            <p>Financial aid for individuals and families experiencing emergency situations, natural disasters, accidents, or personal crises.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>₱10,000</strong><span>Max Amount</span></div>
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

                <div class="req-amount-badge">
                    <span class="amount-label">Maximum Cash Relief</span>
                    <span class="amount-value">₱5,000 – ₱10,000</span>
                    <span class="amount-note">Batay sa assessment ng social worker</span>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        General Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID of the applicant</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Barangay Certificate of Residency and Indigency</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Letter of Request explaining the situation</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-exclamation-circle"></i></span>
                        Para sa Emergency Situations
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Incident Report (for disaster or accident)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Medical Certificate (for medical emergencies)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Police Report (for crime victims)</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Ang cash relief ay ibinibigay matapos ang masusing assessment at approval ng CSWDO. Ang bawat kaso ay sinusuri nang indibidwal.</p>
                </div>
            </div>
        </aside>

        <!-- Application Form -->
        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-hand-holding-usd"></i></span>
                    Apply for Cash Relief Assistance
                </h2>
                <p>Fill in all required fields accurately and attach the necessary supporting documents.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Emergency Details</span>
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
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Emergency Information</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                Type of Emergency <span class="required-star">*</span>
                            </label>
                            <select name="emergency_type" id="emergency_type" required onchange="toggleEmergencyFields()">
                                <option value="">— Select emergency type —</option>
                                <option value="medical">Medical Emergency</option>
                                <option value="disaster">Natural Disaster (Fire, Flood, etc.)</option>
                                <option value="accident">Accident</option>
                                <option value="crime">Victim of Crime</option>
                                <option value="financial">Financial Crisis</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-users"></i></span>
                                Number of Affected Family Members <span class="required-star">*</span>
                            </label>
                            <input type="number" name="affected_members" min="1" max="20" value="1" required>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-calendar-alt"></i></span>
                                    Incident Date
                                </label>
                                <input type="date" name="incident_date">
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-hashtag"></i></span>
                                    Incident Report No.
                                </label>
                                <input type="text" name="incident_report_no" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                Amount Requested (₱) <span class="required-star">*</span>
                            </label>
                            <input type="number" name="amount_requested" min="500" max="10000" step="100" required placeholder="0.00">
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Maximum ₱10,000 — subject to social worker's assessment and approval</div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Explanation of Situation <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="5" required placeholder="Explain your situation in detail — what happened, how it has affected your family, and why you need cash relief assistance..."></textarea>
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
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-barangay_certificate">
                                    <input type="file" name="barangay_certificate" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-barangay_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Barangay Certificate</strong>
                                            <span>Clearance or Indigency Certificate</span>
                                        </div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-incident_report">
                                    <input type="file" name="incident_report" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-incident_report')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Incident Report</strong>
                                            <span>Barangay or NDRRMC Report</span>
                                        </div>
                                        <span class="upload-optional-badge" id="incident-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-medical_certificate">
                                    <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-medical_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-medical"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Medical Certificate</strong>
                                            <span>Required for medical emergencies</span>
                                        </div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-police_report">
                                    <input type="file" name="police_report" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-police_report')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Police Report</strong>
                                            <span>Required for crime victims</span>
                                        </div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-authorization_letter">
                                    <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-authorization_letter')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-signature"></i></div>
                                        <div class="upload-zone-meta">
                                            <strong>Authorization Letter</strong>
                                            <span>If not applying in person</span>
                                        </div>
                                        <span class="upload-optional-badge">Optional</span>
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
                                Submit Cash Relief Application
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
                    <p>You already have a pending Cash Relief application. Please wait for the staff to review your request before submitting another one.</p>
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
        function toggleEmergencyFields() {
            const type = document.getElementById('emergency_type').value;
            const medDoc = document.getElementById('zone-medical_certificate');
            const polDoc = document.getElementById('zone-police_report');
            const incDoc = document.getElementById('zone-incident_report');
            const incBadge = document.getElementById('incident-badge');
            const medInput = document.querySelector('input[name="medical_certificate"]');
            const polInput = document.querySelector('input[name="police_report"]');
            const incInput = document.querySelector('input[name="incident_report"]');

            medDoc.classList.remove('visible');
            polDoc.classList.remove('visible');
            medInput.required = false;
            polInput.required = false;
            incInput.required = false;
            incBadge.textContent = 'Optional';
            incBadge.className = 'upload-optional-badge';

            if (type === 'medical') {
                medDoc.classList.add('visible');
                medInput.required = true;
            } else if (type === 'crime') {
                polDoc.classList.add('visible');
                polInput.required = true;
            } else if (type === 'disaster' || type === 'accident') {
                incInput.required = true;
                incBadge.textContent = 'Required';
                incBadge.className = 'upload-required-badge';
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
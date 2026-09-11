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
                WHERE r.user_id = :user_id AND r.request_type = 'transportation' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
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
    } else {
        $amount_requested   = sanitize($_POST['amount_requested']);
        $purpose            = sanitize($_POST['purpose']);
        $destination        = sanitize($_POST['destination']);
        $travel_date        = sanitize($_POST['travel_date']);
        $number_of_persons  = sanitize($_POST['number_of_persons']);
        $reason_type        = sanitize($_POST['reason_type']);
        $estimated_fare     = sanitize($_POST['estimated_fare'] ?? 0);
        
        $db->beginTransaction();
        
        try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'transportation', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_transportation_details 
                        (aics_id, reason_type, destination, travel_date, number_of_persons, estimated_fare, purpose) 
                        VALUES (:aics_id, :reason_type, :destination, :travel_date, :persons, :fare, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':reason_type', $reason_type);
        $detail_stmt->bindParam(':destination', $destination);
        $detail_stmt->bindParam(':travel_date', $travel_date);
        $detail_stmt->bindParam(':persons', $number_of_persons);
        $detail_stmt->bindParam(':fare', $estimated_fare);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'             => 'Valid ID',
            'authorization_letter' => 'Authorization Letter',
            'medical_certificate'  => 'Medical Certificate',
            'death_certificate'    => 'Death Certificate',
            'court_order'          => 'Court Order or Subpoena'
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
                        VALUES (:user_id, 'Transportation Assistance Submitted', 
                        'Your application has been submitted. Tracking #: $tracking', 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        
        $db->commit();
        $success = "Application submitted successfully! Your tracking number is: <strong>$tracking</strong>";
        
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
                    'request_type' => 'transportation',
                    'user_id' => $_SESSION['user_id']
                ];
                $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
            }
        } catch (Exception $e) {
            error_log("AICS transportation submission email confirmation failed: " . $e->getMessage());
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
    <title>Transportation Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Transportation Assistance under AICS – financial aid for transportation expenses for medical, legal, or urgent needs.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css">
    <style>
        :root {
            --accent: #00695c;
            --accent-light: #00897b;
            --accent-dark: #004d40;
            --accent-rgb: 0, 105, 92;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
        .aics-hero { background: linear-gradient(135deg, #004d40 0%, #00695c 55%, #0F414A 100%); }
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
                <span class="current">Transportation Assistance</span>
            </div>
            <a href="aics.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to AICS</a>
        </div>
    </nav>

    <div class="aics-hero">
        <div class="aics-hero-inner">
            <div class="aics-hero-badge"><i class="fas fa-shield-alt"></i> AICS Program</div>
            <div class="aics-hero-icon"><i class="fas fa-bus"></i></div>
            <h1>Transportation Assistance</h1>
            <p>Financial assistance for transportation expenses related to medical appointments, legal matters, funerals, or other urgent personal needs.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>₱5,000</strong><span>Max Amount</span></div>
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
                <p>Transportation Assistance para sa mga indibidwal na nangangailangan ng suporta sa pamasahe.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-amount-badge">
                    <span class="amount-label">Maximum Transportation Aid</span>
                    <span class="amount-value">Up to ₱5,000</span>
                    <span class="amount-note">Batay sa distansya at bilang ng tao</span>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        Pangunahing Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID ng taong maglalakad / iinterviewhin</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Authorization Letter (if applicable)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-file-alt"></i></span>
                        Supporting Documents (kung naaangkop)
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Medical Certificate (para sa medical appointment)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Death Certificate (para sa libing)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Court Order or Subpoena (para sa legal na usapin)</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Ang transportation assistance ay ibinibigay batay sa distansya, bilang ng tao, at layunin ng biyahe. Kinakailangan ang malinaw na dahilan ng paglalakbay.</p>
                </div>
            </div>
        </aside>

        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-bus"></i></span>
                    Apply for Transportation Assistance
                </h2>
                <p>Fill in your travel details and attach the required supporting documents.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Travel Details</span>
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
                        <div class="form-section-title"><i class="fas fa-route"></i> Travel Information</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-tag"></i></span>
                                Reason for Transportation <span class="required-star">*</span>
                            </label>
                            <select name="reason_type" id="reasonType" required>
                                <option value="">— Select reason —</option>
                                <option value="medical">Medical Appointment / Hospital Visit</option>
                                <option value="legal">Legal Matter / Court Hearing</option>
                                <option value="funeral">Funeral / Burial Attendance</option>
                                <option value="emergency">Family Emergency</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-map-marker-alt"></i></span>
                                Destination / Location <span class="required-star">*</span>
                            </label>
                            <input type="text" name="destination" required placeholder="e.g., Philippine General Hospital, Manila">
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-calendar-day"></i></span>
                                    Travel Date <span class="required-star">*</span>
                                </label>
                                <input type="date" name="travel_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-users"></i></span>
                                    Number of Persons Travelling <span class="required-star">*</span>
                                </label>
                                <input type="number" name="number_of_persons" min="1" max="10" value="1" required>
                            </div>
                        </div>

                        <div class="form-section-title"><i class="fas fa-peso-sign"></i> Financial Details</div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                    Amount Requested (₱) <span class="required-star">*</span>
                                </label>
                                <input type="number" name="amount_requested" min="100" max="5000" step="50" required placeholder="0.00">
                                <div class="form-hint"><i class="fas fa-info-circle"></i> Maximum ₱5,000 subject to approval</div>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-receipt"></i></span>
                                    Estimated Fare (₱)
                                </label>
                                <input type="number" name="estimated_fare" min="0" step="50" placeholder="0.00 (optional)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Explanation / Purpose <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="5" required placeholder="Explain why you need transportation assistance, including details about your medical appointment, legal case, or other urgent reason for travel..."></textarea>
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
                                        <div class="upload-zone-meta"><strong>Valid ID</strong><span>Government-issued ID</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-authorization_letter">
                                    <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-authorization_letter')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-signature"></i></div>
                                        <div class="upload-zone-meta"><strong>Authorization Letter</strong><span>If not the person travelling</span></div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-medical_certificate">
                                    <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-medical_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-medical"></i></div>
                                        <div class="upload-zone-meta"><strong>Medical Certificate</strong><span>Certificate or appointment slip</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-court_order">
                                    <input type="file" name="court_order" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-court_order')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-gavel"></i></div>
                                        <div class="upload-zone-meta"><strong>Court Order / Subpoena</strong><span>For legal matters</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-death_certificate">
                                    <input type="file" name="death_certificate" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-death_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-cross"></i></div>
                                        <div class="upload-zone-meta"><strong>Death Certificate</strong><span>For funeral attendance</span></div>
                                        <span class="upload-required-badge">Required</span>
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
                                Submit Transportation Assistance Application
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
                    <p>You already have a pending Transportation Assistance application. Please wait for the staff to review your request before submitting another one.</p>
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
        document.getElementById('reasonType')?.addEventListener('change', function() {
            const reason = this.value;
            const medDoc    = document.getElementById('zone-medical_certificate');
            const legalDoc  = document.getElementById('zone-court_order');
            const funeralDoc= document.getElementById('zone-death_certificate');

            [medDoc, legalDoc, funeralDoc].forEach(z => z?.classList.remove('visible'));

            if (reason === 'medical')        medDoc?.classList.add('visible');
            else if (reason === 'legal')     legalDoc?.classList.add('visible');
            else if (reason === 'funeral')   funeralDoc?.classList.add('visible');
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
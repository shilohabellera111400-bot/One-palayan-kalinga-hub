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
                WHERE r.user_id = :user_id AND r.request_type = 'food' 
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
    } elseif (!isset($_FILES['barangay_certificate']) || $_FILES['barangay_certificate']['error'] !== UPLOAD_ERR_OK) {
        $error = "A barangay certificate is required.";
    } elseif (!isset($_FILES['house_photo']) || $_FILES['house_photo']['error'] !== UPLOAD_ERR_OK) {
        $error = "A house photo is required.";
    } else {
        $amount_requested = sanitize($_POST['amount_requested'] ?? 0);
        $purpose          = sanitize($_POST['purpose']);
        $household_size   = sanitize($_POST['household_size'] ?? 1);
        $special_needs    = sanitize($_POST['special_needs'] ?? '');
        $monthly_income   = sanitize($_POST['amount_requested'] ?? 0);
        
        $db->beginTransaction();
        
        try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'food', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_food_details 
                        (aics_id, household_size, monthly_income, special_needs, purpose) 
                        VALUES (:aics_id, :household_size, :income, :special_needs, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':household_size', $household_size);
        $detail_stmt->bindParam(':income', $monthly_income);
        $detail_stmt->bindParam(':special_needs', $special_needs);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'             => 'Valid ID',
            'barangay_certificate' => 'Barangay Certificate',
            'proof_of_income'      => 'Proof of Income',
            'house_photo'          => 'House Photo'
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
                        VALUES (:user_id, 'Food Assistance Submitted', 
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
                    'request_type' => 'food',
                    'user_id' => $_SESSION['user_id']
                ];
                $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
            }
        } catch (Exception $e) {
            error_log("AICS food submission email confirmation failed: " . $e->getMessage());
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
    <title>Food Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Food Assistance under AICS – food packs and grocery items for families in crisis situations.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css">
    <style>
        :root {
            --accent: #b45309;
            --accent-light: #d97706;
            --accent-dark: #92400e;
            --accent-rgb: 180, 83, 9;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
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
                <span class="current">Food Assistance</span>
            </div>
            <a href="aics.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to AICS
            </a>
        </div>
    </nav>

    <div class="aics-hero">
        <div class="aics-hero-inner">
            <div class="aics-hero-badge"><i class="fas fa-shield-alt"></i> AICS Program</div>
            <div class="aics-hero-icon"><i class="fas fa-utensils"></i></div>
            <h1>Food Assistance</h1>
            <p>Food packs and grocery items for families in crisis situations who are experiencing difficulty in meeting their basic nutritional needs.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>5–10 kg</strong><span>Bigas Included</span></div>
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
                <p>Status: <strong><?php echo ucfirst($pending_app['status']); ?></strong> — Please wait for staff review before submitting another.</p>
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
                <h2><i class="fas fa-clipboard-list"></i> Requirements</h2>
                <p>Ihanda ang mga sumusunod na dokumento bago mag-apply.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        General Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID ng aplikante (Government-issued)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Barangay Certificate of Residency and Indigency</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Proof of Income (kung may trabaho)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Larawan ng kasalukuyang tirahan</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-file-alt"></i></span>
                        Para sa Food Assistance
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Written letter of request</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Listahan ng mga kasapi ng pamilya</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Certificate of Indigency mula sa Barangay</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-shopping-basket"></i></span>
                        Kasama sa Food Pack
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Bigas (5–10 kilos)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> De-lata (sardinas, corned beef)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Noodles at ibang pagkain</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Coffee / Milk / Asukal at Asin</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Cooking Oil</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Ang food assistance ay ibinibigay batay sa assessment ng social worker at availability ng pondo ng tanggapan.</p>
                </div>
            </div>
        </aside>

        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-utensils"></i></span>
                    Apply for Food Assistance
                </h2>
                <p>Fill in all required fields accurately and attach the necessary supporting documents.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Household Details</span>
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
                        <div class="form-section-title"><i class="fas fa-home"></i> Household Information</div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-users"></i></span>
                                    Household Size <span class="required-star">*</span>
                                </label>
                                <input type="number" name="household_size" min="1" max="20" required placeholder="Number of household members">
                                <div class="form-hint"><i class="fas fa-info-circle"></i> Bilang ng kasapi ng pamilya sa bahay</div>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                    Monthly Household Income (₱) <span class="required-star">*</span>
                                </label>
                                <input type="number" name="amount_requested" min="0" step="100" required placeholder="0.00">
                                <div class="form-hint"><i class="fas fa-info-circle"></i> Kabuuang kita ng pamilya bawat buwan</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Reason for Assistance <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="5" required placeholder="Ilahad ang inyong sitwasyon at bakit kailangan ninyo ng food assistance..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-heart"></i></span>
                                Special Needs / Dietary Restrictions (if any)
                            </label>
                            <textarea name="special_needs" rows="3" placeholder="Hal. may senior citizen, may sakit, buntis, PWD, atbp."></textarea>
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

                                <div class="upload-zone" id="zone-barangay_certificate">
                                    <input type="file" name="barangay_certificate" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-barangay_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta"><strong>Barangay Certificate</strong><span>Clearance / Indigency Certificate</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-proof_of_income">
                                    <input type="file" name="proof_of_income" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-proof_of_income')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-invoice"></i></div>
                                        <div class="upload-zone-meta"><strong>Proof of Income</strong><span>Payslip, COE, or Affidavit of No Income</span></div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-house_photo">
                                    <input type="file" name="house_photo" accept=".jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-house_photo')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-camera"></i></div>
                                        <div class="upload-zone-meta"><strong>House Photo</strong><span>Larawan ng kasalukuyang tirahan</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> JPG, PNG — max 5MB</div>
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
                                Submit Food Assistance Application
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
                    <p>You already have a pending Food Assistance application. Please wait for the staff to review your request before submitting another one.</p>
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

    <?php include 'includes/footer.php'; ?>
</body>
</html>
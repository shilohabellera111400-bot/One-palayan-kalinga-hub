<?php
header("Location: aics.php");
exit();
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
                WHERE r.user_id = :user_id AND r.request_type = 'educational' 
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
    } elseif (!isset($_FILES['school_id']) || $_FILES['school_id']['error'] !== UPLOAD_ERR_OK) {
        $error = "A school ID is required.";
    } elseif (!isset($_FILES['enrollment_form']) || $_FILES['enrollment_form']['error'] !== UPLOAD_ERR_OK) {
        $error = "An enrollment form is required.";
    } else {
        $amount_requested  = sanitize($_POST['amount_requested']);
        $purpose           = sanitize($_POST['purpose']);
        $student_name      = sanitize($_POST['student_name']);
        $school_name       = sanitize($_POST['school_name']);
        $grade_level       = sanitize($_POST['grade_level']);
        $school_year       = sanitize($_POST['school_year']);
        $tuition_fee       = sanitize($_POST['tuition_fee'] ?? 0);
        $enrollment_status = sanitize($_POST['enrollment_status'] ?? 'enrolled');
        
        $db->beginTransaction();
        
        try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'educational', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_educational_details 
                        (aics_id, student_name, school_name, grade_level, school_year, tuition_fee, enrollment_status, purpose) 
                        VALUES (:aics_id, :student_name, :school_name, :grade_level, :school_year, :tuition_fee, :enrollment_status, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':student_name', $student_name);
        $detail_stmt->bindParam(':school_name', $school_name);
        $detail_stmt->bindParam(':grade_level', $grade_level);
        $detail_stmt->bindParam(':school_year', $school_year);
        $detail_stmt->bindParam(':tuition_fee', $tuition_fee);
        $detail_stmt->bindParam(':enrollment_status', $enrollment_status);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'              => 'Valid ID',
            'authorization_letter'  => 'Authorization Letter',
            'school_id'             => 'Validated School ID',
            'enrollment_form'       => 'Enrollment Assessment Form',
            'statement_of_account'  => 'Statement of Account'
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
                        VALUES (:user_id, 'Educational Assistance Submitted', 
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
                    'request_type' => 'educational',
                    'user_id' => $_SESSION['user_id']
                ];
                $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
            }
        } catch (Exception $e) {
            error_log("AICS educational submission email confirmation failed: " . $e->getMessage());
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
    <title>Educational Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Educational Assistance under AICS – support for school supplies, tuition fees, and other educational needs of students.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css">
    <style>
        :root {
            --accent: #1565c0;
            --accent-light: #1976d2;
            --accent-dark: #0d47a1;
            --accent-rgb: 21, 101, 192;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
        .aics-hero { background: linear-gradient(135deg, #0d47a1 0%, #1565c0 55%, #0F414A 100%); }
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
                <span class="current">Educational Assistance</span>
            </div>
            <a href="aics.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to AICS</a>
        </div>
    </nav>

    <div class="aics-hero">
        <div class="aics-hero-inner">
            <div class="aics-hero-badge"><i class="fas fa-shield-alt"></i> AICS Program</div>
            <div class="aics-hero-icon"><i class="fas fa-graduation-cap"></i></div>
            <h1>Educational Assistance</h1>
            <p>Support for school supplies, tuition fees, enrollment fees, and other educational needs of students from indigent families.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>₱20,000</strong><span>Max Assistance</span></div>
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
                <p>Educational Assistance para sa mga mag-aaral na nangangailangan ng suporta.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        General Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID ng Parent / Guardian (Government-issued)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Authorization Letter (if applicable)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-graduation-cap"></i></span>
                        Requirements ng Mag-aaral
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Validated School ID ng mag-aaral</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Enrollment Assessment Form or Certificate of Registration</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Statement of Account (kung may balance sa tuition)</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-info-circle"></i> Uri ng Tulong</div>
                    <p>Ang educational assistance ay maaaring ibigay sa anyo ng cash, school supplies, o direktang bayad sa paaralan ng mag-aaral.</p>
                </div>
            </div>
        </aside>

        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-graduation-cap"></i></span>
                    Apply for Educational Assistance
                </h2>
                <p>Fill in all student information accurately and attach the supporting documents.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Student Details</span>
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
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Student Information</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-user-graduate"></i></span>
                                Name of Student <span class="required-star">*</span>
                            </label>
                            <input type="text" name="student_name" required placeholder="Full name of the student">
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-school"></i></span>
                                School Name <span class="required-star">*</span>
                            </label>
                            <input type="text" name="school_name" required placeholder="Name of the school or university">
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-chalkboard"></i></span>
                                    Grade Level / Year Level <span class="required-star">*</span>
                                </label>
                                <select name="grade_level" required>
                                    <option value="">— Select grade/year —</option>
                                    <option value="Kindergarten">Kindergarten</option>
                                    <option value="Grade 1">Grade 1</option>
                                    <option value="Grade 2">Grade 2</option>
                                    <option value="Grade 3">Grade 3</option>
                                    <option value="Grade 4">Grade 4</option>
                                    <option value="Grade 5">Grade 5</option>
                                    <option value="Grade 6">Grade 6</option>
                                    <option value="Grade 7">Grade 7</option>
                                    <option value="Grade 8">Grade 8</option>
                                    <option value="Grade 9">Grade 9</option>
                                    <option value="Grade 10">Grade 10</option>
                                    <option value="Grade 11">Grade 11 (Senior High)</option>
                                    <option value="Grade 12">Grade 12 (Senior High)</option>
                                    <option value="1st Year College">1st Year College</option>
                                    <option value="2nd Year College">2nd Year College</option>
                                    <option value="3rd Year College">3rd Year College</option>
                                    <option value="4th Year College">4th Year College</option>
                                    <option value="Graduate School">Graduate School</option>
                                    <option value="Vocational">Vocational / Technical</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-calendar-alt"></i></span>
                                    School Year <span class="required-star">*</span>
                                </label>
                                <input type="text" name="school_year" placeholder="e.g., 2024-2025" required>
                            </div>
                        </div>

                        <div class="form-section-title"><i class="fas fa-peso-sign"></i> Financial Information</div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                    Amount Requested (₱) <span class="required-star">*</span>
                                </label>
                                <input type="number" name="amount_requested" min="500" max="20000" step="100" required placeholder="0.00">
                                <div class="form-hint"><i class="fas fa-info-circle"></i> Maximum ₱20,000 subject to approval</div>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-file-invoice"></i></span>
                                    Tuition Fee Balance (if applicable)
                                </label>
                                <input type="number" name="tuition_fee" min="0" step="100" placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Purpose / Explanation <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="5" required placeholder="Explain why you need educational assistance. Include the student's academic performance, family financial situation, and how this will help..."></textarea>
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
                                        <div class="upload-zone-meta"><strong>Valid ID (Parent/Guardian)</strong><span>Government-issued ID</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-school_id">
                                    <input type="file" name="school_id" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-school_id')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-id-badge"></i></div>
                                        <div class="upload-zone-meta"><strong>Validated School ID</strong><span>Student's current school ID</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-enrollment_form">
                                    <input type="file" name="enrollment_form" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-enrollment_form')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta"><strong>Enrollment Assessment Form</strong><span>Certificate of Enrollment / Registration</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-statement_of_account">
                                    <input type="file" name="statement_of_account" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-statement_of_account')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-invoice"></i></div>
                                        <div class="upload-zone-meta"><strong>Statement of Account</strong><span>If there is a tuition balance</span></div>
                                        <span class="upload-optional-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-authorization_letter">
                                    <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-authorization_letter')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-signature"></i></div>
                                        <div class="upload-zone-meta"><strong>Authorization Letter</strong><span>If not the parent applying</span></div>
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
                                Submit Educational Assistance Application
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
                    <p>You already have a pending Educational Assistance application. Please wait for the staff to review your request before submitting another one.</p>
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

</body>
</html>
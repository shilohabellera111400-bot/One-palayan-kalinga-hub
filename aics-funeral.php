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
                WHERE r.user_id = :user_id AND r.request_type = 'funeral' 
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
    } elseif (!isset($_FILES['death_certificate']) || $_FILES['death_certificate']['error'] !== UPLOAD_ERR_OK) {
        $error = "A death certificate is required.";
    } elseif (floatval($_POST['amount_requested'] ?? 0) < 1000 || floatval($_POST['amount_requested'] ?? 0) > 50000) {
        $error = "Amount requested must be between ₱1,000 and ₱50,000.";
    } else {
        $assistance_type     = sanitize($_POST['assistance_type']);
        $amount_requested    = sanitize($_POST['amount_requested'] ?? 0);
        $purpose             = sanitize($_POST['purpose']);
        $deceased_name       = sanitize($_POST['deceased_name']);
        $funeral_home        = sanitize($_POST['funeral_home'] ?? '');
        $relationship        = sanitize($_POST['relationship']);
        $death_cert_no       = sanitize($_POST['death_cert_no'] ?? '');
        $funeral_contract_no = sanitize($_POST['funeral_contract_no'] ?? '');
        $transfer_permit_no  = sanitize($_POST['transfer_permit_no'] ?? '');
        
        $db->beginTransaction();
        
        try {
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, purpose, status) 
                  VALUES (:user_id, 'funeral', :amount, :purpose, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        $detail_query = "INSERT INTO aics_funeral_details 
                        (aics_id, assistance_type, deceased_name, relationship, funeral_home, 
                         death_certificate_no, funeral_contract_no, transfer_permit_no, purpose) 
                        VALUES (:aics_id, :assistance_type, :deceased_name, :relationship, :funeral_home,
                                :death_cert_no, :funeral_contract_no, :transfer_permit_no, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':assistance_type', $assistance_type);
        $detail_stmt->bindParam(':deceased_name', $deceased_name);
        $detail_stmt->bindParam(':relationship', $relationship);
        $detail_stmt->bindParam(':funeral_home', $funeral_home);
        $detail_stmt->bindParam(':death_cert_no', $death_cert_no);
        $detail_stmt->bindParam(':funeral_contract_no', $funeral_contract_no);
        $detail_stmt->bindParam(':transfer_permit_no', $transfer_permit_no);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id'            => 'Valid ID',
            'authorization_letter'=> 'Authorization Letter',
            'death_certificate'   => 'Death Certificate',
            'promissory_note'     => 'Promissory Note',
            'funeral_contract'    => 'Funeral Contract',
            'transfer_permit'     => 'Transfer Permit'
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
                        VALUES (:user_id, 'Funeral Assistance Submitted', 
                        'Your application has been submitted. Tracking #: $tracking', 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        
        $db->commit();
        $success = "Application submitted successfully! Your tracking number is: <strong>$tracking</strong>";
        $submission_success = true;
        $success_tracking_no = $tracking;
        $success_service_type = 'AICS';
        
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
                    'request_type' => 'funeral',
                    'user_id' => $_SESSION['user_id']
                ];
                $mailer->sendAICSConfirmation($user_info['email'], $user_name, $aics_data);
            }
        } catch (Exception $e) {
            error_log("AICS funeral submission email confirmation failed: " . $e->getMessage());
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
    <title>Funeral Assistance - AICS | Palayan-Kalinga Hub</title>
    <meta name="description" content="Apply for Funeral Assistance under AICS – support for funeral parlor bills, burial expenses, and cadaver transfer.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/aics-enhanced.css?v=<?php echo filemtime(__DIR__ . '/css/aics-enhanced.css'); ?>">
    <style>
        .aics-hero { position: relative !important; overflow: hidden !important; }
        .aics-hero::before, .aics-hero::after { pointer-events: none !important; }
        .hero-back-to-aics-btn, .hero-back-to-aics-btn:visited, .hero-back-to-aics-btn:active {
            color: #FFFFFF !important;
            text-decoration: none !important;
            cursor: pointer !important;
            pointer-events: auto !important;
            z-index: 9999 !important;
        }
        .hero-back-to-aics-btn:hover {
            background: #FFFFFF !important;
            color: #0F414A !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
        }
    </style>
    <style>
        :root {
            --accent: #4a148c;
            --accent-light: #7b1fa2;
            --accent-dark: #311b92;
            --accent-rgb: 74, 20, 140;
            --midnight: #0F414A;
            --midnight-light: #1a6a78;
        }
    
        /* Simple Clean Minimalist Footer */
        .aics-simple-footer {
            padding: 24px 0 32px;
            margin-top: 40px;
            text-align: center;
            border-top: 1px solid var(--ref-border, rgba(0, 0, 0, 0.08));
            color: var(--ref-muted, #71818B);
            font-size: 0.8rem;
            background: transparent;
        }
        .aics-simple-footer p {
            margin: 0;
        }
        [data-theme="dark"] .aics-simple-footer {
            border-color: rgba(255, 255, 255, 0.08);
            color: #8AA0A9;
        }

    </style>
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
</head>
<body>

    <!-- Unified Navigation Bar -->
        <div class="dashboard-container">
        <!-- Unified Minimalist Sidebar -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

    <div class="aics-hero">
    <a href="aics.php" class="hero-back-to-aics-btn" style="position: absolute !important; top: 22px !important; right: 26px !important; z-index: 9999 !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; background: rgba(255, 255, 255, 0.22) !important; color: #FFFFFF !important; border: 1.5px solid rgba(255, 255, 255, 0.45) !important; padding: 9px 20px !important; border-radius: 50px !important; font-size: 0.85rem !important; font-weight: 700 !important; text-decoration: none !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; cursor: pointer !important; pointer-events: auto !important; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2) !important;" title="Back to AICS Services"><i class="fas fa-arrow-left"></i> Back to AICS</a>
        <div class="aics-hero-inner">
            <div class="aics-hero-badge"><i class="fas fa-shield-alt"></i> AICS Program</div>
            <div class="aics-hero-icon"><i class="fas fa-cross"></i></div>
            <h1>Funeral / Burial Assistance</h1>
            <p>Financial support for funeral services and burial expenses of a loved one, provided with compassion and respect to grieving families.</p>
            <div class="aics-hero-stats">
                <div class="hero-stat"><strong>₱50,000</strong><span>Max Assistance</span></div>
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
                <p>Dalhin ang mga sumusunod base sa tulong na inilalapit.</p>
            </div>
            <div class="req-panel-body">

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-id-card"></i></span>
                        General Requirements
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Valid ID ng taong maglalakad / iinterviewhin</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Authorization Letter (kung hindi immediate family ang mag-a-apply)</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-cross"></i></span>
                        Para sa Funeral Bill
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Death Certificate (Original or Certified True Copy)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Promissory Note or Certificate of Balance</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Funeral Contract</li>
                    </ul>
                </div>

                <div class="req-group">
                    <div class="req-group-title">
                        <span class="group-icon"><i class="fas fa-truck"></i></span>
                        Para sa Transfer / Cadaver
                    </div>
                    <ul class="req-list">
                        <li><span class="check"><i class="fas fa-check"></i></span> Death Certificate (Original or Certified True Copy)</li>
                        <li><span class="check"><i class="fas fa-check"></i></span> Transfer Permit</li>
                    </ul>
                </div>

                <div class="req-note">
                    <div class="note-title"><i class="fas fa-exclamation-triangle"></i> Paalala</div>
                    <p>Siguraduhing kumpleto ang mga dokumento bago mag-apply upang mapabilis ang proseso ng inyong aplikasyon.</p>
                </div>
            </div>
        </aside>

        <div class="form-panel">
            <div class="form-panel-header">
                <h2>
                    <span class="panel-icon"><i class="fas fa-cross"></i></span>
                    Apply for Funeral Assistance
                </h2>
                <p>Fill in all required fields and upload the supporting documents for faster processing.</p>
            </div>

            <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
            <div class="form-panel-body">

                <div class="form-steps">
                    <div class="form-step active">
                        <span class="step-num">1</span>
                        <span class="step-label">Deceased Details</span>
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

                <form method="POST" enctype="multipart/form-data" id="applicationForm" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="submit_application" value="1">

                    <!-- STEP 1: Details -->
                    <div class="wizard-step" data-step="1">
                        <div class="form-section-title"><i class="fas fa-info-circle"></i> Assistance Type</div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-tag"></i></span>
                                Type of Funeral Assistance <span class="required-star">*</span>
                            </label>
                            <select name="assistance_type" id="assistance_type" required onchange="toggleFields()">
                                <option value="">— Select type of assistance —</option>
                                <option value="funeral_bill">Funeral Bill Payment</option>
                                <option value="transfer_assistance">Transfer / Cadaver Assistance</option>
                            </select>
                        </div>

                        <div class="form-section-title"><i class="fas fa-user"></i> Deceased Information</div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-user"></i></span>
                                    Name of Deceased <span class="required-star">*</span>
                                </label>
                                <input type="text" name="deceased_name" required placeholder="Full name of the deceased person">
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-heart"></i></span>
                                    Your Relationship to Deceased <span class="required-star">*</span>
                                </label>
                                <input type="text" name="relationship" required placeholder="e.g., Son, Daughter, Spouse">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-building"></i></span>
                                Funeral Home / Parlor
                            </label>
                            <input type="text" name="funeral_home" placeholder="Name of the funeral home (if applicable)">
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-peso-sign"></i></span>
                                    Amount Requested (₱) <span class="required-star">*</span>
                                </label>
                                <input type="number" name="amount_requested" min="1000" max="50000" step="100" required placeholder="0.00">
                                <div class="form-hint"><i class="fas fa-info-circle"></i> Maximum ₱50,000 subject to assessment and approval</div>
                            </div>
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-hashtag"></i></span>
                                    Death Certificate No.
                                </label>
                                <input type="text" name="death_cert_no" placeholder="Optional">
                            </div>
                        </div>

                        <div id="funeralBillFields">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>
                                        <span class="field-icon"><i class="fas fa-file-contract"></i></span>
                                        Funeral Contract No.
                                    </label>
                                    <input type="text" name="funeral_contract_no" placeholder="Optional">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <span class="field-icon"><i class="fas fa-file-invoice"></i></span>
                                        Promissory Note No.
                                    </label>
                                    <input type="text" name="promissory_note_no" placeholder="Optional">
                                </div>
                            </div>
                        </div>

                        <div id="transferFields" style="display:none;">
                            <div class="form-group">
                                <label>
                                    <span class="field-icon"><i class="fas fa-file-export"></i></span>
                                    Transfer Permit No.
                                </label>
                                <input type="text" name="transfer_permit_no" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <span class="field-icon"><i class="fas fa-align-left"></i></span>
                                Purpose / Explanation <span class="required-star">*</span>
                            </label>
                            <textarea name="purpose" rows="4" required placeholder="Briefly explain your situation and why you need this assistance..."></textarea>
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

                                <div class="upload-zone" id="zone-death_certificate">
                                    <input type="file" name="death_certificate" accept=".pdf,.jpg,.jpeg,.png" required onchange="markUploaded(this,'zone-death_certificate')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="upload-zone-meta"><strong>Death Certificate</strong><span>Original or Certified True Copy</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-funeral_contract">
                                    <input type="file" name="funeral_contract" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-funeral_contract')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-contract"></i></div>
                                        <div class="upload-zone-meta"><strong>Funeral Contract</strong><span>For Funeral Bill Payment</span></div>
                                        <span class="upload-optional-badge" id="funeral-contract-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-promissory_note">
                                    <input type="file" name="promissory_note" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-promissory_note')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-invoice"></i></div>
                                        <div class="upload-zone-meta"><strong>Promissory Note</strong><span>Certificate of Balance / SOA</span></div>
                                        <span class="upload-optional-badge" id="promissory-badge">Optional</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone dynamic-field" id="zone-transfer_permit">
                                    <input type="file" name="transfer_permit" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-transfer_permit')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-export"></i></div>
                                        <div class="upload-zone-meta"><strong>Transfer Permit</strong><span>For Cadaver Transfer Assistance</span></div>
                                        <span class="upload-required-badge">Required</span>
                                    </div>
                                    <div class="upload-zone-footer"><i class="fas fa-paperclip"></i> PDF, JPG, PNG — max 5MB</div>
                                </div>

                                <div class="upload-zone" id="zone-authorization_letter">
                                    <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png" onchange="markUploaded(this,'zone-authorization_letter')">
                                    <div class="upload-zone-header">
                                        <div class="upload-icon"><i class="fas fa-file-signature"></i></div>
                                        <div class="upload-zone-meta"><strong>Authorization Letter</strong><span>If not the immediate family member</span></div>
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
                            <button type="submit" name="submit_application" class="btn-submit" id="submitBtn">
                                <span class="btn-icon"><i class="fas fa-paper-plane"></i></span>
                                Submit Funeral Assistance Application
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
                    <p>You already have a pending Funeral Assistance application. Please wait for the staff to review your request before submitting another one.</p>
                    <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="btn-track">
                        <i class="fas fa-search"></i> Track Your Application
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

        <!-- Simple Minimal Portal Footer -->
    <footer class="aics-simple-footer">
        <p>&copy; <?php echo date('Y'); ?> <strong>One Palayan-Kalinga Hub</strong> &bull; City Social Welfare and Development Office</p>
    </footer>

    <script>
        function toggleFields() {
            const type = document.getElementById('assistance_type').value;
            const funeralBillFields = document.getElementById('funeralBillFields');
            const transferFields    = document.getElementById('transferFields');
            const transferZone      = document.getElementById('zone-transfer_permit');
            const funeralContractBadge = document.getElementById('funeral-contract-badge');
            const promissoryBadge   = document.getElementById('promissory-badge');

            if (type === 'funeral_bill') {
                funeralBillFields.style.display = 'block';
                transferFields.style.display    = 'none';
                transferZone.classList.remove('visible');
                funeralContractBadge.textContent = 'Required';
                funeralContractBadge.className   = 'upload-required-badge';
                promissoryBadge.textContent      = 'Required';
                promissoryBadge.className        = 'upload-required-badge';
            } else if (type === 'transfer_assistance') {
                funeralBillFields.style.display = 'none';
                transferFields.style.display    = 'block';
                transferZone.classList.add('visible');
                funeralContractBadge.textContent = 'Optional';
                funeralContractBadge.className   = 'upload-optional-badge';
                promissoryBadge.textContent      = 'Optional';
                promissoryBadge.className        = 'upload-optional-badge';
            } else {
                funeralBillFields.style.display = 'block';
                transferFields.style.display    = 'none';
                transferZone.classList.remove('visible');
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

        document.addEventListener('DOMContentLoaded', toggleFields);

        document.querySelectorAll('.upload-zone').forEach(zone => {
            zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
            zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
            zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
        });

        // Submit handling & declaration validation
        const appForm = document.getElementById('applicationForm');
        const declCheck = document.getElementById('declaration-check');
        const submitBtn = document.getElementById('submitBtn');

        if (appForm) {
            appForm.addEventListener('submit', function(e) {
                if (declCheck && !declCheck.checked) {
                    e.preventDefault();
                    declCheck.focus();
                    const confBox = document.querySelector('.summary-confirmation');
                    if (confBox) {
                        confBox.style.border = '2px solid #DC2626';
                        confBox.style.borderRadius = '12px';
                        confBox.style.padding = '10px';
                        setTimeout(() => { confBox.style.border = ''; }, 3500);
                    }
                    alert('Paki-tsek ang declaration checkbox upang kumpirmahin ang iyong aplikasyon.');
                    return false;
                }
                if (submitBtn) {
                    submitBtn.style.pointerEvents = 'none';
                    submitBtn.style.opacity = '0.75';
                    submitBtn.innerHTML = '<span class="btn-icon"><i class="fas fa-spinner fa-spin"></i></span> Submitting...';
                    setTimeout(() => { submitBtn.disabled = true; }, 100);
                }
            });
        }
    </script>

        </div>
    </div>

    <!-- Include Wizard Logic JS -->
    <script src="js/aics-wizard.js"></script>

    <!-- Succession Modal -->
    <?php include __DIR__ . '/includes/submission-success-modal.php'; ?>
</body>
</html>
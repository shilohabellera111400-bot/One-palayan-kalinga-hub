<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Only residents can apply for AICS
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Check if user has pending application
$check_query = "SELECT r.* FROM aics_requests r 
                WHERE r.user_id = :user_id AND r.request_type = 'funeral' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $assistance_type = sanitize($_POST['assistance_type']);
    $amount_requested = sanitize($_POST['amount_requested'] ?? 0);
    $purpose = sanitize($_POST['purpose']);
    $deceased_name = sanitize($_POST['deceased_name']);
    $funeral_home = sanitize($_POST['funeral_home'] ?? '');
    $relationship = sanitize($_POST['relationship']);
    $death_cert_no = sanitize($_POST['death_cert_no'] ?? '');
    $funeral_contract_no = sanitize($_POST['funeral_contract_no'] ?? '');
    $transfer_permit_no = sanitize($_POST['transfer_permit_no'] ?? '');
    
    $db->beginTransaction();
    
    try {
        // Insert into main aics_requests table
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'funeral', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        // Insert into funeral details table
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
        
        // Upload documents
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id' => 'Valid ID',
            'authorization_letter' => 'Authorization Letter',
            'death_certificate' => 'Death Certificate',
            'promissory_note' => 'Promissory Note',
            'funeral_contract' => 'Funeral Contract',
            'transfer_permit' => 'Transfer Permit'
        ];
        
        foreach ($document_fields as $field => $doc_name) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES[$field]['name'],
                    'type' => $_FILES[$field]['type'],
                    'tmp_name' => $_FILES[$field]['tmp_name'],
                    'error' => $_FILES[$field]['error'],
                    'size' => $_FILES[$field]['size']
                ];
                $file_path = $uploader->upload($file, 'aics');
                // Use correct column names: aics_doc_id, aics_id, document_type, document_name, document_path, upload_date
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
        
        // Create notification
        $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'Funeral Assistance Submitted', 
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
    <title>Funeral Assistance - AICS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--gray-50); }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        .simple-header { background: var(--white); padding: 16px 24px; border-bottom: 1px solid var(--gray-200); position: sticky; top: 0; z-index: 100; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--gray-50); padding: 10px 20px; border-radius: 40px; text-decoration: none; color: var(--midnight); font-weight: 500; transition: all 0.3s; border: 1px solid var(--gray-200); }
        .back-btn:hover { background: var(--midnight); color: white; transform: translateX(-3px); }
        .page-header { background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%); color: white; padding: 60px 0; text-align: center; }
        .page-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .page-header p { font-size: 0.9rem; opacity: 0.9; }
        .content-wrapper { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; padding: 60px 0; }
        .requirements-section { background: white; border-radius: var(--radius-card); padding: 28px; box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); position: sticky; top: 100px; height: fit-content; }
        .requirements-section h2 { color: var(--midnight); margin-bottom: 20px; border-bottom: 2px solid var(--maroon); padding-bottom: 10px; }
        .requirements-list { list-style: none; padding: 0; }
        .requirements-list li { padding: 8px 0; padding-left: 25px; position: relative; color: var(--gray-600); border-bottom: 1px dashed var(--gray-200); }
        .requirements-list li:before { content: "✓"; position: absolute; left: 0; color: var(--maroon); font-weight: bold; }
        .note-box { background: rgba(216,186,152,0.15); padding: 15px; border-radius: var(--radius-element); margin-top: 20px; border-left: 4px solid #D8BA98; }
        .application-section { background: white; border-radius: var(--radius-card); padding: 28px; box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); }
        .application-section h2 { color: var(--midnight); margin-bottom: 20px; border-bottom: 2px solid var(--maroon); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--gray-700); font-weight: 600; font-size: 0.85rem; }
        .form-group label i { color: var(--maroon); margin-right: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid var(--gray-200); border-radius: var(--radius-element); font-size: 0.95rem; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--midnight); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .document-upload { background: var(--gray-50); padding: 15px; border-radius: var(--radius-element); margin-bottom: 15px; border: 1px solid var(--gray-200); }
        .document-upload label { font-weight: 600; display: block; margin-bottom: 8px; }
        .document-upload small { display: block; margin-top: 5px; color: var(--gray-600); font-size: 0.7rem; }
        .btn-submit { background: var(--maroon); color: white; padding: 14px; border: none; border-radius: 40px; font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: var(--maroon-light); transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: var(--radius-element); margin-bottom: 20px; }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
        .alert-info { background: #CCE5FF; color: #004085; border: 1px solid #B8DAFF; }
        .pending-card { background: #FFF3CD; border: 1px solid #FFC107; border-radius: var(--radius-element); padding: 15px; margin-bottom: 20px; }
        .pending-card a { background: var(--maroon); color: white; padding: 8px 20px; border-radius: 40px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-top: 10px; }
        .pending-card a:hover { background: var(--maroon-light); }
        @media (max-width: 992px) { .content-wrapper { grid-template-columns: 1fr; } .requirements-section { position: static; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="simple-header">
        <div class="container">
            <a href="aics.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to AICS</a>
        </div>
    </div>
    
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-cross"></i> Funeral / Burial Assistance</h1>
            <p>Financial support for funeral services and burial expenses of a loved one</p>
        </div>
    </div>
    
    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($pending_app && $pending_app['status'] != 'rejected'): ?>
            <div class="pending-card">
                <i class="fas fa-clock"></i> <strong>You have a pending application!</strong>
                <p>Your application is currently <strong><?php echo ucfirst($pending_app['status']); ?></strong>. Please wait for staff review.</p>
                <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>">
                    <i class="fas fa-search"></i> Track Application
                </a>
            </div>
        <?php endif; ?>
        
        <div class="content-wrapper">
            <!-- Requirements Section -->
            <div class="requirements-section">
                <h2><i class="fas fa-clipboard-list"></i> Mga Kinakailangan</h2>
                <div class="note-box">
                    <strong><i class="fas fa-info-circle"></i> Note:</strong>
                    <p>Dalhin ang mga sumusunod base sa tulong na inilalapit.</p>
                </div>
                
                <h3 style="margin-top: 20px;">📋 General Requirements:</h3>
                <ul class="requirements-list">
                    <li>Valid ID ng taong maglalakad/iinterviewhin</li>
                    <li>Authorization Letter (if applicable)</li>
                </ul>
                
                <h3>⚰️ Para sa Funeral Bill:</h3>
                <ul class="requirements-list">
                    <li>Death Certificate (Original or Certified True Copy)</li>
                    <li>Promissory Note or Certificate of Balance</li>
                    <li>Funeral Contract</li>
                </ul>
                
                <h3>🚛 Para sa Transfer/Cadaver Assistance:</h3>
                <ul class="requirements-list">
                    <li>Death Certificate (Original or Certified True Copy)</li>
                    <li>Transfer Permit</li>
                </ul>
                
                <div class="note-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> Paalala:</strong>
                    <p>Siguraduhing kumpleto ang mga dokumento bago mag-apply upang mapabilis ang proseso.</p>
                </div>
            </div>
            
            <!-- Application Form -->
            <div class="application-section">
                <h2><i class="fas fa-paper-plane"></i> Apply for Funeral Assistance</h2>
                
                <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Type of Assistance *</label>
                        <select name="assistance_type" id="assistance_type" required onchange="toggleFields()">
                            <option value="">-- Select type --</option>
                            <option value="funeral_bill">Funeral Bill Payment</option>
                            <option value="transfer_assistance">Transfer / Cadaver Assistance</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user-injured"></i> Name of Deceased *</label>
                            <input type="text" name="deceased_name" required placeholder="Full name of deceased person">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-heart"></i> Relationship to Deceased *</label>
                            <input type="text" name="relationship" required placeholder="e.g., Parent, Spouse, Sibling">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Funeral Home</label>
                        <input type="text" name="funeral_home" placeholder="Name of funeral home (if applicable)">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-money-bill"></i> Amount Requested *</label>
                            <input type="number" name="amount_requested" min="1000" max="50000" step="100" required placeholder="Enter amount">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Death Certificate No.</label>
                            <input type="text" name="death_cert_no" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div id="funeralBillFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-file-contract"></i> Funeral Contract No.</label>
                                <input type="text" name="funeral_contract_no" placeholder="Optional">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-file-invoice"></i> Promissory Note No.</label>
                                <input type="text" name="promissory_note_no" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <div id="transferFields" style="display: none;">
                        <div class="form-group">
                            <label><i class="fas fa-file-export"></i> Transfer Permit No.</label>
                            <input type="text" name="transfer_permit_no" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Purpose / Explanation *</label>
                        <textarea name="purpose" rows="4" required placeholder="Explain your situation and why you need this assistance..."></textarea>
                    </div>
                    
                    <h3 style="margin: 25px 0 15px; color: var(--midnight);">
                        <i class="fas fa-upload"></i> Upload Required Documents
                    </h3>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-id-card"></i> Valid ID *</label>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Government-issued ID (PDF, JPG, PNG up to 5MB)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-alt"></i> Death Certificate *</label>
                        <input type="file" name="death_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Death Certificate or Certification (Original or Certified True Copy)</small>
                    </div>
                    
                    <div class="document-upload" id="funeralContractUpload">
                        <label><i class="fas fa-file-contract"></i> Funeral Contract</label>
                        <input type="file" name="funeral_contract" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Funeral service contract (required for Funeral Bill Payment)</small>
                    </div>
                    
                    <div class="document-upload" id="promissoryNoteUpload">
                        <label><i class="fas fa-file-invoice"></i> Promissory Note</label>
                        <input type="file" name="promissory_note" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Certificate of Balance or Statement of Account (required for Funeral Bill Payment)</small>
                    </div>
                    
                    <div class="document-upload" id="transferPermitUpload" style="display: none;">
                        <label><i class="fas fa-file-export"></i> Transfer Permit</label>
                        <input type="file" name="transfer_permit" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Permit for cadaver transfer (required for Transfer Assistance)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-signature"></i> Authorization Letter (if applicable)</label>
                        <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png">
                        <small>If not the immediate family member applying</small>
                    </div>
                    
                    <button type="submit" name="submit_application" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You already have a pending application. Please wait for staff review before submitting another.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFields() {
            const type = document.getElementById('assistance_type').value;
            const funeralBillFields = document.getElementById('funeralBillFields');
            const transferFields = document.getElementById('transferFields');
            const funeralContractUpload = document.getElementById('funeralContractUpload');
            const promissoryNoteUpload = document.getElementById('promissoryNoteUpload');
            const transferPermitUpload = document.getElementById('transferPermitUpload');
            
            if (type === 'funeral_bill') {
                funeralBillFields.style.display = 'block';
                transferFields.style.display = 'none';
                funeralContractUpload.style.display = 'block';
                promissoryNoteUpload.style.display = 'block';
                transferPermitUpload.style.display = 'none';
                if (document.querySelector('input[name="funeral_contract"]')) {
                    document.querySelector('input[name="funeral_contract"]').required = true;
                }
                if (document.querySelector('input[name="promissory_note"]')) {
                    document.querySelector('input[name="promissory_note"]').required = true;
                }
            } else if (type === 'transfer_assistance') {
                funeralBillFields.style.display = 'none';
                transferFields.style.display = 'block';
                funeralContractUpload.style.display = 'none';
                promissoryNoteUpload.style.display = 'none';
                transferPermitUpload.style.display = 'block';
                if (document.querySelector('input[name="funeral_contract"]')) {
                    document.querySelector('input[name="funeral_contract"]').required = false;
                }
                if (document.querySelector('input[name="promissory_note"]')) {
                    document.querySelector('input[name="promissory_note"]').required = false;
                }
                if (document.querySelector('input[name="transfer_permit"]')) {
                    document.querySelector('input[name="transfer_permit"]').required = true;
                }
            } else {
                funeralBillFields.style.display = 'block';
                transferFields.style.display = 'none';
                funeralContractUpload.style.display = 'block';
                promissoryNoteUpload.style.display = 'block';
                transferPermitUpload.style.display = 'none';
                if (document.querySelector('input[name="funeral_contract"]')) {
                    document.querySelector('input[name="funeral_contract"]').required = false;
                }
                if (document.querySelector('input[name="promissory_note"]')) {
                    document.querySelector('input[name="promissory_note"]').required = false;
                }
                if (document.querySelector('input[name="transfer_permit"]')) {
                    document.querySelector('input[name="transfer_permit"]').required = false;
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFields();
        });
    </script>
</body>
</html>
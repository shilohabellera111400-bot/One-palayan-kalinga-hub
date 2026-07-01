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
                WHERE r.user_id = :user_id AND r.request_type = 'cash_relief' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $amount_requested = sanitize($_POST['amount_requested']);
    $purpose = sanitize($_POST['purpose']);
    $emergency_type = sanitize($_POST['emergency_type']);
    $affected_members = sanitize($_POST['affected_members'] ?? 1);
    $incident_date = sanitize($_POST['incident_date'] ?? '');
    $incident_report_no = sanitize($_POST['incident_report_no'] ?? '');
    $police_report_no = sanitize($_POST['police_report_no'] ?? '');
    
    $db->beginTransaction();
    
    try {
        // Insert into main aics_requests table (NO purpose column)
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'cash_relief', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        // Insert into cash relief details table (NO purpose column)
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
        
        // Upload documents
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id' => 'Valid ID',
            'barangay_certificate' => 'Barangay Certificate',
            'incident_report' => 'Incident Report',
            'medical_certificate' => 'Medical Certificate',
            'police_report' => 'Police Report'
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
    <title>Cash Relief Assistance - AICS</title>
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
        .amount-info { background: rgba(216,186,152,0.15); padding: 15px; border-radius: var(--radius-element); margin-bottom: 20px; text-align: center; }
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
            <h1><i class="fas fa-money-bill-wave"></i> Cash Relief Assistance</h1>
            <p>Financial assistance for individuals and families in crisis situation</p>
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
                <h2><i class="fas fa-clipboard-list"></i> Requirements</h2>
                <div class="note-box">
                    <strong><i class="fas fa-info-circle"></i> Note:</strong>
                    <p>Cash Relief Assistance para sa mga indibidwal at pamilyang nakakaranas ng krisis.</p>
                </div>
                
                <div class="amount-info">
                    <strong><i class="fas fa-chart-line"></i> Maximum Amount:</strong>
                    <p style="font-size: 1.5rem; margin-top: 5px;">₱5,000 - ₱10,000</p>
                    <small>Batay sa assessment ng social worker</small>
                </div>
                
                <h3 style="margin-top: 20px;">📋 General Requirements:</h3>
                <ul class="requirements-list">
                    <li>Valid ID ng aplikante</li>
                    <li>Barangay Certificate of Residency and Indigency</li>
                    <li>Letter of Request explaining the situation</li>
                </ul>
                
                <h3>🚨 Para sa Emergency Situations:</h3>
                <ul class="requirements-list">
                    <li>Incident Report (kung sakuna o aksidente)</li>
                    <li>Medical Certificate (kung may sakit)</li>
                    <li>Police Report (kung biktima ng krimen)</li>
                </ul>
                
                <div class="note-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> Paalala:</strong>
                    <p>Ang cash relief ay ibinibigay matapos ang masusing assessment at approval ng CSWDO.</p>
                </div>
            </div>
            
            <!-- Application Form -->
            <div class="application-section">
                <h2><i class="fas fa-paper-plane"></i> Apply for Cash Relief</h2>
                
                <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-exclamation-triangle"></i> Type of Emergency *</label>
                        <select name="emergency_type" id="emergency_type" required onchange="toggleEmergencyFields()">
                            <option value="">Select type</option>
                            <option value="medical">Medical Emergency</option>
                            <option value="disaster">Natural Disaster (Fire, Flood, etc.)</option>
                            <option value="accident">Accident</option>
                            <option value="crime">Victim of Crime</option>
                            <option value="financial">Financial Crisis</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Number of Affected Family Members *</label>
                        <input type="number" name="affected_members" min="1" max="20" value="1" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Incident Date</label>
                            <input type="date" name="incident_date">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> Incident Report No.</label>
                            <input type="text" name="incident_report_no" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Amount Requested (₱) *</label>
                        <input type="number" name="amount_requested" min="500" max="10000" step="100" required>
                        <small>Maximum amount: ₱10,000 (subject to approval)</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Explanation of Situation *</label>
                        <textarea name="purpose" rows="5" required placeholder="Explain your situation and why you need cash relief assistance..."></textarea>
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
                        <label><i class="fas fa-file-alt"></i> Barangay Certificate *</label>
                        <input type="file" name="barangay_certificate" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Barangay Clearance or Certificate of Indigency</small>
                    </div>
                    
                    <div id="incidentReportField" class="document-upload">
                        <label><i class="fas fa-file-alt"></i> Incident Report</label>
                        <input type="file" name="incident_report" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Barangay Incident Report (required for disasters/accidents)</small>
                    </div>
                    
                    <div id="medicalField" class="document-upload" style="display: none;">
                        <label><i class="fas fa-file-medical"></i> Medical Certificate</label>
                        <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Medical Certificate (required for medical emergencies)</small>
                    </div>
                    
                    <div id="policeField" class="document-upload" style="display: none;">
                        <label><i class="fas fa-file-alt"></i> Police Report</label>
                        <input type="file" name="police_report" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Police Report (required for crime victims)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-signature"></i> Authorization Letter (if applicable)</label>
                        <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png">
                        <small>If not the person applying</small>
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
        function toggleEmergencyFields() {
            const type = document.getElementById('emergency_type').value;
            const incidentField = document.getElementById('incidentReportField');
            const medicalField = document.getElementById('medicalField');
            const policeField = document.getElementById('policeField');
            
            // Incident report is always shown (optional)
            incidentField.style.display = 'block';
            
            if (type === 'medical') {
                medicalField.style.display = 'block';
                policeField.style.display = 'none';
                // Make medical certificate required
                document.querySelector('input[name="medical_certificate"]').required = true;
                document.querySelector('input[name="incident_report"]').required = false;
                document.querySelector('input[name="police_report"]').required = false;
            } else if (type === 'crime') {
                policeField.style.display = 'block';
                medicalField.style.display = 'none';
                // Make police report required
                document.querySelector('input[name="police_report"]').required = true;
                document.querySelector('input[name="incident_report"]').required = false;
                document.querySelector('input[name="medical_certificate"]').required = false;
            } else if (type === 'disaster' || type === 'accident') {
                medicalField.style.display = 'none';
                policeField.style.display = 'none';
                // Make incident report required
                document.querySelector('input[name="incident_report"]').required = true;
                document.querySelector('input[name="medical_certificate"]').required = false;
                document.querySelector('input[name="police_report"]').required = false;
            } else {
                medicalField.style.display = 'none';
                policeField.style.display = 'none';
                document.querySelector('input[name="incident_report"]').required = false;
                document.querySelector('input[name="medical_certificate"]').required = false;
                document.querySelector('input[name="police_report"]').required = false;
            }
        }
    </script>
</body>
</html>
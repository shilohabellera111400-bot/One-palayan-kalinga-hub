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
    $assistance_type = sanitize($_POST['assistance_type']);
    $amount_requested = sanitize($_POST['amount_requested']);
    $purpose = sanitize($_POST['purpose']);
    $hospital_name = sanitize($_POST['hospital_name'] ?? '');
    $doctor_name = sanitize($_POST['doctor_name'] ?? '');
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert AICS request
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, purpose, status, created_at) 
                  VALUES (:user_id, 'medical', :amount, :purpose, 'submitted', NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        // Handle document uploads
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id' => 'Valid ID',
            'medical_certificate' => 'Medical Certificate',
            'hospital_bill' => 'Hospital Bill',
            'prescription' => 'Prescription',
            'laboratory_request' => 'Laboratory Request',
            'quotation' => 'Quotation'
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
                
                $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_path) 
                              VALUES (:aics_id, :doc_type, :path)";
                $doc_stmt = $db->prepare($doc_query);
                $doc_stmt->bindParam(':aics_id', $aics_id);
                $doc_stmt->bindParam(':doc_type', $doc_name);
                $doc_stmt->bindParam(':path', $file_path);
                $doc_stmt->execute();
            }
        }
        
        // Create notification
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES (:user_id, 'AICS Application Submitted', 
                        'Your Medical Assistance application has been submitted successfully. Tracking #: AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT) . "', 'aics')";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        
        $db->commit();
        
        $success = "Application submitted successfully! Your tracking number is: <strong>AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT) . "</strong>";
        
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
    <title>Medical Assistance - AICS</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--gray-50); color: var(--gray-600); }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

        /* Simple Header with Back Button */
        .simple-header {
            background: var(--white);
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gray-50);
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--midnight);
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }
        .back-btn:hover {
            background: var(--midnight);
            color: white;
            transform: translateX(-3px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        .page-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .page-header p { font-size: 1rem; opacity: 0.9; }

        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
            padding: 60px 0;
        }

        .requirements-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        .requirements-section h2 {
            color: var(--midnight);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--maroon);
        }
        .requirements-section h3 {
            color: var(--midnight);
            margin: 20px 0 10px;
            font-size: 1rem;
        }
        .requirements-list {
            list-style: none;
            padding: 0;
        }
        .requirements-list li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
            color: var(--gray-600);
            border-bottom: 1px dashed var(--gray-200);
        }
        .requirements-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--maroon);
            font-weight: bold;
        }
        .note-box {
            background: rgba(216,186,152,0.15);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-top: 20px;
            border-left: 4px solid var(--tan);
        }

        .application-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        .application-section h2 {
            color: var(--midnight);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--maroon);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .form-group label i { color: var(--maroon); margin-right: 5px; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-element);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--midnight);
            box-shadow: 0 0 0 3px rgba(15,65,74,0.1);
        }
        .document-upload {
            background: var(--gray-50);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-bottom: 15px;
        }
        .document-upload label { font-weight: 600; color: var(--midnight); }
        .document-upload small { display: block; color: var(--gray-600); margin-top: 5px; font-size: 0.7rem; }
        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-submit:hover { background: var(--maroon-light); transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: var(--radius-element); margin-bottom: 20px; }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
        .alert-info { background: #CCE5FF; color: #004085; border: 1px solid #B8DAFF; }
        .pending-card {
            background: #FFF3CD;
            border: 1px solid #FFC107;
            border-radius: var(--radius-element);
            padding: 15px;
            margin-bottom: 20px;
        }

        @media (max-width: 992px) {
            .content-wrapper { grid-template-columns: 1fr; }
            .requirements-section { position: static; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 40px 0; }
            .page-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Simple Header with Back Button -->
    <div class="simple-header">
        <div class="container">
            <a href="services.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Services
            </a>
        </div>
    </div>

    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-hospital-user"></i> Medical Assistance</h1>
            <p>Financial assistance for hospital bills, medicines, laboratory tests, and medical procedures</p>
        </div>
    </div>

    <div class="container">
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($pending_app && $pending_app['status'] != 'rejected'): ?>
            <div class="pending-card">
                <i class="fas fa-clock"></i> <strong>You have a pending application!</strong>
                <p>Your application is currently <strong><?php echo ucfirst($pending_app['status']); ?></strong>. Please wait for the staff to review your request.</p>
                <a href="track-aics.php?tracking=AICS-<?php echo str_pad($pending_app['aics_id'], 6, '0', STR_PAD_LEFT); ?>" class="btn-submit" style="display: inline-block; width: auto; margin-top: 10px;">Track Application</a>
            </div>
        <?php endif; ?>

        <div class="content-wrapper">
            <!-- Requirements Section -->
            <div class="requirements-section">
                <h2><i class="fas fa-clipboard-list"></i> Requirements</h2>
                <div class="note-box"><strong><i class="fas fa-info-circle"></i> Note:</strong><p>Dalhin ang mga sumusunod base sa tulong na inilalapit.</p></div>
                <h3>📋 General Requirements:</h3>
                <ul class="requirements-list"><li>Valid ID ng taong maglalakad/iinterviewhin</li><li>Authorization Letter (if applicable)</li></ul>
                <h3>🏥 Para sa Hospital Bill:</h3>
                <ul class="requirements-list"><li>Medical Certificate/Clinical Abstract/Discharge Summary</li><li>Hospital Bill or Statement of Account</li><li>Certificate of Balance and Promissory Note</li><li>Social Case Study Report or Case Summary</li></ul>
                <h3>💊 Para sa Gamot o Assistive Device:</h3>
                <ul class="requirements-list"><li>Medical Certificate/Clinical Abstract</li><li>Prescription with date and signature of Physician</li><li>Quotation of Medicine or Assistive Device (if amount > ₱10,000)</li></ul>
                <div class="note-box"><strong><i class="fas fa-exclamation-triangle"></i> Paalala:</strong><p>Siguraduhing kumpleto ang mga dokumento bago mag-apply upang mapabilis ang proseso.</p></div>
            </div>

            <!-- Application Form -->
            <div class="application-section">
                <h2><i class="fas fa-paper-plane"></i> Apply for Medical Assistance</h2>
                <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Type of Assistance *</label>
                        <select name="assistance_type" required>
                            <option value="">Select type</option>
                            <option value="hospital_bill">Hospital Bill Payment</option>
                            <option value="medicine">Medicine / Assistive Device</option>
                            <option value="laboratory">Laboratory / Medical Procedure</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hospital"></i> Hospital Name</label>
                        <input type="text" name="hospital_name" placeholder="Enter hospital name">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Amount Requested *</label>
                        <input type="number" name="amount_requested" min="100" max="100000" step="100" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Purpose / Explanation *</label>
                        <textarea name="purpose" rows="4" required placeholder="Explain your situation..."></textarea>
                    </div>
                    <h3 style="margin: 20px 0 15px; color: var(--midnight);"><i class="fas fa-upload"></i> Upload Required Documents</h3>
                    <div class="document-upload"><label><i class="fas fa-id-card"></i> Valid ID *</label><input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required><small>Government-issued ID (PDF, JPG, PNG up to 5MB)</small></div>
                    <div class="document-upload"><label><i class="fas fa-file-medical"></i> Medical Certificate *</label><input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png" required><small>Clinical Abstract or Discharge Summary</small></div>
                    <div class="document-upload" id="hospitalBillField"><label><i class="fas fa-file-invoice"></i> Hospital Bill</label><input type="file" name="hospital_bill" accept=".pdf,.jpg,.jpeg,.png"><small>Statement of Account or Certificate of Balance</small></div>
                    <button type="submit" name="submit_application" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Application</button>
                </form>
                <?php else: ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> You already have a pending application. Please wait for the staff to review your request before submitting another.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
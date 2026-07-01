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
                WHERE r.user_id = :user_id AND r.request_type = 'transportation' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $amount_requested = sanitize($_POST['amount_requested']);
    $purpose = sanitize($_POST['purpose']);
    $destination = sanitize($_POST['destination']);
    $travel_date = sanitize($_POST['travel_date']);
    $number_of_persons = sanitize($_POST['number_of_persons']);
    $reason_type = sanitize($_POST['reason_type']);
    $estimated_fare = sanitize($_POST['estimated_fare'] ?? 0);
    
    $db->beginTransaction();
    
    try {
        // Insert into main aics_requests table (NO purpose column)
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'transportation', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        // Insert into transportation details table
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
        
        // Upload documents
        $uploader = new FileUploader();
        $document_fields = [
            'valid_id' => 'Valid ID',
            'authorization_letter' => 'Authorization Letter',
            'medical_certificate' => 'Medical Certificate',
            'death_certificate' => 'Death Certificate',
            'court_order' => 'Court Order or Subpoena'
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
                        VALUES (:user_id, 'Transportation Assistance Submitted', 
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
    <title>Transportation Assistance - AICS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --teal: #00897b;
            --teal-light: #009688;
            --maroon: #7F0303;
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
        
        /* Header */
        .simple-header { background: var(--white); padding: 16px 24px; border-bottom: 1px solid var(--gray-200); position: sticky; top: 0; z-index: 100; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--gray-50); padding: 10px 20px; border-radius: 40px; text-decoration: none; color: var(--midnight); font-weight: 500; transition: all 0.3s; border: 1px solid var(--gray-200); }
        .back-btn:hover { background: var(--midnight); color: white; transform: translateX(-3px); }
        
        .page-header { background: linear-gradient(135deg, var(--teal) 0%, var(--teal-light) 100%); color: white; padding: 60px 0; text-align: center; }
        .page-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .page-header p { font-size: 0.9rem; opacity: 0.9; }
        
        .content-wrapper { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; padding: 60px 0; }
        
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
        .requirements-section h2 { color: var(--teal); margin-bottom: 20px; border-bottom: 2px solid var(--teal); padding-bottom: 10px; }
        .requirements-list { list-style: none; padding: 0; }
        .requirements-list li { padding: 8px 0; padding-left: 25px; position: relative; color: var(--gray-600); border-bottom: 1px dashed var(--gray-200); }
        .requirements-list li:before { content: "✓"; position: absolute; left: 0; color: var(--teal); font-weight: bold; }
        .note-box { background: rgba(0,137,123,0.1); padding: 15px; border-radius: var(--radius-element); margin-top: 20px; border-left: 4px solid var(--teal); }
        
        .application-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        .application-section h2 { color: var(--teal); margin-bottom: 20px; border-bottom: 2px solid var(--teal); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--gray-700); font-weight: 600; font-size: 0.85rem; }
        .form-group label i { color: var(--teal); margin-right: 5px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-element);
            font-size: 0.95rem;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--teal);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .document-upload {
            background: var(--gray-50);
            padding: 15px;
            border-radius: var(--radius-element);
            margin-bottom: 15px;
            border: 1px solid var(--gray-200);
        }
        .document-upload label { font-weight: 600; display: block; margin-bottom: 8px; }
        .document-upload small { display: block; margin-top: 5px; color: var(--gray-600); font-size: 0.7rem; }
        .btn-submit {
            background: var(--teal);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-submit:hover { background: var(--teal-light); transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: var(--radius-element); margin-bottom: 20px; }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
        .alert-info { background: #CCE5FF; color: #004085; border: 1px solid #B8DAFF; }
        .pending-card { background: #FFF3CD; border: 1px solid #FFC107; border-radius: var(--radius-element); padding: 15px; margin-bottom: 20px; }
        .pending-card a { background: var(--teal); color: white; padding: 8px 20px; border-radius: 40px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-top: 10px; }
        
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
            <h1><i class="fas fa-bus"></i> Transportation Assistance</h1>
            <p>Financial assistance for transportation expenses related to medical appointments, legal matters, or other urgent needs</p>
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
                    <p>Transportation Assistance para sa mga indibidwal na nangangailangan ng suporta sa pamasahe.</p>
                </div>
                
                <h3 style="margin-top: 20px;">📋 Pangunahing Requirements:</h3>
                <ul class="requirements-list">
                    <li>Valid ID ng taong maglalakad/iinterviewhin</li>
                    <li>Authorization Letter (if applicable)</li>
                </ul>
                
                <h3>📄 Supporting Documents (kung naaangkop):</h3>
                <ul class="requirements-list">
                    <li>Medical Certificate (kung para sa medical appointment)</li>
                    <li>Death Certificate (kung para sa libing)</li>
                    <li>Court Order or Subpoena (kung para sa legal na usapin)</li>
                </ul>
                
                <div class="note-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> Paalala:</strong>
                    <p>Ang transportation assistance ay ibinibigay batay sa distansya, bilang ng tao, at layunin ng biyahe.</p>
                </div>
            </div>
            
            <!-- Application Form -->
            <div class="application-section">
                <h2><i class="fas fa-paper-plane"></i> Apply for Transportation Assistance</h2>
                
                <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Reason for Transportation *</label>
                        <select name="reason_type" id="reasonType" required>
                            <option value="">Select reason</option>
                            <option value="medical">Medical Appointment / Hospital Visit</option>
                            <option value="legal">Legal Matter / Court Hearing</option>
                            <option value="funeral">Funeral / Burial Attendance</option>
                            <option value="emergency">Family Emergency</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Destination / Location *</label>
                        <input type="text" name="destination" required placeholder="e.g., Philippine General Hospital, Manila">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-day"></i> Travel Date *</label>
                            <input type="date" name="travel_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Number of Persons *</label>
                            <input type="number" name="number_of_persons" min="1" max="10" value="1" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-money-bill"></i> Amount Requested (₱) *</label>
                            <input type="number" name="amount_requested" min="100" max="5000" step="50" required>
                            <small>Maximum: ₱5,000 (subject to approval)</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-receipt"></i> Estimated Fare (₱)</label>
                            <input type="number" name="estimated_fare" min="0" step="50" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Explanation / Purpose *</label>
                        <textarea name="purpose" rows="4" required placeholder="Explain why you need transportation assistance..."></textarea>
                    </div>
                    
                    <h3 style="margin: 25px 0 15px; color: var(--teal);">
                        <i class="fas fa-upload"></i> Upload Required Documents
                    </h3>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-id-card"></i> Valid ID *</label>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>Government-issued ID</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-signature"></i> Authorization Letter</label>
                        <input type="file" name="authorization_letter" accept=".pdf,.jpg,.jpeg,.png">
                        <small>If not the person travelling</small>
                    </div>
                    
                    <div id="medicalDoc" style="display: none;">
                        <div class="document-upload">
                            <label><i class="fas fa-file-medical"></i> Medical Certificate</label>
                            <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png">
                            <small>Medical certificate or appointment slip</small>
                        </div>
                    </div>
                    
                    <div id="legalDoc" style="display: none;">
                        <div class="document-upload">
                            <label><i class="fas fa-gavel"></i> Court Order / Subpoena</label>
                            <input type="file" name="court_order" accept=".pdf,.jpg,.jpeg,.png">
                            <small>Court order or subpoena</small>
                        </div>
                    </div>
                    
                    <div id="funeralDoc" style="display: none;">
                        <div class="document-upload">
                            <label><i class="fas fa-cross"></i> Death Certificate</label>
                            <input type="file" name="death_certificate" accept=".pdf,.jpg,.jpeg,.png">
                            <small>Death certificate of deceased</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_application" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You already have a pending application. Please wait for staff review.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('reasonType')?.addEventListener('change', function() {
            const reason = this.value;
            const medicalDoc = document.getElementById('medicalDoc');
            const legalDoc = document.getElementById('legalDoc');
            const funeralDoc = document.getElementById('funeralDoc');
            
            medicalDoc.style.display = 'none';
            legalDoc.style.display = 'none';
            funeralDoc.style.display = 'none';
            
            if (reason === 'medical') medicalDoc.style.display = 'block';
            else if (reason === 'legal') legalDoc.style.display = 'block';
            else if (reason === 'funeral') funeralDoc.style.display = 'block';
        });
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
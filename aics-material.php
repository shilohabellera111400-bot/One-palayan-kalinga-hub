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
                WHERE r.user_id = :user_id AND r.request_type = 'material' 
                AND r.status IN ('submitted', 'reviewing', 'approved')
                ORDER BY r.submission_date DESC LIMIT 1";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$pending_app = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_application'])) {
    $client_type = sanitize($_POST['client_type']);
    $amount_requested = sanitize($_POST['amount_requested'] ?? 0);
    $purpose = sanitize($_POST['purpose']);
    $materials_needed = sanitize($_POST['materials_needed']);
    $group_name = sanitize($_POST['group_name'] ?? '');
    $number_of_members = sanitize($_POST['number_of_members'] ?? 1);
    $estimated_value = sanitize($_POST['amount_requested'] ?? 0);
    
    $db->beginTransaction();
    
    try {
        // Insert into main aics_requests table (NO purpose column)
        $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, status) 
                  VALUES (:user_id, 'material', :amount, 'submitted')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':amount', $amount_requested);
        $stmt->execute();
        
        $aics_id = $db->lastInsertId();
        
        // Insert into material details table
        $detail_query = "INSERT INTO aics_material_details 
                        (aics_id, client_type, group_name, number_of_members, materials_requested, estimated_value, purpose) 
                        VALUES (:aics_id, :client_type, :group_name, :members, :materials, :estimated, :purpose)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->bindParam(':aics_id', $aics_id);
        $detail_stmt->bindParam(':client_type', $client_type);
        $detail_stmt->bindParam(':group_name', $group_name);
        $detail_stmt->bindParam(':members', $number_of_members);
        $detail_stmt->bindParam(':materials', $materials_needed);
        $detail_stmt->bindParam(':estimated', $estimated_value);
        $detail_stmt->bindParam(':purpose', $purpose);
        $detail_stmt->execute();
        
        // Upload documents
        $uploader = new FileUploader();
        $document_fields = [
            'general_intake' => 'General Intake Sheet (GIS)',
            'material_sheet' => 'Material Distribution Sheet (MDS)',
            'case_summary' => 'Case Summary'
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
                        VALUES (:user_id, 'Material Assistance Submitted', 
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
    <title>Material Assistance - AICS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --purple: #6a1b9a;
            --purple-light: #7b1fa2;
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
        .back-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--gray-50); padding: 10px 20px; border-radius: 40px; text-decoration: none; color: var(--gray-600); font-weight: 500; transition: all 0.3s; border: 1px solid var(--gray-200); }
        .back-btn:hover { background: var(--purple); color: white; transform: translateX(-3px); border-color: var(--purple); }
        
        .page-header { background: linear-gradient(135deg, var(--purple) 0%, var(--purple-light) 100%); color: white; padding: 60px 0; text-align: center; }
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
        .requirements-section h2 { color: var(--purple); margin-bottom: 20px; border-bottom: 2px solid var(--purple); padding-bottom: 10px; }
        .requirements-list { list-style: none; padding: 0; }
        .requirements-list li { padding: 8px 0; padding-left: 25px; position: relative; color: var(--gray-600); border-bottom: 1px dashed var(--gray-200); }
        .requirements-list li:before { content: "✓"; position: absolute; left: 0; color: var(--purple); font-weight: bold; }
        .note-box { background: rgba(106,27,154,0.1); padding: 15px; border-radius: var(--radius-element); margin-top: 20px; border-left: 4px solid var(--purple); }
        
        .application-section {
            background: white;
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        .application-section h2 { color: var(--purple); margin-bottom: 20px; border-bottom: 2px solid var(--purple); padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--gray-700); font-weight: 600; font-size: 0.85rem; }
        .form-group label i { color: var(--purple); margin-right: 5px; }
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
            border-color: var(--purple);
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
            background: var(--purple);
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
        .btn-submit:hover { background: var(--purple-light); transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: var(--radius-element); margin-bottom: 20px; }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
        .alert-info { background: #CCE5FF; color: #004085; border: 1px solid #B8DAFF; }
        .pending-card { background: #FFF3CD; border: 1px solid #FFC107; border-radius: var(--radius-element); padding: 15px; margin-bottom: 20px; }
        .pending-card a { background: var(--purple); color: white; padding: 8px 20px; border-radius: 40px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-top: 10px; }
        
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
            <h1><i class="fas fa-boxes"></i> Material Assistance</h1>
            <p>Non-food items, hygiene kits, sleeping materials, and other essential supplies for individuals and families in need</p>
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
                    <p>Material Assistance para sa mga indibidwal at grupo na nangangailangan ng non-food items.</p>
                </div>
                
                <h3 style="margin-top: 20px;">👤 Para sa Indibidwal na Kliyente:</h3>
                <ul class="requirements-list">
                    <li>General Intake Sheet (GIS)</li>
                    <li>Material Distribution Sheet (MDS)</li>
                </ul>
                
                <h3>👥 Para sa Grupo na Kliyente:</h3>
                <ul class="requirements-list">
                    <li>General Intake Sheet (GIS)</li>
                    <li>Case Summary</li>
                    <li>Material Distribution Sheet (MDS)</li>
                </ul>
                
                <h3>📦 Karaniwang Materyales na Ibinibigay:</h3>
                <ul class="requirements-list">
                    <li>Hygiene Kits (sabon, shampoo, toothpaste, toothbrush)</li>
                    <li>Sleeping Materials (banig, kumot, unan)</li>
                    <li>Clothing (damit para sa pamilya)</li>
                    <li>School Supplies (para sa mga estudyante)</li>
                    <li>Other essential non-food items</li>
                </ul>
                
                <div class="note-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> Paalala:</strong>
                    <p>Ang material assistance ay ibinibigay batay sa assessment ng social worker at availability ng supplies.</p>
                </div>
            </div>
            
            <!-- Application Form -->
            <div class="application-section">
                <h2><i class="fas fa-paper-plane"></i> Apply for Material Assistance</h2>
                
                <?php if(!$pending_app || $pending_app['status'] == 'rejected'): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Client Type *</label>
                        <select name="client_type" id="clientType" required>
                            <option value="">Select client type</option>
                            <option value="individual">Individual Client (Indibidwal)</option>
                            <option value="group">Group Client (Grupo)</option>
                        </select>
                    </div>
                    
                    <div id="groupFields" style="display: none;">
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Group/Organization Name</label>
                            <input type="text" name="group_name" placeholder="Enter group or organization name">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-friends"></i> Number of Members</label>
                            <input type="number" name="number_of_members" min="2" max="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-clipboard-list"></i> Materials Needed *</label>
                        <textarea name="materials_needed" rows="3" required placeholder="List down the materials/items you need..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-money-bill"></i> Estimated Amount (₱)</label>
                        <input type="number" name="amount_requested" min="0" step="100">
                        <small>Estimated value of materials requested (optional)</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Reason for Assistance *</label>
                        <textarea name="purpose" rows="4" required placeholder="Explain why you need material assistance..."></textarea>
                    </div>
                    
                    <h3 style="margin: 25px 0 15px; color: var(--purple);">
                        <i class="fas fa-upload"></i> Upload Required Documents
                    </h3>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-file-alt"></i> General Intake Sheet (GIS) *</label>
                        <input type="file" name="general_intake" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>GIS form (PDF, JPG, PNG up to 5MB)</small>
                    </div>
                    
                    <div class="document-upload">
                        <label><i class="fas fa-table"></i> Material Distribution Sheet (MDS) *</label>
                        <input type="file" name="material_sheet" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>MDS form (PDF, JPG, PNG up to 5MB)</small>
                    </div>
                    
                    <div id="caseSummaryField" style="display: none;">
                        <div class="document-upload">
                            <label><i class="fas fa-file-alt"></i> Case Summary (for Group Client) *</label>
                            <input type="file" name="case_summary" accept=".pdf,.jpg,.jpeg,.png">
                            <small>Case summary document (required for group clients)</small>
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
        document.getElementById('clientType')?.addEventListener('change', function() {
            const type = this.value;
            const groupFields = document.getElementById('groupFields');
            const caseSummaryField = document.getElementById('caseSummaryField');
            
            if (type === 'group') {
                groupFields.style.display = 'block';
                caseSummaryField.style.display = 'block';
                if (document.querySelector('input[name="case_summary"]')) {
                    document.querySelector('input[name="case_summary"]').required = true;
                }
                if (document.querySelector('input[name="group_name"]')) {
                    document.querySelector('input[name="group_name"]').required = true;
                }
                if (document.querySelector('input[name="number_of_members"]')) {
                    document.querySelector('input[name="number_of_members"]').required = true;
                }
            } else {
                groupFields.style.display = 'none';
                caseSummaryField.style.display = 'none';
                if (document.querySelector('input[name="case_summary"]')) {
                    document.querySelector('input[name="case_summary"]').required = false;
                }
                if (document.querySelector('input[name="group_name"]')) {
                    document.querySelector('input[name="group_name"]').required = false;
                }
                if (document.querySelector('input[name="number_of_members"]')) {
                    document.querySelector('input[name="number_of_members"]').required = false;
                }
            }
        });
    </script>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
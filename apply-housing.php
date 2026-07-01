<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get available housing programs
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute();

$error = '';
$success = '';

// Get selected program from URL if any
$selected_program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $program_id = sanitize($_POST['program_id']);
    $household_size = sanitize($_POST['household_size']);
    $monthly_income = sanitize($_POST['monthly_income']);
    $housing_need = sanitize($_POST['housing_need']);
    
    // Validate required documents
    $required_docs = ['letter_intent', 'barangay_clearance', 'valid_id', 'consent_letter', 'house_photo', 'proof_billing', 'tax_declaration'];
    $missing_docs = [];
    
    foreach ($required_docs as $doc) {
        if (!isset($_FILES[$doc]) || $_FILES[$doc]['error'] === UPLOAD_ERR_NO_FILE) {
            $missing_docs[] = ucwords(str_replace('_', ' ', $doc));
        }
    }
    
    if (!empty($missing_docs)) {
        $error = "Missing required documents: " . implode(', ', $missing_docs);
    } else {
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Check if user already has an application
            $check_query = "SELECT beneficiary_id FROM housing_beneficiaries 
                            WHERE user_id = :user_id AND program_id = :program_id 
                            AND status IN ('pending', 'qualified', 'approved')";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->bindParam(':program_id', $program_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("You already have an active application for this program.");
            }
            
            // Insert housing application
            $query = "INSERT INTO housing_beneficiaries 
                      (user_id, program_id, household_composition, monthly_income, housing_need_assessment, status) 
                      VALUES (:user_id, :program_id, :household, :income, :need, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':program_id', $program_id);
            $stmt->bindParam(':household', $household_size);
            $stmt->bindParam(':income', $monthly_income);
            $stmt->bindParam(':need', $housing_need);
            $stmt->execute();
            
            $beneficiary_id = $db->lastInsertId();
            
            // Handle document uploads
            $uploader = new FileUploader();
            $document_types = [
                'letter_intent' => 'Letter of Intent',
                'barangay_clearance' => 'Barangay Clearance',
                'valid_id' => 'Valid ID',
                'consent_letter' => 'Affidavit of Consent',
                'house_photo' => 'House Photo',
                'proof_billing' => 'Proof of Billing',
                'tax_declaration' => 'Tax Declaration',
                'additional' => 'Additional Document'
            ];
            
            foreach ($document_types as $field => $doc_type) {
                if ($field === 'additional') {
                    if (!empty($_FILES['additional']['name'][0])) {
                        $files = $_FILES['additional'];
                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $files['name'][$i],
                                    'type' => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error' => $files['error'][$i],
                                    'size' => $files['size'][$i]
                                ];
                                $file_path = $uploader->upload($file, 'housing');
                                $doc_query = "INSERT INTO documents (user_id, document_type, document_path, reference_id) 
                                              VALUES (:user_id, :doc_type, :path, :ref_id)";
                                $doc_stmt = $db->prepare($doc_query);
                                $doc_stmt->bindParam(':user_id', $_SESSION['user_id']);
                                $doc_stmt->bindParam(':doc_type', $doc_type);
                                $doc_stmt->bindParam(':path', $file_path);
                                $doc_stmt->bindParam(':ref_id', $beneficiary_id);
                                $doc_stmt->execute();
                            }
                        }
                    }
                } elseif (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES[$field]['name'],
                        'type' => $_FILES[$field]['type'],
                        'tmp_name' => $_FILES[$field]['tmp_name'],
                        'error' => $_FILES[$field]['error'],
                        'size' => $_FILES[$field]['size']
                    ];
                    $file_path = $uploader->upload($file, 'housing');
                    $doc_query = "INSERT INTO documents (user_id, document_type, document_path, reference_id) 
                                  VALUES (:user_id, :doc_type, :path, :ref_id)";
                    $doc_stmt = $db->prepare($doc_query);
                    $doc_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $doc_stmt->bindParam(':doc_type', $doc_type);
                    $doc_stmt->bindParam(':path', $file_path);
                    $doc_stmt->bindParam(':ref_id', $beneficiary_id);
                    $doc_stmt->execute();
                }
            }
            
            // Create notification
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'Housing Application Submitted', 
                            'Your housing application has been submitted and is pending review.', 'housing')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            
            $db->commit();
            $success = "Housing application submitted successfully! You will be notified once reviewed.";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Application failed: " . $e->getMessage();
        }
    }
}

// Get selected program details
$selected_program = null;
if ($selected_program_id) {
    $prog_stmt = $db->prepare("SELECT * FROM housing_programs WHERE program_id = ?");
    $prog_stmt->execute([$selected_program_id]);
    $selected_program = $prog_stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Housing - One Palayan-Kalinga Hub</title>
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
            --gray-100: #F1F3F8;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--gray-50); }

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
            padding: 50px 0;
            text-align: center;
        }
        .page-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .page-header p { font-size: 1rem; opacity: 0.9; }

        .apply-housing-container { padding: 50px 0; }

        .requirements-banner {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 20px 25px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .requirements-banner h3 { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 1rem; }
        .requirements-banner .btn-light {
            background: white;
            color: var(--maroon);
            padding: 10px 25px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .requirements-banner .btn-light:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }

        .program-selection {
            background: white;
            padding: 30px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            border: 1px solid var(--gray-200);
        }
        .program-selection h2 { color: var(--midnight); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.3rem; }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin: 20px 0;
        }
        .program-card {
            background: var(--gray-50);
            padding: 25px;
            border-radius: var(--radius-element);
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        .program-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--light-blue); }
        .program-card.selected { border-color: var(--maroon); background: rgba(127,3,3,0.05); }
        .program-card.selected::before {
            content: "✓";
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--maroon);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }
        .program-card h3 { color: var(--midnight); margin-bottom: 12px; font-size: 1.2rem; }
        .program-card .location { color: var(--maroon); font-size: 0.85rem; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
        .program-card .units { color: #4CAF50; font-weight: bold; margin-bottom: 10px; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
        .program-card .description { color: var(--gray-600); font-size: 0.85rem; line-height: 1.5; }

        .application-form {
            background: white;
            padding: 35px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        .application-form h2 { color: var(--midnight); margin-bottom: 10px; font-size: 1.3rem; }
        .form-subtitle { color: var(--gray-600); margin-bottom: 30px; font-size: 0.9rem; }

        .selected-program-info {
            background: rgba(127,3,3,0.08);
            padding: 20px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .selected-program-info p { margin: 0; color: var(--midnight); font-weight: 600; }
        .change-program-btn {
            background: transparent;
            border: 2px solid var(--maroon);
            color: var(--maroon);
            padding: 8px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .change-program-btn:hover { background: var(--maroon); color: white; }

        .form-section {
            background: var(--gray-50);
            padding: 25px;
            border-radius: var(--radius-element);
            margin-bottom: 25px;
        }
        .form-section h3 {
            color: var(--midnight);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-element);
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--midnight);
            box-shadow: 0 0 0 3px rgba(15,65,74,0.1);
        }
        small { color: var(--gray-600); font-size: 0.7rem; display: block; margin-top: 5px; }

        .document-upload-section {
            background: var(--gray-50);
            padding: 25px;
            border-radius: var(--radius-element);
            margin-bottom: 25px;
        }
        .document-upload-section h3 { color: var(--midnight); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.1rem; }
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .upload-item {
            background: white;
            padding: 20px;
            border-radius: var(--radius-element);
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }
        .upload-item:hover { border-color: var(--light-blue); box-shadow: var(--shadow-sm); }
        .upload-item label { display: block; margin-bottom: 12px; font-weight: 600; color: var(--gray-700); }
        .upload-item label i { color: var(--maroon); margin-right: 8px; }
        .upload-item input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .upload-item .file-info { font-size: 0.7rem; color: var(--gray-600); margin-top: 8px; }
        .upload-item.required label::after { content: " *"; color: #f44336; }

        .additional-docs {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: var(--radius-element);
            border: 2px dashed var(--maroon);
        }
        .additional-docs label { font-weight: 600; color: var(--midnight); margin-bottom: 10px; display: block; }
        .additional-docs small { margin-top: 8px; }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            flex: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-primary:hover { background: var(--maroon-light); transform: translateY(-2px); }
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 14px 30px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-secondary:hover { background: var(--gray-300); transform: translateY(-2px); }

        .info-box {
            background: linear-gradient(135deg, rgba(150,192,206,0.1) 0%, rgba(15,65,74,0.05) 100%);
            padding: 25px;
            border-radius: var(--radius-element);
            margin-top: 30px;
            border-left: 4px solid var(--light-blue);
        }
        .info-box h4 { color: var(--midnight); margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .info-box ul { margin-left: 20px; color: var(--gray-600); }
        .info-box li { margin-bottom: 10px; }

        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
        .alert-error { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }

        @media (max-width: 768px) {
            .page-header { padding: 40px 0; }
            .page-header h1 { font-size: 1.5rem; }
            .programs-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .upload-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .requirements-banner { flex-direction: column; text-align: center; }
            .selected-program-info { flex-direction: column; text-align: center; }
            .application-form { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- Simple Header with Back Button -->
    <div class="simple-header">
        <div class="container">
            <a href="housing.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Housing Programs
            </a>
        </div>
    </div>

    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-home"></i> Apply for Housing Program</h1>
            <p>Complete the application form below. Make sure you have all required documents ready.</p>
        </div>
    </div>

    <div class="apply-housing-container">
        <div class="container">
            <!-- Requirements Banner -->
            <div class="requirements-banner">
                <h3><i class="fas fa-file-alt"></i> Required Documents: Letter of Intent, Barangay Clearance, Valid ID, House Photos, Proof of Billing</h3>
                <a href="housing-requirements.php" class="btn-light" target="_blank"><i class="fas fa-eye"></i> View Complete List</a>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Program Selection -->
            <div class="program-selection" id="programSelection" <?php echo $selected_program_id ? 'style="display: none;"' : ''; ?>>
                <h2><i class="fas fa-list"></i> Select a Housing Program</h2>
                <div class="programs-grid">
                    <?php 
                    $programs_stmt->execute();
                    while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                    ?>
                    <div class="program-card <?php echo $selected_program_id == $program['program_id'] ? 'selected' : ''; ?>" 
                         onclick="selectProgram(<?php echo $program['program_id']; ?>, '<?php echo htmlspecialchars($program['program_name']); ?>')">
                        <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($program['location']); ?></p>
                        <p class="units"><i class="fas fa-building"></i> <?php echo $program['total_units']; ?> units available</p>
                        <p class="description"><?php echo htmlspecialchars($program['description']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Application Form -->
            <div class="application-form" id="applicationForm" style="<?php echo $selected_program_id ? 'display: block;' : 'display: none;'; ?>">
                <h2><i class="fas fa-edit"></i> Housing Application Form</h2>
                <p class="form-subtitle">Please fill out all required fields and upload the necessary documents.</p>

                <form method="POST" enctype="multipart/form-data" id="housingForm">
                    <input type="hidden" id="program_id" name="program_id" value="<?php echo $selected_program_id; ?>">
                    
                    <div class="selected-program-info" id="selectedProgram">
                        <?php if($selected_program): ?>
                            <p><i class="fas fa-check-circle" style="color: #4CAF50;"></i> Selected Program: <strong><?php echo htmlspecialchars($selected_program['program_name']); ?></strong></p>
                        <?php else: ?>
                            <p><i class="fas fa-info-circle"></i> Please select a program above</p>
                        <?php endif; ?>
                        <button type="button" class="change-program-btn" onclick="showProgramSelection()"><i class="fas fa-edit"></i> Change Program</button>
                    </div>

                    <!-- Household Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-users"></i> Household Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="household_size"><i class="fas fa-users"></i> Household Size *</label>
                                <input type="number" id="household_size" name="household_size" min="1" max="20" required placeholder="Number of family members">
                                <small>Total number of family members living with you</small>
                            </div>
                            <div class="form-group">
                                <label for="monthly_income"><i class="fas fa-money-bill-wave"></i> Monthly Income (₱) *</label>
                                <input type="number" id="monthly_income" name="monthly_income" min="0" step="500" required placeholder="0.00">
                                <small>Combined monthly income of all household members</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="housing_need"><i class="fas fa-home"></i> Housing Need Assessment *</label>
                            <textarea id="housing_need" name="housing_need" rows="5" placeholder="Describe your current housing situation and why you need assistance..." required></textarea>
                            <small>Include details about your current living conditions, housing problem, and why you're applying</small>
                        </div>
                    </div>

                    <!-- Document Upload Section -->
                    <div class="document-upload-section">
                        <h3><i class="fas fa-upload"></i> Upload Required Documents</h3>
                        <div class="upload-grid">
                            <div class="upload-item required">
                                <label for="letter_intent"><i class="fas fa-file-alt"></i> Letter of Intent / Application</label>
                                <input type="file" id="letter_intent" name="letter_intent" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">PDF, JPG, PNG (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="barangay_clearance"><i class="fas fa-file-signature"></i> Barangay Clearance</label>
                                <input type="file" id="barangay_clearance" name="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">PDF, JPG, PNG (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="valid_id"><i class="fas fa-id-card"></i> Valid ID</label>
                                <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Government-issued ID (Max 5MB)</div>
                            </div>
                            <div class="upload-item">
                                <label for="consent_letter"><i class="fas fa-file-signature"></i> Affidavit of Consent</label>
                                <input type="file" id="consent_letter" name="consent_letter" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">If building on relative's land (Optional)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="house_photo"><i class="fas fa-camera"></i> House Photos</label>
                                <input type="file" id="house_photo" name="house_photo" accept=".jpg,.jpeg,.png" required>
                                <div class="file-info">Photos of current housing situation (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="proof_billing"><i class="fas fa-file-invoice"></i> Proof of Billing</label>
                                <input type="file" id="proof_billing" name="proof_billing" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Recent utility bill (Max 5MB)</div>
                            </div>
                            <div class="upload-item">
                                <label for="tax_declaration"><i class="fas fa-file-invoice-dollar"></i> Tax Declaration</label>
                                <input type="file" id="tax_declaration" name="tax_declaration" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">If applicable (Optional)</div>
                            </div>
                        </div>

                        <div class="additional-docs">
                            <label for="additional"><i class="fas fa-plus-circle"></i> Additional Documents (Optional)</label>
                            <input type="file" id="additional" name="additional[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            <small>Upload any other supporting documents (You can select multiple files)</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Submit Application</button>
                        <button type="button" onclick="cancelApplication()" class="btn-secondary"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Information Box -->
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Important Reminders</h4>
                <ul>
                    <li><strong>File Format:</strong> All documents must be uploaded in PDF, JPG, or PNG format</li>
                    <li><strong>File Size:</strong> Maximum file size is 5MB per file</li>
                    <li><strong>Photo Requirements:</strong> House photos should show the current condition of your housing</li>
                    <li><strong>Clear Documents:</strong> Make sure all documents are clear and readable</li>
                    <li><strong>Notification:</strong> You will receive a notification once your application is reviewed</li>
                    <li><strong>Processing Time:</strong> Please allow 7-14 business days for application review</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    let selectedProgramId = <?php echo $selected_program_id ?: 0; ?>;

    function selectProgram(id, name) {
        selectedProgramId = id;
        document.getElementById('program_id').value = id;
        document.getElementById('selectedProgram').innerHTML = `
            <p><i class="fas fa-check-circle" style="color: #4CAF50;"></i> Selected Program: <strong>${name}</strong></p>
            <button type="button" class="change-program-btn" onclick="showProgramSelection()"><i class="fas fa-edit"></i> Change Program</button>
        `;
        document.getElementById('programSelection').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'block';
        document.getElementById('applicationForm').scrollIntoView({ behavior: 'smooth' });
        document.querySelectorAll('.program-card').forEach(card => card.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
    }

    function showProgramSelection() {
        document.getElementById('programSelection').style.display = 'block';
        document.getElementById('applicationForm').style.display = 'none';
        document.getElementById('programSelection').scrollIntoView({ behavior: 'smooth' });
    }

    function cancelApplication() {
        if (confirm('Are you sure you want to cancel? All entered data will be lost.')) {
            window.location.href = 'housing.php';
        }
    }
    </script>
</body>
</html>
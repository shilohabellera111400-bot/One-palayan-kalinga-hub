<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get available housing programs
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute();

$error = '';
$success = '';

// Get selected program from URL if any
$selected_program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $program_id = sanitize($_POST['program_id']);
    $household_size = sanitize($_POST['household_size']);
    $monthly_income = sanitize($_POST['monthly_income']);
    $housing_need = sanitize($_POST['housing_need']);
    
    // Validate required documents
    $required_docs = ['letter_intent', 'barangay_clearance', 'valid_id', 'house_photo', 'proof_billing'];
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
            $success = "Housing application submitted successfully! You can monitor the progress on your dashboard.";
            
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
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Housing - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE & GLOBAL THEMING ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gold: #C9A86C;
            --gold-light: #E8D5A8;
            --light-blue: #96C0CE;
            --alabaster: #E3E7EB;
            
            --bg-primary: #E8ECF2;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #D5DBE1;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 10px 30px rgba(15, 65, 74, 0.09), 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 16px 40px rgba(15, 65, 74, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --radius-card: 24px;
            --radius-element: 16px;
            
            --color-success: #2e7d32;
            --color-warning: #ed6c02;
            --color-danger: #d32f2f;
            --color-info: #0288d1;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-card: #1a2332;
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --border-color: #2a3a4f;
            --alabaster: #161e2a;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.5);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.15);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
            
            --color-success: #4caf50;
            --color-warning: #ff9800;
            --color-danger: #f44336;
            --color-info: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            transition: background 0.4s ease, color 0.4s ease;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ========== HERO & HEADER ========== */
        .apply-page {
            padding: 130px 0 80px;
            min-height: 85vh;
        }

        .apply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
            animation: fadeInDown 0.6s ease forwards;
        }

        .header-title h1 {
            color: var(--text-primary);
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-card);
            padding: 10px 22px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1.5px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            border-color: var(--maroon);
            color: var(--maroon);
            transform: translateX(-3px);
        }

        [data-theme="dark"] .btn-back:hover {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        /* ========== REQUIREMENTS ALERT BANNER ========== */
        .requirements-banner {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: white;
            padding: 20px 28px;
            border-radius: var(--radius-element);
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: var(--shadow-md);
            animation: fadeInScale 0.4s ease forwards;
        }

        .requirements-banner h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .requirements-banner .btn-view-reqs {
            background: white;
            color: var(--maroon);
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            white-space: nowrap;
        }

        .requirements-banner .btn-view-reqs:hover {
            background: var(--alabaster);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 18px 24px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow-sm);
            animation: fadeInScale 0.4s ease forwards;
        }

        .alert-error {
            background: rgba(211, 47, 47, 0.08);
            color: var(--color-danger);
            border: 1px solid rgba(211, 47, 47, 0.15);
        }

        .alert-success {
            background: rgba(46, 125, 50, 0.08);
            color: var(--color-success);
            border: 1px solid rgba(46, 125, 50, 0.15);
        }

        /* ========== PROGRAM SELECTION GRID ========== */
        .program-selection-box {
            background: var(--bg-card);
            padding: 35px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            margin-bottom: 35px;
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease forwards;
        }

        .program-selection-box h2 {
            color: var(--text-primary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            font-weight: 800;
        }

        .program-selection-box h2 i {
            color: var(--maroon);
        }

        [data-theme="dark"] .program-selection-box h2 i {
            color: var(--gold-light);
        }

        .programs-select-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .program-select-card {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: var(--radius-element);
            border: 2px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }

        .program-select-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-sm);
            border-color: var(--maroon-light);
        }

        [data-theme="dark"] .program-select-card:hover {
            border-color: var(--gold-light);
        }

        .program-select-card.selected {
            border-color: var(--maroon);
            background: rgba(127, 3, 3, 0.04);
        }

        [data-theme="dark"] .program-select-card.selected {
            border-color: var(--gold-light);
            background: rgba(232, 213, 168, 0.04);
        }

        .program-select-card.selected::before {
            content: "✓";
            position: absolute;
            top: -12px;
            right: -12px;
            background: var(--maroon);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.3);
            border: 2px solid var(--bg-card);
        }

        [data-theme="dark"] .program-select-card.selected::before {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 10px rgba(232, 213, 168, 0.3);
        }

        .program-select-card h3 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 1.15rem;
            font-weight: 750;
        }

        .program-select-card .location {
            color: var(--maroon);
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        [data-theme="dark"] .program-select-card .location {
            color: var(--gold-light);
        }

        .program-select-card .units {
            color: var(--color-success);
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .program-select-card .description {
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.5;
            font-weight: 500;
        }

        /* ========== APPLICATION FORM PANEL ========== */
        .application-form-panel {
            background: var(--bg-card);
            padding: 40px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease forwards;
        }

        .application-form-panel h2 {
            color: var(--text-primary);
            margin-bottom: 6px;
            font-size: 1.4rem;
            font-weight: 800;
        }

        .form-subtitle {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Selected Info Alert */
        .selected-program-info {
            background: rgba(127, 3, 3, 0.06);
            padding: 18px 24px;
            border-radius: var(--radius-element);
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid rgba(127,3,3,0.1);
        }

        [data-theme="dark"] .selected-program-info {
            background: rgba(232, 213, 168, 0.05);
            border-color: rgba(232, 213, 168, 0.1);
        }

        .selected-program-info p {
            margin: 0;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .selected-program-info p i {
            color: var(--color-success);
        }

        .btn-change-program {
            background: transparent;
            border: 2px solid var(--maroon);
            color: var(--maroon);
            padding: 8px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .btn-change-program {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        .btn-change-program:hover {
            background: var(--maroon);
            color: white;
        }

        [data-theme="dark"] .btn-change-program:hover {
            background: var(--gold-light);
            color: var(--midnight);
        }

        /* Form Sections */
        .form-section {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .form-section h3 {
            color: var(--text-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 800;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-section h3 i {
            color: var(--maroon);
        }

        [data-theme="dark"] .form-section h3 i {
            color: var(--gold-light);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.88rem;
        }

        .form-group label i {
            color: var(--maroon);
            margin-right: 4px;
        }

        [data-theme="dark"] .form-group label i {
            color: var(--gold-light);
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.95rem;
            color: var(--text-primary);
            background: var(--bg-card);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--midnight);
            box-shadow: 0 0 0 4px rgba(15, 65, 74, 0.08);
        }

        [data-theme="dark"] .form-group input:focus, 
        [data-theme="dark"] .form-group textarea:focus, 
        [data-theme="dark"] .form-group select:focus {
            border-color: var(--gold-light);
            box-shadow: 0 0 0 4px rgba(232, 213, 168, 0.08);
        }

        .form-group small {
            color: var(--text-muted);
            font-size: 0.75rem;
            display: block;
            margin-top: 6px;
            font-weight: 500;
        }

        /* ========== UPLOAD CARDS GRID ========== */
        .upload-section {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: var(--radius-element);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .upload-section h3 {
            color: var(--text-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .upload-item {
            background: var(--bg-card);
            padding: 24px;
            border-radius: var(--radius-element);
            border: 2px dashed var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .upload-item:hover {
            border-color: var(--maroon-light);
            box-shadow: var(--shadow-sm);
        }

        [data-theme="dark"] .upload-item:hover {
            border-color: var(--gold-light);
        }

        .upload-item.has-file {
            border-color: var(--color-success);
            background: rgba(46, 125, 50, 0.02);
        }

        .upload-item label {
            display: block;
            margin-bottom: 12px;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.92rem;
            cursor: pointer;
        }

        .upload-item label i {
            color: var(--maroon);
            margin-right: 8px;
            font-size: 1.1rem;
        }

        [data-theme="dark"] .upload-item label i {
            color: var(--gold-light);
        }

        .upload-item input[type="file"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-primary);
            color: var(--text-secondary);
            cursor: pointer;
        }

        .upload-item .file-info {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 8px;
            font-weight: 500;
        }

        .upload-item.required label::after {
            content: " *";
            color: var(--color-danger);
            font-weight: bold;
        }

        /* File Display indicators */
        .file-name-display {
            margin-top: 10px;
            padding: 8px 12px;
            background: rgba(46, 125, 50, 0.08);
            color: var(--color-success);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            word-break: break-all;
            animation: fadeInScale 0.3s ease;
        }

        .additional-docs-box {
            margin-top: 24px;
            padding: 24px;
            background: var(--bg-card);
            border-radius: var(--radius-element);
            border: 2px dashed var(--border-color);
            transition: all 0.3s;
        }

        .additional-docs-box:hover {
            border-color: var(--maroon-light);
        }

        [data-theme="dark"] .additional-docs-box:hover {
            border-color: var(--gold-light);
        }

        .additional-docs-box label {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: block;
        }

        /* ========== ACTION BUTTONS ========== */
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 35px;
        }

        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 15px 36px;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(127, 3, 3, 0.25);
        }

        .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.35);
        }

        .btn-cancel {
            background: var(--bg-primary);
            color: var(--text-secondary);
            padding: 15px 36px;
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1.5px solid var(--border-color);
        }

        .btn-cancel:hover {
            background: var(--border-color);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        /* Reminders Info Box */
        .reminders-box {
            background: linear-gradient(135deg, rgba(150, 192, 206, 0.12) 0%, rgba(15, 65, 74, 0.05) 100%);
            padding: 30px;
            border-radius: var(--radius-element);
            margin-top: 35px;
            border-left: 5px solid var(--light-blue);
            border: 1px solid var(--border-color);
            border-left: 5px solid var(--light-blue);
        }

        .reminders-box h4 {
            color: var(--text-primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 1.05rem;
        }

        .reminders-box ul {
            margin-left: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .reminders-box li {
            margin-bottom: 10px;
        }

        .reminders-box li:last-child {
            margin-bottom: 0;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Responsive Overrides */
        @media (max-width: 768px) {
            .apply-page { padding: 110px 0 60px; }
            .apply-header { flex-direction: column; align-items: stretch; text-align: center; }
            .btn-back { justify-content: center; width: 100%; }
            .programs-select-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .upload-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .requirements-banner { flex-direction: column; text-align: center; }
            .selected-program-info { flex-direction: column; text-align: center; }
            .application-form-panel { padding: 25px 20px; }
        }
    </style>
</head>
<body>
    <!-- Navbar Include -->
    <?php include 'includes/navbar.php'; ?>

    <div class="apply-page">
        <div class="container">
            
            <!-- Page Header with Back Link -->
            <div class="apply-header">
                <div class="header-title">
                    <h1>Housing Application</h1>
                    <p>Provide family metrics and submit certified photocopies of requirements.</p>
                </div>
                <a href="housing.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Communities
                </a>
            </div>

            <!-- Documents Checklist Alert Banner -->
            <div class="requirements-banner">
                <h3><i class="fas fa-circle-exclamation"></i> Make sure to upload: Letter of Intent, Barangay Clearance, Valid ID, Current House Photos, and Utility Bills.</h3>
                <a href="housing-requirements.php" class="btn-view-reqs" target="_blank">
                    <i class="fas fa-eye"></i> View Checklist Guide
                </a>
            </div>

            <!-- Feedback Alert Displays -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-xmark"></i>
                    <div><strong>Application Error:</strong> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-circle-check"></i>
                    <div><strong>Submitted:</strong> <?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <!-- Program Selection Box -->
            <div class="program-selection-box" id="programSelection" <?php echo $selected_program_id ? 'style="display: none;"' : ''; ?>>
                <h2><i class="fas fa-building-circle-check"></i> Select target resettlement community</h2>
                <div class="programs-select-grid">
                    <?php 
                    $programs_stmt->execute();
                    while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                    ?>
                    <div class="program-select-card <?php echo $selected_program_id == $program['program_id'] ? 'selected' : ''; ?>" 
                         onclick="selectProgram(<?php echo $program['program_id']; ?>, '<?php echo htmlspecialchars($program['program_name']); ?>')">
                        <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                        <p class="location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($program['location']); ?></p>
                        <p class="units"><i class="fas fa-hotel"></i> <?php echo $program['total_units']; ?> Units Available</p>
                        <p class="description"><?php echo htmlspecialchars($program['description']); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Application Form Panel -->
            <div class="application-form-panel" id="applicationForm" style="<?php echo $selected_program_id ? 'display: block;' : 'display: none;'; ?>">
                <h2>Housing Registration Form</h2>
                <p class="form-subtitle">Fill out household details and attach certified copies of legal credentials.</p>

                <form method="POST" enctype="multipart/form-data" id="housingForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" id="program_id" name="program_id" value="<?php echo $selected_program_id; ?>">
                    
                    <div class="selected-program-info" id="selectedProgram">
                        <?php if($selected_program): ?>
                            <p><i class="fas fa-circle-check"></i> Active Site Program: <strong><?php echo htmlspecialchars($selected_program['program_name']); ?></strong></p>
                        <?php else: ?>
                            <p><i class="fas fa-triangle-exclamation"></i> Select an active community site above</p>
                        <?php endif; ?>
                        <button type="button" class="btn-change-program" onclick="showProgramSelection()"><i class="fas fa-pen-to-square"></i> Change Site</button>
                    </div>

                    <!-- Household Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-users-viewfinder"></i> Family & Income Metrics</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="household_size"><i class="fas fa-users"></i> Household Size *</label>
                                <input type="number" id="household_size" name="household_size" min="1" max="20" required placeholder="Number of family members">
                                <small>Count of members occupying the resettlement household</small>
                            </div>
                            <div class="form-group">
                                <label for="monthly_income"><i class="fas fa-wallet"></i> Monthly Household Income (₱) *</label>
                                <input type="number" id="monthly_income" name="monthly_income" min="0" step="500" required placeholder="0.00">
                                <small>Combined monthly salary/earnings of all household members</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="housing_need"><i class="fas fa-house-chimney-crack"></i> Residential Status Assessment *</label>
                            <textarea id="housing_need" name="housing_need" rows="5" placeholder="Detail your current housing issues (e.g. hazard zones, displacement, rent burdens)..." required></textarea>
                            <small>Explain why your family requires municipal housing support</small>
                        </div>
                    </div>

                    <!-- Document Upload Section -->
                    <div class="upload-section">
                        <h3><i class="fas fa-cloud-arrow-up"></i> Attach Certified Photocopies</h3>
                        <div class="upload-grid">
                            <div class="upload-item required">
                                <label for="letter_intent"><i class="fas fa-file-signature"></i> Letter of Intent</label>
                                <input type="file" id="letter_intent" name="letter_intent" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">PDF, JPG, PNG (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="barangay_clearance"><i class="fas fa-file-shield"></i> Barangay Clearance</label>
                                <input type="file" id="barangay_clearance" name="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Barangay Indigency or Clearance (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="valid_id"><i class="fas fa-id-card"></i> Government Valid ID</label>
                                <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Identity Document copy (Max 5MB)</div>
                            </div>
                            <div class="upload-item">
                                <label for="consent_letter"><i class="fas fa-file-contract"></i> land Consent Affidavit</label>
                                <input type="file" id="consent_letter" name="consent_letter" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">If building on relative lot (Optional)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="house_photo"><i class="fas fa-camera"></i> Photos of Current House</label>
                                <input type="file" id="house_photo" name="house_photo" accept=".jpg,.jpeg,.png" required>
                                <div class="file-info">Photos displaying living conditions (Max 5MB)</div>
                            </div>
                            <div class="upload-item required">
                                <label for="proof_billing"><i class="fas fa-file-invoice"></i> Utility Proof of Billing</label>
                                <input type="file" id="proof_billing" name="proof_billing" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Utility bill copies showing address (Max 5MB)</div>
                            </div>
                            <div class="upload-item">
                                <label for="tax_declaration"><i class="fas fa-file-invoice-dollar"></i> Tax Declaration</label>
                                <input type="file" id="tax_declaration" name="tax_declaration" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">Property tax records (Optional)</div>
                            </div>
                        </div>

                        <!-- Multiupload Support -->
                        <div class="additional-docs-box">
                            <label for="additional"><i class="fas fa-circle-plus"></i> Additional Supporting Files (Optional)</label>
                            <input type="file" id="additional" name="additional[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            <small>Attach other supporting proofs (You can select multiple files)</small>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Resettlement Application</button>
                        <button type="button" onclick="cancelApplication()" class="btn-cancel"><i class="fas fa-times-circle"></i> Cancel Application</button>
                    </div>
                </form>
            </div>

            <!-- Guidelines and Reminders Information Block -->
            <div class="reminders-box">
                <h4><i class="fas fa-circle-info"></i> Processing Guidelines</h4>
                <ul>
                    <li><strong>File Requirements:</strong> Legal document scans are accepted in PDF, JPG, or PNG formats only.</li>
                    <li><strong>Upload Limit:</strong> File sizes must not exceed 5MB per document.</li>
                    <li><strong>Residency Verification:</strong> Submissions from non-residents or unverified accounts will be rejected.</li>
                    <li><strong>Review Status:</strong> Follow progress under the Resident Portal dashboard. Initial assessments take 7-14 business days.</li>
                </ul>
            </div>

        </div>
    </div>

    <!-- Footer Include -->
    <?php include 'includes/footer.php'; ?>

    <script>
    let selectedProgramId = <?php echo $selected_program_id ?: 0; ?>;

    // Trigger program selection card updates
    function selectProgram(id, name) {
        selectedProgramId = id;
        document.getElementById('program_id').value = id;
        document.getElementById('selectedProgram').innerHTML = `
            <p><i class="fas fa-circle-check"></i> Active Site Program: <strong>${name}</strong></p>
            <button type="button" class="btn-change-program" onclick="showProgramSelection()"><i class="fas fa-pen-to-square"></i> Change Site</button>
        `;
        document.getElementById('programSelection').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'block';
        document.getElementById('applicationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Remove selection highlights
        document.querySelectorAll('.program-select-card').forEach(card => card.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
    }

    // Toggle Program checklist grid
    function showProgramSelection() {
        document.getElementById('programSelection').style.display = 'block';
        document.getElementById('applicationForm').style.display = 'none';
        document.getElementById('programSelection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Cancel and go back
    function cancelApplication() {
        if (confirm('Cancel and discard your entered application data?')) {
            window.location.href = 'housing.php';
        }
    }

    // Interactive file upload displays
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const parent = e.target.closest('.upload-item, .additional-docs-box');
                const fileList = e.target.files;
                
                // Clear any existing name indicator
                const existingInfo = parent.querySelector('.file-name-display');
                if (existingInfo) {
                    existingInfo.remove();
                }
                
                if (fileList.length > 0) {
                    parent.classList.add('has-file');
                    
                    const infoDisplay = document.createElement('div');
                    infoDisplay.className = 'file-name-display';
                    
                    if (fileList.length === 1) {
                        infoDisplay.innerHTML = `<i class="fas fa-circle-check"></i> ${fileList[0].name}`;
                    } else {
                        infoDisplay.innerHTML = `<i class="fas fa-circle-check"></i> ${fileList.length} files selected`;
                    }
                    
                    parent.appendChild(infoDisplay);
                } else {
                    parent.classList.remove('has-file');
                }
            });
        });
    });
    </script>
</body>
</html>

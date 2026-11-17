<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified resident
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    $_SESSION['error'] = "To apply for socialized housing, please complete your Resident Verification first.";
    redirect('verify-resident.php');
}

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get available housing programs
$programs_query = "SELECT * FROM housing_programs WHERE status = 'active'";
$programs_stmt = $db->prepare($programs_query);
$programs_stmt->execute();

$error = '';
$success = '';

// Get selected program and requested type from URL if any
$selected_program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$requested_type = isset($_GET['type']) ? sanitize($_GET['type']) : '';

if ($selected_program_id == 3) {
    $requested_type = 'in_site';
} elseif ($requested_type === 'in_site' && !$selected_program_id) {
    $selected_program_id = 3;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $program_id = (int)sanitize($_POST['program_id']);
    $housing_type = sanitize($_POST['housing_type'] ?? 'relocation');
    $household_size = sanitize($_POST['household_size']);
    $monthly_income = sanitize($_POST['monthly_income']);
    $housing_need = sanitize($_POST['housing_need']);
    
    // In-Site Housing specific fields
    $land_ownership_status = sanitize($_POST['land_ownership_status'] ?? '');
    $property_location = sanitize($_POST['property_location'] ?? '');
    $lot_area = sanitize($_POST['lot_area'] ?? '');
    $construction_type = sanitize($_POST['construction_type'] ?? '');

    // Sync housing type from database program if available
    $prog_stmt = $db->prepare("SELECT * FROM housing_programs WHERE program_id = ?");
    $prog_stmt->execute([$program_id]);
    $prog_data = $prog_stmt->fetch(PDO::FETCH_ASSOC);
    if ($prog_data && !empty($prog_data['housing_type'])) {
        $housing_type = $prog_data['housing_type'];
    }

    // Validate required documents and fields depending on housing_type
    $required_docs = ['barangay_clearance', 'valid_id', 'house_photo'];
    $missing_docs = [];

    if ($housing_type === 'in_site') {
        // In-Site requires either proof_of_land, tax_declaration, or consent_letter
        $has_land_doc = (isset($_FILES['proof_of_land']) && $_FILES['proof_of_land']['error'] === UPLOAD_ERR_OK) ||
                        (isset($_FILES['tax_declaration']) && $_FILES['tax_declaration']['error'] === UPLOAD_ERR_OK) ||
                        (isset($_FILES['consent_letter']) && $_FILES['consent_letter']['error'] === UPLOAD_ERR_OK);
        if (!$has_land_doc) {
            $missing_docs[] = 'Proof of Land Ownership or Landowner Consent Affidavit';
        }
        if (empty($property_location)) {
            $missing_docs[] = 'Existing Property Location in Palayan City';
        }
        if (empty($land_ownership_status)) {
            $missing_docs[] = 'Land Ownership Status';
        }
        $required_docs[] = 'letter_intent';
    } else {
        $required_docs[] = 'letter_intent';
        $required_docs[] = 'proof_billing';
    }
    
    foreach ($required_docs as $doc) {
        if (!isset($_FILES[$doc]) || $_FILES[$doc]['error'] === UPLOAD_ERR_NO_FILE) {
            $missing_docs[] = ucwords(str_replace('_', ' ', $doc));
        }
    }
    
    if (!empty($missing_docs)) {
        $error = "Missing required information/documents: " . implode(', ', array_unique($missing_docs));
    } else {
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Check if user already has an active application for this program
            $check_query = "SELECT beneficiary_id FROM housing_beneficiaries 
                            WHERE user_id = :user_id AND program_id = :program_id 
                            AND status IN ('pending', 'qualified', 'approved')";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->bindParam(':program_id', $program_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("You already have an active application for this housing program.");
            }
            
            // Insert housing application
            $query = "INSERT INTO housing_beneficiaries 
                      (user_id, program_id, housing_type, household_composition, monthly_income, housing_need_assessment, land_ownership_status, property_location, lot_area, construction_type, application_date, status) 
                      VALUES (:user_id, :program_id, :housing_type, :household, :income, :need, :land_status, :prop_loc, :lot_area, :const_type, CURDATE(), 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':program_id', $program_id);
            $stmt->bindParam(':housing_type', $housing_type);
            $stmt->bindParam(':household', $household_size);
            $stmt->bindParam(':income', $monthly_income);
            $stmt->bindParam(':need', $housing_need);
            $stmt->bindParam(':land_status', $land_ownership_status);
            $stmt->bindParam(':prop_loc', $property_location);
            $stmt->bindParam(':lot_area', $lot_area);
            $stmt->bindParam(':const_type', $construction_type);
            $stmt->execute();
            
            $beneficiary_id = $db->lastInsertId();
            
            // Handle document uploads
            $uploader = new FileUploader();
            $document_types = [
                'letter_intent' => 'Letter of Intent',
                'barangay_clearance' => 'Barangay Clearance',
                'valid_id' => 'Valid ID',
                'proof_of_land' => 'Proof of Land Ownership',
                'consent_letter' => 'Affidavit of Consent',
                'house_photo' => 'House / Lot Photo',
                'proof_billing' => 'Proof of Billing',
                'tax_declaration' => 'Tax Declaration',
                'sketch_plan' => 'Lot Sketch Plan',
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
            
            // Commit database transaction immediately so application is 100% saved
            if ($db->inTransaction()) {
                $db->commit();
            }

            $tracking_no = "HSG-" . date('Y') . "-" . str_pad((string)$beneficiary_id, 5, '0', STR_PAD_LEFT);
            $submission_success = true;
            $success_tracking_no = $tracking_no;
            $success_service_type = 'Housing';
            
            // Create notification safely
            try {
                $notif_title = ($housing_type === 'in_site') ? 'In-Site Housing Application Submitted' : 'Relocation Housing Application Submitted';
                $notif_msg = ($housing_type === 'in_site') 
                    ? 'Your In-Site Housing application (build on existing land) has been submitted and is pending review.'
                    : 'Your Relocation Housing application has been submitted and is pending review.';
                $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES (:user_id, :title, :message, 'housing')";
                $notif_stmt = $db->prepare($notif_query);
                $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $notif_stmt->bindParam(':title', $notif_title);
                $notif_stmt->bindParam(':message', $notif_msg);
                $notif_stmt->execute();
            } catch (\Throwable $notifErr) {
                error_log("Housing application notification error: " . $notifErr->getMessage());
            }

            // Send SMS confirmation via TextBee safely
            try {
                $user_phone_query = "SELECT contact_number, first_name, last_name FROM users WHERE user_id = :uid";
                $up_stmt = $db->prepare($user_phone_query);
                $up_stmt->execute([':uid' => $_SESSION['user_id']]);
                $u_data = $up_stmt->fetch(PDO::FETCH_ASSOC);

                if (!empty($u_data['contact_number'])) {
                    require_once __DIR__ . '/includes/SMSGateway.php';
                    $smsGateway = new SMSGateway();
                    $smsGateway->sendHousingConfirmation(
                        $u_data['contact_number'], 
                        trim(($u_data['first_name'] ?? '') . ' ' . ($u_data['last_name'] ?? '')), 
                        [
                            'beneficiary_id' => $beneficiary_id,
                            'reference_number' => $tracking_no,
                            'housing_type' => $housing_type,
                            'user_id' => $_SESSION['user_id']
                        ]
                    );
                }
            } catch (\Throwable $smsErr) {
                error_log("Housing application SMS confirmation error: " . $smsErr->getMessage());
            }
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
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
            
            --bg-primary: #E4EFE7;
            --bg-secondary: #E4EFE7;
            --bg-card: #FFFFFF;
            --text-primary: #192126;
            --text-secondary: #3A4A5A;
            --text-muted: #71818B;
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
            --bg-primary: #0C1417;
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
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--ref-canvas, #E4EFE7) !important;
            color: var(--ref-text, #192126);
            line-height: 1.6;
            padding: 16px 20px 48px !important;
            margin: 0 !important;
            transition: background 0.35s ease, color 0.35s ease;
            box-sizing: border-box;
        }

        .container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* ========== HERO & HEADER ========== */
        .apply-page {
            padding: 0;
            min-height: auto;
            width: 100%;
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

        /* ========== HOUSING PATHWAY SELECTOR (REQ 1) ========== */
        .pathway-selection-wrapper {
            margin-bottom: 30px;
        }

        .pathway-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 22px;
            margin-bottom: 25px;
        }

        .pathway-card {
            background: var(--bg-card);
            border: 2.5px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 26px 24px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-sm);
        }

        .pathway-card:hover {
            transform: translateY(-4px);
            border-color: var(--maroon);
            box-shadow: var(--shadow-md);
        }

        .pathway-card.active {
            border-color: var(--maroon);
            background: linear-gradient(180deg, rgba(127, 3, 3, 0.03) 0%, rgba(127, 3, 3, 0.01) 100%);
            box-shadow: 0 0 0 4px rgba(127, 3, 3, 0.12), var(--shadow-md);
        }

        [data-theme="dark"] .pathway-card.active {
            border-color: var(--gold-light);
            background: linear-gradient(180deg, rgba(232, 213, 168, 0.06) 0%, transparent 100%);
            box-shadow: 0 0 0 4px rgba(232, 213, 168, 0.15), var(--shadow-md);
        }

        .pathway-card.active::after {
            content: "✓ Selected";
            position: absolute;
            top: 16px;
            right: 16px;
            background: var(--maroon);
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(127, 3, 3, 0.3);
        }

        [data-theme="dark"] .pathway-card.active::after {
            background: var(--gold-light);
            color: var(--midnight);
        }

        .pathway-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .pathway-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .pathway-card.relocation .pathway-icon-wrap {
            background: rgba(127, 3, 3, 0.1);
            color: var(--maroon);
        }

        .pathway-card.insite .pathway-icon-wrap {
            background: rgba(15, 65, 74, 0.12);
            color: var(--midnight);
        }

        [data-theme="dark"] .pathway-card.insite .pathway-icon-wrap {
            background: rgba(232, 213, 168, 0.14);
            color: var(--gold-light);
        }

        .pathway-meta h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px;
        }

        .pathway-meta .pathway-type-tag {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .pathway-card.relocation .pathway-type-tag {
            background: rgba(127, 3, 3, 0.1);
            color: var(--maroon);
        }

        .pathway-card.insite .pathway-type-tag {
            background: rgba(15, 65, 74, 0.12);
            color: var(--midnight);
        }

        [data-theme="dark"] .pathway-card.insite .pathway-type-tag {
            background: rgba(232, 213, 168, 0.15);
            color: var(--gold-light);
        }

        .pathway-desc {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin-bottom: 16px;
            flex-grow: 1;
        }

        .pathway-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: auto;
        }

        .pathway-pill {
            font-size: 0.73rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--bg-primary);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* In-Site Banner & Fields */
        .insite-banner {
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.08) 0%, rgba(150, 192, 206, 0.12) 100%);
            border: 1.5px solid rgba(15, 65, 74, 0.2);
            border-radius: var(--radius-element);
            padding: 18px 22px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        [data-theme="dark"] .insite-banner {
            background: rgba(232, 213, 168, 0.06);
            border-color: rgba(232, 213, 168, 0.2);
        }

        .insite-banner i {
            font-size: 1.8rem;
            color: var(--midnight);
            flex-shrink: 0;
        }

        [data-theme="dark"] .insite-banner i {
            color: var(--gold-light);
        }

        .insite-banner p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--text-primary);
        }

        .doc-req-badge {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 6px;
            display: inline-block;
        }

        .doc-req-badge.relocation-only {
            background: rgba(127, 3, 3, 0.1);
            color: var(--maroon);
        }

        .doc-req-badge.insite-only {
            background: rgba(15, 65, 74, 0.12);
            color: var(--midnight);
        }

        [data-theme="dark"] .doc-req-badge.insite-only {
            background: rgba(232, 213, 168, 0.15);
            color: var(--gold-light);
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
    <div class="app-container">
        <!-- Resident Portal Sidebar -->
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Housing Program Application';
            $banner_subtitle = 'Provide family metrics and submit certified photocopies of requirements';
            $banner_icon = 'fa-house-chimney';
            $banner_actions = [
                ['label' => 'Track Status', 'url' => 'my-housing.php', 'icon' => 'fa-list-check', 'type' => 'primary'],
                ['label' => 'Housing Programs', 'url' => 'housing-programs.php', 'icon' => 'fa-arrow-left', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

                <!-- Resettlement Sites Selection (visible only for Relocation) -->
                <div class="program-selection-box" id="relocationSitesBox" style="<?php echo ($requested_type === 'in_site') ? 'display: none;' : 'display: block;'; ?>">
                    <h2><i class="fas fa-building-circle-check"></i> Select Designated Resettlement Community</h2>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: -15px 0 20px;">Choose the municipal housing subdivision where your family will be relocated.</p>
                    <div class="programs-select-grid">
                        <?php 
                        $programs_stmt->execute();
                        while($program = $programs_stmt->fetch(PDO::FETCH_ASSOC)): 
                            if (($program['housing_type'] ?? '') === 'in_site') continue;
                        ?>
                        <div class="program-select-card <?php echo $selected_program_id == $program['program_id'] ? 'selected' : ''; ?>" 
                             onclick="selectProgram(<?php echo $program['program_id']; ?>, '<?php echo htmlspecialchars($program['program_name']); ?>', 'relocation')">
                            <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                            <p class="location"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($program['location']); ?></p>
                            <p class="units"><i class="fas fa-hotel"></i> <?php echo $program['total_units']; ?> Units Available</p>
                            <p class="description"><?php echo htmlspecialchars($program['description']); ?></p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- In-Site Action Prompt (visible only when In-Site is picked) -->
                <div class="program-selection-box" id="insiteActionBox" style="<?php echo ($requested_type === 'in_site') ? 'display: block;' : 'display: none;'; ?>">
                    <h2><i class="fas fa-house-chimney-user"></i> In-Site Housing Assistance</h2>
                    <div class="insite-banner">
                        <i class="fas fa-shield-heart"></i>
                        <div>
                            <strong>No Relocation Required:</strong> You will construct or rebuild a durable home directly on your existing property in Palayan City. The CSWDO provides building materials, structural subsidy, and construction monitoring for qualified property owners or permitted occupants.
                        </div>
                    </div>
                    <?php
                    // Get In-Site program row
                    $insite_stmt = $db->query("SELECT * FROM housing_programs WHERE housing_type = 'in_site' LIMIT 1");
                    $insite_prog = $insite_stmt->fetch(PDO::FETCH_ASSOC);
                    $insite_id = $insite_prog ? (int)$insite_prog['program_id'] : 3;
                    $insite_name = $insite_prog ? $insite_prog['program_name'] : 'Palayan In-Site Housing Assistance Program';
                    ?>
                    <button type="button" class="btn-submit" style="width: auto; padding: 14px 34px; font-size: 0.95rem;" onclick="selectProgram(<?php echo $insite_id; ?>, '<?php echo htmlspecialchars($insite_name); ?>', 'in_site')">
                        <i class="fas fa-arrow-right"></i> Proceed with In-Site Housing Registration
                    </button>
                </div>

            <!-- Application Form Panel -->
            <div class="application-form-panel" id="applicationForm" style="<?php echo $selected_program_id ? 'display: block;' : 'display: none;'; ?>">
                <h2 id="formHeaderTitle">
                    <?php echo ($selected_program && ($selected_program['housing_type'] ?? '') === 'in_site') ? 'In-Site Housing Registration Form' : 'Relocation Housing Registration Form'; ?>
                </h2>
                <p class="form-subtitle">Fill out household details, property metrics, and attach certified photocopies of credentials.</p>

                <form method="POST" enctype="multipart/form-data" id="housingForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" id="program_id" name="program_id" value="<?php echo $selected_program_id; ?>">
                    <input type="hidden" id="housing_type" name="housing_type" value="<?php echo ($selected_program && ($selected_program['housing_type'] ?? '') === 'in_site') ? 'in_site' : 'relocation'; ?>">
                    
                    <div class="selected-program-info" id="selectedProgram">
                        <?php if($selected_program): ?>
                            <p><i class="fas fa-circle-check"></i> Active Housing Track: <strong><?php echo htmlspecialchars($selected_program['program_name']); ?></strong> (<?php echo (($selected_program['housing_type'] ?? '') === 'in_site') ? 'In-Site Housing' : 'Relocation Housing'; ?>)</p>
                        <?php else: ?>
                            <p><i class="fas fa-triangle-exclamation"></i> Select an active assistance option above</p>
                        <?php endif; ?>
                        <button type="button" class="btn-change-program" onclick="showPathwaySelection()"><i class="fas fa-pen-to-square"></i> Change Option</button>
                    </div>

                    <!-- In-Site Specific Information Section (Conditional) -->
                    <div class="form-section" id="insiteFields" style="<?php echo ($selected_program && ($selected_program['housing_type'] ?? '') === 'in_site') ? 'display: block;' : 'display: none;'; ?>">
                        <h3><i class="fas fa-map-location-dot"></i> Existing Land &amp; Property Verification <span style="font-size: 0.75rem; color: var(--midnight); font-weight: 700; background: rgba(15,65,74,0.1); padding: 3px 10px; border-radius: 12px; margin-left: 10px;">In-Site Housing Track</span></h3>
                        
                        <div class="insite-banner" style="margin-bottom: 20px;">
                            <i class="fas fa-circle-info"></i>
                            <p><strong>Note for In-Site Applicants:</strong> You are not being relocated. Please state the location of the land or property where the house will be built in Palayan City and certify your right of tenure.</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="land_ownership_status"><i class="fas fa-file-contract"></i> Land Ownership / Tenurial Status *</label>
                                <select id="land_ownership_status" name="land_ownership_status">
                                    <option value="">-- Select Land Ownership Status --</option>
                                    <option value="Titled Landowner (TCT / OCT in Applicant's Name)">Titled Landowner (TCT / OCT in Applicant's Name)</option>
                                    <option value="Tax Declaration (Declared Real Property)">Tax Declaration (Declared Real Property)</option>
                                    <option value="Family / Inherited Land (With Heirs Consent)">Family / Inherited Land (With Heirs Consent)</option>
                                    <option value="With Notarized Landowner Consent (Tenant / Permitted)">With Notarized Landowner Consent (Tenant / Permitted)</option>
                                    <option value="Barangay Recognized Tenurial / Lot Occupancy Right">Barangay Recognized Tenurial / Lot Occupancy Right</option>
                                </select>
                                <small>Legal status of the lot where you will construct</small>
                            </div>

                            <div class="form-group">
                                <label for="lot_area"><i class="fas fa-vector-square"></i> Lot Area (in sq.m.)</label>
                                <input type="text" id="lot_area" name="lot_area" placeholder="e.g. 60 sqm, 100 sqm">
                                <small>Approximate surface area of your existing lot</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="property_location"><i class="fas fa-location-dot"></i> Complete Property Address in Palayan City *</label>
                                <input type="text" id="property_location" name="property_location" placeholder="e.g. Lot 12 Block 4, Purok 3, Barangay Singalat, Palayan City">
                                <small>Exact site location of the lot within Palayan City</small>
                            </div>

                            <div class="form-group">
                                <label for="construction_type"><i class="fas fa-trowel-bricks"></i> Proposed Construction Scope *</label>
                                <select id="construction_type" name="construction_type">
                                    <option value="">-- Select Construction Scope --</option>
                                    <option value="New Core House Construction (Fresh Build)">New Core House Construction (Fresh Build)</option>
                                    <option value="Complete Structural Reconstruction (Dilapidated Replacement)">Complete Structural Reconstruction (Dilapidated Replacement)</option>
                                    <option value="Major House Repair & Expansion">Major House Repair &amp; Expansion</option>
                                    <option value="Post-Disaster Reconstruction (Fire / Flood / Storm Recovery)">Post-Disaster Reconstruction (Fire / Flood / Storm Recovery)</option>
                                </select>
                                <small>Type of construction assistance required</small>
                            </div>
                        </div>
                    </div>

                    <!-- Household Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-users-viewfinder"></i> Family &amp; Income Metrics</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="household_size"><i class="fas fa-users"></i> Household Size *</label>
                                <input type="number" id="household_size" name="household_size" min="1" max="20" required placeholder="Number of family members">
                                <small>Count of members occupying the household</small>
                            </div>
                            <div class="form-group">
                                <label for="monthly_income"><i class="fas fa-wallet"></i> Monthly Household Income (₱) *</label>
                                <input type="number" id="monthly_income" name="monthly_income" min="0" step="500" required placeholder="0.00">
                                <small>Combined monthly salary/earnings of all household members</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="housing_need"><i class="fas fa-house-chimney-crack"></i> Residential Status Assessment *</label>
                            <textarea id="housing_need" name="housing_need" rows="4" placeholder="Detail your current housing condition (e.g. dilapidated structure, lack of funds to build, disaster recovery, rent burden)..." required></textarea>
                            <small>Explain why your family qualifies for CSWDO housing assistance</small>
                        </div>
                    </div>

                    <!-- Document Upload Section -->
                    <div class="upload-section">
                        <h3><i class="fas fa-cloud-arrow-up"></i> Attach Certified Photocopies</h3>
                        <div class="upload-grid">
                            <!-- Letter of Intent -->
                            <div class="upload-item required">
                                <label for="letter_intent">
                                    <i class="fas fa-file-signature"></i> Letter of Intent
                                    <span class="doc-req-badge" style="background: rgba(46,125,50,0.1); color: var(--color-success);">Required</span>
                                </label>
                                <input type="file" id="letter_intent" name="letter_intent" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Formal application letter addressed to CSWDO (Max 5MB)</div>
                            </div>

                            <!-- Barangay Clearance -->
                            <div class="upload-item required">
                                <label for="barangay_clearance">
                                    <i class="fas fa-file-shield"></i> Barangay Clearance / Indigency
                                    <span class="doc-req-badge" style="background: rgba(46,125,50,0.1); color: var(--color-success);">Required</span>
                                </label>
                                <input type="file" id="barangay_clearance" name="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Barangay Certificate of Indigency / Residency (Max 5MB)</div>
                            </div>

                            <!-- Government Valid ID -->
                            <div class="upload-item required">
                                <label for="valid_id">
                                    <i class="fas fa-id-card"></i> Government Valid ID
                                    <span class="doc-req-badge" style="background: rgba(46,125,50,0.1); color: var(--color-success);">Required</span>
                                </label>
                                <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-info">Identity Document copy of applicant (Max 5MB)</div>
                            </div>

                            <!-- In-Site Land Title / Proof of Land (Required for In-Site) -->
                            <div class="upload-item" id="proofLandItem">
                                <label for="proof_of_land">
                                    <i class="fas fa-certificate"></i> Proof of Land Ownership / Right
                                    <span class="doc-req-badge insite-only" id="landBadge">Required for In-Site</span>
                                </label>
                                <input type="file" id="proof_of_land" name="proof_of_land" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">Transfer Certificate of Title, Tax Declaration, or Lot Award (Max 5MB)</div>
                            </div>

                            <!-- Landowner Consent Affidavit -->
                            <div class="upload-item">
                                <label for="consent_letter">
                                    <i class="fas fa-file-contract"></i> Landowner Consent Affidavit
                                    <span class="doc-req-badge" style="background: rgba(0,0,0,0.06); color: var(--text-muted);">If Building on Relative/Other Lot</span>
                                </label>
                                <input type="file" id="consent_letter" name="consent_letter" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">Notarized permission from the registered landowner (Max 5MB)</div>
                            </div>

                            <!-- House / Lot Photos -->
                            <div class="upload-item required">
                                <label for="house_photo">
                                    <i class="fas fa-camera"></i> Photos of Current House / Lot
                                    <span class="doc-req-badge" style="background: rgba(46,125,50,0.1); color: var(--color-success);">Required</span>
                                </label>
                                <input type="file" id="house_photo" name="house_photo" accept=".jpg,.jpeg,.png" required>
                                <div class="file-info">Clear photos of living conditions or construction lot (Max 5MB)</div>
                            </div>

                            <!-- Sketch Plan (For In-Site) -->
                            <div class="upload-item" id="sketchPlanItem">
                                <label for="sketch_plan">
                                    <i class="fas fa-drafting-compass"></i> Lot Vicinity Sketch Plan
                                    <span class="doc-req-badge insite-only">In-Site Recommended</span>
                                </label>
                                <input type="file" id="sketch_plan" name="sketch_plan" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">Hand-drawn or certified lot sketch showing landmarks (Max 5MB)</div>
                            </div>

                            <!-- Utility Proof of Billing (For Relocation) -->
                            <div class="upload-item" id="proofBillingItem">
                                <label for="proof_billing">
                                    <i class="fas fa-file-invoice"></i> Utility Proof of Billing
                                    <span class="doc-req-badge relocation-only" id="billingBadge">Required for Relocation</span>
                                </label>
                                <input type="file" id="proof_billing" name="proof_billing" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">Electric or water utility bill displaying address (Max 5MB)</div>
                            </div>

                            <!-- Tax Declaration -->
                            <div class="upload-item">
                                <label for="tax_declaration">
                                    <i class="fas fa-file-invoice-dollar"></i> Real Property Tax Declaration
                                    <span class="doc-req-badge" style="background: rgba(0,0,0,0.06); color: var(--text-muted);">Optional</span>
                                </label>
                                <input type="file" id="tax_declaration" name="tax_declaration" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="file-info">City Assessor Tax Declaration copy (Max 5MB)</div>
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
                        <button type="submit" class="btn-submit" id="btnSubmitForm">
                            <i class="fas fa-paper-plane"></i> Submit Housing Application
                        </button>
                        <button type="button" onclick="cancelApplication()" class="btn-cancel"><i class="fas fa-times-circle"></i> Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Guidelines and Reminders Information Block -->
            <div class="reminders-box">
                <h4><i class="fas fa-circle-info"></i> Housing Assistance Guidelines</h4>
                <ul>
                    <li><strong>Relocation Housing:</strong> Intended for families residing in high-hazard zones, subject to court evictions, or public infrastructure projects requiring resettlement to Akap Village or San Lorenzo NHA.</li>
                    <li><strong>In-Site Housing:</strong> Allows qualified Palayan residents to construct or rebuild a durable home directly on their own land or property without moving away from their current barangay.</li>
                    <li><strong>File Requirements:</strong> Legal document scans are accepted in PDF, JPG, or PNG formats up to 5MB per document.</li>
                    <li><strong>Residency Verification:</strong> All applications undergo physical validation and social worker intake assessment by CSWDO Palayan City.</li>
                </ul>
            </div>

        </div>
    </div>

    <script>
    let selectedProgramId = <?php echo $selected_program_id ?: 0; ?>;
    let currentHousingType = '<?php echo ($requested_type === 'in_site' || ($selected_program && ($selected_program['housing_type'] ?? '') === 'in_site')) ? 'in_site' : 'relocation'; ?>';

    // Switch between Relocation and In-Site pathways in Step 1
    function setHousingPathway(type) {
        currentHousingType = type;
        const relCard = document.getElementById('pathwayCardRelocation');
        const insCard = document.getElementById('pathwayCardInsite');
        const relBox  = document.getElementById('relocationSitesBox');
        const insBox  = document.getElementById('insiteActionBox');

        if (type === 'in_site') {
            relCard.classList.remove('active');
            insCard.classList.add('active');
            relBox.style.display = 'none';
            insBox.style.display = 'block';
            insBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            insCard.classList.remove('active');
            relCard.classList.add('active');
            insBox.style.display = 'none';
            relBox.style.display = 'block';
            relBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Trigger program selection card updates
    function selectProgram(id, name, type) {
        selectedProgramId = id;
        currentHousingType = type || (id === 3 ? 'in_site' : 'relocation');
        
        document.getElementById('program_id').value = id;
        document.getElementById('housing_type').value = currentHousingType;

        const typeLabel = currentHousingType === 'in_site' ? 'In-Site Housing (Build on Existing Land)' : 'Relocation Housing (Resettlement Site)';
        const headerTitle = currentHousingType === 'in_site' ? 'In-Site Housing Registration Form' : 'Relocation Housing Registration Form';

        document.getElementById('formHeaderTitle').textContent = headerTitle;
        document.getElementById('selectedProgram').innerHTML = `
            <p><i class="fas fa-circle-check"></i> Active Housing Track: <strong>${name}</strong> (${typeLabel})</p>
            <button type="button" class="btn-change-program" onclick="showPathwaySelection()"><i class="fas fa-pen-to-square"></i> Change Option</button>
        `;

        // Toggle In-Site specific fields and requirements
        const insiteFields = document.getElementById('insiteFields');
        const landInput = document.getElementById('land_ownership_status');
        const propLocInput = document.getElementById('property_location');
        const constTypeInput = document.getElementById('construction_type');
        const proofBillingInput = document.getElementById('proof_billing');
        const proofLandInput = document.getElementById('proof_of_land');

        if (currentHousingType === 'in_site') {
            insiteFields.style.display = 'block';
            if (landInput) landInput.required = true;
            if (propLocInput) propLocInput.required = true;
            if (constTypeInput) constTypeInput.required = true;
            if (proofBillingInput) proofBillingInput.required = false;
            if (proofLandInput) proofLandInput.required = true;
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fas fa-paper-plane"></i> Submit In-Site Housing Application';
        } else {
            insiteFields.style.display = 'none';
            if (landInput) landInput.required = false;
            if (propLocInput) propLocInput.required = false;
            if (constTypeInput) constTypeInput.required = false;
            if (proofBillingInput) proofBillingInput.required = true;
            if (proofLandInput) proofLandInput.required = false;
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fas fa-paper-plane"></i> Submit Relocation Application';
        }

        document.getElementById('pathwaySection').style.display = 'none';
        document.getElementById('applicationForm').style.display = 'block';
        document.getElementById('applicationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Update card highlight if relocation card clicked
        document.querySelectorAll('.program-select-card').forEach(card => card.classList.remove('selected'));
        if (event && event.currentTarget && event.currentTarget.classList.contains('program-select-card')) {
            event.currentTarget.classList.add('selected');
        }
    }

    // Toggle back to Pathway Selection
    function showPathwaySelection() {
        document.getElementById('pathwaySection').style.display = 'block';
        document.getElementById('applicationForm').style.display = 'none';
        document.getElementById('pathwaySection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Cancel and go back
    function cancelApplication() {
        if (confirm('Cancel and discard your entered application data?')) {
            window.location.href = 'housing.php';
        }
    }

    // Interactive file upload displays
    document.addEventListener('DOMContentLoaded', function() {
        // If loaded with In-Site pre-selection, ensure fields are ready
        if (currentHousingType === 'in_site' && selectedProgramId) {
            const insiteFields = document.getElementById('insiteFields');
            if (insiteFields) insiteFields.style.display = 'block';
            const landInput = document.getElementById('land_ownership_status');
            const propLocInput = document.getElementById('property_location');
            const constTypeInput = document.getElementById('construction_type');
            if (landInput) landInput.required = true;
            if (propLocInput) propLocInput.required = true;
            if (constTypeInput) constTypeInput.required = true;
            const proofLandInput = document.getElementById('proof_of_land');
            if (proofLandInput) proofLandInput.required = true;
        }

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
    <!-- Portal Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
    <!-- Application Succession Modal -->
    <?php include __DIR__ . '/includes/submission-success-modal.php'; ?>
</body>
</html>

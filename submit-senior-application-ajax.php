<?php
/**
 * submit-senior-application-ajax.php
 * Focus: Provision of Identification Card and Purchase Booklet to Senior Citizens
 * CSWDO Palayan City - Special Lane Window 3
 * Eligibility: At least 60 years old; registered voter / bonafide resident of Palayan City.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';
require_once __DIR__ . '/includes/rate-limit.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Mangyaring mag-login muna.']);
    exit;
}

if (isStaff() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Staff and admin cannot submit resident applications.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
checkRateLimit($user_id, 'senior_application', 3, 10);

$database = new Database();
$db = $database->getConnection();

$res_stmt = $db->prepare("SELECT id, birth_date, barangay, senior_citizen FROM residents WHERE user_id = :uid LIMIT 1");
$res_stmt->bindParam(':uid', $user_id);
$res_stmt->execute();
$resident = $res_stmt->fetch(PDO::FETCH_ASSOC);

// Strict age requirement: Must have birth_date on record and be at least 60 years old
if (empty($resident) || empty($resident['birth_date']) || $resident['birth_date'] === '0000-00-00') {
    echo json_encode([
        'success' => false,
        'message' => "Mangyaring kumpletuhin muna ang iyong Profile Information (petsa ng kapanganakan / birth date) bago mag-apply para sa Senior Citizen ID at Purchase Booklet."
    ]);
    exit;
}

$birth = new DateTime($resident['birth_date']);
$today = new DateTime();
$age = $today->diff($birth)->y;
if ($age < 60) {
    echo json_encode([
        'success' => false,
        'message' => "Paumanhin, ang serbisyong ito ay eksklusibo lamang para sa mga residente na may edad 60 pataas. Ang iyong nakatalang edad sa profile ay {$age} taong gulang."
    ]);
    exit;
}

// Auto-sync Senior Citizen status if not yet flagged
if (empty($resident['senior_citizen'])) {
    $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :uid")->execute([':uid' => $user_id]);
    $_SESSION['is_senior_citizen'] = true;
}

$is_voter = sanitize($_POST['is_registered_voter'] ?? 'yes');
$details  = sanitize($_POST['details'] ?? 'Application for Senior Citizen ID and Purchase Booklet');

// Check duplicate active application
$dup_check = $db->prepare(
    "SELECT application_id, tracking_number FROM senior_applications 
     WHERE user_id = :uid AND status IN ('submitted', 'reviewing') LIMIT 1"
);
$dup_check->bindParam(':uid', $user_id);
$dup_check->execute();
$existing_app = $dup_check->fetch(PDO::FETCH_ASSOC);

if ($existing_app) {
    echo json_encode([
        'success' => false,
        'message' => "Mayroon ka nang kasalukuyang aplikasyon para sa Senior Citizen ID at Purchase Booklet (Tracking #: {$existing_app['tracking_number']}). Mangyaring hintayin ang pagproseso ng CSWDO Window 3."
    ]);
    exit;
}

$uploader = new FileUploader();
$valid_id_path           = null;
$id_picture_path         = null;
$barangay_indigency_path = null;

// Valid ID / Proof of Age check:
// Accepts newly uploaded valid_id file, OR automatically reuses approved resident ID if already verified
if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
    try {
        $valid_id_path = $uploader->upload($_FILES['valid_id'], 'senior');
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Upload error (Valid ID): ' . $e->getMessage()]);
        exit;
    }
} else {
    // Check if resident already has an approved residency verification ID on file
    $ver_stmt = $db->prepare("SELECT id_file_path FROM verification_requests WHERE user_id = :uid AND status = 'approved' ORDER BY verification_id DESC LIMIT 1");
    $ver_stmt->execute([':uid' => $user_id]);
    $verified_id_file = $ver_stmt->fetchColumn();

    if ($verified_id_file && (file_exists(__DIR__ . '/' . $verified_id_file) || !empty($verified_id_file))) {
        $valid_id_path = $verified_id_file;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Kinakailangan mag-upload ng Proof of Age (Birth Certificate o Valid Government ID na may petsa ng kapanganakan).'
        ]);
        exit;
    }
}

try {
    if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
        $id_picture_path = $uploader->upload($_FILES['id_picture'], 'senior');
    }

    if (isset($_FILES['voter_or_residency_cert']) && $_FILES['voter_or_residency_cert']['error'] === UPLOAD_ERR_OK) {
        $barangay_indigency_path = $uploader->upload($_FILES['voter_or_residency_cert'], 'senior');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
    exit;
}

$tracking_number = 'SC-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
$resident_id = $resident['id'] ?? null;

$insert_sql = "INSERT INTO senior_applications 
    (user_id, resident_id, tracking_number, application_type, details, valid_id_path, id_picture_path, barangay_indigency_path, status)
    VALUES 
    (:user_id, :resident_id, :tracking, 'id_issuance', :details, :valid_id, :id_pic, :residency_proof, 'submitted')";

$stmt = $db->prepare($insert_sql);
$stmt->bindParam(':user_id',         $user_id);
$stmt->bindParam(':resident_id',     $resident_id);
$stmt->bindParam(':tracking',        $tracking_number);
$stmt->bindParam(':details',         $details);
$stmt->bindParam(':valid_id',        $valid_id_path);
$stmt->bindParam(':id_pic',          $id_picture_path);
$stmt->bindParam(':residency_proof', $barangay_indigency_path);

if ($stmt->execute()) {
    $notif_title = "Senior Citizen ID & Booklet Application Submitted";
    $notif_msg   = "Naisumite ang iyong aplikasyon para sa Senior Citizen ID at Purchase Booklet (Tracking: {$tracking_number}). Ipinoproseso na ito sa CSWDO Window 3 para sa endorsement sa City Mayor's Office.";
    $notif_stmt  = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'system')");
    $notif_stmt->execute([':uid' => $user_id, ':title' => $notif_title, ':msg' => $notif_msg]);

    echo json_encode([
        'success'         => true,
        'message'         => "Matagumpay na naisumite ang iyong aplikasyon para sa Senior Citizen ID at Purchase Booklet! Ang iyong Tracking Number ay {$tracking_number}.",
        'tracking_number' => $tracking_number
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Nagkaroon ng aberya sa pagsusumite. Pakisubukan muli.']);
}
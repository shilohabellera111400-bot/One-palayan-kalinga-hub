<?php
/**
 * submit-pwd-application-ajax.php
 * Focus: Issuance of Person with Disability Identification Card (PWD ID) and Purchase Booklet
 * CSWDO Palayan City - Special Lane Window 1
 * Eligibility: Bonafide resident of Palayan City with permanent disability or their caregiver.
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
checkRateLimit($user_id, 'pwd_application', 3, 10);

$database = new Database();
$db = $database->getConnection();

$res_stmt = $db->prepare("SELECT id, pwd, barangay FROM residents WHERE user_id = :uid LIMIT 1");
$res_stmt->bindParam(':uid', $user_id);
$res_stmt->execute();
$resident = $res_stmt->fetch(PDO::FETCH_ASSOC);

$disability_type = sanitize($_POST['disability_type'] ?? 'Orthopedic / Physical Disability');
$details         = sanitize($_POST['details'] ?? 'Application for PWD ID Card and Purchase Booklet');
$applicant_role  = sanitize($_POST['applicant_role'] ?? 'person_with_disability'); // PWD themselves or Caregiver

// Check duplicate active application
$dup_check = $db->prepare(
    "SELECT application_id, tracking_number FROM pwd_applications 
     WHERE user_id = :uid AND status IN ('submitted', 'reviewing') LIMIT 1"
);
$dup_check->bindParam(':uid', $user_id);
$dup_check->execute();
$existing_app = $dup_check->fetch(PDO::FETCH_ASSOC);

if ($existing_app) {
    echo json_encode([
        'success' => false,
        'message' => "Mayroon ka nang kasalukuyang aplikasyon para sa PWD ID at Purchase Booklet (Tracking #: {$existing_app['tracking_number']}). Mangyaring hintayin ang pagproseso sa CSWDO Window 1."
    ]);
    exit;
}

// Medical Certificate is required by Citizen's Charter
if (!isset($_FILES['medical_certificate']) || $_FILES['medical_certificate']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Kinakailangan mag-upload ng Medical Certificate na nilagdaan ng lisensyadong doktor o City Health Officer bilang patunay ng kapansanan.'
    ]);
    exit;
}

$uploader = new FileUploader();
$medical_cert_path = null;
$valid_id_path     = null;
$id_picture_path   = null;

try {
    $medical_cert_path = $uploader->upload($_FILES['medical_certificate'], 'pwd');

    if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
        $valid_id_path = $uploader->upload($_FILES['valid_id'], 'pwd');
    }

    if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
        $id_picture_path = $uploader->upload($_FILES['id_picture'], 'pwd');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
    exit;
}

$tracking_number = 'PWD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
$resident_id = $resident['id'] ?? null;

$insert_sql = "INSERT INTO pwd_applications 
    (user_id, resident_id, tracking_number, application_type, disability_type, device_requested, details, medical_certificate_path, valid_id_path, id_picture_path, status)
    VALUES 
    (:user_id, :resident_id, :tracking, 'pwd_id', :disability, 'PWD ID & Purchase Booklet', :details, :med_cert, :valid_id, :id_pic, 'submitted')";

$stmt = $db->prepare($insert_sql);
$stmt->bindParam(':user_id',     $user_id);
$stmt->bindParam(':resident_id', $resident_id);
$stmt->bindParam(':tracking',    $tracking_number);
$stmt->bindParam(':disability',  $disability_type);
$stmt->bindParam(':details',     $details);
$stmt->bindParam(':med_cert',    $medical_cert_path);
$stmt->bindParam(':valid_id',    $valid_id_path);
$stmt->bindParam(':id_pic',      $id_picture_path);

if ($stmt->execute()) {
    $notif_title = "PWD ID & Booklet Application Submitted";
    $notif_msg   = "Naisumite ang iyong aplikasyon para sa PWD Identification Card at Purchase Booklet (Tracking: {$tracking_number}). Ipinoproseso na ito sa CSWDO Window 1 para sa pagsusuri at endorsement sa City Mayor's Office.";
    $notif_stmt  = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'system')");
    $notif_stmt->execute([':uid' => $user_id, ':title' => $notif_title, ':msg' => $notif_msg]);

    echo json_encode([
        'success'         => true,
        'message'         => "Matagumpay na naisumite ang iyong aplikasyon para sa PWD ID at Purchase Booklet! Ang iyong Tracking Number ay {$tracking_number}.",
        'tracking_number' => $tracking_number
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Nagkaroon ng aberya sa pagsusumite. Pakisubukan muli.']);
}
<?php
// book-appointment-ajax.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rate-limit.php';
require_once __DIR__ . '/includes/appointment-helper.php';
require_once __DIR__ . '/includes/FileUploader.php';
require_once __DIR__ . '/includes/Mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to book an appointment']);
    exit;
}

// Staff and Admin cannot book appointments as residents
if (isStaff() || isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Staff members cannot book appointments as residents']);
    exit;
}

// Rate limit: max 5 booking attempts per 10 minutes
checkRateLimit((int)$_SESSION['user_id'], 'book_appointment', 5, 10);

// Detect input type: multipart/form-data or JSON
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_multipart = stripos($content_type, 'multipart/form-data') !== false;

if ($is_multipart) {
    $service_id = sanitize($_POST['service_id'] ?? '');
    $office_department = sanitize($_POST['office_department'] ?? 'Social Services / CSWDO');
    $appointment_date = sanitize($_POST['appointment_date'] ?? '');
    $appointment_time = sanitize($_POST['appointment_time'] ?? '');
    $purpose = sanitize($_POST['purpose'] ?? '');
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }
    $service_id = sanitize($data['service_id'] ?? '');
    $office_department = sanitize($data['office_department'] ?? 'Social Services / CSWDO');
    $appointment_date = sanitize($data['appointment_date'] ?? '');
    $appointment_time = sanitize($data['appointment_time'] ?? '');
    $purpose = sanitize($data['purpose'] ?? '');
}

if (empty($service_id) || empty($appointment_date) || empty($appointment_time)) {
    echo json_encode(['success' => false, 'message' => 'Service, date, and time slot are required']);
    exit;
}

// Validate date
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check blackout date
$blackout_stmt = $db->prepare("SELECT reason FROM blackout_dates WHERE blackout_date = :date LIMIT 1");
$blackout_stmt->execute([':date' => $appointment_date]);
$blackout = $blackout_stmt->fetch(PDO::FETCH_ASSOC);
if ($blackout) {
    $date_fmt = date('F d, Y', strtotime($appointment_date));
    echo json_encode(['success' => false, 'message' => "Hindi available ang {$date_fmt}: {$blackout['reason']}. Mangyaring pumili ng ibang petsa."]);
    exit;
}

// 1. DUPLICATE APPOINTMENT PREVENTION (per service)
// Prevent citizen from creating multiple active appointments for the same service
$active_statuses = ['pending', 'for_review', 'confirmed', 'rescheduled', 'checked_in', 'in_progress'];
$in_clause = "'" . implode("','", $active_statuses) . "'";

$dup_service = $db->prepare("SELECT a.appointment_id, a.reference_number, a.appointment_date, a.appointment_time, s.service_name 
                             FROM appointments a
                             JOIN services s ON a.service_id = s.service_id
                             WHERE a.user_id = :user_id 
                               AND a.service_id = :service_id 
                               AND a.status IN ($in_clause)
                             LIMIT 1");
$dup_service->execute([
    ':user_id'    => $_SESSION['user_id'],
    ':service_id' => $service_id
]);

if ($dup_service->rowCount() > 0) {
    $dup = $dup_service->fetch(PDO::FETCH_ASSOC);
    $dup_date = date('F d, Y', strtotime($dup['appointment_date']));
    $dup_ref = $dup['reference_number'] ?: ('#' . $dup['appointment_id']);
    echo json_encode([
        'success' => false,
        'message' => "You already have an active appointment for this service ({$dup['service_name']}) scheduled for {$dup_date} [Ref: {$dup_ref}]. Please wait for it to be completed or cancel it first."
    ]);
    exit;
}

// 2. ONE APPOINTMENT PER CALENDAR DAY POLICY
$daily_check = $db->prepare("SELECT a.appointment_id, s.service_name, a.appointment_time
                              FROM appointments a
                              JOIN services s ON a.service_id = s.service_id
                              WHERE a.user_id = :user_id 
                                AND a.appointment_date = :date 
                                AND a.status IN ($in_clause)
                              LIMIT 1");
$daily_check->execute([
    ':user_id' => $_SESSION['user_id'],
    ':date'    => $appointment_date
]);

if ($daily_check->rowCount() > 0) {
    $existing = $daily_check->fetch(PDO::FETCH_ASSOC);
    $existing_time = date('h:i A', strtotime($existing['appointment_time']));
    $date_formatted = date('F d, Y', strtotime($appointment_date));
    echo json_encode([
        'success' => false,
        'message' => "Isang appointment lamang ang maaaring i-book sa isang araw. Mayroon ka nang appointment para sa \"{$existing['service_name']}\" sa {$date_formatted} ng {$existing_time}. Mangyaring pumili ng ibang petsa."
    ]);
    exit;
}

// 3. CHECK TIME SLOT CAPACITY
$settings = getAppointmentSettings($db);
$max_slots = (int)$settings['max_clients_per_slot'];

$check_query = "SELECT COUNT(*) as count FROM appointments 
                WHERE appointment_date = :date AND appointment_time = :time 
                AND status NOT IN ('cancelled', 'rejected')";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute([
    ':date' => $appointment_date,
    ':time' => $appointment_time
]);
$slot_count = (int)$check_stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($slot_count >= $max_slots) {
    $time_fmt = date('h:i A', strtotime($appointment_time));
    echo json_encode([
        'success' => false,
        'message' => "Puno na (Fully Booked) ang time slot na {$time_fmt}. Mangyaring pumili ng ibang oras o petsa."
    ]);
    exit;
}

// Get service details
$service_query = "SELECT service_name FROM services WHERE service_id = :id";
$service_stmt = $db->prepare($service_query);
$service_stmt->execute([':id' => $service_id]);
$service = $service_stmt->fetch(PDO::FETCH_ASSOC);
$service_name = $service ? $service['service_name'] : 'Social Service';

// Get user info
$user_query = "SELECT email, first_name, last_name, contact_number FROM users WHERE user_id = :id";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([':id' => $_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_email = $user['email'] ?? '';
$user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Start transaction
$db->beginTransaction();

try {
    // Generate Reference Number and QR Token
    $reference_number = generateAppointmentRefNumber($db);
    $qr_token = generateQrToken();

    // Create appointment with status 'pending'
    $query = "INSERT INTO appointments 
              (user_id, service_id, office_department, reference_number, qr_token, appointment_date, appointment_time, purpose, status) 
              VALUES (:user_id, :service_id, :office_dept, :ref, :token, :date, :time, :purpose, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id'     => $_SESSION['user_id'],
        ':service_id'  => $service_id,
        ':office_dept' => $office_department,
        ':ref'         => $reference_number,
        ':token'       => $qr_token,
        ':date'        => $appointment_date,
        ':time'        => $appointment_time,
        ':purpose'     => $purpose
    ]);
    
    $appointment_id = (int)$db->lastInsertId();

    // Handle supporting document uploads
    if (!empty($_FILES['supporting_documents'])) {
        $files = $_FILES['supporting_documents'];
        $upload_dir = __DIR__ . '/uploads/appointments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Check if multiple files or single file
        $file_count = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $file_count; $i++) {
            $err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            if ($err === UPLOAD_ERR_OK) {
                $orig_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                if (in_array($ext, $allowed_exts) && $size <= 10485760) { // max 10MB
                    $new_filename = 'doc_' . $appointment_id . '_' . uniqid() . '.' . $ext;
                    $dest_path = $upload_dir . $new_filename;
                    $rel_path = 'uploads/appointments/' . $new_filename;

                    if (move_uploaded_file($tmp_name, $dest_path)) {
                        $doc_stmt = $db->prepare("INSERT INTO appointment_documents 
                            (appointment_id, document_name, file_path, file_type, file_size) 
                            VALUES (:aid, :name, :path, :type, :size)");
                        $doc_stmt->execute([
                            ':aid'  => $appointment_id,
                            ':name' => $orig_name,
                            ':path' => $rel_path,
                            ':type' => $ext,
                            ':size' => $size
                        ]);
                    }
                }
            }
        }
    }

    // Log in audit trail
    logAppointmentHistory(
        $db, 
        $appointment_id, 
        'submitted', 
        null, 
        'pending', 
        (int)$_SESSION['user_id'], 
        'resident', 
        'Resident submitted appointment request online.'
    );

    // Create in-app notification
    $notif_date_fmt = date('F d, Y', strtotime($appointment_date));
    $notif_time_fmt = date('h:i A', strtotime($appointment_time));
    $notif_msg = "Natanggap na ang inyong appointment request ({$reference_number}) para sa {$service_name} sa {$notif_date_fmt} ({$notif_time_fmt}). Kasalukuyan itong sinusuri ng Social Service Staff.";

    $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :msg, 'appointment')");
    $notif_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':title'   => 'Appointment Request Received [PENDING]',
        ':msg'     => $notif_msg
    ]);

    // Commit DB changes before external network calls (Email / SMS)
    $db->commit();

    // Send email to citizen (Non-blocking external service)
    if (!empty($user_email)) {
        try {
            $mailer = new Mailer();
            $appt_data_for_email = [
                'appointment_id'   => $appointment_id,
                'reference_number' => $reference_number,
                'service_name'     => $service_name,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'user_id'          => $_SESSION['user_id']
            ];
            $mailer->sendAppointmentBookingReceived($user_email, $user_name, $appt_data_for_email);
        } catch (Throwable $mail_err) {
            error_log("Failed sending booking email: " . $mail_err->getMessage());
        }
    }

    // Send SMS notification to citizen (Non-blocking external service)
    if (!empty($user['contact_number'])) {
        try {
            require_once __DIR__ . '/includes/SMSGateway.php';
            $smsGateway = new SMSGateway();
            $appt_data_for_sms = [
                'appointment_id'   => $appointment_id,
                'reference_number' => $reference_number,
                'service_name'     => $service_name,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'user_id'          => $_SESSION['user_id']
            ];
            $smsGateway->sendAppointmentBookingReceived($user['contact_number'], $user_name, $appt_data_for_sms);
        } catch (Throwable $sms_err) {
            error_log("Failed sending booking SMS: " . $sms_err->getMessage());
        }
    }

    echo json_encode([
        'success'          => true, 
        'message'          => 'Appointment request submitted successfully!',
        'reference_number' => $reference_number,
        'appointment_id'   => $appointment_id,
        'service_name'     => $service_name,
        'date'             => $notif_date_fmt,
        'time'             => $notif_time_fmt,
        'status'           => 'pending'
    ]);
    
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to book appointment: ' . $e->getMessage()
    ]);
}
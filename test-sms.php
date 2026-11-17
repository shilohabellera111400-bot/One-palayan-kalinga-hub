<?php
/**
 * TextBee SMS Gateway Tester
 * Palayan Kalinga Hub
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/sms_config.php';
require_once __DIR__ . '/includes/SMSGateway.php';

// Allow local testing or logged in staff/admins
$isLocal = (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) || isLocalHostEnv();
if (!isAdmin() && !isStaff() && !$isLocal) {
    die('Access denied: Staff or Admin privileges required.');
}

$result = null;
$error = null;
$successMsg = null;

// Handle saving credentials from test page if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    $newApiKey = trim($_POST['api_key'] ?? '');
    $newDeviceId = trim($_POST['device_id'] ?? '');

    if (!empty($newApiKey)) {
        $configFile = __DIR__ . '/config/sms_config.php';
        $content = "<?php\n"
                 . "/**\n * TextBee SMS Gateway Configuration\n * Palayan Kalinga Hub\n */\n\n"
                 . "define('TEXTBEE_API_KEY', " . var_export($newApiKey, true) . ");\n"
                 . "define('TEXTBEE_DEVICE_ID', " . var_export($newDeviceId, true) . ");\n"
                 . "define('SMS_ENABLED', true);\n"
                 . "define('TEXTBEE_BASE_URL', 'https://api.textbee.dev/api/v1/gateway');\n"
                 . "define('SMS_SENDER_NAME', 'Palayan CSWDO');\n";
        if (file_put_contents($configFile, $content)) {
            $successMsg = "Configuration saved successfully to config/sms_config.php!";
            // Reload config
            header("Location: test-sms.php?saved=1");
            exit;
        } else {
            $error = "Failed to write to config/sms_config.php. Check folder permissions.";
        }
    } else {
        $error = "API Key cannot be empty.";
    }
}

// Handle sending test SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_sms') {
    $testPhone = trim($_POST['test_phone'] ?? '');
    $testType  = trim($_POST['test_type'] ?? 'test');
    $customMsg = trim($_POST['custom_message'] ?? '');

    $apiKeyOverride   = !empty($_POST['api_key_override']) ? trim($_POST['api_key_override']) : null;
    $deviceIdOverride = !empty($_POST['device_id_override']) ? trim($_POST['device_id_override']) : null;

    $gateway = new SMSGateway($apiKeyOverride, $deviceIdOverride);

    switch ($testType) {
        case 'booking_received':
            $appointment = [
                'reference_number' => 'PAL-SOC-' . date('Y') . '-000123',
                'service_name'     => 'CSWDO Medical Assistance Consultation',
                'appointment_date' => date('Y-m-d', strtotime('+2 days')),
                'appointment_time' => '09:30:00',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAppointmentBookingReceived($testPhone, 'Juan Dela Cruz', $appointment);
            break;

        case 'appointment_confirmed':
        case 'appointment':
            $appointment = [
                'reference_number' => 'PAL-SOC-' . date('Y') . '-000123',
                'service_name'     => 'CSWDO Medical Assistance Consultation',
                'appointment_date' => date('Y-m-d', strtotime('+2 days')),
                'appointment_time' => '09:30:00',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAppointmentStatusUpdate($testPhone, 'Juan Dela Cruz', $appointment, 'confirmed');
            break;

        case 'appointment_rescheduled':
            $appointment = [
                'reference_number' => 'PAL-SOC-' . date('Y') . '-000123',
                'service_name'     => 'CSWDO Medical Assistance Consultation',
                'appointment_date' => date('Y-m-d', strtotime('+5 days')),
                'appointment_time' => '10:00:00',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAppointmentStatusUpdate($testPhone, 'Juan Dela Cruz', $appointment, 'rescheduled', 'Staff reassignment to Window 2.');
            break;

        case 'appointment_completed':
            $appointment = [
                'reference_number' => 'PAL-SOC-' . date('Y') . '-000123',
                'service_name'     => 'CSWDO Medical Assistance Consultation',
                'appointment_date' => date('Y-m-d'),
                'appointment_time' => '09:30:00',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAppointmentStatusUpdate($testPhone, 'Juan Dela Cruz', $appointment, 'completed');
            break;

        case 'aics_submitted':
            $aics = [
                'reference_number' => 'AICS-' . date('Y') . '-000789',
                'request_type'     => 'Medical Assistance',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAICSConfirmation($testPhone, 'Juan Dela Cruz', $aics);
            break;

        case 'aics_reviewing':
            $aics = [
                'reference_number' => 'AICS-' . date('Y') . '-000789',
                'request_type'     => 'Medical Assistance',
                'status'           => 'reviewing',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAICSUpdate($testPhone, 'Juan Dela Cruz', $aics);
            break;

        case 'aics_approved':
        case 'aics':
            $aics = [
                'reference_number' => 'AICS-' . date('Y') . '-000789',
                'request_type'     => 'Medical Assistance',
                'status'           => 'approved',
                'amount_approved'  => 5000.00,
                'payout_date'      => date('Y-m-d', strtotime('+3 days')),
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAICSUpdate($testPhone, 'Juan Dela Cruz', $aics);
            break;

        case 'aics_released':
            $aics = [
                'reference_number' => 'AICS-' . date('Y') . '-000789',
                'request_type'     => 'Medical Assistance',
                'status'           => 'released',
                'amount_approved'  => 5000.00,
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAICSUpdate($testPhone, 'Juan Dela Cruz', $aics);
            break;

        case 'aics_rejected':
            $aics = [
                'reference_number' => 'AICS-' . date('Y') . '-000789',
                'request_type'     => 'Medical Assistance',
                'status'           => 'rejected',
                'rejection_reason' => 'Incomplete clinical abstract/medical certificate.',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendAICSUpdate($testPhone, 'Juan Dela Cruz', $aics);
            break;

        case 'housing_submitted':
            $housing = [
                'reference_number' => 'HSG-' . date('Y') . '-00012',
                'housing_type'     => 'relocation',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendHousingConfirmation($testPhone, 'Juan Dela Cruz', $housing);
            break;

        case 'housing_qualified':
            $housing = [
                'reference_number' => 'HSG-' . date('Y') . '-00012',
                'housing_type'     => 'relocation',
                'status'           => 'qualified',
                'interview_date'   => date('Y-m-d', strtotime('+4 days')),
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendHousingUpdate($testPhone, 'Juan Dela Cruz', $housing);
            break;

        case 'housing_approved':
            $housing = [
                'reference_number' => 'HSG-' . date('Y') . '-00012',
                'housing_type'     => 'relocation',
                'status'           => 'approved',
                'unit_assigned'    => 'Bldg 3, Unit 204 (San Lorenzo Housing)',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendHousingUpdate($testPhone, 'Juan Dela Cruz', $housing);
            break;

        case 'housing_released':
            $housing = [
                'reference_number' => 'HSG-' . date('Y') . '-00012',
                'housing_type'     => 'relocation',
                'status'           => 'released',
                'move_in_date'     => date('Y-m-d', strtotime('+7 days')),
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendHousingUpdate($testPhone, 'Juan Dela Cruz', $housing);
            break;

        case 'housing_rejected':
            $housing = [
                'reference_number' => 'HSG-' . date('Y') . '-00012',
                'housing_type'     => 'relocation',
                'status'           => 'rejected',
                'remarks'          => 'Exceeded income eligibility ceiling.',
                'user_id'          => $_SESSION['user_id'] ?? null
            ];
            $result = $gateway->sendHousingUpdate($testPhone, 'Juan Dela Cruz', $housing);
            break;

        case 'otp':
            $fakeCode = strval(random_int(100000, 999999));
            $result = $gateway->sendPasswordResetOTP($testPhone, 'Juan Dela Cruz', $fakeCode);
            break;

        case 'security_alert':
            $result = $gateway->sendPasswordChangeAlert($testPhone, 'Juan Dela Cruz', '127.0.0.1');
            break;

        case 'verification_approved':
            $result = $gateway->sendAccountVerifiedSMS($testPhone, 'Juan Dela Cruz', $_SESSION['user_id'] ?? null);
            break;

        case 'verification_rejected':
            $result = $gateway->sendVerificationRejectedSMS($testPhone, 'Juan Dela Cruz', 'Malabo o putol ang litrato ng Valid ID.', $_SESSION['user_id'] ?? null);
            break;

        case 'verification_submitted':
            $result = $gateway->sendVerificationRequestReceivedSMS($testPhone, 'Juan Dela Cruz', $_SESSION['user_id'] ?? null);
            break;

        case 'registration_welcome':
            $result = $gateway->sendAccountRegistrationWelcome($testPhone, 'Juan Dela Cruz', 'juan_delacruz', $_SESSION['user_id'] ?? null);
            break;

        case 'custom':
            if (empty($customMsg)) {
                $error = "Custom message cannot be empty.";
            } else {
                $result = $gateway->sendSMS($testPhone, $customMsg, $_SESSION['user_id'] ?? null);
            }
            break;

        case 'test':
        default:
            $msg = "[Palayan CSWDO] Test message from Palayan Kalinga Hub. If you received this, your TextBee SMS Gateway is connected and working! Sent at " . date('h:i:s A');
            $result = $gateway->sendSMS($testPhone, $msg, $_SESSION['user_id'] ?? null);
            break;
    }
}

// Check current config status
$currentKey = defined('TEXTBEE_API_KEY') ? TEXTBEE_API_KEY : '';
$currentDev = defined('TEXTBEE_DEVICE_ID') ? TEXTBEE_DEVICE_ID : '';
$isConfigured = !empty($currentKey) && $currentKey !== 'YOUR_TEXTBEE_API_KEY';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TextBee SMS Gateway Tester - Palayan Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #eef2f5;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1a365d;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .badge-ready { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #4a5568;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.2);
        }
        .btn-submit {
            background: #2b6cb0;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #2c5282;
        }
        .btn-secondary {
            background: #718096;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        .code-block {
            background: #1a202c;
            color: #68d391;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #edf2f7;
            color: #4a5568;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>TextBee SMS Gateway Tester</h1>
            <p style="margin: 4px 0 0; color: #718096; font-size: 14px;">Palayan Kalinga Hub &bull; Android SIM Gateway Integration</p>
        </div>
        <div>
            <?php if ($isConfigured): ?>
                <span class="badge badge-ready">&#10003; Configured</span>
            <?php else: ?>
                <span class="badge badge-pending">&#9888; Credentials Needed</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Configuration saved successfully!</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="alert <?php echo $result['success'] ? 'alert-success' : 'alert-error'; ?>">
            <strong><?php echo $result['success'] ? 'Success!' : 'Dispatch Error:'; ?></strong>
            <?php echo htmlspecialchars($result['message']); ?>
            <div style="margin-top: 6px; font-size: 13px;">
                Recipient: <code><?php echo htmlspecialchars($result['formatted_recipient'] ?? ''); ?></code>
                &bull; HTTP Code: <code><?php echo htmlspecialchars((string)($result['http_code'] ?? '')); ?></code>
            </div>
        </div>

        <?php if (!empty($result['data'])): ?>
            <details style="margin-bottom: 20px;">
                <summary style="cursor: pointer; font-size: 14px; color: #3182ce; font-weight: 600;">View Raw API Response</summary>
                <div class="code-block" style="margin-top: 8px;">
                    <?php echo htmlspecialchars(is_string($result['data']) ? $result['data'] : json_encode($result['data'], JSON_PRETTY_PRINT)); ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Step 1: Configuration Card -->
    <details <?php echo !$isConfigured ? 'open' : ''; ?> class="card" style="margin-bottom: 20px;">
        <summary style="font-weight: 600; cursor: pointer; color: #2d3748; font-size: 16px;">
            1. TextBee Credentials Settings
        </summary>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="action" value="save_config">
            <div class="form-group">
                <label>TextBee API Key: <small style="color: #718096;">(From Dashboard &rarr; API Keys)</small></label>
                <input type="text" name="api_key" class="form-control" required
                       placeholder="e.g. 8f4b6a... or paste your key here"
                       value="<?php echo htmlspecialchars($currentKey !== 'YOUR_TEXTBEE_API_KEY' ? $currentKey : ''); ?>">
            </div>
            <div class="form-group">
                <label>TextBee Device ID: <small style="color: #718096;">(From Dashboard &rarr; Devices, e.g. 660f8...)</small></label>
                <input type="text" name="device_id" class="form-control"
                       placeholder="Optional if you only have 1 active device"
                       value="<?php echo htmlspecialchars($currentDev !== 'YOUR_TEXTBEE_DEVICE_ID' ? $currentDev : ''); ?>">
            </div>
            <button type="submit" class="btn-secondary">Save Credentials to config/sms_config.php</button>
        </form>
    </details>

    <!-- Step 2: Send Test SMS Form -->
    <div class="card">
        <h3 style="margin-top: 0; color: #2d3748; font-size: 17px;">2. Send Test SMS Message</h3>
        <form method="POST">
            <input type="hidden" name="action" value="send_sms">
            
            <div class="form-group">
                <label>Recipient Mobile Number (Philippines):</label>
                <input type="text" name="test_phone" class="form-control" required
                       placeholder="09171234567 or +639171234567"
                       value="<?php echo htmlspecialchars($_POST['test_phone'] ?? ''); ?>">
                <small style="color: #718096; display: block; margin-top: 4px;">
                    Enter any PH format (e.g. 0917..., +63917..., 917...). It will automatically format to <code>+639...</code>
                </small>
            </div>

            <div class="form-group">
                <label>Message Type:</label>
                <select name="test_type" id="testTypeSelect" class="form-control" onchange="toggleCustomMessage()">
                    <option value="test">Basic Connectivity Test</option>
                    <optgroup label="Appointment Notification Templates">
                        <option value="booking_received">1. Appointment Request Received (Pending Review)</option>
                        <option value="appointment_confirmed">2. Appointment Confirmed / Approved</option>
                        <option value="appointment_rescheduled">3. Appointment Rescheduled</option>
                        <option value="appointment_completed">4. Appointment Completed</option>
                    </optgroup>
                    <optgroup label="AICS Financial Assistance Templates">
                        <option value="aics_submitted">5. AICS Application Submitted (Pending)</option>
                        <option value="aics_reviewing">6. AICS Under Review (Interview Notice)</option>
                        <option value="aics_approved">7. AICS Approved (₱5,000 Budget Voucher)</option>
                        <option value="aics_released">8. AICS Released (Payout Complete)</option>
                        <option value="aics_rejected">9. AICS Rejected / Disapproved</option>
                    </optgroup>
                    <optgroup label="Socialized Housing Program Templates">
                        <option value="housing_submitted">10. Housing Application Submitted (Pending)</option>
                        <option value="housing_qualified">11. Housing Qualified (Inspection/Interview Notice)</option>
                        <option value="housing_approved">12. Housing Approved (Unit 204 Assigned)</option>
                        <option value="housing_released">13. Housing Released (Turnover Complete)</option>
                        <option value="housing_rejected">14. Housing Disapproved / Withdrawn</option>
                    </optgroup>
                    <optgroup label="Account Security & Authentication">
                        <option value="otp">15. Password Reset 6-Digit OTP Code</option>
                        <option value="security_alert">16. Password Change Security Alert</option>
                    </optgroup>
                    <optgroup label="Citizen Verification & Account Readiness">
                        <option value="verification_approved">17. Account Verified & Ready to Use (Approved)</option>
                        <option value="verification_rejected">18. Verification Rejected (With Reason)</option>
                        <option value="verification_submitted">19. Verification Request Submitted (Pending Review)</option>
                        <option value="registration_welcome">20. New Account Registration Welcome</option>
                    </optgroup>
                    <optgroup label="Other">
                        <option value="custom">Custom Text Message</option>
                    </optgroup>
                </select>
            </div>

            <div class="form-group" id="customMsgBox" style="display: none;">
                <label>Custom Message:</label>
                <textarea name="custom_message" class="form-control" rows="3" placeholder="Type custom SMS content here..."></textarea>
            </div>

            <button type="submit" class="btn-submit">&#128241; Send Test SMS Now</button>
        </form>
    </div>

    <!-- Step 3: Recent SMS Logs -->
    <div style="margin-top: 30px;">
        <h3 style="margin-bottom: 8px; color: #2d3748;">Recent SMS Logs</h3>
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            $logs = $db->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 10");
            $rows = $logs ? $logs->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $rows = [];
        }
        ?>
        <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 130px;">Date / Time</th>
                        <th style="width: 140px;">Recipient</th>
                        <th>Message</th>
                        <th style="width: 90px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo date('M d, h:i A', strtotime($row['created_at'])); ?></td>
                            <td><code><?php echo htmlspecialchars($row['recipient']); ?></code></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($row['message'], 0, 60, '...')); ?></td>
                            <td>
                                <span style="font-weight: 600; color: <?php echo $row['status'] === 'sent' ? '#38a169' : '#e53e3e'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #718096; font-size: 14px;">No SMS logs found yet. Once you send a message, delivery logs will appear here.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCustomMessage() {
    var select = document.getElementById('testTypeSelect');
    var customBox = document.getElementById('customMsgBox');
    if (select.value === 'custom') {
        customBox.style.display = 'block';
    } else {
        customBox.style.display = 'none';
    }
}
</script>

</body>
</html>

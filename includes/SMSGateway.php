<?php
/**
 * TextBee SMS Gateway Service
 * Palayan Kalinga Hub
 * 
 * Provides automated SMS dispatch via TextBee Android Gateway API
 */

require_once __DIR__ . '/../config/sms_config.php';
require_once __DIR__ . '/../config/database.php';

class SMSGateway
{
    private string $apiKey;
    private string $deviceId;
    private string $baseUrl;
    private bool $enabled;
    private ?PDO $db = null;

    public function __construct(?string $apiKey = null, ?string $deviceId = null)
    {
        $this->apiKey = $apiKey ?: (defined('TEXTBEE_API_KEY') ? TEXTBEE_API_KEY : '');
        $this->deviceId = $deviceId ?: (defined('TEXTBEE_DEVICE_ID') ? TEXTBEE_DEVICE_ID : '');
        $this->baseUrl = defined('TEXTBEE_BASE_URL') ? rtrim(TEXTBEE_BASE_URL, '/') : 'https://api.textbee.dev/api/v1/gateway';
        $this->enabled = defined('SMS_ENABLED') ? (bool)SMS_ENABLED : true;

        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->ensureLogsTableExists();
        } catch (Throwable $e) {
            error_log("SMSGateway DB connection note: " . $e->getMessage());
        }
    }

    /**
     * Ensure sms_logs table exists for tracking
     */
    private function ensureLogsTableExists(): void
    {
        if (!$this->db) return;
        // Never execute DDL statements if connection has an active transaction (prevents MySQL implicit commit)
        if ($this->db->inTransaction()) return;

        static $logsTableChecked = false;
        if ($logsTableChecked) return;
        $logsTableChecked = true;

        try {
            $sql = "CREATE TABLE IF NOT EXISTS `sms_logs` (
                `sms_id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NULL,
                `recipient` VARCHAR(30) NOT NULL,
                `message` TEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `response` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->db->exec($sql);
        } catch (Throwable $e) {
            // Silently continue if table creation is restricted
        }
    }

    /**
     * Normalize Philippine phone numbers into E.164 international format (+639XXXXXXXXX)
     * 
     * Handles:
     * - 09171234567 -> +639171234567
     * - 9171234567  -> +639171234567
     * - 639171234567 -> +639171234567
     * - +639171234567 -> +639171234567
     * 
     * @param string $phone
     * @return string|false E.164 format or false if invalid
     */
    public static function formatPhoneNumber(string $phone)
    {
        // Strip out spaces, dashes, dots, brackets
        $clean = preg_replace('/[^0-9+]/', '', trim($phone));

        // If starts with +63 and followed by 10 digits
        if (preg_match('/^\+639\d{9}$/', $clean)) {
            return $clean;
        }

        // If starts with 639 and followed by 9 digits
        if (preg_match('/^639\d{9}$/', $clean)) {
            return '+' . $clean;
        }

        // If starts with 09 and followed by 9 digits (standard PH local format)
        if (preg_match('/^09\d{9}$/', $clean)) {
            return '+63' . substr($clean, 1);
        }

        // If starts with 9 and has 10 digits
        if (preg_match('/^9\d{9}$/', $clean)) {
            return '+63' . $clean;
        }

        return false;
    }

    /**
     * Send a single SMS via TextBee Gateway
     * 
     * @param string $recipient Phone number (any standard PH format)
     * @param string $message Text content
     * @param int|null $user_id Optional user ID for audit logging
     * @return array ['success' => bool, 'message' => string, 'data' => mixed, 'formatted_recipient' => string]
     */
    public function sendSMS(string $recipient, string $message, ?int $user_id = null): array
    {
        $formatted = self::formatPhoneNumber($recipient);
        if (!$formatted) {
            $err = "Invalid Philippine mobile number: {$recipient}. Expected format: 09XXXXXXXXX or +639XXXXXXXXX";
            $this->logSMS($recipient, $message, 'invalid_number', $err, $user_id);
            return [
                'success' => false,
                'message' => $err,
                'formatted_recipient' => $recipient,
                'http_code' => 400
            ];
        }

        // Check if SMS dispatch is turned off
        if (!$this->enabled) {
            $msg = "SMS sending is disabled (SMS_ENABLED = false). Message simulated.";
            $this->logSMS($formatted, $message, 'simulated', $msg, $user_id);
            return [
                'success' => true,
                'message' => $msg,
                'formatted_recipient' => $formatted,
                'http_code' => 200
            ];
        }

        // Check if API key is configured
        if (empty($this->apiKey) || $this->apiKey === 'YOUR_TEXTBEE_API_KEY') {
            $err = "TextBee API Key is not configured. Please set your key in config/sms_config.php";
            $this->logSMS($formatted, $message, 'config_error', $err, $user_id);
            return [
                'success' => false,
                'message' => $err,
                'formatted_recipient' => $formatted,
                'http_code' => 401
            ];
        }

        // Prepare TextBee payload
        // TextBee endpoint: POST https://api.textbee.dev/api/v1/gateway/send-sms
        // Or device-specific: POST https://api.textbee.dev/api/v1/gateway/devices/{deviceId}/sendSMS
        $endpoint = $this->baseUrl . '/send-sms';
        
        $payload = [
            'recipients' => [$formatted],
            'message'    => $message
        ];

        // Attach deviceId if specified
        if (!empty($this->deviceId) && $this->deviceId !== 'YOUR_TEXTBEE_DEVICE_ID') {
            $payload['deviceId'] = $this->deviceId;
        }

        // Send via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // SSL verification handling (compatible with local XAMPP environment)
        $isLocal = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) || (PHP_SAPI === 'cli');
        if ($isLocal) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $rawResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            $err = "cURL Connection Error: " . $curlError;
            $this->logSMS($formatted, $message, 'curl_error', $err, $user_id);
            return [
                'success' => false,
                'message' => $err,
                'formatted_recipient' => $formatted,
                'http_code' => $httpCode ?: 500
            ];
        }

        $decoded = json_decode($rawResponse, true);

        // TextBee typically returns HTTP 200 or 201 on success
        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logSMS($formatted, $message, 'sent', $rawResponse, $user_id);
            return [
                'success' => true,
                'message' => 'SMS sent successfully via TextBee!',
                'data' => $decoded ?? $rawResponse,
                'formatted_recipient' => $formatted,
                'http_code' => $httpCode
            ];
        }

        // If TextBee returns an error response
        $errMsg = 'TextBee error (HTTP ' . $httpCode . ')';
        if (is_array($decoded)) {
            $errMsg = $decoded['message'] ?? $decoded['error'] ?? json_encode($decoded);
        } else if (!empty($rawResponse)) {
            $errMsg = $rawResponse;
        }

        $this->logSMS($formatted, $message, 'failed', $errMsg, $user_id);
        return [
            'success' => false,
            'message' => $errMsg,
            'data' => $decoded,
            'formatted_recipient' => $formatted,
            'http_code' => $httpCode
        ];
    }

    /**
     * Send bulk SMS to multiple recipients
     */
    public function sendBulkSMS(array $recipients, string $message): array
    {
        $validPhones = [];
        $invalid = [];

        foreach ($recipients as $r) {
            $fmt = self::formatPhoneNumber($r);
            if ($fmt) {
                $validPhones[] = $fmt;
            } else {
                $invalid[] = $r;
            }
        }

        if (empty($validPhones)) {
            return [
                'success' => false,
                'message' => 'No valid mobile numbers provided',
                'invalid' => $invalid
            ];
        }

        $endpoint = $this->baseUrl . '/send-sms';
        $payload = [
            'recipients' => array_values(array_unique($validPhones)),
            'message'    => $message
        ];

        if (!empty($this->deviceId) && $this->deviceId !== 'YOUR_TEXTBEE_DEVICE_ID') {
            $payload['deviceId'] = $this->deviceId;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);

        foreach ($validPhones as $p) {
            $this->logSMS($p, $message, $success ? 'sent' : 'failed', $response);
        }

        return [
            'success' => $success,
            'total_queued' => count($validPhones),
            'invalid_numbers' => $invalid,
            'response' => json_decode($response, true) ?? $response
        ];
    }

    /**
     * Log SMS to database
     */
    public function logSMS(string $recipient, string $message, string $status, ?string $response = null, ?int $user_id = null): void
    {
        if (!$this->db) return;
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO sms_logs (user_id, recipient, message, status, response, created_at) 
                 VALUES (:user_id, :recipient, :message, :status, :response, NOW())"
            );
            $stmt->execute([
                ':user_id'   => $user_id,
                ':recipient' => $recipient,
                ':message'   => $message,
                ':status'    => $status,
                ':response'  => $response ? substr($response, 0, 1000) : null
            ]);
        } catch (Throwable $e) {
            error_log("Failed to log SMS: " . $e->getMessage());
        }
    }

    // ─── Pre-built CSWDO SMS Notification Templates ─────────────────────────────

    /**
     * Template: Appointment Booking Request Received (Pending Review)
     */
    public function sendAppointmentBookingReceived(string $phone, string $fullName, array $appointment): array
    {
        $refNo   = $appointment['reference_number'] ?? ('APT-' . ($appointment['appointment_id'] ?? ''));
        $service = $appointment['service_name'] ?? 'Social Service';
        $date    = isset($appointment['appointment_date']) ? date('M d, Y', strtotime($appointment['appointment_date'])) : '';
        $time    = isset($appointment['appointment_time']) ? date('h:i A', strtotime($appointment['appointment_time'])) : '';

        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Hi {$fullName}, natanggap na ang inyong appointment request para sa {$service} sa {$date} ({$time}). Ref: {$refNo}. Kasalukuyan itong sinusuri ng CSWDO Staff.";

        return $this->sendSMS($phone, $msg, $appointment['user_id'] ?? null);
    }

    /**
     * Template: Appointment Status Update (Confirmed, Rescheduled, Rejected, Cancelled, Completed, etc.)
     */
    public function sendAppointmentStatusUpdate(string $phone, string $fullName, array $appointment, string $status, string $notes = ''): array
    {
        $refNo   = $appointment['reference_number'] ?? ('APT-' . ($appointment['appointment_id'] ?? ''));
        $service = $appointment['service_name'] ?? 'Social Service';
        $date    = isset($appointment['appointment_date']) ? date('M d, Y', strtotime($appointment['appointment_date'])) : '';
        $time    = isset($appointment['appointment_time']) ? date('h:i A', strtotime($appointment['appointment_time'])) : '';
        $prefix  = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';

        switch (strtolower($status)) {
            case 'confirmed':
            case 'approved':
                $msg = "{$prefix}Hi {$fullName}, KUMPIRMADO na ang inyong appointment ({$refNo}) para sa {$service} sa {$date} ({$time}). Dalhin ang inyong valid ID at appointment slip.";
                break;

            case 'rescheduled':
                $reason = !empty($notes) ? " Dahilan: {$notes}" : "";
                $msg = "{$prefix}Hi {$fullName}, ang inyong appointment ({$refNo}) ay NA-RESCHEDULE sa {$date} ({$time}).{$reason}";
                break;

            case 'rejected':
                $reason = !empty($notes) ? " Dahilan: {$notes}" : "";
                $msg = "{$prefix}Hi {$fullName}, ikinalulungkot naming ipabatid na hindi naaprubahan ang inyong appointment ({$refNo}).{$reason}";
                break;

            case 'cancelled':
                $msg = "{$prefix}Hi {$fullName}, ang inyong appointment ({$refNo}) para sa {$service} ay NAKANSELA.";
                break;

            case 'checked_in':
                $msg = "{$prefix}Hi {$fullName}, kayo ay matagumpay nang na-check-in para sa inyong appointment ({$refNo}). Mangyaring maghintay na tawagin sa tanggapan.";
                break;

            case 'completed':
                $msg = "{$prefix}Hi {$fullName}, tapos na ang inyong appointment ({$refNo}) para sa {$service}. Maraming salamat sa inyong pagbisita sa CSWDO Palayan City!";
                break;

            default:
                $statusUpper = strtoupper(str_replace('_', ' ', $status));
                $noteText = !empty($notes) ? " ({$notes})" : "";
                $msg = "{$prefix}Hi {$fullName}, ang status ng inyong appointment ({$refNo}) ay na-update sa {$statusUpper}.{$noteText}";
                break;
        }

        return $this->sendSMS($phone, $msg, $appointment['user_id'] ?? null);
    }

    /**
     * Template: Appointment Confirmation
     */
    public function sendAppointmentConfirmation(string $phone, string $fullName, array $appointment): array
    {
        return $this->sendAppointmentStatusUpdate($phone, $fullName, $appointment, 'confirmed');
    }

    /**
     * Template: AICS Application Submitted Confirmation
     */
    public function sendAICSConfirmation(string $phone, string $fullName, array $aics): array
    {
        $tracking = !empty($aics['reference_number']) ? $aics['reference_number'] : ('AICS-' . str_pad((string)($aics['aics_id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $type = !empty($aics['request_type']) ? ucwords(str_replace('_', ' ', $aics['request_type'])) : 'Financial Assistance';
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Magandang araw {$fullName}, natanggap na ang inyong AICS {$type} application. Tracking: {$tracking}. Kasalukuyan itong sinusuri ng tanggapan.";

        return $this->sendSMS($phone, $msg, $aics['user_id'] ?? null);
    }

    /**
     * Template: AICS Financial Assistance Status Update
     */
    public function sendAICSUpdate(string $phone, string $fullName, array $aics): array
    {
        $tracking = !empty($aics['reference_number']) ? $aics['reference_number'] : ('AICS-' . str_pad((string)($aics['aics_id'] ?? 0), 6, '0', STR_PAD_LEFT));
        $status = strtolower($aics['status'] ?? 'updated');
        $type = !empty($aics['request_type']) ? ucwords(str_replace('_', ' ', $aics['request_type'])) : 'Financial Assistance';
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';

        switch ($status) {
            case 'reviewing':
                $msg = "{$prefix}Magandang araw {$fullName}, ang inyong AICS {$type} ({$tracking}) ay UNDER REVIEW na. Pumunta sa CSWDO Window 1-3 dala ang orihinal na mga dokumento para sa assessment.";
                break;

            case 'approved':
                $amountText = !empty($aics['amount_approved']) ? " [Approved: ₱" . number_format((float)$aics['amount_approved'], 2) . "]" : "";
                $payoutText = !empty($aics['payout_date']) ? " Payout schedule: " . date('M d, Y', strtotime($aics['payout_date'])) . ". Dalhin ang inyong valid ID at claim stub." : " Naipasa na ito sa City Budget Office para sa voucher. Maghintay ng text para sa payout schedule.";
                $msg = "{$prefix}Magandang araw {$fullName}, APPROVED na ang inyong AICS {$type} ({$tracking}){$amountText}.{$payoutText}";
                break;

            case 'released':
                $amountText = !empty($aics['amount_approved']) ? " (₱" . number_format((float)$aics['amount_approved'], 2) . ")" : "";
                $msg = "{$prefix}Magandang araw {$fullName}, ang inyong AICS financial assistance ({$tracking}){$amountText} ay matagumpay nang nai-release. Maraming salamat!";
                break;

            case 'rejected':
                $reason = !empty($aics['rejection_reason']) ? " Dahilan: {$aics['rejection_reason']}" : "";
                $msg = "{$prefix}Magandang araw {$fullName}, ikinalulungkot naming ipabatid na hindi naaprubahan ang inyong AICS application ({$tracking}).{$reason}";
                break;

            default:
                $statusUpper = strtoupper(str_replace('_', ' ', $status));
                $msg = "{$prefix}Magandang araw {$fullName}, ang inyong AICS application ({$tracking}) status ay na-update sa: {$statusUpper}.";
                break;
        }

        return $this->sendSMS($phone, $msg, $aics['user_id'] ?? null);
    }

    /**
     * Template: Housing Application Submitted Confirmation
     */
    public function sendHousingConfirmation(string $phone, string $fullName, array $housing): array
    {
        $app_year = !empty($housing['application_date']) ? date('Y', strtotime($housing['application_date'])) : date('Y');
        $tracking = $housing['reference_number'] ?? ("HSG-" . $app_year . "-" . str_pad((string)($housing['beneficiary_id'] ?? 0), 5, '0', STR_PAD_LEFT));
        $is_insite = (($housing['housing_type'] ?? '') === 'in_site');
        $typeLabel = $is_insite ? 'In-Site Housing (Sariling Lupa)' : 'Relocation Housing';
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Magandang araw {$fullName}, natanggap na ang inyong {$typeLabel} application ({$tracking}). Kasalukuyan itong sinusuri ng Housing & Resettlement Division.";

        return $this->sendSMS($phone, $msg, $housing['user_id'] ?? null);
    }

    /**
     * Template: Housing Application Status Update
     */
    public function sendHousingUpdate(string $phone, string $fullName, array $housing): array
    {
        $app_year = !empty($housing['application_date']) ? date('Y', strtotime($housing['application_date'])) : date('Y');
        $tracking = $housing['reference_number'] ?? ("HSG-" . $app_year . "-" . str_pad((string)($housing['beneficiary_id'] ?? 0), 5, '0', STR_PAD_LEFT));
        $status = strtolower($housing['status'] ?? 'updated');
        $is_insite = (($housing['housing_type'] ?? '') === 'in_site');
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';

        switch ($status) {
            case 'pending':
                $msg = "{$prefix}Magandang araw {$fullName}, ang inyong Housing application ({$tracking}) ay nasa PENDING SCREENING status.";
                break;

            case 'qualified':
                if (!empty($housing['interview_date'])) {
                    $sched = date('M d, Y', strtotime($housing['interview_date']));
                    $action = $is_insite ? "site inspection sa inyong residential lot" : "intake interview sa Window 2 (Housing Division)";
                    $msg = "{$prefix}Magandang araw {$fullName}, QUALIFIED na ang inyong Housing application ({$tracking}). Schedule ng {$action}: {$sched} (09:00 AM). Dalhin ang inyong Claim Stub at original documents.";
                } else {
                    $msg = "{$prefix}Magandang araw {$fullName}, QUALIFIED na ang inyong Housing application ({$tracking}). Mangyaring maghintay ng text para sa inyong schedule ng assessment.";
                }
                break;

            case 'approved':
                $unitLabel = $is_insite ? 'Grant Code' : 'Assigned Unit';
                $unitText = !empty($housing['unit_assigned']) ? " [{$unitLabel}: {$housing['unit_assigned']}]" : "";
                $msg = "{$prefix}Pagbati {$fullName}! APPROVED na ang inyong Socialized Housing assistance ({$tracking}){$unitText}. Maaari na ninyong i-download ang inyong updated Claim Stub sa portal.";
                break;

            case 'released':
                $moveIn = !empty($housing['move_in_date']) ? " Schedule: " . date('M d, Y', strtotime($housing['move_in_date'])) . "." : "";
                $msg = "{$prefix}Pagbati {$fullName}! Ang inyong housing assistance package ({$tracking}) ay opisyal nang nai-turn over at nai-release.{$moveIn} Maraming salamat sa pakikipagtulungan!";
                break;

            case 'rejected':
                $reason = !empty($housing['remarks']) ? " Dahilan: {$housing['remarks']}" : "";
                $msg = "{$prefix}Magandang araw {$fullName}, ikinalulungkot naming ipabatid na hindi naaprubahan ang inyong Housing application ({$tracking}).{$reason}";
                break;

            default:
                $statusUpper = strtoupper(str_replace('_', ' ', $status));
                $msg = "{$prefix}Magandang araw {$fullName}, ang inyong Housing application ({$tracking}) status ay na-update sa {$statusUpper}.";
                break;
        }

        return $this->sendSMS($phone, $msg, $housing['user_id'] ?? null);
    }

    /**
     * Template: Password Reset OTP Verification Code
     */
    public function sendPasswordResetOTP(string $phone, string $fullName, string $otpCode, ?int $user_id = null): array
    {
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Ang inyong 6-digit verification code para sa Palayan Kalinga Hub ay: {$otpCode}. Ito ay balido sa loob ng 15 minuto. HUWAG itong ibahagi kaninuman.";

        return $this->sendSMS($phone, $msg, $user_id);
    }

    /**
     * Template: Security Alert for Changed Password
     */
    public function sendPasswordChangeAlert(string $phone, string $fullName, string $ip = '', ?int $user_id = null): array
    {
        $time = date('M d, Y h:i A');
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}SECURITY ALERT: Matagumpay na napalitan ang password ng inyong account noong {$time}. Kung hindi ikaw ito, makipag-ugnayan agad sa CSWDO Palayan.";

        return $this->sendSMS($phone, $msg, $user_id);
    }

    /**
     * Template: Account Verified & Ready to Use (Approved)
     */
    public function sendAccountVerifiedSMS(string $phone, string $fullName, ?int $user_id = null): array
    {
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Pagbati {$fullName}! APPROVED na ang inyong residency verification. Ang inyong account ay ganap nang VERIFIED at handa nang gamitin para sa Appointments, AICS Assistance, at Housing. Mag-log in sa portal upang magsimula.";

        return $this->sendSMS($phone, $msg, $user_id);
    }

    /**
     * Template: Residency Verification Rejected
     */
    public function sendVerificationRejectedSMS(string $phone, string $fullName, string $reason = '', ?int $user_id = null): array
    {
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $reasonText = !empty($reason) ? " Dahilan: {$reason}." : "";
        $msg = "{$prefix}Magandang araw {$fullName}, hindi naaprubahan ang inyong residency verification.{$reasonText} Mag-log in sa Palayan Kalinga Hub upang mag-upload ng panibagong valid ID.";

        return $this->sendSMS($phone, $msg, $user_id);
    }

    /**
     * Template: Verification Request Submitted Acknowledgement
     */
    public function sendVerificationRequestReceivedSMS(string $phone, string $fullName, ?int $user_id = null): array
    {
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Magandang araw {$fullName}, natanggap na ang inyong Resident Verification documents. Kasalukuyan itong sinusuri ng tanggapan. Maghintay ng text notification kapag na-verify na ang inyong account.";

        return $this->sendSMS($phone, $msg, $user_id);
    }

    /**
     * Template: Account Registration Welcome
     */
    public function sendAccountRegistrationWelcome(string $phone, string $fullName, string $username, ?int $user_id = null): array
    {
        $prefix = defined('SMS_SENDER_NAME') ? '[' . SMS_SENDER_NAME . '] ' : '';
        $msg = "{$prefix}Welcome sa Palayan Kalinga Hub, {$fullName}! Matagumpay ang inyong pagpaparehistro (Username: {$username}). I-kumpleto ang inyong Resident Verification upang ma-unlock ang lahat ng serbisyo.";

        return $this->sendSMS($phone, $msg, $user_id);
    }
}

<?php
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mail;
    private $db;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = MAIL_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = MAIL_USERNAME;
        $this->mail->Password = MAIL_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = MAIL_PORT;

        // Default settings
        $this->mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $this->mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';

        // Local SSL workaround for XAMPP/development environments
        if (
            (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
            (PHP_SAPI === 'cli')
        ) {
            $this->mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        // Database connection for logging
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Send email and log to database
     */
    public function send($to, $subject, $body, $user_id = null)
    {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();

            // Add recipient
            $this->mail->addAddress($to);

            // Email content
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body));

            // Send email
            $sent = $this->mail->send();

            // Log to database
            $this->logEmail($to, $subject, $user_id, 'sent');

            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (Exception $e) {
            // Log error
            $this->logEmail($to, $subject, $user_id, 'failed', $this->mail->ErrorInfo);

            return ['success' => false, 'message' => $this->mail->ErrorInfo];
        }
    }

    /**
     * Queue an email into database for asynchronous sending
     */
    public function queueEmail($to, $subject, $body, $recipient_name = '')
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO email_queue (recipient, recipient_name, subject, body, status, created_at) VALUES (:to, :name, :subject, :body, 'pending', NOW())");
            $stmt->bindParam(':to', $to);
            $stmt->bindParam(':name', $recipient_name);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':body', $body);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to queue email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process pending emails from queue
     */
    public function processQueue($limit = 10)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT :limit");
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = 0;
            foreach ($items as $item) {
                $res = $this->send($item['recipient'], $item['subject'], $item['body']);
                $newStatus = $res['success'] ? 'sent' : 'failed';
                $errMsg = $res['success'] ? null : $res['message'];

                $up = $this->db->prepare("UPDATE email_queue SET status = :status, error_message = :err, processed_at = NOW() WHERE queue_id = :id");
                $up->bindParam(':status', $newStatus);
                $up->bindParam(':err', $errMsg);
                $up->bindParam(':id', $item['queue_id']);
                $up->execute();
                $processed++;
            }
            return $processed;
        } catch (Exception $e) {
            error_log("Failed to process email queue: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($user_email, $user_name, $appointment)
    {
        $subject = "Appointment Confirmation - CSWDO Palayan City";

        $body = $this->getEmailTemplate(
            "Appointment Confirmed",
            $user_name,
            "
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #003366; margin-bottom: 15px;'>Appointment Details:</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Service:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$appointment['service_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Date:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . date('F d, Y', strtotime($appointment['appointment_date'])) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Time:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . date('h:i A', strtotime($appointment['appointment_time'])) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px;'><strong>Reference #:</strong></td>
                        <td style='padding: 10px;'><code style='background: #003366; color: white; padding: 5px 10px; border-radius: 5px;'>APT-" . str_pad($appointment['appointment_id'], 6, '0', STR_PAD_LEFT) . "</code></td>
                    </tr>
                </table>
            </div>
            
            <div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4CAF50;'>
                <h4 style='color: #4CAF50; margin-bottom: 10px;'><span style='font-size: 1.2rem;'>✓</span> What to Bring:</h4>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Valid ID</li>
                    <li>Required documents for your service</li>
                    <li>This confirmation email (printed or digital)</li>
                </ul>
            </div>
            
            <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                <h4 style='color: #856404; margin-bottom: 10px;'><span style='font-size: 1.2rem;'>⏰</span> Reminders:</h4>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Please arrive 10 minutes before your schedule</li>
                    <li>Bring physical copies of your documents</li>
                    <li>If you need to reschedule, please do so at least 24 hours in advance</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . APP_URL . "/view-appointment.php?id={$appointment['appointment_id']}' 
                   style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    View Appointment Details
                </a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $appointment['user_id']);
    }

    /**
     * Send appointment BOOKING RECEIVED email (initial submission waiting review)
     */
    public function sendAppointmentBookingReceived($user_email, $user_name, $appointment)
    {
        $tracking = !empty($appointment['reference_number'])
            ? $appointment['reference_number']
            : ('PAL-SOC-' . date('Y') . '-' . str_pad($appointment['appointment_id'], 6, '0', STR_PAD_LEFT));

        $subject = "Appointment Request Received [PENDING] – {$tracking}";
        $date_str = isset($appointment['appointment_date'])
            ? date('F d, Y', strtotime($appointment['appointment_date'])) : 'N/A';
        $time_str = isset($appointment['appointment_time'])
            ? date('h:i A', strtotime($appointment['appointment_time'])) : 'N/A';
        $service_name = $appointment['service_name'] ?? 'Social Service';

        $body = $this->getEmailTemplate(
            'Appointment Request Received',
            $user_name,
            "
            <div style='text-align:center;margin:20px 0;'>
                <div style='display:inline-block;background:#F59E0B;color:white;padding:10px 24px;border-radius:50px;font-size:1rem;font-weight:700;'>
                    ⏳ PENDING FOR REVIEW
                </div>
            </div>
            <p style='color:#333;line-height:1.6;'>
                Natanggap na po namin ang inyong appointment request sa <strong>One Palayan-Kalinga Hub Social Service Office</strong>. Ito po ay kasalukuyang sinusuri ng ating Social Service Staff.
            </p>
            <div style='background:#f9f9fb;padding:18px;border-radius:10px;margin:16px 0;border:1px solid #e5e7eb;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Reference No.</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;'><code style='background:#7F0303;color:white;padding:3px 10px;border-radius:5px;font-weight:700;'>{$tracking}</code></td></tr>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Requested Service</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;font-weight:600;'>{$service_name}</td></tr>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Preferred Date</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;'>{$date_str}</td></tr>
                    <tr><td style='padding:9px;color:#666;'>Preferred Time</td>
                        <td style='padding:9px;'>{$time_str}</td></tr>
                </table>
            </div>
            <div style='background:#eff6ff;padding:14px;border-radius:8px;border-left:4px solid #3b82f6;margin:16px 0;font-size:0.85rem;color:#1e40af;'>
                <strong>Paalala:</strong> Makakatanggap po kayo ng abiso kapag na-aprubahan o nakumpirma na ang inyong appointment. Kapag nakumpirma, maaari ninyong buksan o i-download ang inyong opisyal na <strong>Appointment Slip at QR Code</strong> sa inyong portal account.
            </div>
            <div style='text-align:center;margin:24px 0;'>
                <a href='" . APP_URL . "/my-appointments.php'
                   style='background:#7F0303;color:white;padding:12px 30px;text-decoration:none;border-radius:30px;font-weight:700;font-size:0.9rem;'>View in My Appointments</a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $appointment['user_id'] ?? null);
    }

    /**
     * Send appointment STATUS UPDATE email (when staff changes status)
     */
    public function sendAppointmentStatusUpdate($user_email, $user_name, $appointment, $new_status, $notes = '')
    {
        $status_labels = [
            'pending'           => ['label' => 'Pending Review',     'color' => '#F59E0B', 'icon' => '⏳'],
            'for_review'        => ['label' => 'Under Review',       'color' => '#3B82F6', 'icon' => '🔍'],
            'confirmed'         => ['label' => 'Confirmed',          'color' => '#10B981', 'icon' => '✅'],
            'rescheduled'       => ['label' => 'Rescheduled',        'color' => '#8B5CF6', 'icon' => '📅'],
            'checked_in'        => ['label' => 'Checked-In',         'color' => '#06B6D4', 'icon' => '🎫'],
            'in_progress'       => ['label' => 'In Progress',        'color' => '#F97316', 'icon' => '⚡'],
            'completed'         => ['label' => 'Completed',          'color' => '#059669', 'icon' => '🎉'],
            'cancelled'         => ['label' => 'Cancelled',          'color' => '#EF4444', 'icon' => '❌'],
            'rejected'          => ['label' => 'Request Rejected',   'color' => '#DC2626', 'icon' => '🚫'],
            'no_show'           => ['label' => 'No-Show',            'color' => '#6B7280', 'icon' => '⚠️'],
            'needs_information' => ['label' => 'Needs Information',  'color' => '#D97706', 'icon' => '📝'],
        ];
        $s = $status_labels[$new_status] ?? ['label' => ucfirst($new_status), 'color' => '#666', 'icon' => '📋'];

        $tracking = !empty($appointment['reference_number'])
            ? $appointment['reference_number']
            : ('PAL-SOC-' . date('Y') . '-' . str_pad($appointment['appointment_id'], 6, '0', STR_PAD_LEFT));

        $subject = "Appointment Update ({$s['label']}) – {$tracking}";

        $date_str = isset($appointment['appointment_date'])
            ? date('F d, Y', strtotime($appointment['appointment_date'])) : 'N/A';
        $time_str = isset($appointment['appointment_time'])
            ? date('h:i A', strtotime($appointment['appointment_time'])) : 'N/A';
        $service_name = $appointment['service_name'] ?? 'Social Service';

        $notes_html = $notes
            ? "<div style='background:#fff8e1;border-left:4px solid #FFC107;padding:14px 18px;border-radius:8px;margin:16px 0;'>
                 <strong style='color:#856404;'>Staff Note / Remarks:</strong><br>{$notes}
               </div>"
            : '';

        $slip_button = '';
        if ($new_status === 'confirmed') {
            $slip_button = "
            <div style='text-align:center;margin:15px 0 25px;'>
                <a href='" . APP_URL . "/appointment-slip.php?ref={$tracking}'
                   style='background:#10B981;color:white;padding:12px 28px;text-decoration:none;border-radius:30px;font-weight:700;font-size:0.9rem;display:inline-block;'>
                   🎫 View & Print Appointment Slip
                </a>
            </div>";
        }

        $body = $this->getEmailTemplate(
            'Appointment Status Update',
            $user_name,
            "
            <div style='text-align:center;margin:20px 0;'>
                <div style='display:inline-block;background:{$s['color']};color:white;padding:12px 28px;border-radius:50px;font-size:1.1rem;font-weight:700;'>
                    {$s['icon']} {$s['label']}
                </div>
            </div>
            <p style='color:#333;line-height:1.6;'>
                Ang status ng inyong appointment sa <strong>One Palayan-Kalinga Hub</strong> ay na-update:
            </p>
            <div style='background:#f9f9fb;padding:18px;border-radius:10px;margin:16px 0;border:1px solid #e5e7eb;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Reference No.</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;'><code style='background:#7F0303;color:white;padding:3px 10px;border-radius:5px;font-weight:700;'>{$tracking}</code></td></tr>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Service</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;font-weight:600;'>{$service_name}</td></tr>
                    <tr><td style='padding:9px;border-bottom:1px solid #eee;color:#666;'>Date</td>
                        <td style='padding:9px;border-bottom:1px solid #eee;'>{$date_str}</td></tr>
                    <tr><td style='padding:9px;color:#666;'>Time</td>
                        <td style='padding:9px;'>{$time_str}</td></tr>
                </table>
            </div>
            {$notes_html}
            {$slip_button}
            <div style='text-align:center;margin:24px 0;'>
                <a href='" . APP_URL . "/my-appointments.php'
                   style='background:#7F0303;color:white;padding:12px 30px;text-decoration:none;border-radius:30px;font-weight:700;font-size:0.9rem;'>View My Appointments</a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $appointment['user_id'] ?? null);
    }

    /**
     * Send AICS status update email
     */
    public function sendAICSUpdate($user_email, $user_name, $aics)
    {
        $status_colors = [
            'submitted' => '#ff9800',
            'reviewing' => '#2196F3',
            'approved' => '#4CAF50',
            'rejected' => '#f44336',
            'released' => '#9C27B0'
        ];

        $status_icons = [
            'submitted' => '📝',
            'reviewing' => '🔍',
            'approved' => '✅',
            'rejected' => '❌',
            'released' => '💰'
        ];

        $status = $aics['status'];
        $color = $status_colors[$status] ?? '#666';
        $icon = $status_icons[$status] ?? '📋';

        $extra_instruction = "";
        if ($status === 'reviewing') {
            $extra_instruction = "
            <div style='background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; border-radius: 8px; margin: 20px 0; color: #b78103;'>
                <strong>📢 INTERVIEW NOTICE:</strong> Please visit the CSWDO Office at Window 1, 2, or 3 for your interview and assessment. Make sure to bring your original supporting documents.
            </div>";
        } elseif ($status === 'approved') {
            $extra_instruction = "
            <div style='background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; border-radius: 8px; margin: 20px 0; color: #2e7d32;'>
                <strong>📢 BUDGET OFFICE FORWARDING:</strong> Your request has been approved and forwarded to the City Budget Office for voucher processing. Please wait for a text or call notification.
            </div>";
        }

        $tracking = "AICS-" . str_pad($aics['aics_id'], 6, '0', STR_PAD_LEFT);

        $subject = "AICS Request Update - {$tracking}";

        $body = $this->getEmailTemplate(
            "AICS Request Status Update",
            $user_name,
            "
            <div style='text-align: center; margin: 20px 0;'>
                <div style='display: inline-block; background: {$color}; color: white; padding: 15px 30px; border-radius: 50px; font-size: 1.2rem; font-weight: bold;'>
                    {$icon} Status: " . ucfirst($status) . "
                </div>
            </div>
            
            {$extra_instruction}
            
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #003366; margin-bottom: 15px;'>Request Details:</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Tracking Number:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><code style='background: #003366; color: white; padding: 5px 10px; border-radius: 5px;'>{$tracking}</code></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Type:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . ucfirst($aics['request_type']) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Amount Requested:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>₱" . number_format($aics['amount_requested'], 2) . "</td>
                    </tr>"
            . ($aics['amount_approved'] ? "
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Amount Approved:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong style='color: #4CAF50;'>₱" . number_format($aics['amount_approved'], 2) . "</strong></td>
                    </tr>" : "") .
            ($aics['rejection_reason'] ? "
                    <tr>
                        <td style='padding: 10px;'><strong>Reason:</strong></td>
                        <td style='padding: 10px; color: #f44336;'>{$aics['rejection_reason']}</td>
                    </tr>" : "") . "
                </table>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . APP_URL . "/track-aics.php?tracking={$tracking}' 
                   style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    Track Your Request
                </a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $aics['user_id']);
    }

    /**
     * Send AICS application submission confirmation email
     */
    public function sendAICSConfirmation($user_email, $user_name, $aics)
    {
        $tracking = "AICS-" . str_pad($aics['aics_id'], 6, '0', STR_PAD_LEFT);
        $subject = "AICS Application Received - {$tracking}";

        $body = $this->getEmailTemplate(
            "AICS Application Submitted",
            $user_name,
            "
            <p>Thank you for submitting your application for <strong>" . ucfirst($aics['request_type']) . " Assistance</strong> under the Assistance to Individuals in Crisis Situations (AICS) program.</p>
            
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #003366; margin-bottom: 15px;'>Application Details:</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Tracking Number:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><code style='background: #003366; color: white; padding: 5px 10px; border-radius: 5px;'>{$tracking}</code></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Type of Assistance:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . ucfirst($aics['request_type']) . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px;'><strong>Status:</strong></td>
                        <td style='padding: 10px;'>Submitted (Pending Review)</td>
                    </tr>
                </table>
            </div>
            
            <p>Our staff will review your submitted documents. You will receive updates about the status of your application via email.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . APP_URL . "/track-aics.php?tracking={$tracking}' 
                   style='background: #7F0303; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    Track Your Request
                </a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $aics['user_id'] ?? null);
    }

    /**
     * Send housing application update email
     */
    public function sendHousingUpdate($user_email, $user_name, $housing)
    {
        $app_year = !empty($housing['application_date']) ? date('Y', strtotime($housing['application_date'])) : date('Y');
        $tracking = "HSG-" . $app_year . "-" . str_pad($housing['beneficiary_id'], 5, '0', STR_PAD_LEFT);
        $status = $housing['status'] ?? 'pending';
        $is_insite = (($housing['housing_type'] ?? '') === 'in_site');
        $pathway_text = $is_insite ? 'In-Site Housing (Sariling Lupa)' : 'Relocation Housing';

        $status_badges = [
            'pending'   => ['bg' => '#FF9800', 'icon' => '⏳', 'text' => 'PENDING SCREENING'],
            'qualified' => ['bg' => '#2196F3', 'icon' => '🔍', 'text' => 'QUALIFIED & SCHEDULED'],
            'approved'  => ['bg' => '#4CAF50', 'icon' => '✅', 'text' => 'OFFICIALLY APPROVED'],
            'released'  => ['bg' => '#9C27B0', 'icon' => '🎉', 'text' => 'RELEASED & TURNED OVER'],
            'rejected'  => ['bg' => '#F44336', 'icon' => '❌', 'text' => 'INACTIVE / WITHDRAWN']
        ];
        $badge = $status_badges[$status] ?? ['bg' => '#607D8B', 'icon' => '📋', 'text' => strtoupper($status)];

        // Notice instruction
        $instruction_html = "";
        if ($status === 'qualified' && !empty($housing['interview_date'])) {
            $formatted_date = date('F d, Y', strtotime($housing['interview_date']));
            $notice_title = $is_insite ? 'SITE INSPECTION SCHEDULED' : 'FAMILY INTERVIEW SCHEDULED';
            $notice_body = $is_insite 
                ? "Our Housing Technical Assessment Team is scheduled to inspect your residential lot on <strong>{$formatted_date}</strong>. Please ensure the lot is accessible and have your land documents and valid IDs ready on-site."
                : "Your intake interview is confirmed for <strong>{$formatted_date} at 09:00 AM</strong> at Window 2 (Housing & Resettlement Division), CSWDO Palayan City Hall. Please present your official Claim Stub and original supporting documents.";
            $instruction_html = "
            <div style='background: #e3f2fd; border-left: 4px solid #2196F3; padding: 16px 18px; border-radius: 8px; margin: 20px 0; color: #0d47a1;'>
                <strong style='font-size: 14px;'>📅 {$notice_title}</strong>
                <p style='margin: 6px 0 0; font-size: 13px; line-height: 1.5;'>{$notice_body}</p>
            </div>";
        } elseif ($status === 'approved') {
            $unit_label = $is_insite ? 'Building Grant / Voucher Code' : 'Assigned Housing Unit';
            $instruction_html = "
            <div style='background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 16px 18px; border-radius: 8px; margin: 20px 0; color: #1b5e20;'>
                <strong style='font-size: 14px;'>✅ APPLICATION APPROVED</strong>
                <p style='margin: 6px 0 0; font-size: 13px; line-height: 1.5;'>Congratulations! Your housing assistance has been approved with {$unit_label}: <strong>" . htmlspecialchars($housing['unit_assigned'] ?? 'Official Grant Assignment') . "</strong>. You may now download your updated Claim Stub from the portal.</p>
            </div>";
        } elseif ($status === 'released') {
            $instruction_html = "
            <div style='background: #f3e5f5; border-left: 4px solid #9C27B0; padding: 16px 18px; border-radius: 8px; margin: 20px 0; color: #4a148c;'>
                <strong style='font-size: 14px;'>🎉 TURNOVER &amp; RELEASE COMPLETED</strong>
                <p style='margin: 6px 0 0; font-size: 13px; line-height: 1.5;'>Your housing assistance package has been successfully released and turned over. Thank you for partnering with the City Government of Palayan.</p>
            </div>";
        }

        $subject = "Housing Application Update [{$tracking}] - " . ucfirst($status);

        $portal_url = (defined('APP_URL') ? APP_URL : 'http://localhost/palayan_kalinga_hub') . "/view-housing.php?id=" . $housing['beneficiary_id'];

        $body = $this->getEmailTemplate(
            "Housing Assistance Application Update",
            $user_name,
            "
            <div style='text-align: center; margin: 15px 0 25px;'>
                <div style='display: inline-block; background: {$badge['bg']}; color: white; padding: 10px 24px; border-radius: 50px; font-size: 0.95rem; font-weight: 700; letter-spacing: 0.5px;'>
                    {$badge['icon']} {$badge['text']}
                </div>
            </div>
            
            {$instruction_html}
            
            <div style='background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                <h3 style='color: #0F414A; margin-top: 0; margin-bottom: 14px; font-size: 15px;'>Application Summary:</h3>
                <table style='width: 100%; border-collapse: collapse; font-size: 13px;'>
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b; width: 40%;'>Tracking Number:</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7;'><code style='background: #7F0303; color: white; padding: 3px 8px; border-radius: 4px; font-weight: 700;'>{$tracking}</code></td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>Assistance Pathway:</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; font-weight: 600; color: #1e293b;'>{$pathway_text}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>Program Name:</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; font-weight: 600; color: #1e293b;'>{$housing['program_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>Application Date:</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #1e293b;'>" . (!empty($housing['application_date']) ? date('F d, Y', strtotime($housing['application_date'])) : 'N/A') . "</td>
                    </tr>"
            . (!empty($housing['unit_assigned']) ? "
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>" . ($is_insite ? 'Grant / Voucher Ref:' : 'Unit Assigned:') . "</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7;'><strong style='color: #2e7d32;'>{$housing['unit_assigned']}</strong></td>
                    </tr>" : "")
            . (!empty($housing['interview_date']) ? "
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>" . ($is_insite ? 'Lot Inspection Date:' : 'Interview Schedule:') . "</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7;'><strong style='color: #0284c7;'>" . date('F d, Y', strtotime($housing['interview_date'])) . " (09:00 AM)</strong></td>
                    </tr>" : "")
            . (!empty($housing['move_in_date']) ? "
                    <tr>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #64748b;'>" . ($is_insite ? 'Grant Release Date:' : 'Turnover / Move-in Date:') . "</td>
                        <td style='padding: 8px 0; border-bottom: 1px solid #edf2f7;'><strong style='color: #9C27B0;'>" . date('F d, Y', strtotime($housing['move_in_date'])) . "</strong></td>
                    </tr>" : "") . "
                </table>
            </div>

            <div style='text-align: center; margin: 30px 0 15px;'>
                <a href='{$portal_url}' 
                   style='background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%); color: white; padding: 12px 28px; text-decoration: none; border-radius: 30px; font-weight: 700; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(127,3,3,0.25);'>
                    View Application &amp; Claim Stub
                </a>
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $housing['user_id']);
    }

    /**
     * Send residency verification approved email
     */
    public function sendVerificationApprovedEmail($user_email, $user_name, $user_id = null)
    {
        $base_app_url = rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/palayan_kalinga_hub', '/');
        $login_url = $base_app_url . '/login.php';

        $subject = "Your Account Has Been Verified - One Palayan - Kalinga Hub";

        $body = $this->getEmailTemplate(
            "Your Account Has Been Verified",
            $user_name,
            "
            <div style='text-align: center; margin: 15px 0 25px;'>
                <div style='display: inline-block; background: #E8F5E9; color: #2E7D32; padding: 10px 24px; border-radius: 50px; font-size: 1rem; font-weight: 700; border: 1px solid #A5D6A7;'>
                    &#10004; ACCOUNT VERIFIED
                </div>
            </div>
            
            <div style='background: #F8FBF8; border-left: 4px solid #2E7D32; padding: 18px 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #1B5E20; margin: 0 0 10px; font-size: 1.15rem;'>Your Account Has Been Verified</h3>
                <p style='color: #2E384D; line-height: 1.6; margin: 0 0 8px; font-size: 0.95rem;'>
                    Your registration for <strong>One Palayan - Kalinga Hub</strong> has been successfully verified.
                </p>
                <p style='color: #2E384D; line-height: 1.6; margin: 0; font-size: 0.95rem;'>
                    You can now log in and access the available services.
                </p>
            </div>
            
            <p style='color: #4A5568; line-height: 1.6; font-size: 0.9rem;'>
                As a verified resident of Palayan City, you now have full access to our digital public welfare services:
            </p>
            <ul style='color: #4A5568; line-height: 1.7; font-size: 0.9rem; padding-left: 22px; margin: 10px 0 25px;'>
                <li><strong>Assistance to Individuals in Crisis Situations (AICS):</strong> Apply for medical, educational, burial, or food assistance online.</li>
                <li><strong>Appointment &amp; Queue Management:</strong> Schedule visits and generate digital QR appointment slips.</li>
                <li><strong>City Housing Programs:</strong> Apply for housing assistance and track your application status.</li>
                <li><strong>Senior Citizen &amp; PWD Services:</strong> Access specialized city services and welfare benefits.</li>
            </ul>
            
            <div style='text-align: center; margin: 30px 0 20px;'>
                <a href='{$login_url}' 
                   style='display: inline-block; background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%); color: #FFFFFF; padding: 14px 36px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 1rem; box-shadow: 0 4px 12px rgba(127,3,3,0.25); letter-spacing: 0.3px;'>
                    Login to Your Account
                </a>
            </div>
            <p style='text-align: center; color: #718096; font-size: 0.8rem; margin: 12px 0 0;'>
                Direct link: <a href='{$login_url}' style='color: #7F0303; word-break: break-all;'>{$login_url}</a>
            </p>
            "
        );

        return $this->send($user_email, $subject, $body, $user_id);
    }

    /**
     * Send residency verification rejected email
     */
    public function sendVerificationRejectedEmail($user_email, $user_name, $rejection_reason, $user_id = null)
    {
        $base_app_url = rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/palayan_kalinga_hub', '/');
        $update_url = $base_app_url . '/verify-resident.php';

        $subject = "Verification Status: Rejected - One Palayan - Kalinga Hub";
        $reason_text = !empty(trim($rejection_reason ?? '')) ? htmlspecialchars(trim($rejection_reason)) : 'Submitted documents did not meet verification criteria or could not be validated.';

        $body = $this->getEmailTemplate(
            "Account Verification Status",
            $user_name,
            "
            <div style='text-align: center; margin: 15px 0 25px;'>
                <div style='display: inline-block; background: #FFEBEE; color: #C62828; padding: 10px 24px; border-radius: 50px; font-size: 1rem; font-weight: 700; border: 1px solid #FFCDD2;'>
                    &#10006; VERIFICATION STATUS: REJECTED
                </div>
            </div>
            
            <div style='background: #FFF5F5; border-left: 4px solid #C62828; padding: 18px 20px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0 0 10px; font-size: 1rem; color: #742A2A;'>
                    <strong>Verification status:</strong> <span style='color: #C62828; font-weight: 700;'>Rejected</span>
                </p>
                <div style='background: #FFFFFF; border: 1px solid #FED7D7; padding: 14px 16px; border-radius: 6px; margin: 10px 0;'>
                    <strong style='color: #9B2C2C; font-size: 0.9rem; display: block; margin-bottom: 6px;'>Reason for rejection:</strong>
                    <p style='margin: 0; color: #2D3748; font-size: 0.95rem; line-height: 1.5; white-space: pre-line;'>{$reason_text}</p>
                </div>
            </div>
            
            <div style='background: #F7FAFC; border-left: 4px solid #4A5568; padding: 16px 20px; border-radius: 8px; margin: 20px 0;'>
                <h4 style='color: #2D3748; margin: 0 0 8px; font-size: 0.95rem;'>Instructions on what you need to update:</h4>
                <p style='color: #4A5568; line-height: 1.6; margin: 0 0 8px; font-size: 0.9rem;'>
                    To complete your verification and access city services, please return to the portal and update your submission:
                </p>
                <ul style='color: #4A5568; line-height: 1.6; margin: 0; padding-left: 20px; font-size: 0.9rem;'>
                    <li>Ensure your uploaded or captured <strong>Valid Government-issued ID</strong> is clear, unexpired, and all information is readable.</li>
                    <li>Ensure your <strong>Proof of Residency / Barangay Certificate</strong> clearly shows your name and current residence in Palayan City.</li>
                    <li>Verify that the personal details on your documents match your registered account information.</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin: 30px 0 20px;'>
                <a href='{$update_url}' 
                   style='display: inline-block; background: linear-gradient(135deg, #7F0303 0%, #9A1515 100%); color: #FFFFFF; padding: 14px 36px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 1rem; box-shadow: 0 4px 12px rgba(127,3,3,0.25); letter-spacing: 0.3px;'>
                    Update Your Information
                </a>
            </div>
            <p style='text-align: center; color: #718096; font-size: 0.8rem; margin: 12px 0 0;'>
                Direct link: <a href='{$update_url}' style='color: #7F0303; word-break: break-all;'>{$update_url}</a>
            </p>
            "
        );

        return $this->send($user_email, $subject, $body, $user_id);
    }

    /**
     * Send document verification email
     */
    public function sendDocumentVerification($user_email, $user_name, $document)
    {
        $subject = "Document Verification Update";

        $body = $this->getEmailTemplate(
            "Document Verification Status",
            $user_name,
            "
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Document:</strong> " . ucfirst(str_replace('_', ' ', $document['document_type'])) . "</p>
                <p><strong>Status:</strong> 
                    <span style='background: " . ($document['verification_status'] == 'verified' ? '#4CAF50' : '#f44336') . "; color: white; padding: 5px 10px; border-radius: 5px;'>
                        " . ucfirst($document['verification_status']) . "
                    </span>
                </p>
                " . ($document['remarks'] ? "<p><strong>Remarks:</strong> {$document['remarks']}</p>" : "") . "
            </div>
            "
        );

        return $this->send($user_email, $subject, $body, $document['user_id']);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset($user_email, $user_name, $reset_link)
    {
        $subject = "Password Reset Request";

        $body = $this->getEmailTemplate(
            "Password Reset",
            $user_name,
            "
            <p>We received a request to reset your password. Click the button below to choose a new password:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$reset_link}' 
                   style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    Reset Password
                </a>
            </div>
            
            <p style='color: #666; font-size: 0.9rem;'>If you didn't request this, please ignore this email or contact support.</p>
            <p style='color: #666; font-size: 0.9rem;'>This link will expire in 1 hour.</p>
            "
        );

        return $this->send($user_email, $subject, $body);
    }

    /**
     * Send 6-digit password reset verification code email
     */
    public function sendPasswordResetCode($user_email, $user_name, $code)
    {
        $subject = "Your Password Reset Code: {$code} - One Palayan-Kalinga Hub";

        $content = "
            <p style='font-size: 1rem; color: #1e293b; margin-bottom: 18px;'>
                Nakatanggap kami ng kahilingan upang i-reset ang password para sa iyong <strong>One Palayan-Kalinga Hub</strong> account.
            </p>
            
            <div style='text-align: center; margin: 28px 0; padding: 24px 20px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 14px;'>
                <span style='display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;'>Iyong 6-Digit Verification Code</span>
                <span style='font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #7F0303; font-family: monospace; display: inline-block; padding: 10px 24px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>{$code}</span>
                <p style='font-size: 0.8rem; color: #94a3b8; margin: 12px 0 0;'>Mag-e-expire ang verification code na ito sa loob ng <strong>15 minuto</strong>.</p>
            </div>
            
            <p style='color: #475569; font-size: 0.88rem; line-height: 1.6;'>
                I-type ang 6-digit code na ito sa screen ng <strong>Forgot Password</strong> upang i-verify ang iyong pagkakakilanlan at makapaglagay ng bagong password.
            </p>
            
            <div style='margin-top: 24px; padding: 12px 16px; background: #fff1f2; border-left: 4px solid #be123c; border-radius: 6px; font-size: 0.82rem; color: #9f1239; line-height: 1.5;'>
                <strong>Paalala sa Seguridad:</strong> Huwag ibahagi ang verification code na ito kahit kanino, maging sa sinumang kawani ng pamahalaan. Kung hindi ikaw ang humiling ng pag-reset ng password, mangyaring balewalain ang mensaheng ito upang manatiling ligtas ang iyong account.
            </div>
        ";

        $body = $this->getEmailTemplate(
            "Password Reset Code",
            $user_name,
            $content
        );

        return $this->send($user_email, $subject, $body);
    }

    /**
     * Send password change security alert email
     */
    public function sendPasswordChangeAlert($user_email, $user_name, $ip_address, $timestamp = null)
    {
        $timestamp = $timestamp ?: date('F d, Y h:i A');
        $subject = "Security Alert: Password Changed - One Palayan-Kalinga Hub";

        $content = "
        <div style='background:#FFF3CD;border:1px solid #FFEEBA;color:#856404;padding:16px 20px;border-radius:10px;margin-bottom:20px;'>
            <strong style='font-size:15px;display:block;margin-bottom:4px;'>⚠️ Security Notification</strong>
            <p style='margin:0;font-size:13px;line-height:1.4;'>The password for your One Palayan-Kalinga Hub account was successfully updated.</p>
        </div>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;width:35%;'>Date &amp; Time:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$timestamp}</td>
            </tr>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;'>IP Address:</td>
                <td style='padding:10px 0;font-family:monospace;font-weight:600;color:#263238;'>{$ip_address}</td>
            </tr>
            <tr>
                <td style='padding:10px 0;color:#6B7A8A;'>Device / Browser:</td>
                <td style='padding:10px 0;color:#263238;font-size:13px;'>" . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser') . "</td>
            </tr>
        </table>
        <p style='color:#3A4A5A;font-size:13px;line-height:1.5;margin-top:16px;'>
            If you made this change, you can safely disregard this message. Your account is active and protected with your new password.
        </p>
        <div style='background:#F8D7DA;border:1px solid #F5C6CB;color:#721C24;padding:14px 18px;border-radius:10px;margin-top:20px;font-size:13px;'>
            <strong>Didn't make this change?</strong><br>
            If you did not authorize this password update, someone may have unauthorized access to your account. Please reset your password immediately or contact the CSWDO IT Helpdesk to secure your citizen record.
        </div>
        ";

        $body = $this->getEmailTemplate("Password Changed", $user_name, $content);
        return $this->send($user_email, $subject, $body);
    }

    /**
     * Send email change alert to the PREVIOUS / OLD email address
     */
    public function sendEmailChangeAlertToOldEmail($old_email, $new_email, $user_name, $ip_address, $timestamp = null)
    {
        $timestamp = $timestamp ?: date('F d, Y h:i A');
        $subject = "Security Alert: Email Address Changed - One Palayan-Kalinga Hub";

        // Partially mask new email for privacy e.g., j***e@domain.com
        $parts = explode('@', $new_email);
        $masked_new = (strlen($parts[0]) > 2)
            ? substr($parts[0], 0, 1) . str_repeat('*', max(1, strlen($parts[0]) - 2)) . substr($parts[0], -1) . '@' . ($parts[1] ?? '')
            : $new_email;

        $content = "
        <div style='background:#FFF3CD;border:1px solid #FFEEBA;color:#856404;padding:16px 20px;border-radius:10px;margin-bottom:20px;'>
            <strong style='font-size:15px;display:block;margin-bottom:4px;'>⚠️ Security Alert: Primary Email Modified</strong>
            <p style='margin:0;font-size:13px;line-height:1.4;'>The registered email address for your One Palayan-Kalinga Hub account has been changed.</p>
        </div>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;width:35%;'>Previous Email:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$old_email}</td>
            </tr>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;'>New Email:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$masked_new}</td>
            </tr>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;'>Date &amp; Time:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$timestamp}</td>
            </tr>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;'>IP Address:</td>
                <td style='padding:10px 0;font-family:monospace;font-weight:600;color:#263238;'>{$ip_address}</td>
            </tr>
            <tr>
                <td style='padding:10px 0;color:#6B7A8A;'>Device / Browser:</td>
                <td style='padding:10px 0;color:#263238;font-size:13px;'>" . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Web Browser') . "</td>
            </tr>
        </table>
        <p style='color:#3A4A5A;font-size:13px;line-height:1.5;margin-top:16px;'>
            From now on, all official system notices, appointment confirmations, and password reset requests will be sent to your new email address.
        </p>
        <div style='background:#F8D7DA;border:1px solid #F5C6CB;color:#721C24;padding:14px 18px;border-radius:10px;margin-top:20px;font-size:13px;'>
            <strong>Didn't make this change?</strong><br>
            If you did not initiate this change, someone may have compromised your credentials. Please contact the City Social Welfare and Development Office (CSWDO) IT Helpdesk immediately to lock and recover your account.
        </div>
        ";

        $body = $this->getEmailTemplate("Email Address Changed", $user_name, $content);
        return $this->send($old_email, $subject, $body);
    }

    /**
     * Send email confirmation alert to the NEW email address
     */
    public function sendEmailChangeConfirmationToNewEmail($new_email, $user_name, $ip_address, $timestamp = null)
    {
        $timestamp = $timestamp ?: date('F d, Y h:i A');
        $subject = "Primary Email Confirmed - One Palayan-Kalinga Hub";

        $content = "
        <div style='background:#D1FAE5;border:1px solid #A7F3D0;color:#065F46;padding:16px 20px;border-radius:10px;margin-bottom:20px;'>
            <strong style='font-size:15px;display:block;margin-bottom:4px;'>✓ Primary Email Successfully Updated</strong>
            <p style='margin:0;font-size:13px;line-height:1.4;'>This email address is now linked as your primary contact on One Palayan-Kalinga Hub.</p>
        </div>
        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;width:35%;'>Active Email:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$new_email}</td>
            </tr>
            <tr style='border-bottom:1px solid #EEF2F6;'>
                <td style='padding:10px 0;color:#6B7A8A;'>Date &amp; Time:</td>
                <td style='padding:10px 0;font-weight:600;color:#263238;'>{$timestamp}</td>
            </tr>
            <tr>
                <td style='padding:10px 0;color:#6B7A8A;'>IP Address:</td>
                <td style='padding:10px 0;font-family:monospace;font-weight:600;color:#263238;'>{$ip_address}</td>
            </tr>
        </table>
        <p style='color:#3A4A5A;font-size:13px;line-height:1.5;margin-top:16px;'>
            You will now receive all social welfare notifications, appointment booking updates, AICS assistance tracking, and security alerts at this address.
        </p>
        <div style='text-align:center;margin:24px 0;'>
            <a href='" . APP_URL . "/settings.php' 
               style='background:#7F0303;color:white;padding:12px 28px;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
                Access Account Settings
            </a>
        </div>
        ";

        $body = $this->getEmailTemplate("Primary Email Updated", $user_name, $content);
        return $this->send($new_email, $subject, $body);
    }

    /**
     * Get email template with header and footer
     */
    private function getEmailTemplate($title, $user_name, $content)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#F0F4F8;'>
            <div style='max-width:600px;margin:24px auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.1);'>

                <!-- Header -->
                <div style='background:linear-gradient(135deg, #0F414A 0%, #7F0303 100%);padding:32px 28px;text-align:center;'>
                    <h1 style='color:white;margin:0 0 4px;font-size:22px;font-weight:800;letter-spacing:-0.5px;'>ONE PALAYAN-KALINGA HUB</h1>
                    <p style='color:rgba(255,255,255,0.75);margin:0;font-size:13px;letter-spacing:1px;'>CSWDO &bull; PALAYAN CITY</p>
                </div>

                <!-- Title Bar -->
                <div style='background:#7F0303;padding:14px 28px;'>
                    <h2 style='color:white;margin:0;font-size:16px;font-weight:700;'>{$title}</h2>
                </div>

                <!-- Content -->
                <div style='padding:28px 28px 20px;'>
                    <p style='margin:0 0 18px;color:#3A4A5A;'>Dear <strong style='color:#263238;'>{$user_name}</strong>,</p>
                    {$content}
                    <hr style='border:none;border-top:1px solid #E8ECF2;margin:24px 0;'>
                    <p style='color:#9aabb8;font-size:12px;margin:0;'>This is an automated message. Please do not reply directly to this email.</p>
                    <div style='margin-top:16px;'>
                        <p style='margin:0;font-weight:700;color:#263238;font-size:13px;'>City Social Welfare and Development Office</p>
                        <p style='margin:3px 0 0;color:#6B7A8A;font-size:12px;'>Palayan City, Nueva Ecija</p>
                        <p style='margin:3px 0 0;color:#6B7A8A;font-size:12px;'>&#9990; (044) 123-4567</p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background:#F8F9FC;padding:16px 28px;text-align:center;border-top:1px solid #E8ECF2;'>
                    <p style='color:#aab4be;margin:0;font-size:11px;'>
                        &copy; " . date('Y') . " CSWDO Palayan City. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Log email to database
     */
    private function logEmail($recipient, $subject, $user_id, $status, $error = null)
    {
        try {
            $query = "INSERT INTO email_logs (recipient, subject, user_id, status, error_message, created_at) 
                      VALUES (:recipient, :subject, :user_id, :status, :error, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':recipient', $recipient);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':error', $error);
            $stmt->execute();
        } catch (Exception $e) {
            // Just log error, don't stop execution
            error_log("Failed to log email: " . $e->getMessage());
        }
    }

    /**
     * Send bulk email (for staff use)
     */
    public function sendBulk($recipients, $subject, $body)
    {
        $success_count = 0;
        $fail_count = 0;

        foreach ($recipients as $recipient) {
            $result = $this->send($recipient['email'], $subject, $body, $recipient['user_id']);
            if ($result['success']) {
                $success_count++;
            } else {
                $fail_count++;
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }

        return [
            'success' => true,
            'success_count' => $success_count,
            'fail_count' => $fail_count,
            'total' => count($recipients)
        ];
    }
}
?>
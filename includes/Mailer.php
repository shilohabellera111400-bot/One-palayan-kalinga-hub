<?php
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $db;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host       = MAIL_HOST;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = MAIL_USERNAME;
        $this->mail->Password   = MAIL_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = MAIL_PORT;
        
        // Default settings
        $this->mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $this->mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
        
        // Database connection for logging
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Send email and log to database
     */
    public function send($to, $subject, $body, $user_id = null) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            
            // Add recipient
            $this->mail->addAddress($to);
            
            // Email content
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
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
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmation($user_email, $user_name, $appointment) {
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
                <a href='http://localhost/palayan_kalinga_hub/view-appointment.php?id={$appointment['appointment_id']}' 
                   style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    View Appointment Details
                </a>
            </div>
            "
        );
        
        return $this->send($user_email, $subject, $body, $appointment['user_id']);
    }
    
    /**
     * Send AICS status update email
     */
    public function sendAICSUpdate($user_email, $user_name, $aics) {
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
                <a href='http://localhost/palayan_kalinga_hub/track-aics.php?tracking={$tracking}' 
                   style='background: #003366; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                    Track Your Request
                </a>
            </div>
            "
        );
        
        return $this->send($user_email, $subject, $body, $aics['user_id']);
    }
    
    /**
     * Send housing application update email
     */
    public function sendHousingUpdate($user_email, $user_name, $housing) {
        $tracking = "HSG-" . str_pad($housing['beneficiary_id'], 6, '0', STR_PAD_LEFT);
        
        $subject = "Housing Application Update - {$tracking}";
        
        $body = $this->getEmailTemplate(
            "Housing Application Status Update",
            $user_name,
            "
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #003366; margin-bottom: 15px;'>Application Details:</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Tracking Number:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><code style='background: #003366; color: white; padding: 5px 10px; border-radius: 5px;'>{$tracking}</code></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Program:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$housing['program_name']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Status:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'><span style='background: " . ($housing['status'] == 'approved' ? '#4CAF50' : '#ff9800') . "; color: white; padding: 5px 10px; border-radius: 5px;'>" . ucfirst($housing['status']) . "</span></td>
                    </tr>"
                    . ($housing['unit_assigned'] ? "
                    <tr>
                        <td style='padding: 10px;'><strong>Unit Assigned:</strong></td>
                        <td style='padding: 10px;'><strong style='color: #4CAF50;'>{$housing['unit_assigned']}</strong></td>
                    </tr>" : "") . "
                </table>
            </div>
            "
        );
        
        return $this->send($user_email, $subject, $body, $housing['user_id']);
    }
    
    /**
     * Send document verification email
     */
    public function sendDocumentVerification($user_email, $user_name, $document) {
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
    public function sendPasswordReset($user_email, $user_name, $reset_link) {
        $subject = "Password Reset Request";
        
        $body = $this->getEmailTemplate(
            "Password Reset",
            $user_name,
            "
            <p>We received a request to reset your password. Click the button below to proceed:</p>
            
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
     * Get email template with header and footer
     */
    private function getEmailTemplate($title, $user_name, $content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f4f4f4;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #003366 0%, #004d99 100%); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>ONE PALAYAN-KALINGA HUB</h1>
                    <p style='color: rgba(255,255,255,0.8); margin: 5px 0 0;'>CSWDO Palayan City</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 30px;'>
                    <h2 style='color: #003366; margin-top: 0;'>{$title}</h2>
                    <p>Dear <strong>{$user_name}</strong>,</p>
                    
                    {$content}
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    
                    <p style='color: #666; font-size: 0.9rem;'>This is an automated message from the CSWDO Palayan City system. Please do not reply to this email.</p>
                    
                    <div style='margin-top: 20px;'>
                        <p style='margin: 0;'><strong>City Social Welfare and Development Office</strong></p>
                        <p style='margin: 5px 0 0; color: #666;'>Palayan City, Nueva Ecija</p>
                        <p style='margin: 5px 0 0; color: #666;'><span style='color: #ff6600;'>☎</span> (044) 123-4567</p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style='background: #f9f9f9; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                    <p style='color: #999; margin: 0; font-size: 0.8rem;'>
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
    private function logEmail($recipient, $subject, $user_id, $status, $error = null) {
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
    public function sendBulk($recipients, $subject, $body) {
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
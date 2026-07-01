<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

// Only allow in development or for admins
if (!isAdmin() && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    die('Access denied');
}

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $test_email = sanitize($_POST['test_email']);
    $test_type = sanitize($_POST['test_type']);
    
    $mailer = new Mailer();
    
    // Get a sample user if needed
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($test_type) {
        case 'appointment':
            // Get a sample appointment
            $query = "SELECT a.*, s.service_name, u.email, u.first_name, u.last_name 
                      FROM appointments a
                      JOIN services s ON a.service_id = s.service_id
                      JOIN users u ON a.user_id = u.user_id
                      ORDER BY a.appointment_id DESC LIMIT 1";
            $stmt = $db->query($query);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                $result = $mailer->sendAppointmentConfirmation(
                    $test_email,
                    $appointment['first_name'] . ' ' . $appointment['last_name'],
                    $appointment
                );
            } else {
                $error = "No sample appointment found";
            }
            break;
            
        case 'aics':
            // Get a sample AICS request
            $query = "SELECT a.*, u.email, u.first_name, u.last_name 
                      FROM aics_requests a
                      JOIN users u ON a.user_id = u.user_id
                      ORDER BY a.aics_id DESC LIMIT 1";
            $stmt = $db->query($query);
            $aics = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($aics) {
                $result = $mailer->sendAICSUpdate(
                    $test_email,
                    $aics['first_name'] . ' ' . $aics['last_name'],
                    $aics
                );
            } else {
                $error = "No sample AICS request found";
            }
            break;
            
        case 'test':
            // Send simple test email
            $result = $mailer->send(
                $test_email,
                'Test Email from CSWDO System',
                '<h1>Test Email</h1><p>If you received this, email configuration is working!</p>',
                null
            );
            break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Test</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div style="max-width: 600px; margin: 50px auto; padding: 20px;">
        <h1>Email Configuration Test</h1>
        
        <?php if ($result): ?>
            <div class="alert alert-<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <?php echo $result['message']; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" style="background: white; padding: 20px; border-radius: 5px;">
            <div class="form-group">
                <label>Test Email Address:</label>
                <input type="email" name="test_email" required 
                       value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Test Type:</label>
                <select name="test_type">
                    <option value="test">Simple Test</option>
                    <option value="appointment">Appointment Confirmation</option>
                    <option value="aics">AICS Status Update</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary">Send Test Email</button>
        </form>
        
        <div style="margin-top: 20px;">
            <h3>Email Logs</h3>
            <?php
            $database = new Database();
            $db = $database->getConnection();
            $logs = $db->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10");
            ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log = $logs->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                        <td><?php echo htmlspecialchars($log['subject']); ?></td>
                        <td>
                            <span style="color: <?php echo $log['status'] == 'sent' ? '#4CAF50' : '#f44336'; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
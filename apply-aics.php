<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_type = sanitize($_POST['request_type']);
    $amount_requested = sanitize($_POST['amount_requested']);
    $purpose = sanitize($_POST['purpose']);
    
    // Validate inputs
    if (empty($request_type) || empty($amount_requested) || empty($purpose)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($amount_requested) || $amount_requested < 100) {
        $error = "Please enter a valid amount (minimum ₱100).";
    } else {
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Insert AICS request
            $query = "INSERT INTO aics_requests (user_id, request_type, amount_requested, purpose, status) 
 VALUES (:user_id, :request_type, :amount, :purpose, 'submitted')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':request_type', $request_type);
            $stmt->bindParam(':amount', $amount_requested);
            $stmt->bindParam(':purpose', $purpose);
            $stmt->execute();
            
            $aics_id = $db->lastInsertId();
            
            // Handle document uploads
            if (!empty($_FILES['documents']['name'][0])) {
                $uploader = new FileUploader();
                $files = $_FILES['documents'];
                
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        
                        $file_path = $uploader->upload($file, 'aics');
                        
                        // Save document reference
                        $doc_query = "INSERT INTO aics_documents (aics_id, document_type, document_path) 
                                      VALUES (:aics_id, 'supporting_document', :path)";
                        $doc_stmt = $db->prepare($doc_query);
                        $doc_stmt->bindParam(':aics_id', $aics_id);
                        $doc_stmt->bindParam(':path', $file_path);
                        $doc_stmt->execute();
                    }
                }
            }
            
            // Create notification
            $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
            $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                            VALUES (:user_id, 'AICS Application Submitted', 
                            'Your AICS application has been submitted. Tracking number: " . $tracking . "', 'aics')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $notif_stmt->execute();
            
            $db->commit();
            
            $tracking = "AICS-" . str_pad($aics_id, 6, '0', STR_PAD_LEFT);
            $success = "AICS application submitted successfully! Your tracking number is: <strong>" . $tracking . "</strong>";
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Application failed: " . $e->getMessage();
        }
    }
}

// Get user's recent AICS requests for display
$recent_query = "SELECT * FROM aics_requests 
                 WHERE user_id = :user_id 
                 ORDER BY submission_date DESC 
                 LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bindParam(':user_id', $_SESSION['user_id']);
$recent_stmt->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for AICS - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></h3>
                <p>Resident</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="book-appointment.php">Book Appointment</a></li>
                <li><a href="my-appointments.php">My Appointments</a></li>
                <li><a href="apply-aics.php" class="active">Apply for AICS</a></li>
                <li><a href="my-aics.php">My AICS Requests</a></li>
                <li><a href="apply-housing.php">Apply for Housing</a></li>
                <li><a href="my-documents.php">My Documents</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Apply for AICS</h1>
            <p class="subtitle">Assistance to Individuals in Crisis Situation</p>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="application-form">
                <form method="POST" enctype="multipart/form-data" id="aicsForm" onsubmit="return validateForm()">
                    <div class="form-section">
                        <h3>Request Details</h3>
                        
                        <div class="form-group">
                            <label for="request_type">Type of Assistance *</label>
                            <select id="request_type" name="request_type" required>
                                <option value="">-- Select type --</option>
                                <option value="medical">Medical Assistance</option>
                                <option value="financial">Financial Assistance</option>
                                <option value="burial">Burial Assistance</option>
                                <option value="food">Food Assistance</option>
                                <option value="transportation">Transportation Assistance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount_requested">Amount Requested (₱) *</label>
                            <input type="number" id="amount_requested" name="amount_requested" 
                                   min="100" max="100000" step="100" 
                                   placeholder="Enter amount in PHP" required>
                            <small>Minimum: ₱100, Maximum: ₱100,000</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose / Explanation *</label>
                            <textarea id="purpose" name="purpose" rows="5" 
                                      placeholder="Please explain your situation and why you need assistance..." 
                                      required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Supporting Documents</h3>
                        <p class="hint">Upload supporting documents (Medical certificate, hospital bills, etc.)</p>
                        
                        <div class="form-group">
                            <label for="documents">Select Files</label>
                            <input type="file" id="documents" name="documents[]" multiple 
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileList()">
                            <small>You can select multiple files. Max 5MB each. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
                        </div>
                        
                        <div id="file-list" class="file-list"></div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Submit Application</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Requirements Checklist -->
            <div class="info-box">
                <h4>📋 Requirements Checklist</h4>
                <ul>
                    <li>Valid ID of applicant (any government-issued ID)</li>
                    <li>Barangay Certificate / Certificate of Indigency</li>
                    <li>Medical certificate / Hospital bill (for medical assistance)</li>
                    <li>Funeral contract / Death certificate (for burial assistance)</li>
                    <li>Prescription / Doctor's referral (for medical assistance)</li>
                    <li>Other supporting documents relevant to your situation</li>
                </ul>
            </div>
            
            <!-- Recent Applications -->
            <?php if($recent_stmt->rowCount() > 0): ?>
            <div class="recent-section">
                <h3>Your Recent Applications</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($request = $recent_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td>AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo ucfirst($request['request_type']); ?></td>
                            <td>₱<?php echo number_format($request['amount_requested'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="track-aics.php?tracking=AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?>" 
                                   class="btn-small">Track</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function updateFileList() {
        const files = document.getElementById('documents').files;
        const fileList = document.getElementById('file-list');
        
        if (files.length > 0) {
            let html = '<h4>Selected Files:</h4><ul>';
            for (let i = 0; i < files.length; i++) {
                html += `<li>${files[i].name} (${(files[i].size / 1024).toFixed(2)} KB)</li>`;
            }
            html += '</ul>';
            fileList.innerHTML = html;
        } else {
            fileList.innerHTML = '';
        }
    }
    
    function validateForm() {
        const requestType = document.getElementById('request_type').value;
        const amount = document.getElementById('amount_requested').value;
        const purpose = document.getElementById('purpose').value;
        
        if (!requestType || !amount || !purpose) {
            alert('Please fill in all required fields.');
            return false;
        }
        
        if (amount < 100) {
            alert('Amount must be at least ₱100.');
            return false;
        }
        
        return confirm('Are you sure you want to submit this application?');
    }
    </script>
    
    <style>
    .application-form {
        background: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .form-section h3 {
        color: #003366;
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #003366;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 0.8rem;
    }
    
    .file-list {
        margin-top: 10px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 5px;
    }
    
    .file-list ul {
        margin: 10px 0 0 20px;
        color: #666;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    
    .info-box {
        background: #e3f2fd;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        border-left: 4px solid #003366;
    }
    
    .info-box h4 {
        color: #003366;
        margin-bottom: 15px;
    }
    
    .info-box ul {
        margin-left: 20px;
        color: #333;
    }
    
    .info-box li {
        margin-bottom: 8px;
    }
    
    .hint {
        color: #666;
        font-style: italic;
        margin-bottom: 15px;
    }
    
    .recent-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .recent-section h3 {
        color: #333;
        margin-bottom: 20px;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .alert-error {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }
    
    .alert-success {
        background: #e8f5e8;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }
    
    @media (max-width: 768px) {
        .form-actions {
            flex-direction: column;
        }
        
        .form-actions button,
        .form-actions a {
            width: 100%;
            text-align: center;
        }
    }
    </style>
    
    <script src="js/main.js"></script>
</body>
</html>
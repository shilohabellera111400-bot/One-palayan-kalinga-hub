<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$error = '';
$success = '';

// Verify appointment belongs to user
if ($appointment_id) {
    $check_query = "SELECT * FROM appointments WHERE appointment_id = :id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $appointment_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        redirect('dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    $uploader = new FileUploader();
    $document_type = sanitize($_POST['document_type']);
    
    try {
        $file_path = $uploader->upload($_FILES['document'], 'documents');
        
        // Save to database
        $query = "INSERT INTO documents (user_id, appointment_id, document_type, document_path) 
                  VALUES (:user_id, :appointment_id, :document_type, :path)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->bindParam(':document_type', $document_type);
        $stmt->bindParam(':path', $file_path);
        
        if ($stmt->execute()) {
            $success = "Document uploaded successfully!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get existing documents
$docs_query = "SELECT * FROM documents WHERE user_id = :user_id";
if ($appointment_id) {
    $docs_query .= " AND appointment_id = :appointment_id";
}
$docs_query .= " ORDER BY upload_date DESC";

$docs_stmt = $db->prepare($docs_query);
$docs_stmt->bindParam(':user_id', $_SESSION['user_id']);
if ($appointment_id) {
    $docs_stmt->bindParam(':appointment_id', $appointment_id);
}
$docs_stmt->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Documents - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Upload Supporting Document';
            $banner_subtitle = 'Upload verified documentary requirements for your city requests and casework';
            $banner_icon = 'fa-cloud-arrow-up';
            $banner_actions = [
                ['label' => 'Documents Library', 'url' => 'documents.php', 'icon' => 'fa-folder-open', 'type' => 'primary'],
                ['label' => 'Printable Forms', 'url' => 'print-forms.php', 'icon' => 'fa-print', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="upload-section">
                <h2>Upload New Document</h2>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="form-group">
                        <label for="document_type">Document Type *</label>
                        <select id="document_type" name="document_type" required>
                            <option value="">-- Select document type --</option>
                            <option value="valid_id">Valid ID</option>
                            <option value="barangay_certificate">Barangay Certificate</option>
                            <option value="medical_certificate">Medical Certificate</option>
                            <option value="birth_certificate">Birth Certificate</option>
                            <option value="marriage_certificate">Marriage Certificate</option>
                            <option value="proof_of_income">Proof of Income</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="document">Select File *</label>
                        <input type="file" id="document" name="document" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <small>Max size: 5MB. Allowed: PDF, JPG, PNG, DOC, DOCX</small>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Upload Document</button>
                    </div>
                </form>
            </div>
            
            <div class="documents-list">
                <h2>Your Documents</h2>
                <?php if($docs_stmt->rowCount() > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document Type</th>
                            <th>Upload Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($doc = $docs_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($doc['upload_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $doc['verification_status']; ?>">
                                    <?php echo ucfirst($doc['verification_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $doc['document_path']; ?>" target="_blank" class="btn-small">View</a>
                                <?php if($doc['verification_status'] == 'pending'): ?>
                                <a href="delete-document.php?id=<?php echo $doc['document_id']; ?>" 
                                   class="btn-small btn-danger" 
                                   onclick="return confirm('Are you sure?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="no-data">No documents uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="js/main.js"></script>
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>
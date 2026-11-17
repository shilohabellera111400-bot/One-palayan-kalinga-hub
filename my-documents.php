<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $document_id = sanitize($_GET['delete']);
    
    // Verify document belongs to user
    $check_query = "SELECT document_path FROM documents WHERE document_id = :id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $document_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $doc = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete file from server
        $uploader = new FileUploader();
        $uploader->deleteFile($doc['document_path']);
        
        // Delete from database
        $delete_query = "DELETE FROM documents WHERE document_id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $document_id);
        $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($delete_stmt->execute()) {
            $success = "Document deleted successfully!";
        } else {
            $error = "Failed to delete document.";
        }
    } else {
        $error = "Document not found or access denied.";
    }
}

// Get filter and search parameters
$filter_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT d.*, 
          CASE 
              WHEN d.appointment_id IS NOT NULL THEN 'Appointment'
              WHEN d.document_type LIKE '%aics%' THEN 'AICS'
              WHEN d.document_type LIKE '%housing%' THEN 'Housing'
              ELSE 'General'
          END as category
          FROM documents d
          WHERE d.user_id = :user_id";

$params = [':user_id' => $_SESSION['user_id']];

if ($filter_type != 'all') {
    if ($filter_type == 'appointment') {
        $query .= " AND d.appointment_id IS NOT NULL";
    } elseif ($filter_type == 'aics') {
        $query .= " AND d.document_type LIKE '%aics%'";
    } elseif ($filter_type == 'housing') {
        $query .= " AND d.document_type LIKE '%housing%'";
    } elseif ($filter_type == 'verified') {
        $query .= " AND d.verification_status = 'verified'";
    } elseif ($filter_type == 'pending') {
        $query .= " AND d.verification_status = 'pending'";
    } elseif ($filter_type == 'rejected') {
        $query .= " AND d.verification_status = 'rejected'";
    }
}

if (!empty($search)) {
    $query .= " AND (d.document_type LIKE :search OR d.remarks LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get document statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    COUNT(DISTINCT CASE WHEN appointment_id IS NOT NULL THEN appointment_id END) as related_appointments
    FROM documents WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get related appointments for filtering
$appointments_query = "SELECT DISTINCT a.appointment_id, s.service_name, a.appointment_date
                       FROM appointments a
                       JOIN services s ON a.service_id = s.service_id
                       WHERE a.user_id = :user_id
                       ORDER BY a.appointment_date DESC";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':user_id', $_SESSION['user_id']);
$appointments_stmt->execute();
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .documents-container {
            padding: 40px 0;
            background: #f9f9f9;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #003366;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #003366;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-details h3 {
            font-size: 1.8rem;
            color: #003366;
            margin-bottom: 5px;
        }

        .stat-details p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Action Bar */
        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .upload-btn {
            background: #ff6600;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .upload-btn:hover {
            background: #ff5500;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,102,0,0.3);
        }

        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-section select,
        .filter-section input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
        }

        .filter-section select {
            min-width: 150px;
        }

        .filter-section input {
            min-width: 250px;
        }

        .search-btn {
            background: #003366;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-btn:hover {
            background: #002244;
        }

        .clear-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .document-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 4px solid #003366;
        }

        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .document-card.status-pending { border-left-color: #ff9800; }
        .document-card.status-verified { border-left-color: #4CAF50; }
        .document-card.status-rejected { border-left-color: #f44336; }

        .document-header {
            padding: 15px 20px;
            background: #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }

        .document-type {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #333;
        }

        .document-type i {
            color: #ff6600;
            font-size: 1.2rem;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .document-body {
            padding: 20px;
        }

        .document-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9rem;
        }

        .meta-item i {
            width: 20px;
            color: #003366;
        }

        .document-preview {
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            text-align: center;
        }

        .document-preview img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 5px;
        }

        .document-preview .file-icon {
            font-size: 3rem;
            color: #ff6600;
        }

        .document-preview .file-name {
            margin-top: 10px;
            color: #666;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .document-remarks {
            margin-top: 15px;
            padding: 12px;
            background: #fff3cd;
            border-radius: 5px;
            color: #856404;
            font-size: 0.9rem;
            border-left: 3px solid #ffc107;
        }

        .document-remarks i {
            margin-right: 8px;
        }

        .document-footer {
            padding: 15px 20px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-view {
            background: #003366;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .btn-view:hover {
            background: #002244;
        }

        .btn-delete {
            background: #f44336;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: #d32f2f;
        }

        .btn-delete:disabled {
            background: #ffcdd2;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }

        .empty-state .btn-primary {
            display: inline-block;
            padding: 12px 30px;
            background: #003366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .empty-state .btn-primary:hover {
            background: #002244;
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-content h3 {
            color: #f44336;
            margin-bottom: 15px;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-actions .btn-confirm {
            background: #f44336;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-actions .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section {
                flex-direction: column;
            }

            .filter-section select,
            .filter-section input,
            .filter-section button {
                width: 100%;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .document-footer {
                flex-direction: column;
            }

            .document-footer a,
            .document-footer button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="documents-container">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-file-alt"></i> My Documents</h1>
                <p>Manage and track all your uploaded documents in one place</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="filterByType('all')">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total'] ?? 0; ?></h3>
                        <p>Total Documents</p>
                    </div>
                </div>
                <div class="stat-card" onclick="filterByType('pending')">
                    <div class="stat-icon" style="background: #ff9800;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                        <p>Pending Verification</p>
                    </div>
                </div>
                <div class="stat-card" onclick="filterByType('verified')">
                    <div class="stat-icon" style="background: #4CAF50;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['verified'] ?? 0; ?></h3>
                        <p>Verified</p>
                    </div>
                </div>
                <div class="stat-card" onclick="filterByType('rejected')">
                    <div class="stat-icon" style="background: #f44336;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                        <p>Disapproved</p>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <a href="upload-documents.php" class="upload-btn">
                    <i class="fas fa-upload"></i> Upload New Document
                </a>

                <div class="filter-section">
                    <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="type">
                            <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Documents</option>
                            <option value="pending" <?php echo $filter_type == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="verified" <?php echo $filter_type == 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="rejected" <?php echo $filter_type == 'rejected' ? 'selected' : ''; ?>>Disapproved</option>
                            <option value="appointment" <?php echo $filter_type == 'appointment' ? 'selected' : ''; ?>>Appointment Related</option>
                            <option value="aics" <?php echo $filter_type == 'aics' ? 'selected' : ''; ?>>AICS Related</option>
                            <option value="housing" <?php echo $filter_type == 'housing' ? 'selected' : ''; ?>>Housing Related</option>
                        </select>

                        <input type="text" name="search" placeholder="Search documents..." 
                               value="<?php echo htmlspecialchars($search); ?>">

                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Filter
                        </button>

                        <?php if($filter_type != 'all' || !empty($search)): ?>
                        <a href="my-documents.php" class="clear-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Documents Grid -->
            <?php if($stmt->rowCount() > 0): ?>
                <div class="documents-grid">
                    <?php while($doc = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="document-card status-<?php echo $doc['verification_status']; ?>">
                            <div class="document-header">
                                <div class="document-type">
                                    <?php
                                    $icon = 'fa-file-alt';
                                    if (strpos($doc['document_type'], 'ID') !== false) $icon = 'fa-id-card';
                                    elseif (strpos($doc['document_type'], 'Certificate') !== false) $icon = 'fa-certificate';
                                    elseif (strpos($doc['document_type'], 'Photo') !== false) $icon = 'fa-camera';
                                    elseif (strpos($doc['document_type'], 'Proof') !== false) $icon = 'fa-file-invoice';
                                    elseif (strpos($doc['document_type'], 'Consent') !== false) $icon = 'fa-file-signature';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <span><?php echo htmlspecialchars($doc['document_type']); ?></span>
                                </div>
                                <span class="status-badge status-<?php echo $doc['verification_status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $doc['verification_status'] == 'pending' ? 'clock' : 
                                            ($doc['verification_status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                                    ?>"></i>
                                    <?php echo ucfirst($doc['verification_status']); ?>
                                </span>
                            </div>

                            <div class="document-body">
                                <div class="document-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Uploaded: <?php echo date('F d, Y h:i A', strtotime($doc['upload_date'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span>Category: <?php echo $doc['category']; ?></span>
                                    </div>
                                    <?php if($doc['appointment_id']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Appointment #: APT-<?php echo str_pad($doc['appointment_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($doc['verification_date']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                                        <span>Verified: <?php echo date('F d, Y', strtotime($doc['verification_date'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="document-preview">
                                    <?php
                                    $file_ext = strtolower(pathinfo($doc['document_path'], PATHINFO_EXTENSION));
                                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                        <img src="<?php echo htmlspecialchars($doc['document_path']); ?>" alt="Document Preview">
                                    <?php else: ?>
                                        <div class="file-icon">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="file-name">
                                        <?php echo basename($doc['document_path']); ?>
                                    </div>
                                </div>

                                <?php if(!empty($doc['remarks'])): ?>
                                <div class="document-remarks">
                                    <i class="fas fa-comment"></i>
                                    <?php echo htmlspecialchars($doc['remarks']); ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="document-footer">
                                <a href="<?php echo htmlspecialchars($doc['document_path']); ?>" target="_blank" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="?delete=<?php echo $doc['document_id']; ?>" 
                                   class="btn-delete" 
                                   onclick="return confirmDelete(event, '<?php echo htmlspecialchars($doc['document_type']); ?>')"
                                   <?php echo $doc['verification_status'] == 'verified' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-file-upload"></i>
                    <h3>No Documents Found</h3>
                    <p>You haven't uploaded any documents yet. Start by uploading your first document.</p>
                    <a href="upload-documents.php" class="btn-primary">
                        <i class="fas fa-upload"></i> Upload Document
                    </a>
                </div>
            <?php endif; ?>

            <!-- Document Usage Info -->
            <?php if($stats['total'] > 0): ?>
            <div class="info-box" style="margin-top: 30px; background: #e3f2fd; border-left-color: #003366;">
                <h4 style="color: #003366;"><i class="fas fa-info-circle"></i> Document Information</h4>
                <ul>
                    <li><i class="fas fa-check-circle" style="color: #4CAF50;"></i> <strong>Verified</strong> documents are valid and accepted</li>
                    <li><i class="fas fa-clock" style="color: #ff9800;"></i> <strong>Pending</strong> documents are still being reviewed by staff</li>
                    <li><i class="fas fa-times-circle" style="color: #f44336;"></i> <strong>Disapproved</strong> documents need to be re-uploaded with corrections</li>
                    <li><i class="fas fa-trash" style="color: #666;"></i> You can only delete documents that are still pending verification</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete Document</h3>
            <p>Are you sure you want to delete <span id="docName"></span>?</p>
            <p style="color: #f44336; font-size: 0.9rem;">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-confirm" id="confirmDelete">Delete</button>
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    let deleteUrl = '';

    function confirmDelete(event, docName) {
        event.preventDefault();
        deleteUrl = event.currentTarget.href;
        document.getElementById('docName').textContent = docName;
        document.getElementById('deleteModal').classList.add('show');
        return false;
    }

    document.getElementById('confirmDelete').addEventListener('click', function() {
        window.location.href = deleteUrl;
    });

    function closeModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }

    function filterByType(type) {
        window.location.href = 'my-documents.php?type=' + type;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    </script>

    <script src="js/main.js"></script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
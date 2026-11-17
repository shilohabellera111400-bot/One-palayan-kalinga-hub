<?php
// documents.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/FileUploader.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Handle document upload
$upload_error = '';
$upload_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    csrf_verify();
    $document_type = sanitize($_POST['document_type']);
    $appointment_id = !empty($_POST['appointment_id']) ? sanitize($_POST['appointment_id']) : null;
    $description = sanitize($_POST['description'] ?? '');
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploader = new FileUploader();
        
        try {
            $file_path = $uploader->upload($_FILES['document'], 'documents');
            
            $query = "INSERT INTO documents (user_id, appointment_id, document_type, document_path, description, verification_status) 
                      VALUES (:user_id, :appointment_id, :document_type, :path, :description, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':appointment_id', $appointment_id);
            $stmt->bindParam(':document_type', $document_type);
            $stmt->bindParam(':path', $file_path);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $upload_success = "Document uploaded successfully!";
            } else {
                $upload_error = "Failed to save document information.";
            }
        } catch (Exception $e) {
            $upload_error = $e->getMessage();
        }
    } else {
        $upload_error = "Please select a file to upload.";
    }
}

// Handle document deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $document_id = sanitize($_GET['delete']);
    
    $check_query = "SELECT document_path FROM documents WHERE document_id = :id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $document_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $doc = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $uploader = new FileUploader();
        $uploader->deleteFile($doc['document_path']);
        
        $delete_query = "DELETE FROM documents WHERE document_id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $document_id);
        $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($delete_stmt->execute()) {
            $upload_success = "Document deleted successfully!";
        } else {
            $upload_error = "Failed to delete document.";
        }
    } else {
        $upload_error = "Document not found or access denied.";
    }
}

$query = "SELECT d.* FROM documents d WHERE d.user_id = :user_id ORDER BY d.upload_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM documents WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$appointments_query = "SELECT a.appointment_id, s.service_name, a.appointment_date 
                       FROM appointments a JOIN services s ON a.service_id = s.service_id
                       WHERE a.user_id = :user_id ORDER BY a.appointment_date DESC";
$appointments_stmt = $db->prepare($appointments_query);
$appointments_stmt->bindParam(':user_id', $_SESSION['user_id']);
$appointments_stmt->execute();
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = '';
$avatar_url = '';
$user_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data) {
    $profile_picture = $user_data['profile_picture'] ?? '';
    $avatar_url = !empty($profile_picture) && file_exists($profile_picture) 
        ? $profile_picture 
        : '';
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg);
            padding: 16px 20px;
            border-radius: var(--radius-element);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .stat-card .stat-icon {
            font-size: 1.2rem;
            color: var(--maroon-light);
            margin-bottom: 4px;
        }

        /* ========== UPLOAD FORM ========== */
        .upload-section {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            display: none;
            transition: all 0.3s;
        }
        .upload-section.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .upload-section .form-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
        }
        .upload-section .form-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .upload-section .form-header i {
            color: var(--maroon-light);
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .form-group label .required {
            color: var(--color-danger);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .btn-submit {
            background: var(--maroon);
            color: white;
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }
        .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-cancel {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-cancel:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        /* ========== DOCUMENTS GRID ========== */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        .document-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .card-header {
            padding: 14px 18px;
            background: var(--badge-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header .doc-type {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header .doc-type i {
            color: var(--maroon-light);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 14px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        .status-badge.pending {
            background: rgba(255, 152, 0, 0.15);
            color: var(--color-warning);
        }
        .status-badge.verified {
            background: rgba(76, 175, 80, 0.15);
            color: var(--color-success);
        }
        .status-badge.rejected {
            background: rgba(244, 67, 54, 0.15);
            color: var(--color-danger);
        }

        .card-body {
            padding: 16px 18px;
        }
        .card-body .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .card-body .detail-row i {
            color: var(--maroon-light);
            width: 18px;
            font-size: 0.8rem;
        }
        .card-body .detail-row .label {
            color: var(--text-muted);
            min-width: 80px;
        }
        .card-body .description-text {
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--badge-bg);
            border-radius: var(--radius-element);
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .card-footer {
            padding: 12px 18px;
            background: var(--badge-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-view {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 6px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-view:hover {
            background: var(--maroon);
            color: white;
            transform: translateY(-2px);
        }
        .btn-delete {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            padding: 6px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-delete:hover {
            background: var(--color-danger);
            color: white;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
        }
        .empty-state i {
            font-size: 3.5rem;
            color: var(--text-muted);
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .empty-state .btn-upload {
            display: inline-flex;
            padding: 10px 24px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            align-items: center;
            gap: 8px;
        }
        .empty-state .btn-upload:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .documents-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .upload-section { padding: 18px 20px; }
            .form-actions { flex-direction: column; }
            .form-actions button { width: 100%; justify-content: center; }
            .documents-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .card-footer { flex-direction: column; }
            .btn-view, .btn-delete { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .upload-section { padding: 14px 16px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "My Documents & Files";
            $banner_subtitle = "Upload and manage your verified credentials and application attachments";
            $banner_icon = "fa-folder-open";
            $banner_actions = [
                ["label" => "Upload Document", "url" => "javascript:void(0);", "onclick" => "toggleUploadForm()", "icon" => "fa-upload", "type" => "primary"],
                ["label" => "Printable Forms", "url" => "print-forms.php", "icon" => "fa-print", "type" => "secondary"]
            ];
            include __DIR__ . "/includes/portal-banner.php";
            ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock" style="color: var(--color-warning);"></i></div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: var(--color-success);"></i></div>
                <div class="stat-number"><?php echo $stats['verified'] ?? 0; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle" style="color: var(--color-danger);"></i></div>
                <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Disapproved</div>
            </div>
        </div>

        <!-- Upload Form -->
        <div id="uploadForm" class="upload-section <?php echo isset($_GET['upload']) ? 'show' : ''; ?>">
            <div class="form-header">
                <i class="fas fa-upload"></i>
                <h2>Upload New Document</h2>
            </div>
            <?php if($upload_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $upload_error; ?>
                </div>
            <?php endif; ?>
            <?php if($upload_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $upload_success; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Document Type <span class="required">*</span></label>
                    <select name="document_type" required>
                        <option value="">Select document type</option>
                        <option value="valid_id">🪪 Valid ID</option>
                        <option value="barangay_certificate">📄 Barangay Certificate</option>
                        <option value="medical_certificate">🏥 Medical Certificate</option>
                        <option value="proof_of_income">💰 Proof of Income</option>
                        <option value="school_id">🎓 School ID</option>
                        <option value="enrollment_form">📋 Enrollment Form</option>
                        <option value="death_certificate">📜 Death Certificate</option>
                        <option value="other">📎 Other Document</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Add a brief description of this document..."></textarea>
                </div>
                <div class="form-group">
                    <label>Select File <span class="required">*</span></label>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small>Accepted formats: PDF, JPG, JPEG, PNG (Max 5MB)</small>
                </div>
                <div class="form-actions">
                    <button type="submit" name="upload_document" class="btn-submit">
                        <i class="fas fa-save"></i> Upload Document
                    </button>
                    <button type="button" class="btn-cancel" onclick="toggleUploadForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Documents Grid -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="documents-grid">
            <?php while($doc = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="document-card">
                <div class="card-header">
                    <span class="doc-type">
                        <i class="fas fa-file-alt"></i>
                        <?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?>
                    </span>
                    <span class="status-badge <?php echo $doc['verification_status']; ?>">
                        <i class="fas fa-<?php 
                            echo $doc['verification_status'] == 'pending' ? 'clock' : 
                                ($doc['verification_status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                        ?>"></i>
                        <?php echo ucfirst($doc['verification_status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <i class="fas fa-calendar"></i>
                        <span class="label">Uploaded:</span>
                        <span><?php echo date('M d, Y h:i A', strtotime($doc['upload_date'])); ?></span>
                    </div>
                    <?php if(!empty($doc['description'])): ?>
                    <div class="description-text">
                        <?php echo htmlspecialchars(substr($doc['description'], 0, 120)); ?>
                        <?php if(strlen($doc['description']) > 120): ?>...<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="<?php echo htmlspecialchars($doc['document_path']); ?>" target="_blank" class="btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php if($doc['verification_status'] == 'pending'): ?>
                    <a href="?delete=<?php echo $doc['document_id']; ?>" class="btn-delete" 
                       onclick="return confirm('Are you sure you want to delete this document? This action cannot be undone.')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-upload"></i>
            <h3>No Documents Found</h3>
            <p>You haven't uploaded any documents yet.</p>
            <button class="btn-upload" onclick="toggleUploadForm()">
                <i class="fas fa-upload"></i> Upload Your First Document
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ========== MOBILE SIDEBAR TOGGLE ==========
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebarClose = document.getElementById('sidebarClose');

function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle ? themeToggle.querySelector('i') : null;
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
}

// ========== UPLOAD FORM TOGGLE ==========
function toggleUploadForm() {
    const form = document.getElementById('uploadForm');
    form.classList.toggle('show');
    if (form.classList.contains('show')) {
        setTimeout(() => {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
}

// ========== AUTO-HIDE ALERTS ==========
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 500);
    });
}, 5000);
</script>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

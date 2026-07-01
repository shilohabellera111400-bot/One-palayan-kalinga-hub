<?php
// admin/view-verification.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$verification_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

if (!$verification_id) {
    redirect('manage-verifications.php');
}

// Fetch verification details
$query = "SELECT v.*, u.first_name, u.last_name, u.email, u.contact_number, u.address,
          s.first_name as staff_first_name, s.last_name as staff_last_name
          FROM verification_requests v
          JOIN users u ON v.user_id = u.user_id
          LEFT JOIN staff s ON v.reviewed_by = s.user_id
          WHERE v.verification_id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $verification_id);
$stmt->execute();
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$verification) {
    redirect('manage-verifications.php?error=Verification not found');
}

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Format date for display
$submitted_date = date('F d, Y h:i A', strtotime($verification['submitted_date']));
$review_date = $verification['review_date'] ? date('F d, Y h:i A', strtotime($verification['review_date'])) : null;

// Get file extensions for icons
$id_file_ext = pathinfo($verification['id_file_path'], PATHINFO_EXTENSION);
$proof_file_ext = pathinfo($verification['proof_of_residency_path'], PATHINFO_EXTENSION);
$barangay_file_ext = $verification['barangay_certificate_path'] ? pathinfo($verification['barangay_certificate_path'], PATHINFO_EXTENSION) : null;

$file_icons = [
    'pdf' => 'fa-file-pdf',
    'jpg' => 'fa-file-image',
    'jpeg' => 'fa-file-image',
    'png' => 'fa-file-image',
    'gif' => 'fa-file-image',
    'doc' => 'fa-file-word',
    'docx' => 'fa-file-word',
    'xls' => 'fa-file-excel',
    'xlsx' => 'fa-file-excel',
    'txt' => 'fa-file-alt'
];

$id_icon = $file_icons[strtolower($id_file_ext)] ?? 'fa-file';
$proof_icon = $file_icons[strtolower($proof_file_ext)] ?? 'fa-file';
$barangay_icon = $barangay_file_ext ? ($file_icons[strtolower($barangay_file_ext)] ?? 'fa-file') : 'fa-file';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Verification - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            --bg-primary: #F8F9FC;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
            
            --radius-card: 20px;
            --radius-element: 12px;
            
            --color-success: #4CAF50;
            --color-warning: #FF9800;
            --color-danger: #EF5350;
            --color-info: #2196F3;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-secondary: #111827;
            --bg-card: #1a2332;
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --border-color: #2a3a4f;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);
            
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            
            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ========== LAYOUT ========== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 36px 40px;
        }

        /* ========== TOP BAR ========== */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 2px;
        }
        .top-bar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .theme-toggle {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: color 0.3s ease;
            text-decoration: none;
            background: var(--card-bg);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .top-icon:hover { 
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--card-bg);
            color: var(--text-secondary);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== VERIFICATION CARDS ========== */
        .verification-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
            margin-bottom: 24px;
        }
        .card:last-child {
            margin-bottom: 0;
        }
        .card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        .card .card-header h2 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
        }
        .card .card-header i {
            color: var(--maroon-light);
            font-size: 1.1rem;
        }

        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row .label {
            width: 140px;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.82rem;
            flex-shrink: 0;
        }
        .info-row .value {
            flex: 1;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .info-row .value .highlight {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
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

        /* Document Links */
        .doc-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--badge-bg);
            border-radius: 50px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        .doc-link:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .doc-link i {
            font-size: 0.9rem;
        }

        /* Action Form */
        .action-form {
            margin-top: 16px;
        }
        .action-form .form-group {
            margin-bottom: 16px;
        }
        .action-form .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.82rem;
        }
        .action-form .form-group select,
        .action-form .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            transition: all 0.3s;
        }
        .action-form .form-group select:focus,
        .action-form .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .action-form .btn-submit {
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
            width: 100%;
            justify-content: center;
        }
        .action-form .btn-submit:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .rejection-reason-box {
            background: rgba(244, 67, 54, 0.06);
            border-left: 4px solid var(--color-danger);
            padding: 12px 16px;
            border-radius: var(--radius-element);
            margin-top: 8px;
        }
        .rejection-reason-box p {
            color: var(--color-danger);
            font-size: 0.82rem;
            margin: 0;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .container { padding: 20px; }
            .verification-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .info-row { flex-direction: column; }
            .info-row .label { width: 100%; margin-bottom: 4px; }
            .card { padding: 18px 20px; }
            .back-btn { justify-content: center; }
        }
        @media (max-width: 480px) {
            .container { padding: 16px; }
            .card { padding: 14px 16px; }
            .doc-link { font-size: 0.7rem; padding: 4px 10px; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="page-title">
            <h1><i class="fas fa-user-check" style="color: var(--maroon-light); margin-right: 10px;"></i> Verification Details</h1>
            <p>Review and manage residency verification request</p>
        </div>
        <div class="top-bar-actions">
            <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
            </button>
            <a href="manage-verifications.php" class="top-icon"><i class="fas fa-arrow-left"></i></a>
        </div>
    </div>

    <a href="manage-verifications.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Verifications
    </a>

    <div class="verification-grid">
        <!-- Left Column: Applicant & Documents -->
        <div>
            <!-- Applicant Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h2>Applicant Information</h2>
                </div>
                <div class="info-row">
                    <span class="label">Request ID</span>
                    <span class="value"><strong class="highlight">#<?php echo $verification['verification_id']; ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Full Name</span>
                    <span class="value"><span class="highlight"><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></span></span>
                </div>
                <div class="info-row">
                    <span class="label">Email</span>
                    <span class="value"><?php echo htmlspecialchars($verification['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Contact Number</span>
                    <span class="value"><?php echo htmlspecialchars($verification['contact_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Address</span>
                    <span class="value"><?php echo htmlspecialchars($verification['address'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <!-- Documents -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-id-card"></i>
                    <h2>Submitted Documents</h2>
                </div>
                <div class="info-row">
                    <span class="label">ID Type</span>
                    <span class="value"><?php echo htmlspecialchars($verification['id_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">ID Number</span>
                    <span class="value"><?php echo htmlspecialchars($verification['id_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Barangay</span>
                    <span class="value"><?php echo htmlspecialchars($verification['barangay_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Complete Address</span>
                    <span class="value"><?php echo nl2br(htmlspecialchars($verification['complete_address'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Valid ID</span>
                    <span class="value">
                        <a href="../uploads/verification/<?php echo basename($verification['id_file_path']); ?>" target="_blank" class="doc-link">
                            <i class="fas <?php echo $id_icon; ?>"></i> View Document
                        </a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Proof of Residency</span>
                    <span class="value">
                        <a href="../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>" target="_blank" class="doc-link">
                            <i class="fas <?php echo $proof_icon; ?>"></i> View Document
                        </a>
                    </span>
                </div>
                <?php if($verification['barangay_certificate_path']): ?>
                <div class="info-row">
                    <span class="label">Barangay Certificate</span>
                    <span class="value">
                        <a href="../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>" target="_blank" class="doc-link">
                            <i class="fas <?php echo $barangay_icon; ?>"></i> View Document
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Status & Action -->
        <div>
            <!-- Status Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h2>Status Information</h2>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge <?php echo $verification['status']; ?>">
                            <i class="fas fa-<?php 
                                echo $verification['status'] == 'pending' ? 'clock' : 
                                    ($verification['status'] == 'verified' ? 'check-circle' : 'times-circle'); 
                            ?>"></i>
                            <?php echo ucfirst($verification['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Submitted</span>
                    <span class="value"><?php echo $submitted_date; ?></span>
                </div>
                <?php if($verification['reviewed_by']): ?>
                <div class="info-row">
                    <span class="label">Reviewed By</span>
                    <span class="value"><?php echo htmlspecialchars($verification['staff_first_name'] . ' ' . $verification['staff_last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Review Date</span>
                    <span class="value"><?php echo $review_date; ?></span>
                </div>
                <?php endif; ?>
                <?php if($verification['rejection_reason']): ?>
                <div class="rejection-reason-box">
                    <p><strong><i class="fas fa-exclamation-circle"></i> Rejection Reason:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($verification['rejection_reason'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Section (only for pending) -->
            <?php if($verification['status'] == 'pending'): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-gavel"></i>
                    <h2>Take Action</h2>
                </div>
                <form method="POST" action="manage-verifications.php" class="action-form">
                    <input type="hidden" name="verification_id" value="<?php echo $verification['verification_id']; ?>">
                    
                    <div class="form-group">
                        <label>Verification Status</label>
                        <select name="status" id="status" required>
                            <option value="verified">✅ Approve - Verified Resident</option>
                            <option value="rejected">❌ Reject - Not Verified</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="reasonDiv" style="display: none;">
                        <label>Rejection Reason <span style="color: var(--color-danger);">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" 
                                  placeholder="Please provide a reason for rejection..."></textarea>
                    </div>
                    
                    <button type="submit" name="update_verification" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Submit Decision
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ========== THEME TOGGLE ==========
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
}

setTheme(currentTheme);

themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// ========== STATUS TOGGLE ==========
const statusSelect = document.getElementById('status');
const reasonDiv = document.getElementById('reasonDiv');
const rejectionReason = document.getElementById('rejection_reason');

if (statusSelect) {
    statusSelect.addEventListener('change', function() {
        if (this.value === 'rejected') {
            reasonDiv.style.display = 'block';
            rejectionReason.required = true;
            reasonDiv.style.animation = 'slideDown 0.3s ease';
        } else {
            reasonDiv.style.display = 'none';
            rejectionReason.required = false;
        }
    });
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>
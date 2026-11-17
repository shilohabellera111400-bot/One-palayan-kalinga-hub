<?php
// admin/view-verification.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin() && !isStaff()) {
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
$page_title = 'Verification Details';
$active_nav = 'verifications';
require_once __DIR__ . '/includes/admin-head.php';
?>
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            --bg-primary: var(--ref-canvas, #E4EFE7);
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #C8D0DC;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 6px 24px rgba(15,65,74,0.12), 0 2px 8px rgba(0,0,0,0.07);
            --shadow-lg: 0 12px 40px rgba(15,65,74,0.15), 0 4px 12px rgba(0,0,0,0.08);
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
            --bg-primary: var(--ref-canvas, #0C1417);
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

        /* ========== DOCUMENT INSPECTION GALLERY & LIGHTBOX ========== */
        .admin-doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }
        .admin-doc-card {
            background: var(--badge-bg);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        .admin-doc-card:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .admin-doc-header {
            padding: 10px 14px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .admin-doc-thumb-wrap {
            position: relative;
            width: 100%;
            height: 180px;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
        }
        .admin-doc-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .admin-doc-thumb-wrap:hover img {
            transform: scale(1.06);
        }
        .admin-doc-hover-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 65, 74, 0.65);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.25s ease;
            backdrop-filter: blur(2px);
        }
        .admin-doc-thumb-wrap:hover .admin-doc-hover-overlay {
            opacity: 1;
        }
        .admin-doc-hover-overlay i {
            font-size: 1.5rem;
            color: var(--gold);
        }
        .admin-doc-hover-overlay span {
            font-size: 0.75rem;
            font-weight: 700;
        }
        .admin-doc-footer {
            padding: 10px 14px;
            background: var(--card-bg);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-inspect-action {
            background: var(--maroon);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-inspect-action:hover {
            background: var(--maroon-light);
        }
        .btn-download-action {
            background: var(--badge-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-download-action:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        /* Lightbox for Admin */
        .admin-lightbox-modal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(8, 12, 16, 0.94);
            backdrop-filter: blur(8px);
            display: none;
            flex-direction: column;
            animation: fadeInLightbox 0.2s ease;
        }
        .admin-lightbox-modal.active {
            display: flex;
        }
        .admin-lightbox-topbar {
            height: 54px;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(15, 23, 42, 0.85);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .admin-lightbox-title {
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-lightbox-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-lb-tool {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .btn-lb-tool:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        .btn-lb-close {
            background: #dc2626;
            border-color: #ef4444;
            color: #fff;
            margin-left: 8px;
        }
        .btn-lb-close:hover {
            background: #b91c1c;
        }
        .admin-lightbox-stage {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            cursor: grab;
            user-select: none;
        }
        .admin-lightbox-stage:active {
            cursor: grabbing;
        }
        .admin-lightbox-stage img {
            max-width: 90%;
            max-height: 85vh;
            object-fit: contain;
            transition: transform 0.15s ease-out;
            transform-origin: center center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            border-radius: 6px;
        }
        .admin-lightbox-footer {
            padding: 8px 24px;
            background: rgba(15, 23, 42, 0.85);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-user-check" style="color: var(--ref-maroon);"></i> Verification Request #<?php echo $verification['verification_id']; ?></h1>
        <p>Review resident documentation and manage approval decision</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <span class="status-badge-pill <?php echo $verification['status']; ?>">
            <i class="fas fa-shield-alt"></i>
            <span style="text-transform: capitalize;"><?php echo ($verification['status'] === 'rejected') ? 'Disapproved' : htmlspecialchars($verification['status']); ?></span>
        </span>
    </div>
</div>

<a href="manage-verifications.php" class="back-btn" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--ref-maroon); text-decoration: none; margin-bottom: 16px;">
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
                <!-- Attached Documents Inspection Grid -->
                <div style="margin-top: 18px; padding-top: 14px; border-top: 1.5px solid var(--border-color);">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-images" style="color: var(--gold);"></i> Attached Verification Documents</span>
                        <span style="font-size: 0.72rem; color: var(--text-muted);"><i class="fas fa-search-plus"></i> Click image to inspect & zoom</span>
                    </div>

                    <div class="admin-doc-grid">
                        <!-- Valid ID Card (Front Side) -->
                        <div class="admin-doc-card">
                            <div class="admin-doc-header">
                                <span><i class="fas fa-id-card" style="color: var(--gold);"></i> Valid ID Front (<?php echo htmlspecialchars($verification['id_type']); ?>)</span>
                            </div>
                            <div class="admin-doc-thumb-wrap" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['id_file_path']); ?>', 'Valid ID Front - <?php echo htmlspecialchars($verification['id_type']); ?>')">
                                <img src="../uploads/verification/<?php echo basename($verification['id_file_path']); ?>" alt="Valid ID Front" onerror="this.src='../images/palayan-city.png'">
                                <div class="admin-doc-hover-overlay">
                                    <i class="fas fa-search-plus"></i>
                                    <span>Zoom & Inspect</span>
                                </div>
                            </div>
                            <div class="admin-doc-footer">
                                <button type="button" class="btn-inspect-action" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['id_file_path']); ?>', 'Valid ID Front - <?php echo htmlspecialchars($verification['id_type']); ?>')">
                                    <i class="fas fa-search-plus"></i> Zoom & Inspect
                                </button>
                                <a href="../uploads/verification/<?php echo basename($verification['id_file_path']); ?>" target="_blank" class="btn-download-action" download>
                                    <i class="fas fa-download"></i> Open Original
                                </a>
                            </div>
                        </div>

                        <!-- Valid ID Card (Back Side) -->
                        <?php if (!empty($verification['id_back_file_path'])): ?>
                        <div class="admin-doc-card">
                            <div class="admin-doc-header">
                                <span><i class="fas fa-id-card-alt" style="color: var(--gold);"></i> Valid ID Back Side</span>
                            </div>
                            <div class="admin-doc-thumb-wrap" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>', 'Valid ID Back Side')">
                                <img src="../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>" alt="Valid ID Back" onerror="this.src='../images/palayan-city.png'">
                                <div class="admin-doc-hover-overlay">
                                    <i class="fas fa-search-plus"></i>
                                    <span>Zoom & Inspect</span>
                                </div>
                            </div>
                            <div class="admin-doc-footer">
                                <button type="button" class="btn-inspect-action" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>', 'Valid ID Back Side')">
                                    <i class="fas fa-search-plus"></i> Zoom & Inspect
                                </button>
                                <a href="../uploads/verification/<?php echo basename($verification['id_back_file_path']); ?>" target="_blank" class="btn-download-action" download>
                                    <i class="fas fa-download"></i> Open Original
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Selfie with ID Card -->
                        <div class="admin-doc-card">
                            <div class="admin-doc-header">
                                <span><i class="fas fa-portrait" style="color: var(--gold);"></i> Proof of Residency (Selfie)</span>
                            </div>
                            <div class="admin-doc-thumb-wrap" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>', 'Resident Selfie with ID')">
                                <img src="../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>" alt="Selfie with ID" onerror="this.src='../images/palayan-city.png'">
                                <div class="admin-doc-hover-overlay">
                                    <i class="fas fa-search-plus"></i>
                                    <span>Zoom & Inspect</span>
                                </div>
                            </div>
                            <div class="admin-doc-footer">
                                <button type="button" class="btn-inspect-action" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>', 'Resident Selfie with ID')">
                                    <i class="fas fa-search-plus"></i> Zoom & Inspect
                                </button>
                                <a href="../uploads/verification/<?php echo basename($verification['proof_of_residency_path']); ?>" target="_blank" class="btn-download-action" download>
                                    <i class="fas fa-download"></i> Open Original
                                </a>
                            </div>
                        </div>

                        <?php if(!empty($verification['barangay_certificate_path'])): ?>
                        <!-- Barangay Certificate Card -->
                        <div class="admin-doc-card">
                            <div class="admin-doc-header">
                                <span><i class="fas fa-file-alt" style="color: var(--gold);"></i> Barangay Certificate</span>
                            </div>
                            <div class="admin-doc-thumb-wrap" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>', 'Barangay Certificate')">
                                <img src="../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>" alt="Barangay Certificate" onerror="this.src='../images/palayan-city.png'">
                                <div class="admin-doc-hover-overlay">
                                    <i class="fas fa-search-plus"></i>
                                    <span>Zoom & Inspect</span>
                                </div>
                            </div>
                            <div class="admin-doc-footer">
                                <button type="button" class="btn-inspect-action" onclick="openAdminLightbox('../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>', 'Barangay Certificate')">
                                    <i class="fas fa-search-plus"></i> Zoom & Inspect
                                </button>
                                <a href="../uploads/verification/<?php echo basename($verification['barangay_certificate_path']); ?>" target="_blank" class="btn-download-action" download>
                                    <i class="fas fa-download"></i> Open Original
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                            <?php echo ($verification['status'] === 'rejected') ? 'Disapproved' : ucfirst($verification['status']); ?>
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
                    <p><strong><i class="fas fa-exclamation-circle"></i> Disapproval Reason:</strong></p>
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
                            <option value="rejected">❌ Disapproved - Not Verified</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="reasonDiv" style="display: none;">
                        <label>Disapproval Reason <span style="color: var(--color-danger);">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" 
                                  placeholder="Please provide a reason for disapproval..."></textarea>
                    </div>
                    
                    <button type="submit" name="update_verification" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Submit Decision
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Universal Full-Screen Lightbox Modal for Admin -->
<div class="admin-lightbox-modal" id="adminLightboxModal">
    <div class="admin-lightbox-topbar">
        <div class="admin-lightbox-title">
            <i class="fas fa-shield-alt" style="color: var(--gold);"></i>
            <span id="adminLightboxTitle">Verification Document Inspection</span>
        </div>
        <div class="admin-lightbox-controls">
            <button type="button" class="btn-lb-tool" id="adminLbZoomInBtn" title="Zoom In (+)"><i class="fas fa-search-plus"></i></button>
            <button type="button" class="btn-lb-tool" id="adminLbZoomOutBtn" title="Zoom Out (-)"><i class="fas fa-search-minus"></i></button>
            <button type="button" class="btn-lb-tool" id="adminLbRotateBtn" title="Rotate 90° Clockwise"><i class="fas fa-redo"></i></button>
            <button type="button" class="btn-lb-tool" id="adminLbResetBtn" title="Reset View"><i class="fas fa-undo"></i></button>
            <button type="button" class="btn-lb-tool" id="adminLbFullscreenBtn" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
            <button type="button" class="btn-lb-tool btn-lb-close" id="adminLbCloseBtn" title="Close (Esc)"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="admin-lightbox-stage" id="adminLightboxStage">
        <img id="adminLightboxImage" src="" alt="Document Preview">
    </div>
    <div class="admin-lightbox-footer">
        <span><i class="fas fa-mouse"></i> Scroll mouse wheel to zoom · Click and drag to pan across high-res details</span>
        <span id="adminLightboxZoomLevel">Zoom: 100%</span>
    </div>
</div>

<script>
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

// ========== ADMIN LIGHTBOX CONTROLLER ==========
const adminModal = document.getElementById('adminLightboxModal');
const adminImg = document.getElementById('adminLightboxImage');
const adminTitle = document.getElementById('adminLightboxTitle');
const adminZoomText = document.getElementById('adminLightboxZoomLevel');
const adminStage = document.getElementById('adminLightboxStage');

let adminLbState = { scale: 1, rotation: 0, translateX: 0, translateY: 0, isDragging: false, startX: 0, startY: 0 };

function updateAdminLbTransform() {
    if (!adminImg) return;
    adminImg.style.transform = `translate(${adminLbState.translateX}px, ${adminLbState.translateY}px) scale(${adminLbState.scale}) rotate(${adminLbState.rotation}deg)`;
    if (adminZoomText) {
        adminZoomText.textContent = `Zoom: ${Math.round(adminLbState.scale * 100)}% · Rotation: ${adminLbState.rotation}°`;
    }
}

function openAdminLightbox(src, title = 'Document Inspection') {
    if (!adminModal || !adminImg || !src) return;
    adminImg.src = src;
    if (adminTitle) adminTitle.textContent = title;
    adminLbState = { scale: 1, rotation: 0, translateX: 0, translateY: 0, isDragging: false, startX: 0, startY: 0 };
    updateAdminLbTransform();
    adminModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAdminLightbox() {
    if (!adminModal) return;
    adminModal.classList.remove('active');
    document.body.style.overflow = '';
    if (adminImg) adminImg.src = '';
}

document.getElementById('adminLbCloseBtn')?.addEventListener('click', closeAdminLightbox);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && adminModal && adminModal.classList.contains('active')) {
        closeAdminLightbox();
    }
});

document.getElementById('adminLbZoomInBtn')?.addEventListener('click', () => {
    adminLbState.scale = Math.min(adminLbState.scale + 0.25, 4.0);
    updateAdminLbTransform();
});
document.getElementById('adminLbZoomOutBtn')?.addEventListener('click', () => {
    adminLbState.scale = Math.max(adminLbState.scale - 0.25, 0.4);
    updateAdminLbTransform();
});
document.getElementById('adminLbRotateBtn')?.addEventListener('click', () => {
    adminLbState.rotation = (adminLbState.rotation + 90) % 360;
    updateAdminLbTransform();
});
document.getElementById('adminLbResetBtn')?.addEventListener('click', () => {
    adminLbState.scale = 1;
    adminLbState.rotation = 0;
    adminLbState.translateX = 0;
    adminLbState.translateY = 0;
    updateAdminLbTransform();
});
document.getElementById('adminLbFullscreenBtn')?.addEventListener('click', () => {
    if (!document.fullscreenElement) {
        adminModal.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
});

if (adminStage) {
    adminStage.addEventListener('wheel', (e) => {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.15 : 0.15;
        adminLbState.scale = Math.min(Math.max(adminLbState.scale + delta, 0.4), 4.0);
        updateAdminLbTransform();
    }, { passive: false });

    adminStage.addEventListener('mousedown', (e) => {
        if (e.target === adminImg || e.target === adminStage) {
            adminLbState.isDragging = true;
            adminLbState.startX = e.clientX - adminLbState.translateX;
            adminLbState.startY = e.clientY - adminLbState.translateY;
        }
    });

    window.addEventListener('mousemove', (e) => {
        if (adminLbState.isDragging) {
            adminLbState.translateX = e.clientX - adminLbState.startX;
            adminLbState.translateY = e.clientY - adminLbState.startY;
            updateAdminLbTransform();
        }
    });

    window.addEventListener('mouseup', () => {
        adminLbState.isDragging = false;
    });
}
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

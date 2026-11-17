<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/FileUploader.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle creating announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    csrf_verify();

    $title = sanitize($_POST['title'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $category = sanitize($_POST['category'] ?? 'general');

    if (empty($title) || empty($content)) {
        $error = "Punan ang pamagat at nilalaman.";
    } else {
        $image_path = null;

        // Handle file upload
        if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploader();
            try {
                $file = [
                    'name' => $_FILES['announcement_image']['name'],
                    'type' => $_FILES['announcement_image']['type'],
                    'tmp_name' => $_FILES['announcement_image']['tmp_name'],
                    'error' => $_FILES['announcement_image']['error'],
                    'size' => $_FILES['announcement_image']['size']
                ];
                $image_path = $uploader->upload($file, 'announcements');
            } catch (Exception $e) {
                $error = "File Upload Error: " . $e->getMessage();
            }
        }

        if (empty($error)) {
            $query = "INSERT INTO announcements (title, content, category, created_by, image_path) 
                      VALUES (:title, :content, :category, :created_by, :image_path)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->bindParam(':image_path', $image_path);

            if ($stmt->execute()) {
                $success = "Matagumpay na nailathala ang anunsyo!";

                // Add audit log
                $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'create_announcement', 'announcements', :details)";
                $log_stmt = $db->prepare($log_query);
                $log_details = "Created announcement: $title";
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':details', $log_details);
                $log_stmt->execute();
            } else {
                $error = "May naganap na error sa paglathala.";
            }
        }
    }
}

// Handle deleting announcement
if (isset($_GET['delete'])) {
    $announcement_id = intval($_GET['delete']);

    // Get image path first
    $img_query = "SELECT image_path FROM announcements WHERE announcement_id = :id";
    $img_stmt = $db->prepare($img_query);
    $img_stmt->bindParam(':id', $announcement_id);
    $img_stmt->execute();
    $img_result = $img_stmt->fetch(PDO::FETCH_ASSOC);

    if ($img_result && !empty($img_result['image_path']) && file_exists($img_result['image_path'])) {
        unlink($img_result['image_path']);
    }

    $query = "DELETE FROM announcements WHERE announcement_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $announcement_id);

    if ($stmt->execute()) {
        $success = "Matagumpay na nabura ang anunsyo.";

        // Add audit log
        $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, old_values) VALUES (:user_id, 'delete_announcement', 'announcements', :details)";
        $log_stmt = $db->prepare($log_query);
        $log_details = "Deleted announcement ID: $announcement_id";
        $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $log_stmt->bindParam(':details', $log_details);
        $log_stmt->execute();
    } else {
        $error = "Hindi nabura ang anunsyo.";
    }
}

// Fetch all announcements
$query = "SELECT a.*, u.first_name, u.last_name 
          FROM announcements a 
          LEFT JOIN users u ON a.created_by = u.user_id 
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check if dark mode is enabled
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
$page_title = 'Manage Announcements';
$active_nav = 'announcements';
require_once __DIR__ . '/includes/admin-head.php';
?>
    <style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gold: #C9A86C;
            --gold-light: #E8D5A8;

            --bg-primary: var(--ref-canvas, #E4EFE7);
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.12);
            --bg-sidebar-active: rgba(255, 255, 255, 0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255, 255, 255, 0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E8ECF2;

            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.12);

            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255, 255, 255, 0.08);
            --input-bg: #FFFFFF;

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
            --bg-sidebar: #0f1629;
            --bg-sidebar-hover: rgba(255, 255, 255, 0.06);
            --bg-sidebar-active: rgba(255, 255, 255, 0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255, 255, 255, 0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;

            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.20);

            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255, 255, 255, 0.05);
            --input-bg: #1a2332;

            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;

            --color-success: #66BB6A;
            --color-warning: #FFA726;
            --color-danger: #EF5350;
            --color-info: #42A5F5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            min-height: 100vh;
            transition: background 0.4s ease, color 0.4s ease;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fade-down {
            animation: fadeInDown 0.6s ease forwards;
        }

        .animate-fade-up {
            animation: fadeInUp 0.6s ease forwards;
        }

        .animate-fade-scale {
            animation: fadeInScale 0.6s ease forwards;
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
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
            font-size: 1.6rem;
            color: var(--text-primary);
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .page-title h1 i {
            color: var(--gold);
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
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }

        .theme-toggle:hover {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            transform: rotate(30deg) scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .top-icon {
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            background: var(--bg-card);
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
            transform: translateY(-2px) scale(1.02);
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
            animation: fadeInDown 0.4s ease;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.12);
            color: var(--color-success);
            border-left-color: var(--color-success);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.12);
            color: var(--color-danger);
            border-left-color: var(--color-danger);
        }

        .alert i {
            font-size: 1.2rem;
        }

        .alert .close-alert {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            font-size: 1.1rem;
        }

        .alert .close-alert:hover {
            opacity: 1;
        }

        /* ========== ANNOUNCEMENTS GRID ========== */
        .announcements-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 28px;
        }

        /* ========== CARD STYLES ========== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 28px 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            position: relative;
        }

        .card .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--gold);
        }

        .card .card-header h3 {
            color: var(--text-primary);
            font-size: 1.15rem;
            font-weight: 700;
        }

        .card .card-header i {
            color: var(--gold);
            font-size: 1.2rem;
        }

        /* ========== FORM ========== */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.82rem;
        }

        .form-group label i {
            color: var(--gold);
            margin-right: 6px;
            width: 18px;
        }

        .form-group label .required {
            color: var(--color-danger);
            margin-left: 2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 140px;
        }

        .form-group .field-hint {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: block;
            margin-top: 4px;
        }

        /* ========== BUTTONS ========== */
        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            transform: scale(0.97);
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-sm:hover {
            background: var(--badge-bg);
            border-color: var(--maroon-light);
        }

        .btn-sm.danger {
            color: var(--color-danger);
            border-color: var(--color-danger);
        }

        .btn-sm.danger:hover {
            background: rgba(244, 67, 54, 0.1);
        }

        /* ========== TABLE ========== */
        .table-wrap {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 10px 12px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
            font-weight: 700;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .table tr:hover td {
            background: var(--badge-bg);
        }

        .table .title-cell {
            font-weight: 600;
            color: var(--text-primary);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-general {
            background: rgba(33, 150, 243, 0.12);
            color: #2196F3;
        }

        .badge-aics {
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon-light);
        }

        .badge-housing {
            background: rgba(0, 150, 136, 0.12);
            color: #009688;
        }

        .badge-appointment {
            background: rgba(255, 152, 0, 0.12);
            color: #FF9800;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            display: block;
            margin-bottom: 16px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .announcements-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .top-bar-actions {
                justify-content: stretch;
                flex-wrap: wrap;
            }

            .card {
                padding: 20px;
            }

            .table td,
            .table th {
                padding: 8px 10px;
            }

            .table .title-cell {
                max-width: 120px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 14px;
            }

            .card {
                padding: 16px;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .top-bar-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .top-bar-actions .theme-toggle,
            .top-bar-actions .top-icon {
                align-self: center;
            }
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-bullhorn" style="color: var(--ref-maroon);"></i> Announcements Management</h1>
        <p>Create, manage, and publish announcements for residents</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <a href="../announcements.php" class="portal-hdr-pill" target="_blank" title="View Public Announcements Page" style="background: var(--ref-card); border: 1px solid var(--ref-border); font-size: 0.78rem;">
            <i class="fas fa-external-link-alt"></i>
            <span>View Public Board</span>
        </a>
    </div>
</div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Announcements Grid -->
            <div class="announcements-grid">
                <!-- Create Form Card -->
                <div class="card animate-fade-up delay-1">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Create New Announcement</h3>
                    </div>
                    <form action="announcements.php" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>

                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                            <input type="text" name="title" placeholder="e.g., New AICS Schedule" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Category <span class="required">*</span></label>
                            <select name="category" required>
                                <option value="general">General</option>
                                <option value="aics">AICS Program</option>
                                <option value="housing">Housing Program</option>
                                <option value="appointment">Appointments</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Banner Image</label>
                            <input type="file" name="announcement_image" accept="image/*">
                            <span class="field-hint">Accepts JPG, PNG, GIF (Max: 5MB)</span>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Content <span class="required">*</span></label>
                            <textarea name="content" placeholder="Write the full announcement here..."
                                required></textarea>
                        </div>

                        <button type="submit" name="create_announcement" class="btn-primary">
                            <i class="fas fa-bullhorn"></i> Publish Now
                        </button>
                    </form>
                </div>

                <!-- Announcements List Card -->
                <div class="card animate-fade-up delay-2">
                    <div class="card-header">
                        <i class="fas fa-list"></i>
                        <h3>Current Announcements</h3>
                        <span
                            style="margin-left: auto; font-size: 0.7rem; color: var(--text-muted); background: var(--badge-bg); padding: 2px 12px; border-radius: 20px;">
                            <?php echo count($announcements); ?> total
                        </span>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements yet.<br>Create your first announcement using the form.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $item): ?>
                                        <tr>
                                            <td class="title-cell" title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo htmlspecialchars($item['category']); ?>">
                                                    <?php echo strtoupper(htmlspecialchars($item['category'])); ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 0.78rem;">
                                                <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="announcements.php?delete=<?php echo $item['announcement_id']; ?>"
                                                    class="btn-sm danger"
                                                    onclick="return confirm('Are you sure you want to delete this announcement?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
        </div>
    </div>

    <script>
        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Ctrl + N to create new announcement (focus on title)
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                const titleInput = document.querySelector('input[name="title"]');
                if (titleInput) {
                    titleInput.focus();
                    titleInput.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>



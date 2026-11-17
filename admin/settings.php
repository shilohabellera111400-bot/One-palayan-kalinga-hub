<?php
// admin/settings.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle system settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_general'])) {
        $site_name = sanitize($_POST['site_name']);
        $site_description = sanitize($_POST['site_description']);
        $contact_email = sanitize($_POST['contact_email']);
        $contact_phone = sanitize($_POST['contact_phone']);
        $address = sanitize($_POST['address']);
        $max_slots_per_timeslot = max(1, (int)($_POST['max_slots_per_timeslot'] ?? 5));
        
        // Update settings in database
        $update_query = "UPDATE system_settings SET 
                         setting_value = CASE setting_key
                             WHEN 'site_name' THEN :site_name
                             WHEN 'site_description' THEN :site_description
                             WHEN 'contact_email' THEN :contact_email
                             WHEN 'contact_phone' THEN :contact_phone
                             WHEN 'address' THEN :address
                         END
                         WHERE setting_key IN ('site_name', 'site_description', 'contact_email', 'contact_phone', 'address')";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':site_name', $site_name);
        $update_stmt->bindParam(':site_description', $site_description);
        $update_stmt->bindParam(':contact_email', $contact_email);
        $update_stmt->bindParam(':contact_phone', $contact_phone);
        $update_stmt->bindParam(':address', $address);
        
        if ($update_stmt->execute()) {
            // Also update max_slots_per_timeslot
            $slot_update = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('max_slots_per_timeslot', :val) ON DUPLICATE KEY UPDATE setting_value = :val2");
            $slot_str = (string)$max_slots_per_timeslot;
            $slot_update->bindParam(':val',  $slot_str);
            $slot_update->bindParam(':val2', $slot_str);
            $slot_update->execute();
            $success = "General settings updated successfully!";
        } else {
            $error = "Failed to update general settings.";
        }
    }

    
    if (isset($_POST['update_maintenance'])) {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $maintenance_message = sanitize($_POST['maintenance_message']);
        
        $update_query = "UPDATE system_settings SET setting_value = :value WHERE setting_key = :key";
        
        $stmt1 = $db->prepare($update_query);
        $stmt1->bindParam(':value', $maintenance_mode);
        $stmt1->bindParam(':key', 'maintenance_mode');
        $stmt1->execute();
        
        $stmt2 = $db->prepare($update_query);
        $stmt2->bindParam(':value', $maintenance_message);
        $stmt2->bindParam(':key', 'maintenance_message');
        $stmt2->execute();
        
        $success = "Maintenance settings updated successfully!";
    }

    if (isset($_POST['update_openai_settings'])) {
        $openai_key = trim($_POST['openai_api_key'] ?? '');
        $openai_model = trim($_POST['openai_model'] ?? 'gpt-4o-mini');
        $openai_enabled = isset($_POST['openai_helpdesk_enabled']) ? '1' : '0';
        $openai_prompt = trim($_POST['openai_system_prompt'] ?? '');

        $keys_to_update = [
            'openai_api_key' => $openai_key,
            'openai_model' => $openai_model,
            'openai_helpdesk_enabled' => $openai_enabled,
            'openai_system_prompt' => $openai_prompt
        ];

        foreach ($keys_to_update as $k => $v) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
            $stmt->execute([':k' => $k, ':v' => $v, ':v2' => $v]);
        }
        $success = "OpenAI Helpdesk AI configuration saved successfully!";
    }
}

// Get current settings
$settings = [];
$query = "SELECT setting_key, setting_value FROM system_settings";
$result = $db->query($query);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default settings if not set
$default_settings = [
    'site_name' => 'One Palayan-Kalinga Hub',
    'site_description' => 'Integrated Social Services Platform for Palayan City Residents',
    'contact_email' => 'cswdo@palayancity.gov.ph',
    'contact_phone' => '+63 (44) 123 4567',
    'address' => 'City Social Welfare and Development Office, Palayan City, Nueva Ecija',
    'maintenance_mode' => 0,
    'maintenance_message' => 'The system is currently under maintenance. Please check back later.',
    'appointment_lead_days' => 30,
    'max_file_size' => 5,
    'allowed_file_types' => 'pdf,jpg,jpeg,png'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get database info for system info
$dbname_query = $db->query("SELECT DATABASE() as dbname");
$dbname = $dbname_query->fetch(PDO::FETCH_ASSOC);

$size_query = "SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = :dbname";
$size_stmt = $db->prepare($size_query);
$size_stmt->bindParam(':dbname', $dbname['dbname']);
$size_stmt->execute();
$db_size = $size_stmt->fetch(PDO::FETCH_ASSOC);
$size_mb = round($db_size['size'] / 1024 / 1024, 2);

// Get table count
$table_query = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = :dbname";
$table_stmt = $db->prepare($table_query);
$table_stmt->bindParam(':dbname', $dbname['dbname']);
$table_stmt->execute();
$table_count = $table_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get user count
$user_query = "SELECT COUNT(*) as count FROM users";
$user_count = $db->query($user_query)->fetch(PDO::FETCH_ASSOC)['count'];

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Check for dark mode preference
$page_title = 'System Settings';
$active_sidebar = 'backup';
require_once __DIR__ . '/includes/admin-head.php';
?>
<style>
        /* ========== CSS VARIABLES - LIGHT/DARK THEME ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            
            --bg-primary: var(--ref-canvas, #E4EFE7);
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #7F0303;
            --bg-sidebar-hover: rgba(255,255,255,0.12);
            --bg-sidebar-active: rgba(255,255,255,0.15);
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --text-sidebar: rgba(255,255,255,0.7);
            --text-sidebar-active: #FFFFFF;
            --border-color: #E8ECF2;
            
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.12);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --card-bg: #FFFFFF;
            --badge-bg: #F1F3F8;
            --divider: rgba(255,255,255,0.08);
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
            --bg-sidebar-hover: rgba(255,255,255,0.06);
            --bg-sidebar-active: rgba(255,255,255,0.10);
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --text-sidebar: rgba(255,255,255,0.6);
            --text-sidebar-active: #FFFFFF;
            --border-color: #2a3a4f;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.40);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.50);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.60);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.25);
            
            --card-bg: #1a2332;
            --badge-bg: #2a3a4f;
            --divider: rgba(255,255,255,0.05);
            --input-bg: #1a2332;
            
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
        .search-box {
            display: flex;
            align-items: center;
            background: var(--card-bg);
            border-radius: 50px;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            gap: 10px;
            transition: background 0.3s ease, border-color 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        .search-box input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: transparent;
            min-width: 180px;
            color: var(--text-primary);
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { color: var(--text-muted); }

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
        .alert i { font-size: 1.2rem; }

        /* ========== STATS ROW ========== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-item {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        .stat-item .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-item .icon.db { background: rgba(33, 150, 243, 0.12); color: var(--color-info); }
        .stat-item .icon.users { background: rgba(76, 175, 80, 0.12); color: var(--color-success); }
        .stat-item .icon.tables { background: rgba(255, 152, 0, 0.12); color: var(--color-warning); }
        .stat-item .icon.size { background: rgba(127, 3, 3, 0.12); color: var(--maroon-light); }
        .stat-item .info h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .stat-item .info p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ========== SETTINGS GRID ========== */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .settings-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        .settings-card .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--maroon);
        }
        .settings-card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .settings-card .card-header i {
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
        .form-group label i {
            color: var(--maroon-light);
            margin-right: 6px;
        }
        .form-group input:not([type="checkbox"]):not([type="radio"]),
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            transition: all 0.3s;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        .form-group input:not([type="checkbox"]):not([type="radio"]):focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--maroon-light);
            box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.1);
        }
        .form-group input:not([type="checkbox"]):not([type="radio"]):disabled,
        .form-group select:disabled,
        .form-group textarea:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-group small {
            display: block;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        .checkbox-group {
            display: inline-flex !important;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: rgba(127, 3, 3, 0.03);
            border: 1.5px solid rgba(127, 3, 3, 0.15);
            border-radius: 12px;
            width: fit-content !important;
            cursor: pointer;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        .checkbox-group:hover {
            background: rgba(127, 3, 3, 0.06);
            border-color: var(--maroon);
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px !important;
            height: 20px !important;
            min-width: 20px;
            max-width: 20px;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            accent-color: var(--maroon);
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-group label {
            margin: 0 !important;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.88rem;
            white-space: nowrap;
        }

        .btn-primary {
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
        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-success {
            background: var(--color-success);
            color: white;
        }
        .btn-success:hover {
            background: #388E3C;
        }
        .btn-warning {
            background: var(--color-warning);
            color: white;
        }
        .btn-warning:hover {
            background: #F57C00;
        }
        .btn-danger {
            background: var(--color-danger);
            color: white;
        }
        .btn-danger:hover {
            background: #C62828;
        }

        .info-box {
            background: var(--badge-bg);
            padding: 14px 18px;
            border-radius: var(--radius-element);
            margin-top: 16px;
            border-left: 4px solid var(--color-info);
        }
        .info-box p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .info-box p i {
            color: var(--color-info);
            margin-right: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        /* Full width card */
        .full-width {
            grid-column: 1 / -1;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .main-content { margin-left: 0; padding: 20px; }
            .settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: stretch; flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { min-width: 120px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .settings-card { padding: 18px 20px; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-primary { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-row { grid-template-columns: 1fr; }
            .settings-card { padding: 14px 16px; }
        }
    </style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
        <p>Configure system-wide settings, platform preferences, and database controls</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <span class="badge" style="background:rgba(127,3,3,0.1);color:var(--dash-maroon);font-weight:700;padding:6px 14px;border-radius:50px;border:1px solid rgba(127,3,3,0.2);">
            <i class="fas fa-database"></i> <?php echo $size_mb; ?> MB DB Size
        </span>
    </div>
</div>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Compact Stats Grid -->
        <div class="compact-stats-grid" style="margin-bottom: 24px;">
            <div class="stat-pill-card">
                <div class="stat-pill-icon sapphire"><i class="fas fa-server"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val" style="font-size:0.95rem;"><?php echo htmlspecialchars($dbname['dbname']); ?></span>
                    <span class="stat-pill-lbl">Database Name</span>
                </div>
            </div>
            <div class="stat-pill-card">
                <div class="stat-pill-icon emerald"><i class="fas fa-table"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $table_count; ?></span>
                    <span class="stat-pill-lbl">Tables</span>
                </div>
            </div>
            <div class="stat-pill-card">
                <div class="stat-pill-icon maroon"><i class="fas fa-users"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $user_count; ?></span>
                    <span class="stat-pill-lbl">Total Users</span>
                </div>
            </div>
            <div class="stat-pill-card">
                <div class="stat-pill-icon amber"><i class="fas fa-database"></i></div>
                <div class="stat-pill-data">
                    <span class="stat-pill-val"><?php echo $size_mb; ?> MB</span>
                    <span class="stat-pill-lbl">Database Size</span>
                </div>
            </div>
        </div>
        
        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- General Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-globe"></i>
                    <h2>General Settings</h2>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Site Name</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Site Description</label>
                        <textarea name="site_description" rows="2"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Contact Email</label>
                        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Contact Phone</label>
                        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Office Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-calendar-check"></i> Max Appointments Per Day</label>
                        <input type="number" name="max_appointments_per_day" value="<?php echo intval($settings['max_appointments_per_day'] ?? 15); ?>" min="1" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Max Slots Per Time Slot</label>
                        <input type="number" name="max_slots_per_timeslot" value="<?php echo intval($settings['max_slots_per_timeslot'] ?? 5); ?>" min="1" max="50" required>
                        <small style="color:var(--text-muted);font-size:.8rem;margin-top:4px;display:block;">How many residents can book the same time slot (e.g., 9:00 AM) on the same day. Default: 5</small>
                    </div>
                    
                    <button type="submit" name="update_general" class="btn-primary">
                        <i class="fas fa-save"></i> Save General Settings
                    </button>
                </form>
            </div>
            
            <!-- Maintenance Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-tools"></i>
                    <h2>Maintenance Mode</h2>
                </div>
                <form method="POST">
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] == 1 ? 'checked' : ''; ?>>
                        <label for="maintenance_mode">Enable Maintenance Mode</label>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> Maintenance Message</label>
                        <textarea name="maintenance_message" rows="3" id="maintenance_message"><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                        <small>This message will be shown to users when maintenance mode is enabled</small>
                    </div>
                    
                    <button type="submit" name="update_maintenance" class="btn-primary">
                        <i class="fas fa-save"></i> Save Maintenance Settings
                    </button>
                </form>
                
                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> <strong>Note:</strong> When maintenance mode is enabled, only administrators can access the system. Regular users will see the maintenance message.</p>
                </div>
            </div>
            
            <!-- OpenAI Helpdesk AI Settings -->
            <div class="settings-card" style="grid-column: 1 / -1;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-robot" style="color: var(--ref-gold);"></i>
                        <h2>OpenAI Helpdesk AI Integration</h2>
                    </div>
                    <span style="font-size: 0.75rem; background: rgba(16,185,129,0.12); color: #059669; padding: 4px 10px; border-radius: 50px; font-weight: 700;">
                        Lingkod Kalinga AI Assistant
                    </span>
                </div>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; margin-bottom: 16px;">
                        <div class="form-group checkbox-group" style="margin-bottom: 0; align-items: center;">
                            <input type="checkbox" name="openai_helpdesk_enabled" id="openai_helpdesk_enabled" value="1" <?php echo ($settings['openai_helpdesk_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label for="openai_helpdesk_enabled" style="font-weight: 700; color: var(--ref-text);">
                                Enable Automated AI Initial Guidance on Helpdesk
                            </label>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-microchip"></i> OpenAI Model</label>
                            <select name="openai_model" id="openai_model" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card); color: var(--text-color);">
                                <option value="gpt-4o-mini" <?php echo ($settings['openai_model'] ?? 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : ''; ?>>gpt-4o-mini (Recommended - Fast & Cost Efficient)</option>
                                <option value="gpt-4o" <?php echo ($settings['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : ''; ?>>gpt-4o (High Intelligence Flagship)</option>
                                <option value="gpt-3.5-turbo" <?php echo ($settings['openai_model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>gpt-3.5-turbo (Legacy Fast)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> OpenAI API Secret Key</label>
                        <div style="position: relative;">
                            <input type="password" name="openai_api_key" id="openai_api_key" value="<?php echo htmlspecialchars($settings['openai_api_key'] ?? ''); ?>" placeholder="sk-proj-..." style="padding-right: 90px; width: 100%;">
                            <button type="button" onclick="toggleApiKeyVisibility()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--ref-muted); cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                                <i class="fas fa-eye" id="toggleKeyIcon"></i> Show
                            </button>
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                            Enter your platform secret key from OpenAI. If left empty or during offline periods, the Helpdesk automatically uses the municipal CSWDO Knowledge Engine fallback with 100% uptime.
                        </small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-brain"></i> Custom AI System Prompt / Guidance Persona</label>
                        <textarea name="openai_system_prompt" rows="3" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; font-family: inherit; font-size: 0.85rem;"><?php echo htmlspecialchars($settings['openai_system_prompt'] ?? ''); ?></textarea>
                        <small style="color: var(--text-muted); font-size: 0.78rem; margin-top: 4px; display: block;">
                            Directs the tone, municipal context, and language guidelines (English/Tagalog/Taglish) for Lingkod Kalinga AI.
                        </small>
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px; flex-wrap: wrap;">
                        <button type="submit" name="update_openai_settings" class="btn-primary">
                            <i class="fas fa-save"></i> Save AI Helpdesk Settings
                        </button>
                        <button type="button" class="btn-secondary" onclick="testOpenAiConnection()" id="btnTestOpenAi">
                            <i class="fas fa-plug"></i> Test API Connection
                        </button>
                        <span id="testApiResult" style="font-size: 0.82rem; font-weight: 700; display: none;"></span>
                    </div>
                </form>

                <div class="info-box" style="margin-top: 18px;">
                    <p><i class="fas fa-shield-alt"></i> <strong>Important Compliance Rule:</strong> AI responses provide immediate preliminary guidance and document preparation steps to assist residents right away. Formal casework verification, fund disbursements, card issuance, and binding official actions remain strictly governed by licensed CSWDO Staff Officers.</p>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-server"></i>
                    <h2>System Information</h2>
                </div>
                <div class="form-group">
                    <label><i class="fab fa-php"></i> PHP Version</label>
                    <input type="text" value="<?php echo phpversion(); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-database"></i> MySQL Version</label>
                    <?php
                    $mysql_version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
                    ?>
                    <input type="text" value="<?php echo $mysql_version; ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-laptop"></i> Server Software</label>
                    <input type="text" value="<?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-upload"></i> Upload Max File Size</label>
                    <input type="text" value="<?php echo ini_get('upload_max_filesize'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Server Time</label>
                    <input type="text" value="<?php echo date('Y-m-d H:i:s'); ?>" disabled>
                </div>
            </div>
            
            <!-- File Upload & App Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-upload"></i>
                    <h2>Upload & App Settings</h2>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-file-alt"></i> Allowed File Types</label>
                    <input type="text" value="<?php echo $settings['allowed_file_types']; ?>" disabled>
                    <small>Allowed file extensions for uploads</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-weight-hanging"></i> Max File Size</label>
                    <input type="text" value="<?php echo $settings['max_file_size']; ?> MB" disabled>
                    <small>Maximum file size for uploads</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-week"></i> Appointment Lead Days</label>
                    <input type="text" value="<?php echo $settings['appointment_lead_days']; ?> days" disabled>
                    <small>Maximum days in advance for booking appointments</small>
                </div>
                
                <div class="info-box">
                    <p><i class="fas fa-shield-alt"></i> <strong>Security Notice:</strong> All uploaded files are scanned and stored securely. File paths are encrypted in the database.</p>
                </div>
            </div>
            
            <!-- Database Management - Full Width -->
            <div class="settings-card full-width">
                <div class="card-header">
                    <i class="fas fa-database"></i>
                    <h2>Database Management</h2>
                </div>
                <div class="action-buttons">
                    <a href="backup.php" class="btn-primary btn-success">
                        <i class="fas fa-download"></i> Backup Database
                    </a>
                    <a href="optimize-db.php" class="btn-primary btn-warning">
                        <i class="fas fa-chart-line"></i> Optimize Database
                    </a>
                    <button onclick="if(confirm('Are you sure you want to clear all cache? This action cannot be undone.')) window.location.href='clear-cache.php';" class="btn-primary btn-danger">
                        <i class="fas fa-trash"></i> Clear Cache
                    </button>
                </div>
                <div class="info-box" style="margin-top: 16px;">
                    <p><i class="fas fa-clock"></i> <strong>Last Backup:</strong> <?php echo date('F d, Y h:i A'); ?></p>
                    <p><i class="fas fa-chart-line"></i> <strong>Database Size:</strong> <?php echo $size_mb; ?> MB</p>
                    <p><i class="fas fa-table"></i> <strong>Total Tables:</strong> <?php echo $table_count; ?></p>
                </div>
            </div>
        </div>

<script>
// ========== MAINTENANCE MODE TOGGLE ==========
const maintenanceCheckbox = document.getElementById('maintenance_mode');
const maintenanceMessage = document.getElementById('maintenance_message');

if (maintenanceCheckbox) {
    maintenanceCheckbox.addEventListener('change', function() {
        if (maintenanceMessage) {
            maintenanceMessage.disabled = !this.checked;
            if (!this.checked) {
                maintenanceMessage.style.opacity = '0.6';
            } else {
                maintenanceMessage.style.opacity = '1';
            }
        }
    });
    // Initialize state
    if (maintenanceMessage) {
        maintenanceMessage.disabled = !maintenanceCheckbox.checked;
        maintenanceMessage.style.opacity = maintenanceCheckbox.checked ? '1' : '0.6';
    }
}

// ========== SEARCH ==========
document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim().toLowerCase();
        const cards = document.querySelectorAll('.settings-card');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(query) ? '' : 'none';
        });
    }
});
</script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>



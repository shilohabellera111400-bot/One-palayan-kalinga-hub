<?php
// staff/appointment-settings.php - Social Services Slot Capacity & Schedule Configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isStaff() && !isAdmin()) {
    redirect('../dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $slot_duration = max(15, min(120, intval($_POST['slot_duration_minutes'] ?? 30)));
    $max_capacity = max(1, min(50, intval($_POST['max_capacity_per_slot'] ?? 5)));
    $start_hour = sanitize($_POST['start_hour'] ?? '08:00:00');
    $end_hour = sanitize($_POST['end_hour'] ?? '17:00:00');
    $lunch_start = sanitize($_POST['lunch_break_start'] ?? '12:00:00');
    $lunch_end = sanitize($_POST['lunch_break_end'] ?? '13:00:00');
    $advance_days = max(1, min(90, intval($_POST['advance_booking_days'] ?? 30)));
    $max_per_user = max(1, min(5, intval($_POST['max_daily_per_user'] ?? 1)));
    $operating_days = isset($_POST['operating_days']) && is_array($_POST['operating_days']) 
        ? implode(',', array_map('sanitize', $_POST['operating_days'])) 
        : 'Mon,Tue,Wed,Thu,Fri';

    // Ensure format HH:MM:00
    if (strlen($start_hour) == 5) $start_hour .= ':00';
    if (strlen($end_hour) == 5) $end_hour .= ':00';
    if (strlen($lunch_start) == 5) $lunch_start .= ':00';
    if (strlen($lunch_end) == 5) $lunch_end .= ':00';

    try {
        $update = $db->prepare("
            UPDATE appointment_settings 
            SET slot_duration_minutes = :slot_duration,
                max_clients_per_slot = :max_capacity,
                office_hours_start = :start_hour,
                office_hours_end = :end_hour,
                break_start = :lunch_start,
                break_end = :lunch_end,
                available_days = :operating_days
            WHERE setting_id = 1
        ");
        $update->execute([
            ':slot_duration' => $slot_duration,
            ':max_capacity' => $max_capacity,
            ':start_hour' => $start_hour,
            ':end_hour' => $end_hour,
            ':lunch_start' => $lunch_start,
            ':lunch_end' => $lunch_end,
            ':operating_days' => $operating_days
        ]);

        $success = "Appointment capacity and schedule settings saved successfully!";
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
$settings = getAppointmentSettings($db);
$selected_days = explode(',', $settings['operating_days'] ?? 'Mon,Tue,Wed,Thu,Fri');

// Staff Info
$staff_id_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :uid";
$staff_stmt = $db->prepare($staff_id_query);
$staff_stmt->execute([':uid' => $_SESSION['user_id']]);
$staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info['id'] ?? 0;

$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute([':staff_id' => $staff_id]);
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) ? '../' . $profile_picture : '';

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<?php
$page_title = 'Appointment Slot Capacity Settings - Staff Panel';
$active_nav = 'appointments';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
:root {
    --card-bg: var(--ref-card);
    --bg-primary: var(--ref-card-subtle);
    --text-primary: var(--ref-text);
    --text-secondary: var(--ref-text);
    --text-muted: var(--ref-muted);
    --border-color: var(--ref-border);
    --maroon: var(--ref-maroon);
    --badge-bg: var(--ref-card-subtle);
    --radius-card: 20px;
    --radius-element: 10px;
}
        .settings-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            max-width: 820px;
            margin: 0 auto 30px auto;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 650px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }
        .form-section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 24px 0 14px 0;
            padding-bottom: 8px;
            border-bottom: 1.5px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title:first-of-type { margin-top: 0; }
        .form-field {
            margin-bottom: 16px;
        }
        .form-field label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-field input, .form-field select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.88rem;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .form-field input:focus, .form-field select:focus {
            outline: none;
            border-color: var(--maroon);
        }
        .form-field .helper {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
            display: block;
        }

        .days-checkbox-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .day-pill-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--badge-bg);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .day-pill-label input[type="checkbox"] {
            accent-color: var(--maroon);
        }

        .btn-save {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            padding: 12px 28px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-save:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="app-container">
        <!-- Modern Staff Sidebar -->
        <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Unified Staff Header Bar -->
            <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

            <!-- Elevated Big Card Container -->
            <div class="staff-big-card">
                <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Appointment Capacity & Slot Rules';
            $banner_subtitle = 'Configure public booking office hours, slot duration, capacity limits, and operating weekdays';
            $banner_icon = 'fa-sliders-h';
            $banner_actions = [
                ['label' => 'Appointment Queue', 'url' => 'manage-appointments.php', 'icon' => 'fa-calendar-check', 'type' => 'primary'],
                ['label' => 'Live QR Scanner', 'url' => 'qr-scanner.php', 'icon' => 'fa-qrcode', 'type' => 'secondary']
            ];
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <?php if (!empty($success)): ?>
            <div style="background: #ECFDF5; border-left: 4px solid #10B981; color: #065F46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div style="background: #FEF2F2; border-left: 4px solid #EF4444; color: #991B1B; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.88rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="settings-card">
                <form method="POST" action="">
                    <!-- Office Hours & Durations -->
                    <div class="form-section-title">
                        <i class="fas fa-clock" style="color: var(--maroon);"></i> Office Hours & Time Slots
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label>Intake Opening Time</label>
                            <input type="time" name="start_hour" value="<?php echo substr($settings['start_hour'], 0, 5); ?>" required>
                            <span class="helper">Earliest appointment time offered to residents (Default: 08:00 AM)</span>
                        </div>
                        <div class="form-field">
                            <label>Intake Closing Time</label>
                            <input type="time" name="end_hour" value="<?php echo substr($settings['end_hour'], 0, 5); ?>" required>
                            <span class="helper">Latest appointment concluding time (Default: 05:00 PM)</span>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label>Lunch Break Start</label>
                            <input type="time" name="lunch_break_start" value="<?php echo substr($settings['lunch_break_start'], 0, 5); ?>" required>
                            <span class="helper">No public appointments booked during lunch break (Default: 12:00 PM)</span>
                        </div>
                        <div class="form-field">
                            <label>Lunch Break End</label>
                            <input type="time" name="lunch_break_end" value="<?php echo substr($settings['lunch_break_end'], 0, 5); ?>" required>
                            <span class="helper">Appointments resume at this hour (Default: 01:00 PM)</span>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label>Time Slot Duration (Minutes)</label>
                            <select name="slot_duration_minutes">
                                <option value="15" <?php echo ($settings['slot_duration_minutes'] == 15) ? 'selected' : ''; ?>>15 Minutes (Express)</option>
                                <option value="20" <?php echo ($settings['slot_duration_minutes'] == 20) ? 'selected' : ''; ?>>20 Minutes</option>
                                <option value="30" <?php echo ($settings['slot_duration_minutes'] == 30) ? 'selected' : ''; ?>>30 Minutes (Recommended)</option>
                                <option value="45" <?php echo ($settings['slot_duration_minutes'] == 45) ? 'selected' : ''; ?>>45 Minutes</option>
                                <option value="60" <?php echo ($settings['slot_duration_minutes'] == 60) ? 'selected' : ''; ?>>60 Minutes (1 Hour)</option>
                            </select>
                            <span class="helper">Interval between appointment windows</span>
                        </div>
                        <div class="form-field">
                            <label>Max Citizens per Time Slot</label>
                            <input type="number" name="max_capacity_per_slot" min="1" max="50" value="<?php echo htmlspecialchars($settings['max_capacity_per_slot']); ?>" required>
                            <span class="helper">Capacity limit per interval (e.g. 5 clients per 30-min window)</span>
                        </div>
                    </div>

                    <!-- Policy Controls -->
                    <div class="form-section-title">
                        <i class="fas fa-shield-alt" style="color: var(--maroon);"></i> Booking Restrictions & Duplicate Safeguards
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label>Advance Booking Window (Days)</label>
                            <input type="number" name="advance_booking_days" min="1" max="90" value="<?php echo htmlspecialchars($settings['advance_booking_days']); ?>" required>
                            <span class="helper">How far in advance residents can choose appointment dates</span>
                        </div>
                        <div class="form-field">
                            <label>Max Daily Bookings per Citizen</label>
                            <input type="number" name="max_daily_per_user" min="1" max="5" value="<?php echo htmlspecialchars($settings['max_daily_per_user']); ?>" required>
                            <span class="helper">Prevents appointment hoarding on the same calendar day (Default: 1)</span>
                        </div>
                    </div>

                    <!-- Operating Weekdays -->
                    <div class="form-section-title">
                        <i class="fas fa-calendar-week" style="color: var(--maroon);"></i> Operating Weekdays
                    </div>
                    <div class="form-field">
                        <label>Select working days available for citizen scheduling:</label>
                        <div class="days-checkbox-group">
                            <?php 
                            $weekdays = [
                                'Mon' => 'Monday',
                                'Tue' => 'Tuesday',
                                'Wed' => 'Wednesday',
                                'Thu' => 'Thursday',
                                'Fri' => 'Friday',
                                'Sat' => 'Saturday (Special)',
                                'Sun' => 'Sunday'
                            ];
                            foreach($weekdays as $code => $lbl):
                                $checked = in_array($code, $selected_days) ? 'checked' : '';
                            ?>
                            <label class="day-pill-label">
                                <input type="checkbox" name="operating_days[]" value="<?php echo $code; ?>" <?php echo $checked; ?>>
                                <?php echo $lbl; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="submit" name="save_settings" class="btn-save">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div> <!-- Close settings-card -->
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

<?php
// admin/manage-blackout.php
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db       = $database->getConnection();

$error   = '';
$success = '';

// Handle POST: add or delete blackout date
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['add_blackout'])) {
        $date   = sanitize($_POST['blackout_date'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');
        if (!$date || !$reason) {
            $error = 'Ang petsa at dahilan ay kinakailangan.';
        } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
            $error = 'Hindi maaaring mag-block ng nakaraang petsa.';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO blackout_dates (blackout_date, reason, created_by)
                 VALUES (:date, :reason, :uid)
                 ON DUPLICATE KEY UPDATE reason = :reason2, created_by = :uid2"
            );
            $stmt->bindParam(':date',    $date);
            $stmt->bindParam(':reason',  $reason);
            $stmt->bindParam(':uid',     $_SESSION['user_id']);
            $stmt->bindParam(':reason2', $reason);
            $stmt->bindParam(':uid2',    $_SESSION['user_id']);
            if ($stmt->execute()) {
                $success = "Naidagdag na ang blackout date: " . date('F d, Y', strtotime($date)) . " ({$reason}).";

                // Broadcast notification to all active residents
                try {
                    $notif_title = "Blackout Date / Walang Pasok: " . date('F d, Y', strtotime($date));
                    $notif_message = "Paunawa sa lahat ng Residente: Ang petsang " . date('F d, Y', strtotime($date)) . " ay opisyal na idineklarang non-working day / blackout date sa CSWDO Palayan City ({$reason}). Pansamantalang walang appointment bookings o opisina sa araw na ito.";
                    $n_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) SELECT user_id, :title, :msg, 'system', 0, NOW() FROM users WHERE role = 'resident' AND is_active = 1");
                    $n_stmt->execute([':title' => $notif_title, ':msg' => $notif_message]);
                } catch (Exception $ne) {}

                // Audit log
                try {
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'create_blackout', 'blackout_dates', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_details = "Added blackout date: $date ($reason)";
                    $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $log_stmt->bindParam(':details', $log_details);
                    $log_stmt->execute();
                } catch (Exception $ae) {}
            } else {
                $error = 'Hindi ma-save ang blackout date. Subukang muli.';
            }
        }
    }

    if (isset($_POST['delete_blackout'])) {
        $id = (int) ($_POST['blackout_id'] ?? 0);
        if ($id) {
            $del = $db->prepare("DELETE FROM blackout_dates WHERE id = :id");
            $del->bindParam(':id', $id);
            $del->execute();
            $success = 'Natanggal na ang blackout date.';
        }
    }
}

// Fetch existing blackout dates
$list_stmt = $db->query("SELECT * FROM blackout_dates ORDER BY blackout_date ASC");
$blackouts = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$initials   = '';
$name_parts = explode(' ', $_SESSION['full_name'] ?? '');
foreach ($name_parts as $part) { $initials .= strtoupper(substr($part, 0, 1)); }
$initials   = substr($initials, 0, 2);
$dark_mode  = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
$page_title = 'Blackout Dates';
$active_sidebar = 'blackout';
require_once __DIR__ . '/includes/admin-head.php';
?>
<style>
.form-row { display: grid; grid-template-columns: 1fr 2fr auto; gap: 14px; align-items: end; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; color: var(--ref-muted); margin-bottom: 6px; }
.form-group input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--ref-border); border-radius: 12px; background: var(--ref-canvas); color: var(--ref-text); font-size: .9rem; }
.form-group input:focus { outline: none; border-color: var(--ref-maroon); }
.empty-state { text-align: center; padding: 40px; color: var(--ref-muted); }
.empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: .4; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
</style>
<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-ban" style="color: var(--ref-maroon);"></i> Blackout Dates Manager</h1>
        <p>Manage holidays and non-working days to restrict appointment bookings</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <a href="calendar.php" class="portal-hdr-pill" style="background: var(--ref-card); border: 1px solid var(--ref-border); font-size: 0.78rem;">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendar Overview</span>
        </a>
    </div>
</div>


    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Add Form -->
    <div class="card">
        <h2><i class="fas fa-plus-circle"></i> Add Blackout Date</h2>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="blackout_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Reason / Holiday Name *</label>
                    <input type="text" name="reason" placeholder="e.g., National Heroes Day, Holy Friday..." required maxlength="255">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" name="add_blackout" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </form>
    </div>

    <!-- List -->
    <div class="card">
        <h2><i class="fas fa-list"></i> Current Blackout Dates (<?php echo count($blackouts); ?>)</h2>
        <?php if (empty($blackouts)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>Walang blackout dates na naka-set. Lahat ng araw ay available para sa booking.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Reason</th>
                        <th>Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blackouts as $b): ?>
                    <tr>
                        <td><strong><?php echo date('F d, Y', strtotime($b['blackout_date'])); ?></strong></td>
                        <td><span class="badge"><?php echo date('l', strtotime($b['blackout_date'])); ?></span></td>
                        <td><?php echo htmlspecialchars($b['reason']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Tanggalin ang blackout date na ito?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="blackout_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" name="delete_blackout" class="btn btn-danger"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
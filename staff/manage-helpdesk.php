<?php
// staff/manage-helpdesk.php - Helpdesk, Inquiries & Citizen Concerns Management Hub
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/OpenAIHelper.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff() && !isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for sidebar display
$staff_id_query = "SELECT s.id, s.position, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? $staff_info['id'] : 0;

// Ensure feedback_replies table and required feedback columns exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `feedback_replies` (
        `reply_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `feedback_id` int(11) NOT NULL,
        `user_id` int(11) DEFAULT NULL,
        `sender_type` enum('resident','staff','ai') NOT NULL DEFAULT 'resident',
        `message` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`feedback_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = $db->query("SHOW COLUMNS FROM feedback")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ticket_number', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `ticket_number` varchar(50) DEFAULT NULL AFTER `feedback_id`");
    }
    if (!in_array('category', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `category` enum('question','concern','feedback') DEFAULT 'question' AFTER `ticket_number`");
    }
    if (!in_array('subject', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `subject` varchar(255) DEFAULT NULL AFTER `category`");
    }
    if (!in_array('status', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `status` enum('open','ai_responded','under_review','staff_replied','resolved','closed') DEFAULT 'open' AFTER `comment`");
    }
    if (!in_array('ai_response', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `ai_response` text DEFAULT NULL AFTER `status`");
    }
    if (!in_array('ai_responded_at', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `ai_responded_at` datetime DEFAULT NULL AFTER `ai_response`");
    }
    if (!in_array('staff_response', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `staff_response` text DEFAULT NULL AFTER `ai_responded_at`");
    }
    if (!in_array('staff_responded_by', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `staff_responded_by` int(11) DEFAULT NULL AFTER `staff_response`");
    }
    if (!in_array('staff_responded_at', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `staff_responded_at` datetime DEFAULT NULL AFTER `staff_responded_by`");
    }
    if (!in_array('resolution_notes', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `resolution_notes` text DEFAULT NULL AFTER `staff_responded_at`");
    }
    if (!in_array('is_starred', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `is_starred` tinyint(1) DEFAULT 0 AFTER `resolution_notes`");
    }
    if (!in_array('updated_at', $cols)) {
        $db->exec("ALTER TABLE feedback ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
} catch (Throwable $e) {}

$success_msg = '';
$error_msg = '';

// Handle AJAX Fetch of Ticket Thread Details
if (isset($_GET['fetch_ticket_thread'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $tkt_id = intval($_GET['fetch_ticket_thread']);

    try {
        $q = "SELECT f.*, u.first_name, u.last_name, u.email, u.contact_number, r.barangay, r.street,
                     su.first_name as staff_fname, su.last_name as staff_lname, st.position as staff_pos
              FROM feedback f
              LEFT JOIN users u ON f.user_id = u.user_id
              LEFT JOIN residents r ON u.user_id = r.user_id
              LEFT JOIN users su ON f.staff_responded_by = su.user_id
              LEFT JOIN staff st ON su.user_id = st.user_id
              WHERE f.feedback_id = :id";
        $st = $db->prepare($q);
        $st->execute([':id' => $tkt_id]);
        $ticket = $st->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
            exit;
        }

        // Fetch discussion thread
        $replies = [];
        try {
            $rep_stmt = $db->prepare("
                SELECT r.*, u.first_name, u.last_name, u.role
                FROM feedback_replies r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.feedback_id = :id
                ORDER BY r.created_at ASC
            ");
            $rep_stmt->execute([':id' => $tkt_id]);
            $replies = $rep_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $re) {
            $replies = [];
        }

        echo json_encode(['success' => true, 'ticket' => $ticket, 'replies' => $replies]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Staff Official Response & Resolution POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_staff_response'])) {
    csrf_verify();

    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $new_status = sanitize($_POST['status'] ?? 'staff_replied');
    $official_response = trim($_POST['official_response'] ?? '');
    $resolution_notes = trim($_POST['resolution_notes'] ?? '');

    $allowed_statuses = ['under_review', 'staff_replied', 'resolved', 'closed'];
    if (!in_array($new_status, $allowed_statuses)) {
        $new_status = 'staff_replied';
    }

    if (empty($official_response) && $new_status !== 'under_review') {
        $error_msg = "Please write an official response to advise or assist the resident.";
    } else {
        // Fetch ticket details before updating
        $chk_stmt = $db->prepare("SELECT f.*, u.first_name, u.last_name, u.email FROM feedback f JOIN users u ON f.user_id = u.user_id WHERE f.feedback_id = :id");
        $chk_stmt->execute([':id' => $feedback_id]);
        $ticket = $chk_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $error_msg = "Helpdesk ticket record not found.";
        } else {
            $upd_sql = "UPDATE feedback SET 
                            status = :status,
                            staff_response = :staff_resp,
                            staff_responded_by = :staff_uid,
                            staff_responded_at = NOW(),
                            resolution_notes = :res_notes,
                            updated_at = NOW()
                        WHERE feedback_id = :id";
            $upd_stmt = $db->prepare($upd_sql);
            $upd_res = $upd_stmt->execute([
                ':status' => $new_status,
                ':staff_resp' => $official_response,
                ':staff_uid' => $_SESSION['user_id'],
                ':res_notes' => $resolution_notes,
                ':id' => $feedback_id
            ]);

            if ($upd_res) {
                // Insert into conversation thread if response provided
                if (!empty($official_response)) {
                    $rep_stmt = $db->prepare("INSERT INTO feedback_replies (feedback_id, user_id, sender_type, message, created_at) VALUES (:fbid, :uid, 'staff', :msg, NOW())");
                    $rep_stmt->execute([
                        ':fbid' => $feedback_id,
                        ':uid' => $_SESSION['user_id'],
                        ':msg' => $official_response
                    ]);
                }

                // Dispatch resident in-app notification
                try {
                    $notif_title = "Helpdesk Ticket {$ticket['ticket_number']}: CSWDO Official Response";
                    $status_text = ($new_status === 'resolved') ? 'marked as Resolved' : 'updated by CSWDO Officers';
                    $notif_msg = "Your inquiry regarding '{$ticket['subject']}' has been $status_text. Check the response in your portal.";
                    $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (:uid, :title, :msg, 'helpdesk', 0, NOW())");
                    $notif_stmt->execute([
                        ':uid' => $ticket['user_id'],
                        ':title' => $notif_title,
                        ':msg' => $notif_msg
                    ]);
                } catch (Exception $ne) {}

                // Dispatch Email Notification if available
                if (!empty($ticket['email'])) {
                    try {
                        $mailer = new Mailer();
                        $subject_mail = "CSWDO Helpdesk Update - Ticket {$ticket['ticket_number']}";
                        $body_mail = "<h3>Magandang araw po, {$ticket['first_name']}!</h3>"
                                   . "<p>Mayroon pong opisyal na tugon mula sa City Social Welfare and Development Office (CSWDO) ng Palayan City para sa inyong tiket:</p>"
                                   . "<div style='background:#f4f6f8; padding:15px; border-left:4px solid #7F0303; border-radius:6px; margin:15px 0;'>"
                                   . "<strong>Ticket #:</strong> {$ticket['ticket_number']}<br>"
                                   . "<strong>Subject:</strong> " . htmlspecialchars($ticket['subject']) . "<br>"
                                   . "<strong>Status:</strong> " . strtoupper($new_status) . "<br><br>"
                                   . "<strong>Opisyal na Tugon ng Kawani:</strong><br>"
                                   . nl2br(htmlspecialchars($official_response))
                                   . "</div>"
                                   . "<p>Maaari ninyong tingnan ang kumpletong impormasyon at mag-reply sa pamamagitan ng pag-log in sa inyong One Palayan-Kalinga Hub account.</p>";
                        $mailer->send($ticket['email'], $subject_mail, $body_mail);
                    } catch (Exception $me) {}
                }

                // Audit log
                try {
                    $log_query = "INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:user_id, 'staff_helpdesk_response', 'feedback', :details)";
                    $log_stmt = $db->prepare($log_query);
                    $log_details = "Staff responded to Ticket {$ticket['ticket_number']} with status $new_status";
                    $log_stmt->execute([':user_id' => $_SESSION['user_id'], ':details' => $log_details]);
                } catch (Exception $ae) {}

                $success_msg = "Official staff response and resolution saved for Ticket {$ticket['ticket_number']}!";
            } else {
                $error_msg = "Database error: Could not save response.";
            }
        }
    }
}

// Handle Star Toggle GET
if (isset($_GET['toggle_star']) && isset($_GET['id'])) {
    $fid = intval($_GET['id']);
    $db->prepare("UPDATE feedback SET is_starred = NOT is_starred WHERE feedback_id = :id")->execute([':id' => $fid]);
    redirect('manage-helpdesk.php');
}

// Fetch KPI statistics
$stats = [
    'total' => 0,
    'pending_action' => 0,
    'under_review' => 0,
    'staff_replied' => 0,
    'resolved' => 0,
    'ai_assisted' => 0
];
try {
    $stats['total'] = (int)$db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
    $stats['pending_action'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status IN ('open', 'ai_responded')")->fetchColumn();
    $stats['under_review'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status = 'under_review'")->fetchColumn();
    $stats['staff_replied'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status = 'staff_replied'")->fetchColumn();
    $stats['resolved'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status IN ('resolved', 'closed')")->fetchColumn();
    $stats['ai_assisted'] = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE ai_response IS NOT NULL AND ai_response != ''")->fetchColumn();
} catch (Exception $e) {}

// Filter parameters
$status_filter = sanitize($_GET['status'] ?? 'all');
$category_filter = sanitize($_GET['category'] ?? 'all');
$service_filter = sanitize($_GET['service_type'] ?? 'all');
$search_query = trim(sanitize($_GET['search'] ?? ''));

// Pagination settings
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$base_query = "SELECT f.*, u.first_name, u.last_name, u.email, u.contact_number, r.barangay,
                      su.first_name as staff_fname, su.last_name as staff_lname
               FROM feedback f
               LEFT JOIN users u ON f.user_id = u.user_id
               LEFT JOIN residents r ON u.user_id = r.user_id
               LEFT JOIN users su ON f.staff_responded_by = su.user_id
               WHERE 1=1";
$count_query = "SELECT COUNT(*) FROM feedback f LEFT JOIN users u ON f.user_id = u.user_id WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'pending_action') {
        $base_query .= " AND f.status IN ('open', 'ai_responded')";
        $count_query .= " AND f.status IN ('open', 'ai_responded')";
    } else {
        $base_query .= " AND f.status = :status";
        $count_query .= " AND f.status = :status";
        $params[':status'] = $status_filter;
    }
}

if ($category_filter !== 'all') {
    $base_query .= " AND f.category = :category";
    $count_query .= " AND f.category = :category";
    $params[':category'] = $category_filter;
}

if ($service_filter !== 'all') {
    $base_query .= " AND f.service_type = :service_type";
    $count_query .= " AND f.service_type = :service_type";
    $params[':service_type'] = $service_filter;
}

if (!empty($search_query)) {
    $base_query .= " AND (f.ticket_number LIKE :search OR f.subject LIKE :search OR f.comment LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $count_query .= " AND (f.ticket_number LIKE :search OR f.subject LIKE :search OR f.comment LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

$base_query .= " ORDER BY f.is_starred DESC, f.created_at DESC LIMIT :offset, :per_page";

// Total records
$c_stmt = $db->prepare($count_query);
foreach ($params as $k => $v) {
    $c_stmt->bindValue($k, $v);
}
$c_stmt->execute();
$total_records = (int)$c_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Fetch tickets
$t_stmt = $db->prepare($base_query);
foreach ($params as $k => $v) {
    $t_stmt->bindValue($k, $v);
}
$t_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$t_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$t_stmt->execute();
$tickets = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Helpdesk & Citizen Support Hub - Staff Portal';
$active_nav = 'helpdesk';
require_once __DIR__ . '/includes/staff-head.php';
?>

<style>
.stat-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.help-stat-card {
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: var(--ref-shadow-sm);
    transition: all 0.2s ease;
}
.help-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--ref-shadow);
    border-color: var(--ref-gold);
}
.help-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.help-stat-icon.maroon { background: rgba(127,3,3,0.1); color: var(--ref-maroon); }
.help-stat-icon.amber { background: rgba(217,119,6,0.1); color: #D97706; }
.help-stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563EB; }
.help-stat-icon.green { background: rgba(5,150,105,0.1); color: #059669; }
.help-stat-icon.purple { background: rgba(139,92,246,0.1); color: #8B5CF6; }

.filter-card {
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 20px;
}
.filter-form-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.filter-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 140px;
    flex: 1;
}
.filter-item label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ref-muted);
    text-transform: uppercase;
}
.filter-item select, .filter-item input {
    padding: 8px 12px;
    border: 1px solid var(--ref-border);
    border-radius: 10px;
    font-size: 0.82rem;
    background: var(--ref-canvas);
    color: var(--ref-text);
}

.tkt-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
}
.tkt-cat-question { background: rgba(37,99,235,0.12); color: #2563EB; }
.tkt-cat-concern { background: rgba(217,119,6,0.12); color: #D97706; }
.tkt-cat-feedback { background: rgba(5,150,105,0.12); color: #059669; }

.tkt-status-open { background: rgba(100,116,139,0.12); color: #475569; }
.tkt-status-ai_responded { background: rgba(139,92,246,0.12); color: #7C3AED; }
.tkt-status-under_review { background: rgba(217,119,6,0.15); color: #B45309; }
.tkt-status-staff_replied { background: rgba(2,132,199,0.15); color: #0284C7; }
.tkt-status-resolved { background: rgba(5,150,105,0.15); color: #059669; }
.tkt-status-closed { background: rgba(107,114,128,0.12); color: #6B7280; }

/* Modal Styling */
.tkt-modal-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.65);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.tkt-modal-backdrop.active { display: flex !important; }
.tkt-modal-card {
    background: #ffffff;
    background: var(--ref-card, #ffffff);
    border-radius: 20px;
    width: 90%;
    max-width: 820px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--ref-shadow-lg);
    border: 1px solid var(--ref-border);
}
.tkt-modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--ref-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--ref-canvas);
}
.tkt-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.tkt-bubble {
    border-radius: 14px;
    padding: 16px 18px;
    border: 1px solid var(--ref-border);
}
.tkt-bubble.resident-bubble {
    background: var(--ref-canvas);
    border-left: 4px solid var(--ref-maroon);
}
.tkt-bubble.ai-bubble {
    background: rgba(139,92,246,0.04);
    border: 1px solid rgba(139,92,246,0.25);
    border-left: 4px solid #8B5CF6;
}
.tkt-bubble.staff-bubble {
    background: rgba(5,150,105,0.04);
    border: 1px solid rgba(5,150,105,0.25);
    border-left: 4px solid #059669;
}
</style>

<div class="app-container">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <main class="main-content">
        <!-- Simplified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Container -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Helpdesk, Inquiries & Citizen Concerns';
            $banner_subtitle = 'Review questions, provide official casework answers, and monitor Lingkod Kalinga AI guidance';
            $banner_icon = 'fa-headset';
            // Banner action buttons removed as requested
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <?php if ($success_msg): ?>
                <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px 18px; background: rgba(5,150,105,0.12); color: #059669; border-radius: 12px; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px 18px; background: rgba(220,38,38,0.12); color: #DC2626; border-radius: 12px; font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- KPI Statistics Cards -->
            <div class="stat-cards-grid">
                <div class="help-stat-card">
                    <div class="help-stat-icon maroon"><i class="fas fa-inbox"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.4rem; color:var(--ref-text);"><?php echo $stats['total']; ?></h3>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:600;">Total Tickets</span>
                    </div>
                </div>
                <div class="help-stat-card">
                    <div class="help-stat-icon amber"><i class="fas fa-clock"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.4rem; color:#D97706;"><?php echo $stats['pending_action']; ?></h3>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:600;">Pending Review</span>
                    </div>
                </div>
                <div class="help-stat-card">
                    <div class="help-stat-icon purple"><i class="fas fa-robot"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.4rem; color:#7C3AED;"><?php echo $stats['ai_assisted']; ?></h3>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:600;">AI Initial Assisted</span>
                    </div>
                </div>
                <div class="help-stat-card">
                    <div class="help-stat-icon blue"><i class="fas fa-user-clock"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.4rem; color:#2563EB;"><?php echo $stats['under_review']; ?></h3>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:600;">Under Active Review</span>
                    </div>
                </div>
                <div class="help-stat-card">
                    <div class="help-stat-icon green"><i class="fas fa-check-double"></i></div>
                    <div>
                        <h3 style="margin:0; font-size:1.4rem; color:#059669;"><?php echo $stats['resolved']; ?></h3>
                        <span style="font-size:0.75rem; color:var(--ref-muted); font-weight:600;">Resolved Cases</span>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="filter-card">
                <form method="GET" action="" class="filter-form-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter==='all'?'selected':''; ?>>All Statuses</option>
                            <option value="pending_action" <?php echo $status_filter==='pending_action'?'selected':''; ?>>Pending Action (Open / AI Responded)</option>
                            <option value="under_review" <?php echo $status_filter==='under_review'?'selected':''; ?>>Under Review</option>
                            <option value="staff_replied" <?php echo $status_filter==='staff_replied'?'selected':''; ?>>Staff Replied</option>
                            <option value="resolved" <?php echo $status_filter==='resolved'?'selected':''; ?>>Resolved</option>
                            <option value="closed" <?php echo $status_filter==='closed'?'selected':''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label><i class="fas fa-tags"></i> Category</label>
                        <select name="category">
                            <option value="all" <?php echo $category_filter==='all'?'selected':''; ?>>All Types</option>
                            <option value="question" <?php echo $category_filter==='question'?'selected':''; ?>>Questions &amp; Inquiries</option>
                            <option value="concern" <?php echo $category_filter==='concern'?'selected':''; ?>>Concerns &amp; Grievances</option>
                            <option value="feedback" <?php echo $category_filter==='feedback'?'selected':''; ?>>Reviews &amp; Feedback</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label><i class="fas fa-hands-helping"></i> Service Program</label>
                        <select name="service_type">
                            <option value="all" <?php echo $service_filter==='all'?'selected':''; ?>>All Programs</option>
                            <option value="AICS Program" <?php echo $service_filter==='AICS Program'?'selected':''; ?>>AICS Program</option>
                            <option value="Housing Program" <?php echo $service_filter==='Housing Program'?'selected':''; ?>>Housing Program</option>
                            <option value="Appointments & QR Pass" <?php echo $service_filter==='Appointments & QR Pass'?'selected':''; ?>>Appointments</option>
                            <option value="Online Hub Portal" <?php echo $service_filter==='Online Hub Portal'?'selected':''; ?>>Portal Tech Support</option>
                        </select>
                    </div>
                    <div class="filter-item" style="flex: 2; min-width: 200px;">
                        <label><i class="fas fa-search"></i> Search Reference or Citizen</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="E.g. HLP-2026-0001 or Juan">
                    </div>
                    <div class="filter-item" style="flex: 0 0 auto; flex-direction: row; gap: 8px;">
                        <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.82rem; border-radius: 10px;">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="manage-helpdesk.php" class="btn-secondary" style="padding: 8px 16px; font-size: 0.82rem; border-radius: 10px; text-decoration: none;">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tickets Data Table -->
            <div class="table-card" style="background:var(--ref-card); border-radius:18px; border:1px solid var(--ref-border); padding:20px; box-shadow:var(--ref-shadow-sm);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="margin:0; font-size:1.05rem; font-weight:800; color:var(--ref-text);">
                        <i class="fas fa-list-alt" style="color:var(--ref-maroon);"></i> Active Helpdesk Tickets (<?php echo $total_records; ?>)
                    </h3>
                </div>

                <?php if (empty($tickets)): ?>
                    <div style="text-align:center; padding:48px 20px; color:var(--ref-muted);">
                        <i class="fas fa-folder-open" style="font-size:42px; opacity:0.3; margin-bottom:12px; display:block;"></i>
                        <h4 style="margin:0 0 6px 0; color:var(--ref-text);">No Helpdesk Tickets Found</h4>
                        <p style="margin:0; font-size:0.85rem;">All resident inquiries, questions, and concerns have been processed.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                            <thead>
                                <tr style="border-bottom:2px solid var(--ref-border); text-align:left; color:var(--ref-muted);">
                                    <th style="padding:10px 12px;">Ticket / Date</th>
                                    <th style="padding:10px 12px;">Citizen Details</th>
                                    <th style="padding:10px 12px;">Category &amp; Program</th>
                                    <th style="padding:10px 12px;">Subject &amp; Inquiry Preview</th>
                                    <th style="padding:10px 12px; text-align:center;">AI Guidance</th>
                                    <th style="padding:10px 12px; text-align:center;">Status</th>
                                    <th style="padding:10px 12px; text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $idx => $t): 
                                    $is_latest = ($page === 1 && $idx === 0);
                                ?>
                                    <tr class="<?php echo $is_latest ? 'row-latest-entry' : ''; ?>" style="border-bottom:1px solid var(--ref-border); transition: background 0.15s;">
                                        <td style="padding:12px;">
                                            <a href="?toggle_star=1&id=<?php echo $t['feedback_id']; ?>" style="text-decoration:none; margin-right:4px; color:<?php echo $t['is_starred'] ? '#D97706' : 'var(--ref-border)'; ?>;">
                                                <i class="fas fa-star"></i>
                                            </a>
                                            <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                                <strong style="color:var(--ref-maroon); font-family:monospace;"><?php echo htmlspecialchars($t['ticket_number'] ?: ('HLP-' . $t['feedback_id'])); ?></strong>
                                                <?php if ($is_latest): ?>
                                                    <span class="badge-latest-tag"><i class="fas fa-sparkles"></i> LATEST</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size:0.72rem; color:var(--ref-muted); margin-top:2px;">
                                                <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td style="padding:12px;">
                                            <strong style="color:var(--ref-text);"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></strong>
                                            <div style="font-size:0.72rem; color:var(--ref-muted);">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($t['barangay'] ?: 'Palayan City'); ?>
                                            </div>
                                            <?php if (!empty($t['contact_number'])): ?>
                                                <div style="font-size:0.72rem; color:var(--ref-muted);"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($t['contact_number']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;">
                                            <span class="tkt-badge tkt-cat-<?php echo $t['category']; ?>">
                                                <?php 
                                                switch($t['category']) {
                                                    case 'question': echo '<i class="fas fa-question-circle"></i> Question'; break;
                                                    case 'concern': echo '<i class="fas fa-exclamation-triangle"></i> Concern'; break;
                                                    case 'feedback': echo '<i class="fas fa-star"></i> Review'; break;
                                                    default: echo htmlspecialchars(ucfirst($t['category']));
                                                }
                                                ?>
                                            </span>
                                            <div style="font-size:0.72rem; color:var(--ref-text); font-weight:600; margin-top:3px;">
                                                <?php echo htmlspecialchars($t['service_type']); ?>
                                            </div>
                                        </td>
                                        <td style="padding:12px; max-width:240px;">
                                            <?php if (!empty($t['subject'])): ?>
                                                <strong style="display:block; margin-bottom:2px; color:var(--ref-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                    <?php echo htmlspecialchars($t['subject']); ?>
                                                </strong>
                                            <?php endif; ?>
                                            <div style="color:var(--ref-muted); font-size:0.76rem; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                                <?php echo htmlspecialchars($t['comment'] ?: 'No comment attached.'); ?>
                                            </div>
                                        </td>
                                        <td style="padding:12px; text-align:center;">
                                            <?php if (!empty($t['ai_response'])): ?>
                                                <span class="tkt-badge tkt-status-ai_responded" title="AI Preliminary Guidance Available">
                                                    <i class="fas fa-robot"></i> Provided
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--ref-muted); font-size:0.72rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px; text-align:center;">
                                            <?php
                                            $st_class = 'tkt-status-' . ($t['status'] ?: 'open');
                                            $st_label = str_replace('_', ' ', ucfirst($t['status'] ?: 'open'));
                                            ?>
                                            <span class="tkt-badge <?php echo $st_class; ?>">
                                                <?php echo $st_label; ?>
                                            </span>
                                            <?php if ($t['staff_fname']): ?>
                                                <div style="font-size:0.68rem; color:#059669; margin-top:2px;" title="Official Caseworker">
                                                    <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($t['staff_fname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px; text-align:center;">
                                            <button type="button" class="btn-primary" onclick="openTicketModal(<?php echo (int)$t['feedback_id']; ?>)" style="padding:6px 14px; font-size:0.75rem; border-radius:50px; cursor:pointer;">
                                                <i class="fas fa-reply"></i> Process
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <span class="page-info">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total tickets)</span>
                            <div class="pagination-links">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&service_type=<?php echo urlencode($service_filter); ?>&search=<?php echo urlencode($search_query); ?>" title="Previous Page">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="disabled" title="No Previous Page"><i class="fas fa-chevron-left"></i></span>
                                <?php endif; ?>

                                <?php for ($i=1; $i<=$total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&service_type=<?php echo urlencode($service_filter); ?>&search=<?php echo urlencode($search_query); ?>" 
                                       class="<?php echo ($i === $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&service_type=<?php echo urlencode($service_filter); ?>&search=<?php echo urlencode($search_query); ?>" title="Next Page">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="disabled" title="No Next Page"><i class="fas fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Interactive Review & Staff Official Response Modal -->
<div id="ticketModal" class="tkt-modal-backdrop">
    <div class="tkt-modal-card">
        <div class="tkt-modal-header">
            <div>
                <h3 id="modalTicketHeader" style="margin:0; font-size:1.1rem; color:var(--ref-maroon); font-weight:800;">
                    <i class="fas fa-ticket-alt"></i> Ticket Details
                </h3>
                <span id="modalTicketMeta" style="font-size:0.75rem; color:var(--ref-muted);">Loading ticket information...</span>
            </div>
            <button type="button" onclick="closeTicketModal()" style="background:none; border:none; font-size:1.4rem; color:var(--ref-muted); cursor:pointer;">&times;</button>
        </div>

        <div class="tkt-modal-body" id="modalTicketBody">
            <!-- Dynamically populated via JavaScript -->
        </div>
    </div>
</div>

<script>
let currentTicketData = null;

function openTicketModal(feedbackId) {
    const modal = document.getElementById('ticketModal');
    const body = document.getElementById('modalTicketBody');
    const header = document.getElementById('modalTicketHeader');
    const meta = document.getElementById('modalTicketMeta');

    body.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:28px; color:var(--ref-maroon);"></i><p style="margin-top:10px;">Loading ticket conversation thread...</p></div>';
    modal.classList.add('active');

    fetch(`manage-helpdesk.php?fetch_ticket_thread=${feedbackId}`)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<div class="alert alert-danger" style="margin:20px; padding:15px; border-radius:10px; background:#FEE2E2; color:#991B1B;">${escapeHtml(data.message || 'Error loading ticket')}</div>`;
                return;
            }

            const t = data.ticket;
            currentTicketData = t;
            const catStr = (t.category || t.service_type || 'Helpdesk').toUpperCase();
            const subjectStr = t.subject || catStr;
            header.innerHTML = `<i class="fas fa-ticket-alt"></i> ${escapeHtml(t.ticket_number || ('HLP-' + t.feedback_id))}: ${escapeHtml(subjectStr)}`;
            const resName = ((t.first_name || '') + ' ' + (t.last_name || '')).trim() || 'Resident';
            meta.innerHTML = `Submitted by <strong>${escapeHtml(resName)}</strong> &bull; Barangay ${escapeHtml(t.barangay || 'Palayan City')} &bull; ${escapeHtml(t.created_at || '')}`;

            let html = '';

            // 1. Citizen Profile Banner
            html += `
            <div style="background:var(--ref-canvas); padding:14px 18px; border-radius:12px; border:1px solid var(--ref-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <span style="font-size:0.72rem; color:var(--ref-muted); text-transform:uppercase; font-weight:700;">Resident Profile</span>
                    <div style="font-weight:700; color:var(--ref-text);">${escapeHtml(resName)}</div>
                    <div style="font-size:0.78rem; color:var(--ref-muted);">${escapeHtml(t.email || 'No email')} &bull; ${escapeHtml(t.contact_number || 'No phone')}</div>
                </div>
                <div>
                    <span style="font-size:0.72rem; color:var(--ref-muted); text-transform:uppercase; font-weight:700;">Program Category</span>
                    <div style="font-weight:700; color:var(--ref-maroon);">${escapeHtml(t.service_type || 'General')}</div>
                </div>
            </div>`;

            // 2. Original Citizen Message
            html += `
            <div class="tkt-bubble resident-bubble">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong style="color:var(--ref-maroon); font-size:0.85rem;"><i class="fas fa-user"></i> Citizen Inquiry / Concern:</strong>
                    <span style="font-size:0.72rem; color:var(--ref-muted);">${escapeHtml(t.created_at)}</span>
                </div>
                <div style="font-size:0.85rem; color:var(--ref-text); line-height:1.5;">${escapeHtml(t.comment || 'No text provided.').replace(/\n/g, '<br>')}</div>
            </div>`;

            // 3. AI Automated Guidance (if present)
            if (t.ai_response) {
                html += `
                <div class="tkt-bubble ai-bubble">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:0.75rem; font-weight:700; color:#7C3AED; display:flex; align-items:center; gap:6px;">
                            <i class="fas fa-robot"></i> Lingkod Kalinga AI Assistant &bull; Automated Preliminary Guidance
                        </span>
                        <span style="font-size:0.72rem; color:var(--ref-muted);">${escapeHtml(t.ai_responded_at || '')}</span>
                    </div>
                    <div style="font-size:0.82rem; color:var(--ref-text); line-height:1.5;">${escapeHtml(t.ai_response).replace(/\n/g, '<br>')}</div>
                    <div style="margin-top:8px; font-size:0.7rem; color:#6B7280; font-style:italic;">
                        <i class="fas fa-info-circle"></i> Sent automatically upon submission to give initial document preparation advice. Official decision is handled below.
                    </div>
                </div>`;
            }

            // 4. Discussion History Thread (if any follow-up replies)
            if (data.replies && data.replies.length > 0) {
                html += '<div style="margin:6px 0;"><h4 style="margin:0 0 10px 0; font-size:0.82rem; color:var(--ref-muted); text-transform:uppercase;"><i class="fas fa-comments"></i> Follow-up Conversation Thread</h4>';
                data.replies.forEach(rep => {
                    const isStaff = (rep.sender_type === 'staff');
                    const isAi = (rep.sender_type === 'ai');
                    if (!isAi) {
                        html += `
                        <div class="tkt-bubble ${isStaff ? 'staff-bubble' : 'resident-bubble'}" style="margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <strong style="font-size:0.78rem; color:${isStaff ? '#059669' : 'var(--ref-maroon)'};">
                                    <i class="fas ${isStaff ? 'fa-id-badge' : 'fa-user'}"></i> ${isStaff ? 'CSWDO Staff Official Reply' : 'Resident Follow-up Reply'}:
                                </strong>
                                <span style="font-size:0.7rem; color:var(--ref-muted);">${escapeHtml(rep.created_at)}</span>
                            </div>
                            <div style="font-size:0.82rem; color:var(--ref-text); line-height:1.5;">${escapeHtml(rep.message).replace(/\n/g, '<br>')}</div>
                        </div>`;
                    }
                });
                html += '</div>';
            }

            // 5. Official Staff Decision & Response Form
            html += `
            <div style="background:var(--ref-card); border:1px solid var(--ref-border); border-radius:14px; padding:18px;">
                <h4 style="margin:0 0 12px 0; font-size:0.92rem; color:var(--ref-text); font-weight:800; display:flex; align-items:center; justify-content:space-between;">
                    <span><i class="fas fa-stamp" style="color:#059669;"></i> Official Staff Action &amp; Response</span>
                    <button type="button" class="btn-secondary" onclick="generateStaffDraft()" style="padding:4px 10px; font-size:0.72rem; border-radius:50px;">
                        <i class="fas fa-magic"></i> AI Draft Helper
                    </button>
                </h4>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="feedback_id" value="${t.feedback_id}">

                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--ref-text); margin-bottom:4px;">
                            Official Response to Resident (Visible in Portal &amp; Emailed): <span style="color:#DC2626;">*</span>
                        </label>
                        <textarea name="official_response" id="modalOfficialResponse" rows="4" style="width:100%; padding:10px 14px; border:1px solid var(--ref-border); border-radius:10px; font-size:0.82rem; background:var(--ref-canvas); color:var(--ref-text); font-family:inherit;" placeholder="Enter official CSWDO instructions, casework evaluation results, or appointment scheduling advice..." required>${escapeHtml(t.staff_response || '')}</textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--ref-text); margin-bottom:4px;">
                                Set Ticket Status:
                            </label>
                            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--ref-border); border-radius:10px; font-size:0.82rem; background:var(--ref-canvas); color:var(--ref-text);">
                                <option value="staff_replied" ${t.status==='staff_replied'?'selected':''}>💬 Staff Replied (Awaiting Resident)</option>
                                <option value="under_review" ${t.status==='under_review'?'selected':''}>🔍 Under Active Casework Review</option>
                                <option value="resolved" ${t.status==='resolved'?'selected':''}>✅ Mark as Resolved (Completed)</option>
                                <option value="closed" ${t.status==='closed'?'selected':''}>🔒 Close Ticket</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--ref-text); margin-bottom:4px;">
                                Internal Staff Notes (Caseworker Only):
                            </label>
                            <input type="text" name="resolution_notes" value="${escapeHtml(t.resolution_notes || '')}" style="width:100%; padding:8px 12px; border:1px solid var(--ref-border); border-radius:10px; font-size:0.82rem; background:var(--ref-canvas); color:var(--ref-text);" placeholder="E.g. Verified with City Assessor / Disbursed vouchers">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary" onclick="closeTicketModal()" style="padding:8px 18px; border-radius:50px;">Cancel</button>
                        <button type="submit" name="submit_staff_response" class="btn-primary" style="padding:8px 24px; border-radius:50px;">
                            <i class="fas fa-paper-plane"></i> Send Official Response
                        </button>
                    </div>
                </form>
            </div>`;

            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger">Error loading ticket: ${err}</div>`;
        });
}

function closeTicketModal() {
    const modal = document.getElementById('ticketModal');
    if (modal) modal.classList.remove('active');
}

function generateStaffDraft() {
    if (!currentTicketData) return;
    const name = currentTicketData.first_name || 'Kababayan';
    const program = currentTicketData.service_type || 'Social Services';
    const txtArea = document.getElementById('modalOfficialResponse');

    txtArea.value = `Magandang araw po, ${name}.\n\n`
                  + `Tungkol po sa inyong pagsangguni hinggil sa ${program}, sinuri na po ng ating CSWDO Casework Team ang inyong talaan.\n\n`
                  + `[Opisyal na tagubilin o desisyon: Halimbawa, nakatakda na ang inyong interbyu o handa na ang inyong dokumento sa Palayan City Hall Ground Floor].\n\n`
                  + `Kung mayroon pa po kayong karagdagang katanungan, maaari kayong mag-reply dito sa ticket o magtungo sa aming tanggapan Lunes hanggang Biyernes, 8:00 AM - 5:00 PM.\n\n`
                  + `Maraming salamat po sa inyong pakikipagtulungan.\n\n`
                  + `CSWDO Palayan City Hall Casework Team`;
    txtArea.focus();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('ticketModal');
    if (e.target === modal) closeTicketModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
        closeTicketModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

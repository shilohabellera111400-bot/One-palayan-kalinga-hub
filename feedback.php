<?php
// feedback.php - Citizen Helpdesk, Questions, Concerns & Feedback Portal
// Integrated with Lingkod Kalinga AI Assistant & Official CSWDO Casework Staff
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/OpenAIHelper.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should use their dedicated management portals
if (isStaff() || isAdmin()) {
    redirect('staff/staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();
$openAIHelper = new OpenAIHelper($db);

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

$error = '';
$success = '';

// ============================================================
// 1. AJAX FETCH TICKET CONVERSATION THREAD
// ============================================================
if (isset($_GET['fetch_ticket_thread'])) {
    header('Content-Type: application/json; charset=utf-8');
    $tkt_id = intval($_GET['fetch_ticket_thread']);

    $q = "SELECT f.*, u.first_name, u.last_name, u.email,
                 su.first_name as staff_fname, su.last_name as staff_lname, st.position as staff_pos
          FROM feedback f
          JOIN users u ON f.user_id = u.user_id
          LEFT JOIN users su ON f.staff_responded_by = su.user_id
          LEFT JOIN staff st ON su.user_id = st.user_id
          WHERE f.feedback_id = :id AND f.user_id = :uid";
    $st = $db->prepare($q);
    $st->execute([':id' => $tkt_id, ':uid' => $_SESSION['user_id']]);
    $ticket = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found or unauthorized.']);
        exit;
    }

    // Fetch conversation replies
    $rep_stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.role, st.position as staff_position
        FROM feedback_replies r
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN staff st ON u.user_id = st.user_id
        WHERE r.feedback_id = :id
        ORDER BY r.created_at ASC
    ");
    $rep_stmt->execute([':id' => $tkt_id]);
    $replies = $rep_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'ticket' => $ticket, 'replies' => $replies]);
    exit;
}

// ============================================================
// 2. RESIDENT FOLLOW-UP REPLY SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_resident_reply'])) {
    csrf_verify();

    $tkt_id = intval($_POST['feedback_id'] ?? 0);
    $reply_msg = trim($_POST['reply_message'] ?? '');

    if (empty($reply_msg)) {
        $error = "Mangyaring maglagay ng mensahe para sa inyong follow-up.";
    } else {
        // Verify ownership and ticket state
        $chk_stmt = $db->prepare("SELECT * FROM feedback WHERE feedback_id = :id AND user_id = :uid");
        $chk_stmt->execute([':id' => $tkt_id, ':uid' => $_SESSION['user_id']]);
        $ticket = $chk_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $error = "Hindi nahanap ang ticket o wala kang pahintulot.";
        } elseif ($ticket['status'] === 'closed') {
            $error = "Sarado na ang ticket na ito at hindi na maaaring lagyan ng karagdagang mensahe.";
        } else {
            // Insert reply
            $ins_rep = $db->prepare("INSERT INTO feedback_replies (feedback_id, user_id, sender_type, message, created_at) VALUES (:fbid, :uid, 'resident', :msg, NOW())");
            $ins_rep->execute([
                ':fbid' => $tkt_id,
                ':uid' => $_SESSION['user_id'],
                ':msg' => $reply_msg
            ]);

            // Update ticket status to under_review so staff is notified
            $upd = $db->prepare("UPDATE feedback SET status = 'under_review', updated_at = NOW() WHERE feedback_id = :id");
            $upd->execute([':id' => $tkt_id]);

            // Notify staff
            try {
                $staff_notif = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    SELECT user_id, :title, :msg, 'helpdesk', 0, NOW() 
                    FROM users 
                    WHERE role IN ('staff', 'admin') AND is_active = 1
                    LIMIT 3
                ");
                $notif_title = "Follow-up sa Ticket " . ($ticket['ticket_number'] ?: '#' . $ticket['feedback_id']);
                $notif_msg = "Nagpadala ng karagdagang tugon si {$_SESSION['full_name']} ukol sa '{$ticket['subject']}'.";
                $staff_notif->execute([':title' => $notif_title, ':msg' => $notif_msg]);
            } catch (Exception $e) {}

            $success = "Matagumpay na naipadala ang inyong karagdagang mensahe sa CSWDO staff.";
        }
    }
}

// ============================================================
// 3. MARK TICKET AS RESOLVED BY RESIDENT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_resolved'])) {
    csrf_verify();

    $tkt_id = intval($_POST['feedback_id'] ?? 0);

    $chk_stmt = $db->prepare("SELECT * FROM feedback WHERE feedback_id = :id AND user_id = :uid");
    $chk_stmt->execute([':id' => $tkt_id, ':uid' => $_SESSION['user_id']]);
    $ticket = $chk_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $error = "Hindi nahanap ang ticket o wala kang pahintulot.";
    } else {
        $upd = $db->prepare("UPDATE feedback SET status = 'resolved', updated_at = NOW() WHERE feedback_id = :id");
        $upd->execute([':id' => $tkt_id]);

        $ins_rep = $db->prepare("INSERT INTO feedback_replies (feedback_id, user_id, sender_type, message, created_at) VALUES (:fbid, :uid, 'resident', 'Minarkahan ng residente ang ticket bilang Nalutas Na (Resolved). Maraming salamat!', NOW())");
        $ins_rep->execute([':fbid' => $tkt_id, ':uid' => $_SESSION['user_id']]);

        $success = "Matagumpay na minarkahan bilang Nalutas Na (Resolved) ang inyong ticket.";
    }
}

// ============================================================
// 4. NEW TICKET SUBMISSION (QUESTION / CONCERN / FEEDBACK)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    csrf_verify();

    $category = sanitize($_POST['category'] ?? 'question');
    $service_type = sanitize($_POST['service_type'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $comment = sanitize($_POST['comment'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);

    // Validate category
    $valid_categories = ['question', 'concern', 'feedback'];
    if (!in_array($category, $valid_categories)) {
        $category = 'question';
    }

    // Default subject if left blank
    if (empty($subject)) {
        if ($category === 'feedback') {
            $subject = "Feedback ukol sa " . ($service_type ?: 'CSWDO Services');
        } elseif ($category === 'question') {
            $subject = "Katanungan ukol sa " . ($service_type ?: 'CSWDO Services');
        } else {
            $subject = "Concern ukol sa " . ($service_type ?: 'CSWDO Services');
        }
    }

    // Validation
    if (empty($service_type)) {
        $error = "Mangyaring pumili ng may kinalamang serbisyo o programa.";
    } elseif (empty($comment)) {
        $error = "Mangyaring ilagay ang detalye ng inyong katanungan, concern, o feedback.";
    } elseif ($category === 'feedback' && ($rating < 1 || $rating > 5)) {
        $error = "Para sa feedback, mangyaring maglagay ng rating mula 1 hanggang 5 bituin.";
    } else {
        // Generate Unique Ticket Number: Prefix-Year-Sequence
        $prefix = ($category === 'question') ? 'QST' : (($category === 'concern') ? 'CRN' : 'FBK');
        $year = date('Y');
        $seq_stmt = $db->query("SELECT MAX(feedback_id) as max_id FROM feedback");
        $max_id_row = $seq_stmt->fetch(PDO::FETCH_ASSOC);
        $next_seq = ($max_id_row['max_id'] ?? 0) + 1;
        $ticket_number = sprintf("%s-%s-%05d", $prefix, $year, $next_seq);

        // Generate AI Preliminary Guidance using OpenAIHelper
        $residentName = $_SESSION['full_name'] ?? 'Kababayan';
        $aiResult = $openAIHelper->generateInitialGuidance($category, $service_type, $subject, $comment, $residentName);

        $status = 'open';
        $ai_response = null;
        $ai_responded_at = null;

        if (!empty($aiResult['success']) && !empty($aiResult['guidance'])) {
            $status = 'ai_responded';
            $ai_response = $aiResult['guidance'];
            $ai_responded_at = date('Y-m-d H:i:s');
        }

        // Insert into feedback table
        $query = "INSERT INTO feedback (
                    ticket_number, category, subject, user_id, service_type, rating, comment, 
                    status, ai_response, ai_responded_at, created_at, updated_at
                  ) VALUES (
                    :ticket_number, :category, :subject, :user_id, :service_type, :rating, :comment,
                    :status, :ai_response, :ai_responded_at, NOW(), NOW()
                  )";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':ticket_number', $ticket_number);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':service_type', $service_type);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':comment', $comment);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':ai_response', $ai_response);
        $stmt->bindParam(':ai_responded_at', $ai_responded_at);

        if ($stmt->execute()) {
            $new_feedback_id = $db->lastInsertId();

            // Record initial AI response into feedback_replies thread if available
            if (!empty($ai_response)) {
                $rep_stmt = $db->prepare("INSERT INTO feedback_replies (feedback_id, user_id, sender_type, message, created_at) VALUES (:fbid, NULL, 'ai', :msg, NOW())");
                $rep_stmt->execute([
                    ':fbid' => $new_feedback_id,
                    ':msg' => $ai_response
                ]);

                // Create in-app notification for resident
                try {
                    $notif_sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (:uid, :title, :msg, 'helpdesk', 0, NOW())";
                    $notif_st = $db->prepare($notif_sql);
                    $notif_st->execute([
                        ':uid' => $_SESSION['user_id'],
                        ':title' => "Helpdesk Ticket {$ticket_number}: Paunang Gabay mula sa Kalinga AI",
                        ':msg' => "Nakatanggap ang inyong katanungan ng paunang gabay ukol sa '{$subject}'. Buksan ang ticket upang basahin."
                    ]);
                } catch (Exception $e) {}
            }

            // Notify staff of new ticket
            try {
                $st_notif = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    SELECT user_id, :title, :msg, 'helpdesk', 0, NOW() 
                    FROM users 
                    WHERE role IN ('staff', 'admin') AND is_active = 1
                    LIMIT 3
                ");
                $st_title = "Bagong Helpdesk Ticket ({$ticket_number})";
                $st_msg = "Isinumite ni {$residentName}: '{$subject}' para sa {$service_type}.";
                $st_notif->execute([':title' => $st_title, ':msg' => $st_msg]);
            } catch (Exception $e) {}

            $cat_label = ($category === 'question') ? 'katanungan' : (($category === 'concern') ? 'concern' : 'feedback');
            $success = "Maraming salamat! Matagumpay na naitala ang inyong {$cat_label} (Ticket No: <strong>{$ticket_number}</strong>). "
                     . (!empty($ai_response) ? "May paunang gabay na inihanda ang Lingkod Kalinga AI habang nakapila ito para sa pormal na pagsusuri ng CSWDO casework officers." : "Susuriin ito ng CSWDO staff para sa opisyal na tugon.");
        } else {
            $error = "May naganap na error sa pagpapadala ng inyong katanungan o feedback.";
        }
    }
}

// ============================================================
// 5. FETCH USER'S TICKETS & FEEDBACKS
// ============================================================
$query = "SELECT f.*, 
                 (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.feedback_id) as reply_count,
                 su.first_name as staff_fname, su.last_name as staff_lname
          FROM feedback f 
          LEFT JOIN users su ON f.staff_responded_by = su.user_id
          WHERE f.user_id = :user_id 
          ORDER BY f.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$my_feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ticket KPI counts for resident
$cnt_total = count($my_feedbacks);
$cnt_active = 0;
$cnt_resolved = 0;
foreach ($my_feedbacks as $fb_item) {
    if (in_array($fb_item['status'], ['resolved', 'closed'])) {
        $cnt_resolved++;
    } else {
        $cnt_active++;
    }
}

// Get user data for sidebar
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

$profile_picture = $user['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture) ? $profile_picture : '';
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSWDO Helpdesk & Citizen Assistance - One Palayan-Kalinga Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== HELPDESK LAYOUT ========== */
        .helpdesk-layout {
            display: grid;
            grid-template-columns: 1.15fr 1.85fr;
            gap: 26px;
            align-items: flex-start;
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
            border-color: var(--maroon-light);
        }

        .card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--border-color);
            position: relative;
        }

        .card .card-header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--gold);
        }

        .card-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card .card-header h2 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        .card .card-header i {
            color: var(--gold);
            font-size: 1.15rem;
        }

        /* Category Switcher Tabs */
        .category-switcher {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            background: rgba(0, 0, 0, 0.04);
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 18px;
        }

        [data-theme="dark"] .category-switcher {
            background: rgba(255, 255, 255, 0.05);
        }

        .cat-tab-btn {
            background: transparent;
            border: none;
            padding: 10px 8px;
            border-radius: 9px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            text-align: center;
        }

        .cat-tab-btn i {
            font-size: 1rem;
        }

        .cat-tab-btn.active {
            background: #FFFFFF;
            color: var(--maroon);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        [data-theme="dark"] .cat-tab-btn.active {
            background: #1E293B;
            color: #FCA5A5;
        }

        .cat-tab-btn[data-category="question"].active {
            border-bottom: 2px solid #2563EB;
        }

        .cat-tab-btn[data-category="concern"].active {
            border-bottom: 2px solid #D97706;
        }

        .cat-tab-btn[data-category="feedback"].active {
            border-bottom: 2px solid #059669;
        }

        /* Category Guidance Callout */
        .category-callout {
            background: rgba(37, 99, 235, 0.07);
            border-left: 3.5px solid #2563EB;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 0.77rem;
            line-height: 1.45;
            color: var(--text-secondary);
        }

        .category-callout.concern-callout {
            background: rgba(217, 119, 6, 0.07);
            border-left-color: #D97706;
        }

        .category-callout.feedback-callout {
            background: rgba(5, 150, 105, 0.07);
            border-left-color: #059669;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.8rem;
        }

        .form-group label i {
            color: var(--gold);
            margin-right: 5px;
            width: 16px;
        }

        .form-group label .required {
            color: var(--color-danger, #EF4444);
            margin-left: 2px;
        }

        .form-control,
        .form-group select,
        .form-group textarea,
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.83rem;
            transition: all 0.25s ease;
            background: var(--input-bg, #FFFFFF);
            color: var(--text-primary);
            font-family: inherit;
            box-sizing: border-box;
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-group select,
        [data-theme="dark"] .form-group textarea,
        [data-theme="dark"] .form-group input[type="text"] {
            background: #141E22;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .form-group select:focus,
        .form-group textarea:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 108, 0.18);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 105px;
        }

        /* Star Rating */
        .star-rating-wrap {
            display: none;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
            padding: 12px 14px;
            background: rgba(201, 168, 108, 0.08);
            border-radius: var(--radius-element);
            border: 1px dashed var(--gold);
        }

        .star-rating-wrap.visible {
            display: flex;
        }

        .star-rating-wrap .stars {
            display: flex;
            gap: 8px;
        }

        .star-item {
            font-size: 1.9rem;
            color: var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .star-item:hover,
        .star-item.active {
            color: var(--gold);
        }

        .star-item:hover {
            transform: scale(1.15);
        }

        /* AI Guidance Callout Banner in Form */
        .ai-banner-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(127, 3, 3, 0.06) 100%);
            border: 1px solid rgba(37, 99, 235, 0.2);
            margin-bottom: 18px;
            font-size: 0.74rem;
            line-height: 1.4;
            color: var(--text-secondary);
        }

        .ai-banner-pill i {
            color: #2563EB;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        /* Buttons */
        .btn-submit-ticket {
            background: var(--maroon);
            color: white;
            padding: 11px 22px;
            border: none;
            border-radius: 50px;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.2);
        }

        .btn-submit-ticket:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(127, 3, 3, 0.3);
        }

        /* ========== TICKET LIST (RIGHT PANEL) ========== */
        .ticket-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .filter-pills {
            display: flex;
            gap: 6px;
        }

        .filter-pill {
            background: rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 4px 12px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .filter-pill {
            background: rgba(255, 255, 255, 0.05);
        }

        .filter-pill.active {
            background: var(--maroon);
            color: #FFFFFF;
            border-color: var(--maroon);
        }

        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 750px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .ticket-item {
            background: var(--badge-bg, rgba(0, 0, 0, 0.02));
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 18px;
            transition: all 0.25s ease;
            position: relative;
        }

        [data-theme="dark"] .ticket-item {
            background: rgba(255, 255, 255, 0.02);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .ticket-item:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
        }

        .ticket-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .ticket-id-tag {
            font-family: monospace;
            font-weight: 800;
            font-size: 0.72rem;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(127, 3, 3, 0.08);
            color: var(--maroon);
            letter-spacing: 0.4px;
        }

        [data-theme="dark"] .ticket-id-tag {
            background: rgba(248, 113, 113, 0.15);
            color: #F87171;
        }

        .ticket-category-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .cat-question {
            background: rgba(37, 99, 235, 0.1);
            color: #2563EB;
        }

        .cat-concern {
            background: rgba(217, 119, 6, 0.1);
            color: #D97706;
        }

        .cat-feedback {
            background: rgba(5, 150, 105, 0.1);
            color: #059669;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-open {
            background: rgba(59, 130, 246, 0.12);
            color: #2563EB;
        }

        .status-ai_responded {
            background: rgba(147, 51, 234, 0.12);
            color: #7E22CE;
        }

        .status-under_review {
            background: rgba(245, 158, 11, 0.15);
            color: #B45309;
        }

        .status-staff_replied {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }

        .status-resolved {
            background: rgba(20, 184, 166, 0.15);
            color: #0F766E;
        }

        .status-closed {
            background: rgba(107, 114, 128, 0.15);
            color: #4B5563;
        }

        .ticket-subject {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 4px;
            line-height: 1.35;
        }

        .ticket-service-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .ticket-comment-snippet {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ticket-footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .ticket-date {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .btn-view-thread {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-thread:hover {
            background: var(--maroon-light);
            transform: scale(1.02);
        }

        .reply-count-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.65rem;
        }

        .rating-stars-gold {
            color: var(--gold);
            font-size: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.8rem;
            opacity: 0.35;
            display: block;
            margin-bottom: 12px;
        }

        /* ========== CONVERSATION THREAD MODAL ========== */
        .thread-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .thread-modal-overlay.open {
            display: flex;
        }

        .thread-modal-card {
            background: var(--bg-card);
            border-radius: 20px;
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border-color);
            animation: modalFadeIn 0.25s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .thread-modal-header {
            padding: 18px 24px;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--maroon-light) 100%);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-shrink: 0;
        }

        .thread-modal-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-close-modal {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: #FFFFFF;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .thread-modal-body {
            padding: 22px 24px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Citizen Initial Submission Card */
        .inquiry-summary-box {
            background: rgba(0, 0, 0, 0.025);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 18px;
        }

        [data-theme="dark"] .inquiry-summary-box {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .inquiry-summary-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 6px;
        }

        .inquiry-summary-title {
            font-weight: 700;
            font-size: 0.96rem;
            color: var(--text-primary);
            margin: 0 0 6px;
        }

        .inquiry-summary-text {
            font-size: 0.83rem;
            color: var(--text-secondary);
            line-height: 1.55;
            white-space: pre-line;
        }

        /* AI Guidance Box */
        .ai-guidance-box {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
            border: 1.5px solid rgba(37, 99, 235, 0.3);
            border-radius: 14px;
            padding: 16px 20px;
            position: relative;
        }

        .ai-guidance-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .ai-guidance-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 0.85rem;
            color: #1D4ED8;
        }

        [data-theme="dark"] .ai-guidance-title {
            color: #93C5FD;
        }

        .ai-guidance-badge {
            background: #2563EB;
            color: #FFFFFF;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .ai-disclaimer-alert {
            background: rgba(217, 119, 6, 0.1);
            border-left: 3px solid #D97706;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.72rem;
            color: #B45309;
            margin-bottom: 12px;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] .ai-disclaimer-alert {
            color: #FCD34D;
        }

        .ai-guidance-text {
            font-size: 0.83rem;
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-line;
        }

        /* Official Staff Response Box */
        .staff-response-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.06) 0%, rgba(5, 150, 105, 0.08) 100%);
            border: 2px solid #059669;
            border-radius: 14px;
            padding: 16px 20px;
        }

        .staff-response-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .staff-response-badge {
            background: #059669;
            color: #FFFFFF;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .staff-responder-info {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .staff-response-text {
            font-size: 0.86rem;
            color: var(--text-primary);
            line-height: 1.6;
            white-space: pre-line;
            font-weight: 500;
        }

        .staff-resolution-note {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed rgba(5, 150, 105, 0.3);
            font-size: 0.75rem;
            color: #047857;
            font-style: italic;
        }

        /* Discussion Replies Timeline */
        .timeline-section-title {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin: 6px 0 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .discussion-thread {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bubble-row {
            display: flex;
            flex-direction: column;
            max-width: 82%;
        }

        .bubble-row.bubble-resident {
            align-self: flex-end;
            align-items: flex-end;
        }

        .bubble-row.bubble-staff {
            align-self: flex-start;
            align-items: flex-start;
        }

        .bubble-author {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 3px;
        }

        .chat-bubble {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.81rem;
            line-height: 1.45;
            white-space: pre-line;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .bubble-resident .chat-bubble {
            background: var(--maroon);
            color: #FFFFFF;
            border-bottom-right-radius: 2px;
        }

        .bubble-staff .chat-bubble {
            background: #FFFFFF;
            border: 1.5px solid var(--border-color);
            color: var(--text-primary);
            border-bottom-left-radius: 2px;
        }

        [data-theme="dark"] .bubble-staff .chat-bubble {
            background: #1E293B;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .bubble-time {
            font-size: 0.62rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* Resident Reply Form Inside Modal */
        .modal-reply-form {
            background: rgba(0, 0, 0, 0.02);
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        [data-theme="dark"] .modal-reply-form {
            background: rgba(255, 255, 255, 0.02);
        }

        .reply-action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-reply-send {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-reply-send:hover {
            background: var(--maroon-light);
        }

        .btn-mark-resolved {
            background: #059669;
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            padding: 8px 16px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-mark-resolved:hover {
            background: #047857;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .helpdesk-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Unified Minimalist Resident Sidebar -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Unified Resident Portal Header -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Citizen Helpdesk & Guidance Hub';
            $banner_subtitle = 'Magtanong ukol sa mga serbisyo, magpaabot ng concern, o magbigay ng feedback sa City Social Welfare and Development Office';
            $banner_icon = 'fa-headset';
            $banner_actions = [
                ['label' => 'Citizen Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large', 'type' => 'primary'],
                ['label' => 'Serbisyo & Programa', 'url' => 'services.php', 'icon' => 'fa-hands-helping', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

            <!-- System Alerts -->
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
                    <?php echo $success; // contains safe HTML for ticket number ?>
                    <button class="close-alert" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Helpdesk Grid Layout -->
            <div class="helpdesk-layout">

                <!-- ========================================== -->
                <!-- LEFT PANEL: TICKET & FEEDBACK SUBMISSION   -->
                <!-- ========================================== -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <i class="fas fa-edit"></i>
                            <h2 id="formHeaderTitle">Magtanong sa Helpdesk</h2>
                        </div>
                    </div>

                    <!-- Category Switcher Tabs -->
                    <div class="category-switcher" id="categorySwitcher">
                        <button type="button" class="cat-tab-btn active" data-category="question" onclick="setSubmissionCategory('question')">
                            <i class="fas fa-question-circle"></i>
                            <span>Magtanong</span>
                        </button>
                        <button type="button" class="cat-tab-btn" data-category="concern" onclick="setSubmissionCategory('concern')">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Mag-ulat ng Concern</span>
                        </button>
                        <button type="button" class="cat-tab-btn" data-category="feedback" onclick="setSubmissionCategory('feedback')">
                            <i class="fas fa-star"></i>
                            <span>Feedback / Rating</span>
                        </button>
                    </div>

                    <!-- Category Guidance Callout -->
                    <div class="category-callout" id="categoryCallout">
                        <i class="fas fa-info-circle"></i> <strong>Katanungan ukol sa Programa:</strong> Magtanong ukol sa mga kinakailangang dokumento, proseso, o schedule. Makakatanggap kaagad ng preliminary guidance mula sa Lingkod Kalinga AI.
                    </div>

                    <!-- AI Assistant Advantage Callout -->
                    <div class="ai-banner-pill" id="aiFeaturePill">
                        <i class="fas fa-robot"></i>
                        <div>
                            <strong>Lingkod Kalinga AI Automated Guidance:</strong>
                            <div style="font-size: 0.7rem; opacity: 0.9;">Makakatanggap kaagad ng paunang gabay pagkatapos magsumite habang nakapila ang inyong tiket para sa pagsusuri ng CSWDO caseworkers.</div>
                        </div>
                    </div>

                    <!-- Submission Form -->
                    <form action="feedback.php" method="POST" id="ticketSubmissionForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="category" id="selectedCategoryInput" value="question">

                        <!-- Service Category -->
                        <div class="form-group">
                            <label><i class="fas fa-hands-helping"></i> May Kinalamang Serbisyo / Programa <span class="required">*</span></label>
                            <select name="service_type" id="serviceTypeSelect" required>
                                <option value="">-- Piliin ang Serbisyo o Tanggapan --</option>
                                <option value="AICS Program">AICS Program (Medical, Burial, Cash, Education)</option>
                                <option value="Housing Program">Socialized Housing (Akap Village, San Lorenzo, In-Site)</option>
                                <option value="Appointments">Appointment Booking & Scheduling</option>
                                <option value="Residency Verification">Residency Verification & Identification</option>
                                <option value="Online Hub Portal">Pangkalahatang Serbisyo ng Hub Portal</option>
                            </select>
                        </div>

                        <!-- Subject Line -->
                        <div class="form-group" id="subjectGroup">
                            <label><i class="fas fa-heading"></i> Paksa / Subject <span class="required" id="subjectReqTag">*</span></label>
                            <input type="text" name="subject" id="subjectInput" class="form-control" placeholder="Hal. Ano po ang kailangan para sa Burial Assistance?" required>
                        </div>

                        <!-- Star Rating (Shown only for Feedback) -->
                        <div class="star-rating-wrap" id="starRatingWrap">
                            <label><i class="fas fa-star"></i> Pangkalahatang Marka / Rating <span class="required">*</span></label>
                            <div class="stars" id="starContainer">
                                <i class="fas fa-star star-item" data-value="1" title="1 Bituin - Nangangailangan ng Pagpapabuti"></i>
                                <i class="fas fa-star star-item" data-value="2" title="2 Bituin - Katamtaman"></i>
                                <i class="fas fa-star star-item" data-value="3" title="3 Bituin - Maayos"></i>
                                <i class="fas fa-star star-item" data-value="4" title="4 Bituin - Napakahusay"></i>
                                <i class="fas fa-star star-item" data-value="5" title="5 Bituin - Natatangi at Mahusay"></i>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="0">
                            <small style="font-size: 0.72rem; color: var(--text-muted);" id="ratingHintText">I-click ang bituin upang pumili ng marka (1-5)</small>
                        </div>

                        <!-- Message / Inquiry Body -->
                        <div class="form-group">
                            <label id="messageLabel"><i class="fas fa-comment-dots"></i> Detalye ng Katanungan <span class="required">*</span></label>
                            <textarea name="comment" id="commentTextarea" class="form-control" rows="4" placeholder="Pakidetalye ang inyong katanungan o sitwasyon upang maibigay ang wastong gabay at impormasyon..." required></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="submit_ticket" class="btn-submit-ticket" id="submitTicketBtn">
                            <i class="fas fa-paper-plane"></i> <span id="submitBtnText">Isumite ang Katanungan</span>
                        </button>
                    </form>
                </div>

                <!-- ========================================== -->
                <!-- RIGHT PANEL: MY TICKETS & FEEDBACK LIST    -->
                <!-- ========================================== -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <i class="fas fa-history"></i>
                            <h2>Aking mga Inquiries at Feedback (<?php echo $cnt_total; ?>)</h2>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="ticket-filter-bar">
                        <div class="filter-pills">
                            <button type="button" class="filter-pill active" onclick="filterTickets('all', this)">Lahat (<?php echo $cnt_total; ?>)</button>
                            <button type="button" class="filter-pill" onclick="filterTickets('active', this)">Aktibo (<?php echo $cnt_active; ?>)</button>
                            <button type="button" class="filter-pill" onclick="filterTickets('resolved', this)">Nalutas Na (<?php echo $cnt_resolved; ?>)</button>
                        </div>
                    </div>

                    <!-- List Container -->
                    <?php if (empty($my_feedbacks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px;">Wala ka pang naisusumiteng inquiry o feedback.</p>
                            <span style="font-size: 0.78rem;">Gamitin ang form sa kaliwa upang magtanong ukol sa mga serbisyo o magpaabot ng concern sa CSWDO.</span>
                        </div>
                    <?php else: ?>
                        <div class="ticket-list" id="ticketListContainer">
                            <?php foreach ($my_feedbacks as $fb): 
                                $cat = $fb['category'] ?? 'feedback';
                                $cat_class = 'cat-' . $cat;
                                $cat_title = ($cat === 'question') ? 'Question' : (($cat === 'concern') ? 'Concern' : 'Feedback');
                                $tkt_no = !empty($fb['ticket_number']) ? $fb['ticket_number'] : ('FBK-' . date('Y', strtotime($fb['created_at'])) . '-' . sprintf('%05d', $fb['feedback_id']));
                                $status = $fb['status'] ?? 'open';
                                $is_active = !in_array($status, ['resolved', 'closed']);
                                
                                // Status Badges Config
                                $status_labels = [
                                    'open' => ['Nakapila', 'status-open', 'fa-clock'],
                                    'ai_responded' => ['AI Guidance Ready', 'status-ai_responded', 'fa-robot'],
                                    'under_review' => ['Sinusuri ng Staff', 'status-under_review', 'fa-search'],
                                    'staff_replied' => ['May Tugon ang Staff', 'status-staff_replied', 'fa-reply-all'],
                                    'resolved' => ['Nalutas Na', 'status-resolved', 'fa-check-circle'],
                                    'closed' => ['Sarado', 'status-closed', 'fa-lock']
                                ];
                                $st_info = $status_labels[$status] ?? ['Submitted', 'status-open', 'fa-clock'];
                            ?>
                                <div class="ticket-item" data-status-group="<?php echo $is_active ? 'active' : 'resolved'; ?>">
                                    <div class="ticket-top-row">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="ticket-id-tag"><?php echo htmlspecialchars($tkt_no); ?></span>
                                            <span class="ticket-category-tag <?php echo $cat_class; ?>">
                                                <i class="<?php echo ($cat === 'question') ? 'fas fa-question-circle' : (($cat === 'concern') ? 'fas fa-exclamation-triangle' : 'fas fa-star'); ?>"></i>
                                                <?php echo $cat_title; ?>
                                            </span>
                                        </div>
                                        <span class="status-badge <?php echo $st_info[1]; ?>">
                                            <i class="fas <?php echo $st_info[2]; ?>"></i>
                                            <?php echo $st_info[0]; ?>
                                        </span>
                                    </div>

                                    <h4 class="ticket-subject">
                                        <?php echo htmlspecialchars($fb['subject'] ?: ($fb['service_type'] . ' Feedback')); ?>
                                    </h4>

                                    <div class="ticket-service-pill">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($fb['service_type']); ?>
                                        <?php if ($cat === 'feedback' && !empty($fb['rating'])): ?>
                                            <span style="margin-left: 8px;" class="rating-stars-gold">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="<?php echo $s <= $fb['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ticket-comment-snippet">
                                        <?php echo htmlspecialchars($fb['comment']); ?>
                                    </div>

                                    <div class="ticket-footer-row">
                                        <span class="ticket-date">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y h:i A', strtotime($fb['created_at'])); ?>
                                            <?php if (!empty($fb['staff_fname'])): ?>
                                                &bull; <span style="color: #047857; font-weight: 600;"><i class="fas fa-user-shield"></i> Handled by Staff</span>
                                            <?php endif; ?>
                                        </span>

                                        <button type="button" class="btn-view-thread" onclick="openTicketModal(<?php echo $fb['feedback_id']; ?>)">
                                            <i class="fas fa-comments"></i> Buksan ang Usapan
                                            <?php if (!empty($fb['reply_count'])): ?>
                                                <span class="reply-count-badge"><?php echo $fb['reply_count']; ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- DISCUSSION THREAD & OFFICIAL RESPONSE MODAL             -->
    <!-- ======================================================== -->
    <div class="thread-modal-overlay" id="threadModalOverlay" onclick="handleModalBackdropClick(event)">
        <div class="thread-modal-card">
            <!-- Modal Header -->
            <div class="thread-modal-header">
                <div>
                    <h3 id="modalTicketTitle"><i class="fas fa-comments"></i> Ticket Conversation</h3>
                    <div style="font-size: 0.72rem; opacity: 0.9; margin-top: 2px;" id="modalTicketMeta">Loading ticket details...</div>
                </div>
                <button type="button" class="btn-close-modal" onclick="closeTicketModal()" aria-label="Close Modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="thread-modal-body" id="modalThreadBody">
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px; font-size: 0.85rem;">Kinukuha ang usapan at opisyal na tugon...</p>
                </div>
            </div>

            <!-- Modal Reply & Resolution Action Bar -->
            <form action="feedback.php" method="POST" class="modal-reply-form" id="modalReplyForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="feedback_id" id="modalReplyTicketId" value="0">

                <textarea name="reply_message" id="modalReplyInput" class="form-control" rows="2" placeholder="Magpadala ng karagdagang tanong o sagot sa CSWDO staff..." required></textarea>

                <div class="reply-action-row">
                    <button type="submit" name="submit_resident_reply" class="btn-reply-send">
                        <i class="fas fa-paper-plane"></i> Ipadala ang Tugon (Send Reply)
                    </button>

                    <div id="modalResolutionActionSlot">
                        <!-- Dynamic Mark as Resolved button will appear here if not yet resolved -->
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Dynamic Form Submission JavaScript -->
    <script>
        // Category Switcher Configuration
        const categoryConfig = {
            question: {
                title: "Magtanong sa Helpdesk",
                callout: "<i class=\"fas fa-info-circle\"></i> <strong>Katanungan ukol sa Programa:</strong> Magtanong ukol sa mga kinakailangang dokumento, proseso, o schedule. Makakatanggap kaagad ng preliminary guidance mula sa Lingkod Kalinga AI.",
                calloutClass: "category-callout",
                subjectLabel: "Paksa / Katanungan",
                subjectPlaceholder: "Hal. Ano po ang kailangan para sa Burial Assistance?",
                subjectRequired: true,
                messageLabel: "Detalye ng Katanungan",
                messagePlaceholder: "Pakidetalye ang inyong katanungan o sitwasyon upang maibigay ang wastong gabay at impormasyon...",
                btnText: "Isumite ang Katanungan",
                btnIcon: "fa-paper-plane",
                showRating: false,
                showAIPill: true
            },
            concern: {
                title: "Mag-ulat ng Concern o Reklamo",
                callout: "<i class=\"fas fa-exclamation-triangle\"></i> <strong>Ipaabot ang Inyong Concern:</strong> Ipagbigay-alam ang inyong hinaing, follow-up, o agarang sitwasyon upang masuri at maaksyunan ng CSWDO staff.",
                calloutClass: "category-callout concern-callout",
                subjectLabel: "Paksa ng Concern / Reklamo",
                subjectPlaceholder: "Hal. Kakulangan sa dokumento / Follow-up sa AICS release",
                subjectRequired: true,
                messageLabel: "Detalye ng Concern / Reklamo",
                messagePlaceholder: "Pakilarawan nang malinaw ang inyong sitwasyon o concern kasama ang mga may kinalamang impormasyon...",
                btnText: "Isumite ang Concern",
                btnIcon: "fa-exclamation-circle",
                showRating: false,
                showAIPill: true
            },
            feedback: {
                title: "Magbahagi ng Feedback at Rating",
                callout: "<i class=\"fas fa-star\"></i> <strong>Ibahagi ang Inyong Karanasan:</strong> Magbigay ng marka (1-5 bituin) at komento upang matulungan ang Pamahalaang Lungsod na mas mapabuti pa ang serbisyo.",
                calloutClass: "category-callout feedback-callout",
                subjectLabel: "Paksa / Pamagat ng Feedback",
                subjectPlaceholder: "Hal. Mabilis at maayos na serbisyo sa CSWDO counter",
                subjectRequired: false,
                messageLabel: "Komento at Suhestiyon",
                messagePlaceholder: "Ibahagi ang inyong karanasan, puna, o suhestiyon upang mapabuti ang ating mga serbisyo...",
                btnText: "Ipadala ang Feedback",
                btnIcon: "fa-paper-plane",
                showRating: true,
                showAIPill: false
            }
        };

        function setSubmissionCategory(cat) {
            const cfg = categoryConfig[cat] || categoryConfig.question;
            
            // Update Tab Active
            document.querySelectorAll('.cat-tab-btn').forEach(btn => {
                if (btn.getAttribute('data-category') === cat) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Update Hidden Input
            document.getElementById('selectedCategoryInput').value = cat;

            // Update Labels & Texts
            document.getElementById('formHeaderTitle').textContent = cfg.title;
            const calloutEl = document.getElementById('categoryCallout');
            calloutEl.className = cfg.calloutClass;
            calloutEl.innerHTML = cfg.callout;

            document.getElementById('subjectInput').placeholder = cfg.subjectPlaceholder;
            document.getElementById('subjectReqTag').style.display = cfg.subjectRequired ? 'inline' : 'none';
            document.getElementById('subjectInput').required = cfg.subjectRequired;

            document.getElementById('messageLabel').innerHTML = `<i class="fas fa-comment-dots"></i> ${cfg.messageLabel} <span class="required">*</span>`;
            document.getElementById('commentTextarea').placeholder = cfg.messagePlaceholder;

            document.getElementById('submitBtnText').textContent = cfg.btnText;
            document.getElementById('submitTicketBtn').querySelector('i').className = `fas ${cfg.btnIcon}`;

            // Star Rating Toggle
            const starWrap = document.getElementById('starRatingWrap');
            const ratingInput = document.getElementById('ratingInput');
            if (cfg.showRating) {
                starWrap.classList.add('visible');
                ratingInput.required = true;
            } else {
                starWrap.classList.remove('visible');
                ratingInput.required = false;
                ratingInput.value = 0;
                document.querySelectorAll('.star-item').forEach(s => s.classList.remove('active'));
            }

            // AI Feature Pill Toggle
            const aiPill = document.getElementById('aiFeaturePill');
            if (cfg.showAIPill) {
                aiPill.style.display = 'flex';
            } else {
                aiPill.style.display = 'none';
            }
        }

        // Star Rating Interactions
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('.star-item');
            const ratingInput = document.getElementById('ratingInput');
            const ratingHint = document.getElementById('ratingHintText');

            const ratingLabels = {
                1: '1 Bituin - Nangangailangan ng Malaking Pagpapabuti',
                2: '2 Bituin - Katamtaman / May Kakulangan',
                3: '3 Bituin - Maayos at Katanggap-tanggap',
                4: '4 Bituin - Napakahusay at Mabilis',
                5: '5 Bituin - Natatangi, Maaasahan at Mapagkalinga'
            };

            stars.forEach(star => {
                star.addEventListener('click', function () {
                    const ratingValue = parseInt(this.getAttribute('data-value'));
                    ratingInput.value = ratingValue;
                    ratingHint.textContent = ratingLabels[ratingValue] || '';
                    ratingHint.style.color = 'var(--gold)';
                    ratingHint.style.fontWeight = '700';

                    stars.forEach((s, idx) => {
                        if (idx < ratingValue) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });

                star.addEventListener('mouseenter', function () {
                    const ratingValue = parseInt(this.getAttribute('data-value'));
                    stars.forEach((s, idx) => {
                        if (idx < ratingValue) {
                            s.style.color = 'var(--gold)';
                        }
                    });
                });

                star.addEventListener('mouseleave', function () {
                    stars.forEach(s => {
                        s.style.color = '';
                    });
                });
            });
        });

        // Filter Tickets Function
        function filterTickets(group, btn) {
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');

            const items = document.querySelectorAll('.ticket-item');
            items.forEach(item => {
                const itemGroup = item.getAttribute('data-status-group');
                if (group === 'all' || itemGroup === group) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // ========================================================
        // MODAL LOGIC & AJAX THREAD LOADER
        // ========================================================
        function openTicketModal(feedbackId) {
            const overlay = document.getElementById('threadModalOverlay');
            const modalBody = document.getElementById('modalThreadBody');
            const modalTitle = document.getElementById('modalTicketTitle');
            const modalMeta = document.getElementById('modalTicketMeta');
            const replyTicketId = document.getElementById('modalReplyTicketId');
            const actionSlot = document.getElementById('modalResolutionActionSlot');

            replyTicketId.value = feedbackId;
            actionSlot.innerHTML = '';
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';

            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px; font-size: 0.85rem;">Kinukuha ang buong usapan at opisyal na tugon...</p>
                </div>
            `;

            // AJAX Call to fetch ticket details & discussion thread
            fetch(`feedback.php?fetch_ticket_thread=${feedbackId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        modalBody.innerHTML = `<div class="alert alert-error">${data.message || 'Error fetching ticket'}</div>`;
                        return;
                    }

                    const t = data.ticket;
                    const replies = data.replies || [];
                    const tktNo = t.ticket_number || ('FBK-' + t.feedback_id);

                    // Update Header
                    modalTitle.innerHTML = `<i class="fas fa-ticket-alt"></i> ${escapeHtml(tktNo)}: ${escapeHtml(t.subject || t.service_type)}`;
                    modalMeta.innerHTML = `<i class="fas fa-tag"></i> ${escapeHtml(t.service_type)} &bull; Katayuan: <strong>${t.status.toUpperCase()}</strong> &bull; Petsa: ${t.created_at}`;

                    // Build Body HTML
                    let html = '';

                    // 1. Citizen Inquiry Box
                    html += `
                        <div class="inquiry-summary-box">
                            <div class="inquiry-summary-meta">
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--maroon);">
                                    <i class="fas fa-user"></i> ${escapeHtml(t.first_name + ' ' + t.last_name)} (Ikaw)
                                </span>
                                <span style="font-size: 0.7rem; color: var(--text-muted);">${escapeHtml(t.created_at)}</span>
                            </div>
                            <div class="inquiry-summary-title">${escapeHtml(t.subject || 'Inquiry ukol sa ' + t.service_type)}</div>
                            <div class="inquiry-summary-text">${escapeHtml(t.comment)}</div>
                        </div>
                    `;

                    // 2. Lingkod Kalinga AI Guidance Box (if present)
                    if (t.ai_response) {
                        html += `
                            <div class="ai-guidance-box">
                                <div class="ai-guidance-header">
                                    <div class="ai-guidance-title">
                                        <i class="fas fa-robot"></i> Lingkod Kalinga AI (Awtomatikong Paunang Gabay)
                                    </div>
                                    <span class="ai-guidance-badge">AUTOMATED GUIDANCE</span>
                                </div>
                                <div class="ai-disclaimer-alert">
                                    <i class="fas fa-shield-alt"></i>
                                    <div>
                                        <strong>Paunawa:</strong> Ang tugon na ito ay awtomatikong nilikha ng AI batay sa opisyal na patakaran at gabay ng CSWDO Palayan City. <em>Hindi ito opisyal na desisyon o pag-apruba ng caseworker.</em>
                                    </div>
                                </div>
                                <div class="ai-guidance-text">${escapeHtml(t.ai_response)}</div>
                            </div>
                        `;
                    }

                    // 3. Official CSWDO Staff Response (if present)
                    if (t.staff_response) {
                        const staffName = t.staff_fname ? `${t.staff_fname} ${t.staff_lname}` : 'CSWDO Casework Officer';
                        const staffPos = t.staff_pos || 'Social Welfare Officer';
                        html += `
                            <div class="staff-response-box">
                                <div class="staff-response-header">
                                    <span class="staff-response-badge">
                                        <i class="fas fa-shield-alt"></i> OPISYAL NA TUGON NG CSWDO
                                    </span>
                                    <span class="staff-responder-info">
                                        <i class="fas fa-user-check"></i> ${escapeHtml(staffName)} (${escapeHtml(staffPos)}) &bull; ${escapeHtml(t.staff_responded_at || '')}
                                    </span>
                                </div>
                                <div class="staff-response-text">${escapeHtml(t.staff_response)}</div>
                                ${t.resolution_notes ? `<div class="staff-resolution-note"><strong>Resolution Notes:</strong> ${escapeHtml(t.resolution_notes)}</div>` : ''}
                            </div>
                        `;
                    }

                    // 4. Discussion Thread (Additional replies)
                    if (replies.length > 0) {
                        // Filter out replies that repeat the initial AI response or initial staff response if needed
                        const extraReplies = replies.filter(r => r.sender_type === 'resident' || (r.sender_type === 'staff' && r.message !== t.staff_response));
                        
                        if (extraReplies.length > 0) {
                            html += `
                                <div class="timeline-section-title">
                                    <i class="fas fa-comments"></i> Tala ng Karagdagang Usapan
                                </div>
                                <div class="discussion-thread">
                            `;

                            extraReplies.forEach(rep => {
                                const isResident = (rep.sender_type === 'resident');
                                const rowClass = isResident ? 'bubble-resident' : 'bubble-staff';
                                const senderName = isResident ? 'Ikaw (Residente)' : (rep.first_name ? `${rep.first_name} ${rep.last_name} (${rep.staff_position || 'Staff'})` : 'CSWDO Staff');

                                html += `
                                    <div class="bubble-row ${rowClass}">
                                        <span class="bubble-author">${escapeHtml(senderName)}</span>
                                        <div class="chat-bubble">${escapeHtml(rep.message)}</div>
                                        <span class="bubble-time">${escapeHtml(rep.created_at)}</span>
                                    </div>
                                `;
                            });

                            html += `</div>`;
                        }
                    }

                    modalBody.innerHTML = html;

                    // Resolution Button in Action Bar
                    if (t.status !== 'resolved' && t.status !== 'closed') {
                        actionSlot.innerHTML = `
                            <form action="feedback.php" method="POST" style="margin: 0;" onsubmit="return confirm('Kumpirmahin: Nalutas na ba ang inyong katanungan o concern?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="feedback_id" value="${t.feedback_id}">
                                <button type="submit" name="mark_resolved" class="btn-mark-resolved">
                                    <i class="fas fa-check-circle"></i> I-marka bilang Nalutas Na
                                </button>
                            </form>
                        `;
                    } else {
                        actionSlot.innerHTML = `
                            <span style="font-size: 0.75rem; font-weight: 700; color: #047857; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-double"></i> Nalutas Na ang Ticket
                            </span>
                        `;
                    }
                })
                .catch(err => {
                    modalBody.innerHTML = `<div class="alert alert-error">May naganap na error sa pagkuha ng usapan: ${err.message}</div>`;
                });
        }

        function closeTicketModal() {
            const overlay = document.getElementById('threadModalOverlay');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        function handleModalBackdropClick(event) {
            if (event.target === document.getElementById('threadModalOverlay')) {
                closeTicketModal();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        // Close on Escape Key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeTicketModal();
            }
        });
    </script>

    <!-- Search Modal -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>

</html>

<?php
// notifications.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check user verification status (informational, do not block notifications)
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);
$raw_status = strtolower(trim((string)($user_verify['verification_status'] ?? '')));
$is_verified = ($raw_status === 'verified');
$verification_status = $raw_status ?: 'unverified';

// Mark all as read
if (isset($_GET['mark_read'])) {
    $update_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $update_stmt->execute();
    redirect('notifications.php');
}

// Delete notification
if (isset($_GET['delete'])) {
    $delete_query = "DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $_GET['delete']);
    $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $delete_stmt->execute();
    redirect('notifications.php');
}

$query = "SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = FALSE";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bindParam(':user_id', $_SESSION['user_id']);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get stats
$total_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = :user_id";
$total_stmt = $db->prepare($total_query);
$total_stmt->bindParam(':user_id', $_SESSION['user_id']);
$total_stmt->execute();
$total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

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
    <title>Notifications - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== NOTIFICATIONS TOOLBAR (ALIGNED STATS & ACTIONS) ========== */
        .notifications-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .stats-grid {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 0;
            flex-wrap: wrap;
        }
        .stat-card {
            background: var(--card-bg, #FFFFFF);
            padding: 9px 18px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1.5px solid var(--border-color, rgba(0,0,0,0.08));
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            min-width: 120px;
            flex: 0 1 auto;
            cursor: pointer;
            user-select: none;
            color: inherit;
            font-family: inherit;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            border-color: rgba(127, 3, 3, 0.3);
        }
        .stat-card.active {
            border-color: #7F0303;
            background: var(--card-bg, #FFFFFF);
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.12);
            outline: 2px solid rgba(127, 3, 3, 0.15);
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 0;
            flex-shrink: 0;
            background: rgba(127, 3, 3, 0.08);
            color: #7F0303;
            transition: transform 0.2s ease;
        }
        .stat-card:hover .stat-icon {
            transform: scale(1.06);
        }
        .stat-card.stat-unread .stat-icon {
            background: rgba(2, 132, 199, 0.1);
            color: #0284C7;
        }
        .stat-card.stat-read .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        .stat-card-text {
            display: flex;
            flex-direction: column;
        }
        .stat-card .stat-number {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.1;
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }

        /* ========== TOOLBAR ACTIONS (ALIGNED TO STATS) ========== */
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }
        .btn-mark-all-read {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            background: var(--card-bg, #FFFFFF);
            border: 1.5px solid rgba(127, 3, 3, 0.25);
            color: #7F0303;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(127, 3, 3, 0.06);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            white-space: nowrap;
        }
        .btn-mark-all-read:hover {
            background: #7F0303;
            color: #ffffff;
            border-color: #7F0303;
            box-shadow: 0 6px 18px rgba(127, 3, 3, 0.22);
            transform: translateY(-2px);
        }
        .btn-mark-all-read .unread-count-chip {
            background: #7F0303;
            color: #ffffff;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1.2;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .btn-mark-all-read:hover .unread-count-chip {
            background: #ffffff;
            color: #7F0303;
        }
        .all-caught-up-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #059669;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* ========== NOTIFICATIONS LIST & CARDS ========== */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .notification-card {
            background: var(--card-bg, #FFFFFF);
            border-radius: 16px;
            padding: 18px 22px;
            display: flex;
            gap: 18px;
            align-items: flex-start;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color, rgba(0,0,0,0.08));
            border-left: 4px solid transparent;
            transition: all 0.25s ease;
            position: relative;
        }
        .notification-card:hover {
            transform: translateX(4px);
            box-shadow: 0 8px 22px rgba(0,0,0,0.06);
            border-color: var(--border-color, rgba(0,0,0,0.15));
        }
        .notification-card.unread {
            background: linear-gradient(to right, rgba(127, 3, 3, 0.015), var(--card-bg, #FFFFFF) 20%);
            border-left: 4px solid #7F0303;
            box-shadow: 0 3px 12px rgba(127, 3, 3, 0.05);
        }
        .notif-badge-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
        }
        .notif-body {
            flex: 1;
            min-width: 0;
        }
        .notif-header-line {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .notif-title {
            color: var(--text-primary);
            font-size: 0.98rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.35;
        }
        .badge-unread {
            background: #7F0303;
            color: #ffffff;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
        }
        .dot-unread {
            width: 5px;
            height: 5px;
            background: #ffffff;
            border-radius: 50%;
            display: inline-block;
        }
        .notif-text {
            color: var(--text-secondary);
            font-size: 0.88rem;
            line-height: 1.55;
            margin: 0 0 10px 0;
            word-break: break-word;
        }
        .notif-footer-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.75rem;
        }
        .notif-time {
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .notif-category-pill {
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid currentColor;
        }
        .notif-action-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #7F0303;
            text-decoration: none;
            padding: 3px 10px;
            background: rgba(127, 3, 3, 0.06);
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .notif-action-link:hover {
            background: #7F0303;
            color: #ffffff;
        }
        .notif-actions {
            margin-left: 6px;
            align-self: flex-start;
        }
        .delete-btn {
            color: var(--text-muted);
            text-decoration: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.88rem;
        }
        .delete-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            background: var(--card-bg, #FFFFFF);
            border-radius: 18px;
            border: 1px dashed var(--border-color);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.35;
            display: block;
            margin-bottom: 14px;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .empty-state p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin: 0;
        }

        /* ========== RESPONSIVE MOBILE SMALL UI/UX ========== */
        @media (max-width: 768px) {
            .notifications-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                margin-bottom: 14px;
            }
            .stats-grid {
                width: 100%;
                display: flex;
                gap: 6px;
                flex-wrap: nowrap;
            }
            .stat-card {
                flex: 1 1 0;
                min-width: 0;
                padding: 6px 8px !important;
                gap: 6px !important;
                border-radius: 10px !important;
            }
            .stat-card .stat-icon {
                width: 26px !important;
                height: 26px !important;
                font-size: 0.72rem !important;
                border-radius: 6px !important;
            }
            .stat-card .stat-number {
                font-size: 0.95rem !important;
                line-height: 1 !important;
            }
            .stat-card .stat-label {
                font-size: 0.56rem !important;
                letter-spacing: 0.02em !important;
            }
            .toolbar-actions {
                width: 100%;
                margin-left: 0;
            }
            .btn-mark-all-read {
                width: 100%;
                justify-content: center;
                padding: 6px 14px !important;
                font-size: 0.74rem !important;
                border-radius: 20px !important;
            }
            .all-caught-up-pill {
                width: 100%;
                justify-content: center;
                padding: 6px 14px !important;
                font-size: 0.74rem !important;
            }
            .notifications-list {
                gap: 8px !important;
            }
            .notification-card {
                padding: 10px 12px !important;
                gap: 10px !important;
                border-radius: 12px !important;
                border-left-width: 3px !important;
            }
            .notif-badge-icon {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.8rem !important;
                border-radius: 8px !important;
            }
            .notif-header-line {
                margin-bottom: 3px !important;
                gap: 6px !important;
            }
            .notif-title {
                font-size: 0.82rem !important;
                line-height: 1.3 !important;
            }
            .badge-unread {
                padding: 1px 6px !important;
                font-size: 0.58rem !important;
            }
            .notif-text {
                font-size: 0.74rem !important;
                line-height: 1.4 !important;
                margin: 0 0 6px 0 !important;
            }
            .notif-footer-meta {
                gap: 8px !important;
                font-size: 0.68rem !important;
            }
            .notif-category-pill {
                padding: 1px 7px !important;
                font-size: 0.62rem !important;
            }
            .notif-action-link {
                padding: 2px 8px !important;
                font-size: 0.68rem !important;
            }
            .delete-btn {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.74rem !important;
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                gap: 4px;
            }
            .stat-card {
                padding: 5px 6px !important;
            }
        }

        /* ========== DARK MODE OVERRIDES ========== */
        [data-theme="dark"] .btn-mark-all-read {
            color: #F87171;
            border-color: rgba(248, 113, 113, 0.35);
        }
        [data-theme="dark"] .btn-mark-all-read .unread-count-chip {
            background: #F87171;
            color: #152227;
        }
        [data-theme="dark"] .btn-mark-all-read:hover {
            background: #F87171;
            color: #152227;
            border-color: #F87171;
        }
        [data-theme="dark"] .stat-card.active {
            border-color: #F87171;
            outline-color: rgba(248, 113, 113, 0.25);
        }
        [data-theme="dark"] .notification-card.unread {
            background: linear-gradient(to right, rgba(248, 113, 113, 0.06), var(--card-bg) 25%);
            border-left-color: #F87171;
        }
        [data-theme="dark"] .notif-action-link {
            color: #F87171;
            background: rgba(248, 113, 113, 0.12);
        }
        [data-theme="dark"] .notif-action-link:hover {
            background: #F87171;
            color: #152227;
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
            $banner_title = 'Notifications & Alerts';
            $banner_subtitle = 'Stay updated with your latest status updates, casework approvals, and advisories';
            $banner_icon = 'fa-bell';
            $banner_actions = [
                ['label' => 'Citizen Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large', 'type' => 'primary'],
                ['label' => 'Announcements', 'url' => 'announcements.php', 'icon' => 'fa-bullhorn', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

        <!-- Notifications Toolbar (Aligned Stats Cards & Mark All as Read) -->
        <div class="notifications-toolbar">
            <div class="stats-grid">
                <button type="button" class="stat-card active" data-filter="all" title="Show all notifications">
                    <div class="stat-icon"><i class="fas fa-bell"></i></div>
                    <div class="stat-card-text">
                        <span class="stat-number"><?php echo (int)$total_count; ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                </button>
                <button type="button" class="stat-card stat-unread" data-filter="unread" title="Show unread only">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-card-text">
                        <span class="stat-number" id="unreadStatNum"><?php echo (int)$unread_count; ?></span>
                        <span class="stat-label">Unread</span>
                    </div>
                </button>
                <button type="button" class="stat-card stat-read" data-filter="read" title="Show read only">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-text">
                        <span class="stat-number"><?php echo (int)($total_count - $unread_count); ?></span>
                        <span class="stat-label">Read</span>
                    </div>
                </button>
            </div>

            <div class="toolbar-actions">
                <?php if($unread_count > 0): ?>
                <a href="?mark_read=1" class="btn-mark-all-read" title="Mark all notifications as read">
                    <i class="fas fa-check-double"></i>
                    <span>Mark All as Read</span>
                    <span class="unread-count-chip"><?php echo (int)$unread_count; ?></span>
                </a>
                <?php else: ?>
                <span class="all-caught-up-pill">
                    <i class="fas fa-check-circle"></i> All caught up
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if($stmt->rowCount() > 0): ?>
        <div class="notifications-list" id="notificationsList">
            <?php while($notif = $stmt->fetch(PDO::FETCH_ASSOC)): 
                $type = strtolower(trim((string)($notif['type'] ?? 'general')));
                
                // Rich metadata per notification type
                $type_map = [
                    'appointment' => [
                        'icon'  => 'fas fa-calendar-check',
                        'bg'    => 'rgba(2, 132, 199, 0.12)',
                        'color' => '#0284c7',
                        'label' => 'Appointment',
                        'link'  => 'appointments.php',
                        'btn'   => 'View Appointments'
                    ],
                    'aics' => [
                        'icon'  => 'fas fa-hand-holding-heart',
                        'bg'    => 'rgba(16, 185, 129, 0.12)',
                        'color' => '#059669',
                        'label' => 'AICS Assistance',
                        'link'  => 'aics-requests.php',
                        'btn'   => 'View Requests'
                    ],
                    'housing' => [
                        'icon'  => 'fas fa-house-user',
                        'bg'    => 'rgba(217, 119, 6, 0.12)',
                        'color' => '#d97706',
                        'label' => 'Housing Desk',
                        'link'  => 'view-housing.php',
                        'btn'   => 'View Housing'
                    ],
                    'verification' => [
                        'icon'  => 'fas fa-shield-alt',
                        'bg'    => 'rgba(124, 58, 237, 0.12)',
                        'color' => '#7c3aed',
                        'label' => 'Verification',
                        'link'  => 'verify-resident.php',
                        'btn'   => 'Verify Account'
                    ],
                    'general' => [
                        'icon'  => 'fas fa-bullhorn',
                        'bg'    => 'rgba(127, 3, 3, 0.10)',
                        'color' => '#7F0303',
                        'label' => 'Notice / Advisory',
                        'link'  => 'announcements.php',
                        'btn'   => 'View Bulletins'
                    ]
                ];
                $meta = $type_map[$type] ?? [
                    'icon'  => 'fas fa-bell',
                    'bg'    => 'rgba(127, 3, 3, 0.08)',
                    'color' => '#7F0303',
                    'label' => ucfirst($type),
                    'link'  => '',
                    'btn'   => ''
                ];
                $is_unread = empty($notif['is_read']);
                $target_link = $meta['link'];
                if ($type === 'housing' && !empty($target_link)) {
                    if (preg_match('/PAL-HOU-\d{4}-(\d+)/i', $notif['message'], $matches)) {
                        $extracted_id = intval($matches[1]);
                        if ($extracted_id > 0) {
                            $target_link = 'view-housing.php?id=' . $extracted_id;
                        }
                    }
                }
            ?>
            <div class="notification-card <?php echo $is_unread ? 'unread' : 'is-read'; ?>" data-status="<?php echo $is_unread ? 'unread' : 'read'; ?>">
                <div class="notif-badge-icon" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                    <i class="<?php echo $meta['icon']; ?>"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-header-line">
                        <h4 class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></h4>
                        <?php if($is_unread): ?>
                        <span class="badge-unread"><span class="dot-unread"></span> NEW</span>
                        <?php endif; ?>
                    </div>
                    <p class="notif-text"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                    <div class="notif-footer-meta">
                        <span class="notif-time">
                            <i class="far fa-clock"></i> <?php echo date('M d, Y • h:i A', strtotime($notif['created_at'])); ?>
                        </span>
                        <span class="notif-category-pill" style="color: <?php echo $meta['color']; ?>; border-color: <?php echo $meta['color']; ?>33; background: <?php echo $meta['bg']; ?>;">
                            <?php echo htmlspecialchars($meta['label']); ?>
                        </span>
                        <?php if(!empty($target_link) && !empty($meta['btn'])): ?>
                        <a href="<?php echo htmlspecialchars($target_link); ?>" class="notif-action-link">
                            <?php echo htmlspecialchars($meta['btn']); ?> <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <a href="?delete=<?php echo (int)$notif['notification_id']; ?>" class="delete-btn" onclick="return confirm('Delete this notification?')" title="Delete notification">
                        <i class="far fa-trash-alt"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>

            <!-- Client Filter Empty State -->
            <div id="filterEmptyState" class="empty-state" style="display: none;">
                <i class="fas fa-filter"></i>
                <h3>No Matching Notifications</h3>
                <p>There are no notifications matching the selected filter.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>No Notifications</h3>
            <p>You don't have any notifications yet. We'll notify you when there are updates.</p>
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

// ========== STATS CARD INTERACTIVE FILTERING ==========
const statFilterBtns = document.querySelectorAll('.stat-card[data-filter]');
if (statFilterBtns.length > 0) {
    statFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.getAttribute('data-filter');
            statFilterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.notification-card');
            let visible = 0;
            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                if (filter === 'all' || status === filter) {
                    card.style.display = 'flex';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            const filterEmpty = document.getElementById('filterEmptyState');
            if (filterEmpty) {
                filterEmpty.style.display = (visible === 0 && cards.length > 0) ? 'block' : 'none';
            }
        });
    });
}
</script>

<!-- Search Modal (Req 7) -->
<?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

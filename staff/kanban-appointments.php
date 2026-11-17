<?php
// staff/kanban-appointments.php - Interactive Kanban Board for Appointments Management
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Access check: Get assigned categories for this staff
$staff_id_query = "SELECT id FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_data = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_data ? $staff_data['id'] : 0;

$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch all appointments
$query = "SELECT a.*, s.service_name, sc.category_name, u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture, r.barangay 
          FROM appointments a 
          LEFT JOIN services s ON a.service_id = s.service_id
          LEFT JOIN service_categories sc ON s.category_id = sc.category_id
          JOIN users u ON a.user_id = u.user_id
          LEFT JOIN residents r ON u.user_id = r.user_id
          ORDER BY a.appointment_date DESC, a.appointment_time ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_appts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define 5 Kanban Columns
$columns = [
    'pending' => [],
    'confirmed' => [],
    'active' => [],
    'completed' => [],
    'cancelled' => []
];

foreach ($all_appts as &$app) {
    $st = strtolower($app['status'] ?? 'pending');
    if (in_array($st, ['pending', 'for_review', 'needs_information'])) {
        $columns['pending'][] = $app;
    } elseif (in_array($st, ['confirmed', 'rescheduled'])) {
        $columns['confirmed'][] = $app;
    } elseif (in_array($st, ['checked_in', 'in_progress'])) {
        $columns['active'][] = $app;
    } elseif ($st === 'completed') {
        $columns['completed'][] = $app;
    } else {
        $columns['cancelled'][] = $app;
    }
}
unset($app);

$column_config = [
    'pending' => [
        'title' => 'Pending Review',
        'icon'  => 'fa-clock',
        'color' => '#D97706',
        'bg'    => 'rgba(217, 119, 6, 0.12)',
        'target_status' => 'pending'
    ],
    'confirmed' => [
        'title' => 'Confirmed & Slotted',
        'icon'  => 'fa-calendar-check',
        'color' => '#059669',
        'bg'    => 'rgba(5, 150, 105, 0.12)',
        'target_status' => 'confirmed'
    ],
    'active' => [
        'title' => 'Active / Serving',
        'icon'  => 'fa-user-clock',
        'color' => '#2563EB',
        'bg'    => 'rgba(37, 99, 235, 0.12)',
        'target_status' => 'checked_in'
    ],
    'completed' => [
        'title' => 'Completed',
        'icon'  => 'fa-circle-check',
        'color' => '#7C3AED',
        'bg'    => 'rgba(124, 58, 237, 0.12)',
        'target_status' => 'completed'
    ],
    'cancelled' => [
        'title' => 'Cancelled / Rejected',
        'icon'  => 'fa-circle-xmark',
        'color' => '#DC2626',
        'bg'    => 'rgba(220, 38, 38, 0.12)',
        'target_status' => 'cancelled'
    ]
];

$page_title = 'Appointments Kanban Board - Staff Portal';
$active_nav = 'appointments';
require_once __DIR__ . '/includes/staff-head.php';
?>

<style>
.kanban-controls-card {
    background: var(--ref-card);
    border-radius: 20px;
    padding: 18px 24px;
    margin-bottom: 22px;
    border: 1px solid var(--ref-border);
    box-shadow: var(--ref-shadow-sm);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.kanban-view-pills {
    display: inline-flex;
    background: var(--ref-card-subtle);
    padding: 4px;
    border-radius: 50px;
    border: 1px solid var(--ref-border);
    gap: 4px;
}

.kanban-view-pill {
    padding: 7px 18px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ref-muted);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s ease;
}

.kanban-view-pill.active {
    background: var(--ref-maroon);
    color: #FFFFFF;
    box-shadow: 0 2px 8px rgba(127, 3, 3, 0.35);
}

.kanban-search-box {
    position: relative;
    min-width: 260px;
    flex: 1;
    max-width: 380px;
}

.kanban-search-box input {
    width: 100%;
    padding: 9px 14px 9px 36px;
    border-radius: 12px;
    border: 1px solid var(--ref-border);
    background: var(--ref-canvas);
    color: var(--ref-text);
    font-size: 0.85rem;
    font-family: inherit;
}

.kanban-search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ref-muted);
    font-size: 0.85rem;
}

.kanban-board-container {
    display: grid;
    grid-template-columns: repeat(5, minmax(290px, 1fr));
    gap: 18px;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 24px;
    min-height: calc(100vh - 280px);
}

.kanban-column {
    background: var(--ref-card-subtle);
    border-radius: 18px;
    border: 1px solid var(--ref-border);
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 240px);
    transition: all 0.2s ease;
}

.kanban-column.drag-over {
    border-color: var(--ref-maroon);
    background: rgba(127, 3, 3, 0.05);
    box-shadow: 0 0 16px rgba(127, 3, 3, 0.15);
}

.column-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--ref-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--ref-card);
    border-radius: 18px 18px 0 0;
}

.column-header-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--ref-text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.column-count-badge {
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 800;
}

.card-list {
    padding: 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
    min-height: 120px;
}

.kanban-card {
    background: var(--ref-card);
    border: 1px solid var(--ref-border);
    border-radius: 14px;
    padding: 14px;
    box-shadow: var(--ref-shadow-sm);
    cursor: grab;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    user-select: none;
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--ref-shadow-md);
    border-color: rgba(127, 3, 3, 0.3);
}

.kanban-card.dragging {
    opacity: 0.45;
    transform: scale(0.97);
}

.kanban-card.card-just-moved {
    animation: cardPulse 1.2s ease;
}

@keyframes cardPulse {
    0% { transform: scale(0.96); box-shadow: 0 0 0 3px #059669; }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.card-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.card-ref-badge {
    font-size: 0.72rem;
    font-weight: 800;
    font-family: monospace;
    background: var(--ref-card-subtle);
    padding: 2px 8px;
    border-radius: 6px;
    color: var(--ref-maroon);
    border: 1px solid var(--ref-border);
}

.card-window-pill {
    font-size: 0.68rem;
    font-weight: 700;
    background: #E0E7FF;
    color: #3730A3;
    padding: 2px 7px;
    border-radius: 6px;
}

.card-client-name {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--ref-text);
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-service-name {
    font-size: 0.78rem;
    color: var(--ref-muted);
    margin-bottom: 10px;
    line-height: 1.3;
}

.card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--ref-muted);
    padding-top: 8px;
    border-top: 1px dashed var(--ref-border);
}

.card-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.card-actions-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}

.card-btn {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid var(--ref-border);
    background: var(--ref-card-subtle);
    color: var(--ref-text);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.18s;
}

.card-btn:hover {
    background: var(--ref-maroon);
    color: #FFFFFF;
    border-color: var(--ref-maroon);
}

.empty-placeholder {
    padding: 30px 15px;
    text-align: center;
    color: var(--ref-muted);
    font-size: 0.8rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    opacity: 0.65;
}

.empty-placeholder i {
    font-size: 1.6rem;
}

/* Modal */
.kanban-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.kanban-modal-backdrop.active {
    display: flex;
}
.kanban-modal-box {
    background: var(--ref-card);
    border-radius: 20px;
    max-width: 440px;
    width: 100%;
    padding: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 1px solid var(--ref-border);
    animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.94) translateY(10px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

/* Toast Notification System */
@keyframes toastSlideIn {
    0% { opacity: 0; transform: translateX(50px) scale(0.95); }
    100% { opacity: 1; transform: translateX(0) scale(1); }
}
@keyframes toastSlideOut {
    0% { opacity: 1; transform: translateX(0) scale(1); }
    100% { opacity: 0; transform: translateX(50px) scale(0.95); }
}
</style>

<body>
<div class="app-container">
    <?php include __DIR__ . '/includes/staff-sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Universal Top Hero Banner -->
        <?php
        $banner_title = "Appointments Kanban Flow";
        $banner_subtitle = "Visual pipeline to monitor, drag, and advance appointments from intake to completion";
        $banner_icon = "fa-columns";
        $banner_actions = [
            ["label" => "QR Live Scanner", "url" => "qr-scanner.php", "icon" => "fa-qrcode", "type" => "primary"]
        ];
        include __DIR__ . "/../includes/portal-banner.php";
        ?>

        <!-- Kanban View Switcher & Live Filter Toolbar -->
        <div class="kanban-controls-card">
            <div class="kanban-view-pills">
                <a href="manage-appointments.php" class="kanban-view-pill">
                    <i class="fas fa-table-list"></i> Table List
                </a>
                <a href="kanban-appointments.php" class="kanban-view-pill active">
                    <i class="fas fa-columns"></i> Kanban Board
                </a>
            </div>

            <div class="kanban-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="kanbanSearchInput" placeholder="Filter citizen, ref #, or service...">
            </div>

            <div style="display: flex; gap: 8px;">
                <select id="categoryFilter" style="padding: 8px 14px; border-radius: 10px; border: 1px solid var(--ref-border); background: var(--ref-canvas); color: var(--ref-text); font-size: 0.82rem; font-weight: 600;">
                    <option value="all">All Categories</option>
                    <option value="AICS">AICS Welfare</option>
                    <option value="Housing">Housing Programs</option>
                </select>
            </div>
        </div>

        <!-- Kanban 5-Column Pipeline -->
        <div class="kanban-board-container">
            <?php foreach ($columns as $status_key => $items): ?>
                <?php $cfg = $column_config[$status_key]; ?>
                <div class="kanban-column" data-col-status="<?php echo $status_key; ?>" data-target-status="<?php echo $cfg['target_status']; ?>">
                    <div class="column-header">
                        <div class="column-header-title">
                            <i class="fas <?php echo $cfg['icon']; ?>" style="color: <?php echo $cfg['color']; ?>;"></i>
                            <span><?php echo $cfg['title']; ?></span>
                        </div>
                        <span class="column-count-badge" id="count-<?php echo $status_key; ?>" style="background: <?php echo $cfg['bg']; ?>; color: <?php echo $cfg['color']; ?>;">
                            <?php echo count($items); ?>
                        </span>
                    </div>

                    <div class="card-list" id="list-<?php echo $status_key; ?>">
                        <?php if (empty($items)): ?>
                            <div class="empty-placeholder">
                                <i class="fas fa-inbox"></i>
                                <span>No appointments</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($items as $app): ?>
                                <div class="kanban-card" 
                                     draggable="true" 
                                     id="card-<?php echo $app['appointment_id']; ?>"
                                     data-id="<?php echo $app['appointment_id']; ?>"
                                     data-ref="<?php echo htmlspecialchars($app['reference_number'] ?? ''); ?>"
                                     data-name="<?php echo htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?>"
                                     data-service="<?php echo htmlspecialchars($app['service_name'] ?? ''); ?>"
                                     data-category="<?php echo htmlspecialchars($app['category_name'] ?? ''); ?>"
                                     data-current-status="<?php echo strtolower($app['status'] ?? 'pending'); ?>">
                                    
                                    <div class="card-top-row">
                                        <span class="card-ref-badge"><?php echo htmlspecialchars($app['reference_number'] ?? ('#'.$app['appointment_id'])); ?></span>
                                        <?php
                                        $status_badges = [
                                            'pending'           => ['label' => 'Pending', 'bg' => '#FEF3C7', 'color' => '#B45309', 'icon' => 'fa-hourglass-start'],
                                            'for_review'        => ['label' => 'For Review', 'bg' => '#E0F2FE', 'color' => '#0369A1', 'icon' => 'fa-magnifying-glass'],
                                            'confirmed'         => ['label' => 'Confirmed', 'bg' => '#DCFCE7', 'color' => '#15803D', 'icon' => 'fa-check'],
                                            'checked_in'        => ['label' => 'Checked-In', 'bg' => '#DBEAFE', 'color' => '#1D4ED8', 'icon' => 'fa-location-dot'],
                                            'in_progress'       => ['label' => 'In Progress', 'bg' => '#F3E8FF', 'color' => '#7E22CE', 'icon' => 'fa-play'],
                                            'completed'         => ['label' => 'Completed', 'bg' => '#D1FAE5', 'color' => '#047857', 'icon' => 'fa-circle-check'],
                                            'rescheduled'       => ['label' => 'Rescheduled', 'bg' => '#FEF9C3', 'color' => '#A16207', 'icon' => 'fa-arrows-rotate'],
                                            'needs_information' => ['label' => 'Needs Info', 'bg' => '#FFEDD5', 'color' => '#C2410C', 'icon' => 'fa-file-circle-question'],
                                            'rejected'          => ['label' => 'Rejected', 'bg' => '#FEE2E2', 'color' => '#B91C1C', 'icon' => 'fa-xmark'],
                                            'no_show'           => ['label' => 'No-Show', 'bg' => '#F1F5F9', 'color' => '#475569', 'icon' => 'fa-user-slash'],
                                            'cancelled'         => ['label' => 'Cancelled', 'bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => 'fa-ban']
                                        ];
                                        $st_key = strtolower($app['status'] ?? 'pending');
                                        $badge_info = $status_badges[$st_key] ?? ['label' => ucfirst($st_key), 'bg' => '#F1F5F9', 'color' => '#475569', 'icon' => 'fa-circle'];
                                        ?>
                                        <span class="card-status-pill" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; background: <?php echo $badge_info['bg']; ?>; color: <?php echo $badge_info['color']; ?>;">
                                            <i class="fas <?php echo $badge_info['icon']; ?>"></i> <?php echo $badge_info['label']; ?>
                                        </span>
                                    </div>

                                    <div class="card-client-name">
                                        <i class="fas fa-user-circle" style="color: var(--ref-maroon);"></i>
                                        <span><?php echo htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')); ?></span>
                                    </div>

                                    <div class="card-service-name">
                                        <?php echo htmlspecialchars($app['service_name'] ?? 'General Inquiry'); ?>
                                    </div>

                                    <div class="card-meta-row">
                                        <span class="card-date-pill">
                                            <i class="fas fa-calendar-day"></i>
                                            <?php echo !empty($app['appointment_date']) ? date('M d, Y', strtotime($app['appointment_date'])) : 'TBD'; ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i>
                                            <?php echo !empty($app['appointment_time']) ? date('h:i A', strtotime($app['appointment_time'])) : 'Anytime'; ?>
                                        </span>
                                    </div>

                                    <div class="card-actions-row">
                                        <a href="view-appointment.php?id=<?php echo $app['appointment_id']; ?>" class="card-btn" title="View Full Details">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        <a href="../appointment-slip.php?ref=<?php echo urlencode($app['reference_number'] ?? ''); ?>" target="_blank" class="card-btn" title="Print Slip">
                                            <i class="fas fa-print"></i> Slip
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Move Confirmation Modal -->
<div class="kanban-modal-backdrop" id="moveConfirmModal">
    <div class="kanban-modal-box">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--ref-text); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrows-split-up-and-left" style="color: var(--ref-maroon);"></i>
            <span>Confirm Status Move</span>
        </h3>
        <p style="font-size: 0.85rem; color: var(--ref-muted); margin-bottom: 16px; line-height: 1.45;" id="moveModalPrompt">
            Are you sure you want to advance this appointment?
        </p>

        <div style="margin-bottom: 14px;">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; margin-bottom: 6px;">Target Process Status *</label>
            <select id="modalStatusSelect" style="width: 100%; padding: 9px 12px; border-radius: 10px; border: 1px solid var(--ref-border); background: var(--ref-canvas); color: var(--ref-text); font-weight: 700; font-family: inherit; font-size: 0.85rem;">
                <optgroup label="Stage 1: Intake & Review">
                    <option value="pending">⏳ Pending (Submitted)</option>
                    <option value="for_review">🔍 For Review (Staff Assigned)</option>
                    <option value="needs_information">📝 Needs Information (Missing Documents)</option>
                </optgroup>
                <optgroup label="Stage 2: Scheduling & Booking">
                    <option value="confirmed">✅ Confirmed (Schedule Approved)</option>
                    <option value="rescheduled">🔄 Rescheduled (Change Date / Time)</option>
                </optgroup>
                <optgroup label="Stage 3: On-Site Presence & Handling">
                    <option value="checked_in">📍 Checked-In (Citizen Arrived On-Site)</option>
                    <option value="in_progress">▶️ In Progress (Interview / Assessment)</option>
                </optgroup>
                <optgroup label="Stage 4: Resolution & Closeout">
                    <option value="completed">🎉 Completed (Resolved & Released)</option>
                    <option value="rejected">❌ Rejected (Not Qualified / Invalid)</option>
                    <option value="no_show">🚫 No-Show (Did Not Arrive)</option>
                    <option value="cancelled">🛑 Cancelled</option>
                </optgroup>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: var(--ref-muted); text-transform: uppercase; margin-bottom: 6px;">Status Notes / Action Summary</label>
            <input type="text" id="modalStatusNotes" placeholder="Optional notes for citizen audit log..." style="width: 100%; padding: 8px 12px; border-radius: 10px; border: 1px solid var(--ref-border); background: var(--ref-canvas); color: var(--ref-text); font-family: inherit;">
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" id="btnCancelMove" style="padding: 8px 18px; border-radius: 10px; border: 1px solid var(--ref-border); background: var(--ref-card-subtle); color: var(--ref-text); font-weight: 700; cursor: pointer;">Cancel</button>
            <button type="button" id="btnConfirmMove" style="padding: 8px 20px; border-radius: 10px; border: none; background: var(--ref-maroon); color: #FFFFFF; font-weight: 700; cursor: pointer;">Advance Status</button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<?php include __DIR__ . '/../includes/portal-search-modal.php'; ?>

<script>
(function() {
    let draggedCard = null;
    let pendingMove = null;

    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    const modal = document.getElementById('moveConfirmModal');
    const btnCancel = document.getElementById('btnCancelMove');
    const btnConfirm = document.getElementById('btnConfirmMove');
    const searchInput = document.getElementById('kanbanSearchInput');
    const categoryFilter = document.getElementById('categoryFilter');

    // Drag events
    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.getAttribute('data-id'));
            e.dataTransfer.effectAllowed = 'move';
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over'));
        });
    });

    // Column drop targets
    columns.forEach(col => {
        col.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        col.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        col.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            if (!draggedCard) return;

            const targetCol = this;
            const targetStatusKey = targetCol.getAttribute('data-col-status');
            const targetStatus = targetCol.getAttribute('data-target-status');
            const sourceCol = draggedCard.closest('.kanban-column');
            const sourceStatusKey = sourceCol.getAttribute('data-col-status');

            if (targetStatusKey === sourceStatusKey) return;

            const apptId = draggedCard.getAttribute('data-id');
            const refNo = draggedCard.getAttribute('data-ref');
            const clientName = draggedCard.getAttribute('data-name');

            pendingMove = {
                card: draggedCard,
                sourceCol: sourceCol,
                targetCol: targetCol,
                apptId: apptId,
                refNo: refNo,
                targetStatus: targetStatus
            };

            const statusSelect = document.getElementById('modalStatusSelect');
            if (statusSelect) {
                if (targetStatusKey === 'pending') {
                    statusSelect.value = 'for_review';
                } else if (targetStatusKey === 'confirmed') {
                    statusSelect.value = 'confirmed';
                } else if (targetStatusKey === 'active') {
                    statusSelect.value = 'checked_in';
                } else if (targetStatusKey === 'completed') {
                    statusSelect.value = 'completed';
                } else if (targetStatusKey === 'cancelled') {
                    statusSelect.value = 'cancelled';
                }
            }

            document.getElementById('moveModalPrompt').innerHTML = 
                `Move appointment <strong>${refNo}</strong> (${clientName}) to <strong>${targetCol.querySelector('.column-header-title span').textContent}</strong>?`;

            modal.classList.add('active');
        });
    });

    // Close modal
    btnCancel.addEventListener('click', function() {
        modal.classList.remove('active');
        pendingMove = null;
    });

    // Custom Sliding Toast Notification (Replaces native browser alert)
    function showNotification(type, title, message) {
        if (window.showProcessToast) {
            window.showProcessToast(message, type !== 'success', title);
            return;
        }

        let container = document.getElementById('kanbanToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'kanbanToastContainer';
            container.style.cssText = 'position: fixed; top: 24px; right: 24px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; max-width: 380px; width: calc(100% - 48px);';
            document.body.appendChild(container);
        }

        const isSuccess = type === 'success';
        const toast = document.createElement('div');
        toast.style.cssText = 'pointer-events: auto; background: var(--ref-card); color: var(--ref-text); border-radius: 14px; padding: 14px 16px; border: 1.5px solid ' + (isSuccess ? '#10B981' : '#EF4444') + '; box-shadow: 0 14px 35px rgba(0,0,0,0.22); display: flex; align-items: flex-start; gap: 12px; animation: toastSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;';

        const iconHtml = isSuccess 
            ? '<div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;"><i class="fas fa-check"></i></div>'
            : '<div style="width: 32px; height: 32px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #DC2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem;"><i class="fas fa-triangle-exclamation"></i></div>';

        toast.innerHTML = `
            ${iconHtml}
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 0.88rem; font-weight: 800; color: var(--ref-text); margin-bottom: 3px;">${title}</div>
                <div style="font-size: 0.78rem; color: var(--ref-muted); line-height: 1.4;">${message}</div>
            </div>
            <button type="button" style="background: none; border: none; color: var(--ref-muted); cursor: pointer; padding: 2px 4px; font-size: 1.1rem; line-height: 1;" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastSlideOut 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            setTimeout(() => toast.remove(), 350);
        }, 4000);
    }
    window.showNotification = showNotification;

    // Confirm Move Action via AJAX
    btnConfirm.addEventListener('click', function() {
        if (!pendingMove) return;
        const move = pendingMove;
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

        const chosenStatus = document.getElementById('modalStatusSelect') ? document.getElementById('modalStatusSelect').value : move.targetStatus;
        const notesVal = document.getElementById('modalStatusNotes').value;

        fetch('update-appointment-status-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: move.apptId,
                status: chosenStatus,
                notes: notesVal
            })
        })
        .then(res => res.json())
        .then(data => {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Advance Status';
            modal.classList.remove('active');

            if (data.success) {
                const targetList = move.targetCol.querySelector('.card-list');
                const emptyMsg = targetList.querySelector('.empty-placeholder');
                if (emptyMsg) emptyMsg.remove();

                targetList.prepend(move.card);
                move.card.classList.add('card-just-moved');
                move.card.setAttribute('data-current-status', chosenStatus);

                // Update card status pill
                const statusPill = move.card.querySelector('.card-status-pill');
                if (statusPill) {
                    const statusConfig = {
                        'pending':           { label: 'Pending', bg: '#FEF3C7', color: '#B45309', icon: 'fa-hourglass-start' },
                        'for_review':        { label: 'For Review', bg: '#E0F2FE', color: '#0369A1', icon: 'fa-magnifying-glass' },
                        'confirmed':         { label: 'Confirmed', bg: '#DCFCE7', color: '#15803D', icon: 'fa-check' },
                        'checked_in':        { label: 'Checked-In', bg: '#DBEAFE', color: '#1D4ED8', icon: 'fa-location-dot' },
                        'in_progress':       { label: 'In Progress', bg: '#F3E8FF', color: '#7E22CE', icon: 'fa-play' },
                        'completed':         { label: 'Completed', bg: '#D1FAE5', color: '#047857', icon: 'fa-circle-check' },
                        'rescheduled':       { label: 'Rescheduled', bg: '#FEF9C3', color: '#A16207', icon: 'fa-arrows-rotate' },
                        'needs_information': { label: 'Needs Info', bg: '#FFEDD5', color: '#C2410C', icon: 'fa-file-circle-question' },
                        'rejected':          { label: 'Rejected', bg: '#FEE2E2', color: '#B91C1C', icon: 'fa-xmark' },
                        'no_show':           { label: 'No-Show', bg: '#F1F5F9', color: '#475569', icon: 'fa-user-slash' },
                        'cancelled':         { label: 'Cancelled', bg: '#FEE2E2', color: '#991B1B', icon: 'fa-ban' }
                    };
                    const sInfo = statusConfig[chosenStatus] || { label: chosenStatus, bg: '#F1F5F9', color: '#475569', icon: 'fa-circle' };
                    statusPill.style.background = sInfo.bg;
                    statusPill.style.color = sInfo.color;
                    statusPill.innerHTML = `<i class="fas ${sInfo.icon}"></i> ${sInfo.label}`;
                }

                setTimeout(() => move.card.classList.remove('card-just-moved'), 1200);

                updateCounters();
                showNotification('success', 'Appointment Advanced', data.message || `Status updated to ${chosenStatus}.`);
            } else {
                showNotification('error', 'Status Update Failed', data.message || 'Failed to update appointment status.');
            }
            pendingMove = null;
        })
        .catch(err => {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Advance Status';
            modal.classList.remove('active');
            showNotification('error', 'Network Error', 'A connection or server error occurred.');
            pendingMove = null;
        });
    });

    function updateCounters() {
        columns.forEach(col => {
            const list = col.querySelector('.card-list');
            const cardCount = list.querySelectorAll('.kanban-card').length;
            const badge = col.querySelector('.column-count-badge');
            if (badge) badge.textContent = cardCount;

            if (cardCount === 0 && !list.querySelector('.empty-placeholder')) {
                list.innerHTML = `<div class="empty-placeholder"><i class="fas fa-inbox"></i><span>No appointments</span></div>`;
            }
        });
    }

    // Live search & category filtering
    function filterKanban() {
        const query = searchInput.value.toLowerCase().trim();
        const cat = categoryFilter.value;

        cards.forEach(card => {
            const ref = (card.getAttribute('data-ref') || '').toLowerCase();
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const service = (card.getAttribute('data-service') || '').toLowerCase();
            const cardCat = card.getAttribute('data-category') || '';

            const matchQuery = !query || ref.includes(query) || name.includes(query) || service.includes(query);
            const matchCat = (cat === 'all') || cardCat.toLowerCase().includes(cat.toLowerCase());

            if (matchQuery && matchCat) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterKanban);
    categoryFilter.addEventListener('change', filterKanban);

})();
</script>
</body>
</html>

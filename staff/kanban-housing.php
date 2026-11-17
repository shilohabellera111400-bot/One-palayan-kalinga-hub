<?php
// staff/kanban-housing.php - Interactive Housing Process Flow Kanban Board for Staff
require_once __DIR__ . '/../config/database.php';

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

$has_housing_access = in_array('Housing', $assigned_categories);

if (!$has_housing_access) {
    $_SESSION['error'] = "You do not have permission to manage Housing applications.";
    redirect('../staff-dashboard.php');
}

// Fetch staff details
$staff_info_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.id = :id";
$staff_info_stmt = $db->prepare($staff_info_query);
$staff_info_stmt->bindParam(':id', $staff_id);
$staff_info_stmt->execute();
$staff_info = $staff_info_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch active programs for filter pills
$prog_stmt = $db->query("SELECT program_id, program_name, housing_type, total_units FROM housing_programs WHERE status = 'active' ORDER BY program_name ASC");
$active_programs = $prog_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all Housing beneficiaries
$query = "SELECT h.*, p.program_name, p.housing_type as prog_type, p.location, p.total_units,
                 u.first_name, u.last_name, u.contact_number, u.email, u.profile_picture
          FROM housing_beneficiaries h
          JOIN housing_programs p ON h.program_id = p.program_id
          JOIN users u ON h.user_id = u.user_id
          ORDER BY h.application_date DESC, h.beneficiary_id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map beneficiaries into 5 process flow columns
$columns = [
    'pending'   => [],
    'qualified' => [],
    'approved'  => [],
    'released'  => [],
    'rejected'  => []
];

foreach ($all_beneficiaries as $ben) {
    $st = $ben['status'] ?? 'pending';
    if (array_key_exists($st, $columns)) {
        $columns[$st][] = $ben;
    } else {
        $columns['pending'][] = $ben;
    }
}

// Column configuration metadata
$column_config = [
    'pending' => [
        'title' => 'Submitted / Screening',
        'icon'  => 'fa-clock',
        'color' => '#FF9800',
        'bg'    => 'rgba(255, 152, 0, 0.12)',
        'step'  => 'Stage 1'
    ],
    'qualified' => [
        'title' => 'Assessment & Interview',
        'icon'  => 'fa-clipboard-check',
        'color' => '#2196F3',
        'bg'    => 'rgba(33, 150, 243, 0.12)',
        'step'  => 'Stage 2'
    ],
    'approved' => [
        'title' => 'Approved & Allocated',
        'icon'  => 'fa-check-circle',
        'color' => '#4CAF50',
        'bg'    => 'rgba(76, 175, 80, 0.12)',
        'step'  => 'Stage 3'
    ],
    'released' => [
        'title' => 'Turned Over / Released',
        'icon'  => 'fa-key',
        'color' => '#9C27B0',
        'bg'    => 'rgba(156, 39, 176, 0.12)',
        'step'  => 'Stage 4'
    ],
    'rejected' => [
        'title' => 'Did Not Qualify',
        'icon'  => 'fa-times-circle',
        'color' => '#EF5350',
        'bg'    => 'rgba(244, 67, 54, 0.12)',
        'step'  => 'Disqualified'
    ]
];

// Profile picture path
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) 
    ? '../' . $profile_picture 
    : '';

// Dark mode check
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

$page_title = 'Housing Process Flow Kanban - Staff Portal';
$active_nav = 'housing';
require_once __DIR__ . '/includes/staff-head.php';
?>
<style>
    :root {
        --maroon: var(--ref-maroon, #7F0303);
        --maroon-light: var(--ref-maroon-light, #9e0404);
        --radius-card: 18px;
        --radius-element: 12px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background: var(--ref-canvas, #F8FAFC);
        color: var(--ref-text, #1E293B);
        line-height: 1.5;
        min-height: 100vh;
    }

    .app-container { display: flex; min-height: 100vh; }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: 260px;
        padding: 28px 32px 40px;
        min-height: 100vh;
        background: var(--ref-canvas, #F8FAFC);
        display: flex;
        flex-direction: column;
    }

    /* Process Flow Pipeline Ribbon */
    .flow-pipeline-bar {
        display: flex;
        align-items: center;
        background: var(--ref-card, #FFFFFF);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: var(--radius-card);
        padding: 12px 18px;
        margin-bottom: 20px;
        box-shadow: var(--ref-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
        overflow-x: auto;
        gap: 12px;
    }
    .flow-step-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        border-radius: 12px;
        background: var(--ref-card-subtle, #F1F5F9);
        flex: 1;
        min-width: 175px;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }
    .flow-step-item:hover {
        border-color: var(--ref-border);
        transform: translateY(-1px);
    }
    .flow-step-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 800;
        color: white;
        flex-shrink: 0;
    }
    .flow-step-info {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    .flow-step-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--ref-text);
    }
    .flow-step-desc {
        font-size: 0.68rem;
        color: var(--ref-muted);
        margin-top: 2px;
    }
    .flow-arrow-sep {
        color: var(--ref-muted);
        opacity: 0.45;
        font-size: 0.85rem;
    }

    /* Kanban Toolbar Controls */
    .kanban-toolbar {
        background: var(--ref-card, #FFFFFF);
        padding: 14px 20px;
        border-radius: var(--radius-card);
        border: 1px solid var(--ref-border, #E2E8F0);
        box-shadow: var(--ref-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .search-box {
        position: relative;
        flex: 1;
        min-width: 260px;
        max-width: 380px;
    }
    .search-box i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ref-muted, #64748B);
        font-size: 0.85rem;
    }
    .search-box input {
        width: 100%;
        padding: 9px 14px 9px 38px;
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 50px;
        background: var(--ref-card-subtle, #F8FAFC);
        color: var(--ref-text, #1E293B);
        font-size: 0.82rem;
        font-family: inherit;
        transition: all 0.2s;
    }
    .search-box input:focus {
        outline: none;
        border-color: var(--maroon);
        background: var(--ref-card, #FFFFFF);
        box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
    }

    .filter-pills-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .filter-pill {
        background: var(--ref-card-subtle, #F1F5F9);
        border: 1px solid var(--ref-border, #E2E8F0);
        color: var(--ref-text, #1E293B);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-pill:hover {
        background: var(--ref-card);
        border-color: var(--maroon);
        color: var(--maroon);
    }
    .filter-pill.active {
        background: var(--maroon);
        color: #FFFFFF;
        border-color: var(--maroon);
        box-shadow: 0 2px 8px rgba(127, 3, 3, 0.25);
    }

    /* Kanban Container & Grid */
    .kanban-container {
        flex: 1;
        overflow-x: auto;
        padding-bottom: 24px;
    }
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(5, minmax(290px, 1fr));
        gap: 18px;
        align-items: start;
        min-width: 1480px;
    }

    .kanban-column {
        background: var(--ref-card, #FFFFFF);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: var(--radius-card);
        padding: 16px;
        min-height: 640px;
        display: flex;
        flex-direction: column;
        box-shadow: var(--ref-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
        transition: all 0.25s ease;
    }
    .kanban-column.drag-over {
        border-color: var(--maroon-light);
        background: rgba(127, 3, 3, 0.04);
        box-shadow: var(--ref-shadow-md, 0 8px 24px rgba(0,0,0,0.08));
        transform: scale(1.01);
    }

    .column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 2px solid var(--ref-border, #E2E8F0);
    }
    .column-header-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .column-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    .column-header h3 {
        font-size: 0.88rem;
        font-weight: 800;
        color: var(--ref-text, #1E293B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .column-count {
        padding: 3px 10px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        color: white;
    }

    .card-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        flex: 1;
        min-height: 500px;
    }
    .card-list.empty {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .empty-placeholder {
        text-align: center;
        color: var(--ref-muted, #64748B);
        opacity: 0.55;
        padding: 40px 10px;
        font-size: 0.8rem;
    }
    .empty-placeholder i {
        font-size: 2rem;
        margin-bottom: 8px;
        display: block;
    }

    /* Kanban Card */
    .kanban-card {
        background: var(--ref-card, #FFFFFF);
        border: 1.5px solid var(--ref-border, #E2E8F0);
        border-radius: var(--radius-element);
        padding: 14px;
        cursor: grab;
        box-shadow: var(--ref-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .kanban-card:active { cursor: grabbing; }
    .kanban-card.dragging {
        opacity: 0.35;
        transform: scale(0.96);
        border-style: dashed;
    }
    .kanban-card:hover {
        box-shadow: var(--ref-shadow-md, 0 8px 24px rgba(0,0,0,0.08));
        border-color: var(--maroon-light);
        transform: translateY(-2px);
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .tracking-id {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--maroon);
        background: rgba(127, 3, 3, 0.08);
        padding: 2px 8px;
        border-radius: 6px;
        letter-spacing: 0.3px;
    }
    .type-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 50px;
        text-transform: capitalize;
    }
    .type-badge.relocation {
        background: rgba(33, 150, 243, 0.12);
        color: #1976D2;
        border: 1px solid rgba(33, 150, 243, 0.25);
    }
    .type-badge.in_site {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.25);
    }

    .applicant-name {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--ref-text, #1E293B);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .mini-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--maroon);
        color: white;
        font-size: 0.65rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }
    .mini-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .card-program {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--ref-muted, #64748B);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .card-program i { color: var(--maroon); }

    .card-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 10px;
    }
    .card-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
        background: var(--ref-card-subtle, #F1F5F9);
        color: var(--ref-text, #334155);
        border: 1px solid var(--ref-border, #E2E8F0);
    }
    .card-chip.highlight {
        background: rgba(76, 175, 80, 0.12);
        color: #2E7D32;
        border-color: rgba(76, 175, 80, 0.3);
    }
    .card-chip.schedule {
        background: rgba(33, 150, 243, 0.12);
        color: #1565C0;
        border-color: rgba(33, 150, 243, 0.3);
    }

    .card-footer-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.72rem;
        color: var(--ref-muted, #64748B);
        border-top: 1px dashed var(--ref-border, #E2E8F0);
        padding-top: 10px;
        margin-top: 6px;
    }
    .card-income {
        font-weight: 700;
        color: var(--ref-text, #1E293B);
    }
    .card-actions {
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .card-btn {
        background: var(--ref-card-subtle, #F1F5F9);
        color: var(--ref-text, #334155);
        border: 1px solid var(--ref-border, #E2E8F0);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        font-size: 0.75rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    .card-btn:hover {
        background: var(--maroon);
        color: white;
        border-color: var(--maroon);
        transform: scale(1.06);
    }
    .card-btn.card-btn-adjust {
        color: var(--maroon);
    }
    .card-btn.card-btn-delete {
        color: #EF4444;
    }
    .card-btn.card-btn-delete:hover {
        background: #DC2626 !important;
        color: white !important;
        border-color: #DC2626;
    }

    /* Modal Backdrop & Dialog */
    .kanban-modal-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .kanban-modal-backdrop.active {
        opacity: 1;
        visibility: visible;
    }
    .kanban-modal-box {
        background: var(--ref-card, #FFFFFF);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 20px;
        max-width: 520px;
        width: 100%;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        overflow: hidden;
        transform: scale(0.92) translateY(12px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        color: var(--ref-text, #1E293B);
        max-height: 92vh;
        display: flex;
        flex-direction: column;
    }
    .kanban-modal-backdrop.active .kanban-modal-box {
        transform: scale(1) translateY(0);
    }
    .kanban-modal-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--ref-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--ref-card-subtle, #F8FAFC);
    }
    .kanban-modal-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(127, 3, 3, 0.12);
        color: var(--maroon);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .kanban-modal-title-group h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
        color: var(--ref-text);
    }
    .kanban-modal-title-group p {
        font-size: 0.75rem;
        color: var(--ref-muted);
        margin: 2px 0 0 0;
    }
    .kanban-modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--ref-muted);
        cursor: pointer;
        margin-left: auto;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .kanban-modal-close:hover {
        background: rgba(0, 0, 0, 0.08);
        color: var(--ref-text);
    }
    .kanban-modal-body {
        padding: 22px 24px;
        overflow-y: auto;
    }

    /* Summary Card Preview inside Modal */
    .modal-card-summary {
        background: var(--ref-card-subtle, #F8FAFC);
        border: 1px solid var(--ref-border, #E2E8F0);
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 18px;
    }
    .modal-card-summary .summary-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .summary-id {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--maroon);
        background: rgba(127, 3, 3, 0.08);
        padding: 2px 8px;
        border-radius: 6px;
    }
    .summary-applicant {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .summary-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--maroon);
        color: white;
        font-size: 0.78rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }
    .summary-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .summary-name {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--ref-text);
    }
    .summary-program {
        font-size: 0.76rem;
        color: var(--ref-muted);
    }

    /* Status Transition Visual */
    .status-transition-visual {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding-top: 10px;
        border-top: 1px dashed var(--ref-border);
    }
    .transition-step {
        flex: 1;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .transition-arrow {
        color: var(--ref-muted);
        font-size: 0.85rem;
    }

    /* Modal Form Inputs */
    .modal-form-group {
        margin-bottom: 16px;
    }
    .modal-form-group label {
        display: block;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--ref-text);
        margin-bottom: 6px;
    }
    .modal-form-group input, 
    .modal-form-group select, 
    .modal-form-group textarea {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--ref-border, #CBD5E1);
        border-radius: 10px;
        font-size: 0.82rem;
        background: var(--ref-canvas, #FFFFFF);
        color: var(--ref-text, #1E293B);
        font-family: inherit;
        box-sizing: border-box;
    }
    .modal-form-group input:focus,
    .modal-form-group select:focus,
    .modal-form-group textarea:focus {
        outline: none;
        border-color: var(--maroon);
        box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
    }
    .modal-form-group small {
        display: block;
        font-size: 0.72rem;
        color: var(--ref-muted);
        margin-top: 4px;
        line-height: 1.4;
    }

    .modal-context-box {
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 16px;
        border: 1px solid transparent;
    }
    .modal-context-box.info {
        background: rgba(33, 150, 243, 0.08);
        border-color: rgba(33, 150, 243, 0.25);
    }
    .modal-context-box.warning {
        background: rgba(245, 158, 11, 0.08);
        border-color: rgba(245, 158, 11, 0.25);
    }
    .modal-context-box.danger {
        background: rgba(239, 68, 68, 0.08);
        border-color: rgba(239, 68, 68, 0.25);
    }

    .modal-audit-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 0.72rem;
        color: var(--ref-muted);
        line-height: 1.45;
        padding: 10px 14px;
        background: var(--ref-card-subtle, #F8FAFC);
        border: 1px solid var(--ref-border);
        border-radius: 10px;
    }
    .modal-audit-note i {
        color: var(--maroon);
        margin-top: 2px;
        flex-shrink: 0;
    }

    .kanban-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--ref-border);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        background: var(--ref-card-subtle);
    }
    .btn-modal-cancel {
        padding: 9px 20px;
        border-radius: 50px;
        font-size: 0.82rem;
        font-weight: 600;
        background: var(--ref-card);
        color: var(--ref-text);
        border: 1px solid var(--ref-border);
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-modal-cancel:hover {
        background: var(--ref-card-subtle);
    }
    .btn-modal-confirm {
        padding: 9px 24px;
        border-radius: 50px;
        font-size: 0.82rem;
        font-weight: 600;
        background: var(--maroon);
        color: #FFFFFF;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(127, 3, 3, 0.28);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-modal-confirm:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(127, 3, 3, 0.38);
    }
    .btn-modal-confirm:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Card pulse effect after move */
    .card-just-moved {
        animation: cardHighlight 1.3s ease;
    }
    @keyframes cardHighlight {
        0% { transform: scale(1.03); box-shadow: 0 0 0 3px var(--maroon); }
        50% { box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.4); }
        100% { transform: scale(1); box-shadow: none; }
    }

    /* Toast Notification */
    .toast-container {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 100000;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .toast {
        background: #1E293B;
        color: white;
        padding: 13px 22px;
        border-radius: 14px;
        font-size: 0.85rem;
        font-weight: 600;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(255, 255, 255, 0.1);
        max-width: 420px;
    }
    .toast.toast-success {
        background: #1b5e20 !important;
        border-left: 5px solid #4caf50;
    }
    .toast.toast-error {
        background: #b71c1c !important;
        border-left: 5px solid #ef5350;
    }
    @keyframes toastIn {
        from { transform: translateY(20px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    @media (max-width: 992px) {
        .main-content { margin-left: 0; padding: 20px; }
    }
</style>
</head>
<body>
<div class="app-container">
    <!-- Modern Staff Sidebar -->
    <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Unified Staff Header Bar -->
        <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

        <!-- Elevated Big Card Container -->
        <div class="staff-big-card">
            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "Housing Applications & Beneficiaries";
            $banner_subtitle = "Review, verify, and process socialized housing applications and resettlement pipelines";
            $banner_icon = "fa-home";
            $banner_actions = [
                ["label" => "Table View", "url" => "manage-housing.php", "icon" => "fa-table", "type" => "secondary"],
                ["label" => "Kanban Board", "url" => "kanban-housing.php", "icon" => "fa-columns", "type" => "primary"]
            ];
            include __DIR__ . "/../includes/portal-banner.php";
            ?>

            <!-- Process Flow Pipeline Ribbon -->
            <div class="flow-pipeline-bar">
                <div class="flow-step-item">
                    <div class="flow-step-num" style="background: #FF9800;">1</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Screening</span>
                        <span class="flow-step-desc">Application &amp; Residency Verification</span>
                    </div>
                </div>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <div class="flow-step-item">
                    <div class="flow-step-num" style="background: #2196F3;">2</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Assessment &amp; Interview</span>
                        <span class="flow-step-desc">Caseworker Intake &amp; Field Check</span>
                    </div>
                </div>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <div class="flow-step-item">
                    <div class="flow-step-num" style="background: #4CAF50;">3</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Unit / Grant Allocated</span>
                        <span class="flow-step-desc">Clearance Granted &amp; Lot Assigned</span>
                    </div>
                </div>
                <div class="flow-arrow-sep"><i class="fas fa-chevron-right"></i></div>
                <div class="flow-step-item">
                    <div class="flow-step-num" style="background: #9C27B0;">4</div>
                    <div class="flow-step-info">
                        <span class="flow-step-title">Turnover &amp; Move-in</span>
                        <span class="flow-step-desc">Grant Disbursed or Keys Handed</span>
                    </div>
                </div>
            </div>

            <!-- Kanban Toolbar / Filter Controls -->
            <div class="kanban-toolbar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="kanbanSearch" placeholder="Filter applicant name, ID, program, or unit...">
                </div>
                
                <div class="filter-pills-group" id="typePills">
                    <button class="filter-pill active" data-type="all"><i class="fas fa-layer-group"></i> All Types</button>
                    <button class="filter-pill" data-type="relocation"><i class="fas fa-home"></i> Relocation</button>
                    <button class="filter-pill" data-type="in_site"><i class="fas fa-hammer"></i> In-Site</button>
                </div>

                <div class="filter-pills-group" id="programPills">
                    <button class="filter-pill active" data-program="all"><i class="fas fa-city"></i> All Programs</button>
                    <?php foreach($active_programs as $p): ?>
                        <button class="filter-pill" data-program="<?php echo $p['program_id']; ?>">
                            <?php echo htmlspecialchars($p['program_name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kanban Board Grid -->
            <div class="kanban-container">
                <div class="kanban-board">
                    <?php foreach($column_config as $status_key => $config): ?>
                    <div class="kanban-column" id="col-<?php echo $status_key; ?>" data-status="<?php echo $status_key; ?>">
                        <div class="column-header">
                            <div class="column-header-title">
                                <div class="column-icon" style="background: <?php echo $config['bg']; ?>; color: <?php echo $config['color']; ?>;">
                                    <i class="fas <?php echo $config['icon']; ?>"></i>
                                </div>
                                <div>
                                    <h3><?php echo $config['title']; ?></h3>
                                </div>
                            </div>
                            <span class="column-count" id="count-<?php echo $status_key; ?>" style="background: <?php echo $config['color']; ?>;">
                                <?php echo count($columns[$status_key]); ?>
                            </span>
                        </div>

                        <div class="card-list" id="list-<?php echo $status_key; ?>">
                            <?php if(empty($columns[$status_key])): ?>
                                <div class="empty-placeholder">
                                    <i class="fas fa-inbox"></i>
                                    <span>No applications in this stage</span>
                                </div>
                            <?php else: ?>
                                <?php foreach($columns[$status_key] as $b): 
                                    $app_year = !empty($b['application_date']) ? date('Y', strtotime($b['application_date'])) : date('Y');
                                    $tracking_code = "HSG-" . $app_year . "-" . str_pad($b['beneficiary_id'], 5, '0', STR_PAD_LEFT);
                                    $app_full_name = trim($b['first_name'] . ' ' . $b['last_name']);
                                    $app_initials = strtoupper(substr($b['first_name'], 0, 1) . substr($b['last_name'], 0, 1));
                                    $app_pic = !empty($b['profile_picture']) && file_exists(__DIR__ . '/../' . $b['profile_picture']) 
                                        ? '../' . $b['profile_picture'] 
                                        : '';
                                    $is_insite = (($b['housing_type'] ?? '') === 'in_site');
                                    $type_label = $is_insite ? 'In-Site Housing' : 'Relocation Housing';
                                    $formatted_income = !empty($b['monthly_income']) ? '₱' . number_format($b['monthly_income'], 2) : 'No declared income';
                                ?>
                                    <div class="kanban-card" draggable="true" id="card-<?php echo $b['beneficiary_id']; ?>"
                                        data-id="<?php echo $b['beneficiary_id']; ?>"
                                        data-tracking="<?php echo htmlspecialchars($tracking_code); ?>"
                                        data-applicant="<?php echo htmlspecialchars($app_full_name); ?>"
                                        data-type="<?php echo htmlspecialchars($b['housing_type']); ?>"
                                        data-program-id="<?php echo $b['program_id']; ?>"
                                        data-program-name="<?php echo htmlspecialchars($b['program_name']); ?>"
                                        data-status="<?php echo $b['status']; ?>"
                                        data-unit="<?php echo htmlspecialchars($b['unit_assigned'] ?? ''); ?>"
                                        data-interview="<?php echo htmlspecialchars($b['interview_date'] ?? ''); ?>"
                                        data-move-in="<?php echo htmlspecialchars($b['move_in_date'] ?? ''); ?>"
                                        data-income="<?php echo htmlspecialchars($formatted_income); ?>"
                                        data-search="<?php echo strtolower($app_full_name . ' ' . $tracking_code . ' ' . $b['program_name'] . ' ' . ($b['unit_assigned'] ?? '')); ?>">
                                        
                                        <div class="card-top">
                                            <span class="tracking-id"><?php echo htmlspecialchars($tracking_code); ?></span>
                                            <span class="type-badge <?php echo $b['housing_type']; ?>"><?php echo $is_insite ? 'In-Site' : 'Relocation'; ?></span>
                                        </div>

                                        <div class="applicant-name">
                                            <div class="mini-avatar">
                                                <?php if(!empty($app_pic)): ?>
                                                    <img src="<?php echo htmlspecialchars($app_pic); ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?php echo $app_initials; ?>
                                                <?php endif; ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($app_full_name); ?></span>
                                        </div>

                                        <div class="card-program">
                                            <i class="fas fa-home"></i>
                                            <span><?php echo htmlspecialchars($b['program_name']); ?></span>
                                        </div>

                                        <div class="card-chips">
                                            <?php if(!empty($b['unit_assigned'])): ?>
                                                <span class="card-chip highlight" title="<?php echo $is_insite ? 'Grant Reference' : 'Assigned Unit'; ?>">
                                                    <i class="fas <?php echo $is_insite ? 'fa-file-invoice' : 'fa-door-open'; ?>"></i>
                                                    <?php echo htmlspecialchars($b['unit_assigned']); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if(!empty($b['interview_date']) && $b['interview_date'] != '0000-00-00'): ?>
                                                <span class="card-chip schedule" title="Interview / Inspection Schedule">
                                                    <i class="fas fa-calendar-check"></i>
                                                    <?php echo date('M d, Y', strtotime($b['interview_date'])); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if(!empty($b['move_in_date']) && $b['move_in_date'] != '0000-00-00'): ?>
                                                <span class="card-chip" title="Official Move-In Date">
                                                    <i class="fas fa-key"></i>
                                                    <?php echo date('M d, Y', strtotime($b['move_in_date'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="card-footer-meta">
                                            <span class="card-income" title="Monthly Household Income">
                                                <i class="fas fa-wallet" style="color:var(--ref-muted); font-size:0.68rem;"></i> <?php echo $formatted_income; ?>
                                            </span>
                                            <div class="card-actions">
                                                <button type="button" class="card-btn card-btn-adjust" title="Adjust Status &amp; Details" onclick="openAdjustmentModalDirect(<?php echo $b['beneficiary_id']; ?>)">
                                                    <i class="fas fa-sliders-h"></i>
                                                </button>
                                                <a href="view-housing.php?id=<?php echo $b['beneficiary_id']; ?>" class="card-btn" title="View application details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="card-btn card-btn-delete" title="Delete application" onclick="openDeleteModal(event, <?php echo $b['beneficiary_id']; ?>, '<?php echo htmlspecialchars($tracking_code); ?>', '<?php echo htmlspecialchars(addslashes($app_full_name)); ?>', '<?php echo htmlspecialchars(addslashes($b['program_name'])); ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

<!-- Interactive Status Adjustment & Confirmation Modal -->
<div class="kanban-modal-backdrop" id="adjustConfirmModal" aria-hidden="true" role="dialog" aria-labelledby="modalTitle">
    <div class="kanban-modal-box">
        <div class="kanban-modal-header">
            <div class="kanban-modal-icon-wrap">
                <i class="fas fa-home"></i>
            </div>
            <div class="kanban-modal-title-group">
                <h3 id="modalTitle">Adjust Housing Application Status</h3>
                <p>Palayan Kalinga Hub &bull; Housing Casework &amp; Resettlement Pipeline</p>
            </div>
            <button type="button" class="kanban-modal-close" id="btnCloseAdjustModal" aria-label="Close modal">&times;</button>
        </div>

        <div class="kanban-modal-body">
            <!-- Card Summary Preview -->
            <div class="modal-card-summary">
                <div class="summary-top">
                    <span class="summary-id" id="modalTrackingId">HSG-2026-00000</span>
                    <span class="type-badge" id="modalTypeBadge">Relocation</span>
                </div>
                <div class="summary-applicant">
                    <div class="summary-avatar" id="modalAvatar">JD</div>
                    <div class="summary-details">
                        <span class="summary-name" id="modalApplicantName">Applicant Name</span>
                        <span class="summary-program" id="modalProgramName"><i class="fas fa-building"></i> Program Name</span>
                    </div>
                </div>

                <!-- Visual Transition -->
                <div class="status-transition-visual">
                    <div class="transition-step" id="modalVisualFrom">
                        <i class="fas fa-clock"></i> <span>Submitted</span>
                    </div>
                    <div class="transition-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="transition-step" id="modalVisualTo">
                        <i class="fas fa-clipboard-check"></i> <span>Assessment</span>
                    </div>
                </div>
            </div>

            <!-- Target Status Selector -->
            <div class="modal-form-group">
                <label for="modalSelectStatus"><i class="fas fa-exchange-alt"></i> Pipeline Stage / Status *</label>
                <select id="modalSelectStatus">
                    <option value="pending">Submitted / Initial Screening</option>
                    <option value="qualified">Qualified / Caseworker Assessment &amp; Interview</option>
                    <option value="approved">Approved / Unit or Grant Voucher Allocated</option>
                    <option value="released">Released / Move-in Officialized or Disbursed</option>
                    <option value="rejected">Disqualified / Did Not Qualify</option>
                </select>
            </div>

            <!-- Context-Aware Dynamic Input Fields -->
            
            <!-- Qualified: Schedule Interview / Lot Inspection -->
            <div id="sectionQualified" class="modal-context-box info" style="display:none;">
                <div style="font-weight:700; color:#1565C0; font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-calendar-alt"></i> Schedule Caseworker Interview &amp; Assessment
                </div>
                <div class="modal-form-group" style="margin-bottom:10px;">
                    <label for="modalInterviewDate">Appointment / Inspection Date</label>
                    <input type="date" id="modalInterviewDate" min="<?php echo date('Y-m-d'); ?>">
                    <small><i class="fas fa-info-circle"></i> Auto-schedules and confirms an appointment at <strong>Window 2 (Housing Division)</strong> with a resident QR token.</small>
                </div>
                <div class="modal-form-group" style="margin-bottom:0;">
                    <label for="modalQualNotes">Caseworker Assessment Notes</label>
                    <textarea id="modalQualNotes" rows="2" placeholder="Record evaluation notes, family situation, income verification..."></textarea>
                </div>
            </div>

            <!-- Approved: Unit / Voucher Allocation -->
            <div id="sectionApproved" class="modal-context-box warning" style="display:none;">
                <div style="font-weight:700; color:#B45309; font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-check-circle"></i> Unit or Grant Allocation
                </div>
                <div class="modal-form-group" style="margin-bottom:10px;">
                    <label for="modalUnitAssigned" id="lblUnitAssigned">Unit Assignment / Block &amp; Lot</label>
                    <input type="text" id="modalUnitAssigned" placeholder="e.g. Block 4 Lot 12 or GRANT-2026-089">
                    <small>System will verify against duplicate allocations and program limits.</small>
                </div>
                <div class="modal-form-group" style="margin-bottom:0;">
                    <label for="modalApprovalNotes">Approval Clearance Remarks</label>
                    <textarea id="modalApprovalNotes" rows="2" placeholder="Remarks or clearance terms for Mayor / Division Chief review..."></textarea>
                </div>
            </div>

            <!-- Released: Official Move-In or Grant Release -->
            <div id="sectionReleased" class="modal-context-box info" style="display:none;">
                <div style="font-weight:700; color:#7B1FA2; font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-key"></i> Official Move-in / Turnover Date
                </div>
                <div class="modal-form-group" style="margin-bottom:0;">
                    <label for="modalMoveInDate">Official Move-in / Grant Release Date</label>
                    <input type="date" id="modalMoveInDate" value="<?php echo date('Y-m-d'); ?>">
                    <small>Official date beneficiary takes residency or receives housing voucher.</small>
                </div>
            </div>

            <!-- Rejected: Rejection Reason -->
            <div id="sectionRejected" class="modal-context-box danger" style="display:none;">
                <div style="font-weight:700; color:#DC2626; font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-times-circle"></i> Disqualification Reason *
                </div>
                <div class="modal-form-group" style="margin-bottom:0;">
                    <label for="modalRejectionRemarks">Specific Ineligibility Grounds *</label>
                    <textarea id="modalRejectionRemarks" rows="2" placeholder="e.g. Exceeds income threshold / Non-resident / Prior socialized housing grant recipient..."></textarea>
                    <small>Will be recorded in beneficiary history and notified to applicant.</small>
                </div>
            </div>

            <div class="modal-audit-note">
                <i class="fas fa-shield-alt"></i>
                <span>This action updates beneficiary records, synchronizes appointment schedules, logs changes to the municipal audit trail, and dispatches in-app/email notifications.</span>
            </div>
        </div>

        <div class="kanban-modal-footer">
            <button type="button" class="btn-modal-cancel" id="btnCancelAdjust">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-modal-confirm" id="btnConfirmAdjust">
                <i class="fas fa-check"></i> Save &amp; Move
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="kanban-modal-backdrop" id="deleteConfirmModal" aria-hidden="true" role="dialog" aria-labelledby="deleteModalTitle">
    <div class="kanban-modal-box">
        <div class="kanban-modal-header" style="background: rgba(220, 38, 38, 0.06); border-bottom: 1px solid rgba(220, 38, 38, 0.18);">
            <div class="kanban-modal-icon-wrap" style="background: rgba(220, 38, 38, 0.12); color: #DC2626;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="kanban-modal-title-group">
                <h3 id="deleteModalTitle" style="color: #DC2626;">Delete Housing Application</h3>
                <p>Palayan Kalinga Hub &bull; Permanent Removal</p>
            </div>
            <button type="button" class="kanban-modal-close" id="btnCloseDeleteModal" aria-label="Close modal">&times;</button>
        </div>
        <div class="kanban-modal-body">
            <p style="font-size: 0.95rem; line-height: 1.5; margin-bottom: 14px;">
                Are you sure you want to permanently delete application <span id="deleteModalTrackingId" style="font-weight: 700; color: #DC2626;">HSG-00000</span> for <strong id="deleteModalApplicantName">Applicant</strong>?
            </p>

            <div class="modal-card-summary" style="margin-bottom: 14px; border-left: 4px solid #DC2626;">
                <div class="summary-top">
                    <span class="summary-id" id="deleteSummaryTracking" style="color: #DC2626; background: rgba(220, 38, 38, 0.1);">HSG-00000</span>
                    <span id="deleteSummaryProgram" style="font-size: 0.75rem; font-weight: 600; color: var(--ref-muted);">Program Name</span>
                </div>
                <div style="font-size: 0.88rem; color: var(--ref-text); font-weight: 700; margin-top: 6px;" id="deleteSummaryApplicant">
                    Applicant Name
                </div>
            </div>

            <div class="modal-audit-note" style="background: rgba(220, 38, 38, 0.06); border-color: rgba(220, 38, 38, 0.2); color: var(--ref-muted);">
                <i class="fas fa-exclamation-triangle" style="color: #DC2626;"></i>
                <span><strong>Warning:</strong> This action cannot be undone. All assigned units, assessment notes, and application records will be permanently removed and logged in the municipal audit trail.</span>
            </div>
        </div>
        <div class="kanban-modal-footer">
            <button type="button" class="btn-modal-cancel" id="btnCancelDelete">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-modal-confirm" id="btnConfirmDelete" style="background: #DC2626; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);">
                <i class="fas fa-trash-alt"></i> Delete Permanently
            </button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const statusConfig = {
        'pending': {
            title: 'Submitted / Screening',
            icon: 'fa-clock',
            color: '#FF9800',
            bg: 'rgba(255, 152, 0, 0.14)',
            border: 'rgba(255, 152, 0, 0.35)'
        },
        'qualified': {
            title: 'Assessment & Interview',
            icon: 'fa-clipboard-check',
            color: '#2196F3',
            bg: 'rgba(33, 150, 243, 0.14)',
            border: 'rgba(33, 150, 243, 0.35)'
        },
        'approved': {
            title: 'Approved & Allocated',
            icon: 'fa-check-circle',
            color: '#4CAF50',
            bg: 'rgba(76, 175, 80, 0.14)',
            border: 'rgba(76, 175, 80, 0.35)'
        },
        'released': {
            title: 'Turned Over / Released',
            icon: 'fa-key',
            color: '#9C27B0',
            bg: 'rgba(156, 39, 176, 0.14)',
            border: 'rgba(156, 39, 176, 0.35)'
        },
        'rejected': {
            title: 'Did Not Qualify',
            icon: 'fa-times-circle',
            color: '#EF5350',
            bg: 'rgba(239, 83, 80, 0.14)',
            border: 'rgba(239, 83, 80, 0.35)'
        }
    };

    // Modal elements
    const adjustConfirmModal = document.getElementById('adjustConfirmModal');
    const btnCloseAdjustModal = document.getElementById('btnCloseAdjustModal');
    const btnCancelAdjust = document.getElementById('btnCancelAdjust');
    const btnConfirmAdjust = document.getElementById('btnConfirmAdjust');
    const modalSelectStatus = document.getElementById('modalSelectStatus');
    const modalTrackingId = document.getElementById('modalTrackingId');
    const modalTypeBadge = document.getElementById('modalTypeBadge');
    const modalApplicantName = document.getElementById('modalApplicantName');
    const modalProgramName = document.getElementById('modalProgramName');
    const modalAvatar = document.getElementById('modalAvatar');
    const modalVisualFrom = document.getElementById('modalVisualFrom');
    const modalVisualTo = document.getElementById('modalVisualTo');

    // Context field elements
    const sectionQualified = document.getElementById('sectionQualified');
    const sectionApproved = document.getElementById('sectionApproved');
    const sectionReleased = document.getElementById('sectionReleased');
    const sectionRejected = document.getElementById('sectionRejected');
    const modalInterviewDate = document.getElementById('modalInterviewDate');
    const modalQualNotes = document.getElementById('modalQualNotes');
    const modalUnitAssigned = document.getElementById('modalUnitAssigned');
    const lblUnitAssigned = document.getElementById('lblUnitAssigned');
    const modalApprovalNotes = document.getElementById('modalApprovalNotes');
    const modalMoveInDate = document.getElementById('modalMoveInDate');
    const modalRejectionRemarks = document.getElementById('modalRejectionRemarks');

    // Drag State
    let draggedCard = null;
    let sourceColumn = null;
    let currentEditState = null;
    let isSubmitting = false;

    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');

    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            sourceColumn = this.closest('.kanban-column');
            e.dataTransfer.setData('text/plain', this.id);
            e.dataTransfer.effectAllowed = 'move';
            this.classList.add('dragging');
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over'));
        });
    });

    columns.forEach(col => {
        col.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });

        col.addEventListener('dragleave', function(e) {
            if (!this.contains(e.relatedTarget)) {
                this.classList.remove('drag-over');
            }
        });

        col.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.getElementById(cardId) || draggedCard;
            if (!card) return;

            const fromCol = card.closest('.kanban-column');
            const targetCol = this;
            const fromStatus = fromCol ? fromCol.getAttribute('data-status') : null;
            const targetStatus = targetCol.getAttribute('data-status');

            if (!fromStatus) return;

            // Open adjustment modal with target status pre-selected
            openAdjustmentModal(card, fromCol, targetCol, fromStatus, targetStatus);
        });
    });

    window.openAdjustmentModalDirect = function(beneficiaryId) {
        const card = document.getElementById('card-' + beneficiaryId);
        if (!card) return;
        const col = card.closest('.kanban-column');
        const currStatus = card.getAttribute('data-status') || (col ? col.getAttribute('data-status') : 'pending');
        openAdjustmentModal(card, col, col, currStatus, currStatus);
    };

    function openAdjustmentModal(card, fromCol, targetCol, fromStatus, targetStatus) {
        currentEditState = {
            card: card,
            beneficiaryId: card.getAttribute('data-id'),
            fromCol: fromCol,
            targetCol: targetCol,
            fromStatus: fromStatus,
            targetStatus: targetStatus,
            trackingId: card.getAttribute('data-tracking'),
            applicantName: card.getAttribute('data-applicant'),
            housingType: card.getAttribute('data-type'),
            programName: card.getAttribute('data-program-name'),
            unitAssigned: card.getAttribute('data-unit') || '',
            interviewDate: card.getAttribute('data-interview') || '',
            moveInDate: card.getAttribute('data-move-in') || ''
        };

        const isInsite = (currentEditState.housingType === 'in_site');

        // Populate summary
        modalTrackingId.textContent = currentEditState.trackingId;
        modalTypeBadge.textContent = isInsite ? 'In-Site Housing' : 'Relocation Housing';
        modalTypeBadge.className = 'type-badge ' + currentEditState.housingType;
        modalApplicantName.textContent = currentEditState.applicantName;
        modalProgramName.innerHTML = `<i class="fas fa-home"></i> ${currentEditState.programName}`;

        const cardAvatar = card.querySelector('.mini-avatar');
        if (cardAvatar) {
            modalAvatar.innerHTML = cardAvatar.innerHTML;
        } else {
            modalAvatar.textContent = currentEditState.applicantName.charAt(0).toUpperCase();
        }

        // Set unit label according to pathway
        if (lblUnitAssigned) {
            lblUnitAssigned.textContent = isInsite ? 'Grant / Voucher Reference Number' : 'Unit Assignment (Block & Lot)';
        }

        // Prepopulate field values
        modalSelectStatus.value = targetStatus;
        modalUnitAssigned.value = currentEditState.unitAssigned;
        modalInterviewDate.value = (currentEditState.interviewDate && currentEditState.interviewDate !== '0000-00-00') ? currentEditState.interviewDate : '';
        modalMoveInDate.value = (currentEditState.moveInDate && currentEditState.moveInDate !== '0000-00-00') ? currentEditState.moveInDate : new Date().toISOString().split('T')[0];
        modalQualNotes.value = '';
        modalApprovalNotes.value = '';
        modalRejectionRemarks.value = '';

        updateModalVisuals(fromStatus, targetStatus);
        renderContextFields(targetStatus);

        btnConfirmAdjust.disabled = false;
        btnConfirmAdjust.innerHTML = '<i class="fas fa-check"></i> Save &amp; Move';

        adjustConfirmModal.classList.add('active');
        adjustConfirmModal.setAttribute('aria-hidden', 'false');
    }

    function updateModalVisuals(fromStatus, toStatus) {
        const fromCfg = statusConfig[fromStatus] || { title: fromStatus, icon: 'fa-circle', color: '#666', bg: '#eee', border: '#ccc' };
        const toCfg = statusConfig[toStatus] || { title: toStatus, icon: 'fa-circle', color: '#666', bg: '#eee', border: '#ccc' };

        modalVisualFrom.innerHTML = `<i class="fas ${fromCfg.icon}"></i> <span>${fromCfg.title}</span>`;
        modalVisualFrom.style.background = fromCfg.bg;
        modalVisualFrom.style.color = fromCfg.color;
        modalVisualFrom.style.border = `1px solid ${fromCfg.border}`;

        modalVisualTo.innerHTML = `<i class="fas ${toCfg.icon}"></i> <span>${toCfg.title}</span>`;
        modalVisualTo.style.background = toCfg.bg;
        modalVisualTo.style.color = toCfg.color;
        modalVisualTo.style.border = `1px solid ${toCfg.border}`;
    }

    function renderContextFields(status) {
        sectionQualified.style.display = (status === 'qualified') ? 'block' : 'none';
        sectionApproved.style.display = (status === 'approved') ? 'block' : 'none';
        sectionReleased.style.display = (status === 'released') ? 'block' : 'none';
        sectionRejected.style.display = (status === 'rejected') ? 'block' : 'none';
    }

    modalSelectStatus.addEventListener('change', function() {
        if (!currentEditState) return;
        const newTarget = this.value;
        currentEditState.targetStatus = newTarget;
        const targetColumnEl = document.getElementById('col-' + newTarget);
        if (targetColumnEl) {
            currentEditState.targetCol = targetColumnEl;
        }
        updateModalVisuals(currentEditState.fromStatus, newTarget);
        renderContextFields(newTarget);
    });

    function closeAdjustModal() {
        if (isSubmitting) return;
        adjustConfirmModal.classList.remove('active');
        adjustConfirmModal.setAttribute('aria-hidden', 'true');
        currentEditState = null;
        draggedCard = null;
        sourceColumn = null;
    }

    btnCloseAdjustModal.addEventListener('click', closeAdjustModal);
    btnCancelAdjust.addEventListener('click', closeAdjustModal);

    adjustConfirmModal.addEventListener('click', function(e) {
        if (e.target === adjustConfirmModal) closeAdjustModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && adjustConfirmModal.classList.contains('active')) {
            closeAdjustModal();
        }
    });

    // Confirm Adjustment & Status Update
    btnConfirmAdjust.addEventListener('click', function() {
        if (!currentEditState || isSubmitting) return;

        const targetStatus = modalSelectStatus.value;
        const isRejection = (targetStatus === 'rejected');
        const rejectionRemarks = modalRejectionRemarks.value.trim();

        if (isRejection && !rejectionRemarks) {
            showToast('Please provide the grounds / reason for disqualification.', true);
            modalRejectionRemarks.focus();
            return;
        }

        const payload = {
            beneficiary_id: currentEditState.beneficiaryId,
            status: targetStatus,
            unit_assigned: modalUnitAssigned.value.trim(),
            interview_date: modalInterviewDate.value.trim(),
            move_in_date: modalMoveInDate.value.trim(),
            remarks: rejectionRemarks,
            assessment_notes: (targetStatus === 'approved') ? modalApprovalNotes.value.trim() : modalQualNotes.value.trim()
        };

        isSubmitting = true;
        btnConfirmAdjust.disabled = true;
        btnCancelAdjust.disabled = true;
        btnConfirmAdjust.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('update-housing-status-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const card = currentEditState.card;
                const targetCol = document.getElementById('col-' + targetStatus);
                const targetList = targetCol.querySelector('.card-list');

                // Remove placeholder in target if present
                const emptyMsg = targetList.querySelector('.empty-placeholder');
                if (emptyMsg) emptyMsg.remove();

                // Update card dataset
                card.setAttribute('data-status', targetStatus);
                card.setAttribute('data-unit', payload.unit_assigned);
                card.setAttribute('data-interview', payload.interview_date);
                card.setAttribute('data-move-in', payload.move_in_date);

                // Update chips inside card
                updateCardChips(card, payload, currentEditState.housingType === 'in_site');

                // Move card element
                targetList.appendChild(card);

                // Pulse effect
                card.classList.add('card-just-moved');
                setTimeout(() => card.classList.remove('card-just-moved'), 1400);

                // Update column counters
                updateCounters();
                filterCards();

                const targetTitle = (statusConfig[targetStatus] && statusConfig[targetStatus].title) || targetStatus.toUpperCase();
                showToast(`Application <strong>${currentEditState.trackingId}</strong> successfully moved to <strong>${targetTitle}</strong>!`, false);

                isSubmitting = false;
                closeAdjustModal();
            } else {
                isSubmitting = false;
                btnConfirmAdjust.disabled = false;
                btnCancelAdjust.disabled = false;
                btnConfirmAdjust.innerHTML = '<i class="fas fa-check"></i> Save &amp; Move';
                showToast(data.message || 'Error updating status.', true);
            }
        })
        .catch(err => {
            isSubmitting = false;
            btnConfirmAdjust.disabled = false;
            btnCancelAdjust.disabled = false;
            btnConfirmAdjust.innerHTML = '<i class="fas fa-check"></i> Save &amp; Move';
            showToast('Network error: ' + err.message, true);
        });
    });

    function updateCardChips(card, payload, isInsite) {
        const chipsContainer = card.querySelector('.card-chips');
        if (!chipsContainer) return;

        let chipsHtml = '';
        if (payload.unit_assigned) {
            chipsHtml += `<span class="card-chip highlight" title="${isInsite ? 'Grant Reference' : 'Assigned Unit'}"><i class="fas ${isInsite ? 'fa-file-invoice' : 'fa-door-open'}"></i> ${escapeHtml(payload.unit_assigned)}</span>`;
        }
        if (payload.interview_date) {
            chipsHtml += `<span class="card-chip schedule" title="Interview / Inspection Schedule"><i class="fas fa-calendar-check"></i> ${formatDateStr(payload.interview_date)}</span>`;
        }
        if (payload.move_in_date) {
            chipsHtml += `<span class="card-chip" title="Official Move-In Date"><i class="fas fa-key"></i> ${formatDateStr(payload.move_in_date)}</span>`;
        }
        chipsContainer.innerHTML = chipsHtml;
    }

    function formatDateStr(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return '';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } catch(e) {
            return dateStr;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updateCounters() {
        Object.keys(statusConfig).forEach(status => {
            const col = document.getElementById('col-' + status);
            if (!col) return;
            const countSpan = document.getElementById('count-' + status);
            const cardList = col.querySelector('.card-list');
            const visibleCards = cardList.querySelectorAll('.kanban-card:not([style*="display: none"])');
            const totalCards = cardList.querySelectorAll('.kanban-card');

            if (countSpan) {
                countSpan.textContent = totalCards.length;
            }

            // If empty, ensure placeholder exists
            if (totalCards.length === 0 && !cardList.querySelector('.empty-placeholder')) {
                cardList.innerHTML = `<div class="empty-placeholder"><i class="fas fa-inbox"></i><span>No applications in this stage</span></div>`;
            }
        });
    }

    // Live Search and Filters
    const searchInput = document.getElementById('kanbanSearch');
    const typePills = document.querySelectorAll('#typePills .filter-pill');
    const programPills = document.querySelectorAll('#programPills .filter-pill');

    let activeType = 'all';
    let activeProgram = 'all';

    typePills.forEach(pill => {
        pill.addEventListener('click', function() {
            typePills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            activeType = this.getAttribute('data-type');
            filterCards();
        });
    });

    programPills.forEach(pill => {
        pill.addEventListener('click', function() {
            programPills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            activeProgram = this.getAttribute('data-program');
            filterCards();
        });
    });

    searchInput.addEventListener('input', filterCards);

    function filterCards() {
        const query = searchInput.value.toLowerCase().trim();
        const allCards = document.querySelectorAll('.kanban-card');

        allCards.forEach(card => {
            const cardSearch = card.getAttribute('data-search') || '';
            const cardType = card.getAttribute('data-type') || '';
            const cardProgram = card.getAttribute('data-program-id') || '';

            const matchesSearch = (!query || cardSearch.includes(query));
            const matchesType = (activeType === 'all' || cardType === activeType);
            const matchesProgram = (activeProgram === 'all' || cardProgram === activeProgram);

            if (matchesSearch && matchesType && matchesProgram) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Delete Modal Handling
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const btnCloseDeleteModal = document.getElementById('btnCloseDeleteModal');
    const btnCancelDelete = document.getElementById('btnCancelDelete');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const deleteModalTrackingId = document.getElementById('deleteModalTrackingId');
    const deleteModalApplicantName = document.getElementById('deleteModalApplicantName');
    const deleteSummaryTracking = document.getElementById('deleteSummaryTracking');
    const deleteSummaryApplicant = document.getElementById('deleteSummaryApplicant');
    const deleteSummaryProgram = document.getElementById('deleteSummaryProgram');

    let pendingDeleteId = null;

    window.openDeleteModal = function(e, beneficiaryId, trackingCode, applicantName, programName) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        pendingDeleteId = beneficiaryId;
        deleteModalTrackingId.textContent = trackingCode;
        deleteModalApplicantName.textContent = applicantName;
        deleteSummaryTracking.textContent = trackingCode;
        deleteSummaryApplicant.textContent = applicantName;
        deleteSummaryProgram.textContent = programName;

        deleteConfirmModal.classList.add('active');
        deleteConfirmModal.setAttribute('aria-hidden', 'false');
    };

    function closeDeleteModal() {
        deleteConfirmModal.classList.remove('active');
        deleteConfirmModal.setAttribute('aria-hidden', 'true');
        pendingDeleteId = null;
    }

    btnCloseDeleteModal.addEventListener('click', closeDeleteModal);
    btnCancelDelete.addEventListener('click', closeDeleteModal);

    btnConfirmDelete.addEventListener('click', function() {
        if (!pendingDeleteId) return;

        btnConfirmDelete.disabled = true;
        btnConfirmDelete.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        fetch('delete-housing-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ beneficiary_id: pendingDeleteId })
        })
        .then(res => res.json())
        .then(data => {
            btnConfirmDelete.disabled = false;
            btnConfirmDelete.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
            if (data.success) {
                const card = document.getElementById('card-' + pendingDeleteId);
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        card.remove();
                        updateCounters();
                    }, 300);
                }
                showToast(data.message || 'Application deleted successfully.', false);
                closeDeleteModal();
            } else {
                showToast(data.message || 'Failed to delete application.', true);
            }
        })
        .catch(err => {
            btnConfirmDelete.disabled = false;
            btnConfirmDelete.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Permanently';
            showToast('Network error: ' + err.message, true);
        });
    });

    // Toast notification
    function showToast(message, isError = false) {
        if (window.showProcessToast) {
            window.showProcessToast(message.replace(/<[^>]*>?/gm, ''), isError);
            return;
        }
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast ' + (isError ? 'toast-error' : 'toast-success');
        toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

});
</script>
</body>
</html>

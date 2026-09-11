<?php
// staff/manage-pwd.php - PWD Services & Welfare Management
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff's assigned services for sidebar display & access check
$staff_id_query = "SELECT id, position FROM staff WHERE user_id = :user_id";
$staff_id_stmt = $db->prepare($staff_id_query);
$staff_id_stmt->bindParam(':user_id', $_SESSION['user_id']);
$staff_id_stmt->execute();
$staff_info = $staff_id_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info ? $staff_info['id'] : 0;

// Get assigned service categories
$assigned_categories = [];
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->bindParam(':staff_id', $staff_id);
$cat_stmt->execute();
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Access check flags
$has_aics_access = in_array('AICS', $assigned_categories) || isAdmin();
$has_housing_access = in_array('Housing', $assigned_categories) || isAdmin();
$has_senior_access = in_array('Senior Citizen', $assigned_categories) || isAdmin();
$has_pwd_access = in_array('PWD', $assigned_categories) || isAdmin() || empty($assigned_categories);

// Handle status update POST
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pwd_status'])) {
    csrf_verify();
    $target_user_id = sanitize($_POST['user_id']);
    $pwd_status = sanitize($_POST['pwd_status']); // 1 for active/verified, 0 for pending/inactive
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    // Check if resident record exists
    $check_res = $db->prepare("SELECT id FROM residents WHERE user_id = :uid");
    $check_res->bindParam(':uid', $target_user_id);
    $check_res->execute();
    $res_data = $check_res->fetch(PDO::FETCH_ASSOC);
    
    if ($res_data) {
        $update_res = $db->prepare("UPDATE residents SET pwd = :pwd WHERE user_id = :uid");
        $update_res->bindParam(':pwd', $pwd_status);
        $update_res->bindParam(':uid', $target_user_id);
        $update_res->execute();
    } else {
        // Create basic resident entry marked as PWD
        $insert_res = $db->prepare("INSERT INTO residents (user_id, pwd, is_active) VALUES (:uid, :pwd, 1)");
        $insert_res->bindParam(':pwd', $pwd_status);
        $insert_res->bindParam(':uid', $target_user_id);
        $insert_res->execute();
    }
    
    // Create resident notification
    $status_label = ($pwd_status == 1) ? 'Verified PWD Member' : 'Pending Review';
    $notif_msg = "Your PWD status has been updated to: " . $status_label;
    if (!empty($remarks)) {
        $notif_msg .= ". Remarks: " . $remarks;
    }
    
    $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, 'PWD Status Update', :msg, 'system')");
    $notif_stmt->bindParam(':uid', $target_user_id);
    $notif_stmt->bindParam(':msg', $notif_msg);
    $notif_stmt->execute();
    
    $success_msg = "PWD resident status updated successfully!";
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$barangay_filter = isset($_GET['barangay']) ? sanitize($_GET['barangay']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build main query for PWD Residents
$query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.address, u.profile_picture, u.created_at,
                 r.id as resident_id, r.birth_date, r.gender, r.barangay, r.pwd, r.emergency_contact_name, r.emergency_contact_number
          FROM users u 
          LEFT JOIN residents r ON u.user_id = r.user_id 
          WHERE u.role = 'resident'";

$params = [];

if ($status_filter === 'verified') {
    $query .= " AND r.pwd = 1";
} elseif ($status_filter === 'pending') {
    $query .= " AND (r.pwd IS NULL OR r.pwd = 0)";
}

if ($barangay_filter !== 'all' && !empty($barangay_filter)) {
    $query .= " AND r.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.contact_number LIKE :search OR r.barangay LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$pwds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Summary Statistics
$total_pwd_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN r.pwd = 1 THEN 1 ELSE 0 END) as verified_pwds,
    SUM(CASE WHEN r.pwd IS NULL OR r.pwd = 0 THEN 1 ELSE 0 END) as pending_pwds
    FROM users u 
    LEFT JOIN residents r ON u.user_id = r.user_id 
    WHERE u.role = 'resident'";
$stats = $db->query($total_pwd_query)->fetch(PDO::FETCH_ASSOC);

// Get total appointments count for PWD service (service_id = 3)
$appointments_count = $db->query("SELECT COUNT(*) FROM appointments WHERE service_id = 3")->fetchColumn();

// Get unique barangays for filter dropdown
$barangays = $db->query("SELECT DISTINCT barangay FROM residents WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);

// Staff user details for header
$avatar_url = '';
$initials = '';
if (isset($_SESSION['full_name'])) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    foreach($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

$staff_user = $db->prepare("SELECT profile_picture FROM users WHERE user_id = :uid");
$staff_user->bindParam(':uid', $_SESSION['user_id']);
$staff_user->execute();
$staff_pic = $staff_user->fetch(PDO::FETCH_ASSOC);
if ($staff_pic && !empty($staff_pic['profile_picture']) && file_exists('../' . $staff_pic['profile_picture'])) {
    $avatar_url = '../' . $staff_pic['profile_picture'];
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Services Management - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/portal-layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--bg-card);
            padding: 22px;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 18px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
        }
        .stat-icon.total { background: linear-gradient(135deg, #4A148C, #311B92); }
        .stat-icon.verified { background: linear-gradient(135deg, #2E7D32, #1B5E20); }
        .stat-icon.pending { background: linear-gradient(135deg, #ED6C02, #E65100); }
        .stat-icon.appointments { background: linear-gradient(135deg, #0288D1, #01579B); }
        .stat-info h3 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-bottom: 2px; }
        .stat-info p { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }

        .filter-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.85rem;
        }
        .filter-btn {
            padding: 10px 22px;
            background: #4A148C;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn:hover { background: #6a1b9a; transform: translateY(-2px); }
        .reset-btn {
            padding: 10px 18px;
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .data-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }
        .table-responsive { overflow-x: auto; }
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .modern-table th {
            padding: 14px;
            background: var(--bg-primary);
            color: var(--text-muted);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }
        .modern-table td {
            padding: 14px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.88rem;
            color: var(--text-primary);
            vertical-align: middle;
        }
        .modern-table tr:hover td { background: rgba(74, 20, 140, 0.02); }

        .resident-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #4A148C;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .resident-info-cell { display: flex; align-items: center; gap: 12px; }
        .resident-name { font-weight: 700; color: var(--text-primary); }
        .resident-email { font-size: 0.78rem; color: var(--text-muted); }

        .badge-status {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-verified { background: rgba(46, 125, 50, 0.12); color: #2E7D32; }
        .badge-pending { background: rgba(237, 108, 2, 0.12); color: #ED6C02; }

        .action-btns { display: flex; gap: 8px; }
        .btn-act {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-view { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-view:hover { border-color: #4A148C; color: #4A148C; }
        .btn-update { background: #4A148C; color: white; }
        .btn-update:hover { background: #6a1b9a; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 14, 23, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 30px;
            width: 90%;
            max-width: 540px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-box { transform: translateY(0); }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h3 { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Menu -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga</h2>
                    <span>Staff Portal</span>
                </div>
            </div>
            
            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if(!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <span><?php echo htmlspecialchars($staff_info['position'] ?? 'Staff Member'); ?></span>
                </div>
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-divider">Appointments</li>
                <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Manage Appointments</a></li>
                <li class="menu-divider">Services</li>
                <?php if($has_aics_access): ?>
                <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> Manage AICS</a></li>
                <?php endif; ?>
                <?php if($has_housing_access): ?>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Manage Housing</a></li>
                <?php endif; ?>
                <li><a href="manage-senior.php"><i class="fas fa-person-cane"></i> Senior Citizen</a></li>
                <li><a href="manage-pwd.php" class="active"><i class="fas fa-wheelchair"></i> PWD Services</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            </ul>
            
            <div class="sidebar-footer">
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <div class="page-title">
                        <h1>♿ PWD Services & Welfare Management</h1>
                        <p>Verify Persons with Disability status, ID issuances, and discount booklet privileges.</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="../logout.php" class="top-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='manage-pwd.php?status=all'">
                    <div class="stat-icon total"><i class="fas fa-wheelchair"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Total Registered Residents</p>
                    </div>
                </div>
                <div class="stat-card" onclick="window.location.href='manage-pwd.php?status=verified'">
                    <div class="stat-icon verified"><i class="fas fa-id-card"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['verified_pwds']); ?></h3>
                        <p>Verified PWD Members</p>
                    </div>
                </div>
                <div class="stat-card" onclick="window.location.href='manage-pwd.php?status=pending'">
                    <div class="stat-icon pending"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['pending_pwds']); ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                </div>
                <div class="stat-card" onclick="window.location.href='manage-appointments.php?service_id=3'">
                    <div class="stat-icon appointments"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo number_format($appointments_count); ?></h3>
                        <p>PWD Appointments</p>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Controls -->
            <div class="filter-card">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search resident name, email, or contact..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All PWD Statuses</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified PWD Member</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select name="barangay">
                            <option value="all">All Barangays</option>
                            <?php foreach ($barangays as $bgy): ?>
                                <option value="<?php echo htmlspecialchars($bgy); ?>" <?php echo $barangay_filter === $bgy ? 'selected' : ''; ?>><?php echo htmlspecialchars($bgy); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="manage-pwd.php" class="reset-btn"><i class="fas fa-redo"></i> Reset</a>
                </form>
            </div>

            <!-- Data Table Section -->
            <div class="data-card">
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Contact Details</th>
                                <th>Barangay / Address</th>
                                <th>PWD Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pwds)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 2.2rem; margin-bottom: 10px; opacity: 0.4; display: block;"></i>
                                    No PWD resident records found.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($pwds as $row): ?>
                            <?php 
                                $is_pwd = ($row['pwd'] == 1);
                                $pic_path = !empty($row['profile_picture']) && file_exists('../' . $row['profile_picture']) ? '../' . $row['profile_picture'] : '';
                                $user_initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="resident-info-cell">
                                        <?php if (!empty($pic_path)): ?>
                                            <img src="<?php echo htmlspecialchars($pic_path); ?>" class="resident-avatar" alt="Avatar">
                                        <?php else: ?>
                                            <div class="resident-avatar"><?php echo $user_initials; ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="resident-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            <div class="resident-email"><?php echo htmlspecialchars($row['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['barangay'] ?? $row['address'] ?? 'Palayan City'); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $is_pwd ? 'badge-verified' : 'badge-pending'; ?>">
                                        <i class="fas fa-<?php echo $is_pwd ? 'wheelchair' : 'clock'; ?>"></i>
                                        <?php echo $is_pwd ? 'Verified PWD' : 'Pending Review'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-act btn-view" onclick="openPwdDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                        <button class="btn-act btn-update" onclick="openStatusModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>', <?php echo $row['pwd'] ?? 0; ?>)">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Details View Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-wheelchair" style="color: #4A148C;"></i> PWD Resident Details</h3>
                <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div id="modalDetailsBody" style="font-size: 0.88rem; line-height: 1.6; color: var(--text-secondary);"></div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit" style="color: #4A148C;"></i> Update PWD Resident Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="update_pwd_status" value="1">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <p style="margin-bottom: 15px; font-weight: 700; color: var(--text-primary);" id="modalUserName"></p>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 700; font-size: 0.82rem; margin-bottom: 6px;">PWD Registration Status:</label>
                    <select name="pwd_status" id="modalPwdStatus" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                        <option value="1">Verified PWD Member</option>
                        <option value="0">Pending / Unverified</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; font-size: 0.82rem; margin-bottom: 6px;">Social Worker Remarks / Notes:</label>
                    <textarea name="remarks" rows="3" placeholder="Add optional remarks regarding PWD ID issuance, discount booklet, or medical cert..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-family: inherit;"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-act btn-view" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn-act btn-update" style="padding: 10px 20px;">Save Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openPwdDetails(data) {
        const body = document.getElementById('modalDetailsBody');
        body.innerHTML = `
            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                <div class="resident-avatar" style="width:54px; height:54px; font-size: 1.2rem;">${data.first_name[0]}${data.last_name[0]}</div>
                <div>
                    <h4 style="font-size: 1.1rem; font-weight: 800; color: var(--text-primary); margin-bottom: 2px;">${data.first_name} ${data.last_name}</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">${data.email}</span>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div><strong>Contact:</strong> ${data.contact_number || 'N/A'}</div>
                <div><strong>Barangay:</strong> ${data.barangay || 'N/A'}</div>
                <div><strong>Birth Date:</strong> ${data.birth_date || 'N/A'}</div>
                <div><strong>Gender:</strong> ${data.gender || 'N/A'}</div>
                <div><strong>Emergency Contact:</strong> ${data.emergency_contact_name || 'N/A'}</div>
                <div><strong>Emergency Phone:</strong> ${data.emergency_contact_number || 'N/A'}</div>
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color);">
                <strong>Current PWD Status:</strong> 
                <span class="badge-status ${data.pwd == 1 ? 'badge-verified' : 'badge-pending'}">
                    ${data.pwd == 1 ? 'Verified PWD Member' : 'Pending Review'}
                </span>
            </div>
        `;
        document.getElementById('detailsModal').classList.add('active');
    }

    function openStatusModal(userId, userName, currentStatus) {
        document.getElementById('modalUserId').value = userId;
        document.getElementById('modalUserName').textContent = 'Updating Resident: ' + userName;
        document.getElementById('modalPwdStatus').value = currentStatus ? 1 : 0;
        document.getElementById('statusModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // Theme Toggle Script
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    themeToggle.addEventListener('click', () => {
        const currentTheme = html.getAttribute('data-theme');
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', nextTheme);
        document.cookie = `dark_mode=${nextTheme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
    });
    </script>
</body>
</html>
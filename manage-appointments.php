<?php
// Fix the path to config - go up one level to root, then into config folder
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

if (!isStaff()) {
    // Fix redirect path - go to staff-login.php in root
    redirect('staff-login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $appointment_id = sanitize($_POST['appointment_id']);
    $new_status = sanitize($_POST['new_status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    $update_query = "UPDATE appointments SET 
                      status = :status,
                      notes = CONCAT(IFNULL(notes, ''), '\n[Staff Update] ', :notes)
                      WHERE appointment_id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':notes', $notes);
    $update_stmt->bindParam(':id', $appointment_id);
    
    if ($update_stmt->execute()) {
        // Get full appointment + user details for email and notification
        $user_query = "SELECT a.*, s.service_name, u.user_id, u.email, u.first_name, u.last_name
                       FROM appointments a
                       JOIN services s ON a.service_id = s.service_id
                       JOIN users u ON a.user_id = u.user_id
                       WHERE a.appointment_id = :id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->bindParam(':id', $appointment_id);
        $user_stmt->execute();
        $appt_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if ($appt_details) {
            // Create in-app notification
            $notif_msg = "Your appointment for {$appt_details['service_name']} has been updated to: " . ucfirst($new_status);
            $notif_query = "INSERT INTO notifications (user_id, title, message, type)
                            VALUES (:user_id, 'Appointment Status Updated', :msg, 'appointment')";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->bindParam(':user_id', $appt_details['user_id']);
            $notif_stmt->bindParam(':msg', $notif_msg);
            $notif_stmt->execute();

            // Send email notification
            try {
                $mailer = new Mailer();
                $user_name = $appt_details['first_name'] . ' ' . $appt_details['last_name'];
                $email_result = $mailer->sendAppointmentStatusUpdate(
                    $appt_details['email'],
                    $user_name,
                    $appt_details,
                    $new_status,
                    $notes
                );
                $email_status = $email_result['success']
                    ? 'Email notification sent.'
                    : 'Email failed: ' . $email_result['message'];
                if (!$email_result['success']) error_log($email_status);
            } catch (Exception $e) {
                error_log('Appointment email error: ' . $e->getMessage());
            }
        }

        $success = 'Appointment status updated successfully! ' . ($email_status ?? '');
    }
}

// Build query
$query = "SELECT a.*, s.service_name, u.first_name, u.last_name, u.contact_number, u.email
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          JOIN users u ON a.user_id = u.user_id
          WHERE a.appointment_date = :date";

$params = [':date' => $date_filter];

if ($status_filter != 'all') {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.contact_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY a.appointment_time ASC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

// Get statistics for today
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments WHERE appointment_date = :date";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':date', $date_filter);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments - Staff Dashboard</title>
    <!-- Fix CSS paths - go up one level to root css folder -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
    <!-- Fix navbar path - go up one level to root includes folder -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>Staff</p>
            </div>
            <ul class="sidebar-menu">
                <!-- Fix all navigation links -->
                <li><a href="../staff-dashboard.php">🏠 Dashboard</a></li>
                <li><a href="manage-appointments.php" class="active">📅 Manage Appointments</a></li>
                <li><a href="manage-aics.php">💰 Manage AICS</a></li>
                <li><a href="manage-housing.php">🏘️ Manage Housing</a></li>
                <li><a href="verify-documents.php">📄 Verify Documents</a></li>
                <li><a href="reports.php">📊 Reports</a></li>
                <li class="menu-divider"></li>
                <li><a href="../logout.php">🚪 Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Manage Appointments</h1>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Date Navigation -->
            <div class="date-navigation">
                <a href="?date=<?php echo date('Y-m-d', strtotime($date_filter . ' -1 day')); ?>" class="nav-btn">← Previous Day</a>
                <div class="current-date">
                    <h2><?php echo date('F d, Y', strtotime($date_filter)); ?></h2>
                    <a href="?date=<?php echo date('Y-m-d'); ?>" class="today-btn">Today</a>
                </div>
                <a href="?date=<?php echo date('Y-m-d', strtotime($date_filter . ' +1 day')); ?>" class="nav-btn">Next Day →</a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card confirmed">
                    <div class="stat-value"><?php echo $stats['confirmed'] ?? 0; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-value"><?php echo $stats['completed'] ?? 0; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <form method="GET" class="filters-form">
                    <input type="hidden" name="date" value="<?php echo $date_filter; ?>">
                    
                    <div class="filter-group">
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <input type="text" name="search" placeholder="Search by name or contact..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn-primary">Filter</button>
                        <?php if($status_filter != 'all' || !empty($search)): ?>
                        <a href="manage-appointments.php?date=<?php echo $date_filter; ?>" class="btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Appointments List -->
            <div class="appointments-list">
                <?php if($stmt->rowCount() > 0): ?>
                    <?php while($app = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="appointment-card status-<?php echo $app['status']; ?>">
                        <div class="appointment-time">
                            <span class="time"><?php echo date('h:i A', strtotime($app['appointment_time'])); ?></span>
                            <span class="status-badge"><?php echo ucfirst($app['status']); ?></span>
                        </div>
                        
                        <div class="appointment-info">
                            <h3><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h3>
                            <p class="service"><?php echo htmlspecialchars($app['service_name']); ?></p>
                            <p class="contact">
                                📞 <?php echo htmlspecialchars($app['contact_number']); ?><br>
                                ✉️ <?php echo htmlspecialchars($app['email']); ?>
                            </p>
                            <?php if(!empty($app['purpose'])): ?>
                            <p class="purpose"><strong>Purpose:</strong> <?php echo htmlspecialchars($app['purpose']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="appointment-actions">
                            <button onclick="showUpdateModal(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                                    class="btn-small">Update</button>
                            <a href="view-appointment.php?id=<?php echo $app['appointment_id']; ?>" class="btn-small">View</a>
                            
                            <?php
                            $doc_check = "SELECT COUNT(*) as count FROM documents WHERE appointment_id = :id";
                            $doc_stmt = $db->prepare($doc_check);
                            $doc_stmt->bindParam(':id', $app['appointment_id']);
                            $doc_stmt->execute();
                            $doc_count = $doc_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            ?>
                            <?php if($doc_count > 0): ?>
                            <span class="doc-badge" title="Documents uploaded">📄 <?php echo $doc_count; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No appointments found for this date.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Update Appointment</h2>
            <form method="POST" id="updateForm">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div class="form-group">
                    <label>Client</label>
                    <p id="modal_client" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label>Service</label>
                    <p id="modal_service" class="readonly-field"></p>
                </div>
                
                <div class="form-group">
                    <label for="modal_status">New Status</label>
                    <select name="new_status" id="modal_status" required>
                        <option value="confirmed">Confirm</option>
                        <option value="completed">Mark as Completed</option>
                        <option value="cancelled">Cancel</option>
                        <option value="pending">Mark as Pending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_notes">Notes / Remarks</label>
                    <textarea name="notes" id="modal_notes" rows="3" 
                              placeholder="Add any notes about this appointment..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .date-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .current-date {
        text-align: center;
    }
    
    .current-date h2 {
        color: #003366;
        margin-bottom: 5px;
    }
    
    .today-btn {
        color: #003366;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    .today-btn:hover {
        text-decoration: underline;
    }
    
    .nav-btn {
        padding: 8px 15px;
        background: #f5f5f5;
        border-radius: 5px;
        color: #333;
        text-decoration: none;
        transition: background 0.3s;
    }
    
    .nav-btn:hover {
        background: #e0e0e0;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card.pending { border-left: 4px solid #ff9800; }
    .stat-card.confirmed { border-left: 4px solid #2196F3; }
    .stat-card.completed { border-left: 4px solid #4CAF50; }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #003366;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }
    
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filters-form {
        display: flex;
        gap: 15px;
    }
    
    .filter-group {
        flex: 1;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .search-group {
        display: flex;
        gap: 10px;
    }
    
    .appointments-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .appointment-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #ddd;
    }
    
    .appointment-card.status-pending { border-left-color: #ff9800; }
    .appointment-card.status-confirmed { border-left-color: #2196F3; }
    .appointment-card.status-completed { border-left-color: #4CAF50; }
    .appointment-card.status-cancelled { border-left-color: #f44336; opacity: 0.7; }
    
    .appointment-time {
        min-width: 100px;
        text-align: center;
    }
    
    .appointment-time .time {
        display: block;
        font-size: 1.2rem;
        font-weight: bold;
        color: #003366;
        margin-bottom: 5px;
    }
    
    .appointment-time .status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 0.8rem;
        background: #f5f5f5;
        color: #666;
    }
    
    .appointment-info {
        flex: 1;
    }
    
    .appointment-info h3 {
        color: #333;
        margin-bottom: 5px;
    }
    
    .appointment-info .service {
        color: #003366;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .appointment-info .contact {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .appointment-info .purpose {
        color: #666;
        font-size: 0.9rem;
        background: #f9f9f9;
        padding: 8px;
        border-radius: 5px;
    }
    
    .appointment-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .doc-badge {
        background: #003366;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }
    
    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-content h2 {
        color: #003366;
        margin-bottom: 20px;
    }
    
    .readonly-field {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 5px;
        color: #333;
    }
    
    .btn-small {
        display: inline-block;
        padding: 5px 10px;
        background: #003366;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
    }
    
    .btn-small:hover {
        background: #002244;
    }
    
    .btn-primary {
        background: #003366;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: #f9f9f9;
        border-radius: 10px;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .date-navigation {
            flex-direction: column;
            gap: 15px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filters-form {
            flex-direction: column;
        }
        
        .search-group {
            flex-wrap: wrap;
        }
        
        .appointment-card {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .appointment-time {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .appointment-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    </style>
    
    <script>
    function showUpdateModal(appointment) {
        document.getElementById('modal_appointment_id').value = appointment.appointment_id;
        document.getElementById('modal_client').textContent = 
            appointment.first_name + ' ' + appointment.last_name;
        document.getElementById('modal_service').textContent = appointment.service_name;
        document.getElementById('updateModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('updateModal').style.display = 'none';
        document.getElementById('modal_notes').value = '';
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('updateModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
    
    <!-- Fix JS path -->
    <script src="../js/main.js"></script>
</body>
</html>
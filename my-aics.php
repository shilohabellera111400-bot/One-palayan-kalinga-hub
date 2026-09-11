<?php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get all AICS requests for the user
$query = "SELECT * FROM aics_requests 
          WHERE user_id = :user_id 
          ORDER BY submission_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My AICS Requests - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></h3>
                <p>Resident</p>
            </div>
            <?php include __DIR__ . '/includes/usersidenav.php'; ?>
        </div>
        
        <div class="main-content">
            <h1>My AICS Requests</h1>
            
            <div class="action-bar">
                <a href="apply-aics.php" class="btn-primary">+ New Application</a>
            </div>
            
            <?php if($stmt->rowCount() > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Type</th>
                        <th>Amount Requested</th>
                        <th>Amount Approved</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($request = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong>AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><?php echo ucfirst($request['request_type']); ?></td>
                        <td>₱<?php echo number_format($request['amount_requested'], 2); ?></td>
                        <td>
                            <?php if($request['amount_approved']): ?>
                                ₱<?php echo number_format($request['amount_approved'], 2); ?>
                            <?php else: ?>
                                ---
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view-aics.php?id=<?php echo $request['aics_id']; ?>" class="btn-small">View</a>
                            <a href="track-aics.php?tracking=AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?>" 
                               class="btn-small">Track</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p>You haven't submitted any AICS requests yet.</p>
                <a href="apply-aics.php" class="btn-primary">Apply for AICS Now</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .action-bar {
        margin-bottom: 20px;
        text-align: right;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .empty-state p {
        color: #666;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }
    </style>
    
    <script src="js/main.js"></script>
</body>
</html>
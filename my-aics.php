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
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>
        
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Page Title Subheader -->
            <div class="portal-page-subheader" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding: 4px 6px;">
                <div class="page-title">
                    <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--ref-text, #191D1E); margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list-alt" style="color: #7F0303;"></i> My AICS Requests
                    </h1>
                    <p style="font-size: 0.85rem; color: var(--ref-muted, #71818B); margin: 4px 0 0;">View and track your welfare assistance filings</p>
                </div>
                <div>
                    <a href="apply-aics.php" class="btn btn-primary" style="background: #7F0303; border-radius: 20px; padding: 8px 18px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; color: #fff; text-decoration: none;">+ New Application</a>
                </div>
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
    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get all PWD applications
$query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.address, u.created_at 
          FROM users u 
          WHERE u.role = 'resident' 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pwds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Management - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container { max-width: 1280px; margin: 0 auto; padding: 20px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { color: #003366; font-size: 1.8rem; }
        .table-container { background: white; border-radius: 10px; padding: 20px; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f5f5f5; color: #333; font-weight: 600; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #d4edda; color: #155724; }
        .btn-small { background: #003366; color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 0.75rem; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-wheelchair"></i> PWD Management</h1>
            <p>Manage Persons with Disability registration and benefits</p>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Contact</th><th>Address</th><th>Registered</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach($pwds as $pwd): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pwd['first_name'] . ' ' . $pwd['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($pwd['email']); ?></td>
                        <td><?php echo htmlspecialchars($pwd['contact_number']); ?></td>
                        <td><?php echo htmlspecialchars($pwd['address']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($pwd['created_at'])); ?></td>
                        <td><span class="status-badge">Active</span></td>
                        <td><a href="view-pwd.php?id=<?php echo $pwd['user_id']; ?>" class="btn-small">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 
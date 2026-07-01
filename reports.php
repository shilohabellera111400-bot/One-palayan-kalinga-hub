<?php
require_once __DIR__ . '/../config/database.php';

if (!isStaff()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get date range from request
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'all';

// Build reports data
$reports = [];

// 1. AICS Summary
if ($report_type == 'all' || $report_type == 'aics') {
    $aics_query = "SELECT 
        DATE_FORMAT(submission_date, '%Y-%m-%d') as date,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(amount_requested) as total_requested,
        SUM(amount_approved) as total_approved
        FROM aics_requests
        WHERE submission_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(submission_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $aics_stmt = $db->prepare($aics_query);
    $aics_stmt->bindParam(':from', $date_from);
    $aics_stmt->bindParam(':to', $date_to);
    $aics_stmt->execute();
    $reports['aics'] = $aics_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // AICS totals
    $aics_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as total_rejected,
        SUM(amount_requested) as sum_requested,
        SUM(amount_approved) as sum_approved
        FROM aics_requests
        WHERE submission_date BETWEEN :from AND :to";
    $aics_total_stmt = $db->prepare($aics_total_query);
    $aics_total_stmt->bindParam(':from', $date_from);
    $aics_total_stmt->bindParam(':to', $date_to);
    $aics_total_stmt->execute();
    $reports['aics_totals'] = $aics_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Appointments Summary
if ($report_type == 'all' || $report_type == 'appointments') {
    $app_query = "SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(appointment_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $app_stmt = $db->prepare($app_query);
    $app_stmt->bindParam(':from', $date_from);
    $app_stmt->bindParam(':to', $date_to);
    $app_stmt->execute();
    $reports['appointments'] = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Appointments totals
    $app_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE appointment_date BETWEEN :from AND :to";
    $app_total_stmt = $db->prepare($app_total_query);
    $app_total_stmt->bindParam(':from', $date_from);
    $app_total_stmt->bindParam(':to', $date_to);
    $app_total_stmt->execute();
    $reports['app_totals'] = $app_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Housing Summary
if ($report_type == 'all' || $report_type == 'housing') {
    $housing_query = "SELECT 
        DATE_FORMAT(application_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_beneficiaries
        WHERE application_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(application_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $housing_stmt = $db->prepare($housing_query);
    $housing_stmt->bindParam(':from', $date_from);
    $housing_stmt->bindParam(':to', $date_to);
    $housing_stmt->execute();
    $reports['housing'] = $housing_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Housing totals
    $housing_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released
        FROM housing_beneficiaries
        WHERE application_date BETWEEN :from AND :to";
    $housing_total_stmt = $db->prepare($housing_total_query);
    $housing_total_stmt->bindParam(':from', $date_from);
    $housing_total_stmt->bindParam(':to', $date_to);
    $housing_total_stmt->execute();
    $reports['housing_totals'] = $housing_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. Documents Summary
if ($report_type == 'all' || $report_type == 'documents') {
    $doc_query = "SELECT 
        DATE_FORMAT(upload_date, '%Y-%m-%d') as date,
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents
        WHERE upload_date BETWEEN :from AND :to
        GROUP BY DATE_FORMAT(upload_date, '%Y-%m-%d')
        ORDER BY date DESC";
    
    $doc_stmt = $db->prepare($doc_query);
    $doc_stmt->bindParam(':from', $date_from);
    $doc_stmt->bindParam(':to', $date_to);
    $doc_stmt->execute();
    $reports['documents'] = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Documents totals
    $doc_total_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents
        WHERE upload_date BETWEEN :from AND :to";
    $doc_total_stmt = $db->prepare($doc_total_query);
    $doc_total_stmt->bindParam(':from', $date_from);
    $doc_total_stmt->bindParam(':to', $date_to);
    $doc_total_stmt->execute();
    $reports['doc_totals'] = $doc_total_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle export (simplified - would need PHPSpreadsheet for full export)
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers based on report type
    if ($report_type == 'all' || $report_type == 'aics') {
        fputcsv($output, ['AICS Report', 'Date Range: ' . $date_from . ' to ' . $date_to]);
        fputcsv($output, ['Date', 'Total', 'Approved', 'Rejected', 'Pending', 'Amount Requested', 'Amount Approved']);
        foreach ($reports['aics'] as $row) {
            fputcsv($output, [
                $row['date'],
                $row['total_requests'],
                $row['approved'],
                $row['rejected'],
                $row['pending'],
                $row['total_requested'],
                $row['total_approved']
            ]);
        }
        fputcsv($output, []); // Empty line
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Enhanced navbar with home link -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="../index.php" style="text-decoration: none; color: inherit;">
                    <h1>ONE PALAYAN-KALINGA HUB</h1>
                    <p class="subtitle">CSWDO Palayan City</p>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="../services.php">Services</a></li>
                <li><a href="../appointments.php">Appointments</a></li>
                <li><a href="../track-aics.php">Track AICS</a></li>
                <li><a href="../housing.php">Housing Program</a></li>
                <li class="dropdown">
                    <a href="#"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?> <i class="fas fa-caret-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="../staff-dashboard.php">Dashboard</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="user-profile">
                <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p>Staff</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../staff-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage-appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                <li><a href="manage-aics.php"><i class="fas fa-hand-holding-heart"></i> AICS Requests</a></li>
                <li><a href="manage-housing.php"><i class="fas fa-home"></i> Housing</a></li>
                <li><a href="verify-documents.php"><i class="fas fa-file-alt"></i> Verify Documents</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li class="menu-divider"></li>
                <li><a href="../index.php"><i class="fas fa-globe"></i> View Main Site</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <!-- Breadcrumb navigation -->
            <div class="breadcrumb">
                <a href="../staff-dashboard.php">Dashboard</a> > 
                <a href="reports.php">Reports</a>
            </div>
            
            <h1>Reports</h1>
            
            <!-- Quick navigation bar -->
            <div class="quick-nav">
                <a href="../index.php" class="quick-nav-btn">
                    <i class="fas fa-home"></i> Main Site
                </a>
                <a href="../staff-dashboard.php" class="quick-nav-btn">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="manage-appointments.php" class="quick-nav-btn">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="manage-aics.php" class="quick-nav-btn">
                    <i class="fas fa-hand-holding-heart"></i> AICS
                </a>
                <a href="manage-housing.php" class="quick-nav-btn">
                    <i class="fas fa-home"></i> Housing
                </a>
                <a href="verify-documents.php" class="quick-nav-btn">
                    <i class="fas fa-file-alt"></i> Documents
                </a>
            </div>
            
            <!-- Date Range Filter -->
            <div class="filters-bar">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label>Report Type</label>
                        <select name="report_type">
                            <option value="all" <?php echo $report_type == 'all' ? 'selected' : ''; ?>>All Reports</option>
                            <option value="aics" <?php echo $report_type == 'aics' ? 'selected' : ''; ?>>AICS Only</option>
                            <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments Only</option>
                            <option value="housing" <?php echo $report_type == 'housing' ? 'selected' : ''; ?>>Housing Only</option>
                            <option value="documents" <?php echo $report_type == 'documents' ? 'selected' : ''; ?>>Documents Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group search-group">
                        <button type="submit" class="btn-primary">Generate Report</button>
                        <a href="reports.php?export=csv&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&report_type=<?php echo $report_type; ?>" 
                           class="btn-secondary">Export CSV</a>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <?php if($report_type == 'all' || $report_type == 'aics'): ?>
            <div class="report-section">
                <h2><i class="fas fa-hand-holding-heart"></i> AICS Summary</h2>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $reports['aics_totals']['total'] ?? 0; ?></div>
                        <div class="summary-label">Total Requests</div>
                    </div>
                    <div class="summary-card approved">
                        <div class="summary-value"><?php echo $reports['aics_totals']['total_approved'] ?? 0; ?></div>
                        <div class="summary-label">Approved</div>
                    </div>
                    <div class="summary-card rejected">
                        <div class="summary-value"><?php echo $reports['aics_totals']['total_rejected'] ?? 0; ?></div>
                        <div class="summary-label">Rejected</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">₱<?php echo number_format($reports['aics_totals']['sum_approved'] ?? 0, 2); ?></div>
                        <div class="summary-label">Total Amount Approved</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Pending</th>
                                <th>Amount Requested</th>
                                <th>Amount Approved</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reports['aics'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total_requests']; ?></td>
                                <td><?php echo $row['approved']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td>₱<?php echo number_format($row['total_requested'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($row['total_approved'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($report_type == 'all' || $report_type == 'appointments'): ?>
            <div class="report-section">
                <h2><i class="fas fa-calendar-check"></i> Appointments Summary</h2>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $reports['app_totals']['total'] ?? 0; ?></div>
                        <div class="summary-label">Total Appointments</div>
                    </div>
                    <div class="summary-card completed">
                        <div class="summary-value"><?php echo $reports['app_totals']['completed'] ?? 0; ?></div>
                        <div class="summary-label">Completed</div>
                    </div>
                    <div class="summary-card pending">
                        <div class="summary-value"><?php echo $reports['app_totals']['pending'] ?? 0; ?></div>
                        <div class="summary-label">Pending</div>
                    </div>
                    <div class="summary-card cancelled">
                        <div class="summary-value"><?php echo $reports['app_totals']['cancelled'] ?? 0; ?></div>
                        <div class="summary-label">Cancelled</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>Cancelled</th>
                                <th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reports['appointments'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['completed']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['cancelled']; ?></td>
                                <td>
                                    <?php 
                                    $rate = ($row['total'] > 0) ? round(($row['completed'] / $row['total']) * 100, 2) : 0;
                                    echo $rate . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($report_type == 'all' || $report_type == 'housing'): ?>
            <div class="report-section">
                <h2><i class="fas fa-home"></i> Housing Summary</h2>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $reports['housing_totals']['total'] ?? 0; ?></div>
                        <div class="summary-label">Total Applications</div>
                    </div>
                    <div class="summary-card approved">
                        <div class="summary-value"><?php echo $reports['housing_totals']['approved'] ?? 0; ?></div>
                        <div class="summary-label">Approved</div>
                    </div>
                    <div class="summary-card pending">
                        <div class="summary-value"><?php echo $reports['housing_totals']['pending'] ?? 0; ?></div>
                        <div class="summary-label">Pending</div>
                    </div>
                    <div class="summary-card released">
                        <div class="summary-value"><?php echo $reports['housing_totals']['released'] ?? 0; ?></div>
                        <div class="summary-label">Released</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Approved</th>
                                <th>Pending</th>
                                <th>Rejected</th>
                                <th>Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reports['housing'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['approved']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                                <td><?php echo $row['released']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($report_type == 'all' || $report_type == 'documents'): ?>
            <div class="report-section">
                <h2><i class="fas fa-file-alt"></i> Documents Summary</h2>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $reports['doc_totals']['total'] ?? 0; ?></div>
                        <div class="summary-label">Total Documents</div>
                    </div>
                    <div class="summary-card verified">
                        <div class="summary-value"><?php echo $reports['doc_totals']['verified'] ?? 0; ?></div>
                        <div class="summary-label">Verified</div>
                    </div>
                    <div class="summary-card pending">
                        <div class="summary-value"><?php echo $reports['doc_totals']['pending'] ?? 0; ?></div>
                        <div class="summary-label">Pending</div>
                    </div>
                    <div class="summary-card rejected">
                        <div class="summary-value"><?php echo $reports['doc_totals']['rejected'] ?? 0; ?></div>
                        <div class="summary-label">Rejected</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Verified</th>
                                <th>Pending</th>
                                <th>Rejected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reports['documents'] as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['total']; ?></td>
                                <td><?php echo $row['verified']; ?></td>
                                <td><?php echo $row['pending']; ?></td>
                                <td><?php echo $row['rejected']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    /* Navigation styles */
    .breadcrumb {
        padding: 10px 0;
        margin-bottom: 20px;
        color: #666;
    }
    
    .breadcrumb a {
        color: #003366;
        text-decoration: none;
    }
    
    .breadcrumb a:hover {
        text-decoration: underline;
    }
    
    .quick-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .quick-nav-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        background: #f5f5f5;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        font-size: 0.9rem;
        transition: all 0.3s;
    }
    
    .quick-nav-btn:hover {
        background: #003366;
        color: white;
    }
    
    .quick-nav-btn i {
        font-size: 1rem;
    }
    
    .sidebar-menu a i {
        width: 20px;
        margin-right: 10px;
        text-align: center;
    }
    
    /* Report styles */
    .filters-bar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filters-form {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .search-group {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .report-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .report-section h2 {
        color: #003366;
        margin-bottom: 20px;
        font-size: 1.3rem;
    }
    
    .report-section h2 i {
        margin-right: 10px;
    }
    
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .summary-card {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #003366;
    }
    
    .summary-card.approved { border-left-color: #4CAF50; }
    .summary-card.rejected { border-left-color: #f44336; }
    .summary-card.pending { border-left-color: #ff9800; }
    .summary-card.completed { border-left-color: #2196F3; }
    .summary-card.cancelled { border-left-color: #9e9e9e; }
    .summary-card.verified { border-left-color: #4CAF50; }
    .summary-card.released { border-left-color: #00bcd4; }
    
    .summary-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #003366;
        margin-bottom: 5px;
    }
    
    .summary-label {
        color: #666;
        font-size: 0.9rem;
    }
    
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f5f5f5;
        padding: 12px;
        text-align: left;
        color: #333;
        font-weight: 600;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .data-table tr:hover {
        background: #f9f9f9;
    }
    
    .btn-primary {
        background: #003366;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
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
    
    @media (max-width: 768px) {
        .filters-form {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .search-group {
            flex-direction: column;
        }
        
        .search-group button,
        .search-group a {
            width: 100%;
            text-align: center;
        }
        
        .quick-nav {
            flex-direction: column;
        }
        
        .quick-nav-btn {
            width: 100%;
            justify-content: center;
        }
        
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
    
    <script src="../js/main.js"></script>
</body>
</html>
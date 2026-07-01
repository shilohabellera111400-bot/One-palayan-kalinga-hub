<?php
require_once __DIR__ . '/config/database.php';

$tracking_number = isset($_GET['tracking']) ? htmlspecialchars(trim($_GET['tracking'])) : '';
$request = null;
$error = '';

if ($tracking_number) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!preg_match('/^AICS-\d{6}$/', $tracking_number)) {
        $error = "Invalid tracking number format. Please use format: AICS-000001";
    } else {
        $id = intval(str_replace('AICS-', '', $tracking_number));
        
        // FIXED: Select only columns that exist in your table
        $query = "SELECT a.aics_id, a.user_id, a.request_type, a.amount_requested, a.amount_approved, 
                         a.status, a.submission_date, a.review_date, a.approval_date, a.release_date, 
                         a.rejection_reason, a.purpose,
                         u.first_name, u.last_name 
                  FROM aics_requests a
                  JOIN users u ON a.user_id = u.user_id
                  WHERE a.aics_id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "No request found with tracking number: " . htmlspecialchars($tracking_number);
        }
    }
}

// Get recent stats
$database = new Database();
$db = $database->getConnection();
$stats_query = "SELECT 
    COUNT(*) as total_tracked,
    COUNT(DISTINCT user_id) as unique_users
    FROM aics_requests 
    WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track AICS Request - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== PROFESSIONAL COLOR PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-dark: #5a0202;
            --maroon-light: #9a1515;
            --midnight-light: #1a6a78;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-100: #F1F3F8;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 16px 32px rgba(0, 0, 0, 0.08);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.5;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

        /* ========== NAVBAR STYLES ========== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            z-index: 1000;
            transition: all 0.3s ease-in-out;
            background: var(--midnight);
            border-bottom: none;
        }

        .navbar.scrolled {
            background: var(--white);
            box-shadow: var(--shadow-md);
            padding: 0.7rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: var(--maroon);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            transition: all 0.3s;
        }

        .logo-text h2 {
            font-size: 1.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, white 0%, var(--light-blue) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
        }

        .logo-text span {
            font-size: 0.7rem;
            font-weight: 500;
            background: var(--tan);
            padding: 2px 10px;
            border-radius: 40px;
            margin-left: 8px;
            color: #3b2a1f;
        }

        .navbar.scrolled .logo-text h2 {
            background: linear-gradient(135deg, var(--midnight) 0%, var(--light-blue) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .navbar.scrolled .logo-icon {
            background: var(--maroon);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            position: relative;
        }

        .nav-links a:hover {
            color: white;
        }

        .navbar.scrolled .nav-links a {
            color: var(--gray-700);
        }

        .navbar.scrolled .nav-links a:hover {
            color: var(--maroon);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .profile-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.12);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        .profile-icon i {
            font-size: 1.1rem;
        }
        .profile-icon span {
            font-size: 0.85rem;
        }
        .profile-icon:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        .logout-icon {
            background: rgba(255, 255, 255, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }
        .logout-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .navbar.scrolled .profile-icon {
            background: var(--alabaster);
            color: var(--midnight);
        }
        .navbar.scrolled .profile-icon:hover {
            background: var(--tan);
        }
        .navbar.scrolled .logout-icon {
            background: var(--gray-100);
            color: var(--maroon);
        }
        .navbar.scrolled .logout-icon:hover {
            background: var(--gray-200);
        }

        .login-btn {
            background: var(--maroon);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s;
        }
        .login-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        .navbar.scrolled .login-btn {
            background: var(--maroon);
            color: white !important;
        }
        .navbar.scrolled .login-btn:hover {
            background: var(--maroon-light);
        }

        .register-btn {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s;
        }
        .register-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .navbar.scrolled .register-btn {
            background: var(--alabaster);
            color: var(--midnight) !important;
        }
        .navbar.scrolled .register-btn:hover {
            background: var(--tan);
        }

        .hamburger {
            display: none;
            cursor: pointer;
            background: none;
            border: none;
            padding: 8px;
        }

        .hamburger span {
            display: block;
            width: 25px;
            height: 2px;
            margin: 5px 0;
            background: white;
            transition: all 0.3s;
        }

        .navbar.scrolled .hamburger span {
            background: var(--midnight);
        }

        /* Tracking Page */
        .tracking-page { 
            padding: 140px 0 60px; 
            background: var(--gray-50); 
            min-height: 80vh; 
        }
        .tracking-header { text-align: center; margin-bottom: 40px; }
        .tracking-header h1 { color: var(--midnight); font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; }
        .tracking-header p { color: var(--gray-600); font-size: 1rem; max-width: 600px; margin: 0 auto; }

        .tracking-stats {
            display: flex; justify-content: center; gap: 40px; margin-top: 20px;
            padding: 20px; background: var(--white); border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm); max-width: 500px; margin: 20px auto;
            border: 1px solid var(--gray-200);
        }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--midnight); }
        .stat-label { color: var(--gray-600); font-size: 0.8rem; }

        .tracking-search { max-width: 600px; margin: 30px auto; background: var(--white); padding: 30px; border-radius: var(--radius-card); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); }
        .search-box { display: flex; gap: 10px; }
        .search-box input { flex: 1; padding: 14px; border: 2px solid var(--gray-200); border-radius: var(--radius-element); font-size: 1rem; transition: all 0.3s; }
        .search-box input:focus { outline: none; border-color: var(--midnight); box-shadow: 0 0 0 3px rgba(15,65,74,0.1); }
        .btn-track { padding: 14px 30px; background: var(--maroon); color: white; border: none; border-radius: 40px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-track:hover { background: var(--maroon-light); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(127,3,3,0.3); }

        .tracking-result { background: var(--white); border-radius: var(--radius-card); padding: 30px; margin-top: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--gray-200); flex-wrap: wrap; gap: 15px; }
        .request-header h2 { color: var(--midnight); margin: 0; font-size: 1.3rem; }
        .tracking-number { background: var(--gray-100); padding: 8px 20px; border-radius: 40px; font-family: monospace; font-size: 1rem; color: var(--midnight); font-weight: 700; }

        .status-timeline { display: flex; justify-content: space-between; margin: 40px 0; position: relative; padding: 0 20px; flex-wrap: wrap; gap: 20px; }
        .status-timeline::before { content: ''; position: absolute; top: 30px; left: 40px; right: 40px; height: 3px; background: linear-gradient(90deg, #4CAF50 0%, #4CAF50 var(--progress, 0%), var(--gray-200) var(--progress, 0%), var(--gray-200) 100%); z-index: 1; border-radius: 3px; }
        .timeline-step { flex: 1; position: relative; z-index: 2; background: white; text-align: center; padding: 0 10px; }
        .step-icon { width: 60px; height: 60px; background: var(--gray-200); color: var(--gray-600); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-weight: 700; font-size: 1.2rem; border: 3px solid white; box-shadow: var(--shadow-sm); transition: all 0.3s; }
        .timeline-step.completed .step-icon { background: #4CAF50; color: white; transform: scale(1.05); }
        .timeline-step.active .step-icon { background: #FF9800; color: white; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
        .step-content h4 { color: var(--midnight); margin-bottom: 5px; font-size: 0.9rem; }
        .step-content p { color: var(--gray-600); font-size: 0.8rem; }

        .request-details { margin-top: 40px; padding: 20px; background: var(--gray-50); border-radius: var(--radius-element); }
        .request-details h3 { color: var(--midnight); margin-bottom: 20px; font-size: 1.1rem; }
        .details-table { width: 100%; border-collapse: collapse; }
        .details-table tr { border-bottom: 1px solid var(--gray-200); }
        .details-table tr:last-child { border-bottom: none; }
        .details-table th { width: 180px; padding: 15px; color: var(--gray-600); font-weight: 500; text-align: left; }
        .details-table td { padding: 15px; color: var(--gray-700); }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 30px; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; }
        .status-submitted { background: #FFF3CD; color: #856404; }
        .status-reviewing { background: #CCE5FF; color: #004085; }
        .status-approved { background: #D4EDDA; color: #155724; }
        .status-rejected { background: #F8D7DA; color: #721C24; }
        .status-released { background: #D1ECF1; color: #0C5460; }

        .alert { padding: 15px 20px; border-radius: var(--radius-element); margin: 20px 0; }
        .alert-error { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }

        .info-section { margin-top: 50px; }
        .info-section h3 { text-align: center; color: var(--midnight); margin-bottom: 30px; font-size: 1.5rem; font-weight: 700; }
        .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-top: 20px; }
        .step-card { background: var(--white); padding: 25px; border-radius: var(--radius-card); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.3s; }
        .step-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .step-number { width: 40px; height: 40px; background: var(--maroon); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-weight: 700; }
        .step-card h4 { color: var(--midnight); margin-bottom: 10px; }
        .step-card p { color: var(--gray-600); font-size: 0.85rem; line-height: 1.5; }

        .need-help { text-align: center; margin-top: 40px; padding: 30px; background: var(--white); border-radius: var(--radius-card); border: 1px solid var(--gray-200); }
        .need-help i { font-size: 2rem; color: var(--maroon); margin-bottom: 10px; }
        .need-help h4 { color: var(--midnight); margin-bottom: 10px; }
        .need-help p { color: var(--gray-600); margin-bottom: 5px; }
        .btn-login { display: inline-block; padding: 12px 28px; background: var(--maroon); color: white; text-decoration: none; border-radius: 40px; margin-top: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-login:hover { background: var(--maroon-light); transform: translateY(-2px); }

        /* Footer */
        .footer {
            background: var(--midnight);
            color: white;
            padding: 60px 0 20px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h3,
        .footer-section h4 {
            margin-bottom: 20px;
            font-weight: 700;
        }

        .footer-section h3 {
            color: var(--tan);
            font-size: 1.2rem;
        }

        .footer-section h4 {
            color: var(--light-blue);
            font-size: 1rem;
        }

        .footer-section p {
            color: rgba(255,255,255,0.7);
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.85rem;
        }

        .footer-section a:hover {
            color: var(--tan);
        }

        .contact-number, .email {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
        }

        .status-timeline { --progress: <?php 
            $progress_map = ['submitted' => 25, 'reviewing' => 50, 'approved' => 75, 'released' => 100];
            echo $request ? ($progress_map[$request['status']] ?? 0) : 0;
        ?>%; }

        @media (max-width: 968px) {
            .tracking-page { padding: 120px 0 60px; }
            .tracking-header h1 { font-size: 2rem; }
            .search-box { flex-direction: column; }
            .btn-track { width: 100%; justify-content: center; }
            .status-timeline { flex-direction: column; }
            .status-timeline::before { display: none; }
            .timeline-step { display: flex; align-items: center; text-align: left; gap: 20px; }
            .step-icon { margin: 0; }
            .request-header { flex-direction: column; text-align: center; }
            .steps-grid { grid-template-columns: 1fr; }
            .details-table th { display: block; width: 100%; padding-bottom: 0; }
            .details-table td { display: block; width: 100%; padding-top: 0; }
            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                padding: 20px 0;
                gap: 15px;
            }
            .nav-links.active { display: flex; }
            .hamburger { display: block; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1.5rem; }
            .navbar.scrolled { padding: 0.6rem 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Navbar - Same as index.php -->
    <nav class="navbar" id="navbar">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-hand-holding-heart"></i>
            </div>
            <div class="logo-text">
                <h2>ONE PALAYAN-KALINGA HUB</h2>
                <span>CSWDO Palayan City</span>
            </div>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <a href="services.php">Services</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            
            <?php if(function_exists('isLoggedIn') && isLoggedIn()): ?>
                <div class="user-menu">
                    <a href="dashboard.php" class="profile-icon">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                    </a>
                    <a href="logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            <?php endif; ?>
        </div>
        <button class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>
    
    <div class="tracking-page">
        <div class="container">
            <div class="tracking-header">
                <h1>Track Your AICS Request</h1>
                <p>Enter your tracking number to check the real-time status of your assistance request</p>
            </div>

            <div class="tracking-stats">
                <div class="stat-item"><div class="stat-value"><?php echo number_format($stats['total_tracked'] ?? 0); ?></div><div class="stat-label">Requests This Month</div></div>
                <div class="stat-item"><div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div><div class="stat-label">Beneficiaries</div></div>
            </div>
            
            <div class="tracking-search">
                <form method="GET" action="" id="trackingForm">
                    <div class="search-box">
                        <input type="text" name="tracking" id="trackingInput" placeholder="Enter Tracking Number (e.g., AICS-000001)" value="<?php echo htmlspecialchars($tracking_number); ?>" pattern="AICS-\d{6}" title="Please enter in format: AICS-000001" required>
                        <button type="submit" class="btn-track"><i class="fas fa-search"></i> Track Request</button>
                    </div>
                    <small style="display: block; text-align: center; margin-top: 10px; color: var(--gray-600);">Format: AICS-000001 (AICS followed by 6 digits)</small>
                </form>
            </div>
            
            <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($tracking_number && $request): ?>
            <div class="tracking-result">
                <div class="request-header">
                    <h2><i class="fas fa-clipboard-list"></i> Request Status</h2>
                    <span class="tracking-number"><i class="fas fa-hashtag"></i> AICS-<?php echo str_pad($request['aics_id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="status-timeline">
                    <div class="timeline-step <?php echo in_array($request['status'], ['submitted', 'reviewing', 'approved', 'released']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'submitted' ? 'active' : ''; ?>">
                        <div class="step-icon">01</div><div class="step-content"><h4>Submitted</h4><p><?php echo date('M d, Y', strtotime($request['submission_date'])); ?></p></div>
                    </div>
                    <div class="timeline-step <?php echo in_array($request['status'], ['reviewing', 'approved', 'released']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'reviewing' ? 'active' : ''; ?>">
                        <div class="step-icon">02</div><div class="step-content"><h4>Under Review</h4><p><?php echo $request['review_date'] ? date('M d, Y', strtotime($request['review_date'])) : 'Pending'; ?></p></div>
                    </div>
                    <div class="timeline-step <?php echo in_array($request['status'], ['approved', 'released']) ? 'completed' : ''; ?> <?php echo $request['status'] == 'approved' ? 'active' : ''; ?>">
                        <div class="step-icon">03</div><div class="step-content"><h4>Approved</h4><p><?php echo $request['approval_date'] ? date('M d, Y', strtotime($request['approval_date'])) : 'Pending'; ?></p></div>
                    </div>
                    <div class="timeline-step <?php echo $request['status'] == 'released' ? 'completed active' : ''; ?>">
                        <div class="step-icon">04</div><div class="step-content"><h4>Released</h4><p><?php echo $request['release_date'] ? date('M d, Y', strtotime($request['release_date'])) : 'Pending'; ?></p></div>
                    </div>
                </div>
                
                <div class="request-details">
                    <h3><i class="fas fa-info-circle"></i> Request Details</h3>
                    <table class="details-table">
                        <tr><th>Applicant Name:</th><td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td></tr>
                        <tr><th>Type of Assistance:</th><td><?php echo ucfirst($request['request_type'] ?? 'N/A'); ?></td></tr>
                        <tr><th>Amount Requested:</th><td><strong>₱<?php echo number_format($request['amount_requested'] ?? 0, 2); ?></strong></td></tr>
                        <?php if(isset($request['amount_approved']) && $request['amount_approved'] > 0): ?>
                        <tr><th>Amount Approved:</th><td><strong style="color: #4CAF50;">₱<?php echo number_format($request['amount_approved'], 2); ?></strong></td></tr>
                        <?php endif; ?>
                        <tr><th>Purpose:</th><td><?php echo nl2br(htmlspecialchars($request['purpose'] ?? 'Not specified')); ?></td></tr>
                        <tr><th>Current Status:</th><td><span class="status-badge status-<?php echo $request['status']; ?>"><i class="fas fa-<?php echo $request['status'] == 'submitted' ? 'clock' : ($request['status'] == 'reviewing' ? 'search' : ($request['status'] == 'approved' ? 'check-circle' : ($request['status'] == 'released' ? 'gift' : 'times-circle'))); ?>"></i> <?php echo ucfirst($request['status']); ?></span></td></tr>
                        <?php if(isset($request['rejection_reason']) && $request['rejection_reason']): ?>
                        <tr><th>Rejection Reason:</th><td style="color: #f44336;"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($request['rejection_reason']); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if(function_exists('isLoggedIn') && isLoggedIn() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $request['user_id']): ?>
                <div style="margin-top: 20px; text-align: right;">
                    <a href="view-aics.php?id=<?php echo $request['aics_id']; ?>" class="btn-track" style="display: inline-block; text-decoration: none;"><i class="fas fa-eye"></i> View Full Details</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-section">
                <h3><i class="fas fa-question-circle"></i> How to Track Your Request</h3>
                <div class="steps-grid">
                    <div class="step-card"><div class="step-number">1</div><h4>Submit Application</h4><p>After submitting your AICS request, you'll receive a tracking number via email and SMS</p></div>
                    <div class="step-card"><div class="step-number">2</div><h4>Enter Tracking Number</h4><p>Type or paste your tracking number in the format AICS-000001 in the search box above</p></div>
                    <div class="step-card"><div class="step-number">3</div><h4>View Status</h4><p>See real-time updates on your request including review, approval, and release dates</p></div>
                </div>
            </div>

            <div class="need-help">
                <i class="fas fa-headset"></i>
                <h4>Need Assistance?</h4>
                <p>If you've lost your tracking number or need help, please contact our office</p>
                <p><i class="fas fa-phone"></i> (044) 123-4567 | <i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p>
                <?php if(!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login to View All Requests</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3>ONE PALAYAN-KALINGA HUB</h3>
                    <p>CSWDO Palayan City — Integrated Social Services Platform</p>
                    <div>
                        <h4>SERVICES</h4>
                        <ul>
                            <li><a href="appointments.php">Appointment Booking</a></li>
                            <li><a href="aics.php">AICS Application</a></li>
                            <li><a href="housing.php">Housing Programs</a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-section">
                    <h4>RESOURCES</h4>
                    <ul>
                        <li><a href="user-guide.php">User Guide</a></li>
                        <li><a href="faqs.php">FAQs</a></li>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>CONTACT US</h4>
                    <p>City Social Welfare and Development Office<br>
                    Palayan City, Nueva Ecija, Philippines</p>
                    <p class="contact-number"><i class="fas fa-phone"></i> +63 (44) 123 4567</p>
                    <p class="email"><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 One Palayan-Kalinga Hub. All rights reserved. | A project of CSWDO Palayan City</p>
            </div>
        </div>
    </footer>

    <script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
    }

    document.getElementById('trackingInput')?.addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase();
        value = value.replace(/[^AICS0-9]/g, '');
        if (value.startsWith('AICS') && value.length > 4) value = 'AICS-' + value.substring(4);
        e.target.value = value;
    });
    document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
        if (!/^AICS-\d{6}$/.test(document.getElementById('trackingInput').value)) {
            e.preventDefault();
            alert('Please enter a valid tracking number (e.g., AICS-000001)');
        }
    });
    <?php if($request): ?>
    document.addEventListener('DOMContentLoaded', function() { document.querySelector('.tracking-result')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    <?php endif; ?>
    </script>
    
    <script src="js/main.js"></script>
</body>
</html>
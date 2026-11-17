<?php
// print-claim-stub.php - Official Claim Stub for Senior Citizen & PWD Services
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$type = sanitize($_GET['type'] ?? '');
$id = intval($_GET['id'] ?? 0);

if (!in_array($type, ['senior', 'pwd', 'housing']) || $id <= 0) {
    die("Invalid claim stub request.");
}

$secondary_control_label = "Purchase Booklet Control No.";
$window_icon = "fa-id-card";
$footer_dept = "Office of the Senior Citizens Affairs & PWD Welfare Division • City Hall Compound, Palayan City";

// Fetch record
if ($type === 'senior') {
    $stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.contact_number, r.barangay, r.resident_number, r.birth_date
                          FROM senior_applications a
                          JOIN users u ON a.user_id = u.user_id
                          LEFT JOIN residents r ON a.user_id = r.user_id
                          WHERE a.application_id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    // Permission check: resident must own application unless staff/admin
    if (!isStaff() && !isAdmin() && ($app['user_id'] ?? 0) != $_SESSION['user_id']) {
        die("Unauthorized access to claim stub.");
    }

    $service_title = "Provision of Identification Card & Purchase Booklet to Senior Citizens";
    $window_assigned = "Window 3 (OSCA Special Lane)";
    $control_id_label = "OSCA ID Number";
    $control_id_val = !empty($app['osca_id_number']) ? $app['osca_id_number'] : "Pending Assignment upon Release";
    $booklet_val = !empty($app['booklet_number']) ? $app['booklet_number'] : "Pending Issuance";
    $app_title = "SENIOR CITIZEN CLAIM STUB";
    $requirements_to_present = [
        "Present this printed or digital Claim Stub at Window 3",
        "1 Valid Government ID / Voter's ID for identity verification",
        "If through a representative: Authorization letter signed by the Senior Citizen, plus Valid ID of both representative and Senior"
    ];
} elseif ($type === 'pwd') {
    $stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.contact_number, r.barangay, r.resident_number, r.birth_date
                          FROM pwd_applications a
                          JOIN users u ON a.user_id = u.user_id
                          LEFT JOIN residents r ON a.user_id = r.user_id
                          WHERE a.application_id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!isStaff() && !isAdmin() && ($app['user_id'] ?? 0) != $_SESSION['user_id']) {
        die("Unauthorized access to claim stub.");
    }

    $service_title = "Issuance of Person with Disability (PWD) ID & Purchase Booklet";
    $window_assigned = "Window 1 (Special Lane)";
    $control_id_label = "PWD ID Number";
    $control_id_val = !empty($app['pwd_id_number']) ? $app['pwd_id_number'] : "Pending Assignment upon Release";
    $booklet_val = !empty($app['booklet_number']) ? $app['booklet_number'] : "Pending Issuance";
    $app_title = "PWD BENEFICIARY CLAIM STUB";
    $requirements_to_present = [
        "Present this printed or digital Claim Stub at Window 1",
        "Original Medical Certificate verifying disability category",
        "If through a caregiver/parent: Valid ID of parent/caregiver and proof of relation"
    ];
} elseif ($type === 'housing') {
    $stmt = $db->prepare("SELECT h.*, u.first_name, u.last_name, u.email, u.contact_number, u.address,
                                 r.resident_number, r.birth_date, r.barangay, r.street,
                                 p.program_name, p.location as program_location,
                                 s.first_name as staff_first_name, s.last_name as staff_last_name
                          FROM housing_beneficiaries h
                          JOIN users u ON h.user_id = u.user_id
                          JOIN housing_programs p ON h.program_id = p.program_id
                          LEFT JOIN residents r ON h.user_id = r.user_id
                          LEFT JOIN staff s ON h.approved_by = s.user_id
                          WHERE h.beneficiary_id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!isStaff() && !isAdmin() && ($app['user_id'] ?? 0) != $_SESSION['user_id']) {
        die("Unauthorized access to claim stub.");
    }

    $app['tracking_number'] = 'HSG-' . date('Y', strtotime($app['application_date'] ?? 'now')) . '-' . str_pad($app['beneficiary_id'], 5, '0', STR_PAD_LEFT);
    $app['created_at'] = $app['application_date'];

    $is_insite = (($app['housing_type'] ?? '') === 'in_site');
    $service_title = $is_insite 
        ? "Palayan In-Site Housing Assistance (Sariling Lupa / Core Shelter Grant)"
        : "Socialized Housing & Community Resettlement Assistance Program";
    $window_assigned = "Window 2 (Housing & Resettlement Division)";
    $control_id_label = $is_insite ? "Grant / Lot Voucher Code" : "Assigned Housing Unit";
    $control_id_val = !empty($app['unit_assigned']) ? $app['unit_assigned'] : "Pending Qualification & Assessment";
    $secondary_control_label = "Scheduled Field Visit / Interview";
    $booklet_val = !empty($app['interview_date']) ? date('M d, Y', strtotime($app['interview_date'])) : "To Be Scheduled by CSWDO";
    $app_title = $is_insite ? "IN-SITE HOUSING INTAKE & CLAIM STUB" : "HOUSING ASSISTANCE INTAKE & CLAIM STUB";
    $window_icon = "fa-home";
    $footer_dept = "Housing & Community Resettlement Division • CSWDO Palayan City Hall";

    if ($is_insite) {
        $requirements_to_present = [
            "Present this printed or digital Claim Stub during the On-Site Lot Inspection",
            "Original or Certified True Copy of Proof of Land Ownership (TCT, Tax Declaration, or Notarized Consent/Waiver)",
            "Barangay Certificate of Lot Residency & Indigency",
            "1 Valid Government-issued Photo ID of Principal Applicant",
            "Lot Vicinity Sketch Plan showing nearest landmarks and right-of-way"
        ];
    } else {
        $requirements_to_present = [
            "Present this printed or digital Claim Stub at Window 2 (Housing Division)",
            "1 Valid Government-issued Photo ID of Principal Applicant",
            "Original Barangay Certificate of Indigency & Residency in Palayan City",
            "Latest Electric or Water Utility Proof of Billing displaying address",
            "Proof of Income / Certificate of Low Income or Indigency"
        ];
    }
}

if (!$app) {
    die("Application record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Claim Stub - <?php echo htmlspecialchars($app['tracking_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once __DIR__ . '/includes/pwa-meta.php'; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #eef2f5;
            color: #212529;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }
        .stub-card {
            background: #ffffff;
            width: 100%;
            max-width: 650px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 2px dashed #b0bec5;
            padding: 32px;
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #7F0303;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .header h3 {
            font-size: 0.85rem;
            color: #616161;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }
        .header h1 {
            font-size: 1.4rem;
            color: #7F0303;
            font-weight: 900;
            margin: 4px 0;
        }
        .header p {
            font-size: 0.82rem;
            color: #424242;
            font-weight: 600;
        }
        .badge-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fbf1f1;
            border: 1px solid #ffcdd2;
            padding: 10px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .badge-bar .tracking {
            font-family: monospace;
            font-size: 1.15rem;
            font-weight: 800;
            color: #7F0303;
        }
        .badge-bar .status {
            display: inline-block;
            background: #2e7d32;
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 22px;
        }
        .data-item {
            background: #f8fafc;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .data-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .data-val {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .window-box {
            background: linear-gradient(135deg, #0288D1, #01579B);
            color: white;
            padding: 14px 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }
        .window-box div h4 {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .window-box div h2 {
            font-size: 1.25rem;
            font-weight: 900;
        }
        .requirements-box {
            background: #fff9db;
            border: 1px solid #ffe066;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 24px;
        }
        .requirements-box h4 {
            font-size: 0.85rem;
            color: #856404;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .requirements-box ul {
            list-style: none;
            padding-left: 5px;
        }
        .requirements-box li {
            font-size: 0.8rem;
            color: #495057;
            margin-bottom: 5px;
            position: relative;
            padding-left: 18px;
        }
        .requirements-box li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #2e7d32;
            font-weight: 800;
        }
        .footer-barcode {
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }
        .barcode-mock {
            font-family: monospace;
            letter-spacing: 6px;
            font-size: 1.3rem;
            font-weight: 900;
            color: #334155;
            background: repeating-linear-gradient(90deg, #000 0px, #000 2px, transparent 2px, transparent 4px);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
            padding: 4px 10px;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn-print {
            background: #7F0303;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(127,3,3,0.3);
            text-decoration: none;
        }
        .btn-back {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .stub-card { padding: 16px; border-radius: 14px; }
            .header h1 { font-size: 1.15rem; }
            .badge-bar { flex-direction: column; gap: 8px; align-items: flex-start; }
            .data-grid { grid-template-columns: 1fr; }
            .btn-print, .btn-back { padding: 8px 16px; font-size: 0.78rem; }
        }
        @media print {
            body { background: white; padding: 0; }
            .stub-card { box-shadow: none; border: 1.5px dashed #000; width: 100%; max-width: 100%; padding: 20px; }
            .action-buttons { display: none; }
        }
    </style>
</head>
<body>
    <div class="stub-card">
        <div class="header">
            <h3>City Government of Palayan • CSWDO</h3>
            <h1>One Palayan-Kalinga Hub</h1>
            <p><?php echo htmlspecialchars($service_title); ?></p>
        </div>

        <div class="badge-bar">
            <div>
                <span style="font-size:0.75rem;color:#757575;text-transform:uppercase;font-weight:700;display:block;">Tracking Number</span>
                <span class="tracking"><?php echo htmlspecialchars($app['tracking_number']); ?></span>
            </div>
            <div class="status">
                <i class="fas fa-check-circle"></i> <?php echo strtoupper($app['status']); ?>
            </div>
        </div>

        <div class="data-grid">
            <div class="data-item">
                <div class="data-label">Applicant Full Name</div>
                <div class="data-val"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
            </div>
            <div class="data-item">
                <div class="data-label">Barangay Residence</div>
                <div class="data-val"><?php echo htmlspecialchars($app['barangay'] ?? 'Palayan City'); ?></div>
            </div>
            <div class="data-item">
                <div class="data-label"><?php echo htmlspecialchars($control_id_label); ?></div>
                <div class="data-val" style="color: #2e7d32;"><?php echo htmlspecialchars($control_id_val); ?></div>
            </div>
            <div class="data-item">
                <div class="data-label"><?php echo htmlspecialchars($secondary_control_label); ?></div>
                <div class="data-val" style="color: #0288D1;"><?php echo htmlspecialchars($booklet_val); ?></div>
            </div>
            <div class="data-item">
                <div class="data-label">Contact Number</div>
                <div class="data-val"><?php echo htmlspecialchars($app['contact_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="data-item">
                <div class="data-label">Date Processed</div>
                <div class="data-val"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></div>
            </div>
        </div>

        <div class="window-box">
            <div>
                <h4>Official Service Window / Lane</h4>
                <h2><?php echo htmlspecialchars($window_assigned); ?></h2>
            </div>
            <div style="font-size: 2.2rem; opacity: 0.9;">
                <i class="fas <?php echo htmlspecialchars($window_icon); ?>"></i>
            </div>
        </div>

        <div class="requirements-box">
            <h4><i class="fas fa-info-circle"></i> Instructions &amp; Requirements to Present:</h4>
            <ul>
                <?php foreach ($requirements_to_present as $req): ?>
                    <li><?php echo htmlspecialchars($req); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-barcode">
            <div class="barcode-mock"><?php echo htmlspecialchars($app['tracking_number']); ?></div>
            <p style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;"><?php echo htmlspecialchars($footer_dept); ?></p>
        </div>

        <div class="action-buttons">
            <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Official Stub</button>
            <?php 
                $return_url = ($type === 'housing') 
                    ? 'view-housing.php?id=' . $id 
                    : ($type === 'senior' ? 'senior.php?tab=status' : 'pwd.php?tab=status');
            ?>
            <a href="<?php echo $return_url; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Return to Portal</a>
        </div>
    </div>
</body>
</html>
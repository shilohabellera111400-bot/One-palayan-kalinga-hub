<?php
// printable-forms.php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Staff and Admin should not access this page
if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Fetch resident profile details for pre-filling printable templates
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch resident details for GIS-CIU pre-fill
$res_query = "SELECT * FROM residents WHERE user_id = :user_id LIMIT 1";
$res_stmt = $db->prepare($res_query);
$res_stmt->bindParam(':user_id', $_SESSION['user_id']);
$res_stmt->execute();
$resident_info = $res_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch latest PWD application if any (for PWD ID number, application date, and status)
$pwd_app_stmt = $db->prepare("SELECT * FROM pwd_applications WHERE user_id = :user_id ORDER BY application_id DESC LIMIT 1");
$pwd_app_stmt->execute([':user_id' => $_SESSION['user_id']]);
$pwd_app = $pwd_app_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch latest Senior application if any (for tracking number and application date)
$senior_app_stmt = $db->prepare("SELECT * FROM senior_applications WHERE user_id = :user_id ORDER BY application_id DESC LIMIT 1");
$senior_app_stmt->execute([':user_id' => $_SESSION['user_id']]);
$senior_app = $senior_app_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Get initials for sidebar avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Profile picture path
$profile_picture = $user_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists($profile_picture)
    ? $profile_picture
    : '';

// Check if dark mode is enabled
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get greeting based on time
$hour = date('H');
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
$greeting_emoji = $hour < 12 ? '🌅' : ($hour < 18 ? '☀️' : '🌙');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printable Forms - One Palayan-Kalinga Hub</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ========== FORMS GRID ========== */
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .form-item-card {
            padding: 24px;
            display: flex;
            flex-direction: column;
            height: 100%;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease both;
        }

        .form-item-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-item-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .form-item-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .form-item-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .form-item-card:nth-child(5) {
            animation-delay: 0.5s;
        }

        .form-item-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--gold);
        }

        .form-item-icon {
            font-size: 2.2rem;
            color: var(--maroon);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .form-item-card:hover .form-item-icon {
            transform: scale(1.1) rotate(-5deg);
        }

        .form-item-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .form-item-card p {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
        }

        .btn-primary {
            background: var(--maroon);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px) scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:active {
            transform: scale(0.97);
        }

        /* ========== PRINT MODAL ========== */
        .print-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            padding: 40px;
            overflow-y: auto;
            animation: fadeInScale 0.3s ease;
        }

        .print-modal .modal-content {
            max-width: 900px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 32px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .print-modal .modal-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }

        .print-modal .modal-actions .btn-close {
            background: var(--badge-bg);
            color: var(--text-secondary);
            padding: 10px 24px;
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .print-modal .modal-actions .btn-close:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }

        @media print {
            @page {
                size: auto;
                margin: 8mm 10mm;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body > *:not(#printModal) {
                display: none !important;
            }

            .print-modal {
                display: block !important;
                position: static !important;
                inset: auto !important;
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
                backdrop-filter: none !important;
                box-shadow: none !important;
                width: 100% !important;
            }

            .print-modal .modal-content {
                box-shadow: none !important;
                border: none !important;
                padding: 10px !important;
                margin: 0 !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .print-modal .modal-actions {
                display: none !important;
            }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 992px) {
            .forms-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .forms-grid {
                grid-template-columns: 1fr;
            }

            .form-item-card {
                padding: 20px;
            }

            .print-modal {
                padding: 16px;
            }

            .print-modal .modal-content {
                padding: 20px;
            }

            .print-modal .modal-actions {
                flex-direction: column;
            }

            .print-modal .modal-actions .btn-primary,
            .print-modal .modal-actions .btn-close {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .form-item-card {
                padding: 16px;
            }

            .form-item-icon {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Unified Minimalist Sidebar (Req 2 & 9) -->
        <?php if (isLoggedIn() && !isStaff() && !isAdmin()): ?>
            <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Unified Resident Portal Header (Req 1, 4, 6, 10) -->
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'Printable Forms & Templates';
            $banner_subtitle = 'Download or print official pre-filled citizen assistance forms';
            $banner_icon = 'fa-print';
            $banner_actions = [
                ['label' => 'Upload New Form', 'url' => 'documents.php', 'icon' => 'fa-file-upload', 'type' => 'primary'],
                ['label' => 'My Documents', 'url' => 'documents.php', 'icon' => 'fa-folder-open', 'type' => 'secondary']
            ];
            include __DIR__ . '/includes/portal-banner.php';
            ?>

            <!-- Forms Grid -->
            <div class="forms-grid">
                <!-- Form 1: General Intake Sheet (GIS-CIU) -->
                <div class="form-item-card">
                    <i class="fas fa-file-medical-alt form-item-icon"></i>
                    <h3>General Intake Sheet <small style="font-size:0.7em; opacity:0.8;">(GIS-CIU)</small></h3>
                    <p>Opisyal na DSWD form para sa Crisis Intervention Unit (CIU). Kasama ang Client Info, Beneficiary, Family Composition, Assessment, at Recommended Services.</p>
                    <button onclick="openPrintModal('intake')" class="btn-primary">
                        <i class="fas fa-print"></i> Print Form
                    </button>
                </div>

                <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                <!-- Form 2: PWD Registration -->
                <div class="form-item-card">
                    <i class="fas fa-wheelchair form-item-icon"></i>
                    <h3>PWD ID Application Form</h3>
                    <p>Form ng pagpaparehistro para sa pagkuha ng PWD ID at mga kaukulang diskwento/benepisyo.</p>
                    <button onclick="openPrintModal('pwd')" class="btn-primary">
                        <i class="fas fa-print"></i> Print Form
                    </button>
                </div>

                <!-- Form 3: Senior Citizen Registration -->
                <div class="form-item-card">
                    <i class="fas fa-id-card form-item-icon"></i>
                    <h3>Senior Citizen ID Form</h3>
                    <p>Porma para sa aplikasyon ng Senior Citizen card at pagiging benepisyaryo ng mga programa para sa
                        nakatatanda.</p>
                    <button onclick="openPrintModal('senior')" class="btn-primary">
                        <i class="fas fa-print"></i> Print Form
                    </button>
                </div>
                <?php endif; ?>

                <!-- Form 4: Housing Application -->
                <div class="form-item-card">
                    <i class="fas fa-home form-item-icon"></i>
                    <h3>Housing Application Form</h3>
                    <p>Porma para sa pag-apply ng socialized housing programs tulad ng AKAP Village at San Lorenzo
                        Residences.</p>
                    <button onclick="openPrintModal('housing')" class="btn-primary">
                        <i class="fas fa-print"></i> Print Form
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Modal -->
    <div id="printModal" class="print-modal">
        <div class="modal-content">
            <div class="modal-actions">
                <button onclick="window.print()" class="btn-primary">
                    <i class="fas fa-print"></i> Print Now
                </button>
                <button onclick="closePrintModal()" class="btn-close">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="printContent"></div>
        </div>
    </div>

    <script>
        // ========== PRINT MODAL ==========
        const userInfo = <?php echo json_encode($user_info ?: []); ?>;
        const residentInfo = <?php echo json_encode($resident_info ?: []); ?>;
        const pwdApp = <?php echo json_encode($pwd_app ?: []); ?>;
        const seniorApp = <?php echo json_encode($senior_app ?: []); ?>;
        const pkhLogoSrc = <?php 
            $print_logo_file = __DIR__ . '/images/pkh-logo-print.png';
            if (!file_exists($print_logo_file)) $print_logo_file = __DIR__ . '/images/logo.png';
            echo json_encode('data:image/png;base64,' . base64_encode(file_get_contents($print_logo_file)));
        ?>;

        function openPrintModal(type) {
            let html = '';

            if (type === 'intake' || type === 'gis_ciu') {
                const r = residentInfo || {};
                const u = userInfo || {};
                const ln  = (r.last_name  || u.last_name  || '').toUpperCase();
                const fn  = (r.first_name || u.first_name || '').toUpperCase();
                const mn  = (r.middle_name || '').toUpperCase();
                const sfx = r.suffix || '';
                const dob = r.birth_date || '';
                let dobY = '', dobM = '', dobD = '';
                if (dob) {
                    const d = new Date(dob + 'T00:00:00');
                    dobY = d.getFullYear();
                    dobM = String(d.getMonth() + 1).padStart(2, '0');
                    dobD = String(d.getDate()).padStart(2, '0');
                }
                const gen  = (r.gender || '').toLowerCase();
                const cs   = (r.civil_status || '').toLowerCase();
                const brgy = r.barangay || '';
                const cty  = r.city || 'Palayan City';
                const prov = r.province || 'Nueva Ecija';
                const str  = r.street || r.address || '';
                const edu  = r.educational_attainment || '';
                const occ  = r.occupation || '';
                const inc  = r.monthly_income ? Number(r.monthly_income).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '';
                const cont = r.contact_number || u.contact_number || '';
                const mC   = gen === 'male'    ? '&#9745;' : '&#9744;';
                const fC   = gen === 'female'  ? '&#9745;' : '&#9744;';
                const snC  = cs  === 'single'  ? '&#9745;' : '&#9744;';
                const mrC  = cs  === 'married' ? '&#9745;' : '&#9744;';

                html = `
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;background:#fff;padding:10px;max-width:820px;margin:0 auto;">

                  <!-- HEADER -->
                  <table width="100%" style="border-collapse:collapse;border:1px solid #000;margin-bottom:-1px;">
                    <tr>
                      <td style="width:42%;border-right:1px solid #000;padding:5px 8px;vertical-align:middle;">
                        <div style="display:flex;align-items:center;gap:8px;">
                          <img src="${pkhLogoSrc}" style="width:48px;height:48px;object-fit:contain;flex-shrink:0;" alt="One Palayan Kalinga Hub Logo">
                          <div style="font-size:7.5pt;line-height:1.3;font-family:Arial,sans-serif;">
                            <span style="font-size:6pt;display:block;margin-bottom:2px;color:#555;">Rev. 04.11.14</span>
                            Republic of the Philippines<br>
                            Department of Social Welfare and Development<br>
                            <strong style="color:#7F0303;">Crisis Intervention Unit (CIU)</strong>
                          </div>
                        </div>
                      </td>
                      <td style="text-align:center;border-right:1px solid #000;padding:8px;vertical-align:middle;">
                        <div style="font-size:16pt;font-weight:bold;letter-spacing:1px;font-family:Arial,sans-serif;">General Intake Sheet</div>
                      </td>
                      <td style="width:22%;padding:5px 8px;vertical-align:top;font-size:8pt;font-family:Arial,sans-serif;">
                        &#9658; Case No.
                        <div style="display:flex;align-items:center;gap:2px;margin-top:3px;">
                          <span style="border:1px solid #000;display:inline-flex;align-items:center;justify-content:center;width:28px;height:22px;font-size:11pt;font-weight:bold;">2,</span>
                          <span style="border:1px solid #000;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;font-size:11pt;font-weight:bold;">0,</span>
                          <span style="padding:0 2px;">|</span>
                          <span style="border:1px solid #000;display:inline-block;width:16px;height:22px;"></span>
                          <span style="padding:0 2px;">|</span>
                          <span style="border:1px solid #000;display:inline-block;width:16px;height:22px;"></span>
                          <span style="padding:0 2px;">|</span>
                          <span style="border:1px solid #000;display:inline-block;width:28px;height:22px;"></span>
                        </div>
                        <div style="display:flex;font-size:7pt;color:#555;gap:2px;margin-top:1px;justify-content:space-between;width:110px;">
                          <span style="width:40px;text-align:center;">MM</span>
                          <span style="width:20px;text-align:center;">DD</span>
                          <span style="width:40px;text-align:center;">YYYY</span>
                        </div>
                      </td>
                    </tr>
                  </table>

                  <!-- SECTION I -->
                  <table width="100%" style="border-collapse:collapse;font-size:8.5pt;margin-top:-1px;">
                    <tr><td colspan="2" style="background:#b0b0b0;padding:3px 6px;border:1px solid #000;font-weight:bold;font-size:9.5pt;">I. Clients Identifying Information</td></tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;">
                        1. Client's Name*
                        <div style="display:flex;gap:4px;margin-top:3px;">
                          <div style="flex:3;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${ln}</div><div style="font-size:7pt;color:#555;margin-top:1px;">Last Name</div></div>
                          <div style="flex:2.5;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${fn}</div><div style="font-size:7pt;color:#555;margin-top:1px;">First Name</div></div>
                          <div style="flex:2;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${mn}</div><div style="font-size:7pt;color:#555;margin-top:1px;">Middle Name</div></div>
                          <div style="flex:0.8;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${sfx}</div><div style="font-size:7pt;color:#555;margin-top:1px;">Ext (Jr,Sr)</div></div>
                        </div>
                      </td>
                      <td style="border:1px solid #000;padding:4px 8px;width:130px;vertical-align:top;">2. Sex*<br>${mC} Male &nbsp;&nbsp; ${fC} Female</td>
                    </tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;width:215px;vertical-align:top;">
                        3. Date of Birth*
                        <div style="display:flex;align-items:center;gap:3px;margin-top:3px;">
                          <div style="border-bottom:1px solid #000;width:50px;text-align:center;padding:1px 0;">${dobY}</div><span>/</span>
                          <div style="border-bottom:1px solid #000;width:28px;text-align:center;padding:1px 0;">${dobM}</div><span>/</span>
                          <div style="border-bottom:1px solid #000;width:28px;text-align:center;padding:1px 0;">${dobD}</div>
                        </div>
                        <div style="font-size:7pt;color:#555;margin-top:1px;display:flex;gap:12px;"><span>YYYY</span><span>MM</span><span>DD</span></div>
                      </td>
                      <td style="border:1px solid #000;padding:4px 6px;">
                        4. Present Address*
                        <div style="display:flex;gap:4px;margin-top:3px;">
                          <div style="flex:1.2;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Region</div></div>
                          <div style="flex:1.5;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${prov}</div><div style="font-size:7pt;color:#555;margin-top:1px;">Province</div></div>
                          <div style="flex:1;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">District</div></div>
                          <div style="flex:1.8;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${cty}</div><div style="font-size:7pt;color:#555;margin-top:1px;">City/Municipality</div></div>
                          <div style="flex:1.5;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${brgy}</div><div style="font-size:7pt;color:#555;margin-top:1px;">Barangay</div></div>
                          <div style="flex:2;"><div style="border-bottom:1px solid #000;min-height:16px;padding:1px 2px;">${str}</div><div style="font-size:7pt;color:#555;margin-top:1px;">No/Street/Purok</div></div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;">5. Place of Birth<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></td>
                      <td style="border:1px solid #000;padding:4px 6px;">6. Relationship to Beneficiary<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></td>
                    </tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;vertical-align:top;">7. Civil Status*<br>${snC} Single &nbsp;&nbsp; &#9744; Other, Specify ___________<br>${mrC} Married</td>
                      <td style="border:1px solid #000;padding:4px 6px;">
                        <div style="display:flex;gap:8px;">
                          <div style="flex:2;border-right:1px solid #000;padding-right:6px;">8. Religion*<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></div>
                          <div style="flex:1.5;">9. Nationality*<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">Filipino</div></div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2" style="border:1px solid #000;padding:4px 6px;">
                        <div style="display:flex;gap:6px;">
                          <div style="flex:2;border-right:1px solid #000;padding-right:6px;">10. Highest Educational Attainment*<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;padding:1px 2px;">${edu}</div></div>
                          <div style="flex:2;border-right:1px solid #000;padding-right:6px;">11. Skills/Occupation*<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;padding:1px 2px;">${occ}</div></div>
                          <div style="flex:1.5;">12. Estimated* Monthly Income<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;padding:1px 2px;">${inc ? '&#8369; ' + inc : ''}</div></div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2" style="border:1px solid #000;padding:4px 6px;">
                        <div style="display:flex;gap:6px;">
                          <div style="flex:1.5;border-right:1px solid #000;padding-right:6px;">10. PhilHealth No.<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></div>
                          <div style="flex:1.2;border-right:1px solid #000;padding-right:6px;">13. Mode of Admission*<br>&#9744; Walk-in<br>&#9744; Referral</div>
                          <div style="flex:2;border-right:1px solid #000;padding-right:6px;">15. Referring Party<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></div>
                          <div style="flex:2;">16. Contact #/Address<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;padding:1px 2px;">${cont}</div></div>
                        </div>
                      </td>
                    </tr>
                  </table>

                  <!-- SECTION II -->
                  <table width="100%" style="border-collapse:collapse;font-size:8.5pt;margin-top:-1px;">
                    <tr><td colspan="2" style="background:#b0b0b0;padding:3px 6px;border:1px solid #000;font-weight:bold;font-size:9.5pt;">II. Beneficiary Identifying Information</td></tr>
                    <tr><td colspan="2" style="border:1px solid #000;padding:4px 8px;">&#9744; NHTS &ndash; PR &nbsp;&nbsp;&nbsp; &#9744; ISF &nbsp;&nbsp;&nbsp; &#9744; Disadvantaged Individual &nbsp;&nbsp;&nbsp; &#9744; Pantawid</td></tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;">
                        1. Beneficiary's Name*
                        <div style="display:flex;gap:4px;margin-top:3px;">
                          <div style="flex:3;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Last Name</div></div>
                          <div style="flex:2.5;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">First Name</div></div>
                          <div style="flex:2;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Middle Name</div></div>
                          <div style="flex:0.8;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Ext (Jr,Sr)</div></div>
                        </div>
                      </td>
                      <td style="border:1px solid #000;padding:4px 8px;width:130px;vertical-align:top;">2. Sex*<br>&#9744; Male &nbsp;&nbsp; &#9744; Female</td>
                    </tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;vertical-align:top;width:215px;">
                        3. Date of Birth*
                        <div style="display:flex;align-items:center;gap:3px;margin-top:3px;">
                          <div style="border-bottom:1px solid #000;width:50px;text-align:center;">&nbsp;</div><span>/</span>
                          <div style="border-bottom:1px solid #000;width:28px;text-align:center;">&nbsp;</div><span>/</span>
                          <div style="border-bottom:1px solid #000;width:28px;text-align:center;">&nbsp;</div>
                        </div>
                        <div style="font-size:7pt;color:#555;margin-top:1px;">YYYY / MM / DD</div>
                      </td>
                      <td style="border:1px solid #000;padding:4px 6px;">
                        4. Present Address*
                        <div style="display:flex;gap:4px;margin-top:3px;">
                          <div style="flex:1.2;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Region</div></div>
                          <div style="flex:1.5;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Province</div></div>
                          <div style="flex:1;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">District</div></div>
                          <div style="flex:1.8;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">City/Municipality</div></div>
                          <div style="flex:1.5;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">Barangay</div></div>
                          <div style="flex:2;"><div style="border-bottom:1px solid #000;min-height:16px;">&nbsp;</div><div style="font-size:7pt;color:#555;margin-top:1px;">No/Street/Purok</div></div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;">5. Place of Birth<div style="border-bottom:1px solid #000;min-height:16px;margin-top:3px;">&nbsp;</div></td>
                      <td style="border:1px solid #000;padding:4px 6px;">7. Civil Status &nbsp; &#9744; Single &nbsp;&nbsp; &#9744; Married &nbsp;&nbsp; &#9744; Other, Specify _______________</td>
                    </tr>
                  </table>

                  <!-- SECTION III: Family Composition -->
                  <table width="100%" style="border-collapse:collapse;font-size:8.5pt;margin-top:-1px;">
                    <tr><td colspan="10" style="background:#b0b0b0;padding:3px 6px;border:1px solid #000;font-weight:bold;font-size:9.5pt;">III. Beneficiary's Family Composition <span style="font-weight:normal;font-size:7.5pt;">(use additional sheets as necessary)</span></td></tr>
                    <tr style="font-size:7.5pt;background:#e0e0e0;text-align:center;">
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">LastName</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">FirstName</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">MiddleName</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;width:28px;">Sex</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;width:55px;">Birthdate yyyy/mm/dd</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;width:42px;">Civil Status</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">Relationship</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">Highest Educational Attainment</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;">Skills / Occupation</th>
                      <th style="border:1px solid #000;padding:2px 3px;font-weight:600;width:55px;">Est. Monthly Income</th>
                    </tr>
                    ${['1a','1b','1c','1d','1e','1f'].map(row => `<tr><td style="border:1px solid #000;padding:2px 4px;height:20px;font-size:7pt;color:#888;">${row}</td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>`).join('')}
                  </table>

                  <!-- SECTION IV: Assessment -->
                  <table width="100%" style="border-collapse:collapse;font-size:8.5pt;margin-top:-1px;">
                    <tr><td colspan="2" style="background:#b0b0b0;padding:3px 6px;border:1px solid #000;font-weight:bold;font-size:9.5pt;">IV. Assessment <span style="font-weight:normal;font-size:7.5pt;">(use additional sheets as necessary)</span></td></tr>
                    <tr>
                      <td style="border:1px solid #000;padding:4px 6px;width:58%;vertical-align:top;">
                        1. Problem/s Presented
                        <div style="min-height:95px;margin-top:5px;border-bottom:1px solid #ccc;">&nbsp;</div>
                      </td>
                      <td style="border:1px solid #000;padding:4px 8px;vertical-align:top;">
                        3. Client Category <em>(check only one)</em>
                        <div style="line-height:1.85;margin-top:5px;">
                          &#9744; Children in Need of Special Protection<br>
                          &#9744; Youth in Need of Special Protection<br>
                          &#9744; Women in Especially Difficult Circumstances<br>
                          &#9744; Person with Disability<br>
                          &#9744; Senior Citizen<br>
                          &#9744; Family Head and Other Needy Adult
                        </div>
                        <div style="margin-top:10px;">4. Beneficiary Sub-Category<div style="border-bottom:1px solid #000;min-height:16px;margin-top:4px;">&nbsp;</div></div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="2" style="border:1px solid #000;padding:4px 6px;">
                        2. Social Worker's Assessment
                        <div style="min-height:55px;margin-top:5px;">&nbsp;</div>
                      </td>
                    </tr>
                  </table>

                  <!-- SECTION V: Recommended Services -->
                  <table width="100%" style="border-collapse:collapse;font-size:8.5pt;margin-top:-1px;">
                    <tr>
                      <td colspan="3" style="background:#b0b0b0;padding:3px 6px;border:1px solid #000;font-weight:bold;font-size:9.5pt;">V. Recommended Services and Assistance</td>
                    </tr>
                    <tr>
                      <td colspan="3" style="border:1px solid #000;padding:4px 8px;">
                        1. Nature of <u>Service</u> / <u>Assistance</u><br>
                        <div style="margin-top:4px; display:flex; gap:15px; align-items:center;">
                          <span>&#9744; Counseling</span>
                          <span>&#9744; Legal Assistance <em>(Retainer Lawyer/Others)</em></span>
                          <span>&#9744; Referral <em>(Specify)</em> _______________________</span>
                          <span>&#9744; Others specify: _______________________</span>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Financial Assistance Grid -->
                    <tr>
                      <!-- Left: Financial Assistance Sub-table -->
                      <td style="border:1px solid #000; width:55%; padding:0; vertical-align:top;">
                        <table width="100%" style="border-collapse:collapse; height:100%; font-size:8.5pt;">
                          <tr>
                            <td colspan="3" style="padding:4px 6px; font-weight:bold; border-bottom:1px solid #000;">
                              &#9744; <u>Financial Assistance</u>
                            </td>
                          </tr>
                          <tr>
                            <!-- Checkbox List -->
                            <td style="width:40%; padding:6px; line-height:1.7; vertical-align:top;">
                              &#9744; Medical<br>
                              &#9744; Burial<br>
                              &#9744; Transportation<br>
                              &#9744; Educational<br>
                              &#9744; Food Subsidy/Allowance<br>
                              &#9744; Others
                            </td>
                            <!-- Specify Box -->
                            <td style="width:38%; border-left:1px solid #000; border-right:1px solid #000; padding:6px; position:relative; vertical-align:bottom;">
                              <div style="font-size:7.5pt; border-top:1px solid #000; padding-top:4px; margin-top:60px;">
                                specify : __________________
                              </div>
                            </td>
                            <!-- Value (Pesos) -->
                            <td style="width:22%; padding:6px 4px; text-align:center; vertical-align:top;">
                              <div style="text-decoration:underline; font-weight:bold; font-size:8pt; margin-bottom:5px;">Value (Pesos)</div>
                              <div style="height:80px;"></div>
                            </td>
                          </tr>
                          <tr>
                            <td colspan="2" style="border-top:1px solid #000; padding:4px 6px; text-align:right; font-weight:bold;">
                              Sub-total
                            </td>
                            <td style="border-top:1px solid #000; border-left:1px solid #000; padding:4px;">
                              &nbsp;
                            </td>
                          </tr>
                        </table>
                      </td>
                      
                      <!-- Right: Financial Details -->
                      <td colspan="2" style="border:1px solid #000; padding:6px 8px; vertical-align:top;">
                        <div style="font-size:8pt; font-weight:500;">Amount of Financial Assistance to be Extended</div>
                        <div style="border:1px solid #000; padding:4px 8px; margin:4px 0; font-size:10pt; font-weight:bold; width:100%; box-sizing:border-box;">
                          P <span style="display:inline-block; width:80%; border-bottom:1px dotted #000; margin-left:8px;">&nbsp;</span>
                        </div>
                        
                        <div style="margin-top:6px;">
                          <span style="font-weight:bold; font-size:8pt;">Mode of Financial Assistance</span>
                          <div style="margin-top:2px; display:grid; grid-template-columns: 1fr 1fr; gap:2px; font-size:8pt; line-height:1.4;">
                            <div>&#9744; Cash</div>
                            <div>&#9744; Check</div>
                            <div>&#9744; Guarantee Letter</div>
                            <div>&#9744; Tickets &nbsp;&nbsp; &#9744; Bus &nbsp;&nbsp; &#9744; Boat</div>
                          </div>
                        </div>
                        
                        <div style="margin-top:8px;">
                          <span style="font-weight:bold; font-size:8pt;">Source of Assistance</span>
                          <div style="margin-top:2px; font-size:8pt; line-height:1.4;">
                            &#9744; Regular Funds<br>
                            &#9744; Donation<br>
                            &#9744; Expanded AICS<br>
                            &#9744; Others ________________________________
                          </div>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Material Assistance Grid -->
                    <tr>
                      <!-- Left: Material Assistance Sub-table -->
                      <td style="border:1px solid #000; width:55%; padding:0; vertical-align:top;">
                        <table width="100%" style="border-collapse:collapse; font-size:8.5pt;">
                          <tr>
                            <td colspan="3" style="padding:4px 6px; font-weight:bold; border-bottom:1px solid #000;">
                              &#9744; <u>Material Assistance</u>
                            </td>
                          </tr>
                          <tr>
                            <!-- Checkbox List -->
                            <td style="width:40%; padding:6px; line-height:1.7; vertical-align:top;">
                              &#9744; Food Pack<br>
                              &#9744; Used Clothing<br>
                              &#9744; Hot Meal<br>
                              &#9744; Assistive Device
                            </td>
                            <!-- Specify Box -->
                            <td style="width:38%; border-left:1px solid #000; border-right:1px solid #000; padding:6px; position:relative; vertical-align:bottom;">
                              <div style="font-size:7.5pt; margin-top:35px;">
                                specify : __________________
                              </div>
                            </td>
                            <!-- Value Column -->
                            <td style="width:22%; padding:6px 4px; text-align:center; vertical-align:top;">
                              &nbsp;
                            </td>
                          </tr>
                          <tr>
                            <td colspan="2" style="border-top:1px solid #000; padding:4px 6px; text-align:right; font-weight:bold;">
                              Sub-total
                            </td>
                            <td style="border-top:1px solid #000; border-left:1px solid #000; padding:4px;">
                              &nbsp;
                            </td>
                          </tr>
                          <tr>
                            <td colspan="3" style="border-top:1px solid #000; padding:6px; vertical-align:top;">
                              <span style="font-weight:bold; font-size:8pt;">Source of Assistance</span>
                              <div style="margin-top:2px; display:grid; grid-template-columns: 1fr 1fr; gap:2px; font-size:8pt;">
                                <div>&#9744; Regular Funds</div>
                                <div>&#9744; Donation</div>
                                <div>&#9744; Expanded AICS</div>
                                <div>&#9744; Others ________________</div>
                              </div>
                              
                              <div style="margin-top:8px; display:flex; align-items:center; gap:6px;">
                                <strong style="font-size:9.5pt;">Total</strong>
                                <div style="border:1px solid #000; padding:4px 8px; font-size:10pt; font-weight:bold; flex:1;">
                                  P <span style="display:inline-block; width:80%; border-bottom:1px dotted #000; margin-left:8px;">&nbsp;</span>
                                </div>
                              </div>
                            </td>
                          </tr>
                        </table>
                      </td>
                      
                      <!-- Right: Signatures & Social Worker Sign-offs -->
                      <td colspan="2" style="border:1px solid #000; padding:0; vertical-align:top;">
                        <table width="100%" style="border-collapse:collapse; font-size:8.5pt;">
                          <tr>
                            <!-- Client Signature -->
                            <td style="width:60%; height:80px; border-bottom:1px solid #000; padding:4px; vertical-align:bottom; text-align:center;">
                              <div style="border-top:1px solid #000; width:90%; margin:0 auto 4px;">&nbsp;</div>
                              <div style="font-size:7.5pt; font-weight:bold;">Client's Signature</div>
                            </td>
                            <!-- Thumb Mark -->
                            <td style="width:40%; height:80px; border-left:1px solid #000; border-bottom:1px solid #000; padding:4px; vertical-align:middle; text-align:center;">
                              <div style="border:1px solid #000; width:65px; height:50px; margin:0 auto 3px;"></div>
                              <div style="font-size:7pt; font-weight:bold;">Thumb Mark</div>
                            </td>
                          </tr>
                          <tr>
                            <td colspan="2" style="padding:6px; border-bottom:1px solid #000;">
                              <div style="font-size:8pt;">Interviewed by:</div>
                              <div style="border-bottom:1px solid #000; width:90%; margin:15px auto 2px; height:1px;">&nbsp;</div>
                              <div style="font-size:7.5pt; text-align:center; font-weight:500; color:#333;">Name/Signature of Social Worker</div>
                            </td>
                          </tr>
                          <tr>
                            <td colspan="2" style="padding:6px;">
                              <div style="font-size:8pt;">Reviewed and Approved by:</div>
                              <div style="border-bottom:1px solid #000; width:90%; margin:15px auto 2px; height:1px;">&nbsp;</div>
                              <div style="font-size:7.5pt; text-align:center; font-weight:500; color:#333;">Name/Signature of Unit Head</div>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    
                    <!-- Payee Info -->
                    <tr>
                      <td colspan="3" style="border:1px solid #000; padding:4px 8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                          <span style="font-weight:bold;">2. Name of Payee:</span>
                          <span style="border-bottom:1px solid #000; display:inline-block; flex:1; min-height:16px;">&nbsp;</span>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="3" style="border:1px solid #000; padding:4px 8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                          <span style="font-weight:bold;">3. Address of Payee:</span>
                          <span style="border-bottom:1px solid #000; display:inline-block; flex:1; min-height:16px;">&nbsp;</span>
                        </div>
                      </td>
                    </tr>
                  </table>
                </div>
                `;
            } else if (type === 'pwd') {
                const r = residentInfo || {};
                const u = userInfo || {};
                const pa = pwdApp || {};

                const ln  = (r.last_name  || u.last_name  || '').toUpperCase();
                const fn  = (r.first_name || u.first_name || '').toUpperCase();
                const mn  = (r.middle_name || '').toUpperCase();
                const sfx = (r.suffix || '').toUpperCase();

                const dob = r.birth_date || '';
                let dobFormatted = '';
                if (dob && dob !== '0000-00-00') {
                    const d = new Date(dob + 'T00:00:00');
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    const yyyy = d.getFullYear();
                    dobFormatted = `${mm}/${dd}/${yyyy}`;
                }

                const gen  = (r.gender || '').toLowerCase();
                const cs   = (r.civil_status || '').toLowerCase();
                const brgy = (r.barangay || '').toUpperCase();
                const str  = (r.street || r.address || '').toUpperCase();
                const cont = r.contact_number || u.contact_number || '';
                const email = (r.email || u.email || '').toLowerCase();
                const occ  = (r.occupation || '').toUpperCase();
                const edu  = (r.educational_attainment || '').toLowerCase();

                const isRenewal = (r.pwd == 1 && pa.pwd_id_number);
                const pwdNumber = pa.pwd_id_number || (r.resident_number ? ('03-4919-' + r.resident_number.replace('RES-', '')) : '___________________________________');
                const dateApplied = pa.created_at ? new Date(pa.created_at).toLocaleDateString() : new Date().toLocaleDateString();

                html = `
                <div style="font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; max-width: 820px; margin: 0 auto; padding: 10px; font-size: 8pt; line-height: 1.25;">

                    <!-- LOGO HEADER (DOH Seal on Left, City Government of Palayan Seal on Right) -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 0 10px;">
                        <div style="text-align: left;">
                            <img src="images/doh-logo.png" style="width: 78px; height: 78px; object-fit: contain;" alt="Department of Health Seal">
                        </div>
                        <div style="text-align: center; flex: 1; padding: 0 15px;">
                            <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #333; letter-spacing: 0.5px;">Republic of the Philippines</div>
                            <div style="font-size: 10.5pt; font-weight: 800; text-transform: uppercase; margin: 2px 0; color: #000;">Department of Health</div>
                            <div style="font-size: 8.5pt; font-weight: 700; text-transform: uppercase; color: #004D40;">Philippine Registry for Persons with Disability</div>
                            <div style="font-size: 7.5pt; font-weight: 600; color: #555;">City Social Welfare &amp; Development Office &bull; Palayan City</div>
                        </div>
                        <div style="text-align: right;">
                            <img src="${pkhLogoSrc}" style="width: 78px; height: 78px; object-fit: contain;" alt="One Palayan Kalinga Hub Logo">
                        </div>
                    </div>

                    <!-- MAIN BORDERED FORM CONTAINER -->
                    <div style="border: 2px solid #000;">

                        <!-- SECTION 0: APPLICANT TYPE, PWD NUMBER, NAME & 1X1 PHOTO -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #000;">
                            <tr>
                                <td style="width: 78%; padding: 0; vertical-align: top; border-right: 2px solid #000;">
                                    <!-- Row A: Applicant Type & Date -->
                                    <div style="display: flex; align-items: center; padding: 5px 8px; border-bottom: 1px solid #000; font-size: 8pt; font-weight: bold;">
                                        <div style="margin-right: 20px; display: inline-flex; align-items: center; gap: 4px;">
                                            <span style="display: inline-block; width: 11px; height: 11px; border: 1.2px solid #000; text-align: center; line-height: 10px; font-size: 8pt;">${isRenewal ? '' : '✓'}</span> NEW APPLICANT
                                        </div>
                                        <div style="margin-right: 25px; display: inline-flex; align-items: center; gap: 4px;">
                                            <span style="display: inline-block; width: 11px; height: 11px; border: 1.2px solid #000; text-align: center; line-height: 10px; font-size: 8pt;">${isRenewal ? '✓' : ''}</span> RENEWAL
                                        </div>
                                        <div style="flex: 1; text-align: right;">
                                            DATE APPLIED: <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 130px; text-align: center; font-weight: bold;">${dateApplied}</span>
                                        </div>
                                    </div>
                                    <!-- Row B: PWD Number -->
                                    <div style="padding: 5px 8px; border-bottom: 1px solid #000; font-size: 8pt; font-weight: bold;">
                                        1. PWD NUMBER (RR-PPMM-BBB-NNNNNNN): <span style="font-family: monospace; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.5px;">${pwdNumber}</span>
                                    </div>
                                    <!-- Row C: Full Name -->
                                    <div style="padding: 5px 8px; font-size: 7.5pt; font-weight: bold;">
                                        <div style="display: flex; gap: 8px;">
                                            <div style="flex: 1.2;">
                                                2. LAST NAME:<br>
                                                <span style="display: block; border-bottom: 1px solid #000; min-height: 16px; font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${ln}</span>
                                            </div>
                                            <div style="flex: 1.2;">
                                                FIRST NAME:<br>
                                                <span style="display: block; border-bottom: 1px solid #000; min-height: 16px; font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${fn}</span>
                                            </div>
                                            <div style="flex: 1.2;">
                                                MIDDLE NAME:<br>
                                                <span style="display: block; border-bottom: 1px solid #000; min-height: 16px; font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${mn}</span>
                                            </div>
                                            <div style="width: 55px;">
                                                SUFFIX:<br>
                                                <span style="display: block; border-bottom: 1px solid #000; min-height: 16px; font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${sfx}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- 1x1 Photo placeholder -->
                                <td style="width: 22%; text-align: center; vertical-align: middle; padding: 6px;">
                                    <div style="width: 95px; height: 95px; border: 1.5px dashed #444; margin: 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: bold; font-size: 7.5pt; color: #444; line-height: 1.3;">
                                        PLACE 1X1<br>PHOTO HERE
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 3 & 4: TYPE & CAUSE OF DISABILITY -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000;">
                            <tr>
                                <td style="width: 54%; border-right: 1.5px solid #000; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 8pt; margin-bottom: 4px;">3. TYPE OF DISABILITY: <em>(check all that apply)</em></div>
                                    <div style="display: flex; gap: 8px; font-size: 7.5pt; line-height: 1.65;">
                                        <div style="flex: 1;">
                                            <div>&#9744; DEAF/HARD OF HEARING</div>
                                            <div>&#9744; INTELLECTUAL DISABILITY</div>
                                            <div>&#9744; LEARNING DISABILITY</div>
                                            <div>&#9744; MENTAL DISABILITY</div>
                                            <div>&#9744; PHYSICAL DISABILITY</div>
                                        </div>
                                        <div style="flex: 1;">
                                            <div>&#9744; PSYCHOSOCIAL DISABILITY</div>
                                            <div>&#9744; SPEECH/LANGUAGE IMPAIRMENT</div>
                                            <div>&#9744; VISUAL DISABILITY</div>
                                            <div>&#9744; CANCER (RA-11215)</div>
                                            <div>&#9744; RARE DISEASE (RA-10747)</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="width: 46%; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 8pt; margin-bottom: 4px;">4. CAUSE OF DISABILITY:</div>
                                    <div style="display: flex; gap: 8px; font-size: 7.5pt; line-height: 1.65;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: bold;">&#9744; CONGENITAL/INBORN</div>
                                            <div style="padding-left: 12px;">&#9744; AUTISM</div>
                                            <div style="padding-left: 12px;">&#9744; ADHD</div>
                                            <div style="padding-left: 12px;">&#9744; DOWN SYNDROME</div>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: bold;">&#9744; ACQUIRED</div>
                                            <div style="padding-left: 12px;">&#9744; CHRONIC ILLNESS</div>
                                            <div style="padding-left: 12px;">&#9744; CEREBRAL PALSY</div>
                                            <div style="padding-left: 12px;">&#9744; INJURY</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 5: ADDRESS -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000; font-size: 7.5pt;">
                            <tr>
                                <td style="width: 65%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">5. ADDRESS: HOUSE NO. AND STREET NAME:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding-top: 2px; text-transform: uppercase;">${str || '&nbsp;'}</div>
                                </td>
                                <td style="width: 35%; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">BARANGAY:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding-top: 2px; text-transform: uppercase;">${brgy || '&nbsp;'}</div>
                                </td>
                            </tr>
                            <tr style="border-top: 1px solid #000;">
                                <td colspan="2" style="padding: 4px 8px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 7.5pt;">
                                        <div><strong>CITY/MUNICIPALITY:</strong> <span style="font-weight: 600; font-size: 8pt; text-transform: uppercase;">PALAYAN CITY</span></div>
                                        <div><strong>PROVINCE:</strong> <span style="font-weight: 600; font-size: 8pt; text-transform: uppercase;">NUEVA ECIJA</span></div>
                                        <div><strong>REGION:</strong> <span style="font-weight: 600; font-size: 8pt; text-transform: uppercase;">REGION III</span></div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 6: CONTACT DETAILS -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000; font-size: 7.5pt;">
                            <tr>
                                <td style="width: 30%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">6. CONTACT DETAILS:</div>
                                    <div>LANDLINE: <span style="border-bottom: 1px solid #000; display: inline-block; width: 105px;">&nbsp;</span></div>
                                </td>
                                <td style="width: 35%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">MOBILE NUMBER:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${cont || '&nbsp;'}</div>
                                </td>
                                <td style="width: 35%; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">EMAIL ADDRESS:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding-top: 2px;">${email || '&nbsp;'}</div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 7, 8, 9: DATE OF BIRTH, SEX, CIVIL STATUS -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000; font-size: 7.5pt;">
                            <tr>
                                <td style="width: 28%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold;">7. DATE OF BIRTH (mm/dd/yyyy):</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding-top: 3px;">${dobFormatted || '&nbsp;'}</div>
                                </td>
                                <td style="width: 24%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; margin-bottom: 2px;">8. SEX:</div>
                                    <div style="display: flex; gap: 12px; font-size: 7.5pt; padding-top: 1px;">
                                        <div><span style="font-size: 8.5pt;">${gen === 'male' ? '&#9745;' : '&#9744;'}</span> MALE</div>
                                        <div><span style="font-size: 8.5pt;">${gen === 'female' ? '&#9745;' : '&#9744;'}</span> FEMALE</div>
                                    </div>
                                </td>
                                <td style="width: 48%; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; margin-bottom: 2px;">9. CIVIL STATUS:</div>
                                    <div style="font-size: 7.5pt; line-height: 1.5;">
                                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                            <div><span style="font-size: 8.5pt;">${cs === 'single' ? '&#9745;' : '&#9744;'}</span> SINGLE</div>
                                            <div><span style="font-size: 8.5pt;">${cs === 'separated' ? '&#9745;' : '&#9744;'}</span> SEPARATED</div>
                                            <div><span style="font-size: 8.5pt;">${cs === 'cohabitation' || cs === 'live-in' ? '&#9745;' : '&#9744;'}</span> COHABITATION (LIVE-IN)</div>
                                        </div>
                                        <div style="display: flex; gap: 12px; margin-top: 2px;">
                                            <div><span style="font-size: 8.5pt;">${cs === 'married' ? '&#9745;' : '&#9744;'}</span> MARRIED</div>
                                            <div><span style="font-size: 8.5pt;">${cs === 'widow' || cs === 'widowed' || cs === 'widower' ? '&#9745;' : '&#9744;'}</span> WIDOW/ER</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 10, 11, 12: EDUCATION, EMPLOYMENT, OCCUPATION -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000; font-size: 7pt;">
                            <tr>
                                <!-- 10. Educational Attainment -->
                                <td style="width: 32%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">10. EDUCATIONAL ATTAINMENT:</div>
                                    <div style="line-height: 1.6;">
                                        <div><span style="font-size: 8pt;">${edu === 'none' ? '&#9745;' : '&#9744;'}</span> NONE</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('kindergarten') ? '&#9745;' : '&#9744;'}</span> KINDERGARTEN</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('elementary') ? '&#9745;' : '&#9744;'}</span> ELEMENTARY</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('junior') ? '&#9745;' : '&#9744;'}</span> JUNIOR HIGH SCHOOL</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('senior') ? '&#9745;' : '&#9744;'}</span> SENIOR HIGH SCHOOL</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('college') ? '&#9745;' : '&#9744;'}</span> COLLEGE</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('vocational') ? '&#9745;' : '&#9744;'}</span> VOCATIONAL</div>
                                        <div><span style="font-size: 8pt;">${edu.includes('post') ? '&#9745;' : '&#9744;'}</span> POST GRADUATE</div>
                                    </div>
                                </td>
                                <!-- 11. Employment Status -->
                                <td style="width: 34%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">11. EMPLOYMENT STATUS:</div>
                                    <div style="line-height: 1.55;">
                                        <div>&#9744; EMPLOYED</div>
                                        <div>&#9744; UNEMPLOYED</div>
                                        <div>&#9744; SELF EMPLOYED</div>
                                    </div>
                                    <div style="font-weight: bold; font-size: 7.5pt; margin: 4px 0 2px;">11.1. CATEGORY OF EMPLOYMENT</div>
                                    <div style="line-height: 1.55;">
                                        <div>&#9744; GOVERNMENT</div>
                                        <div>&#9744; PRIVATE</div>
                                    </div>
                                    <div style="font-weight: bold; font-size: 7.5pt; margin: 4px 0 2px;">11.2. NATURE OF EMPLOYMENT</div>
                                    <div style="line-height: 1.55;">
                                        <div>&#9744; PERMANENT/REGULAR</div>
                                        <div>&#9744; CASUAL</div>
                                        <div>&#9744; SEASONAL</div>
                                        <div>&#9744; EMERGENCY</div>
                                    </div>
                                </td>
                                <!-- 12. Occupation -->
                                <td style="width: 34%; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">12. OCCUPATION:</div>
                                    <div style="line-height: 1.5;">
                                        <div>&#9744; MANAGER</div>
                                        <div>&#9744; PROFESSIONAL</div>
                                        <div>&#9744; TECHNICIAN AND ASSOCIATE PROFESSIONALS</div>
                                        <div>&#9744; CLERICAL SUPPORT WORKERS</div>
                                        <div>&#9744; SERVICE AND SALES WORKERS</div>
                                        <div>&#9744; SKILLED AGRICULTURAL, FORESTRY AND FISHERY WORKERS</div>
                                        <div>&#9744; CRAFT &amp; RELATED TRADE WORKERS</div>
                                        <div>&#9744; PLANT &amp; MACHINE OPERATORS AND ASSEMBLERS</div>
                                        <div>&#9744; ELEMENTARY OCCUPATIONS</div>
                                        <div>&#9744; ARMED FORCES OCCUPATIONS</div>
                                        <div style="margin-top: 2px;">
                                            &#9744; OTHER, SPECIFY: <span style="border-bottom: 1px solid #000; display: inline-block; width: 90px; text-transform: uppercase;">${occ || '&nbsp;'}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 13, 14, 15: BLOOD TYPE, ORGANIZATION, ID REFERENCE -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 7pt;">
                            <tr>
                                <!-- 13. Blood Type -->
                                <td style="width: 24%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">13. BLOOD TYPE:</div>
                                    <div style="display: flex; gap: 14px; line-height: 1.6;">
                                        <div>
                                            <div>&#9744; A+</div>
                                            <div>&#9744; A-</div>
                                            <div>&#9744; B+</div>
                                            <div>&#9744; B-</div>
                                        </div>
                                        <div>
                                            <div>&#9744; AB+</div>
                                            <div>&#9744; AB-</div>
                                            <div>&#9744; O+</div>
                                            <div>&#9744; O-</div>
                                        </div>
                                    </div>
                                </td>
                                <!-- 14. Organization Affiliated -->
                                <td style="width: 38%; border-right: 1px solid #000; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">14. ORGANIZATION AFFILIATED:</div>
                                    <div style="line-height: 1.65;">
                                        <div>Organization Affiliated: <span style="border-bottom: 1px solid #000; display: inline-block; width: 130px;">&nbsp;</span></div>
                                        <div>Contact Person: <span style="border-bottom: 1px solid #000; display: inline-block; width: 165px;">&nbsp;</span></div>
                                        <div>Office Address: <span style="border-bottom: 1px solid #000; display: inline-block; width: 170px;">&nbsp;</span></div>
                                        <div>Tel No.: <span style="border-bottom: 1px solid #000; display: inline-block; width: 195px;">&nbsp;</span></div>
                                    </div>
                                </td>
                                <!-- 15. ID Reference No. -->
                                <td style="width: 38%; padding: 4px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 3px;">15. ID REFERENCE NO.</div>
                                    <div style="line-height: 1.6;">
                                        <div>&#9744; SSS NO. <span style="border-bottom: 1px solid #000; display: inline-block; width: 155px;">&nbsp;</span></div>
                                        <div>&#9744; GSIS NO. <span style="border-bottom: 1px solid #000; display: inline-block; width: 150px;">&nbsp;</span></div>
                                        <div>&#9744; PSN NO. <span style="border-bottom: 1px solid #000; display: inline-block; width: 155px;">&nbsp;</span></div>
                                        <div>&#9744; PHILHEALTH NO. <span style="border-bottom: 1px solid #000; display: inline-block; width: 110px;">&nbsp;</span></div>
                                        <div style="padding-left: 16px;">
                                            <div>&#9744; Philhealth Member</div>
                                            <div>&#9744; Philhealth Member-Dependent</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </div>
                </div>
                `;
            } else if (type === 'senior') {
                const r = residentInfo || {};
                const u = userInfo || {};
                const sa = seniorApp || {};

                const fn  = (r.first_name || u.first_name || '').toUpperCase();
                const mn  = (r.middle_name || '').toUpperCase();
                const ln  = (r.last_name  || u.last_name  || '').toUpperCase();
                const sfx = (r.suffix || '').toUpperCase();
                const fullName = [fn, mn, ln, sfx].filter(Boolean).join(' ');

                const brgy = (r.barangay || '').toUpperCase();
                const str  = (r.street || r.address || '').toUpperCase();
                const fullAddress = [str, brgy, 'PALAYAN CITY', 'NUEVA ECIJA'].filter(Boolean).join(', ');
                const cont = r.contact_number || u.contact_number || '';

                const dob = r.birth_date || '';
                let dobFormatted = '';
                let age = '';
                if (dob && dob !== '0000-00-00') {
                    const d = new Date(dob + 'T00:00:00');
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    const yyyy = d.getFullYear();
                    dobFormatted = `${mm}/${dd}/${yyyy}`;
                    
                    const today = new Date();
                    let ageVal = today.getFullYear() - d.getFullYear();
                    const mDiff = today.getMonth() - d.getMonth();
                    if (mDiff < 0 || (mDiff === 0 && today.getDate() < d.getDate())) {
                        ageVal--;
                    }
                    age = ageVal >= 0 ? ageVal : '';
                }

                const gen = (r.gender || '').toUpperCase();
                const cs  = (r.civil_status || '').toUpperCase();
                const currentDate = new Date().toLocaleDateString();

                html = `
                <div style="font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; max-width: 820px; margin: 0 auto; padding: 12px 16px; font-size: 8.5pt; line-height: 1.3;">

                    <!-- LOGO & HEADER -->
                    <div style="position: relative; text-align: center; margin-bottom: 12px; min-height: 80px;">
                        <img src="${pkhLogoSrc}" style="position: absolute; left: 10px; top: 0; width: 75px; height: 75px; object-fit: contain;" alt="One Palayan Kalinga Hub Logo">
                        
                        <div style="padding: 0 90px;">
                            <div style="font-size: 8.5pt; color: #333;">Republic of the Philippines</div>
                            <div style="font-size: 10pt; font-weight: bold; margin: 1px 0;">City of Palayan</div>
                            <div style="font-size: 8.5pt; font-weight: bold; color: #000; margin-top: 6px;">OFFICE OF THE SENIOR CITIZENS AFFAIRS (OSCA)</div>
                            <div style="font-size: 7.5pt; color: #0288D1; text-decoration: underline; margin-top: 2px;">palayan-kalinga-hub.cityhall.gov.ph</div>
                        </div>
                    </div>

                    <!-- TITLE & BARANGAY / DATE BAR -->
                    <h2 style="text-align: center; font-size: 12.5pt; font-weight: 800; letter-spacing: 0.5px; margin: 12px 0 10px; text-transform: uppercase;">
                        SENIOR CITIZENS APPLICATION FORM
                    </h2>

                    <div style="display: flex; justify-content: space-between; align-items: flex-end; font-size: 8.5pt; font-weight: bold; margin-bottom: 6px; padding: 0 2px;">
                        <div>
                            BARANGAY: <span style="text-decoration: underline; text-transform: uppercase; font-weight: 800;">${brgy ? (brgy + ', PALAYAN CITY') : 'PALAYAN CITY'}</span>
                        </div>
                        <div>
                            Date: <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 140px; text-align: center; font-weight: bold;">${currentDate}</span>
                        </div>
                    </div>

                    <div style="font-size: 8pt; font-weight: bold; margin-bottom: 6px;">
                        Note: PLEASE PRINT
                    </div>

                    <!-- MAIN BORDERED APPLICATION TABLE -->
                    <div style="border: 1.5px solid #000;">

                        <!-- ROW 1: GIVEN NAME -->
                        <div style="border-bottom: 1px solid #000; padding: 5px 8px;">
                            <div style="font-size: 7.5pt; font-weight: bold;">GIVEN NAME:</div>
                            <div style="font-size: 9pt; font-weight: 600; text-transform: uppercase; padding: 2px 0;">${fn || '&nbsp;'}</div>
                        </div>

                        <!-- ROW 2: MIDDLE NAME -->
                        <div style="border-bottom: 1px solid #000; padding: 5px 8px;">
                            <div style="font-size: 7.5pt; font-weight: bold;">MIDDLE NAME:</div>
                            <div style="font-size: 9pt; font-weight: 600; text-transform: uppercase; padding: 2px 0;">${mn || '&nbsp;'}</div>
                        </div>

                        <!-- ROW 3: SURNAME -->
                        <div style="border-bottom: 1px solid #000; padding: 5px 8px;">
                            <div style="font-size: 7.5pt; font-weight: bold;">SURNAME:</div>
                            <div style="font-size: 9pt; font-weight: 600; text-transform: uppercase; padding: 2px 0;">${ln}${sfx ? (' ' + sfx) : ''}</div>
                        </div>

                        <!-- ROW 4: ADDRESS & CONTACT NUMBER (2 COLUMNS) -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #000;">
                            <tr>
                                <td style="width: 65%; border-right: 1px solid #000; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-size: 7.5pt; font-weight: bold;">COMPLETE ADDRESS (Number, Street or Purok, Barangay)</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; text-transform: uppercase; padding: 2px 0;">${fullAddress || '&nbsp;'}</div>
                                </td>
                                <td style="width: 35%; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-size: 7.5pt; font-weight: bold;">CONTACT NUMBER:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding: 2px 0;">${cont || '&nbsp;'}</div>
                                </td>
                            </tr>
                        </table>

                        <!-- ROW 5: DATE OF BIRTH, AGE, PLACE OF BIRTH (3 COLUMNS) -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #000;">
                            <tr>
                                <td style="width: 35%; border-right: 1px solid #000; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-size: 7.5pt; font-weight: bold;">DATE OF BIRTH:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding: 2px 0;">${dobFormatted || '&nbsp;'}</div>
                                </td>
                                <td style="width: 20%; border-right: 1px solid #000; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-size: 7.5pt; font-weight: bold;">AGE:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; padding: 2px 0;">${age ? (age + ' yrs') : '&nbsp;'}</div>
                                </td>
                                <td style="width: 45%; padding: 5px 8px; vertical-align: top;">
                                    <div style="font-size: 7.5pt; font-weight: bold;">PLACE OF BIRTH:</div>
                                    <div style="font-size: 8.5pt; font-weight: 600; text-transform: uppercase; padding: 2px 0;">PALAYAN CITY, NUEVA ECIJA</div>
                                </td>
                            </tr>
                        </table>

                        <!-- ROW 6: RELIGION, BLOOD TYPE, CIVIL STATUS, GENDER, NO. OF YRS IN PALAYAN, CITIZENSHIP (6 COLUMNS) -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000; font-size: 7.5pt;">
                            <tr>
                                <td style="width: 16%; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">RELIGION:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0; text-transform: uppercase;">ROMAN CATHOLIC</div>
                                </td>
                                <td style="width: 15%; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">BLOOD TYPE:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0;">&nbsp;</div>
                                </td>
                                <td style="width: 17%; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">CIVIL STATUS:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0; text-transform: uppercase;">${cs || '&nbsp;'}</div>
                                </td>
                                <td style="width: 14%; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">GENDER:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0; text-transform: uppercase;">${gen || '&nbsp;'}</div>
                                </td>
                                <td style="width: 20%; border-right: 1px solid #000; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">NO. OF YRS IN PALAYAN:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0;">SINCE BIRTH</div>
                                </td>
                                <td style="width: 18%; padding: 5px 6px; vertical-align: top;">
                                    <div style="font-weight: bold; font-size: 7pt;">CITIZENSHIP:</div>
                                    <div style="font-size: 8pt; font-weight: 600; padding: 2px 0; text-transform: uppercase;">FILIPINO</div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 2: REQUIREMENTS -->
                        <div style="padding: 6px 10px; border-bottom: 1.5px solid #000; font-size: 7.5pt; line-height: 1.55;">
                            <div style="font-weight: bold; font-size: 8pt; margin-bottom: 3px;">REQUIREMENTS:</div>
                            <ul style="margin: 0 0 4px 18px; padding: 0; list-style-type: disc;">
                                <li>2 PCS 1X1 PICTURE</li>
                                <li>XEROX COPY BIRTH CERTIFICATE/BAPTISMAL</li>
                                <li>COMELEC CERTIFICATION</li>
                                <li>BARANGAY CLEARANCE</li>
                                <li>CEDULA (LATEST) XEROX COPY</li>
                            </ul>
                            <div style="padding-left: 18px; font-size: 7.5pt;">
                                Other supporting Documents: Any Valid ID (SSS, PRC, GSIS, TIN, PASSPORT, POSTAL ID)
                            </div>
                        </div>

                        <!-- SECTION 3: THUMBMARK & SIGNATURE -->
                        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000;">
                            <tr>
                                <td style="width: 25%; border-right: 1.5px solid #000; padding: 6px 8px; vertical-align: top; text-align: center;">
                                    <div style="font-weight: bold; font-size: 7.5pt; margin-bottom: 6px; text-align: left;">THUMBMARK</div>
                                    <div style="width: 80px; height: 75px; border: 1px dashed #666; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 7pt; color: #888;">
                                        Right Thumb
                                    </div>
                                </td>
                                <td style="width: 75%; padding: 10px 15px; vertical-align: bottom; text-align: center;">
                                    <div style="border-bottom: 1px solid #000; width: 85%; margin: 0 auto 4px; min-height: 38px; display: flex; align-items: flex-end; justify-content: center; font-weight: bold; font-size: 9.5pt; text-transform: uppercase;">
                                        ${fullName}
                                    </div>
                                    <div style="font-weight: bold; font-size: 7.5pt; text-transform: uppercase;">
                                        SIGNATURE OVER PRINTED NAME OF APPLICANT
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <!-- SECTION 4: CERTIFIED BY -->
                        <div style="padding: 8px 12px; border-bottom: 2px solid #000; font-size: 8pt;">
                            <div style="font-weight: bold; margin-bottom: 15px;">CERTIFIED BY:</div>
                            <div style="text-align: center; width: 60%; margin: 0 auto;">
                                <div style="border-bottom: 1px solid #000; width: 100%; min-height: 25px;">&nbsp;</div>
                                <div style="font-weight: bold; font-size: 7.5pt; margin-top: 4px; text-transform: uppercase;">
                                    PRESIDENT – SENIOR CITIZEN ASSOCIATION
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 5: OFFICE USE ONLY (4 COLUMNS) -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 7.5pt;">
                            <tr>
                                <!-- REMARKS -->
                                <td style="width: 28%; border-right: 1px solid #000; padding: 6px 8px; vertical-align: top; line-height: 1.7;">
                                    <div style="font-weight: bold; margin-bottom: 4px;">REMARKS:</div>
                                    <div>New : <span style="border-bottom: 1px solid #000; display: inline-block; width: 120px;">&nbsp;</span></div>
                                    <div>Lost : <span style="border-bottom: 1px solid #000; display: inline-block; width: 120px;">&nbsp;</span></div>
                                    <div>Change : <span style="border-bottom: 1px solid #000; display: inline-block; width: 105px;">&nbsp;</span></div>
                                    <div>Transfer : <span style="border-bottom: 1px solid #000; display: inline-block; width: 100px;">&nbsp;</span></div>
                                </td>
                                <!-- ISSUED BY -->
                                <td style="width: 24%; border-right: 1px solid #000; padding: 6px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; margin-bottom: 50px;">ISSUED BY:</div>
                                    <div style="border-top: 1px solid #000; padding-top: 2px; text-align: center; font-size: 7pt; color: #555;">OSCA Officer Signature</div>
                                </td>
                                <!-- DATE ISSUED -->
                                <td style="width: 24%; border-right: 1px solid #000; padding: 6px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; margin-bottom: 50px;">DATE ISSUED:</div>
                                    <div style="border-top: 1px solid #000; padding-top: 2px; text-align: center; font-size: 7pt; color: #555;">Date Stamp</div>
                                </td>
                                <!-- RECEIVED BY -->
                                <td style="width: 24%; padding: 6px 8px; vertical-align: top;">
                                    <div style="font-weight: bold; margin-bottom: 50px;">RECEIVED BY:</div>
                                    <div style="border-top: 1px solid #000; padding-top: 2px; text-align: center; font-size: 7pt; color: #555;">Applicant Signature</div>
                                </td>
                            </tr>
                        </table>

                    </div>
                </div>
                `;
            } else if (type === 'housing') {
                html = `
                    <div style="border: 2px solid #000; padding: 25px; font-family: Arial, sans-serif; max-width: 820px; margin: 0 auto; color: #000; background: #fff;">
                        <div style="position: relative; text-align: center; margin-bottom: 12px; min-height: 65px;">
                            <img src="${pkhLogoSrc}" style="position: absolute; left: 0; top: 0; width: 60px; height: 60px; object-fit: contain;" alt="One Palayan Kalinga Hub Logo">
                            <div style="font-size: 8.5pt; font-weight: bold; text-transform: uppercase;">Republic of the Philippines &bull; Province of Nueva Ecija</div>
                            <h3 style="margin: 3px 0; font-size: 13pt;">CITY SOCIAL WELFARE AND DEVELOPMENT OFFICE</h3>
                            <div style="font-size: 9pt; font-weight: bold; letter-spacing: 1px;">CITY GOVERNMENT OF PALAYAN</div>
                            <h4 style="margin: 8px 0 0; font-size: 11pt; text-decoration: underline; letter-spacing: 0.5px;">SOCIALIZED HOUSING ASSISTANCE APPLICATION FORM</h4>
                        </div>
                        <hr style="border: none; border-top: 1.5px solid #000; margin: 8px 0 14px;">

                        <!-- HOUSING ASSISTANCE PATHWAY SELECTION -->
                        <div style="border: 1px solid #000; padding: 8px 12px; margin-bottom: 12px; background: #f5f5f5;">
                            <div style="font-weight: bold; font-size: 9pt; margin-bottom: 6px;">I. HOUSING ASSISTANCE PATHWAY (Pumili ng Isa):</div>
                            <div style="display: flex; gap: 24px; font-size: 8.5pt; line-height: 1.6;">
                                <div style="flex: 1; border-right: 1px dashed #999; padding-right: 12px;">
                                    <div style="font-weight: bold;">&#9744; RELOCATION HOUSING</div>
                                    <div style="font-size: 7.5pt; color: #333; margin-top: 2px;">Para sa paglipat sa designated housing community:</div>
                                    <div style="margin-top: 4px; padding-left: 12px;">
                                        <div>&#9744; Akap Village Housing Project</div>
                                        <div>&#9744; San Lorenzo NHA Housing</div>
                                    </div>
                                </div>
                                <div style="flex: 1.2;">
                                    <div style="font-weight: bold; color: #004d40;">&#9744; IN-SITE HOUSING (Sariling Lote)</div>
                                    <div style="font-size: 7.5pt; color: #333; margin-top: 2px;">Para sa pagpapatayo sa sarili o pinapahintulutang lupa sa Palayan City (Walang relocation na kailangan).</div>
                                </div>
                            </div>
                        </div>

                        <!-- CLIENT IDENTIFYING INFO -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 12px;">
                            <tr style="background: #e0e0e0;">
                                <td colspan="4" style="padding: 4px 8px; border: 1px solid #000; font-weight: bold;">II. APPLICANT INFORMATION</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; width: 18%; font-weight: bold;">Full Name:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000; width: 32%;">${userInfo.first_name} ${userInfo.last_name}</td>
                                <td style="padding: 5px 8px; border: 1px solid #000; width: 18%; font-weight: bold;">Contact No:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000; width: 32%;">${userInfo.contact_number || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; font-weight: bold;">Current Address:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;" colspan="3">${userInfo.address || 'Palayan City, Nueva Ecija'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; font-weight: bold;">Email Address:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;">${userInfo.email || 'N/A'}</td>
                                <td style="padding: 5px 8px; border: 1px solid #000; font-weight: bold;">Monthly Income:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;">&#8369; _____________________</td>
                            </tr>
                        </table>

                        <!-- IN-SITE PROPERTY PARTICULARS -->
                        <table style="width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 12px;">
                            <tr style="background: #e0e0e0;">
                                <td colspan="2" style="padding: 4px 8px; border: 1px solid #000; font-weight: bold;">III. IN-SITE HOUSING PROPERTY DETAILS (Kung In-Site Housing ang Aplikasyon)</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; width: 28%; font-weight: bold;">Land Ownership / Tenure:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;">
                                    &#9744; Titled Landowner (TCT/OCT) &nbsp;&nbsp;
                                    &#9744; Real Property Tax Declaration &nbsp;&nbsp;
                                    &#9744; Inherited / Family Lot &nbsp;&nbsp;
                                    &#9744; With Notarized Landowner Consent
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; font-weight: bold;">Proposed Site Address:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;">Street / Purok / Barangay, Palayan City: __________________________________________________</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 8px; border: 1px solid #000; font-weight: bold;">Lot Area / Scope:</td>
                                <td style="padding: 5px 8px; border: 1px solid #000;">
                                    Lot Area: __________ sq.m. &nbsp;&nbsp;|&nbsp;&nbsp;
                                    Scope: &#9744; New Core House &nbsp;&nbsp; &#9744; Full Reconstruction &nbsp;&nbsp; &#9744; Shelter Repair
                                </td>
                            </tr>
                        </table>

                        <!-- CERTIFICATION -->
                        <div style="font-size: 8pt; line-height: 1.5; margin: 12px 0;">
                            <p style="margin: 0;">Ako ay nagpapatunay na ang lahat ng impormasyong nakasulat sa itaas ay totoo at tama sa abot ng aking kaalaman. Nauunawaan ko na ang anumang maling pahayag ay maaaring maging sanhi ng pagkakansela ng aking aplikasyon para sa programang pabahay ng Lungsod ng Palayan.</p>
                        </div>

                        <!-- SIGNATURES -->
                        <div style="margin-top: 35px; display: flex; justify-content: space-between; font-size: 8.5pt;">
                            <div style="text-align: center;">
                                <div style="border-top: 1px solid #000; width: 220px; padding-top: 4px; font-weight: bold;">Lagda ng Aplikante / Beneficiary</div>
                                <div style="font-size: 7.5pt; color: #555;">Pangalan at Lagda</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="border-top: 1px solid #000; width: 140px; padding-top: 4px; font-weight: bold;">Petsa (Date)</div>
                                <div style="font-size: 7.5pt; color: #555;">${new Date().toLocaleDateString()}</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="border-top: 1px solid #000; width: 220px; padding-top: 4px; font-weight: bold;">CSWDO Intake Officer / Social Worker</div>
                                <div style="font-size: 7.5pt; color: #555;">Pagsusuri at Balidasyon</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            document.getElementById('printContent').innerHTML = html;
            document.getElementById('printModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closePrintModal() {
            const modal = document.getElementById('printModal');
            if (modal) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = '';
        }

        window.openPrintModal = openPrintModal;
        window.closePrintModal = closePrintModal;

        // Close modal on outside click
        const modalEl = document.getElementById('printModal');
        if (modalEl) {
            modalEl.addEventListener('click', function (e) {
                if (e.target === this) {
                    closePrintModal();
                }
            });
        }

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Escape to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('printModal');
                if (modal && modal.style.display === 'block') {
                    closePrintModal();
                }
            }
            // Ctrl + P to print (when modal is open)
            if (e.ctrlKey && e.key === 'p') {
                const modal = document.getElementById('printModal');
                if (modal && modal.style.display === 'block') {
                    e.preventDefault();
                    window.print();
                }
            }
        });

        // ========== AUTO-OPEN VIA URL PARAMETER ==========
        const urlParams = new URLSearchParams(window.location.search);
        const formParam = urlParams.get('form');
        if (formParam && ['intake', 'gis_ciu', 'pwd', 'senior', 'housing'].includes(formParam)) {
            openPrintModal(formParam);
        }

        // ========== CONSOLE WELCOME ==========
        console.log('📄 One Palayan-Kalinga Hub - Printable Forms ready');
    </script>

    <!-- Search Modal (Req 7) -->
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>

</html>
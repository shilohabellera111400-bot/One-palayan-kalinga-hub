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
    <link rel="stylesheet" href="css/portal-layout.css">
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
            body * {
                visibility: hidden;
            }

            .print-modal,
            .print-modal * {
                visibility: visible;
            }

            .print-modal {
                position: fixed;
                inset: 0;
                background: white;
                padding: 0;
                backdrop-filter: none;
            }

            .print-modal .modal-content {
                box-shadow: none;
                border: none;
                padding: 40px;
                border-radius: 0;
                background: white;
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
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-print"></i></div>
                <div class="logo-text">
                    <h2>One Palayan-Kalinga Hub</h2>
                    <span>Integrated Service Portal</span>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="user-info-sidebar">
                <div class="user-avatar-sidebar">
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </h4>
                    <span>Resident</span>
                </div>
            </div>

            <?php include __DIR__ . '/includes/usersidenav.php'; ?>

            <div class="sidebar-footer">
                <a href="index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                        <span>Menu</span>
                    </button>
                    <div class="page-title">
                        <h1><i class="fas fa-print"></i> Printable Forms</h1>
                        <p>Download or print pre-filled forms with your information</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
                        <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                    </button>
                    <a href="dashboard.php" class="top-icon"><i class="fas fa-tachometer-alt"></i></a>
                </div>
            </div>

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

                <!-- Form 5: Medical Assistance -->
                <div class="form-item-card">
                    <i class="fas fa-medkit form-item-icon"></i>
                    <h3>Medical Assistance Form</h3>
                    <p>Porma para sa pag-apply ng medical assistance, hospital bills, at iba pang health-related
                        support.</p>
                    <button onclick="openPrintModal('medical')" class="btn-primary">
                        <i class="fas fa-print"></i> Print Form
                    </button>
                </div>

                <!-- Form 6: Burial Assistance -->
                <div class="form-item-card">
                    <i class="fas fa-cross form-item-icon"></i>
                    <h3>Burial Assistance Form</h3>
                    <p>Porma para sa pag-apply ng burial assistance at iba pang funeral support services.</p>
                    <button onclick="openPrintModal('burial')" class="btn-primary">
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
        // ========== MOBILE SIDEBAR TOGGLE ==========
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebarClose = document.getElementById('sidebarClose');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        hamburgerBtn.addEventListener('click', openSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);

        // Close sidebar on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========== THEME TOGGLE ==========
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        const currentTheme = localStorage.getItem('theme') ||
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        function setTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            document.cookie = `dark_mode=${theme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;

            document.body.style.transition = 'background 0.4s ease, color 0.4s ease';
        }

        setTheme(currentTheme);

        themeToggle.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);

            themeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                themeToggle.style.transform = '';
            }, 200);
        });

        // ========== PRINT MODAL ==========
        const userInfo = <?php echo json_encode($user_info); ?>;
        const residentInfo = <?php echo json_encode($resident_info); ?>;

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
                          <img src="images/logo.png" style="width:45px;height:45px;object-fit:contain;" alt="Logo">
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
                html = `
                    <div style="border: 2px solid #000; padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #000; background: #fff;">
                        <h3 style="text-align: center; margin: 0;">PWD REGISTRATION AND APPLICATION FORM</h3>
                        <h4 style="text-align: center; margin-top: 5px; color: #555;">City of Palayan</h4>
                        <hr style="border-top: 2px solid #000; margin-top: 15px;">

                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <tr>
                                <td style="padding: 10px; border: 1px solid #000; font-weight: bold;">Name:</td>
                                <td style="padding: 10px; border: 1px solid #000;" colspan="3">${userInfo.first_name} ${userInfo.last_name}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #000; font-weight: bold;">Contact No:</td>
                                <td style="padding: 10px; border: 1px solid #000;">${userInfo.contact_number || 'N/A'}</td>
                                <td style="padding: 10px; border: 1px solid #000; font-weight: bold;">Email:</td>
                                <td style="padding: 10px; border: 1px solid #000;">${userInfo.email || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #000; font-weight: bold;">Address:</td>
                                <td style="padding: 10px; border: 1px solid #000;" colspan="3">${userInfo.address || 'Palayan City, Nueva Ecija'}</td>
                            </tr>
                        </table>

                        <div style="margin-top: 30px;">
                            <p>I hereby certify that I have a disability and wish to register for the PWD ID Card of Palayan City.</p>
                        </div>

                        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Applicant's Signature</p>
                            </div>
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Date</p>
                            </div>
                        </div>
                    </div>
                `;
            } else if (type === 'senior') {
                html = `
                    <div style="border: 2px solid #000; padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #000; background: #fff;">
                        <h3 style="text-align: center; margin: 0;">SENIOR CITIZEN REGISTRATION FORM</h3>
                        <h4 style="text-align: center; margin-top: 5px; color: #555;">Palayan City Senior Citizen Affairs Office</h4>
                        <hr style="border-top: 2px solid #000; margin-top: 15px;">

                        <div style="margin-top: 30px; line-height: 2;">
                            <p><strong>Name:</strong> ${userInfo.first_name} ${userInfo.last_name}</p>
                            <p><strong>Address:</strong> ${userInfo.address || 'Palayan City, Nueva Ecija'}</p>
                            <p><strong>Contact Number:</strong> ${userInfo.contact_number || 'N/A'}</p>
                            <p><strong>Email:</strong> ${userInfo.email || 'N/A'}</p>
                            <p><strong>Date of Registration:</strong> ${new Date().toLocaleDateString()}</p>
                        </div>

                        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Applicant's Signature</p>
                            </div>
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Date</p>
                            </div>
                        </div>
                    </div>
                `;
            } else if (type === 'housing') {
                html = `
                    <div style="border: 2px solid #000; padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #000; background: #fff;">
                        <h3 style="text-align: center; margin: 0;">HOUSING APPLICATION FORM</h3>
                        <h4 style="text-align: center; margin-top: 5px; color: #555;">Palayan City Housing Program</h4>
                        <hr style="border-top: 2px solid #000; margin-top: 15px;">

                        <div style="margin-top: 30px; line-height: 2;">
                            <p><strong>Name:</strong> ${userInfo.first_name} ${userInfo.last_name}</p>
                            <p><strong>Address:</strong> ${userInfo.address || 'Palayan City, Nueva Ecija'}</p>
                            <p><strong>Contact Number:</strong> ${userInfo.contact_number || 'N/A'}</p>
                            <p><strong>Email:</strong> ${userInfo.email || 'N/A'}</p>
                            <p><strong>Date of Application:</strong> ${new Date().toLocaleDateString()}</p>
                        </div>

                        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Applicant's Signature</p>
                            </div>
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Date</p>
                            </div>
                        </div>
                    </div>
                `;
            } else if (type === 'medical') {
                html = `
                    <div style="border: 2px solid #000; padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #000; background: #fff;">
                        <h3 style="text-align: center; margin: 0;">MEDICAL ASSISTANCE FORM</h3>
                        <h4 style="text-align: center; margin-top: 5px; color: #555;">CSWDO Palayan City</h4>
                        <hr style="border-top: 2px solid #000; margin-top: 15px;">

                        <div style="margin-top: 30px; line-height: 2;">
                            <p><strong>Name:</strong> ${userInfo.first_name} ${userInfo.last_name}</p>
                            <p><strong>Address:</strong> ${userInfo.address || 'Palayan City, Nueva Ecija'}</p>
                            <p><strong>Contact Number:</strong> ${userInfo.contact_number || 'N/A'}</p>
                            <p><strong>Date of Application:</strong> ${new Date().toLocaleDateString()}</p>
                        </div>

                        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Applicant's Signature</p>
                            </div>
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Date</p>
                            </div>
                        </div>
                    </div>
                `;
            } else if (type === 'burial') {
                html = `
                    <div style="border: 2px solid #000; padding: 30px; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; color: #000; background: #fff;">
                        <h3 style="text-align: center; margin: 0;">BURIAL ASSISTANCE FORM</h3>
                        <h4 style="text-align: center; margin-top: 5px; color: #555;">CSWDO Palayan City</h4>
                        <hr style="border-top: 2px solid #000; margin-top: 15px;">

                        <div style="margin-top: 30px; line-height: 2;">
                            <p><strong>Name:</strong> ${userInfo.first_name} ${userInfo.last_name}</p>
                            <p><strong>Address:</strong> ${userInfo.address || 'Palayan City, Nueva Ecija'}</p>
                            <p><strong>Contact Number:</strong> ${userInfo.contact_number || 'N/A'}</p>
                            <p><strong>Date of Application:</strong> ${new Date().toLocaleDateString()}</p>
                        </div>

                        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Applicant's Signature</p>
                            </div>
                            <div>
                                <p style="border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 8px;">Date</p>
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
            document.getElementById('printModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Close modal on outside click
        document.getElementById('printModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closePrintModal();
            }
        });

        // ========== KEYBOARD SHORTCUTS ==========
        document.addEventListener('keydown', function (e) {
            // Escape to close sidebar or modal
            if (e.key === 'Escape') {
                closeSidebar();
                const modal = document.getElementById('printModal');
                if (modal.style.display === 'block') {
                    closePrintModal();
                }
            }
            // Ctrl + P to print (when modal is open)
            if (e.ctrlKey && e.key === 'p') {
                const modal = document.getElementById('printModal');
                if (modal.style.display === 'block') {
                    e.preventDefault();
                    window.print();
                }
            }
        });

        // ========== CONSOLE WELCOME ==========
        console.log('📄 One Palayan-Kalinga Hub - Printable Forms');
        console.log('💡 Keyboard shortcuts: ESC (Close Modal/Menu), Ctrl+P (Print when modal open)');
        console.log('📱 Mobile navigation: Click the hamburger menu (☰) to open sidebar');
        console.log('🌓 Toggle dark/light mode using the sun/moon button!');
    </script>
</body>

</html>
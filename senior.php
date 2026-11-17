<?php
// senior.php - Simplified & Senior-Friendly Portal for Senior Citizens
// CSWDO Palayan City - Provision of Identification Card & Purchase Booklet
require_once __DIR__ . '/config/database.php';

if (!isPwdSeniorScopeActive()) {
    $_SESSION['info'] = "Senior Citizen online services are temporarily inactive in the current system scope. Please visit CSWDO Window 3 at City Hall for in-person OSCA assistance.";
    redirect('services.php');
}

if (!isLoggedIn()) {
    redirect('login.php?redirect=senior.php');
}

if (isStaff() || isAdmin()) {
    redirect('staff-dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Check if user is verified resident
$check_verified = "SELECT verification_status FROM users WHERE user_id = :user_id";
$verify_stmt = $db->prepare($check_verified);
$verify_stmt->bindParam(':user_id', $_SESSION['user_id']);
$verify_stmt->execute();
$user_verify = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verify || $user_verify['verification_status'] != 'verified') {
    redirect('verify-resident.php');
}

// Fetch resident profile
$check_senior = $db->prepare("SELECT * FROM residents WHERE user_id = :id");
$check_senior->bindParam(':id', $_SESSION['user_id']);
$check_senior->execute();
$resident_info = $check_senior->fetch(PDO::FETCH_ASSOC);

// Calculate Age and RA 9994 Eligibility (Strictly at least 60 years old)
$resident_age = null;
$is_eligible_senior = false;
$has_birth_date = !empty($resident_info['birth_date']) && $resident_info['birth_date'] !== '0000-00-00';

if ($has_birth_date) {
    $birth = new DateTime($resident_info['birth_date']);
    $today = new DateTime();
    $resident_age = $today->diff($birth)->y;
    $is_eligible_senior = ($resident_age >= 60);
}

// Auto-detect and sync Senior Citizen status if age >= 60; clear if under 60
if ($is_eligible_senior) {
    if (($resident_info['senior_citizen'] ?? 0) != 1) {
        $db->prepare("UPDATE residents SET senior_citizen = 1 WHERE user_id = :id")->execute([':id' => $_SESSION['user_id']]);
        $resident_info['senior_citizen'] = 1;
    }
    $_SESSION['is_senior_citizen'] = true;
} else {
    if (($resident_info['senior_citizen'] ?? 0) == 1) {
        $db->prepare("UPDATE residents SET senior_citizen = 0 WHERE user_id = :id")->execute([':id' => $_SESSION['user_id']]);
        $resident_info['senior_citizen'] = 0;
    }
    $_SESSION['is_senior_citizen'] = false;
}
$is_verified_senior = $is_eligible_senior;

// Check if resident has approved residency verification ID on file
$ver_stmt = $db->prepare("SELECT id_file_path FROM verification_requests WHERE user_id = :uid AND status = 'approved' ORDER BY verification_id DESC LIMIT 1");
$ver_stmt->execute([':uid' => $_SESSION['user_id']]);
$has_verified_id = (bool)$ver_stmt->fetchColumn();

// Fetch all applications submitted by this user
$my_apps_stmt = $db->prepare("SELECT * FROM senior_applications WHERE user_id = :uid ORDER BY created_at DESC");
$my_apps_stmt->bindParam(':uid', $_SESSION['user_id']);
$my_apps_stmt->execute();
$my_senior_apps = $my_apps_stmt->fetchAll(PDO::FETCH_ASSOC);

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
$senior_large_text = isset($_COOKIE['senior_large_text']) && $_COOKIE['senior_large_text'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>" class="<?php echo $senior_large_text ? 'senior-large-font' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Citizen Services & ID Booklet - CSWDO Palayan City</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        /* ==========================================================================
           SENIOR CITIZEN ACCESSIBILITY & SIMPLIFIED INTERFACE STYLES
           High legibility, clean visual hierarchy, generous touch targets
           ========================================================================== */
        :root {
            --snr-navy: #0F3B56;
            --snr-blue: #0284C7;
            --snr-blue-light: #E0F2FE;
            --snr-green: #059669;
            --snr-green-light: #ECFDF5;
            --snr-amber: #D97706;
            --snr-amber-light: #FFFBEB;
            --snr-card-bg: #FFFFFF;
            --snr-text-main: #1E293B;
            --snr-text-sub: #475569;
            --snr-border: #CBD5E1;
        }

        [data-theme="dark"] {
            --snr-navy: #0369A1;
            --snr-blue: #38BDF8;
            --snr-blue-light: rgba(56, 189, 248, 0.15);
            --snr-green: #10B981;
            --snr-green-light: rgba(16, 185, 129, 0.15);
            --snr-amber: #F59E0B;
            --snr-amber-light: rgba(245, 158, 11, 0.15);
            --snr-card-bg: #1E293B;
            --snr-text-main: #F8FAFC;
            --snr-text-sub: #94A3B8;
            --snr-border: #334155;
        }

        /* Senior Large Text Booster */
        .senior-large-font {
            font-size: 112%;
        }
        .senior-large-font .snr-step-desc,
        .senior-large-font .snr-benefit-p,
        .senior-large-font .snr-form-label {
            font-size: 1.15rem !important;
            line-height: 1.65 !important;
        }

        /* Top Accessibility Bar */
        .snr-access-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--snr-card-bg);
            border: 1px solid var(--snr-border);
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            flex-wrap: wrap;
            gap: 12px;
        }
        .snr-access-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: var(--snr-text-main);
            font-size: 0.95rem;
        }
        .snr-access-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .snr-zoom-btn {
            background: var(--snr-blue-light);
            color: var(--snr-blue);
            border: 1px solid var(--snr-border);
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .snr-zoom-btn:hover {
            transform: translateY(-1px);
            background: var(--snr-blue);
            color: #fff;
        }

        /* Simplified Senior Friendly Hero */
        .snr-hero-card {
            background: linear-gradient(135deg, #0F3B56 0%, #0369A1 100%);
            border-radius: 24px;
            padding: 30px 34px;
            color: #FFFFFF;
            box-shadow: 0 10px 25px rgba(15, 59, 86, 0.18);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .snr-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(6px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .snr-hero-title {
            font-size: 1.85rem;
            font-weight: 800;
            margin: 0 0 10px 0;
            line-height: 1.25;
            color: #FFFFFF;
        }
        .snr-hero-subtitle {
            font-size: 1.05rem;
            line-height: 1.6;
            margin: 0 0 20px 0;
            max-width: 820px;
            opacity: 0.95;
        }
        .snr-pills-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .snr-pill-tag {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #FFFFFF;
        }

        /* Simplified 4-Tab Bar */
        .snr-tabs-bar {
            display: flex;
            gap: 10px;
            background: var(--snr-card-bg);
            border: 1px solid var(--snr-border);
            border-radius: 18px;
            padding: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .snr-tab-btn {
            flex: 1;
            min-width: 170px;
            padding: 14px 20px;
            border: none;
            background: transparent;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--snr-text-sub);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .snr-tab-btn:hover {
            color: var(--snr-blue);
            background: var(--snr-blue-light);
        }
        .snr-tab-btn.active {
            background: var(--snr-blue);
            color: #FFFFFF !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .snr-tab-content {
            display: none;
        }
        .snr-tab-content.active {
            display: block;
            animation: fadeIn 0.25s ease-out forwards;
        }

        /* Simple 3-Step Visual Process (Replaces dense 5-col table) */
        .snr-steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .snr-step-card {
            background: var(--snr-card-bg);
            border: 1.5px solid var(--snr-border);
            border-radius: 20px;
            padding: 26px 24px;
            position: relative;
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: transform 0.2s ease;
        }
        .snr-step-card:hover {
            transform: translateY(-3px);
            border-color: var(--snr-blue);
        }
        .snr-step-num {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--snr-blue-light);
            color: var(--snr-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
        }
        .snr-step-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--snr-text-main);
            margin: 0;
            line-height: 1.35;
        }
        .snr-step-desc {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--snr-text-sub);
            margin: 0;
        }
        .snr-step-badge {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px dashed var(--snr-border);
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--snr-green);
        }

        /* Prominent Action Strip */
        .snr-action-strip {
            background: var(--snr-card-bg);
            border: 2px solid var(--snr-blue);
            border-radius: 22px;
            padding: 24px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.1);
            margin-bottom: 25px;
        }
        .snr-action-btn-main {
            background: var(--snr-green);
            color: #FFFFFF !important;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 800;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .snr-action-btn-main:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        .snr-action-btn-sec {
            background: var(--snr-blue-light);
            color: var(--snr-blue) !important;
            padding: 14px 24px;
            border-radius: 50px;
            font-size: 0.98rem;
            font-weight: 700;
            border: 1.5px solid var(--snr-blue);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .snr-action-btn-sec:hover {
            background: var(--snr-blue);
            color: #FFFFFF !important;
        }

        /* Large Accessible Form */
        .snr-form-card {
            background: var(--snr-card-bg);
            border: 1px solid var(--snr-border);
            border-radius: 24px;
            padding: 34px 38px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.04);
            max-width: 800px;
            margin: 0 auto;
        }
        .snr-form-group {
            margin-bottom: 22px;
        }
        .snr-form-label {
            display: block;
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--snr-text-main);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .snr-form-input,
        .snr-form-select,
        .snr-form-textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--snr-border);
            border-radius: 14px;
            font-size: 1rem;
            font-family: inherit;
            color: var(--snr-text-main);
            background: var(--snr-card-bg);
            box-sizing: border-box;
            transition: border-color 0.2s ease;
        }
        .snr-form-input:focus,
        .snr-form-select:focus,
        .snr-form-textarea:focus {
            outline: none;
            border-color: var(--snr-blue);
            box-shadow: 0 0 0 4px var(--snr-blue-light);
        }
        .snr-form-hint {
            display: block;
            font-size: 0.88rem;
            color: var(--snr-text-sub);
            margin-top: 6px;
            line-height: 1.45;
        }

        /* Large Benefit Cards */
        .snr-benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .snr-benefit-card {
            background: var(--snr-card-bg);
            border: 1px solid var(--snr-border);
            border-radius: 20px;
            padding: 26px 24px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .snr-benefit-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .snr-benefit-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--snr-text-main);
            margin: 0;
        }
        .snr-benefit-p {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--snr-text-sub);
            margin: 0;
        }

        /* Restriction Box for non-seniors */
        .snr-restriction-card {
            background: var(--snr-card-bg);
            border: 2px solid #E2E8F0;
            border-radius: 24px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0,0,0,0.04);
            max-width: 680px;
            margin: 40px auto;
        }
        .snr-restriction-icon {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #FEF3C7;
            color: #D97706;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 20px auto;
        }
        .snr-restriction-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--snr-text-main);
            margin: 0 0 12px 0;
        }
        .snr-restriction-desc {
            font-size: 1.05rem;
            line-height: 1.65;
            color: var(--snr-text-sub);
            margin: 0 0 24px 0;
        }
        .snr-restriction-btns {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Accessibility Toolbar for Elderly Citizens -->
            <div class="snr-access-bar">
                <div class="snr-access-title">
                    <i class="fas fa-universal-access" style="color: var(--snr-blue); font-size: 1.25rem;"></i>
                    <span>Gabay sa Pagbasa para sa Senior Citizens (Senior Friendly View)</span>
                </div>
                <div class="snr-access-actions">
                    <button type="button" class="snr-zoom-btn" onclick="toggleSeniorLargeFont()" id="snrZoomBtn">
                        <i class="fas fa-magnifying-glass-plus"></i>
                        <span><?php echo $senior_large_text ? 'Ibalik sa Karaniwang Letra' : 'A+ Palakihin ang Letra'; ?></span>
                    </button>
                </div>
            </div>

            <?php if (!$is_eligible_senior): ?>
            <!-- ==========================================================================
                 RESTRICTION CARD: EXCLUSIVELY FOR SENIOR CITIZENS (AGE 60+)
                 ========================================================================== -->
            <div class="snr-restriction-card">
                <div class="snr-restriction-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <h2 class="snr-restriction-title">Eksklusibong Serbisyo para sa Senior Citizens</h2>
                <p class="snr-restriction-desc">
                    Ayon sa Republic Act No. 9994 (Expanded Senior Citizens Act) at CSWDO Citizen's Charter ng Lungsod ng Palayan, ang Senior Citizen ID at Purchase Booklet ay nakalaan lamang para sa mga residenteng may <strong>edad 60 taong gulang pataas</strong>.
                </p>

                <div style="background: var(--snr-amber-light); border: 1.5px solid var(--snr-amber); border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; text-align: left; display: flex; align-items: center; gap: 14px;">
                    <i class="fas fa-info-circle" style="color: var(--snr-amber); font-size: 1.4rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.95rem; color: var(--snr-text-main); line-height: 1.5;">
                        <strong>Katayuan sa Profile:</strong>
                        <?php if ($resident_age !== null): ?>
                            Nakatala bilang <strong><?php echo $resident_age; ?> taong gulang</strong>.
                        <?php else: ?>
                            Wala pang nakatalang Petsa ng Kapanganakan (Birth Date).
                        <?php endif; ?>
                        Kung ikaw ay 60 taong gulang na, maaari mong i-update ang iyong profile upang mabuksan ang serbisyong ito.
                    </div>
                </div>

                <div class="snr-restriction-btns">
                    <a href="dashboard.php" class="snr-action-btn-sec" style="border-radius: 50px;">
                        <i class="fas fa-home"></i> Bumalik sa Dashboard
                    </a>
                    <a href="services.php" class="snr-action-btn-main" style="background: var(--snr-navy); border-radius: 50px;">
                        <i class="fas fa-hand-holding-heart"></i> Iba Pang Serbisyo (AICS & Housing)
                    </a>
                    <?php if (!$has_birth_date): ?>
                    <a href="profile.php" class="snr-action-btn-main" style="border-radius: 50px;">
                        <i class="fas fa-user-edit"></i> I-update ang Profile
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- ==========================================================================
                 SIMPLIFIED SENIOR CITIZEN INTERFACE (FOR VERIFIED SENIORS 60+)
                 ========================================================================== -->

            <!-- Simplified Clean Hero Card (No duplicate banners, no technical jargon) -->
            <div class="snr-hero-card">
                <span class="snr-hero-badge">
                    <i class="fas fa-certificate"></i> CSWDO Palayan City • Window 3 Special Senior Lane
                </span>
                <h1 class="snr-hero-title">Senior Citizen ID at Purchase Booklet</h1>
                <p class="snr-hero-subtitle">
                    Libreng ID at dalawang (2) Purchase Booklet para sa 20% diskwento sa mga gamot, bilihin, at pamasahe ng ating mga nakatatanda sa Palayan City.
                </p>
                <div class="snr-pills-row">
                    <span class="snr-pill-tag"><i class="fas fa-gift" style="color: #6EE7B7;"></i> 100% Libre (Walang Bayad)</span>
                    <span class="snr-pill-tag"><i class="fas fa-calendar-check" style="color: #93C5FD;"></i> 1 Araw na Pagproseso</span>
                    <span class="snr-pill-tag"><i class="fas fa-user-check" style="color: #FDE047;"></i> Rehistradong Senior (Edad <?php echo $resident_age; ?>)</span>
                </div>
            </div>

            <!-- Simplified 4-Tab Navigation Bar -->
            <div class="snr-tabs-bar" role="tablist">
                <button type="button" class="snr-tab-btn active" onclick="switchSeniorTab('tab-process', this)">
                    <i class="fas fa-list-check" style="font-size: 1.1rem;"></i>
                    <span>Paano Kumuha (3 Hakbang)</span>
                </button>
                <button type="button" class="snr-tab-btn" onclick="switchSeniorTab('tab-apply', this)">
                    <i class="fas fa-file-pen" style="font-size: 1.1rem;"></i>
                    <span>Mag-apply Online</span>
                </button>
                <button type="button" class="snr-tab-btn" onclick="switchSeniorTab('tab-discounts', this)">
                    <i class="fas fa-tags" style="font-size: 1.1rem;"></i>
                    <span>20% Diskwento at Benepisyo</span>
                </button>
                <button type="button" class="snr-tab-btn" onclick="switchSeniorTab('tab-apps', this)">
                    <i class="fas fa-folder-open" style="font-size: 1.1rem;"></i>
                    <span>Aking Aplikasyon (<?php echo count($my_senior_apps); ?>)</span>
                </button>
            </div>

            <!-- TAB 1: SIMPLIFIED 3-STEP PROCESS -->
            <div id="tab-process" class="snr-tab-content active">
                <div class="snr-steps-grid">
                    <!-- Step 1 -->
                    <div class="snr-step-card">
                        <div class="snr-step-num">1</div>
                        <h3 class="snr-step-title">Katibayan ng Edad</h3>
                        <p class="snr-step-desc">
                            Maghanda ng patunay na may edad 60 pataas tulad ng PSA Birth Certificate o anumang Government Valid ID.
                        </p>
                        <?php if ($has_verified_id): ?>
                        <div class="snr-step-badge" style="color: var(--snr-green);">
                            <i class="fas fa-check-circle"></i> May approved ID ka na sa aming rekord!
                        </div>
                        <?php else: ?>
                        <div class="snr-step-badge" style="color: var(--snr-text-sub);">
                            <i class="fas fa-id-card"></i> Dalhin o i-upload sa online form
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Step 2 -->
                    <div class="snr-step-card">
                        <div class="snr-step-num">2</div>
                        <h3 class="snr-step-title">Libreng Litrato</h3>
                        <p class="snr-step-desc">
                            Libreng kukunan ng litrato sa Window 3 ng City Hall, o maaari ring mag-upload ng 1x1 / 2x2 photo dito online.
                        </p>
                        <div class="snr-step-badge" style="color: var(--snr-green);">
                            <i class="fas fa-camera"></i> 100% Libreng photo shoot sa City Hall
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="snr-step-card">
                        <div class="snr-step-num">3</div>
                        <h3 class="snr-step-title">Kunin ang ID at Booklet</h3>
                        <p class="snr-step-desc">
                            Tanggapin ang iyong laminated Senior Citizen ID at dalawang (2) Purchase Booklet para sa mga gamot at bilihin.
                        </p>
                        <div class="snr-step-badge" style="color: var(--snr-blue);">
                            <i class="fas fa-clock"></i> Mabilis na release sa Window 3 Lane
                        </div>
                    </div>
                </div>

                <!-- Prominent Quick Action Strip -->
                <div class="snr-action-strip">
                    <div>
                        <h4 style="font-size: 1.25rem; font-weight: 800; color: var(--snr-text-main); margin: 0 0 6px 0;">
                            Handa ka na bang kumuha ng Senior Citizen ID?
                        </h4>
                        <p style="font-size: 0.95rem; color: var(--snr-text-sub); margin: 0;">
                            Punan ang maikling online form upang maihanda ng CSWDO ang iyong ID bago ka pumunta sa City Hall.
                        </p>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <a href="print-forms.php?form=senior" class="snr-action-btn-sec" target="_blank" title="I-print ang Papel na Form kung nais dalhin sa City Hall">
                            <i class="fas fa-print"></i> I-print ang Form
                        </a>
                        <button type="button" class="snr-action-btn-main" onclick="switchSeniorTab('tab-apply', document.querySelectorAll('.snr-tab-btn')[1])">
                            <i class="fas fa-paper-plane"></i> Mag-apply Online Ngayon
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SIMPLIFIED APPLICATION FORM -->
            <div id="tab-apply" class="snr-tab-content">
                <div class="snr-form-card">
                    <div style="text-align: center; margin-bottom: 24px;">
                        <div style="width: 56px; height: 56px; border-radius: 16px; background: var(--snr-blue-light); color: var(--snr-blue); display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 12px;">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--snr-text-main); margin: 0 0 6px 0;">
                            Aplikasyon para sa Senior Citizen ID at Booklet
                        </h2>
                        <p style="font-size: 0.95rem; color: var(--snr-text-sub); margin: 0;">
                            CSWDO Palayan City • Window 3 Special Lane • Libre (Walang Bayad)
                        </p>
                    </div>

                    <!-- Direct Alert Feedback Box -->
                    <div id="seniorApplyAlert" style="display: none; padding: 14px 18px; border-radius: 14px; font-size: 0.98rem; font-weight: 600; margin-bottom: 22px;"></div>

                    <form id="seniorDirectForm" onsubmit="submitSeniorDirect(event)" enctype="multipart/form-data">
                        <!-- Requirement 1: Residency Status -->
                        <div class="snr-form-group">
                            <label class="snr-form-label">
                                <i class="fas fa-map-marker-alt" style="color: var(--snr-blue);"></i> 1. Ikaw ba ay residente ng Palayan City? *
                            </label>
                            <select name="is_registered_voter" class="snr-form-select" required style="font-weight: 600;">
                                <option value="yes">Oo, ako ay rehistradong botante o residente ng Palayan City</option>
                                <option value="no">Hindi (Hindi kwalipikado)</option>
                            </select>
                            <span class="snr-form-hint">Ang programang ito ay para sa mga residente ng Palayan City.</span>
                        </div>

                        <!-- Requirement 2: Proof of Age -->
                        <div class="snr-form-group">
                            <label class="snr-form-label">
                                <i class="fas fa-calendar-day" style="color: var(--snr-blue);"></i> 2. Katibayan ng Edad (Birth Certificate o Valid ID) <?php echo $has_verified_id ? '' : '*'; ?>
                            </label>
                            
                            <?php if ($has_verified_id): ?>
                            <div style="background: var(--snr-green-light); border: 1.5px solid var(--snr-green); border-radius: 14px; padding: 12px 16px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; color: var(--snr-green); font-size: 0.92rem; font-weight: 700;">
                                <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                                <span>May aprubadong Valid ID ka na sa rekord. Hindi na kailangang mag-upload maliban kung may bagong ID.</span>
                            </div>
                            <?php endif; ?>

                            <input type="file" name="valid_id" class="snr-form-input" accept=".pdf,image/*" <?php echo $has_verified_id ? '' : 'required'; ?>>
                            <span class="snr-form-hint">Maaaring litrato o PDF ng Birth Certificate, Passport, Driver's License, o Voters ID na nagpapatunay ng edad 60 pataas.</span>
                        </div>

                        <!-- Requirement 3: Residency Certification -->
                        <div class="snr-form-group">
                            <label class="snr-form-label">
                                <i class="fas fa-file-contract" style="color: var(--snr-blue);"></i> 3. Barangay Certificate of Residency o Voter's Cert (Optional)
                            </label>
                            <input type="file" name="voter_or_residency_cert" class="snr-form-input" accept=".pdf,image/*">
                            <span class="snr-form-hint">Maaari ring i-presenta ang orihinal sa Window 3 kapag kukunin na ang ID.</span>
                        </div>

                        <!-- Requirement 4: 1x1 Photo -->
                        <div class="snr-form-group">
                            <label class="snr-form-label">
                                <i class="fas fa-camera" style="color: var(--snr-blue);"></i> 4. 1x1 o 2x2 Larawan (Photo para sa ID)
                            </label>
                            <input type="file" name="id_picture" class="snr-form-input" accept="image/*">
                            <span class="snr-form-hint">Kung walang dalang litrato, may libreng photo shoot sa Window 3 Special Lane ng City Hall.</span>
                        </div>

                        <!-- Optional Notes -->
                        <div class="snr-form-group">
                            <label class="snr-form-label">
                                <i class="fas fa-comment-dots" style="color: var(--snr-blue);"></i> Karagdagang Mensahe (Hindi Sapilitan / Optional)
                            </label>
                            <textarea name="details" class="snr-form-textarea" rows="2" placeholder="Halimbawa: Unang beses mag-aapply para sa Senior ID..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="seniorDirectBtn" class="snr-action-btn-main" style="width: 100%; justify-content: center; font-size: 1.15rem; padding: 16px; border-radius: 16px; margin-top: 10px;">
                            <i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 3
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 3: SENIOR CITIZEN PRIVILEGES & 20% DISCOUNTS (RA 9994) -->
            <div id="tab-discounts" class="snr-tab-content">
                <div class="snr-benefits-grid">
                    <!-- Benefit 1 -->
                    <div class="snr-benefit-card">
                        <div class="snr-benefit-icon" style="background: #E0F2FE; color: #0284C7;">
                            <i class="fas fa-prescription-bottle-medical"></i>
                        </div>
                        <h3 class="snr-benefit-title">20% Diskwento sa Gamot at Bitamina</h3>
                        <p class="snr-benefit-p">
                            Gamitin ang iyong <strong>Medicine Purchase Booklet</strong> kasama ang Senior Citizen ID sa lahat ng botika upang makakuha ng 20% diskwento at walang babayarang VAT alinsunod sa batas.
                        </p>
                    </div>

                    <!-- Benefit 2 -->
                    <div class="snr-benefit-card">
                        <div class="snr-benefit-icon" style="background: #ECFDF5; color: #059669;">
                            <i class="fas fa-cart-shopping"></i>
                        </div>
                        <h3 class="snr-benefit-title">5% Diskwento sa Pangunahing Bilihin</h3>
                        <p class="snr-benefit-p">
                            Gamitin ang iyong <strong>Grocery Purchase Booklet</strong> sa mga grocery store at supermarket para sa 5% diskwento sa bigas, gatas, kape, karne, at pangunahing pagkain.
                        </p>
                    </div>

                    <!-- Benefit 3 -->
                    <div class="snr-benefit-card">
                        <div class="snr-benefit-icon" style="background: #FEF3C7; color: #D97706;">
                            <i class="fas fa-bus-simple"></i>
                        </div>
                        <h3 class="snr-benefit-title">20% Bawas sa Pamasahe sa Sasakyan</h3>
                        <p class="snr-benefit-p">
                            20% diskwento sa lahat ng pampublikong sasakyan tulad ng jeepney, bus, tricycle, tren, barko, at eroplano sa buong bansa gamit ang opisyal na OSCA ID.
                        </p>
                    </div>

                    <!-- Benefit 4 -->
                    <div class="snr-benefit-card">
                        <div class="snr-benefit-icon" style="background: #F3E8FF; color: #9333EA;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h3 class="snr-benefit-title">5% Diskwento sa Kuryente at Tubig</h3>
                        <p class="snr-benefit-p">
                            Diskwento sa konsumo sa kuryente (hanggang 100 kWh) at tubig (hanggang 30 metro kubiko) sa residential account na nakapangalan sa Senior Citizen.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MY APPLICATIONS STATUS -->
            <div id="tab-apps" class="snr-tab-content">
                <?php if (empty($my_senior_apps)): ?>
                <div style="background: var(--snr-card-bg); border-radius: 20px; border: 1.5px solid var(--snr-border); padding: 48px 20px; text-align: center; color: var(--snr-text-sub);">
                    <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 14px; opacity: 0.4; display: block; color: var(--snr-blue);"></i>
                    <h3 style="color: var(--snr-text-main); font-size: 1.3rem; font-weight: 800; margin: 0 0 8px 0;">Wala pang Naisusumiteng Aplikasyon</h3>
                    <p style="font-size: 0.98rem; max-width: 520px; margin: 0 auto 20px auto; line-height: 1.6;">
                        Maaari kang mag-apply para sa iyong libreng Senior Citizen ID at Purchase Booklet gamit ang aming simpleng online form.
                    </p>
                    <button type="button" class="snr-action-btn-main" onclick="switchSeniorTab('tab-apply', document.querySelectorAll('.snr-tab-btn')[1])">
                        <i class="fas fa-file-pen"></i> Mag-apply Ngayon
                    </button>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($my_senior_apps as $app): ?>
                    <?php
                        $status_colors = [
                            'submitted' => ['bg' => '#E0F2FE', 'text' => '#0369A1', 'icon' => 'fa-paper-plane', 'label' => 'Sinusuri sa Window 3 (Under Review)'],
                            'reviewing' => ['bg' => '#FEF3C7', 'text' => '#B45309', 'icon' => 'fa-clock', 'label' => 'Nasa CMO / Mayor para sa Lagda'],
                            'approved'  => ['bg' => '#ECFDF5', 'text' => '#047857', 'icon' => 'fa-check-circle', 'label' => 'Aprubado na - Handa nang Kunin!'],
                            'released'  => ['bg' => '#E0E7FF', 'text' => '#4338CA', 'icon' => 'fa-box-open', 'label' => 'Naibigay na ang ID at Booklet'],
                            'rejected'  => ['bg' => '#FEE2E2', 'text' => '#B91C1C', 'icon' => 'fa-times-circle', 'label' => 'Hindi Kwalipikado']
                        ];
                        $st = $status_colors[$app['status']] ?? $status_colors['submitted'];
                    ?>
                    <div style="background: var(--snr-card-bg); border-radius: 20px; border: 1.5px solid var(--snr-border); padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <span style="font-family: monospace; font-size: 0.88rem; font-weight: 800; color: var(--snr-text-sub); background: var(--snr-blue-light); padding: 4px 10px; border-radius: 8px;">
                                #<?php echo htmlspecialchars($app['tracking_number']); ?>
                            </span>
                            <span style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['text']; ?>; font-size: 0.82rem; font-weight: 800; padding: 5px 12px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas <?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
                            </span>
                        </div>

                        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--snr-text-main); margin: 0;">
                            Senior Citizen ID at Purchase Booklet
                        </h3>

                        <?php if (!empty($app['osca_id_number'])): ?>
                        <div style="background: #ECFDF5; border-left: 4px solid #059669; padding: 10px 14px; border-radius: 8px; font-size: 0.92rem; color: #047857; font-weight: 700;">
                            <i class="fas fa-id-card"></i> OSCA ID Number: <?php echo htmlspecialchars($app['osca_id_number']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($app['booklet_number'])): ?>
                        <div style="background: #EFF6FF; border-left: 4px solid #2563EB; padding: 10px 14px; border-radius: 8px; font-size: 0.92rem; color: #1D4ED8; font-weight: 700;">
                            <i class="fas fa-book"></i> Booklet Control #: <?php echo htmlspecialchars($app['booklet_number']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($app['status'] === 'rejected' && !empty($app['rejection_reason'])): ?>
                        <div style="background: #FEF2F2; border-left: 4px solid #DC2626; padding: 10px 14px; border-radius: 8px; font-size: 0.9rem; color: #B91C1C;">
                            <strong>Dahilan:</strong> <?php echo htmlspecialchars($app['rejection_reason']); ?>
                        </div>
                        <?php endif; ?>

                        <div style="font-size: 0.88rem; color: var(--snr-text-sub); border-top: 1px solid var(--snr-border); padding-top: 12px; margin-top: auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span>Petsa: <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if ($app['status'] === 'approved' || $app['status'] === 'released'): ?>
                                <a href="print-claim-stub.php?type=senior&id=<?php echo $app['application_id']; ?>" target="_blank" class="snr-action-btn-main" style="padding: 7px 16px; font-size: 0.85rem; border-radius: 50px;">
                                    <i class="fas fa-print"></i> Claim Stub
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($app['valid_id_path'])): ?>
                                <a href="<?php echo htmlspecialchars($app['valid_id_path']); ?>" target="_blank" style="color: var(--snr-blue); font-weight: 700; text-decoration: none; font-size: 0.88rem;">
                                    <i class="fas fa-paperclip"></i> Tingnan ang ID
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
    // Senior Tab Switching
    function switchSeniorTab(tabId, btnElement) {
        document.querySelectorAll('.snr-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.snr-tab-content').forEach(content => content.classList.remove('active'));
        if (btnElement) btnElement.classList.add('active');
        const target = document.getElementById(tabId);
        if (target) {
            target.classList.add('active');
            window.scrollTo({ top: target.offsetTop - 100, behavior: 'smooth' });
        }
    }

    // Senior Large Font Mode Toggle
    function toggleSeniorLargeFont() {
        const html = document.documentElement;
        const isLarge = html.classList.toggle('senior-large-font');
        const btn = document.getElementById('snrZoomBtn');
        if (btn) {
            btn.querySelector('span').textContent = isLarge ? 'Ibalik sa Karaniwang Letra' : 'A+ Palakihin ang Letra';
        }
        document.cookie = `senior_large_text=${isLarge ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
    }

    // Direct Form Submission
    function submitSeniorDirect(e) {
        e.preventDefault();
        const form = document.getElementById('seniorDirectForm');
        const alertBox = document.getElementById('seniorApplyAlert');
        const btn = document.getElementById('seniorDirectBtn');

        alertBox.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Isinusumite sa Window 3...';

        const formData = new FormData(form);

        fetch('submit-senior-application-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 3';
            if (data.success) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#ECFDF5';
                alertBox.style.color = '#047857';
                alertBox.style.border = '1.5px solid #059669';
                alertBox.innerHTML = `<i class="fas fa-check-circle" style="font-size: 1.2rem;"></i> ${data.message}`;
                form.reset();
                setTimeout(() => window.location.reload(), 2500);
            } else {
                alertBox.style.display = 'block';
                alertBox.style.background = '#FEF2F2';
                alertBox.style.color = '#B91C1C';
                alertBox.style.border = '1.5px solid #DC2626';
                alertBox.innerHTML = `<i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i> ${data.message}`;
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 3';
            alertBox.style.display = 'block';
            alertBox.style.background = '#FEF2F2';
            alertBox.style.color = '#B91C1C';
            alertBox.style.border = '1.5px solid #DC2626';
            alertBox.innerHTML = '<i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i> Network error. Pakisubukan muli.';
        });
    }

    // Dark Mode integration
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            const nextTheme = isDark ? 'light' : 'dark';
            html.setAttribute('data-theme', nextTheme);
            const icon = themeToggle.querySelector('i');
            if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            document.cookie = `dark_mode=${nextTheme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
        });
    }
    </script>
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>

<?php
// pwd.php - Issuance of Person with Disability Identification Card (PWD ID) and Purchase Booklet
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php?redirect=pwd.php');
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

// Fetch PWD resident profile
$check_pwd = $db->prepare("SELECT * FROM residents WHERE user_id = :id");
$check_pwd->bindParam(':id', $_SESSION['user_id']);
$check_pwd->execute();
$resident_info = $check_pwd->fetch(PDO::FETCH_ASSOC);

$is_verified_pwd = ($resident_info['pwd'] ?? 0) == 1;

// Fetch all PWD applications submitted by this user
$my_apps_stmt = $db->prepare("SELECT * FROM pwd_applications WHERE user_id = :uid ORDER BY created_at DESC");
$my_apps_stmt->bindParam(':uid', $_SESSION['user_id']);
$my_apps_stmt->execute();
$my_pwd_apps = $my_apps_stmt->fetchAll(PDO::FETCH_ASSOC);

// User initials & avatar
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) $initials .= strtoupper(substr($part, 0, 1));
$initials = substr($initials, 0, 2);

$avatar_url = '';
$user_query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $_SESSION['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data && !empty($user_data['profile_picture']) && file_exists($user_data['profile_picture'])) {
    $avatar_url = $user_data['profile_picture'];
}

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issuance of PWD Identification Card & Purchase Booklet - CSWDO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
    <style>
        .charter-hero {
            background: linear-gradient(135deg, #1A237E 0%, #283593 50%, #0D47A1 100%);
            border-radius: var(--radius-card);
            padding: 32px 36px;
            color: white;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .charter-hero h1 { font-size: 1.65rem; font-weight: 800; margin-bottom: 8px; }
        .charter-hero p { font-size: 0.92rem; opacity: 0.92; max-width: 780px; line-height: 1.6; }

        .service-info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 22px 26px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        .info-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .info-table th { text-align: left; padding: 8px 12px; width: 25%; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .info-table td { padding: 8px 12px; color: var(--text-primary); font-weight: 600; border-bottom: 1px solid var(--border-color); }

        .tabs-header { display: flex; gap: 8px; border-bottom: 2px solid var(--border-color); margin-bottom: 22px; overflow-x: auto; }
        .tab-btn {
            padding: 12px 18px;
            border: none;
            background: none;
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover { color: #1A237E; }
        .tab-btn.active { color: #1A237E; }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #1A237E;
            border-radius: 3px 3px 0 0;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease forwards; }

        .charter-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            background: var(--bg-card);
            border-radius: var(--radius-element);
            overflow: hidden;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .charter-table th {
            background: #1A237E;
            color: white;
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        .charter-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            color: var(--text-primary);
            line-height: 1.5;
        }
        .charter-table tr:hover td { background: rgba(26, 35, 126, 0.03); }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: #1A237E;
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-action:hover { background: #0D47A1; transform: translateY(-2px); }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 700; font-size: 0.84rem; color: var(--text-primary); margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = "PWD Welfare & Support Services";
            $banner_subtitle = "Person with Disability (PWD) Identification Card, Medicine Booklets & Specialized Assistance";
            $banner_icon = "fa-wheelchair";
            $banner_actions = [
                ["label" => "Book Appointment", "url" => "appointments.php", "icon" => "fa-calendar-plus", "type" => "primary"],
                ["label" => "Printable Forms", "url" => "print-forms.php", "icon" => "fa-print", "type" => "secondary"]
            ];
            include __DIR__ . "/includes/portal-banner.php";
            ?>

            <?php if ($is_verified_pwd): ?>
            <div style="background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; border-radius: var(--radius-card); padding: 16px 22px; margin-bottom: 22px; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm);">
                <i class="fas fa-wheelchair" style="font-size: 1.5rem; flex-shrink: 0;"></i>
                <div style="font-size: 0.88rem; line-height: 1.5;">
                    <strong style="font-size: 0.95rem; display: block; margin-bottom: 2px;">Beripikadong PWD Member:</strong>
                    Aktibo ang iyong rehistro bilang Person with Disability sa Palayan City. May karapatan ka sa 20% statutory discount at 12% VAT exemption alinsunod sa Republic Act No. 10754.
                </div>
            </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <div class="charter-hero">
                <span class="hero-badge"><i class="fas fa-shield-halved"></i> CSWDO Official Citizen's Charter</span>
                <h1>Issuance of Person with Disability Identification Card (PWD ID) & Purchase Booklet</h1>
                <p>Opisyal na serbisyo ng Pamahalaang Lungsod ng Palayan para sa pagrerehistro at pag-iisyu ng PWD ID Card at Medicine/Grocery Purchase Booklet para sa mga bonafide residente na may permanenteng kapansanan o kanilang tagapangalaga.</p>
            </div>

            <!-- Service Information Card -->
            <div class="service-info-card">
                <table class="info-table">
                    <tr>
                        <th>Office or Division</th>
                        <td>City Social Welfare and Development Office (CSWDO) / Special Lane (Window 1)</td>
                        <th>Classification</th>
                        <td>Simple (1 Day Processing Time)</td>
                    </tr>
                    <tr>
                        <th>Type of Transaction</th>
                        <td>G2C – Government to Citizen & G2G – Government to Government</td>
                        <th>Schedule of Availability</th>
                        <td>Monday thru Friday, 8:00 am – 5:00 pm</td>
                    </tr>
                    <tr>
                        <th>Who May Avail</th>
                        <td colspan="3"><strong style="color: #1A237E;">Any bonafide resident of Palayan City with permanent disability as those with long-term physical, mental, intellectual or sensory impairments that interfere with their interactions in society and or their caregiver.</strong></td>
                    </tr>
                </table>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('tab-charter', this)"><i class="fas fa-table-list"></i> Step-by-Step Process (Citizen's Charter)</button>
                <button class="tab-btn" onclick="switchTab('tab-apply', this)"><i class="fas fa-file-signature"></i> Apply Online (PWD ID & Booklet)</button>
                <button class="tab-btn" onclick="switchTab('tab-discounts', this)"><i class="fas fa-tags"></i> Privileges & Discounts (RA 10754)</button>
                <button class="tab-btn" onclick="switchTab('tab-apps', this)"><i class="fas fa-list-check"></i> My Applications (<?php echo count($my_pwd_apps); ?>)</button>
            </div>

            <!-- TAB 1: CITIZEN'S CHARTER PROCESS -->
            <div id="tab-charter" class="tab-content active">
                <div style="overflow-x: auto; margin-bottom: 25px;">
                    <table class="charter-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Client Steps</th>
                                <th style="width: 30%;">Agency Action</th>
                                <th style="width: 12%;">Fees to be Paid</th>
                                <th style="width: 15%;">Processing Time</th>
                                <th style="width: 18%;">Person Responsible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1. Register on the client’s log book</strong><br><small style="color: var(--text-muted);">Special lane in Window 1. (Register name, address, gender, contact number, and purpose of visit)</small></td>
                                <td>Assist the client in administering client’s logbook.</td>
                                <td><span style="color:#2e7d32; font-weight:700;">None (FREE)</span></td>
                                <td>1 Minute</td>
                                <td>Any CSWDO staff (CSWDO)</td>
                            </tr>
                            <tr>
                                <td><strong>2. Submit the complete documentary requirements</strong><br><small style="color: var(--text-muted);">Medical Certificate, Barangay Certificate of Residency, Valid ID, 1x1 photo.</small></td>
                                <td>Receive & Review required documents and check for completeness.</td>
                                <td><span style="color:#2e7d32; font-weight:700;">None</span></td>
                                <td>2 Minutes</td>
                                <td>PWD Focal Person (CSWDO)</td>
                            </tr>
                            <tr>
                                <td><strong>3. Fill up / administer Phil. Registration form for PWD</strong><br><small style="color: var(--text-muted);">Affix signature or thumb mark to the PWD Identification Card.</small></td>
                                <td>Assist Client in filling up registry Form and affixing signature or thumb mark to the PWD Identification Card.</td>
                                <td><span style="color:#2e7d32; font-weight:700;">None</span></td>
                                <td>10 Minutes</td>
                                <td>PWD Focal Person (CSWDO)</td>
                            </tr>
                            <tr>
                                <td><strong>4. Waiting for approval and release</strong><br><small style="color: var(--text-muted);">Receive official PWD Identification Card & Purchase Booklet.</small></td>
                                <td>Approval of PWD ID and Endorsed Purchase Booklet to CMO for signature of the City Mayor and Released the Booklet.</td>
                                <td><span style="color:#2e7d32; font-weight:700;">None</span></td>
                                <td>10 Minutes</td>
                                <td>Focal Person (CSWDO)</td>
                            </tr>
                            <tr style="background: rgba(26, 35, 126, 0.05); font-weight: 700;">
                                <td colspan="2" style="text-align: right;">TOTAL PROCESSING TIME:</td>
                                <td><span style="color:#2e7d32;">None (FREE)</span></td>
                                <td colspan="2"><span style="color:#1A237E;">1 Day (Simple Transaction)</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-element); padding: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: 4px; font-size: 1.05rem;">Gusto mo bang magsumite online nang maaga?</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">I-upload ang iyong Medical Certificate at mga patunay upang maihanda ng CSWDO Window 1 ang iyong PWD ID at Booklet bago ka pumunta.</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="print-forms.php?form=pwd" class="btn-action" style="background: transparent; color: #1A237E; border: 1.5px solid #1A237E; text-decoration: none;" title="Print Pre-filled Official DOH PWD Form"><i class="fas fa-print"></i> I-print ang DOH PWD Form</a>
                        <button class="btn-action" onclick="switchTab('tab-apply', document.querySelectorAll('.tab-btn')[1])"><i class="fas fa-file-signature"></i> Mag-apply Online Ngayon</button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: APPLY ONLINE -->
            <div id="tab-apply" class="tab-content">
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: 30px; width: 100%; box-shadow: var(--shadow-sm);">
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">
                        <i class="fas fa-wheelchair" style="color: #1A237E;"></i> Form ng Aplikasyon: PWD ID at Purchase Booklet
                    </h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px;">
                        CSWDO Palayan City • Window 1 Special Lane • Libre (Walang Bayad)
                    </p>

                    <div id="pwdApplyAlert" style="display: none; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 18px;"></div>

                    <form id="pwdDirectForm" onsubmit="submitPwdDirect(event)" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Sino ang nag-aapply? *</label>
                            <select name="applicant_role" required>
                                <option value="person_with_disability">Ako mismo (Mismong may kapansanan)</option>
                                <option value="caregiver">Caregiver / Awtorisadong Tagapangalaga</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Uri ng Kapansanan (DOH / NCDA Classification) *</label>
                            <select name="disability_type" required>
                                <option value="Orthopedic / Physical Disability">Orthopedic / Physical Disability (Kapansanan sa Pagkilos)</option>
                                <option value="Visual Impairment">Visual Impairment (Kapansanan sa Paningin)</option>
                                <option value="Hearing Impairment">Hearing Impairment (Kapansanan sa Pandinig)</option>
                                <option value="Speech and Language Impairment">Speech and Language Impairment (Kapansanan sa Pagsasalita)</option>
                                <option value="Intellectual Disability">Intellectual Disability</option>
                                <option value="Learning Disability">Learning Disability</option>
                                <option value="Psychosocial / Mental Disability">Psychosocial / Mental Disability</option>
                                <option value="Chronic Illness with Disability">Chronic Illness with Disability (Malubhang Karamdaman)</option>
                                <option value="Multiple Disabilities">Multiple Disabilities (Maramihang Kapansanan)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-file-medical"></i> 1. Medical Certificate (Mula sa Doktor o City Health Officer) *</label>
                            <input type="file" name="medical_certificate" accept=".pdf,image/*" required>
                            <small style="color: var(--text-muted); font-size: 0.76rem;">PDF o Image (Max 5MB). Nilagdaan ng lisensyadong manggagamot bilang patunay ng kapansanan.</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> 2. Valid ID o Barangay Certificate of Residency</label>
                            <input type="file" name="valid_id" accept=".pdf,image/*">
                            <small style="color: var(--text-muted); font-size: 0.76rem;">Patunay ng paninirahan sa Palayan City.</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-camera"></i> 3. 1x1 o 2x2 ID Photo (Para sa PWD ID at Booklet)</label>
                            <input type="file" name="id_picture" accept="image/*">
                            <small style="color: var(--text-muted); font-size: 0.76rem;">Maaari ring kumuha ng litrato sa CSWDO Window 1.</small>
                        </div>

                        <div class="form-group">
                            <label>Karagdagang Impormasyon ukol sa Kondisyon</label>
                            <textarea name="details" rows="2" placeholder="Ilarawan ang kondisyon ng kapansanan o iba pang tala..."></textarea>
                        </div>

                        <button type="submit" id="pwdDirectBtn" class="btn-action" style="width: 100%; justify-content: center; padding: 12px; font-size: 0.95rem;">
                            <i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 1
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 3: STATUTORY DISCOUNTS -->
            <div id="tab-discounts" class="tab-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-element); padding: 24px;">
                        <h3 style="color: var(--text-primary); font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">
                            <i class="fas fa-prescription-bottle-medical" style="color: #1A237E;"></i> 20% Discount + 12% VAT Exemption
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                            Sa ilalim ng RA 10754, ang mga may hawak ng PWD ID at Purchase Booklet ay may karapatan sa 20% discount at 12% VAT exemption sa mga gamot, serbisyong medikal, at mga bilihin.
                        </p>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-element); padding: 24px;">
                        <h3 style="color: var(--text-primary); font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">
                            <i class="fas fa-utensils" style="color: #004D40;"></i> 20% Dining & Recreation Benefits
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                            20% diskwento sa mga restaurant, hotel, sinehan, at recreational facilities sa buong bansa gamit ang opisyal na PWD ID.
                        </p>
                    </div>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-element); padding: 24px;">
                        <h3 style="color: var(--text-primary); font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">
                            <i class="fas fa-bus" style="color: #0288D1;"></i> 20% Transportation Discount
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                            Bawas sa pamasahe sa lahat ng pampublikong transportasyon (lupa, dagat, at himpapawid) para sa rehistradong PWD.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MY APPLICATIONS -->
            <div id="tab-apps" class="tab-content">
                <?php if (empty($my_pwd_apps)): ?>
                <div style="background: var(--bg-card); border-radius: var(--radius-element); border: 1px solid var(--border-color); padding: 45px 20px; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-folder-open" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; display: block;"></i>
                    <h4 style="color: var(--text-primary); margin-bottom: 6px;">Wala pang Naisusumiteng PWD Request</h4>
                    <p style="font-size: 0.85rem; max-width: 480px; margin: 0 auto 16px;">Maaari kang mag-apply para sa PWD ID at Purchase Booklet gamit ang button sa ibaba.</p>
                    <button class="btn-action" onclick="switchTab('tab-apply', document.querySelectorAll('.tab-btn')[1])"><i class="fas fa-plus"></i> Mag-apply Ngayon</button>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px;">
                    <?php foreach ($my_pwd_apps as $app): ?>
                    <?php
                        $status_colors = [
                            'submitted' => ['bg' => 'rgba(2,136,209,0.12)', 'text' => '#0288D1', 'icon' => 'fa-paper-plane', 'label' => 'Submitted / Window 1 Review'],
                            'reviewing' => ['bg' => 'rgba(237,108,2,0.12)', 'text' => '#ed6c02', 'icon' => 'fa-clock', 'label' => 'Endorsed to Mayor (CMO)'],
                            'approved'  => ['bg' => 'rgba(46,125,50,0.12)', 'text' => '#2e7d32', 'icon' => 'fa-check-circle', 'label' => 'Approved (Ready for Release)'],
                            'released'  => ['bg' => 'rgba(0,121,107,0.12)', 'text' => '#00796b', 'icon' => 'fa-box-open', 'label' => 'Released PWD ID & Booklet'],
                            'rejected'  => ['bg' => 'rgba(211,47,47,0.12)', 'text' => '#d32f2f', 'icon' => 'fa-times-circle', 'label' => 'Did Not Qualify']
                        ];
                        $st = $status_colors[$app['status']] ?? $status_colors['submitted'];
                    ?>
                    <div style="background: var(--bg-card); border-radius: var(--radius-element); border: 1px solid var(--border-color); padding: 20px; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); background: var(--bg-primary); padding: 3px 8px; border-radius: 6px;">
                                <?php echo htmlspecialchars($app['tracking_number']); ?>
                            </span>
                            <span style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['text']; ?>; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
                            </span>
                        </div>
                        <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
                            PWD ID Card & Purchase Booklet
                        </h4>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px;">
                            <i class="fas fa-tag"></i> <strong>Disability:</strong> <?php echo htmlspecialchars($app['disability_type']); ?>
                        </div>
                        <?php if (!empty($app['pwd_id_number'])): ?>
                        <div style="background: rgba(46,125,50,0.08); border-left: 3px solid #2e7d32; padding: 8px 12px; border-radius: 4px; font-size: 0.82rem; color: #2e7d32; margin-bottom: 6px;">
                            <i class="fas fa-id-card"></i> <strong>PWD ID Number:</strong> <?php echo htmlspecialchars($app['pwd_id_number']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($app['booklet_number'])): ?>
                        <div style="background: rgba(0,121,107,0.08); border-left: 3px solid #00796b; padding: 8px 12px; border-radius: 4px; font-size: 0.82rem; color: #00796b; margin-bottom: 10px;">
                            <i class="fas fa-book"></i> <strong>Purchase Booklet Control #:</strong> <?php echo htmlspecialchars($app['booklet_number']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($app['status'] === 'rejected' && !empty($app['rejection_reason'])): ?>
                        <div style="background: rgba(211,47,47,0.08); border-left: 3px solid #d32f2f; padding: 8px 12px; border-radius: 4px; font-size: 0.8rem; color: #d32f2f; margin-bottom: 10px;">
                            <strong>Dahilan:</strong> <?php echo htmlspecialchars($app['rejection_reason']); ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <span>Petsa: <?php echo date('M d, Y', strtotime($app['created_at'])); ?></span>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <?php if ($app['status'] === 'approved' || $app['status'] === 'released'): ?>
                                <a href="print-claim-stub.php?type=pwd&id=<?php echo $app['application_id']; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; background: #7F0303; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 0.75rem;">
                                    <i class="fas fa-print"></i> Claim Stub
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($app['medical_certificate_path'])): ?>
                                <a href="<?php echo htmlspecialchars($app['medical_certificate_path']); ?>" target="_blank" style="color: #1A237E; text-decoration: none; font-weight: 600;"><i class="fas fa-file-medical"></i> View Med Cert</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tabId, btnElement) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        btnElement.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    }

    function submitPwdDirect(e) {
        e.preventDefault();
        const form = document.getElementById('pwdDirectForm');
        const alertBox = document.getElementById('pwdApplyAlert');
        const btn = document.getElementById('pwdDirectBtn');

        alertBox.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting to Window 1...';

        const formData = new FormData(form);

        fetch('submit-pwd-application-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 1';
            if (data.success) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#e8f5e9';
                alertBox.style.color = '#2e7d32';
                alertBox.style.border = '1px solid #c8e6c9';
                alertBox.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                form.reset();
                setTimeout(() => window.location.reload(), 2000);
            } else {
                alertBox.style.display = 'block';
                alertBox.style.background = '#ffebee';
                alertBox.style.color = '#c62828';
                alertBox.style.border = '1px solid #ffcdd2';
                alertBox.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Isumite ang Aplikasyon sa CSWDO Window 1';
            alertBox.style.display = 'block';
            alertBox.style.background = '#ffebee';
            alertBox.style.color = '#c62828';
            alertBox.style.border = '1px solid #ffcdd2';
            alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Pakisubukan muli.';
        });
    }

    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    themeToggle.addEventListener('click', () => {
        const isDark = html.getAttribute('data-theme') === 'dark';
        const nextTheme = isDark ? 'light' : 'dark';
        html.setAttribute('data-theme', nextTheme);
        themeToggle.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        document.cookie = `dark_mode=${nextTheme === 'dark' ? 'enabled' : 'disabled'}; path=/; max-age=31536000`;
    });
    </script>
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>
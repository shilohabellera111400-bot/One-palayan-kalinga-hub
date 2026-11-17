<?php
// staff/qr-scanner.php - Social Services On-Site QR Scanner & Check-in Desk
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment-helper.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isStaff() && !isAdmin()) {
    redirect('../dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Get staff info
$staff_id_query = "SELECT s.*, u.email, u.profile_picture FROM staff s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = :uid";
$staff_stmt = $db->prepare($staff_id_query);
$staff_stmt->execute([':uid' => $_SESSION['user_id']]);
$staff_info = $staff_stmt->fetch(PDO::FETCH_ASSOC);
$staff_id = $staff_info['id'] ?? 0;

// Assigned categories
$cat_query = "SELECT DISTINCT sc.category_name 
               FROM staff_service_assignments ssa
               JOIN service_categories sc ON ssa.category_id = sc.category_id
               WHERE ssa.staff_id = :staff_id AND ssa.is_active = 1";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute([':staff_id' => $staff_id]);
$assigned_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Initials
$initials = '';
$name_parts = explode(' ', $_SESSION['full_name']);
foreach($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
$profile_picture = $staff_info['profile_picture'] ?? '';
$avatar_url = !empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture) ? '../' . $profile_picture : '';

// Recent Check-Ins today
$today = date('Y-m-d');
$recent_stmt = $db->prepare("
    SELECT a.*, s.service_name, u.first_name, u.last_name, r.barangay, u.contact_number
    FROM appointments a
    JOIN services s ON a.service_id = s.service_id
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN residents r ON u.user_id = r.user_id
    WHERE a.appointment_date = :today AND a.status IN ('checked_in', 'in_progress', 'completed')
    ORDER BY a.checked_in_at DESC, a.appointment_time DESC
    LIMIT 8
");
$recent_stmt->execute([':today' => $today]);
$recent_checkins = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's summary stats
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_today,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as expected_today,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_today,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_today,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_today
    FROM appointments 
    WHERE appointment_date = :today
");
$stats_stmt->execute([':today' => $today]);
$today_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<?php
$page_title = 'QR Scanner & Check-in Desk - Staff Portal';
$active_nav = 'scanner';
require_once __DIR__ . '/includes/staff-head.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
:root {
    --card-bg: var(--ref-card);
    --text-primary: var(--ref-text);
    --text-muted: var(--ref-muted);
    --border-color: var(--ref-border);
    --maroon: var(--ref-maroon);
    --radius-card: 20px;
}
        .scanner-grid {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 24px;
            margin-bottom: 30px;
        }
        @media (max-width: 1024px) {
            .scanner-grid { grid-template-columns: 1fr; }
        }

        .scanner-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            padding: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .camera-viewport-wrap {
            position: relative;
            background: #0F172A;
            border-radius: 14px;
            overflow: hidden;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
            margin-bottom: 16px;
        }

        #reader {
            width: 100% !important;
            border: none !important;
        }
        #reader video {
            border-radius: 12px;
            object-fit: cover;
        }

        .manual-input-box {
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }
        .manual-input-box input {
            flex: 1;
            font-family: 'Courier Prime', monospace;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-element);
            background: var(--bg-primary);
            color: var(--text-primary);
            text-transform: uppercase;
        }
        .manual-input-box input:focus {
            outline: none;
            border-color: var(--maroon);
        }
        .btn-lookup {
            background: var(--maroon);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-element);
            padding: 10px 18px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-lookup:hover {
            background: var(--maroon-light);
        }

        /* Result Panel */
        .result-empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .result-empty i {
            font-size: 3.5rem;
            opacity: 0.3;
            margin-bottom: 14px;
            display: block;
        }

        .client-profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        .client-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--maroon);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .client-info h3 {
            margin: 0 0 4px 0;
            font-size: 1.15rem;
            color: var(--text-primary);
        }
        .client-info p {
            margin: 0;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-color);
            font-size: 0.84rem;
        }
        .detail-label { color: var(--text-muted); font-weight: 500; }
        .detail-val { color: var(--text-primary); font-weight: 700; text-align: right; }

        .action-tray {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-action-main {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-action-checkin { background: #16A34A; color: white; }
        .btn-action-checkin:hover { background: #15803D; }
        .btn-action-start { background: #2563EB; color: white; }
        .btn-action-start:hover { background: #1D4ED8; }
        .btn-action-complete { background: var(--maroon); color: white; }
        .btn-action-complete:hover { background: var(--maroon-light); }

        .stats-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .stats-strip { grid-template-columns: repeat(2, 1fr); }
        }
        .strip-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 12px 14px;
            text-align: center;
        }
        .strip-card .val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
        }
        .strip-card .lbl {
            font-size: 0.68rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
        }

        .recent-table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 8px 12px;
            background: var(--badge-bg);
            text-align: left;
        }
        .recent-table td {
            font-size: 0.8rem;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
<div class="app-container">
        <!-- Modern Staff Sidebar -->
        <?php require_once __DIR__ . '/includes/staff-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Unified Staff Header Bar -->
            <?php require_once __DIR__ . '/includes/staff-header.php'; ?>

            <!-- Elevated Big Card Container -->
            <div class="staff-big-card">
                <!-- Universal Top Hero Banner -->
            <?php
            $banner_title = 'QR Check-In & Intake Desk';
            $banner_subtitle = 'Scan citizen digital appointment passes or search reference numbers for immediate counter intake';
            $banner_icon = 'fa-qrcode';
            $banner_actions = [
                ['label' => 'Appointment Queue', 'url' => 'manage-appointments.php', 'icon' => 'fa-calendar-check', 'type' => 'primary'],
                ['label' => 'Slot Settings', 'url' => 'appointment-settings.php', 'icon' => 'fa-sliders-h', 'type' => 'secondary']
            ];
            include __DIR__ . '/../includes/portal-banner.php';
            ?>

            <!-- Two Column Scanner Interface -->
            <div class="scanner-grid">
                <!-- Left Column: Camera Scanner & Manual Search -->
                <div class="scanner-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-camera" style="color: var(--maroon);"></i> Live QR Scanner
                        </h3>
                        <div id="cameraControls" style="display: flex; gap: 6px;">
                            <button type="button" id="toggleCameraBtn" class="btn-lookup" style="padding: 5px 12px; font-size: 0.75rem; background: var(--badge-bg); color: var(--text-primary);">
                                <i class="fas fa-video"></i> Toggle Camera
                            </button>
                        </div>
                    </div>

                    <div class="camera-viewport-wrap">
                        <div id="reader"></div>
                        <div id="scannerOverlayMessage" style="position: absolute; color: white; font-size: 0.84rem; text-align: center; pointer-events: none;">
                            <i class="fas fa-expand" style="font-size: 2.2rem; display: block; margin-bottom: 8px; opacity: 0.7;"></i>
                            Point camera at resident QR code
                        </div>
                    </div>

                    <div style="margin-top: 14px;">
                        <label style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">
                            <i class="fas fa-barcode"></i> Manual Reference # or Barcode Gun Input
                        </label>
                        <form id="manualLookupForm" onsubmit="handleManualLookup(event)" class="manual-input-box">
                            <input type="text" id="manualRefInput" placeholder="PAL-SOC-2026-000001" autocomplete="off" autofocus>
                            <button type="submit" class="btn-lookup" id="manualLookupBtn">
                                <i class="fas fa-search"></i> Verify
                            </button>
                        </form>
                        <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; display: block;">
                            Tip: USB Barcode / QR scanners will automatically trigger verification on scan.
                        </span>
                    </div>

                    <div id="scannerStatusAlert" style="margin-top: 14px; display: none; padding: 10px 14px; border-radius: 8px; font-size: 0.82rem;"></div>
                </div>

                <!-- Right Column: Scanned Citizen Verification Card -->
                <div class="scanner-card" id="verificationContainer">
                    <div class="result-empty" id="emptyResultBox">
                        <i class="fas fa-id-card"></i>
                        <h4 style="margin: 0 0 6px 0; color: var(--text-primary);">Awaiting Citizen Scan</h4>
                        <p style="font-size: 0.82rem; margin: 0;">Scan an appointment slip or type a Reference Number on the left to review client record, assign windows, and complete intake.</p>
                    </div>

                    <!-- Populated Scanned Card (Hidden initially) -->
                    <div id="activeScannedCard" style="display: none;">
                        <div class="client-profile-header">
                            <div class="client-avatar" id="clientAvatarInitials">--</div>
                            <div class="client-info" style="flex: 1;">
                                <h3 id="clientFullName">Resident Name</h3>
                                <p><i class="fas fa-map-marker-alt" style="color: var(--maroon);"></i> <span id="clientBarangay">Barangay</span> • <i class="fas fa-phone"></i> <span id="clientPhone">0900-000-0000</span></p>
                                <div style="margin-top: 6px; display: flex; gap: 6px; align-items: center;">
                                    <span id="appointmentStatusBadge"></span>
                                    <span id="dateMismatchBadge" style="display: none; background: #FEE2E2; color: #DC2626; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 50px;">
                                        <i class="fas fa-calendar-times"></i> Scheduled for another day!
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">Reference Number</span>
                            <span class="detail-val" id="refNumDisplay" style="font-family: 'Courier Prime', monospace; color: var(--maroon); font-size: 0.95rem;"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Social Service</span>
                            <span class="detail-val" id="serviceNameDisplay"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Department / Division</span>
                            <span class="detail-val" id="deptDisplay"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Scheduled Date & Time</span>
                            <span class="detail-val" id="schedDateTimeDisplay"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Purpose / Concern</span>
                            <span class="detail-val" id="purposeDisplay" style="max-width: 250px; font-weight: 500;"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Uploaded Supporting Docs</span>
                            <span class="detail-val" id="docsCountDisplay">0 files</span>
                        </div>

                        <!-- Intake Workflow Form -->
                        <div style="margin-top: 16px; background: var(--bg-subtle); padding: 14px; border-radius: 10px; border: 1px solid var(--border-color);">
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 0.78rem; font-weight: 700; margin-bottom: 4px; color: var(--text-primary);">
                                    <i class="fas fa-door-open" style="color: var(--maroon);"></i> Assign Intake Window / Desk Counter
                                </label>
                                <select id="windowAssignmentSelect" style="width: 100%; padding: 8px 10px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.84rem; background: var(--bg-primary); color: var(--text-primary);">
                                    <option value="Window 1 - Intake / Assessment">Window 1 - Intake / Assessment</option>
                                    <option value="Window 2 - Social Worker Interview">Window 2 - Social Worker Interview</option>
                                    <option value="Window 3 - Document Verification">Window 3 - Document Verification</option>
                                    <option value="Window 4 - Cashier / Disbursing">Window 4 - Cashier / Disbursing</option>
                                    <option value="Window 5 - Senior Citizen / PWD Express">Window 5 - Senior Citizen / PWD Express</option>
                                    <option value="Executive / CSWDO Head Office">Executive / CSWDO Head Office</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.78rem; font-weight: 700; margin-bottom: 4px; color: var(--text-primary);">
                                    <i class="fas fa-comment-dots" style="color: var(--maroon);"></i> Intake / Transaction Notes (Optional)
                                </label>
                                <input type="text" id="intakeNotesInput" placeholder="E.g. Verified with Senior Citizen ID, routed for urgent medical assistance." style="width: 100%; padding: 8px 10px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.84rem; background: var(--bg-primary); color: var(--text-primary);">
                            </div>
                        </div>

                        <!-- Actions Tray -->
                        <div class="action-tray">
                            <button type="button" id="btnCheckIn" onclick="executeIntakeAction('checkin')" class="btn-action-main btn-action-checkin">
                                <i class="fas fa-check-double"></i> Confirm Arrival & Check-In Resident
                            </button>
                            <button type="button" id="btnStartService" onclick="executeIntakeAction('start_service')" class="btn-action-main btn-action-start" style="display: none;">
                                <i class="fas fa-play"></i> Start Interview / In-Progress
                            </button>
                            <button type="button" id="btnCompleteService" onclick="executeIntakeAction('complete_service')" class="btn-action-main btn-action-complete" style="display: none;">
                                <i class="fas fa-flag-checkered"></i> Complete Transaction & Resolve
                            </button>
                            
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <a id="btnViewSlip" href="#" target="_blank" class="btn-lookup" style="flex: 1; justify-content: center; background: var(--badge-bg); color: var(--text-primary); text-decoration: none; font-size: 0.8rem;">
                                    <i class="fas fa-print"></i> View Digital Slip
                                </a>
                                <button type="button" onclick="executeIntakeAction('mark_no_show')" class="btn-lookup" style="background: rgba(239, 68, 68, 0.12); color: #DC2626; font-size: 0.8rem;">
                                    <i class="fas fa-user-slash"></i> No-Show
                                </button>
                                <button type="button" onclick="resetActiveCard()" class="btn-lookup" style="background: var(--badge-bg); color: var(--text-muted); font-size: 0.8rem;">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Check-Ins Today -->
            <div class="scanner-card">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list-check" style="color: var(--maroon);"></i> Intake Activity Log (Today: <?php echo date('F d, Y'); ?>)
                </h3>
                <div style="overflow-x: auto;">
                    <table class="recent-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Check-in Time</th>
                                <th>Reference #</th>
                                <th>Resident</th>
                                <th>Service</th>
                                <th>Assigned Window</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_checkins)): ?>
                                <?php foreach($recent_checkins as $rc): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo !empty($rc['checked_in_at']) ? date('h:i A', strtotime($rc['checked_in_at'])) : date('h:i A', strtotime($rc['appointment_time'])); ?></strong>
                                    </td>
                                    <td style="font-family: 'Courier Prime', monospace; font-weight: 700; color: var(--maroon);">
                                        <?php echo htmlspecialchars($rc['reference_number'] ?: 'PAL-SOC-'.$rc['appointment_id']); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rc['first_name'] . ' ' . $rc['last_name']); ?></strong>
                                        <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($rc['barangay'] ?: 'Palayan City'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($rc['service_name']); ?></td>
                                    <td><span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($rc['window_assignment'] ?: 'Window 1'); ?></span></td>
                                    <td><?php echo getAppointmentBadge($rc['status']); ?></td>
                                    <td>
                                        <button type="button" onclick="loadAppointmentIntoCard('<?php echo htmlspecialchars($rc['reference_number']); ?>')" class="btn-lookup" style="padding: 4px 10px; font-size: 0.72rem; background: var(--badge-bg); color: var(--text-primary);">
                                            <i class="fas fa-arrow-up-right-from-square"></i> Open
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px 10px;">
                                        No residents checked in yet today.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> <!-- Close staff-big-card -->
    </main>
</div> <!-- Close app-container -->

    <script>
    // Audio synthesizer beep for successful QR scan
    function playBeepSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'sine';
            osc.frequency.value = 880; // A5 note
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.18);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.18);
        } catch(e) {
            console.log('Audio not allowed without gesture');
        }
    }

    let activeAppointmentData = null;
    let html5QrScanner = null;
    let isCameraRunning = false;

    // Show scanner alerts
    function showScannerAlert(message, type = 'info') {
        const el = document.getElementById('scannerStatusAlert');
        el.style.display = 'block';
        el.textContent = message;
        if (type === 'success') {
            el.style.background = '#ECFDF5';
            el.style.color = '#065F46';
            el.style.border = '1px solid #A7F3D0';
        } else if (type === 'error') {
            el.style.background = '#FEF2F2';
            el.style.color = '#991B1B';
            el.style.border = '1px solid #FECACA';
        } else {
            el.style.background = '#EFF6FF';
            el.style.color = '#1E40AF';
            el.style.border = '1px solid #BFDBFE';
        }
    }

    // Lookup Appointment
    function lookupAppointment(query) {
        if (!query) return;
        showScannerAlert('Verifying appointment pass: ' + query + '...', 'info');

        fetch('checkin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'lookup', query: query })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.appointment) {
                playBeepSound();
                showScannerAlert('Appointment verified successfully!', 'success');
                renderScannedAppointment(data.appointment, data.badge_html);
            } else {
                showScannerAlert(data.message || 'No appointment found matching code.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showScannerAlert('Network error while querying appointment.', 'error');
        });
    }

    function handleManualLookup(e) {
        e.preventDefault();
        const val = document.getElementById('manualRefInput').value.trim();
        if (val) lookupAppointment(val);
    }

    function loadAppointmentIntoCard(refNumber) {
        document.getElementById('manualRefInput').value = refNumber;
        lookupAppointment(refNumber);
    }

    // Render Appointment Details
    function renderScannedAppointment(appt, badgeHtml) {
        activeAppointmentData = appt;
        document.getElementById('emptyResultBox').style.display = 'none';
        document.getElementById('activeScannedCard').style.display = 'block';

        const fullName = (appt.first_name + ' ' + appt.last_name).trim();
        document.getElementById('clientFullName').textContent = fullName;
        document.getElementById('clientBarangay').textContent = appt.barangay || 'Palayan City';
        document.getElementById('clientPhone').textContent = appt.contact_number || 'N/A';

        // Initials
        const parts = fullName.split(' ');
        let inits = '';
        parts.forEach(p => { if (p) inits += p[0].toUpperCase(); });
        document.getElementById('clientAvatarInitials').textContent = inits.substring(0, 2);

        document.getElementById('appointmentStatusBadge').innerHTML = badgeHtml;
        document.getElementById('refNumDisplay').textContent = appt.reference_number || ('PAL-SOC-' + appt.appointment_id);
        document.getElementById('serviceNameDisplay').textContent = appt.service_name;
        document.getElementById('deptDisplay').textContent = appt.office_department || 'Social Welfare & Development';
        document.getElementById('schedDateTimeDisplay').textContent = appt.appointment_date + ' at ' + appt.appointment_time;
        document.getElementById('purposeDisplay').textContent = appt.purpose || 'None specified';
        document.getElementById('docsCountDisplay').textContent = (appt.documents_count || 0) + ' attached';

        document.getElementById('btnViewSlip').href = '../appointment-slip.php?ref=' + encodeURIComponent(appt.reference_number || appt.appointment_id);

        if (appt.window_assignment) {
            document.getElementById('windowAssignmentSelect').value = appt.window_assignment;
        }

        // Check date mismatch
        const todayStr = '<?php echo date('Y-m-d'); ?>';
        const mismatchEl = document.getElementById('dateMismatchBadge');
        if (appt.appointment_date !== todayStr) {
            mismatchEl.style.display = 'inline-flex';
            mismatchEl.innerHTML = `<i class="fas fa-calendar-times"></i> Scheduled for ${appt.appointment_date}`;
        } else {
            mismatchEl.style.display = 'none';
        }

        // Configure buttons by status
        const btnCheckIn = document.getElementById('btnCheckIn');
        const btnStartService = document.getElementById('btnStartService');
        const btnCompleteService = document.getElementById('btnCompleteService');

        btnCheckIn.style.display = 'none';
        btnStartService.style.display = 'none';
        btnCompleteService.style.display = 'none';

        if (['pending', 'for_review', 'confirmed', 'rescheduled'].includes(appt.status)) {
            btnCheckIn.style.display = 'inline-flex';
        } else if (appt.status === 'checked_in') {
            btnStartService.style.display = 'inline-flex';
        } else if (appt.status === 'in_progress') {
            btnCompleteService.style.display = 'inline-flex';
        }
    }

    function resetActiveCard() {
        activeAppointmentData = null;
        document.getElementById('emptyResultBox').style.display = 'block';
        document.getElementById('activeScannedCard').style.display = 'none';
        document.getElementById('manualRefInput').value = '';
    }

    // Execute Intake Action
    function executeIntakeAction(action) {
        if (!activeAppointmentData) return;

        const windowAssigned = document.getElementById('windowAssignmentSelect').value;
        const notes = document.getElementById('intakeNotesInput').value.trim();

        fetch('checkin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                appointment_id: activeAppointmentData.appointment_id,
                window: windowAssigned,
                notes: notes
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showScannerAlert(data.message, 'success');
                if (data.appointment) {
                    renderScannedAppointment(data.appointment, data.badge_html);
                }
            } else {
                showScannerAlert(data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showScannerAlert('Action execution failed due to network error.', 'error');
        });
    }

    // Camera scanner initialization
    function initCameraScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            console.warn('Html5Qrcode library not loaded');
            return;
        }

        html5QrScanner = new Html5Qrcode("reader");
        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            console.log(`Scan result: ${decodedText}`);
            lookupAppointment(decodedText);
        };

        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        html5QrScanner.start({ facingMode: "environment" }, config, qrCodeSuccessCallback)
            .then(() => {
                isCameraRunning = true;
                const msg = document.getElementById('scannerOverlayMessage');
                if (msg) msg.style.display = 'none';
            })
            .catch(err => {
                console.log("Camera start error, probably no camera attached or permission denied: ", err);
                const msg = document.getElementById('scannerOverlayMessage');
                if (msg) msg.innerHTML = '<i class="fas fa-video-slash" style="font-size:2rem;display:block;margin-bottom:8px;"></i> Camera scanner inactive or permission denied.<br>Use manual reference input or barcode scanner.';
            });
    }

    document.getElementById('toggleCameraBtn')?.addEventListener('click', function() {
        if (!html5QrScanner) {
            initCameraScanner();
            return;
        }
        if (isCameraRunning) {
            html5QrScanner.stop().then(() => {
                isCameraRunning = false;
                const msg = document.getElementById('scannerOverlayMessage');
                if (msg) msg.style.display = 'block';
            });
        } else {
            initCameraScanner();
        }
    });

    // Auto-start camera if available
    window.addEventListener('load', function() {
        initCameraScanner();
    });

    </script>
    <?php require_once __DIR__ . '/includes/staff-footer.php'; ?>
</body>
</html>

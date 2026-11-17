<?php
// appointment-slip.php
// Official Digital & Printable Appointment Slip for Palayan City Social Service System

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/appointment-helper.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

$ref = isset($_GET['ref']) ? sanitize($_GET['ref']) : '';
$id  = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($ref) && $id <= 0) {
    redirect('my-appointments.php?error=Appointment reference is missing');
}

// Fetch appointment details
if (!empty($ref)) {
    $stmt = $db->prepare("SELECT a.*, s.service_name, s.requirements, s.category_id,
                                 u.first_name, u.last_name, r.middle_name, u.email, u.contact_number,
                                 r.barangay, r.street,
                                 st.first_name as staff_first, st.last_name as staff_last, st.position as staff_pos
                          FROM appointments a
                          LEFT JOIN services s ON a.service_id = s.service_id
                          JOIN users u ON a.user_id = u.user_id
                          LEFT JOIN residents r ON u.user_id = r.user_id
                          LEFT JOIN staff st ON a.staff_id = st.id
                          WHERE a.reference_number = :ref");
    $stmt->execute([':ref' => $ref]);
} else {
    $stmt = $db->prepare("SELECT a.*, s.service_name, s.requirements, s.category_id,
                                 u.first_name, u.last_name, r.middle_name, u.email, u.contact_number,
                                 r.barangay, r.street,
                                 st.first_name as staff_first, st.last_name as staff_last, st.position as staff_pos
                          FROM appointments a
                          LEFT JOIN services s ON a.service_id = s.service_id
                          JOIN users u ON a.user_id = u.user_id
                          LEFT JOIN residents r ON u.user_id = r.user_id
                          LEFT JOIN staff st ON a.staff_id = st.id
                          WHERE a.appointment_id = :id");
    $stmt->execute([':id' => $id]);
}

$appt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appt) {
    if (isStaff() || isAdmin()) {
        redirect('staff/manage-appointments.php?error=Appointment not found');
    } else {
        redirect('my-appointments.php?error=Appointment not found');
    }
}

// Security authorization:
// Residents can only view their own appointment slips.
if (!isStaff() && !isAdmin() && $appt['user_id'] != $_SESSION['user_id']) {
    redirect('my-appointments.php?error=Unauthorized access');
}

$reference_number = $appt['reference_number'] ?: ('PAL-SOC-' . date('Y', strtotime($appt['created_at'])) . '-' . str_pad($appt['appointment_id'], 6, '0', STR_PAD_LEFT));
$client_name = trim($appt['first_name'] . ' ' . ($appt['middle_name'] ? $appt['middle_name'] . ' ' : '') . $appt['last_name']);
$date_fmt = date('F d, Y (l)', strtotime($appt['appointment_date']));
$time_fmt = date('h:i A', strtotime($appt['appointment_time']));
$office_dept = $appt['office_department'] ?: 'City Social Welfare & Development Office';
$location = 'Social Service Center, Ground Floor, Palayan City Hall Complex';
$qr_payload = $appt['qr_token'] ? ($reference_number . '|' . $appt['qr_token']) : $reference_number;

$offices = getSocialServiceOffices();
if (isset($offices[$office_dept]['location'])) {
    $location = $offices[$office_dept]['location'];
}

$assigned_staff = (!empty($appt['staff_first'])) 
    ? ($appt['staff_first'] . ' ' . $appt['staff_last'] . ' (' . ($appt['staff_pos'] ?? 'Officer') . ')') 
    : 'Assigned Duty Officer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Slip - <?php echo htmlspecialchars($reference_number); ?> - Palayan City</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Courier+Prime:wght@700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php include_once __DIR__ . '/includes/pwa-meta.php'; ?>
    <style>
        :root {
            --maroon: #7B1113;
            --maroon-dark: #5A0202;
            --gold: #C9A86C;
            --bg-page: #F1F5F9;
            --card-bg: #FFFFFF;
            --text-dark: #0F172A;
            --text-muted: #475569;
            --border-color: #CBD5E1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            padding: 30px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Top Action Bar */
        .actions-bar {
            width: 100%;
            max-width: 800px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-back {
            background: #FFFFFF;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }
        .btn-back:hover {
            background: #F8FAFC;
        }

        .btn-print {
            background: var(--maroon);
            color: white;
            box-shadow: 0 4px 12px rgba(123, 17, 19, 0.25);
        }
        .btn-print:hover {
            background: var(--maroon-dark);
            transform: translateY(-1px);
        }

        /* Slip Paper Layout */
        .slip-container {
            width: 100%;
            max-width: 800px;
            background: #FFFFFF;
            border: 2px solid #0F172A;
            border-radius: 12px;
            padding: 36px 44px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        /* Watermark */
        .slip-container::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 380px;
            height: 380px;
            background-image: url('images/palayan-city.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.04;
            pointer-events: none;
            z-index: 0;
        }

        .slip-content {
            position: relative;
            z-index: 1;
        }

        /* Header with Palayan City Logo */
        .slip-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 3px double #0F172A;
            margin-bottom: 24px;
            text-align: center;
        }

        .city-logo {
            width: 84px;
            height: 84px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-text h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .header-text h1 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--maroon);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 2px 0;
        }

        .header-text h2 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0F172A;
        }

        .header-text p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Title Ribbon */
        .slip-title-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-left: 6px solid var(--maroon);
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .slip-title-box .title-main {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            color: var(--maroon);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ref-pill {
            font-family: 'Courier Prime', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            background: #0F172A;
            color: #FFFFFF;
            padding: 5px 14px;
            border-radius: 6px;
            letter-spacing: 0.05em;
        }

        /* Main Details & QR Grid */
        .slip-body-grid {
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 28px;
            margin-bottom: 28px;
            align-items: start;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table tr {
            border-bottom: 1px solid #E2E8F0;
        }

        .details-table tr:last-child {
            border-bottom: none;
        }

        .details-table th {
            text-align: left;
            width: 150px;
            padding: 9px 8px 9px 0;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            vertical-align: top;
            font-weight: 600;
        }

        .details-table td {
            padding: 9px 0;
            font-size: 0.9rem;
            color: #0F172A;
            font-weight: 500;
            vertical-align: top;
        }

        .highlight-text {
            font-size: 1rem;
            font-weight: 700;
            color: var(--maroon);
        }

        /* QR Code Container */
        .qr-card {
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .qr-card #qrcode {
            background: white;
            padding: 10px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 10px;
        }

        .qr-card p {
            font-size: 0.7rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Instructions & Requirements */
        .slip-notes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .note-box h4 {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--maroon);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .note-box ul {
            padding-left: 18px;
            font-size: 0.78rem;
            color: #334155;
            line-height: 1.5;
        }

        .note-box ul li {
            margin-bottom: 4px;
        }

        .important-banner {
            background: #FEF3C7;
            border-left: 4px solid #D97706;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 0.78rem;
            color: #92400E;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Slip Footer */
        .slip-footer {
            border-top: 1px dashed var(--border-color);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 8px;
        }

        .seal-mark {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            color: var(--maroon);
        }

        /* Print Media Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .actions-bar {
                display: none !important;
            }
            .slip-container {
                border: 2px solid #000;
                box-shadow: none;
                max-width: 100%;
                padding: 24px 30px;
                border-radius: 0;
            }
            .ref-pill {
                background: #000 !important;
                color: #FFF !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .important-banner, .slip-title-box, .qr-card, .slip-notes-grid {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 768px) {
            .slip-container {
                padding: 24px 20px;
            }
            .slip-header {
                flex-direction: column;
                gap: 12px;
            }
            .slip-body-grid {
                grid-template-columns: 1fr;
            }
            .slip-notes-grid {
                grid-template-columns: 1fr;
            }
            .qr-card {
                margin: 0 auto;
                max-width: 260px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (Hidden on Print) -->
    <div class="actions-bar">
        <?php if (isStaff() || isAdmin()): ?>
            <a href="staff/manage-appointments.php" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Back to Appointments
            </a>
        <?php else: ?>
            <a href="my-appointments.php" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Back to My Appointments
            </a>
        <?php endif; ?>

        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn-action btn-back" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Appointment Slip
            </button>
        </div>
    </div>

    <!-- Official Appointment Slip Paper -->
    <div class="slip-container">
        <div class="slip-content">
            <!-- Header with Official Palayan City Logo -->
            <div class="slip-header">
                <img src="images/palayan-city.png" alt="City Government of Palayan Logo" class="city-logo">
                <div class="header-text">
                    <h3>Republic of the Philippines</h3>
                    <h1>City Government of Palayan</h1>
                    <h2><?php echo htmlspecialchars($office_dept); ?></h2>
                    <p>One Palayan-Kalinga Hub • Citizen Service Assistance Center</p>
                </div>
            </div>

            <!-- Title & Reference Pill -->
            <div class="slip-title-box">
                <div class="title-main">
                    <i class="fas fa-ticket-alt"></i> OFFICIAL APPOINTMENT SLIP
                </div>
                <div class="ref-pill">
                    <?php echo htmlspecialchars($reference_number); ?>
                </div>
            </div>

            <div class="important-banner">
                <i class="fas fa-info-circle"></i>
                <span>Please present this appointment slip or the corresponding QR code upon arrival at the Social Service Office.</span>
            </div>

            <!-- Body Grid: Details & QR Code -->
            <div class="slip-body-grid">
                <div>
                    <table class="details-table">
                        <tr>
                            <th>Client Name</th>
                            <td><strong><?php echo htmlspecialchars($client_name); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Contact / Email</th>
                            <td>
                                <?php echo htmlspecialchars($appt['contact_number'] ?: 'N/A'); ?> &bull; 
                                <?php echo htmlspecialchars($appt['email']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Barangay / Address</th>
                            <td>
                                <?php 
                                    $brgy = $appt['barangay'] ?: 'Palayan City Resident';
                                    $street = $appt['street'] ? ($appt['street'] . ', ') : '';
                                    echo htmlspecialchars($street . $brgy . ', Palayan City'); 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Requested Service</th>
                            <td><span class="highlight-text"><?php echo htmlspecialchars($appt['service_name'] ?? 'Social Welfare Service'); ?></span></td>
                        </tr>
                        <tr>
                            <th>Office / Department</th>
                            <td><?php echo htmlspecialchars($office_dept); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Appointment</th>
                            <td><strong style="color:#0F172A; font-size:0.95rem;"><?php echo htmlspecialchars($date_fmt); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Designated Time</th>
                            <td><strong style="color:var(--maroon); font-size:1rem;"><?php echo htmlspecialchars($time_fmt); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Venue / Location</th>
                            <td><?php echo htmlspecialchars($location); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><?php echo getAppointmentBadge($appt['status']); ?></td>
                        </tr>
                        <tr>
                            <th>Assigned Officer</th>
                            <td><?php echo htmlspecialchars($assigned_staff); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- QR Code Box -->
                <div class="qr-card">
                    <div id="qrcode"></div>
                    <div style="font-family:'Courier Prime', monospace; font-size:0.75rem; font-weight:700; color:#0F172A; margin-bottom:4px;">
                        <?php echo htmlspecialchars($reference_number); ?>
                    </div>
                    <p>Scannable for express check-in at City Hall Entrance / CSWDO kiosk</p>
                </div>
            </div>

            <!-- Instructions & Required Documents -->
            <div class="slip-notes-grid">
                <div class="note-box">
                    <h4><i class="fas fa-clipboard-list"></i> Important Instructions</h4>
                    <ul>
                        <li>Arrive at least <strong>10 to 15 minutes</strong> before your scheduled time.</li>
                        <li>Proceed directly to the designated social service window or reception desk.</li>
                        <li>Show this slip on your mobile phone or bring the printed paper copy.</li>
                        <li>Follow health, safety, and dress code guidelines within the City Hall complex.</li>
                    </ul>
                </div>

                <div class="note-box">
                    <h4><i class="fas fa-folder-open"></i> Documents to Bring</h4>
                    <ul>
                        <li><strong>1 Original & 1 Photocopy</strong> of Valid Government-Issued ID.</li>
                        <li>Barangay Certificate of Residency or Indigency (when applicable).</li>
                        <li>Original medical abstract, bill, or funeral contract (for AICS requests).</li>
                        <li>Proof of appointment (this printed slip or digital QR code).</li>
                    </ul>
                </div>
            </div>

            <!-- Slip Footer -->
            <div class="slip-footer">
                <div>
                    Generated on: <?php echo date('F d, Y h:i A'); ?> &bull; System Verified
                </div>
                <div class="seal-mark">
                    <i class="fas fa-shield-alt"></i> One Palayan-Kalinga Hub Official Record
                </div>
            </div>
        </div>
    </div>

    <script>
        // Generate QR Code cleanly
        window.addEventListener('DOMContentLoaded', () => {
            const qrContainer = document.getElementById('qrcode');
            if (qrContainer) {
                new QRCode(qrContainer, {
                    text: <?php echo json_encode($qr_payload); ?>,
                    width: 140,
                    height: 140,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        });
    </script>
</body>
</html>

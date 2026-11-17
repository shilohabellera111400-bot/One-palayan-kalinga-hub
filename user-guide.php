<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen User Guide - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --maroon: #7F0303;
            --midnight: #0F414A;
            --bg-primary: #E4EFE7;
            --bg-secondary: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #4B5563;
            --border-color: #CBD5E1;
            --radius-card: 20px;
        }
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        .guide-hero {
            background: linear-gradient(135deg, #7F0303 0%, #003366 100%);
            color: white;
            padding: 70px 20px;
            text-align: center;
        }
        .guide-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .guide-hero p {
            font-size: 1.05rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        .guide-container {
            max-width: 960px;
            margin: -40px auto 60px auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }
        .guide-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-card);
            padding: 36px 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        .guide-card h2 {
            color: #7F0303;
            font-size: 1.35rem;
            margin-top: 0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid rgba(127, 3, 3, 0.1);
            padding-bottom: 12px;
        }
        .step-pill {
            background: #7F0303;
            color: white;
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 50px;
            font-weight: 700;
        }
        .guide-card p {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 0.95rem;
            margin-bottom: 16px;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .feature-item {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 18px;
        }
        .feature-item h4 {
            margin: 0 0 6px 0;
            color: #0F414A;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        .feature-item p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        .highlight-box {
            background: rgba(46, 125, 50, 0.08);
            border-left: 4px solid #2e7d32;
            padding: 14px 18px;
            border-radius: 8px;
            margin: 16px 0;
            font-size: 0.9rem;
            color: #1b5e20;
        }
    </style>
</head>
<body>

    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>
    <div class="header-spacer" style="height: 70px;"></div>

    <!-- Hero Section -->
    <section class="guide-hero">
        <div class="container">
            <h1><i class="fas fa-book-open-reader"></i> Citizen User Guide</h1>
            <p>Step-by-step instructions for Palayan City residents on accessing digital social welfare services</p>
        </div>
    </section>

    <!-- Content -->
    <div class="guide-container">
        <!-- Step 1 -->
        <div class="guide-card">
            <h2><span class="step-pill">Step 1</span> <i class="fas fa-id-card"></i> Account Registration & Residency Verification</h2>
            <p>To ensure municipal resources directly benefit true Palayanos, all applicants must complete residency verification upon creating an account.</p>
            <div class="feature-grid">
                <div class="feature-item">
                    <h4><i class="fas fa-user-plus"></i> 1. Register Account</h4>
                    <p>Sign up with your legal full name, mobile number, and active email address.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-file-arrow-up"></i> 2. Upload Barangay Proof</h4>
                    <p>Upload a clear photo of your Valid Government ID and Barangay Clearance/Residency Certificate.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-shield-check"></i> 3. Evaluator Approval</h4>
                    <p>CSWDO verifies your residency within 1-2 working days and unlocks full assistance portals.</p>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="guide-card">
            <h2><span class="step-pill">Step 2</span> <i class="fas fa-hand-holding-heart"></i> Applying for AICS Assistance</h2>
            <p>The Assistance to Individuals in Crisis Situations (AICS) provides targeted emergency support for families facing acute distress:</p>
            <div class="feature-grid">
                <div class="feature-item">
                    <h4><i class="fas fa-notes-medical"></i> Medical Assistance</h4>
                    <p>Subsidies for hospitalization, dialysis, chemotherapy, and laboratory bills.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-cross"></i> Funeral & Burial</h4>
                    <p>Support for funeral home fees, burial space, and death certificates.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-graduation-cap"></i> Educational Aid</h4>
                    <p>Stipends for qualified underprivileged students in public secondary/tertiary schools.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-box-tissue"></i> Immediate Food & Relief</h4>
                    <p>Dry rations and core relief packs for fire or calamity victims.</p>
                </div>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="guide-card">
            <h2><span class="step-pill">Step 3</span> <i class="fas fa-house-chimney"></i> Housing Assistance Pathways</h2>
            <p>The CSWDO Housing module offers two distinct programs tailored to your family's situation:</p>
            
            <div class="highlight-box">
                <strong><i class="fas fa-house-chimney-user"></i> Option A: In-Site Housing (Sariling Lupa)</strong><br>
                For qualified beneficiaries who already own or possess certified ancestral/titled land in Palayan City. You do <em>not</em> need to relocate. CSWDO provides core shelter building material vouchers and technical construction clearances.
            </div>

            <div class="highlight-box" style="background: rgba(15, 65, 74, 0.08); border-left-color: #0F414A; color: #0F414A;">
                <strong><i class="fas fa-truck-ramp-box"></i> Option B: Relocation Housing</strong><br>
                For qualified families living in danger zones, riverbanks, or government right-of-ways who need safe resettlement in designated municipal resettlement communities.
            </div>
        </div>

        <!-- Step 4 -->
        <div class="guide-card">
            <h2><span class="step-pill">Step 4</span> <i class="fas fa-calendar-check"></i> Appointments & QR Check-in</h2>
            <p>Skip the long lines at City Hall by reserving an appointment slip online:</p>
            <div class="feature-grid">
                <div class="feature-item">
                    <h4><i class="fas fa-calendar-days"></i> 1. Pick Slot</h4>
                    <p>Select your required service and preferred date and morning/afternoon slot.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-qrcode"></i> 2. Save QR Slip</h4>
                    <p>Download or screenshot your digital appointment slip with your encrypted QR code.</p>
                </div>
                <div class="feature-item">
                    <h4><i class="fas fa-camera"></i> 3. Express Check-in</h4>
                    <p>Present your QR code to the CSWDO scanner window for prioritized assistance.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

</body>
</html>

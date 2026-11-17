<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - One Palayan-Kalinga Hub</title>
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
        .policy-hero {
            background: linear-gradient(135deg, #7F0303 0%, #0F414A 100%);
            color: white;
            padding: 70px 20px;
            text-align: center;
        }
        .policy-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .policy-hero p {
            font-size: 1.05rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        .policy-container {
            max-width: 900px;
            margin: -40px auto 60px auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }
        .policy-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        .policy-card h2 {
            color: #7F0303;
            font-size: 1.35rem;
            margin-top: 0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(127, 3, 3, 0.1);
            padding-bottom: 10px;
        }
        .policy-card p, .policy-card li {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 0.95rem;
        }
        .policy-card ul {
            padding-left: 20px;
            margin-bottom: 16px;
        }
        .policy-card li {
            margin-bottom: 8px;
        }
        .legal-notice {
            background: rgba(15, 65, 74, 0.08);
            border-left: 4px solid #0F414A;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
            color: #0F414A;
        }
    </style>
</head>
<body>

    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>
    <div class="header-spacer" style="height: 70px;"></div>

    <!-- Hero Section -->
    <section class="policy-hero">
        <div class="container">
            <h1><i class="fas fa-shield-halved"></i> Data Privacy Policy</h1>
            <p>City Social Welfare and Development Office (CSWDO) — City Government of Palayan, Nueva Ecija</p>
        </div>
    </section>

    <!-- Content -->
    <div class="policy-container">
        <div class="policy-card">
            <h2><i class="fas fa-scale-balanced"></i> Statutory Compliance (Republic Act No. 10173)</h2>
            <p>The City Social Welfare and Development Office of Palayan City is strictly committed to protecting the privacy, confidentiality, and integrity of personal information and sensitive personal data collected from applicants and beneficiaries in accordance with Republic Act No. 10173, otherwise known as the <strong>Data Privacy Act of 2012 (DPA)</strong> of the Republic of the Philippines.</p>
            <div class="legal-notice">
                <i class="fas fa-info-circle"></i> By utilizing the One Palayan-Kalinga Hub, you explicitly consent to the collection, processing, recording, and storage of your personal data solely for social welfare assistance and qualification verification purposes.
            </div>
        </div>

        <div class="policy-card">
            <h2><i class="fas fa-folder-open"></i> Personal Data Collected</h2>
            <p>In order to evaluate eligibility for national and municipal social protection programs, CSWDO collects the following data points:</p>
            <ul>
                <li><strong>Identity Information:</strong> Full name, date of birth, age, sex, civil status, photograph (1x1 ID photo), and government-issued identification cards.</li>
                <li><strong>Residency Verification:</strong> Barangay residence, street address, and supporting proof (Barangay Certificate of Residency).</li>
                <li><strong>Socio-Economic Data:</strong> Household composition, total monthly income, employment status, housing tenure, and property titles/tax declarations.</li>
                <li><strong>Health & Special Needs:</strong> Medical diagnoses, attending physician notes, and disability assessment records (for AICS Medical and PWD Registry).</li>
            </ul>
        </div>

        <div class="policy-card">
            <h2><i class="fas fa-lock"></i> Data Protection & Security Controls</h2>
            <p>All sensitive citizen records are encrypted in transit and protected on secure government servers. Access is strictly role-restricted to authorized CSWDO licensed social workers, evaluators, and system administrators with active audit logging. We never sell, lease, or disclose applicant information to third parties without prior informed consent or a lawful court order.</p>
        </div>

        <div class="policy-card">
            <h2><i class="fas fa-user-check"></i> Citizen Rights as Data Subjects</h2>
            <p>Under the Data Privacy Act of 2012, citizens retain the following rights:</p>
            <ul>
                <li><strong>Right to be Informed:</strong> To understand how their data is gathered, evaluated, and stored.</li>
                <li><strong>Right to Access:</strong> To view, track, and print copies of their submitted applications at any time.</li>
                <li><strong>Right to Rectification:</strong> To update or correct outdated or incomplete personal details.</li>
                <li><strong>Right to Inquire:</strong> Direct any privacy-related questions to the Palayan City CSWDO Data Protection Officer at <em>cswdo@palayancity.gov.ph</em>.</li>
            </ul>
        </div>
    </div>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

</body>
</html>

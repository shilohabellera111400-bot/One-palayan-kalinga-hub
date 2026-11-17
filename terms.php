<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - One Palayan-Kalinga Hub</title>
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
        .terms-hero {
            background: linear-gradient(135deg, #0F414A 0%, #7F0303 100%);
            color: white;
            padding: 70px 20px;
            text-align: center;
        }
        .terms-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .terms-hero p {
            font-size: 1.05rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        .terms-container {
            max-width: 900px;
            margin: -40px auto 60px auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }
        .terms-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-card);
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        .terms-card h2 {
            color: #0F414A;
            font-size: 1.35rem;
            margin-top: 0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(15, 65, 74, 0.1);
            padding-bottom: 10px;
        }
        .terms-card p, .terms-card li {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 0.95rem;
        }
        .terms-card ul {
            padding-left: 20px;
            margin-bottom: 16px;
        }
        .terms-card li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>
    <div class="header-spacer" style="height: 70px;"></div>

    <!-- Hero Section -->
    <section class="terms-hero">
        <div class="container">
            <h1><i class="fas fa-file-contract"></i> Terms of Service & Citizen Charter</h1>
            <p>Terms governing user accounts, eligibility standards, and social welfare requests</p>
        </div>
    </section>

    <!-- Content -->
    <div class="terms-container">
        <div class="terms-card">
            <h2><i class="fas fa-handshake"></i> Acceptance of Terms</h2>
            <p>Welcome to the <strong>One Palayan-Kalinga Hub</strong>, the official digital social welfare management portal of the City Government of Palayan. By creating an account, uploading identification documents, and filing assistance applications, you agree to comply with these terms, government policies, and applicable municipal ordinances.</p>
        </div>

        <div class="terms-card">
            <h2><i class="fas fa-address-card"></i> Account Registration & Residency Verification</h2>
            <p>To access government subsidies, core shelter grants, or special sector privileges:</p>
            <ul>
                <li>You must be a bona fide resident of Palayan City, Nueva Ecija, with verified barangay certification.</li>
                <li>You must provide accurate, current, and truthful personal details. Any falsification of public documents or misrepresentation of income/household status is punishable under the Revised Penal Code of the Philippines.</li>
                <li>Each citizen is permitted only one active portal account associated with their unique Philippine identification.</li>
            </ul>
        </div>

        <div class="terms-card">
            <h2><i class="fas fa-hand-holding-heart"></i> Social Welfare Assistance Guidelines</h2>
            <p>All submitted applications (including AICS Medical, Burial, Educational, and Housing Assistance) undergo formal social casework assessment. Submission of an application does not guarantee automatic monetary release. Final grant awards are subject to:</p>
            <ul>
                <li>Verification of documentary requirements by licensed social welfare officers.</li>
                <li>In-person or on-site field validation (e.g., lot inspection for In-Site Housing).</li>
                <li>Availability of municipal funds and slot allocations approved by the Sangguniang Panlungsod.</li>
            </ul>
        </div>

        <div class="terms-card">
            <h2><i class="fas fa-ban"></i> Account Termination & Penalties</h2>
            <p>CSWDO reserves the right to suspend or revoke portal access and disqualify applicants from municipal assistance programs if fraudulent documents, duplicate claims, or abusive behavior toward caseworkers or administrative staff are discovered.</p>
        </div>
    </div>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

</body>
</html>

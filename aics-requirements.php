<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AICS Requirements and Procedures - CSWDO Palayan City</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #003366 0%, #004d99 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header h1 i {
            color: #ff6600;
            margin-right: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Main Content */
        .main-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        /* Title Section */
        .title-section {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #ff6600;
        }

        .title-section h2 {
            color: #003366;
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .title-section .badge {
            display: inline-block;
            background: #ff6600;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-right: 10px;
        }

        .title-section .badge i {
            margin-right: 5px;
        }

        .description {
            color: #666;
            line-height: 1.8;
            font-size: 1.1rem;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #003366;
        }

        /* Info Box */
        .info-box {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box i {
            font-size: 2rem;
            color: #ff6600;
        }

        .info-box p {
            color: #856404;
            margin: 0;
            font-size: 1rem;
        }

        /* Classification */
        .classification {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .classification i {
            font-size: 2rem;
            color: #003366;
        }

        .classification h3 {
            color: #003366;
            margin-bottom: 5px;
        }

        .classification p {
            color: #666;
        }

        /* Requirements Grid */
        .requirements-section {
            margin: 40px 0;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .section-header i {
            font-size: 2rem;
            color: #ff6600;
            background: #fff3e0;
            padding: 15px;
            border-radius: 50%;
        }

        .section-header h3 {
            color: #003366;
            font-size: 1.8rem;
        }

        .requirements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .requirement-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .requirement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,51,102,0.15);
        }

        .card-header {
            background: #003366;
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header i {
            font-size: 1.8rem;
            color: #ff6600;
        }

        .card-header h4 {
            font-size: 1.2rem;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            padding: 10px 0;
            padding-left: 28px;
            position: relative;
            color: #555;
            border-bottom: 1px dashed #e0e0e0;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .requirements-list li:last-child {
            border-bottom: none;
        }

        .requirements-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .requirements-list li strong {
            color: #003366;
        }

        /* Procedures Section */
        .procedures-section {
            margin: 50px 0;
        }

        .procedures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .procedure-card {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .procedure-header {
            background: #ff6600;
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .procedure-header i {
            font-size: 1.8rem;
            color: white;
        }

        .procedure-header h4 {
            font-size: 1.2rem;
            margin: 0;
        }

        .procedure-body {
            padding: 20px;
        }

        .steps-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .steps-list li {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .steps-list li:last-child {
            border-bottom: none;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: #003366;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-content strong {
            color: #003366;
            display: block;
            margin-bottom: 5px;
        }

        .step-content p {
            color: #666;
            font-size: 0.95rem;
            margin: 0;
        }

        /* Agency Actions */
        .agency-actions {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }

        .agency-actions h4 {
            color: #1a472a;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .agency-actions ul {
            list-style: none;
            padding: 0;
        }

        .agency-actions li {
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            color: #2e7d32;
        }

        .agency-actions li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #ff6600;
            font-weight: bold;
        }

        /* Footer Note */
        .footer-note {
            margin-top: 40px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            text-align: center;
            color: #666;
            font-style: italic;
        }

        .footer-note i {
            color: #ff6600;
            margin-right: 8px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: #003366;
            color: white;
        }

        .btn-primary:hover {
            background: #002244;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,51,102,0.3);
        }

        .btn-secondary {
            background: #ff6600;
            color: white;
        }

        .btn-secondary:hover {
            background: #ff5500;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,102,0,0.3);
        }

        .print-btn {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            transition: background 0.3s;
        }

        .print-btn:hover {
            background: #5a6268;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }

            .main-content {
                padding: 20px;
            }

            .procedures-grid {
                grid-template-columns: 1fr;
            }

            .requirements-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons a {
                width: 100%;
                justify-content: center;
            }

            .info-box {
                flex-direction: column;
                text-align: center;
            }
        }

        @media print {
            .header, .action-buttons, .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-hand-holding-heart"></i> AICS Citizen's Charter</h1>
            <p>Assistance to Individuals in Crisis Situation - Requirements and Procedures</p>
        </div>
    </div>

    <div class="container">
        <!-- Print Button -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print / I-print
            </button>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Title Section -->
            <div class="title-section">
                <h2><i class="fas fa-file-alt" style="color: #ff6600; margin-right: 10px;"></i> ASSISTANCE TO INDIVIDUALS IN CRISIS SITUATION (AICS)</h2>
                <div>
                    <span class="badge"><i class="fas fa-balance-scale"></i> SOCIAL SAFETY NET</span>
                    <span class="badge"><i class="fas fa-clock"></i> STOP-GAP MEASURE</span>
                </div>
                
                <div class="description">
                    <p>The CSWDO has available funding which shall be used to provide financial assistance to the individuals and families so that the vulnerable and marginalized individuals and families needing immediate intervention may have access to basic services. In this context, AICS serves as social safety net or stop-gap measure to support the recovery of individuals and families suffering from unexpected life events or crisis.</p>
                    <p style="margin-top: 10px;">The provision of psychosocial intervention and/or direct financial/material assistance may enable them to meet their basic needs in the form of medical, transportation, and burial assistance. The provision of financial and material assistance to clients shall be based on the assessment and recommendation of the local social workers and duly approved by its authorized.</p>
                </div>
            </div>

            <!-- Classification & Info -->
            <div class="info-box">
                <i class="fas fa-building"></i>
                <div>
                    <p><strong>Office/Division:</strong> City Social Welfare and Development Office (CSWDO)</p>
                    <p><strong>Classification:</strong> Government to Citizen (G2C) / Government to Government (G2G)</p>
                    <p><strong>Who may avail:</strong> Individuals and families in crisis situation residing in Palayan City</p>
                </div>
            </div>

            <!-- Requirements Section -->
            <div class="requirements-section">
                <div class="section-header">
                    <i class="fas fa-check-circle"></i>
                    <h3>CHECKLIST OF REQUIREMENTS</h3>
                </div>

                <div class="requirements-grid">
                    <!-- Burial Assistance -->
                    <div class="requirement-card">
                        <div class="card-header">
                            <i class="fas fa-cross"></i>
                            <h4>Burial Assistance</h4>
                        </div>
                        <div class="card-body">
                            <ul class="requirements-list">
                                <li><strong>Certified True Copy</strong> of relative's registered death certificate</li>
                                <li><strong>Original Barangay Certificate</strong> of Residency and Indigency</li>
                                <li><strong>Photocopy</strong> of Valid ID of the client</li>
                                <li><strong>Original Funeral Contract</strong> (Unpaid)</li>
                                <li><strong>Written letter</strong> of request</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Food and Transportation Assistance -->
                    <div class="requirement-card">
                        <div class="card-header">
                            <i class="fas fa-utensils"></i>
                            <h4>Food and Transportation Assistance</h4>
                        </div>
                        <div class="card-body">
                            <ul class="requirements-list">
                                <li><strong>Original Barangay Certificate</strong> of Residency and Indigency</li>
                                <li><strong>Photocopy</strong> of Valid ID of the client</li>
                                <li><strong>Request letter</strong></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Medical/Financial Assistance -->
                    <div class="requirement-card">
                        <div class="card-header">
                            <i class="fas fa-hospital"></i>
                            <h4>Medical/Financial Assistance</h4>
                        </div>
                        <div class="card-body">
                            <ul class="requirements-list">
                                <li><strong>Original Barangay Certificate</strong> of Residency and Indigency</li>
                                <li><strong>Photocopy</strong> of Valid ID of the client</li>
                                <li><strong>Doctor's prescription</strong> (for medicines or laboratory request)</li>
                                <li><strong>Medical Abstract</strong> / Medical Certificate</li>
                                <li><strong>Hospital Bill</strong> (for payment of hospital bill)</li>
                                <li><strong>Request letter</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Procedures Section -->
            <div class="procedures-section">
                <div class="section-header">
                    <i class="fas fa-list-ol"></i>
                    <h3>CLIENT STEPS & AGENCY ACTION</h3>
                </div>

                <div class="procedures-grid">
                    <!-- Client Steps -->
                    <div class="procedure-card">
                        <div class="procedure-header">
                            <i class="fas fa-user"></i>
                            <h4>CLIENT STEPS</h4>
                        </div>
                        <div class="procedure-body">
                            <ul class="steps-list">
                                <li>
                                    <span class="step-number">1</span>
                                    <div class="step-content">
                                        <strong>Register on the client's log book</strong>
                                        <p>Register your name, address, gender, contact number and purpose of visit either in Window 1, 2 or 3.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">2</span>
                                    <div class="step-content">
                                        <strong>Submit required documents</strong>
                                        <p>Submit required documents either in Window 1, 2 or 3 for initial assessment and verification.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">3</span>
                                    <div class="step-content">
                                        <strong>Submit oneself for an interview</strong>
                                        <p>Undergo interview with social worker for assessment.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">4</span>
                                    <div class="step-content">
                                        <strong>Wait for notification</strong>
                                        <p>Client will be informed through text/call or through brgy. officials/volunteers once his/her medical/financial assistance is available or ready for release.</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Agency Action -->
                    <div class="procedure-card">
                        <div class="procedure-header" style="background: #003366;">
                            <i class="fas fa-users-cog"></i>
                            <h4>AGENCY ACTION</h4>
                        </div>
                        <div class="procedure-body">
                            <ul class="steps-list">
                                <li>
                                    <span class="step-number">1</span>
                                    <div class="step-content">
                                        <strong>Assist client</strong>
                                        <p>Assist the client during their registration.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">2</span>
                                    <div class="step-content">
                                        <strong>Receive & Review documents</strong>
                                        <p>Receive & Review required documents and check for completeness.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">3</span>
                                    <div class="step-content">
                                        <strong>Conduct interview and assessment</strong>
                                        <p>Conduct an interview and assessment to the client to gather relevant information.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">4</span>
                                    <div class="step-content">
                                        <strong>Prepare documents</strong>
                                        <p>Preparation of Justification/Social Case Study Report, Certificate of Eligibility and voucher.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">5</span>
                                    <div class="step-content">
                                        <strong>Review and approval</strong>
                                        <p>Review and approval of recommendation.</p>
                                    </div>
                                </li>
                                <li>
                                    <span class="step-number">6</span>
                                    <div class="step-content">
                                        <strong>Submission to City Budget Office</strong>
                                        <p>Forward approved documents to City Budget Office.</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total -->
            <div class="agency-actions">
                <h4><i class="fas fa-clock"></i> PROCESSING TIME</h4>
                <p><strong>Total:</strong> None (Varies based on case assessment and fund availability)</p>
            </div>

            <!-- Certificate of Indigency Info -->
            <div class="footer-note">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> The Certificate of Indigency or a Certificate of Low Income is a document that is sometimes required for an individual's financial stability assessment.
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (isLoggedIn()): ?>
                    <a href="apply-aics.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Apply for AICS
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Apply
                    </a>
                <?php endif; ?>
                <a href="track-aics.php" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Track Application
                </a>
                <a href="index.php" class="btn" style="background: #6c757d; color: white;">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds (if any)
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                if (alert) alert.style.display = 'none';
            });
        }, 5000);

        // Print functionality
        document.querySelector('.print-btn')?.addEventListener('click', function() {
            window.print();
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
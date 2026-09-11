<?php
require_once __DIR__ . '/config/database.php';

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Requirements - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== COLOR PALETTE & GLOBAL THEMING ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gold: #C9A86C;
            --gold-light: #E8D5A8;
            --light-blue: #96C0CE;
            --alabaster: #E3E7EB;
            
            --bg-primary: #E8ECF2;
            --bg-card: #FFFFFF;
            --text-primary: #263238;
            --text-secondary: #3A4A5A;
            --text-muted: #6B7A8A;
            --border-color: #D5DBE1;
            
            --shadow-sm: 0 2px 10px rgba(15,65,74,0.10), 0 1px 4px rgba(0,0,0,0.07);
            --shadow-md: 0 10px 30px rgba(15, 65, 74, 0.09), 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 16px 40px rgba(15, 65, 74, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 12px 40px rgba(127, 3, 3, 0.15);
            
            --radius-card: 24px;
            --radius-element: 16px;
            
            --color-success: #2e7d32;
            --color-warning: #ed6c02;
            --color-danger: #d32f2f;
            --color-info: #0288d1;
        }

        [data-theme="dark"] {
            --bg-primary: #0a0e17;
            --bg-card: #1a2332;
            --text-primary: #E8EDF5;
            --text-secondary: #B0C4DE;
            --text-muted: #6B85A0;
            --border-color: #2a3a4f;
            --alabaster: #161e2a;
            
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, 0.5);
            --shadow-hover: 0 12px 40px rgba(139, 26, 26, 0.15);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
            
            --color-success: #4caf50;
            --color-warning: #ff9800;
            --color-danger: #f44336;
            --color-info: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
            transition: background 0.4s ease, color 0.4s ease;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ========== HERO & HEADER ========== */
        .requirements-hero {
            background: var(--midnight);
            color: white;
            padding: 170px 0 95px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .requirements-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: 0;
            animation: slowZoom 20s ease-in-out infinite alternate;
        }

        .requirements-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 65, 74, 0.88), rgba(15, 65, 74, 0.75));
            z-index: 0;
        }

        .requirements-hero .container {
            position: relative;
            z-index: 2;
            animation: fadeInDown 0.6s ease forwards;
        }

        .requirements-hero h1 {
            font-size: 3.2rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .requirements-hero h1 span {
            color: var(--gold-light);
        }

        .requirements-hero p {
            font-size: 1.2rem;
            max-width: 750px;
            margin: 0 auto;
            opacity: 0.95;
            color: var(--alabaster);
            line-height: 1.7;
        }

        /* ========== CONTENT CONTAINER ========== */
        .requirements-content {
            padding: 70px 0;
            background: var(--bg-primary);
        }

        .requirements-container {
            max-width: 960px;
            margin: 0 auto;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            padding: 45px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            animation: fadeInUp 0.6s ease forwards;
        }

        /* ========== LANGUAGE & PRINT CONTROLS ========== */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .language-toggle {
            display: flex;
            gap: 10px;
        }

        .lang-btn {
            padding: 10px 22px;
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-radius: 40px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .lang-btn.active {
            background: var(--maroon);
            color: white;
            border-color: var(--maroon);
            box-shadow: 0 4px 10px rgba(127, 3, 3, 0.25);
        }

        [data-theme="dark"] .lang-btn.active {
            background: var(--gold-light);
            color: var(--midnight);
            border-color: var(--gold-light);
            box-shadow: 0 4px 10px rgba(232, 213, 168, 0.25);
        }

        .lang-btn:hover:not(.active) {
            border-color: var(--maroon);
            color: var(--maroon);
        }

        [data-theme="dark"] .lang-btn:hover:not(.active) {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        .print-btn {
            background: var(--bg-primary);
            color: var(--text-secondary);
            padding: 10px 22px;
            border: 2px solid var(--border-color);
            border-radius: 40px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: var(--midnight);
            color: white;
            border-color: var(--midnight);
            transform: translateY(-2px);
        }

        [data-theme="dark"] .print-btn:hover {
            background: var(--gold-light);
            color: var(--midnight);
            border-color: var(--gold-light);
        }

        /* ========== OFFICE INTRO HEADER ========== */
        .office-info {
            text-align: center;
            margin-bottom: 40px;
        }

        .office-info h3 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .office-info .city {
            color: var(--maroon);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        [data-theme="dark"] .office-info .city {
            color: var(--gold-light);
        }

        /* Section Headings */
        .header-with-icon {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--maroon);
        }

        [data-theme="dark"] .header-with-icon {
            border-bottom-color: var(--gold-light);
        }

        .header-with-icon i {
            font-size: 1.8rem;
            color: var(--maroon);
        }

        [data-theme="dark"] .header-with-icon i {
            color: var(--gold-light);
        }

        .header-with-icon h2 {
            color: var(--text-primary);
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }

        /* ========== REQUIREMENTS STEP LIST ========== */
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }

        .requirements-list li {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 24px;
            margin-bottom: 16px;
            background: var(--bg-primary);
            border-radius: var(--radius-element);
            border-left: 5px solid var(--maroon);
            border: 1px solid var(--border-color);
            border-left: 5px solid var(--maroon);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] .requirements-list li {
            border-left-color: var(--gold-light);
        }

        .requirements-list li:hover {
            transform: translateX(6px);
            box-shadow: var(--shadow-sm);
            border-color: var(--maroon-light);
        }

        [data-theme="dark"] .requirements-list li:hover {
            border-color: var(--gold-light);
        }

        .requirements-list li .number-badge {
            background: var(--maroon);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(127, 3, 3, 0.2);
        }

        [data-theme="dark"] .requirements-list li .number-badge {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 8px rgba(232, 213, 168, 0.2);
        }

        .requirements-list li .content-wrapper {
            flex: 1;
        }

        .requirements-list li .content-wrapper strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 6px;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .requirements-list li .content-wrapper .english-translation {
            color: var(--text-muted);
            font-size: 0.88rem;
            font-style: italic;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--border-color);
            display: block;
            line-height: 1.5;
        }

        /* ========== LEGAL / ADDITIONAL REMINDERS ========== */
        .additional-info {
            margin-top: 45px;
            padding: 30px;
            background: rgba(216, 186, 152, 0.12);
            border-radius: var(--radius-element);
            border-left: 5px solid var(--tan);
            border: 1px solid var(--border-color);
            border-left: 5px solid var(--tan);
        }

        .additional-info h4 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 1.1rem;
            font-weight: 750;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .additional-info h4 i {
            color: var(--maroon);
        }

        [data-theme="dark"] .additional-info h4 i {
            color: var(--gold-light);
        }

        .additional-info p {
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 0.92rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-weight: 500;
        }

        .additional-info p:last-child {
            margin-bottom: 0;
        }

        .additional-info p i {
            color: var(--color-success);
            margin-top: 4px;
            flex-shrink: 0;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 45px;
            flex-wrap: wrap;
        }

        .btn-action-primary {
            padding: 14px 32px;
            background: var(--maroon);
            color: white;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.2);
        }

        .btn-action-primary:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(127, 3, 3, 0.35);
        }

        .btn-action-secondary {
            padding: 14px 32px;
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            border: 1.5px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-secondary:hover {
            background: var(--border-color);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        /* Bilingual Toggle visibility classes */
        .filipino-only { display: none; }
        .show-filipino .filipino-only { display: block; }
        .show-filipino .english-only { display: none; }

        .show-filipino .filipino-text-toggle { display: inline; }
        .show-filipino .english-text-toggle { display: none; }
        .filipino-text-toggle { display: none; }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slowZoom {
            from { transform: scale(1); }
            to { transform: scale(1.05); }
        }

        /* ========== PRINT STYLING OVERRIDES ========== */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            .navbar, .footer, .requirements-hero, .controls-row, .action-buttons {
                display: none !important;
            }
            .requirements-content {
                padding: 0 !important;
                background: white !important;
            }
            .requirements-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                background: white !important;
            }
            .requirements-list li {
                background: white !important;
                border: 1px solid #ccc !important;
                border-left: 5px solid black !important;
                page-break-inside: avoid;
            }
            .requirements-list li .number-badge {
                background: black !important;
                color: white !important;
                border: none !important;
            }
            /* force show both english & tagalog on prints */
            .filipino-only, .english-only {
                display: block !important;
            }
            .requirements-list li .content-wrapper .english-translation {
                border-top-color: #ccc !important;
                color: #555 !important;
            }
            .additional-info {
                border: 1px solid #ccc !important;
                border-left: 5px solid #666 !important;
                background: #f9f9f9 !important;
            }
        }

        /* Responsive override updates */
        @media (max-width: 768px) {
            .requirements-hero { padding: 130px 0 60px; }
            .requirements-hero h1 { font-size: 2rem; }
            .requirements-container { padding: 25px 20px; }
            .controls-row { flex-direction: column; align-items: stretch; }
            .language-toggle { justify-content: center; }
            .print-btn { justify-content: center; }
            .requirements-list li { padding: 18px; }
            .action-buttons { flex-direction: column; align-items: stretch; }
            .btn-action-primary, .btn-action-secondary { justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- Navbar Include -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="requirements-hero">
        <div class="container">
            <h1>Housing Requirements</h1>
            <p>Gabay sa mga legal at sibil na dokumentong kinakailangan sa pag-apply / Detailed guide for required housing credentials</p>
        </div>
    </section>

    <!-- Main Content Section -->
    <section class="requirements-content">
        <div class="container">
            <div class="requirements-container" id="requirementsContainer">
                
                <!-- Controls: Language Toggle & Print -->
                <div class="controls-row">
                    <div class="language-toggle">
                        <button class="lang-btn active" id="btnFilipino" onclick="setLanguage('filipino')">🇵🇭 Filipino</button>
                        <button class="lang-btn" id="btnEnglish" onclick="setLanguage('english')">🇬🇧 English</button>
                    </div>
                    <button class="print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> 
                        <span class="filipino-text-toggle">I-print ang Checklist</span>
                        <span class="english-text-toggle">Print Checklist</span>
                    </button>
                </div>

                <!-- Office Header -->
                <div class="office-info">
                    <h3>CITY SOCIAL WELFARE AND DEVELOPMENT OFFICE</h3>
                    <div class="city">PALAYAN CITY</div>
                </div>

                <!-- Title with Icon -->
                <div class="header-with-icon">
                    <i class="fas fa-file-shield"></i>
                    <h2 class="filipino-only">MGA KINAKAILANGANG DOKUMENTO</h2>
                    <h2 class="english-only">REQUIRED DOCUMENTS</h2>
                </div>

                <!-- Requirements Steps List -->
                <ul class="requirements-list">
                    <li>
                        <span class="number-badge">1</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Kasulatan ng Layunin (Letter of Intent) o Aplikasyon na naglalaman ng mga pangalan ng lahat ng kasapi ng pamilya.</strong>
                            <strong class="english-only">Letter of Intent or Application containing the names of all household members.</strong>
                            <span class="english-translation">A formal request letter addressed to the CSWDO detailing your intent to apply for resettlement.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">2</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Barangay Clearance o Certificate of Indigency.</strong>
                            <strong class="english-only">Barangay Clearance or Certificate of Indigency.</strong>
                            <span class="english-translation">An official barangay document certifying your residency and status as a low-income family unit.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">3</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katibayan ng Pagkakakilanlan (Government IDs) tulad ng Voter's Certification, Senior Citizen ID, PhilSys ID, PWD, Solo Parent, o 4Ps ID at katunayan ng pagiging residente ng Palayan City.</strong>
                            <strong class="english-only">Proof of Identity (Government IDs) such as Voter's Certification, Senior Citizen ID, PhilSys ID, PWD, Solo Parent, or 4Ps ID, and proof of residency in Palayan City.</strong>
                            <span class="english-translation">Bring at least one government-issued identification card along with a certificate proving you reside within the city.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">4</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Kasulatan ng Kapahintulutan (Affidavit of Consent) mula sa magulang o kamag-anak na nagmamay-ari ng lupa (kung magpapatayo ng bahay sa kanilang lote).</strong>
                            <strong class="english-only">Written Affidavit of Consent from parents or relatives who own the land (if building a house on their lot).</strong>
                            <span class="english-translation">A notarized consent document signed by the property owner allowing building/resettlement works.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">5</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Larawan ng kasalukuyang bahay o bahay ng mga magulang.</strong>
                            <strong class="english-only">Photos of your current house or parents' house.</strong>
                            <span class="english-translation">Clear printed photos displaying the structural condition of your current living situation.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">6</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Family Intake Sheet na may kasamang Assessment mula sa itinalagang Social Worker.</strong>
                            <strong class="english-only">Family Intake Sheet with Assessment from the assigned Social Worker.</strong>
                            <span class="english-translation">A background check profile filled out during interview assessments with CSWDO social workers.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">7</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Proof of Billing (katibayan ng bayarin sa kuryente o tubig sa kasalukuyang tirahan).</strong>
                            <strong class="english-only">Proof of Billing (utility bills at current residence).</strong>
                            <span class="english-translation">Recent household billing statement reflecting your registered address.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">8</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Tax Declaration (kung mayroon o kung naaangkop).</strong>
                            <strong class="english-only">Tax Declaration (if applicable).</strong>
                            <span class="english-translation">Real estate tax declaration documents, if available.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">9</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Iba pang mga kinakailangang dokumento na maaaring hingin para sa karagdagang pag-apruba ng komite.</strong>
                            <strong class="english-only">Other required documents that may be requested by the committee for final approval.</strong>
                            <span class="english-translation">Any additional supporting credentials requested depending on your specific assessment metrics.</span>
                        </div>
                    </li>
                </ul>

                <!-- Additional Reminders Block -->
                <div class="additional-info">
                    <h4><i class="fas fa-triangle-exclamation"></i> <span class="filipino-only">Mahahalagang Paalala</span><span class="english-only">Important Reminders</span></h4>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Siguraduhing kumpleto at orihinal ang lahat ng dokumento bago isumite.</span><span class="english-only">Ensure all documents are complete and original before submission.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Maghanda ng dalawang (2) malinaw na photocopy ng bawat dokumento.</span><span class="english-only">Prepare two (2) clear photocopies of each required document.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Ang mga larawan ay dapat naka-print sa bond paper nang malinaw at may sapat na liwanag.</span><span class="english-only">Ensure photos are printed clearly on plain bond paper in high lighting.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Lahat ng isinumiteng dokumento ay isasailalim sa pagsusuri at balidasyon ng CSWDO.</span><span class="english-only">All documents are subject to verification and validation by CSWDO personnel.</span></p>
                </div>

                <!-- Footer Action Buttons -->
                <div class="action-buttons">
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <a href="apply-housing.php" class="btn-action-primary">
                            <i class="fas fa-paper-plane"></i> 
                            <span class="filipino-only">Mag-apply na Ngayon</span>
                            <span class="english-only">Apply Now Online</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-action-primary">
                            <i class="fas fa-right-to-bracket"></i> 
                            <span class="filipino-only">Mag-login para Mag-apply</span>
                            <span class="english-only">Login to Apply</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="housing.php" class="btn-action-secondary">
                        <i class="fas fa-circle-arrow-left"></i> 
                        <span class="filipino-only">Bumalik sa Programs</span>
                        <span class="english-only">Back to Communities</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Include -->
    <?php include 'includes/footer.php'; ?>

    <script>
    function setLanguage(lang) {
        const container = document.getElementById('requirementsContainer');
        const btnFilipino = document.getElementById('btnFilipino');
        const btnEnglish = document.getElementById('btnEnglish');
        
        if (lang === 'filipino') {
            container.classList.add('show-filipino');
            btnFilipino.classList.add('active');
            btnEnglish.classList.remove('active');
        } else {
            container.classList.remove('show-filipino');
            btnEnglish.classList.add('active');
            btnFilipino.classList.remove('active');
        }
        localStorage.setItem('preferredLanguage', lang);
    }

    // Set preference on load
    document.addEventListener('DOMContentLoaded', function() {
        const savedLang = localStorage.getItem('preferredLanguage') || 'filipino';
        setLanguage(savedLang);
    });

    // Reset language visibility for A4 Printing
    window.onbeforeprint = function() {
        document.getElementById('requirementsContainer').classList.remove('show-filipino');
    };
    window.onafterprint = function() {
        const savedLang = localStorage.getItem('preferredLanguage') || 'filipino';
        setLanguage(savedLang);
    };
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/cms-helper.php';

// Check for dark mode preference from cookie
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housing Requirements - <?php echo htmlspecialchars(cms_get('site_title', 'One Palayan-Kalinga Hub')); ?></title>
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
            
            --bg-primary: #E4EFE7;
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
            --bg-primary: #0C1417;
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

        /* ========== PATHWAY SELECTOR TABS ========== */
        .pathway-tabs-container {
            margin-bottom: 30px;
        }

        .pathway-tabs-nav {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: var(--bg-primary);
            padding: 6px;
            border-radius: 50px;
            border: 1px solid var(--border-color);
        }

        .pathway-tab-btn {
            padding: 14px 20px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 40px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .pathway-tab-btn i {
            font-size: 1.1rem;
        }

        .pathway-tab-btn.active {
            background: var(--maroon);
            color: white;
            box-shadow: 0 4px 14px rgba(127, 3, 3, 0.25);
        }

        [data-theme="dark"] .pathway-tab-btn.active {
            background: var(--gold-light);
            color: var(--midnight);
            box-shadow: 0 4px 14px rgba(232, 213, 168, 0.25);
        }

        .pathway-highlight-box {
            margin: 20px 0 30px;
            padding: 20px 24px;
            border-radius: var(--radius-element);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            gap: 16px;
            background: rgba(15, 65, 74, 0.05);
            border-left: 5px solid var(--midnight);
        }

        [data-theme="dark"] .pathway-highlight-box {
            background: rgba(184, 149, 58, 0.08);
            border-left-color: var(--gold-light);
        }

        .pathway-highlight-box.insite {
            background: rgba(46, 125, 50, 0.06);
            border-left: 5px solid #2e7d32;
        }

        [data-theme="dark"] .pathway-highlight-box.insite {
            background: rgba(76, 175, 80, 0.1);
            border-left-color: #4caf50;
        }

        .pathway-highlight-box i {
            font-size: 1.6rem;
            margin-top: 2px;
            color: var(--midnight);
        }

        [data-theme="dark"] .pathway-highlight-box i {
            color: var(--gold-light);
        }

        .pathway-highlight-box.insite i {
            color: #2e7d32;
        }

        [data-theme="dark"] .pathway-highlight-box.insite i {
            color: #4caf50;
        }

        .pathway-highlight-box h4 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 750;
            margin-bottom: 4px;
        }

        .pathway-highlight-box p {
            color: var(--text-secondary);
            font-size: 0.88rem;
            margin: 0;
            line-height: 1.5;
        }

        .req-pill {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 30px;
            margin-left: 8px;
            vertical-align: middle;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .req-pill.reloc {
            background: rgba(15, 65, 74, 0.12);
            color: var(--midnight);
            border: 1px solid rgba(15, 65, 74, 0.25);
        }

        [data-theme="dark"] .req-pill.reloc {
            background: rgba(232, 213, 168, 0.15);
            color: var(--gold-light);
            border-color: rgba(232, 213, 168, 0.3);
        }

        .req-pill.insite {
            background: rgba(46, 125, 50, 0.12);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.3);
        }

        [data-theme="dark"] .req-pill.insite {
            background: rgba(76, 175, 80, 0.15);
            color: #81c784;
            border-color: rgba(76, 175, 80, 0.3);
        }

        .req-pill.mandatory {
            background: rgba(211, 47, 47, 0.1);
            color: var(--color-danger);
            border: 1px solid rgba(211, 47, 47, 0.25);
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

                <!-- Pathway Switcher Tabs -->
                <div class="pathway-tabs-container">
                    <div class="pathway-tabs-nav">
                        <button type="button" class="pathway-tab-btn active" id="tabRelocationBtn" onclick="switchPathway('relocation')">
                            <i class="fas fa-truck-ramp-box"></i>
                            <span class="filipino-only">Relocation Housing</span>
                            <span class="english-only">Relocation Housing</span>
                        </button>
                        <button type="button" class="pathway-tab-btn" id="tabInsiteBtn" onclick="switchPathway('insite')">
                            <i class="fas fa-house-chimney-user"></i>
                            <span class="filipino-only">In-Site Housing (Sariling Lupa)</span>
                            <span class="english-only">In-Site Housing (Own Lot)</span>
                        </button>
                    </div>

                    <!-- Relocation Summary Box -->
                    <div class="pathway-highlight-box" id="relocationSummaryBox">
                        <i class="fas fa-circle-info"></i>
                        <div>
                            <h4>
                                <span class="filipino-only">Para sa Paglipat sa Ligtas na Resettlement Site</span>
                                <span class="english-only">For Transfer to Designated Resettlement Communities</span>
                            </h4>
                            <p class="filipino-only">
                                Para sa mga benepisyaryong kailangang ilipat mula sa danger zones, road widening, o walang sariling tirahan patungo sa mga itinalagang pabahay ng lungsod (Akap Village o San Lorenzo NHA).
                            </p>
                            <p class="english-only">
                                Intended for qualified beneficiaries needing safe resettlement from hazard zones or eviction to Palayan City municipal housing communities (Akap Village or San Lorenzo NHA).
                            </p>
                        </div>
                    </div>

                    <!-- In-Site Summary Box -->
                    <div class="pathway-highlight-box insite" id="insiteSummaryBox" style="display: none;">
                        <i class="fas fa-hand-holding-hand"></i>
                        <div>
                            <h4>
                                <span class="filipino-only">Pagtatayo sa Sariling Lote (Hindi Kailangang Lumipat ng Lugar)</span>
                                <span class="english-only">Construction on Existing Own Lot (No Relocation Required)</span>
                            </h4>
                            <p class="filipino-only">
                                Para sa mga residente ng Palayan City na may sarili o pinapahintulutang lupa (TCT, Tax Dec, o Notarized Consent) at nangangailangan ng tulong-pinansyal at materyales para magpatayo o mag-reconstruct ng bahay sa mismong kanilang lote.
                            </p>
                            <p class="english-only">
                                For Palayan City residents who already own or hold authorized tenure over their land/lot and need building assistance to construct directly on-site without moving away from their community.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Office Header -->
                <div class="office-info">
                    <h3>CITY SOCIAL WELFARE AND DEVELOPMENT OFFICE</h3>
                    <div class="city">PALAYAN CITY</div>
                </div>

                <!-- Title with Icon -->
                <div class="header-with-icon">
                    <i class="fas fa-file-shield"></i>
                    <h2 class="filipino-only" id="checklistTitleFilipino">MGA KINAKAILANGANG DOKUMENTO: RELOCATION HOUSING</h2>
                    <h2 class="english-only" id="checklistTitleEnglish">REQUIRED DOCUMENTS: RELOCATION HOUSING</h2>
                </div>

                <!-- RELOCATION REQUIREMENTS LIST -->
                <ul class="requirements-list" id="relocationList">
                    <li>
                        <span class="number-badge">1</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Kasulatan ng Layunin (Letter of Intent) para sa Resettlement Housing <span class="req-pill reloc">Relocation</span><span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Formal Letter of Intent for Resettlement Housing <span class="req-pill reloc">Relocation</span><span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">A formal request letter addressed to the CSWDO stating household composition and requesting housing unit allocation at Akap Village or San Lorenzo NHA.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">2</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Barangay Clearance o Certificate of Indigency <span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Barangay Clearance or Certificate of Indigency <span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">Official certification from your current barangay establishing bona fide residency and low-income status.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">3</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katibayan ng Pagkakakilanlan (Government IDs) ng Aplikante at Asawa <span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Government-Issued Valid IDs of Applicant and Spouse <span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">PhilSys National ID, Voter's Certification, Senior Citizen, PWD, 4Ps ID, or Driver's License proving Palayan City residency.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">4</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katunayan ng Pangangailangan sa Paglipat (Notice of Eviction / Danger Zone Certification / Displaced Settler)</strong>
                            <strong class="english-only">Proof of Need for Relocation (Notice of Eviction / Danger Zone Certification / Displaced Settler)</strong>
                            <span class="english-translation">Documentation or barangay endorsement confirming dwelling is located within a flood-prone waterway, danger area, or government right-of-way.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">5</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Larawan ng Kasalukuyang Tirahan (Interior at Exterior)</strong>
                            <strong class="english-only">Photographs of Current Dwelling (Interior and Exterior Views)</strong>
                            <span class="english-translation">Clear printed photographs showing current informal/unsafe housing condition and living quarters.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">6</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Family Intake Sheet na may Pagsusuri ng Assigned CSWDO Social Worker <span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Family Intake Sheet with CSWDO Case Worker Assessment <span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">Social case study report completed during CSWDO interview validation confirming eligibility for relocation housing.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">7</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Proof of Billing (Kuryente o Tubig sa Kasalukuyang Tirahan)</strong>
                            <strong class="english-only">Proof of Billing (Recent Electric or Water Utility Bills)</strong>
                            <span class="english-translation">Recent billing statement reflecting current domestic address in Palayan City.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">8</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katibayan ng Kita (Certificate of Employment, Pay Slip, o Barangay Income Certificate)</strong>
                            <strong class="english-only">Proof of Household Income (Certificate of Employment, Pay Slip, or Barangay Income Certificate)</strong>
                            <span class="english-translation">Document verifying monthly household earnings to compute affordability metrics for socialized programs.</span>
                        </div>
                    </li>
                </ul>

                <!-- IN-SITE REQUIREMENTS LIST -->
                <ul class="requirements-list" id="insiteList" style="display: none;">
                    <li>
                        <span class="number-badge">1</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katunayan ng Pagmamay-ari o Karapatan sa Lupa (Proof of Land Ownership / Tenurial Right) <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <strong class="english-only">Proof of Land Ownership or Tenurial Right <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <span class="english-translation">Land Title (TCT/OCT), Updated Real Property Tax Declaration (Katibayan ng Pagbabayad ng Buwis), or Notarized Deed of Conveyance/Sale in applicant's name.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">2</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Kasulatan ng Kapahintulutan (Notarized Affidavit of Consent mula sa May-ari ng Lupa - kung hindi sa sariling pangalan) <span class="req-pill insite">In-Site</span></strong>
                            <strong class="english-only">Notarized Affidavit of Consent from Landowner (if lot is under parents, relatives, or community estate) <span class="req-pill insite">In-Site</span></strong>
                            <span class="english-translation">If constructing on family or inherited land, a notarized affidavit allowing the applicant to erect a residential house without contest.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">3</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Barangay Lot Occupancy & Residency Certification sa Palayan City <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <strong class="english-only">Barangay Lot Occupancy & Palayan City Residency Certification <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <span class="english-translation">Barangay certification explicitly certifying that the applicant currently resides in and occupies the specific lot location in Palayan City.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">4</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Sketsa o Vicinity Sketch Map ng Lokasyon ng Lote at Bahay <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <strong class="english-only">Lot Vicinity Sketch Map & Location Plan <span class="req-pill insite">In-Site</span><span class="req-pill mandatory">Mandatory</span></strong>
                            <span class="english-translation">Clear hand-drawn or surveyed sketch showing landmarks, road access, and boundary markers of the lot where house will be built.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">5</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Larawan ng Kasalukuyang Lote at Sirang Bahay (Photos of Lot & Existing Dilapidated Structure) <span class="req-pill insite">In-Site</span></strong>
                            <strong class="english-only">Photographs of Lot & Existing Dilapidated Structure <span class="req-pill insite">In-Site</span></strong>
                            <span class="english-translation">Recent 4R photos showcasing the building lot, boundaries, and current damaged or unfinished dwelling structure.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">6</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Katibayan ng Pagkakakilanlan (Government IDs) ng Aplikante at Kasama sa Bahay <span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Government-Issued Valid Identification Cards of Household Heads <span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">PhilSys ID, Palayan City Resident Voter's Certificate, Senior Citizen, PWD, or 4Ps ID.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">7</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Family Intake Sheet at CSWDO On-Site Verification Assessment <span class="req-pill mandatory">Required</span></strong>
                            <strong class="english-only">Family Intake Sheet & CSWDO On-Site Verification Assessment <span class="req-pill mandatory">Required</span></strong>
                            <span class="english-translation">Intake form completed during CSWDO on-site property inspection to confirm lot feasibility and social eligibility.</span>
                        </div>
                    </li>
                    <li>
                        <span class="number-badge">8</span>
                        <div class="content-wrapper">
                            <strong class="filipino-only">Kasulatan ng Layunin para sa In-Site House Construction (Letter of Intent) <span class="req-pill insite">In-Site</span></strong>
                            <strong class="english-only">Letter of Intent for In-Site Housing Assistance <span class="req-pill insite">In-Site</span></strong>
                            <span class="english-translation">Written intent declaring proposed scope: core house construction, full rebuild, or emergency shelter rehab on own land.</span>
                        </div>
                    </li>
                </ul>

                <!-- Additional Reminders Block -->
                <div class="additional-info">
                    <h4><i class="fas fa-triangle-exclamation"></i> <span class="filipino-only">Mahahalagang Paalala</span><span class="english-only">Important Reminders</span></h4>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Siguraduhing kumpleto at orihinal ang lahat ng dokumento bago isumite sa online portal o sa CSWDO office.</span><span class="english-only">Ensure all documents are complete and authentic before submitting through the portal or in person at the CSWDO office.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Para sa <strong>In-Site Housing</strong>, magsasagawa ang CSWDO ng ocular inspection sa inyong lote upang mapatunayan ang lupang pagtatayuan.</span><span class="english-only">For <strong>In-Site Housing</strong>, CSWDO case officers will perform an ocular validation visit at your specified lot address.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Maghanda ng dalawang (2) malinaw na photocopy ng bawat dokumento sakaling kailanganin sa onsite interview.</span><span class="english-only">Prepare two (2) clear photocopies of each required document for your physical case folder.</span></p>
                    <p><i class="fas fa-circle-check"></i> <span class="filipino-only">Lahat ng isinumiteng dokumento ay isasailalim sa masusing pagsusuri at balidasyon ng CSWDO Committee.</span><span class="english-only">All documents undergo strict validation and committee deliberation by the CSWDO Housing Evaluation Board.</span></p>
                </div>

                <!-- Footer Action Buttons -->
                <div class="action-buttons">
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <a href="apply-housing.php?type=in_site" class="btn-action-primary" style="background: #2e7d32;">
                            <i class="fas fa-house-chimney-user"></i> 
                            <span class="filipino-only">Mag-apply para sa In-Site Housing</span>
                            <span class="english-only">Apply for In-Site Housing</span>
                        </a>
                        <a href="apply-housing.php?type=relocation" class="btn-action-primary">
                            <i class="fas fa-truck-ramp-box"></i> 
                            <span class="filipino-only">Mag-apply para sa Relocation</span>
                            <span class="english-only">Apply for Relocation Housing</span>
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
    let currentPathway = 'relocation';

    function switchPathway(pathway) {
        currentPathway = pathway;
        const tabReloc = document.getElementById('tabRelocationBtn');
        const tabInsite = document.getElementById('tabInsiteBtn');
        const relocSummary = document.getElementById('relocationSummaryBox');
        const insiteSummary = document.getElementById('insiteSummaryBox');
        const relocList = document.getElementById('relocationList');
        const insiteList = document.getElementById('insiteList');
        const titleFilipino = document.getElementById('checklistTitleFilipino');
        const titleEnglish = document.getElementById('checklistTitleEnglish');

        if (pathway === 'insite') {
            tabReloc.classList.remove('active');
            tabInsite.classList.add('active');
            relocSummary.style.display = 'none';
            insiteSummary.style.display = 'flex';
            relocList.style.display = 'none';
            insiteList.style.display = 'block';
            titleFilipino.textContent = 'MGA KINAKAILANGANG DOKUMENTO: IN-SITE HOUSING (SARILING LUPA)';
            titleEnglish.textContent = 'REQUIRED DOCUMENTS: IN-SITE HOUSING (OWN LOT)';
        } else {
            tabInsite.classList.remove('active');
            tabReloc.classList.add('active');
            insiteSummary.style.display = 'none';
            relocSummary.style.display = 'flex';
            insiteList.style.display = 'none';
            relocList.style.display = 'block';
            titleFilipino.textContent = 'MGA KINAKAILANGANG DOKUMENTO: RELOCATION HOUSING';
            titleEnglish.textContent = 'REQUIRED DOCUMENTS: RELOCATION HOUSING';
        }
    }

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

    // Set preference on load and check query string for default pathway
    document.addEventListener('DOMContentLoaded', function() {
        const savedLang = localStorage.getItem('preferredLanguage') || 'filipino';
        setLanguage(savedLang);

        const urlParams = new URLSearchParams(window.location.search);
        const reqType = urlParams.get('type');
        if (reqType === 'in_site' || reqType === 'insite') {
            switchPathway('insite');
        } else {
            switchPathway('relocation');
        }
    });

    // Reset language visibility for A4 Printing
    window.onbeforeprint = function() {
        document.getElementById('requirementsContainer').classList.remove('show-filipino');
        // When printing, show both lists with headings
        document.getElementById('relocationList').style.display = 'block';
        document.getElementById('insiteList').style.display = 'block';
        document.getElementById('relocationSummaryBox').style.display = 'flex';
        document.getElementById('insiteSummaryBox').style.display = 'flex';
    };
    window.onafterprint = function() {
        const savedLang = localStorage.getItem('preferredLanguage') || 'filipino';
        setLanguage(savedLang);
        switchPathway(currentPathway);
    };
    </script>
</body>
</html>

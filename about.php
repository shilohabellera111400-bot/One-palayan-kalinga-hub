<?php
require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== MODERN PROFESSIONAL PALETTE & VARIABLES ========== */
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-dark: #5a0202;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            
            /* Light mode default tokens */
            --bg-primary: #E8ECF2;
            --bg-secondary: #FFFFFF;
            --bg-card: #FFFFFF;
            --text-primary: #1F2937;
            --text-secondary: #374151;
            --text-muted: #6B7A8A;
            --border-color: #C8D0DC;
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 12px 30px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.08);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        /* Dark mode overrides (automatically enabled via body.dark-mode or html[data-theme="dark"]) */
        body.dark-mode, [data-theme="dark"] body {
            --bg-primary: #0D1117;
            --bg-secondary: #161B22;
            --bg-card: #161B22;
            --text-primary: #F0F6FC;
            --text-secondary: #C9D1D9;
            --text-muted: #8B949E;
            --border-color: #30363D;
            --white: #161B22;
            --shadow-sm: 0 4px 16px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.4);
            --alabaster: #21262D;
            --midnight: #0F414A;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header Fix for Floating Navbar */
        .header-spacer {
            height: 90px;
        }
        @media (max-width: 768px) {
            .header-spacer { height: 75px; }
        }

        /* ========== About Hero Section ========== */
        .about-hero {
            background: linear-gradient(135deg, var(--midnight) 0%, #155d6a 50%, var(--maroon-dark) 100%);
            color: white;
            padding: 90px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 5px solid var(--maroon);
        }

        .about-hero::before {
            content: '🏛️';
            font-size: 16rem;
            position: absolute;
            right: -20px;
            bottom: -50px;
            opacity: 0.05;
            transform: rotate(15deg);
            pointer-events: none;
        }

        .about-hero h1 {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 850;
            margin-bottom: 20px;
            letter-spacing: -0.03em;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .about-hero p {
            font-size: clamp(1rem, 2vw, 1.25rem);
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }

        /* ========== Mission Vision Section ========== */
        .mission-vision-section {
            padding: 80px 0;
            background: var(--bg-primary);
        }

        .mv-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .mission-card, .vision-card {
            background: var(--bg-card);
            padding: 45px 40px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .mission-card:hover, .vision-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .mission-card {
            border-bottom: 5px solid var(--maroon);
        }

        .vision-card {
            border-bottom: 5px solid var(--midnight);
        }

        .mv-icon {
            font-size: 2.8rem;
            margin-bottom: 24px;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: var(--bg-primary);
        }

        .mission-card .mv-icon {
            color: var(--maroon);
            background: rgba(127, 3, 3, 0.08);
        }

        .vision-card .mv-icon {
            color: #1a6a78;
            background: rgba(26, 106, 120, 0.08);
        }

        .mission-card h2, .vision-card h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.01em;
        }

        .mission-card h2 {
            color: var(--maroon);
        }
        body.dark-mode .mission-card h2 {
            color: #ff6b6b;
        }

        .vision-card h2 {
            color: var(--midnight);
        }
        body.dark-mode .vision-card h2 {
            color: var(--light-blue);
        }

        .mv-content {
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--text-secondary);
            flex-grow: 1;
        }

        .mv-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .mv-list li {
            padding: 10px 0 10px 32px;
            position: relative;
            font-size: 1rem;
        }

        .mv-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: var(--maroon);
            font-weight: bold;
            font-size: 1.2rem;
            background: rgba(127, 3, 3, 0.1);
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        body.dark-mode .mv-list li:before {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.15);
        }

        /* ========== History Section ========== */
        .history-section {
            padding: 90px 0;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .history-content {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }

        .history-icon {
            font-size: 3.5rem;
            color: var(--maroon);
            margin-bottom: 24px;
            display: inline-flex;
            width: 80px;
            height: 80px;
            align-items: center;
            justify-content: center;
            background: rgba(127, 3, 3, 0.08);
            border-radius: 50%;
        }

        body.dark-mode .history-icon {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.08);
        }

        .history-content h2 {
            color: var(--text-primary);
            font-size: 2.2rem;
            font-weight: 850;
            margin-bottom: 25px;
            letter-spacing: -0.02em;
        }

        .history-text {
            color: var(--text-secondary);
            line-height: 1.8;
            font-size: 1.1rem;
            text-align: justify;
        }

        .history-text p {
            margin-bottom: 20px;
        }

        /* ========== Organizational Chart Section ========== */
        .org-chart-section {
            padding: 90px 0;
            background: var(--bg-primary);
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            color: var(--text-primary);
            font-size: 2.4rem;
            font-weight: 850;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .org-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .dept-head {
            text-align: center;
            margin-bottom: 50px;
        }

        .head-card {
            background: linear-gradient(135deg, var(--midnight) 0%, #155d6a 100%);
            color: white;
            padding: 40px 30px;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-lg);
            display: inline-block;
            min-width: 380px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }

        .head-card:hover {
            transform: scale(1.03);
        }

        .head-icon {
            font-size: 2.8rem;
            margin-bottom: 16px;
            color: var(--tan);
        }

        .head-name {
            font-size: 1.55rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .head-position {
            font-size: 1.05rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .org-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 28px;
            margin-top: 40px;
        }

        .org-card {
            background: var(--bg-card);
            border-radius: var(--radius-element);
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .org-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon);
        }

        .org-avatar {
            width: 70px;
            height: 70px;
            background: rgba(12, 51, 102, 0.08);
            color: var(--midnight);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
        }

        body.dark-mode .org-avatar {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-blue);
        }

        .org-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .org-position {
            color: var(--maroon);
            font-size: 0.88rem;
            font-weight: 600;
        }

        body.dark-mode .org-position {
            color: #ff6b6b;
        }

        /* ========== Core Values Section ========== */
        .values-section {
            padding: 90px 0;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .value-card {
            background: var(--bg-primary);
            padding: 40px 25px;
            border-radius: var(--radius-element);
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
        }

        .value-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
            border-color: var(--maroon);
        }

        .value-icon {
            width: 75px;
            height: 75px;
            background: var(--maroon);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 24px;
            box-shadow: 0 6px 15px rgba(127, 3, 3, 0.15);
        }

        body.dark-mode .value-icon {
            background: #ff6b6b;
            color: #0D1117;
            box-shadow: 0 6px 15px rgba(255, 107, 107, 0.15);
        }

        .value-card h3 {
            color: var(--text-primary);
            margin-bottom: 14px;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .value-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* ========== Responsive Styles ========== */
        @media (max-width: 968px) {
            .mv-container { grid-template-columns: 1fr; gap: 30px; }
            .org-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        }

        @media (max-width: 768px) {
            .head-card { min-width: auto; width: 100%; }
            .values-grid { grid-template-columns: 1fr; }
            .about-hero { padding: 70px 0; }
        }
    </style>
</head>
<body>

    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Header Spacer for Sticky Navbar -->
    <div class="header-spacer"></div>

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="container">
            <h1>About CSWDO Palayan City</h1>
            <p>Committed to serving the vulnerable and socially dysfunctional sectors of society through care, protection, rehabilitation, development, and integration.</p>
        </div>
    </section>

    <!-- Mission and Vision Section -->
    <section class="mission-vision-section">
        <div class="container">
            <div class="mv-container">
                <div class="mission-card">
                    <div class="mv-icon"><i class="fas fa-bullseye"></i></div>
                    <h2>MISSION</h2>
                    <div class="mv-content">
                        <p>Care, Protect, Rehabilitate, Develop and Integrate into the mainstream the vulnerable and socially dysfunctional sectors of society.</p>
                    </div>
                </div>

                <div class="vision-card">
                    <div class="mv-icon"><i class="fas fa-eye"></i></div>
                    <h2>VISION</h2>
                    <div class="mv-content">
                        <p>Empowered Individuals, Families and Communities:</p>
                        <ul class="mv-list">
                            <li>With improved quality of Life performing their expected roles</li>
                            <li>Able to mobilize resources to meet their basic needs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- History Section -->
    <section class="history-section">
        <div class="container">
            <div class="history-content">
                <div class="history-icon"><i class="fas fa-landmark"></i></div>
                <h2>Our History</h2>
                <div class="history-text">
                    <p>The City Social Welfare and Development Office (CSWDO) of Palayan City has been at the forefront of social services, dedicated to improving the quality of life for every resident. Through the years, we have evolved to meet the changing needs of our community, always with the goal of empowering individuals, families, and communities.</p>
                    <p>Today, we continue this legacy through the One Palayan-Kalinga Hub, a digital transformation initiative that brings our services closer to the people we serve, streamlining processes, appointment scheduling, and relief support.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Organizational Chart Section -->
    <section class="org-chart-section">
        <div class="container">
            <div class="section-header">
                <h2>Organizational Chart</h2>
                <p>Meet the dedicated team behind CSWDO Palayan City</p>
            </div>

            <div class="org-container">
                <div class="dept-head">
                    <div class="head-card">
                        <div class="head-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="head-name">MARIA TERESA M. RAMOS, RSW</div>
                        <div class="head-position">City Social Welfare and Development Officer</div>
                    </div>
                </div>

                <div class="org-grid">
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user-shield"></i></div>
                        <div class="org-name">JOCELYN M. AGULTO, RSW</div>
                        <div class="org-position">City Social Welfare Officer IV</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user-shield"></i></div>
                        <div class="org-name">MA. VICTORIA A. VENTURA, RSW</div>
                        <div class="org-position">Social Welfare Officer III</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-user-cog"></i></div>
                        <div class="org-name">JONALYN C. CONCEPCION</div>
                        <div class="org-position">Administrative Officer V</div>
                    </div>
                    <div class="org-card">
                        <div class="org-avatar"><i class="fas fa-tasks"></i></div>
                        <div class="org-name">JOHN DEXTER A. VILLAMIN</div>
                        <div class="org-position">Project Development Officer II</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values Section -->
    <section class="values-section">
        <div class="container">
            <div class="section-header">
                <h2>Our Core Values</h2>
                <p>Guiding principles that shape our service delivery</p>
            </div>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-heart"></i></div>
                    <h3>Malasakit</h3>
                    <p>Genuine care and compassion for every individual we serve.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <h3>Integridad</h3>
                    <p>Honesty and transparency in all our transactions.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-users"></i></div>
                    <h3>Pakikipagkapwa</h3>
                    <p>Building strong relationships within the community.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Kahusayan</h3>
                    <p>Continuous improvement and excellence in service delivery.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Global Footer -->
    <?php include 'includes/footer.php'; ?>

</body>
</html>

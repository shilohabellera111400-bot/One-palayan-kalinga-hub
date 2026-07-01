<?php
require_once __DIR__ . '/config/database.php';

// No login required for FAQs page

// Get categories for FAQ filtering
$database = new Database();
$db = $database->getConnection();

// Get unique FAQ categories from database (if you have a table)
// For now, we'll use static categories

$categories = [
    'all' => 'All Questions',
    'general' => 'General Information',
    'appointments' => 'Appointments',
    'aics' => 'AICS Assistance',
    'housing' => 'Housing Program',
    'documents' => 'Documents',
    'technical' => 'Technical Support'
];

// FAQ Data (can be moved to database later)
$faqs = [
    // General Information
    ['id' => 1, 'category' => 'general', 'question' => 'What is One Palayan-Kalinga Hub?', 'answer' => 'One Palayan-Kalinga Hub is an integrated online platform developed by CSWDO Palayan City to streamline social services. It allows residents to book appointments, apply for AICS assistance, track housing applications, upload documents, and receive real-time notifications—all from one convenient portal.'],
    ['id' => 2, 'category' => 'general', 'question' => 'Who can use this platform?', 'answer' => 'The platform is designed for all residents of Palayan City, Nueva Ecija. It serves: <br><br>✓ <strong>Residents</strong> - Access social services, apply for assistance, book appointments<br>✓ <strong>CSWDO Staff</strong> - Process requests, manage appointments, verify documents<br>✓ <strong>Administrators</strong> - Manage users, system settings, and generate reports'],
    ['id' => 3, 'category' => 'general', 'question' => 'Is there a fee to use this platform?', 'answer' => 'No, the platform is completely FREE for all Palayan City residents. CSWDO services are government-funded and provided at no cost to qualified beneficiaries.'],
    ['id' => 4, 'category' => 'general', 'question' => 'What are the office hours of CSWDO Palayan City?', 'answer' => '<strong>Office Hours:</strong><br>Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday - Sunday: Closed<br>Holidays: Closed<br><br>For emergencies, please contact our hotline directly.'],
    
    // Appointments
    ['id' => 5, 'category' => 'appointments', 'question' => 'How do I book an appointment?', 'answer' => 'To book an appointment:<br><br>1. Login to your account<br>2. Click on "Book Appointment" in the menu<br>3. Select your preferred service<br>4. Choose your preferred date and time<br>5. Add any notes or purpose<br>6. Click "Book Appointment"<br><br>You will receive a confirmation notification once your appointment is scheduled.'],
    ['id' => 6, 'category' => 'appointments', 'question' => 'How far in advance can I book an appointment?', 'answer' => 'You can book appointments up to 30 days in advance. Same-day appointments are available only if there are open slots. We recommend booking at least 3-5 days in advance to secure your preferred schedule.'],
    ['id' => 7, 'category' => 'appointments', 'question' => 'Can I cancel or reschedule my appointment?', 'answer' => 'Yes, you can cancel or reschedule your appointment through the "My Appointments" page. To cancel:<br><br>1. Go to "My Appointments"<br>2. Find the appointment you want to cancel<br>3. Click the "Cancel" button<br><br>Note: Please cancel at least 24 hours in advance to allow others to book the slot.'],
    ['id' => 8, 'category' => 'appointments', 'question' => 'What if I miss my appointment?', 'answer' => 'If you miss your appointment, it will be marked as "No Show". You can book a new appointment for a different date and time. Frequent no-shows may affect your ability to book future appointments.'],
    
    // AICS Assistance
    ['id' => 9, 'category' => 'aics', 'question' => 'What is AICS?', 'answer' => 'AICS (Assistance to Individuals in Crisis Situation) is a program that provides financial, medical, educational, transportation, food, and material assistance to individuals and families facing crisis situations. It aims to provide immediate support to those in need.'],
    ['id' => 10, 'category' => 'aics', 'question' => 'What types of assistance are available under AICS?', 'answer' => 'The following assistance types are available:<br><br>✓ <strong>Medical Assistance</strong> - Hospital bills, medicines, laboratory tests<br>✓ <strong>Funeral/Burial Assistance</strong> - Funeral services and burial expenses<br>✓ <strong>Educational Assistance</strong> - School supplies, tuition fees<br>✓ <strong>Transportation Assistance</strong> - Fare for medical or legal appointments<br>✓ <strong>Food Assistance</strong> - Food packs and grocery items<br>✓ <strong>Cash Relief</strong> - Financial assistance for crisis situations<br>✓ <strong>Material Assistance</strong> - Non-food items, hygiene kits'],
    ['id' => 11, 'category' => 'aics', 'question' => 'How do I apply for AICS?', 'answer' => 'To apply for AICS:<br><br>1. Login to your account<br>2. Click on "Apply for AICS" in the menu<br>3. Select the type of assistance you need<br>4. Fill out the application form<br>5. Upload required documents<br>6. Submit your application<br><br>You will receive updates on your application status via notifications.'],
    ['id' => 12, 'category' => 'aics', 'question' => 'How long does it take to process an AICS application?', 'answer' => 'Processing time varies depending on the type of assistance and completeness of requirements:<br><br>✓ Medical Assistance: 3-5 business days<br>✓ Funeral Assistance: 1-3 business days (urgent)<br>✓ Educational Assistance: 5-7 business days<br>✓ Other Assistance: 3-5 business days<br><br>You can track your application status in real-time through the platform.'],
    ['id' => 13, 'category' => 'aics', 'question' => 'What documents do I need for AICS?', 'answer' => 'General requirements include:<br><br>✓ Valid ID (Government-issued)<br>✓ Barangay Certificate of Indigency<br>✓ Written letter of request<br><br>Additional documents may be required based on the type of assistance (e.g., medical certificate, death certificate, enrollment form, etc.).'],
    
    // Housing Program
    ['id' => 14, 'category' => 'housing', 'question' => 'How do I apply for housing assistance?', 'answer' => 'To apply for housing assistance:<br><br>1. Login to your account<br>2. Click on "Housing Applications" in the menu<br>3. Select a housing program<br>4. Fill out the application form<br>5. Upload required documents<br>6. Submit your application<br><br>A CSWDO staff will review your application and contact you for an interview if needed.'],
    ['id' => 15, 'category' => 'housing', 'question' => 'What are the requirements for housing assistance?', 'answer' => 'Requirements include:<br><br>✓ Letter of Intent / Application Form<br>✓ Barangay Clearance / Certificate of Indigency<br>✓ Valid ID & Proof of Residency<br>✓ Affidavit of Consent (if applicable)<br>✓ Photos of current housing<br>✓ Proof of Billing (utility bills)<br>✓ Tax Declaration (if applicable)'],
    ['id' => 16, 'category' => 'housing', 'question' => 'How long does the housing application process take?', 'answer' => 'The housing application process typically takes 15-30 days, including:<br><br>✓ Document verification: 3-5 days<br>✓ Eligibility assessment: 5-7 days<br>✓ Site visit/interview: 3-5 days<br>✓ Approval/release: 3-5 days<br><br>You will be notified at each step of the process.'],
    
    // Documents
    ['id' => 17, 'category' => 'documents', 'question' => 'How do I upload documents?', 'answer' => 'To upload documents:<br><br>1. Login to your account<br>2. Go to "My Documents" in the menu<br>3. Click "Upload Document"<br>4. Select document type<br>5. Choose file from your device<br>6. Add description (optional)<br>7. Click "Upload"<br><br>Supported formats: PDF, JPG, JPEG, PNG (Max 5MB per file).'],
    ['id' => 18, 'category' => 'documents', 'question' => 'What file formats are accepted?', 'answer' => 'We accept the following file formats:<br><br>✓ PDF (Portable Document Format)<br>✓ JPG / JPEG (Images)<br>✓ PNG (Images)<br><br>Maximum file size: 5MB per file.<br><br>For best results, ensure your documents are clear and readable.'],
    ['id' => 19, 'category' => 'documents', 'question' => 'How long are my documents stored?', 'answer' => 'Your documents are stored securely in our system for the duration of your active account. Documents related to approved applications are kept for 5 years for record-keeping purposes. You can delete documents at any time from your "My Documents" page.'],
    
    // Technical Support
    ['id' => 20, 'category' => 'technical', 'question' => 'I forgot my password. How do I reset it?', 'answer' => 'To reset your password:<br><br>1. Click on "Login" in the navigation menu<br>2. Click "Forgot Password?" below the login form<br>3. Enter your registered email address<br>4. Check your email for reset instructions<br>5. Click the link in the email to reset your password<br><br>If you don\'t receive the email, check your spam folder or contact CSWDO for assistance.'],
    ['id' => 21, 'category' => 'technical', 'question' => 'Why am I not receiving notifications?', 'answer' => 'If you\'re not receiving notifications:<br><br>✓ Check your notification settings in your profile<br>✓ Ensure your email address is correct<br>✓ Check your spam/junk folder for emails<br>✓ Make sure your contact number is correct for SMS<br>✓ Check your internet connection for in-app notifications<br><br>If problems persist, contact CSWDO technical support.'],
    ['id' => 22, 'category' => 'technical', 'question' => 'What browsers are supported?', 'answer' => 'The platform works best with modern browsers:<br><br>✓ Google Chrome (recommended)<br>✓ Mozilla Firefox<br>✓ Microsoft Edge<br>✓ Safari<br><br>Make sure your browser is updated to the latest version for optimal performance.'],
    ['id' => 23, 'category' => 'technical', 'question' => 'Is my personal information secure?', 'answer' => 'Yes, we take data security seriously:<br><br>✓ All data is encrypted during transmission (SSL/TLS)<br>✓ Passwords are hashed and never stored in plain text<br>✓ Access to personal data is restricted to authorized staff<br>✓ Regular security audits are conducted<br>✓ We comply with data privacy laws (RA 10173 - Data Privacy Act)']
];

// Get filter parameter
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : 'all';

// Filter FAQs
$filtered_faqs = [];
foreach ($faqs as $faq) {
    if ($category_filter != 'all' && $faq['category'] != $category_filter) {
        continue;
    }
    $filtered_faqs[] = $faq;
}

// Group FAQs by category for display
$grouped_faqs = [];
foreach ($filtered_faqs as $faq) {
    $grouped_faqs[$faq['category']][] = $faq;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --tan: #D8BA98;
            --maroon: #7F0303;
            --alabaster: #EFE8DF;
            --light-blue: #96C0CE;
            --midnight: #0F414A;
            --maroon-light: #9a1515;
            --white: #FFFFFF;
            --gray-50: #F8F9FC;
            --gray-100: #F1F3F8;
            --gray-200: #E4E7E9;
            --gray-600: #6B7A8A;
            --gray-700: #3A4A5A;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
            --radius-card: 24px;
            --radius-element: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--gray-700);
            line-height: 1.5;
        }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

        /* Simple Header with Back Button */
        .simple-header {
            background: var(--white);
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gray-50);
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--midnight);
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }
        .back-btn:hover {
            background: var(--midnight);
            color: white;
            transform: translateX(-3px);
        }

        /* Page Header */
        .faq-header {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            color: white;
            padding: 60px 0 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .faq-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: 0;
        }
        .faq-header .container { position: relative; z-index: 2; }
        .faq-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .faq-header p {
            font-size: 0.9rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Category Filter */
        .category-filter {
            margin: 40px 0 30px;
            text-align: center;
        }
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
        .filter-btn {
            padding: 8px 20px;
            background: white;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .filter-btn:hover {
            border-color: var(--maroon);
            color: var(--maroon);
            transform: translateY(-2px);
        }
        .filter-btn.active {
            background: var(--maroon);
            border-color: var(--maroon);
            color: white;
        }

        /* FAQ Sections */
        .faq-content {
            padding: 0 0 80px;
            background: var(--gray-50);
        }
        .faq-category {
            margin-bottom: 50px;
        }
        .faq-category h2 {
            color: var(--midnight);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--maroon);
            display: inline-block;
        }
        .faq-grid {
            display: grid;
            gap: 20px;
        }
        .faq-item {
            background: white;
            border-radius: var(--radius-element);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
        }
        .faq-item:hover {
            box-shadow: var(--shadow-md);
        }
        .faq-question {
            padding: 18px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            background: white;
        }
        .faq-question h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--midnight);
            flex: 1;
            padding-right: 15px;
        }
        .faq-question i {
            color: var(--maroon);
            font-size: 1.1rem;
            transition: transform 0.3s;
        }
        .faq-question.active i {
            transform: rotate(180deg);
        }
        .faq-answer {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: var(--gray-50);
            border-top: 1px solid transparent;
        }
        .faq-answer.active {
            padding: 20px 25px;
            max-height: 500px;
            border-top-color: var(--gray-200);
        }
        .faq-answer p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Contact Support */
        .contact-support {
            background: linear-gradient(135deg, var(--midnight) 0%, #1a6a78 100%);
            border-radius: var(--radius-card);
            padding: 40px;
            text-align: center;
            color: white;
            margin-top: 40px;
        }
        .contact-support h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .contact-support p {
            margin-bottom: 20px;
            opacity: 0.9;
        }
        .contact-support .btn-contact {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--maroon);
            color: white;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .contact-support .btn-contact:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: var(--midnight);
            color: white;
            padding: 60px 0 20px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-section h3, .footer-section h4 { margin-bottom: 20px; font-weight: 700; }
        .footer-section h3 { color: var(--tan); font-size: 1.2rem; }
        .footer-section h4 { color: var(--light-blue); font-size: 1rem; }
        .footer-section p { color: rgba(255,255,255,0.7); margin-bottom: 15px; font-size: 0.85rem; }
        .footer-section ul { list-style: none; }
        .footer-section ul li { margin-bottom: 10px; }
        .footer-section a { color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.3s; font-size: 0.85rem; }
        .footer-section a:hover { color: var(--tan); }
        .contact-number, .email { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.7); font-size: 0.85rem; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.5); font-size: 0.75rem; }

        @media (max-width: 968px) {
            .footer-grid { grid-template-columns: 1fr; gap: 30px; }
            .filter-buttons { gap: 8px; }
            .filter-btn { padding: 6px 14px; font-size: 0.75rem; }
        }
        @media (max-width: 768px) {
            .faq-header h1 { font-size: 1.5rem; }
            .faq-question h3 { font-size: 0.9rem; }
            .contact-support { margin: 20px; }
        }
    </style>
</head>
<body>
    <!-- Simple Header with Back Button -->
    <div class="simple-header">
        <div class="container">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Page Header -->
    <section class="faq-header">
        <div class="container">
            <h1><i class="fas fa-question-circle"></i> Frequently Asked Questions</h1>
            <p>Find answers to common questions about our services, appointments, AICS assistance, housing programs, and more.</p>
        </div>
    </section>

    <!-- Category Filter -->
    <div class="container">
        <div class="category-filter">
            <div class="filter-buttons">
                <?php foreach($categories as $key => $label): ?>
                    <a href="?category=<?php echo $key; ?>" 
                       class="filter-btn <?php echo $category_filter == $key ? 'active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- FAQ Content -->
    <section class="faq-content">
        <div class="container">
            <?php if(empty($filtered_faqs)): ?>
                <div style="text-align: center; padding: 60px; background: white; border-radius: var(--radius-card);">
                    <i class="fas fa-question-circle" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 20px; display: block;"></i>
                    <h3>No questions found</h3>
                    <p style="color: var(--gray-600); margin-top: 10px;">Try selecting a different category.</p>
                    <a href="faqs.php" class="filter-btn" style="margin-top: 20px; display: inline-block;">View all FAQs</a>
                </div>
            <?php else: ?>
                <?php foreach($grouped_faqs as $category => $faq_list): ?>
                    <div class="faq-category">
                        <h2>
                            <i class="fas fa-<?php 
                                echo $category == 'general' ? 'info-circle' : 
                                    ($category == 'appointments' ? 'calendar-check' : 
                                    ($category == 'aics' ? 'hand-holding-heart' : 
                                    ($category == 'housing' ? 'home' : 
                                    ($category == 'documents' ? 'file-alt' : 'laptop-code')))); 
                            ?>"></i>
                            <?php echo ucfirst($category); ?>
                        </h2>
                        <div class="faq-grid">
                            <?php foreach($faq_list as $faq): ?>
                                <div class="faq-item">
                                    <div class="faq-question" onclick="toggleFAQ(this)">
                                        <h3><?php echo htmlspecialchars($faq['question']); ?></h3>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p><?php echo $faq['answer']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Contact Support -->
            <div class="contact-support">
                <h3><i class="fas fa-headset"></i> Still have questions?</h3>
                <p>Can't find the answer you're looking for? We're here to help!</p>
                <a href="contact.php" class="btn-contact">
                    <i class="fas fa-envelope"></i> Contact CSWDO Support
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section"><h3>ONE PALAYAN-KALINGA HUB</h3><p>CSWDO Palayan City — Integrated Social Services Platform</p><div><h4>SERVICES</h4><ul><li><a href="appointments.php">Appointment Booking</a></li><li><a href="aics.php">AICS Application</a></li><li><a href="housing.php">Housing Programs</a></li></ul></div></div>
                <div class="footer-section"><h4>RESOURCES</h4><ul><li><a href="faqs.php">FAQs</a></li><li><a href="user-guide.php">User Guide</a></li><li><a href="privacy-policy.php">Privacy Policy</a></li><li><a href="terms.php">Terms of Service</a></li></ul></div>
                <div class="footer-section"><h4>CONTACT US</h4><p>City Social Welfare and Development Office<br>Palayan City, Nueva Ecija, Philippines</p><p class="contact-number"><i class="fas fa-phone"></i> +63 (44) 123 4567</p><p class="email"><i class="fas fa-envelope"></i> cswdo@palayancity.gov.ph</p></div>
            </div>
            <div class="footer-bottom"><p>&copy; 2026 One Palayan-Kalinga Hub. All rights reserved. | A project of CSWDO Palayan City</p></div>
        </div>
    </footer>

    <script>
        // FAQ Accordion
        function toggleFAQ(element) {
            element.classList.toggle('active');
            const answer = element.nextElementSibling;
            answer.classList.toggle('active');
        }
    </script>
    <script src="js/main.js"></script>
</body>
</html>
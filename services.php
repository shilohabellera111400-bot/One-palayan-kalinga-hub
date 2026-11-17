<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/cms-helper.php';

$database = new Database();
$db = $database->getConnection();

// Check for dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';

// Get user data if logged in
$user_data = null;
$is_authenticated = isLoggedIn();
if ($is_authenticated) {
    $user_query = "SELECT * FROM users WHERE user_id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get initials for avatar
$initials = '';
if ($is_authenticated && isset($_SESSION['full_name'])) {
    $name_parts = explode(' ', $_SESSION['full_name']);
    foreach($name_parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

/**
 * Verified Official CSWDO Services Catalog
 * Complete 9-point information structure per service:
 * 1. Service Name
 * 2. Service Overview & Purpose
 * 3. Who Can Apply (Eligibility Criteria)
 * 4. Documentary Requirements Checklist
 * 5. Application Procedure (Citizen's Charter steps, duration, responsible officer)
 * 6. Where to Apply (Office / Desk / Window)
 * 7. Processing Time
 * 8. Fees (100% Free of Charge)
 * 9. Additional Reminders & Contacts
 */
$services_data = [
    // --- HOUSING SERVICES ---
    'housing-akap' => [
        'id' => 'housing-akap',
        'category' => 'housing',
        'category_name' => 'Housing Programs',
        'name' => 'Akap Village Resettlement Housing Project',
        'badge' => 'Relocation Housing',
        'icon' => 'fas fa-home',
        'short_desc' => 'Low-cost socialized resettlement project providing permanent subdivision lots and core housing units for underprivileged and hazard-zone Palayan families.',
        'overview' => 'Akap Village Housing Project is a premier socialized municipal housing community in Palayan City designed to provide secure, resilient, and dignified permanent housing units for informal settlers, families displaced from waterways and hazard zones, and underprivileged households.',
        'who_can_apply' => [
            'Bona fide residents of Palayan City who do not own any real property or residential land anywhere in the Philippines.',
            'Families residing in danger zones (riverbanks, active waterways, steep slopes, government right-of-ways).',
            'Informal settler families subject to court-ordered eviction or public infrastructure displacement.',
            'Low-income households whose combined family earnings do not exceed the statutory poverty threshold for socialized housing.'
        ],
        'requirements' => [
            ['name' => 'Formal Letter of Intent for Resettlement Housing addressed to CSWDO', 'type' => 'mandatory', 'source' => 'Applicant'],
            ['name' => 'Barangay Clearance and Certificate of Indigency', 'type' => 'mandatory', 'source' => 'Barangay Hall of current residence'],
            ['name' => 'Government-Issued Valid IDs of Applicant and Legal Spouse', 'type' => 'mandatory', 'source' => 'Applicant & Spouse (PhilSys, Voter\'s, Driver\'s License)'],
            ['name' => 'Proof of Need for Relocation (Notice of Eviction / Danger Zone Certification / Displaced Settler)', 'type' => 'conditional', 'source' => 'Barangay / City Planning / CDRRMO'],
            ['name' => 'Clear Photographs of Current Living Quarters (Interior and Exterior)', 'type' => 'mandatory', 'source' => 'Applicant'],
            ['name' => 'Family Intake Sheet with Assigned CSWDO Social Worker Assessment', 'type' => 'mandatory', 'source' => 'CSWDO Social Worker Interview'],
            ['name' => 'Recent Electric or Water Utility Bills (Proof of Domestic Billing)', 'type' => 'conditional', 'source' => 'Utility Provider (NEECO / Local Water District)'],
            ['name' => 'Proof of Household Income (Certificate of Employment, Pay Slip, or Barangay Income Affidavit)', 'type' => 'mandatory', 'source' => 'Employer / Barangay Hall']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Online Application or Desk Filing', 'desc' => 'Submit socialized housing application online via Kalinga Hub or visit the CSWDO Housing Division.', 'duration' => '20 mins', 'actor' => 'Applicant'],
            ['step' => 2, 'title' => 'Document Pre-Screening & Central Cross-Check', 'desc' => 'Validation against the municipal beneficiary database and City Assessor records to confirm non-land ownership.', 'duration' => '2-3 days', 'actor' => 'Housing Verification Officer'],
            ['step' => 3, 'title' => 'Field Home Visitation & Social Case Study Report', 'desc' => 'Licensed CSWDO social worker conducts an on-site interview at the applicant\'s current home.', 'duration' => '3-5 days', 'actor' => 'Licensed Social Worker'],
            ['step' => 4, 'title' => 'City Housing Board Deliberation & Unit Awarding', 'desc' => 'The Palayan City Housing Board reviews eligible applicants and awards designated unit lots at Akap Village.', 'duration' => '7-10 days', 'actor' => 'City Housing Board & Mayor']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Housing & Resettlement Division, 2nd Floor, Palayan City Hall.',
        'processing_time' => '7 to 10 working days for evaluation; awarding subject to unit batch release',
        'fees' => 'None — Application, social assessment, and verification are 100% Free of Charge',
        'additional_info' => [
            'Beneficiaries must personally occupy and maintain the awarded housing unit.',
            'Units cannot be leased, rented, mortgaged, or sold to third parties under municipal ordinance.',
            'Applicants must have had continuous bona fide residency in Palayan City for at least 3 years.',
            'For unit inquiries, contact the Housing Division at (044) 123-4567.'
        ],
        'apply_url' => 'apply-housing.php?program_id=1&type=relocation',
        'tags' => ['Akap Village', 'Socialized Housing', 'Relocation', 'Free Assessment']
    ],

    'housing-sanlorenzo' => [
        'id' => 'housing-sanlorenzo',
        'category' => 'housing',
        'category_name' => 'Housing Programs',
        'name' => 'San Lorenzo NHA Housing Project',
        'badge' => 'NHA Resettlement',
        'icon' => 'fas fa-city',
        'short_desc' => 'Joint National Housing Authority and Palayan City government community providing secure tenure, complete road amenities, and durable housing units.',
        'overview' => 'San Lorenzo NHA Housing Project is an organized government housing resettlement program established in partnership with the National Housing Authority (NHA) to provide disaster-resilient homes, concrete road networks, water/power connections, and community amenities to qualified Palayan families.',
        'who_can_apply' => [
            'Bona fide residents of Palayan City who do not own residential land or housing properties.',
            'Informal settlers and families relocated due to national or local infrastructure projects.',
            'Families living in danger zones or areas vulnerable to environmental disasters.',
            'Qualified low-income applicants screened through the joint LGU-NHA selection criteria.'
        ],
        'requirements' => [
            ['name' => 'Formal Application Letter addressed to the City Mayor and CSWDO', 'type' => 'mandatory', 'source' => 'Applicant'],
            ['name' => 'Barangay Certificate of Residency & Indigency in Palayan City', 'type' => 'mandatory', 'source' => 'Barangay Hall of current residence'],
            ['name' => 'Government-Issued Identification Cards of Household Heads', 'type' => 'mandatory', 'source' => 'Applicant & Spouse'],
            ['name' => 'Joint LGU-NHA Socio-Economic Household Survey Form', 'type' => 'mandatory', 'source' => 'CSWDO Housing Division'],
            ['name' => 'Certification of Non-Ownership of Real Estate Property', 'type' => 'mandatory', 'source' => 'City Assessor\'s Office'],
            ['name' => 'PSA Marriage Certificate or PSA Birth Certificates of Children', 'type' => 'mandatory', 'source' => 'Philippine Statistics Authority (PSA)'],
            ['name' => 'Proof of Household Income or Certificate of Low-Income Status', 'type' => 'mandatory', 'source' => 'Employer / Barangay Hall'],
            ['name' => 'Photographs of Current Living Conditions / Relocation Notice', 'type' => 'mandatory', 'source' => 'Applicant']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Filing of NHA Socialized Housing Application', 'desc' => 'Submit online application or visit CSWDO Housing Division with documentary requirements.', 'duration' => '20 mins', 'actor' => 'Applicant'],
            ['step' => 2, 'title' => 'NHA Central Beneficiary Registry Cross-Check', 'desc' => 'Records are validated against national and regional housing beneficiary registries to ensure no duplication.', 'duration' => '2-3 days', 'actor' => 'NHA-LGU Validation Desk'],
            ['step' => 3, 'title' => 'Socio-Economic Case Study & Housing Interview', 'desc' => 'Licensed social worker reviews family capability and resettlement eligibility.', 'duration' => '3-5 days', 'actor' => 'CSWDO Social Worker'],
            ['step' => 4, 'title' => 'Notice of Allocation & Community Orientation', 'desc' => 'Approved beneficiaries receive their Notice of Unit Allocation and attend community development briefing.', 'duration' => '7-10 days', 'actor' => 'NHA & Palayan City Housing Board']
        ],
        'where_to_apply' => 'CSWDO Housing Division, in coordination with NHA Palayan Field Office, 2nd Floor, Palayan City Hall.',
        'processing_time' => '7 to 10 working days for evaluation; batch allocation dependent on NHA schedules',
        'fees' => 'None — Application and socio-economic screening are 100% Free of Charge',
        'additional_info' => [
            'San Lorenzo NHA units are protected by national socialized housing laws and cannot be subleased or alienated.',
            'Beneficiaries are provided with water and electricity service application assistance upon turnover.',
            'For updates, applicants can track their status online using their Kalinga Hub reference number.'
        ],
        'apply_url' => 'apply-housing.php?program_id=2&type=relocation',
        'tags' => ['San Lorenzo', 'NHA Housing', 'Resettlement Community', 'Free Assessment']
    ],

    'housing-insite' => [
        'id' => 'housing-insite',
        'category' => 'housing',
        'category_name' => 'Housing Programs',
        'name' => 'Palayan In-Site Housing Assistance Program (Sariling Lupa)',
        'badge' => 'Build on Own Lot',
        'icon' => 'fas fa-house-chimney-user',
        'short_desc' => 'Core shelter building grants and construction support for qualified beneficiaries who already own or hold land tenure and wish to build on-site without relocating.',
        'overview' => 'The Palayan In-Site Housing Assistance Program is an innovative local housing initiative dedicated to qualified Palayan City residents who already own, co-own, or have authorized legal tenure over their own residential lot and need construction grants, materials, and technical building assistance to erect a durable, safe home directly on their own land without moving away from their current neighborhood.',
        'who_can_apply' => [
            'Bona fide Palayan City residents who possess legal ownership, inherited claim, or notarized authorized tenure over a private residential lot.',
            'Beneficiaries living in dilapidated, unbuilt, damaged, or temporary shanties on their own verified land.',
            'Households wanting to build on-site without relocating to Akap Village or San Lorenzo.',
            'Low-income families lacking financial resources to construct a durable core home on their lot.'
        ],
        'requirements' => [
            ['name' => 'Proof of Land Ownership or Tenurial Right (TCT/OCT, Tax Declaration, or Notarized Deed of Sale/Conveyance)', 'type' => 'mandatory', 'source' => 'Registry of Deeds / City Assessor / Applicant'],
            ['name' => 'Notarized Affidavit of Consent from Landowner (if lot is under parents, relatives, or community estate)', 'type' => 'conditional', 'source' => 'Landowner / Notary Public'],
            ['name' => 'Barangay Lot Occupancy & Palayan City Residency Certification', 'type' => 'mandatory', 'source' => 'Barangay Hall of lot location'],
            ['name' => 'Vicinity Sketch Map & Lot Location Plan indicating boundary markers and access roads', 'type' => 'mandatory', 'source' => 'Applicant / Certified Geodetic Sketch'],
            ['name' => 'Clear Photographs of Current Lot & Existing Dilapidated Structure (Interior and Exterior)', 'type' => 'mandatory', 'source' => 'Applicant'],
            ['name' => 'Government-Issued Valid Identification Cards of Household Heads', 'type' => 'mandatory', 'source' => 'Applicant & Spouse (PhilSys, Voter\'s ID)'],
            ['name' => 'Family Intake Sheet & CSWDO On-Site Technical Verification Assessment', 'type' => 'mandatory', 'source' => 'CSWDO Social Worker & City Engineer'],
            ['name' => 'Certificate of Indigency / Proof of Financial Hardship to Self-Build', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Submit In-Site Program Application', 'desc' => 'File application online or at the CSWDO Housing Division, attaching land title/tax declaration and lot sketch.', 'duration' => '20 mins', 'actor' => 'Applicant'],
            ['step' => 2, 'title' => 'Land & Lot Status Verification', 'desc' => 'CSWDO coordinates with City Assessor and City Planning to confirm land tenure and ensure lot is not within a dangerous waterway.', 'duration' => '2-3 days', 'actor' => 'City Assessor & Planning Office'],
            ['step' => 3, 'title' => 'On-Site Technical & Social Inspection', 'desc' => 'Joint field visit by CSWDO case worker and City Engineering technician to inspect lot terrain and verify household living conditions.', 'duration' => '3-5 days', 'actor' => 'CSWDO Case Worker & City Engineer'],
            ['step' => 4, 'title' => 'Core Shelter Grant Approval & Building Scheduling', 'desc' => 'Approval of building grant package or materials turnover for supervised on-site core shelter construction.', 'duration' => '7-10 days', 'actor' => 'City Housing Board & Beneficiary']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), In-Site Housing Desk, 2nd Floor, Palayan City Hall.',
        'processing_time' => '7 to 10 working days for land verification and technical inspection',
        'fees' => 'None — Site validation, engineering inspection, and social evaluation are 100% Free of Charge',
        'additional_info' => [
            'No relocation is required; applicants stay rooted in their home barangay and community.',
            'The lot must be declared safe for residential dwelling (not in an active riverway, flood canal, or protected government reserve).',
            'If the land is family-owned or inherited, all living heirs or legal owners must provide written notarized consent allowing construction.',
            'For on-site inspection inquiries, contact the CSWDO Housing Division at (044) 123-4567.'
        ],
        'apply_url' => 'apply-housing.php?program_id=3&type=in_site',
        'tags' => ['Own Lot', 'In-Site Housing', 'No Relocation', 'Land Ownership Required', 'Free Assessment']
    ],

    // --- AICS SERVICES ---
    'aics-medical' => [
        'id' => 'aics-medical',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Medical Assistance',
        'badge' => 'Financial Relief',
        'icon' => 'fas fa-notes-medical',
        'short_desc' => 'Financial assistance for hospital billing, medicines, laboratory diagnostics, and specialized therapeutic treatments for indigent residents.',
        'overview' => 'The Assistance to Individuals in Crisis Situation (AICS) Medical Assistance provides immediate financial aid and guarantee letters to indigent, low-income, or crisis-stricken Palayan City residents who are burdened with unexpected hospital bills, high maintenance prescription costs, diagnostic laboratory procedures, or specialized medical therapies.',
        'who_can_apply' => [
            'Bona fide residents of Palayan City in need of urgent medical assistance.',
            'Indigent and low-income patients or their authorized immediate family members (spouse, parents, children, or siblings).',
            'Patients admitted in government, district, or partner hospitals, or undergoing outpatient procedures unable to afford medical costs.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Applicant and Patient', 'type' => 'mandatory', 'source' => 'Applicant / Patient'],
            ['name' => 'Barangay Certificate of Indigency / Residency', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Medical Certificate or Clinical Abstract with Doctor\'s License & Signature', 'type' => 'mandatory', 'source' => 'Attending Physician / Hospital'],
            ['name' => 'Hospital Statement of Account / Official Hospital Bill (for admitted patients)', 'type' => 'conditional', 'source' => 'Hospital Billing Section'],
            ['name' => 'Official Doctor\'s Prescription with Date and Physician License (within 3 months)', 'type' => 'conditional', 'source' => 'Attending Physician'],
            ['name' => 'Official Laboratory / Diagnostic Request signed by Physician', 'type' => 'conditional', 'source' => 'Attending Physician / Clinic'],
            ['name' => 'Formal Price Quotation (if medical procedure/device exceeds ₱10,000)', 'type' => 'conditional', 'source' => 'Pharmacy or Medical Equipment Provider']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Application Submission', 'desc' => 'Submit request online through Kalinga Hub or present required documents at the CSWDO AICS Desk.', 'duration' => '10-15 mins', 'actor' => 'Applicant & Receiving Officer'],
            ['step' => 2, 'title' => 'Intake Interview & Assessment', 'desc' => 'A registered CSWDO social worker evaluates the patient\'s socio-economic situation and hospital urgency.', 'duration' => '20-30 mins', 'actor' => 'Licensed Social Worker'],
            ['step' => 3, 'title' => 'Approval & Voucher Processing', 'desc' => 'The Social Case Study Summary is finalized and approved by the CSWDO Department Head.', 'duration' => '1-2 days', 'actor' => 'CSWDO Head'],
            ['step' => 4, 'title' => 'Release of Guarantee Letter or Cash Relief', 'desc' => 'Beneficiary or representative claims the official Guarantee Letter (GL) for hospital deduction or checks at City Treasury.', 'duration' => '1 day', 'actor' => 'Disbursement Officer']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), AICS Desk, Ground Floor, Palayan City Hall.',
        'processing_time' => '3 to 5 working days (Emergency cases prioritized within 24-48 hours)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Medical assistance is granted based on verified assessment and availability of local disaster/welfare funds.',
            'Prescriptions must be dated within three (3) months of application.',
            'Immediate family members applying on behalf of a patient must present proof of relationship.'
        ],
        'apply_url' => 'apply-aics.php?type=medical',
        'tags' => ['Hospital Bills', 'Prescription Medicines', 'Laboratory Tests', 'Free of Charge']
    ],

    'aics-funeral' => [
        'id' => 'aics-funeral',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Funeral and Burial Assistance',
        'badge' => 'Bereavement Support',
        'icon' => 'fas fa-cross',
        'short_desc' => 'Financial aid to bereaved families to help cover casket costs, mortuary care, and funeral services for deceased residents.',
        'overview' => 'Funeral and Burial Assistance offers dignified bereavement relief to low-income and indigent families in Palayan City following the demise of a family member. It helps defray funeral parlor charges, casket purchase, and municipal cemetery expenses.',
        'who_can_apply' => [
            'Immediate family members (surviving spouse, children, parents, or siblings) of a deceased bona fide resident of Palayan City.',
            'Bereaved families experiencing severe financial distress and inability to shoulder funeral expenses.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Bereaved Claimant', 'type' => 'mandatory', 'source' => 'Claimant'],
            ['name' => 'Certified True Copy of Registered Death Certificate', 'type' => 'mandatory', 'source' => 'Local Civil Registrar / PSA'],
            ['name' => 'Barangay Certificate of Indigency / Residency of Claimant', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Funeral Contract / Statement of Account from Funeral Home', 'type' => 'conditional', 'source' => 'Licensed Funeral Parlor'],
            ['name' => 'Proof of Relationship to the Deceased (Marriage Contract, Birth Certificate, or Barangay Kinship Proof)', 'type' => 'mandatory', 'source' => 'PSA / Barangay Hall']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Filing of Bereavement Request', 'desc' => 'Submit burial assistance request online or present death documents at the CSWDO Crisis Intervention Unit.', 'duration' => '10 mins', 'actor' => 'Bereaved Claimant'],
            ['step' => 2, 'title' => 'Verification of Death Record & Kinship', 'desc' => 'CSWDO intake officer reviews the validity of the death certificate and kinship proof.', 'duration' => '15 mins', 'actor' => 'CSWDO Intake Officer'],
            ['step' => 3, 'title' => 'Social Assessment & Endorsement', 'desc' => 'Social worker prepares the bereavement grant recommendation for administrative approval.', 'duration' => '1 day', 'actor' => 'Social Worker & CSWDO Head'],
            ['step' => 4, 'title' => 'Release of Burial Aid / Voucher', 'desc' => 'Burial grant is disbursed to claimant or paid directly via Guarantee Letter to partner mortuary services.', 'duration' => '1-2 days', 'actor' => 'City Treasury & Claimant']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Crisis Desk, Ground Floor, Palayan City Hall.',
        'processing_time' => '2 to 3 working days',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Application should ideally be filed within thirty (30) days from the date of demise.',
            'The claimant\'s name should ideally correspond to the signatory on the funeral contract or be the recognized next of kin.'
        ],
        'apply_url' => 'apply-aics.php?type=funeral',
        'tags' => ['Mortuary Care', 'Casket Support', 'Burial Grant', 'Immediate Relief']
    ],

    'aics-educational' => [
        'id' => 'aics-educational',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Educational Assistance',
        'badge' => 'Student Relief',
        'icon' => 'fas fa-graduation-cap',
        'short_desc' => 'Educational financial grants to support students from vulnerable and low-income families with school supplies, uniforms, and academic fees.',
        'overview' => 'Educational Assistance provides targeted financial support to students enrolled in elementary, secondary, vocational, or tertiary institutions whose families are experiencing economic hardship, helping ensure continuous learning without financial disruption.',
        'who_can_apply' => [
            'Bona fide residents of Palayan City currently enrolled in formal schooling or vocational training.',
            'Elementary, High School, Senior High, College, or Tech-Voc students from indigent households.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Parent or Legal Guardian', 'type' => 'mandatory', 'source' => 'Parent / Guardian'],
            ['name' => 'Validated Current School ID of Student (Front and Back)', 'type' => 'mandatory', 'source' => 'School Registrar'],
            ['name' => 'Certificate of Registration / Enrollment Assessment Form', 'type' => 'mandatory', 'source' => 'School Registrar\'s Office'],
            ['name' => 'Barangay Certificate of Indigency / Residency of Family', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Submit Student Credentials', 'desc' => 'Fill out online educational assistance form or submit school documents at the CSWDO Educational Desk.', 'duration' => '15 mins', 'actor' => 'Student / Parent'],
            ['step' => 2, 'title' => 'Residency & School Verification', 'desc' => 'CSWDO officers cross-check enrollment standing and Palayan residency.', 'duration' => '1-2 days', 'actor' => 'CSWDO Education Focal'],
            ['step' => 3, 'title' => 'Social Case Review', 'desc' => 'Social workers verify household income brackets and finalize beneficiary listings.', 'duration' => '2-3 days', 'actor' => 'Licensed Social Worker'],
            ['step' => 4, 'title' => 'Disbursement of Educational Stipend', 'desc' => 'Educational grant is disbursed directly to parent/student via scheduled payout.', 'duration' => '1-2 days', 'actor' => 'City Treasury']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Education Support Desk, Palayan City Hall.',
        'processing_time' => '5 to 7 working days',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Assistance is granted once per semester or school term per eligible student, subject to budget availability.'
        ],
        'apply_url' => 'apply-aics.php?type=educational',
        'tags' => ['School Supplies', 'Tuition Support', 'Student Grant', 'Indigent Students']
    ],

    'aics-transportation' => [
        'id' => 'aics-transportation',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Transportation Assistance',
        'badge' => 'Transit Support',
        'icon' => 'fas fa-bus',
        'short_desc' => 'Transit fare assistance for stranded individuals, crisis victims, and patients requiring emergency travel or medical transfer.',
        'overview' => 'Transportation Assistance provides urgent travel fare or transit vouchers for individuals and families stranded within Palayan City, referred for outside hospital care in regional centers, or needing emergency travel due to distress or disaster.',
        'who_can_apply' => [
            'Stranded individuals or families in Palayan City without financial resources to return home.',
            'Patients referred for specialized hospital management in regional medical centers.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Traveler', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Barangay Certificate / Endorsement of Residence or Transit', 'type' => 'mandatory', 'source' => 'Barangay Hall or PNP Substation'],
            ['name' => 'Medical Referral / Hospital Transfer Slip (for medical travel)', 'type' => 'conditional', 'source' => 'Attending Physician or Hospital']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Intake Interview at Crisis Desk', 'desc' => 'Applicant presents travel emergency and verified destination details.', 'duration' => '15 mins', 'actor' => 'Applicant & CSWDO Worker'],
            ['step' => 2, 'title' => 'Preparation of Travel Voucher / Cash Aid', 'desc' => 'Assistance voucher or cash fare amount computed based on standard public transit tariffs.', 'duration' => '1-2 hours', 'actor' => 'CSWDO Head'],
            ['step' => 3, 'title' => 'Disbursement of Travel Assistance', 'desc' => 'Direct release of fare assistance or transit ticket to applicant.', 'duration' => 'Same Day / 1 Day', 'actor' => 'Disbursing Officer']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Ground Floor, Palayan City Hall.',
        'processing_time' => '1 to 2 working days (Immediate on the same day for acute stranded emergencies)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Declared destinations must be verified with active contact coordinates.'
        ],
        'apply_url' => 'apply-aics.php?type=transportation',
        'tags' => ['Medical Referral', 'Stranded Assistance', 'Emergency Transit', 'Immediate Release']
    ],

    'aics-food' => [
        'id' => 'aics-food',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Food Assistance',
        'badge' => 'Nutritional Relief',
        'icon' => 'fas fa-utensils',
        'short_desc' => 'Family food packs and essential groceries for households experiencing acute hunger, sudden displacement, or income loss.',
        'overview' => 'Food Assistance provides emergency family food packs, nutritional goods, and grocery staples to low-income households in Palayan City experiencing sudden economic shock, job loss, or temporary food insecurity.',
        'who_can_apply' => [
            'Low-income and indigent households residing in Palayan City.',
            'Families experiencing temporary emergency crisis, income disruption, or post-disaster displacement.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Household Head', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Barangay Certificate of Indigency or Crisis Endorsement', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Submit Crisis Request', 'desc' => 'Register food assistance request online or walk in to the CSWDO Crisis Unit.', 'duration' => '10 mins', 'actor' => 'Applicant'],
            ['step' => 2, 'title' => 'Household Evaluation & Voucher', 'desc' => 'CSWDO staff assesses family size and issues food requisition voucher.', 'duration' => '15 mins', 'actor' => 'CSWDO Intake Officer'],
            ['step' => 3, 'title' => 'Claim Family Food Pack', 'desc' => 'Claim prepared sealed family food packs at the CSWDO Relief Warehouse.', 'duration' => 'Same Day / 1 Day', 'actor' => 'Relief Operations Desk']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Relief & Logistics Section, Palayan City Hall.',
        'processing_time' => '1 to 2 working days (Immediate release for walk-in crisis cases)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Food packs include rice, canned goods, noodles, milk, and nutritional supplements.'
        ],
        'apply_url' => 'apply-aics.php?type=food',
        'tags' => ['Food Packs', 'Emergency Groceries', 'Nutritional Aid', 'Zero Cost']
    ],

    'aics-cash-relief' => [
        'id' => 'aics-cash-relief',
        'category' => 'aics',
        'category_name' => 'AICS Assistance',
        'name' => 'AICS - Cash Relief Assistance',
        'badge' => 'Emergency Funds',
        'icon' => 'fas fa-hand-holding-usd',
        'short_desc' => 'Direct emergency cash assistance for victims of house fires, natural calamities, sudden displacement, or critical socio-economic crisis.',
        'overview' => 'Cash Relief Assistance provides direct financial aid to individuals and families who have suffered severe structural loss, localized house fires, typhoon damage, or acute socioeconomic distress, enabling them to meet urgent basic needs.',
        'who_can_apply' => [
            'Registered residents of Palayan City affected by house fires, typhoons, severe floods, or localized accidents.',
            'Households whose breadwinner suddenly suffered disabling injuries or passed away.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Claimant / Household Head', 'type' => 'mandatory', 'source' => 'Claimant'],
            ['name' => 'Barangay Certificate of Calamity / Damage Assessment', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Bureau of Fire Protection (BFP) Incident Report (for fire victims)', 'type' => 'conditional', 'source' => 'Palayan City BFP Station'],
            ['name' => 'Photographs of Damaged House Structure or Personal Property', 'type' => 'mandatory', 'source' => 'Applicant']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Incident Filing & Document Submission', 'desc' => 'Applicant or barangay officials file calamity assistance notice with photos.', 'duration' => '15 mins', 'actor' => 'Applicant / Barangay'],
            ['step' => 2, 'title' => 'On-Site Disaster Assessment', 'desc' => 'CSWDO and CDRRMO officers conduct rapid on-site validation of damages.', 'duration' => '24-48 hours', 'actor' => 'CSWDO Field Worker'],
            ['step' => 3, 'title' => 'Disbursement of Cash Relief', 'desc' => 'Emergency cash relief is disbursed through the City Treasury or official cash disbursement assembly.', 'duration' => '1-2 days', 'actor' => 'City Treasury & Beneficiary']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Disaster Response Section, Palayan City Hall.',
        'processing_time' => '3 to 5 working days (On-site validation conducted within 24 hours)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Claims must be lodged within fifteen (15) days of the incident occurrence.'
        ],
        'apply_url' => 'apply-aics.php?type=cash_relief',
        'tags' => ['Fire Relief', 'Calamity Assistance', 'Direct Cash Aid', 'Disaster Recovery']
    ],

    // --- SENIOR CITIZEN SERVICES ---
    'senior-id' => [
        'id' => 'senior-id',
        'category' => 'senior',
        'category_name' => 'Senior Citizen Services',
        'name' => 'Provision of Identification Card & Purchase Booklet to Senior Citizens (OSCA)',
        'badge' => 'Window 3 Priority Lane',
        'icon' => 'fas fa-id-card',
        'short_desc' => 'Issuance of Senior Citizen ID and Medicine/Grocery Purchase Booklets granting 20% discount, VAT exemption, and RA 9994 privileges at Window 3.',
        'overview' => 'Official issuance of Senior Citizen Identification Card and Endorsed Medicine and Grocery Purchase Booklets pursuant to Republic Act No. 9994 (Expanded Senior Citizens Act). Enables elderly residents to enjoy 20% discounts on medical, transport, and grocery necessities, exemption from Value-Added Tax (VAT), and priority public lanes.',
        'who_can_apply' => [
            'Filipino citizens who are bona fide residents of Palayan City.',
            'Individuals who are at least sixty (60) years old on or before the date of application.',
            'Existing senior citizens seeking replacement of lost, damaged, or expired IDs or booklets.'
        ],
        'requirements' => [
            ['name' => 'Barangay Clearance from community where applicant resides', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence in Palayan City'],
            ['name' => 'Voter’s ID or Voter\'s Certification / OSCA Endorsement', 'type' => 'mandatory', 'source' => 'COMELEC / Senior Citizen Organization President'],
            ['name' => 'Duly Accomplished Senior Citizen Application Form', 'type' => 'mandatory', 'source' => 'CSWDO Office / Window 3 Special Lane'],
            ['name' => 'Proof of Birth Date (PSA Birth Certificate, Valid Passport, or Valid ID showing birth date)', 'type' => 'mandatory', 'source' => 'PSA / Government Issuer'],
            ['name' => 'Two (2) 1x1 Recent ID Photos with white background', 'type' => 'mandatory', 'source' => 'Applicant / Photo Studio']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Register in Client\'s Logbook (Window 3)', 'desc' => 'Register on the client\'s logbook at Window 3 Special Senior Lane (name, address, gender, contact number, purpose of visit).', 'duration' => '1 minute', 'actor' => 'Client & CSWDO Staff'],
            ['step' => 2, 'title' => 'Submit Documentary Requirements', 'desc' => 'CSWDO focal person receives and inspects the completeness and age validity of submitted documents.', 'duration' => '2 minutes', 'actor' => 'Focal Person for Senior Citizen / OSCA'],
            ['step' => 3, 'title' => 'Accomplish OSCA Registry Form', 'desc' => 'Assist client in filling up the official registry form and affixing signature or thumbprint.', 'duration' => '10 minutes', 'actor' => 'OSCA Focal Person & Client'],
            ['step' => 4, 'title' => 'Processing & Laminating of Senior Citizen ID', 'desc' => 'Encoding of senior details, printing, and photo lamination of the official ID card.', 'duration' => '5 minutes', 'actor' => 'Daycare Worker / Admin Aide (CSWDO)'],
            ['step' => 5, 'title' => 'Approval and Release of ID & Purchase Booklets', 'desc' => 'Final review and signature by OSCA Head / City Mayor, followed by immediate release of Senior ID and Medicine/Grocery Booklets.', 'duration' => '10 minutes', 'actor' => 'OSCA Focal Person & Beneficiary']
        ],
        'where_to_apply' => 'Office of Senior Citizens Affairs (OSCA) Special Lane — Window 3, Ground Floor, CSWDO, Palayan City Hall.',
        'processing_time' => '1 day (Walk-in processing completed in 25–30 minutes at Window 3)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Window 3 is dedicated exclusively as a priority lane for senior citizens and pregnant women.',
            'Two booklets are issued: 1) Medicine Purchase Booklet, and 2) Groceries & Prime Commodities Purchase Booklet.',
            'Bedridden senior citizens may have their immediate family member file with an authorization letter and medical certification.'
        ],
        'apply_url' => 'senior.php',
        'tags' => ['Senior ID', '20% Discount', 'Purchase Booklet', 'Window 3 Priority', 'RA 9994']
    ],

    'senior-pension' => [
        'id' => 'senior-pension',
        'category' => 'senior',
        'category_name' => 'Senior Citizen Services',
        'name' => 'Senior Citizen Social Pension & Welfare Assistance',
        'badge' => 'Monthly Stipend',
        'icon' => 'fas fa-hand-holding-heart',
        'short_desc' => 'Monthly social pension stipends and health wellness monitoring for indigent, frail, and sickly senior citizens.',
        'overview' => 'The Social Pension Program provides monthly government cash subsidies (₱1,000 monthly, disbursed semi-annually or quarterly) to indigent senior citizens in Palayan City to augment capacity for daily food and medicine needs, in coordination with the DSWD.',
        'who_can_apply' => [
            'Bona fide Palayan City senior citizens aged 60 and above.',
            'Frail, sickly, or senior citizens with disability.',
            'Indigent seniors without permanent income, regular financial support from relatives, or private savings.',
            'Must NOT be receiving any pension from SSS, GSIS, PVAO, or private insurance schemes.'
        ],
        'requirements' => [
            ['name' => 'Senior Citizen Identification Card (OSCA ID)', 'type' => 'mandatory', 'source' => 'OSCA Palayan City'],
            ['name' => 'Barangay Certificate of Indigency with Certification of No Existing Pension', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence in Palayan City'],
            ['name' => 'Duly Accomplished DSWD Social Pension Application Form', 'type' => 'mandatory', 'source' => 'OSCA Window 3 / CSWDO Desk']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Endorsement at OSCA Window 3', 'desc' => 'Applicant or barangay OSCA chapter submits candidate name and indigency documents.', 'duration' => '15 mins', 'actor' => 'Applicant / OSCA Leader'],
            ['step' => 2, 'title' => 'Cross-Database Pension Validation', 'desc' => 'CSWDO cross-matches applicant details against SSS, GSIS, and PVAO pensioner databases.', 'duration' => '2-3 days', 'actor' => 'CSWDO Data Analyst'],
            ['step' => 3, 'title' => 'Home Visitation & Poverty Validation', 'desc' => 'Social worker validates household living situation and physical health status.', 'duration' => '3-5 days', 'actor' => 'Licensed Social Worker'],
            ['step' => 4, 'title' => 'Masterlist Enrollment & Payout Distribution', 'desc' => 'Approved senior is enrolled in the official masterlist and receives cash stipend during scheduled distribution assemblies.', 'duration' => 'Periodic Payout Schedule', 'actor' => 'DSWD & City Treasury']
        ],
        'where_to_apply' => 'OSCA Window 3, Ground Floor, CSWDO, Palayan City Hall.',
        'processing_time' => '5 to 7 working days for validation; payouts follow city quarterly schedule',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Enrollment is subject to available national and local social pension slots.'
        ],
        'apply_url' => 'senior.php',
        'tags' => ['Social Pension', 'Indigent Seniors', 'Cash Subsidy', 'Welfare Support']
    ],

    // --- PWD SERVICES ---
    'pwd-id' => [
        'id' => 'pwd-id',
        'category' => 'pwd',
        'category_name' => 'PWD Services',
        'name' => 'Issuance of Person with Disability Identification Card (PWD ID) & Purchase Booklet',
        'badge' => 'Window 1 Priority Lane',
        'icon' => 'fas fa-wheelchair',
        'short_desc' => 'Registration and issuance of PWD ID card and Medicine/Food Purchase Booklets granting 20% discount and VAT exemption under RA 7277 and RA 10754 at Window 1.',
        'overview' => 'Official registration and issuance of Person with Disability Identification Card (PWD ID) and Endorsed Medicine and Basic Necessities Purchase Booklets under Republic Act No. 7277 (Magna Carta for Disabled Persons) as amended by RA 10754. Entitles holders to 20% discounts and VAT exemption on medicine, medical care, transportation, dining, and hotel services.',
        'who_can_apply' => [
            'Bona fide residents of Palayan City with verified permanent disabilities.',
            'Eligible disability categories: Psychosocial/Mental, Physical/Orthopedic, Visual/Blindness, Hearing/Deafness, Speech/Language Impairment, Intellectual, Learning Disability, or Multiple Disabilities.'
        ],
        'requirements' => [
            ['name' => 'Original Medical Certificate from Attending Physician stating disability type and permanent condition', 'type' => 'mandatory', 'source' => 'Licensed Physician / City Health Office'],
            ['name' => 'Three (3) 1x1 Recent ID Photos with applicant\'s signature and printed name at the back', 'type' => 'mandatory', 'source' => 'Applicant / Photo Studio'],
            ['name' => 'Barangay Clearance of residence in Palayan City', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Any Valid Government-Issued ID or School ID (for minors)', 'type' => 'mandatory', 'source' => 'Applicant'],
            ['name' => 'Duly Accomplished Philippine Registry for PWD Application Form', 'type' => 'mandatory', 'source' => 'CSWDO Window 1 Special Lane']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Register in Client\'s Logbook (Window 1)', 'desc' => 'Register on the client\'s logbook at Window 1 Special PWD Lane (name, address, gender, contact number, purpose of visit).', 'duration' => '1 minute', 'actor' => 'Client & CSWDO Staff'],
            ['step' => 2, 'title' => 'Submit Complete Documentary Requirements', 'desc' => 'Receive and review medical certificate and required documents to check completeness and clinical validity.', 'duration' => '2 minutes', 'actor' => 'PWD Focal Person (CSWDO)'],
            ['step' => 3, 'title' => 'Fill Up Philippine Registry Form for PWD', 'desc' => 'Assist client in filling out official registry form and affixing signature or thumb mark to the card.', 'duration' => '10 minutes', 'actor' => 'PWD Focal Person & Client'],
            ['step' => 4, 'title' => 'Approval and Release of PWD ID & Booklets', 'desc' => 'Approval of PWD ID and endorsed purchase booklet to CMO for signature of City Mayor, followed by official release.', 'duration' => '10 minutes', 'actor' => 'PWD Focal Person & City Mayor']
        ],
        'where_to_apply' => 'Persons with Disability Affairs Office (PDAO) / Window 1 Special Lane, Ground Floor, CSWDO, Palayan City Hall.',
        'processing_time' => '1 day (Walk-in processing completed in 25–30 minutes at Window 1)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Window 1 is an accessible ground-floor special lane equipped with ramps and priority accessibility.',
            'For non-apparent disabilities (psychosocial, intellectual, speech), clinical assessment abstracts from specialist physicians are required.'
        ],
        'apply_url' => 'pwd.php',
        'tags' => ['PWD ID', '20% Discount', 'Purchase Booklet', 'Window 1 Priority', 'RA 10754']
    ],

    'pwd-assistive' => [
        'id' => 'pwd-assistive',
        'category' => 'pwd',
        'category_name' => 'PWD Services',
        'name' => 'PWD Assistive Support & Community Rehabilitation',
        'badge' => 'Mobility Aids',
        'icon' => 'fas fa-hands-helping',
        'short_desc' => 'Provision of assistive devices (wheelchairs, crutches, canes, hearing aids) and community-based rehabilitation for registered PWDs.',
        'overview' => 'Assistive Support and Community-Based Rehabilitation provides free mobility devices such as wheelchairs, walkers, crutches, quad canes, and white canes, together with physical rehabilitation coordination to empower persons with disabilities to achieve mobility and active community participation.',
        'who_can_apply' => [
            'Registered Persons with Disabilities who are bona fide residents of Palayan City.',
            'Individuals with verified mobility, visual, or physical impairments needing specialized assistive equipment.'
        ],
        'requirements' => [
            ['name' => 'Valid Palayan City PWD Identification Card', 'type' => 'mandatory', 'source' => 'PDAO Palayan City'],
            ['name' => 'Barangay Certificate of Indigency / Residency', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Medical Assessment / Prescription from Doctor or Physical Therapist (specifying device type)', 'type' => 'mandatory', 'source' => 'City Health Office / Attending Physician'],
            ['name' => 'Formal Letter of Request addressed to the City Mayor or CSWDO Department Head', 'type' => 'mandatory', 'source' => 'Applicant / Guardian']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Submit Assistive Request at Window 1', 'desc' => 'File device request letter and doctor\'s prescription at Window 1.', 'duration' => '15 mins', 'actor' => 'Applicant / Guardian'],
            ['step' => 2, 'title' => 'Physical & Mobility Evaluation', 'desc' => 'CSWDO case officer conducts sizing and compatibility check.', 'duration' => '20 mins', 'actor' => 'CSWDO Rehabilitation Focal'],
            ['step' => 3, 'title' => 'Turnover & Orientation of Assistive Device', 'desc' => 'Formal turnover of device and orientation on safety maintenance and proper ergonomics.', 'duration' => '1 day', 'actor' => 'CSWDO Head & Beneficiary']
        ],
        'where_to_apply' => 'CSWDO Window 1, Persons with Disability Affairs Office, Palayan City Hall.',
        'processing_time' => '3 to 5 working days (subject to inventory availability)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Devices are assigned based on clinical compatibility to ensure patient safety and comfort.'
        ],
        'apply_url' => 'pwd.php',
        'tags' => ['Wheelchairs', 'Crutches', 'Walking Canes', 'Free Mobility Aids']
    ],

    // --- APPOINTMENT SERVICES ---
    'appointments' => [
        'id' => 'appointments',
        'category' => 'appointments',
        'category_name' => 'Appointment Services',
        'name' => 'Online Appointment Scheduling & Priority Queueing',
        'badge' => 'Express Booking',
        'icon' => 'fas fa-calendar-check',
        'short_desc' => 'Digital queueing and appointment booking system to schedule CSWDO consultations, applications, and document submissions with zero waiting lines.',
        'overview' => 'The Online Appointment Scheduling service allows residents to choose their convenient date, time slot, and designated service window in advance. Confirmed bookings receive instant digital QR confirmation slips and SMS alerts, guaranteeing express priority lane access upon arrival at Palayan City Hall.',
        'who_can_apply' => [
            'All residents, community representatives, and visitors seeking social services, consultations, or document submission at CSWDO.',
            'Working citizens needing predictable, scheduled service times without long physical queues.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID or Resident Profile', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Active Mobile Phone Number and Email Address (for SMS/Email confirmations)', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Declared Purpose of Visit & Target Service', 'type' => 'mandatory', 'source' => 'Client']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Choose Service & Preferred Slot', 'desc' => 'Log in to the portal, choose your target service, and select available calendar date and time.', 'duration' => '3 mins', 'actor' => 'Client'],
            ['step' => 2, 'title' => 'Instant Digital Confirmation', 'desc' => 'System generates an appointment confirmation slip with QR code and sends SMS verification.', 'duration' => 'Instant', 'actor' => 'System Automated'],
            ['step' => 3, 'title' => 'Show Up at CSWDO Priority Desk', 'desc' => 'Present your digital or printed appointment slip at Palayan City Hall for immediate express lane servicing.', 'duration' => 'On Scheduled Date', 'actor' => 'CSWDO Front Desk Officer']
        ],
        'where_to_apply' => 'Available 24/7 online through Kalinga Hub; Walk-in kiosks available at Ground Floor Lobby, Palayan City Hall.',
        'processing_time' => 'Instant online confirmation; slots honored within 15 minutes of scheduled time',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'Please arrive at least 10 minutes prior to your selected appointment time.',
            'Appointments can be rescheduled or cancelled online up to 2 hours before the scheduled time slot.'
        ],
        'apply_url' => 'appointments.php',
        'tags' => ['Zero Wait Time', 'SMS Alerts', 'Online Booking', '24/7 Available']
    ],

    // --- DOCUMENT SERVICES ---
    'documents' => [
        'id' => 'documents',
        'category' => 'documents',
        'category_name' => 'Document Services',
        'name' => 'Document Pre-Verification Service',
        'badge' => 'Pre-Screening',
        'icon' => 'fas fa-file-shield',
        'short_desc' => 'Secure digital document upload and pre-verification before in-person appointments to eliminate multiple trips and expedite approvals.',
        'overview' => 'Document Pre-Verification enables applicants to upload digital copies of their documentary requirements (IDs, clinical abstracts, clearances, land titles) prior to visiting City Hall. CSWDO verification officers review file completeness within 24 hours, alerting applicants to any missing items in advance.',
        'who_can_apply' => [
            'Any Palayan City resident planning to apply for AICS financial relief, Housing resettlement, Senior ID, or PWD benefits.',
            'Citizens wishing to avoid travel delays or multiple visits due to incomplete paperwork.'
        ],
        'requirements' => [
            ['name' => 'Clear digital photo or PDF scan of required documentary credentials', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Valid Resident Account on Kalinga Hub', 'type' => 'mandatory', 'source' => 'One Palayan-Kalinga Hub']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Upload Digital Documents', 'desc' => 'Upload scanned or photographed requirements through your secure Resident Portal dashboard.', 'duration' => '5 mins', 'actor' => 'Resident'],
            ['step' => 2, 'title' => 'CSWDO Pre-Verification Review', 'desc' => 'Social work document officer checks legibility, authenticity, and completeness against statutory requirements.', 'duration' => 'Within 24 hours', 'actor' => 'CSWDO Verification Officer'],
            ['step' => 3, 'title' => 'Verification Badge or Feedback Notice', 'desc' => 'Receive a green "Pre-Verified" checkmark or clear notice specifying which exact item needs correction.', 'duration' => 'Instant Notification', 'actor' => 'System & Resident']
        ],
        'where_to_apply' => 'Online via Kalinga Hub Resident Portal (Documents Tab).',
        'processing_time' => '1 working day (24 hours for online verification review)',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'All uploaded files are safeguarded with 256-bit encryption compliant with the Data Privacy Act of 2012.'
        ],
        'apply_url' => 'documents.php',
        'tags' => ['Cloud Verification', 'Data Privacy Protected', 'Fast Approvals', 'Zero Repeat Trips']
    ],

    'social-case' => [
        'id' => 'social-case',
        'category' => 'documents',
        'category_name' => 'Document Services',
        'name' => 'Social Case Study Report (SCSR) & Family Welfare Casework',
        'badge' => 'Professional Casework',
        'icon' => 'fas fa-user-shield',
        'short_desc' => 'In-depth socio-economic evaluation, psychosocial counseling, court referral evaluation, and family casework by licensed social workers.',
        'overview' => 'A comprehensive Social Case Study Report (SCSR) prepared by licensed CSWDO Social Workers providing an official, legally recognized socio-economic assessment, home visitation report, and psychosocial evaluation required by trial courts, charitable foundations, tertiary hospitals, or national agencies.',
        'who_can_apply' => [
            'Individuals and families requiring official casework assessment for court litigation, adoption, legal guardianship, or medical charity assistance.',
            'Solo parents, children in need of special protection, or victims of domestic abuse requiring welfare case management.'
        ],
        'requirements' => [
            ['name' => 'Valid Government-Issued ID of Client / Guardian', 'type' => 'mandatory', 'source' => 'Client'],
            ['name' => 'Barangay Certificate of Indigency and Residency with family background note', 'type' => 'mandatory', 'source' => 'Barangay Hall of residence'],
            ['name' => 'Official Referral Letter or Court Order (if requested by court, hospital, or NGO)', 'type' => 'conditional', 'source' => 'Requesting Agency / Hospital / Court']
        ],
        'procedure' => [
            ['step' => 1, 'title' => 'Intake Counseling & Initial Interview', 'desc' => 'In-depth face-to-face intake interview with a licensed CSWDO social worker.', 'duration' => '45-60 mins', 'actor' => 'Client & Registered Social Worker'],
            ['step' => 2, 'title' => 'Collateral Interviews & Home Visit', 'desc' => 'Social worker conducts a home visitation to assess family dynamics and neighborhood conditions.', 'duration' => '1-2 days', 'actor' => 'Licensed Social Worker'],
            ['step' => 3, 'title' => 'Official Release or Agency Transmittal', 'desc' => 'Confidential release to client or direct transmittal to the requesting court, hospital, or agency.', 'duration' => '1 day', 'actor' => 'CSWDO Records Section']
        ],
        'where_to_apply' => 'City Social Welfare and Development Office (CSWDO), Family Casework Section, 2nd Floor, Palayan City Hall.',
        'processing_time' => '3 to 5 working days',
        'fees' => 'None — Free of Charge',
        'additional_info' => [
            'All case records and interviews are strictly confidential under the Social Work Professional Act and the Data Privacy Act.'
        ],
        'apply_url' => 'appointments.php?service=counseling',
        'tags' => ['Court Endorsement', 'Licensed Social Workers', 'Confidential', 'Family Welfare']
    ]
];

// ─── DYNAMIC CMS SERVICES SYNC (Database-Driven CMS) ──────────────────────────
// Synchronize all database services (AICS, Housing, Senior, PWD, Appointments, Pre-verification)
// so any changes made in Admin CMS immediately reflect here without code edits.
try {
    $db_services = cms_get_services(true);
    foreach ($db_services as $db_svc) {
        $slug = !empty($db_svc['service_code']) ? strtolower(str_replace(['_', ' '], '-', $db_svc['service_code'])) : ('service-' . $db_svc['service_id']);
        
        // Map category to catalog tabs
        $cat_raw = strtolower($db_svc['category'] ?? '');
        $cat_slug = 'general';
        if (strpos($cat_raw, 'housing') !== false) $cat_slug = 'housing';
        elseif (strpos($cat_raw, 'aics') !== false || strpos($cat_raw, 'crisis') !== false) $cat_slug = 'aics';
        elseif (strpos($cat_raw, 'senior') !== false) $cat_slug = 'senior';
        elseif (strpos($cat_raw, 'pwd') !== false || strpos($cat_raw, 'disability') !== false) $cat_slug = 'pwd';
        elseif (strpos($cat_raw, 'appointment') !== false) $cat_slug = 'appointments';
        elseif (strpos($cat_raw, 'document') !== false) $cat_slug = 'documents';

        // Parse eligibility lines if provided
        $elig = [];
        if (!empty($db_svc['eligibility'])) {
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $db_svc['eligibility']));
            foreach ($lines as $ln) {
                $tln = trim($ln, " \t\n\r\0\x0B•-");
                if (!empty($tln)) $elig[] = $tln;
            }
        }

        // Parse requirements lines if provided
        $reqs = [];
        if (!empty($db_svc['requirements'])) {
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $db_svc['requirements']));
            foreach ($lines as $ln) {
                $tln = trim($ln, " \t\n\r\0\x0B•-");
                if (!empty($tln)) {
                    $reqs[] = ['name' => $tln, 'type' => 'mandatory', 'source' => 'Applicant / Issuing Authority'];
                }
            }
        }

        // Parse procedure steps if provided
        $proc = [];
        if (!empty($db_svc['procedure'])) {
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $db_svc['procedure']));
            $st = 1;
            foreach ($lines as $ln) {
                $tln = trim($ln, " \t\n\r\0\x0B");
                if (!empty($tln)) {
                    $proc[] = [
                        'step' => $st++,
                        'title' => 'Process Step',
                        'desc' => $tln,
                        'duration' => $db_svc['processing_time'] ?: 'Standard',
                        'actor' => 'CSWDO Staff'
                    ];
                }
            }
        }

        // Merge or append to services catalog
        if (isset($services_data[$slug])) {
            if (!empty($db_svc['service_name'])) $services_data[$slug]['name'] = $db_svc['service_name'];
            if (!empty($db_svc['badge'])) $services_data[$slug]['badge'] = $db_svc['badge'];
            if (!empty($db_svc['icon'])) $services_data[$slug]['icon'] = $db_svc['icon'];
            if (!empty($db_svc['short_desc'])) $services_data[$slug]['short_desc'] = $db_svc['short_desc'];
            if (!empty($db_svc['description'])) $services_data[$slug]['overview'] = $db_svc['description'];
            if (!empty($elig)) $services_data[$slug]['who_can_apply'] = $elig;
            if (!empty($reqs)) $services_data[$slug]['requirements'] = $reqs;
            if (!empty($proc)) $services_data[$slug]['procedure'] = $proc;
            if (!empty($db_svc['where_to_apply'])) $services_data[$slug]['where_to_apply'] = $db_svc['where_to_apply'];
            if (!empty($db_svc['processing_time'])) $services_data[$slug]['processing_time'] = $db_svc['processing_time'];
            if (!empty($db_svc['fees'])) $services_data[$slug]['fees'] = $db_svc['fees'];
            if (!empty($db_svc['apply_url'])) $services_data[$slug]['apply_url'] = $db_svc['apply_url'];
        } else {
            // Brand new service added through the Admin CMS
            $services_data[$slug] = [
                'id' => $slug,
                'category' => $cat_slug,
                'category_name' => $db_svc['category'] ?: 'Social Welfare Services',
                'name' => $db_svc['service_name'],
                'badge' => $db_svc['badge'] ?: 'Public Service',
                'icon' => $db_svc['icon'] ?: 'fas fa-hands-helping',
                'short_desc' => $db_svc['short_desc'] ?: $db_svc['description'],
                'overview' => $db_svc['description'] ?: $db_svc['short_desc'],
                'who_can_apply' => !empty($elig) ? $elig : ['Bona fide residents of Palayan City'],
                'requirements' => !empty($reqs) ? $reqs : [['name' => 'Valid Government ID and Barangay Clearance', 'type' => 'mandatory', 'source' => 'Applicant']],
                'procedure' => !empty($proc) ? $proc : [['step' => 1, 'title' => 'Submit Application', 'desc' => 'Visit CSWDO or apply online', 'duration' => $db_svc['processing_time'] ?: 'Standard', 'actor' => 'CSWDO Officer']],
                'where_to_apply' => $db_svc['where_to_apply'] ?: 'City Social Welfare and Development Office, Palayan City Hall',
                'processing_time' => $db_svc['processing_time'] ?: '1-3 working days',
                'fees' => $db_svc['fees'] ?: 'Free of Charge',
                'additional_info' => ['For inquiries, contact CSWDO Palayan City Hall.'],
                'apply_url' => $db_svc['apply_url'] ?: 'appointments.php',
                'tags' => [$db_svc['category'], 'Palayan City', 'CSWDO']
            ];
        }
    }

    // Filter out services marked inactive by administrator
    $inactive_svcs = $db->query("SELECT service_code, service_id FROM services WHERE is_active = 0")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($inactive_svcs as $inact) {
        $inact_slug = !empty($inact['service_code']) ? strtolower(str_replace(['_', ' '], '-', $inact['service_code'])) : ('service-' . $inact['service_id']);
        unset($services_data[$inact_slug]);
    }
} catch (Exception $e) {
    error_log('[Services CMS Sync Error] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services &amp; Citizen's Charter - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== BRAND VARIABLES & PALETTE ========== */
        :root {
            --tan: #D8BA98;
            --khaki: #F6D8A8;
            --khaki-dark: #D8BA98;
            --maroon: #7F0303;
            --maroon-light: #9a1515;
            --maroon-dark: #5a0202;
            --midnight: #0F414A;
            --white: #FFFFFF;
            --gold: #C9A86C;
            --gold-light: #E8D5A8;
            --light-blue: #96C0CE;
            --alabaster: #EFE8DF;
            
            --bg-primary: #E4EFE7;
            --bg-card: #FFFFFF;
            --text-primary: #0F414A;
            --text-secondary: #2C3E50;
            --text-muted: #5C6F84;
            --border-color: #D3DFD7;
            
            --shadow-sm: 0 4px 16px rgba(15, 65, 74, 0.08);
            --shadow-md: 0 10px 30px rgba(15, 65, 74, 0.12);
            --shadow-hover: 0 16px 40px rgba(127, 3, 3, 0.16);
            
            --radius-card: 22px;
            --radius-element: 14px;
            --radius-pill: 50px;
            
            --color-success: #2E7D32;
            --color-warning: #ED6C02;
            --color-danger: #D32F2F;
            --color-info: #0288D1;
        }

        [data-theme="dark"] {
            --bg-primary: #0C1417;
            --bg-card: #15222E;
            --text-primary: #E4EFE7;
            --text-secondary: #B0C4DE;
            --text-muted: #7E95AD;
            --border-color: #243547;
            --alabaster: #1A2836;
            
            --shadow-sm: 0 4px 18px rgba(0, 0, 0, 0.45);
            --shadow-md: 0 10px 32px rgba(0, 0, 0, 0.55);
            --shadow-hover: 0 16px 42px rgba(139, 26, 26, 0.28);
            
            --maroon: #8B1A1A;
            --maroon-light: #A82828;
            --maroon-dark: #5a0202;
            --gold: #B8953A;
            --gold-light: #D4B86A;
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
            transition: background 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ========== HERO SECTION ========== */
        .services-hero {
            background: linear-gradient(135deg, var(--midnight) 0%, #17545E 100%);
            color: white;
            padding: 145px 0 70px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .services-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/coverphoto.png');
            background-size: cover;
            background-position: center;
            opacity: 0.10;
            z-index: 0;
        }

        .services-hero .container {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 18px;
            border-radius: var(--radius-pill);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: var(--gold-light);
            margin-bottom: 16px;
            backdrop-filter: blur(8px);
        }

        .services-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .services-hero h1 span {
            color: var(--gold-light);
        }

        .services-hero p {
            font-size: 1.12rem;
            max-width: 780px;
            margin: 0 auto 24px;
            opacity: 0.92;
            line-height: 1.7;
        }

        .trust-bar {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .trust-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.84rem;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(0, 0, 0, 0.18);
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .trust-item i {
            color: var(--gold-light);
        }

        /* ========== PUBLIC NOTICE BANNER ========== */
        .public-notice-wrapper {
            margin: -24px auto 35px;
            max-width: 1200px;
            padding: 0 24px;
            position: relative;
            z-index: 5;
        }

        .public-notice-banner {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-left: 6px solid var(--color-success);
            padding: 16px 22px;
            border-radius: var(--radius-element);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .notice-content {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .notice-icon-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(46, 125, 50, 0.12);
            color: var(--color-success);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .notice-text h3 {
            font-size: 0.96rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .notice-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.45;
        }

        .notice-actions {
            flex-shrink: 0;
        }

        .btn-notice-login {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: var(--maroon);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-pill);
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .btn-notice-login:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
        }

        /* ========== SEARCH & FILTER SECTION ========== */
        .controls-section {
            padding: 0 0 25px;
        }

        .search-control-bar {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            padding: 6px 8px 6px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            max-width: 860px;
            margin: 0 auto;
        }

        .search-control-bar i.search-icon {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        .search-control-input {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 0.95rem;
            color: var(--text-primary);
            font-family: inherit;
            outline: none;
            padding: 8px 0;
        }

        .clear-search-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            display: none;
        }

        .clear-search-btn:hover {
            color: var(--maroon);
        }

        .search-btn-action {
            background: var(--midnight);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: var(--radius-pill);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            white-space: nowrap;
        }

        .search-btn-action:hover {
            background: var(--maroon);
        }

        .category-filter-nav {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .filter-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            padding: 8px 18px;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip:hover {
            border-color: var(--midnight);
            color: var(--midnight);
            background: var(--alabaster);
        }

        .filter-chip.active {
            background: var(--midnight);
            color: white;
            border-color: var(--midnight);
            box-shadow: 0 4px 12px rgba(15, 65, 74, 0.2);
        }

        .filter-chip.active i {
            color: var(--gold-light);
        }

        /* ========== CATEGORIES GRID (6 CORE CARDS WITH PHOTOS) ========== */
        .categories-section {
            padding: 20px 0 80px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 30px;
        }

        .category-card {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .category-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: var(--khaki-dark);
        }

        /* Card Slideshow & Picture Container */
        .card-image-container {
            height: 220px;
            position: relative;
            overflow: hidden;
            background-color: var(--midnight);
        }

        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 0.8s ease-in-out, transform 8s ease;
            transform: scale(1);
        }

        .slide.active {
            opacity: 1;
            transform: scale(1.04);
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(15, 65, 74, 0.1) 0%, rgba(15, 65, 74, 0.45) 100%);
            pointer-events: none;
            z-index: 1;
        }

        .slide-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(15, 65, 74, 0.85));
            color: white;
            padding: 30px 20px 14px;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            z-index: 2;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .slide-caption i {
            color: var(--gold-light);
        }

        .slide-dots {
            position: absolute;
            bottom: 12px;
            right: 18px;
            z-index: 3;
            display: flex;
            gap: 5px;
        }

        .dot {
            width: 7px;
            height: 7px;
            background-color: rgba(255, 255, 255, 0.55);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active, .dot:hover {
            background-color: var(--gold-light);
            width: 18px;
            border-radius: 8px;
        }

        /* Card Content Area */
        .category-content {
            padding: 24px 26px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-header-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .category-icon {
            width: 50px;
            height: 50px;
            background: var(--alabaster);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--maroon);
            font-size: 1.4rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .category-card:hover .category-icon {
            background: var(--midnight);
            color: var(--gold-light);
            transform: scale(1.05);
        }

        .category-title-group h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .category-desc {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 18px;
        }

        /* Minimalist & Organized Program Options (No Oval, No Icons) */
        .category-subservices {
            background: #F8F9FA;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            margin-top: auto;
        }

        .subservice-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748B;
            margin-bottom: 8px;
            display: block;
        }

        .subservice-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .subservice-pill {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.78rem;
            font-weight: 500;
            color: #334155;
            cursor: pointer;
            transition: all 0.18s ease;
            display: inline-block;
            font-family: inherit;
            line-height: 1.3;
        }

        .subservice-pill:hover {
            background: var(--midnight);
            color: #FFFFFF;
            border-color: var(--midnight);
            box-shadow: 0 2px 6px rgba(15, 65, 74, 0.15);
        }

        .subservice-pill.highlight {
            border-color: #CBD5E1;
            background: #FFFFFF;
            color: #1E293B;
            font-weight: 600;
        }

        .subservice-pill.highlight:hover {
            background: var(--midnight);
            color: #FFFFFF;
            border-color: var(--midnight);
        }

        [data-theme="dark"] .category-subservices {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .subservice-label {
            color: rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] .subservice-pill {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.85);
        }

        [data-theme="dark"] .subservice-pill:hover {
            background: var(--gold);
            color: #111827;
            border-color: var(--gold);
        }

        /* Card Action Buttons */
        .category-btn-row {
            display: flex;
            gap: 10px;
        }

        .category-btn {
            flex: 1;
            padding: 11px 16px;
            background: var(--alabaster);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
            font-family: inherit;
            text-decoration: none;
        }

        .category-btn:hover {
            background: var(--midnight);
            color: white;
            border-color: var(--midnight);
        }

        .category-apply-btn {
            padding: 11px 18px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.25s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .category-apply-btn:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
            color: white;
        }

        /* ========== DETAILS MODAL ========== */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 65, 74, 0.65);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-backdrop.show {
            display: flex;
            opacity: 1;
        }

        .modal-dialog {
            background: var(--bg-card);
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            border-radius: var(--radius-card);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.95) translateY(20px);
            transition: transform 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .modal-backdrop.show .modal-dialog {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: 22px 28px;
            background: linear-gradient(135deg, var(--midnight) 0%, #164e57 100%);
            color: white;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .modal-icon-badge {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--gold-light);
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-title-group h2 {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }

        .modal-category-tag {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--gold-light);
            background: rgba(0, 0, 0, 0.25);
            padding: 2px 10px;
            border-radius: var(--radius-pill);
            letter-spacing: 0.5px;
        }

        .modal-close-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .modal-close-btn:hover {
            background: var(--maroon);
            transform: rotate(90deg);
        }

        .modal-summary-bar {
            background: var(--alabaster);
            padding: 14px 28px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-stat {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--midnight);
            font-size: 0.95rem;
        }

        .summary-stat-text .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .summary-stat-text .value {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .summary-stat-text .value.free {
            color: var(--color-success);
        }

        .modal-nav-tabs {
            display: flex;
            background: var(--bg-card);
            border-bottom: 2px solid var(--border-color);
            padding: 0 28px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .modal-tab-link {
            padding: 14px 18px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-tab-link:hover {
            color: var(--maroon);
        }

        .modal-tab-link.active {
            color: var(--maroon);
            border-bottom-color: var(--maroon);
            font-weight: 700;
        }

        .modal-body {
            padding: 24px 28px;
            overflow-y: auto;
            flex-grow: 1;
            max-height: calc(90vh - 270px);
        }

        .modal-tab-panel {
            display: none;
        }

        .modal-tab-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .panel-section {
            margin-bottom: 24px;
        }

        .panel-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-section-title i {
            color: var(--maroon);
        }

        .panel-text {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 14px;
        }

        .checklist-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .checklist-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 12px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            user-select: none;
        }

        .checklist-item:hover {
            border-color: var(--midnight);
            background: var(--alabaster);
        }

        .checklist-item.checked {
            background: rgba(46, 125, 50, 0.08);
            border-color: rgba(46, 125, 50, 0.4);
        }

        .checklist-item.checked .checklist-title {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .checklist-checkbox {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            flex-shrink: 0;
            margin-top: 2px;
            transition: all 0.2s ease;
        }

        .checklist-item.checked .checklist-checkbox {
            background: var(--color-success);
            border-color: var(--color-success);
        }

        .checklist-content {
            flex-grow: 1;
        }

        .checklist-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .checklist-source {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .badge-mandatory {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 4px;
            background: rgba(127, 3, 3, 0.12);
            color: var(--maroon);
            margin-left: 6px;
        }

        .badge-conditional {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 4px;
            background: rgba(2, 136, 209, 0.12);
            color: var(--color-info);
            margin-left: 6px;
        }

        .step-timeline {
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
            padding-left: 6px;
        }

        .step-item {
            display: flex;
            gap: 16px;
            position: relative;
        }

        .step-number-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--midnight);
            color: var(--gold-light);
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(15, 65, 74, 0.2);
        }

        .step-content-card {
            flex-grow: 1;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 14px 18px;
        }

        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .step-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .step-meta {
            display: inline-flex;
            gap: 8px;
        }

        .step-duration {
            font-size: 0.74rem;
            font-weight: 600;
            background: var(--alabaster);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .step-actor {
            font-size: 0.74rem;
            font-weight: 600;
            background: rgba(15, 65, 74, 0.1);
            color: var(--midnight);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .step-desc {
            font-size: 0.86rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin: 0;
        }

        .eligibility-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .eligibility-list li {
            position: relative;
            padding-left: 28px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .eligibility-list li::before {
            content: '\f058';
            font-family: 'Font Awesome 5 Free', 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 2px;
            color: var(--color-success);
            font-size: 1rem;
        }

        .reminder-box {
            background: rgba(237, 108, 2, 0.08);
            border: 1px solid rgba(237, 108, 2, 0.3);
            border-left: 5px solid var(--color-warning);
            padding: 16px 20px;
            border-radius: var(--radius-element);
            margin-top: 18px;
        }

        .reminder-box h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #b25302;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reminder-box ul {
            margin: 0;
            padding-left: 18px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .modal-footer {
            padding: 16px 28px;
            background: var(--alabaster);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .modal-footer-left {
            display: flex;
            gap: 10px;
        }

        .btn-modal-tool {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-tool:hover {
            background: var(--midnight);
            color: white;
            border-color: var(--midnight);
        }

        .modal-footer-right {
            display: flex;
            gap: 12px;
        }

        .btn-modal-close {
            padding: 9px 18px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-close:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .btn-modal-apply {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: var(--radius-element);
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(127, 3, 3, 0.25);
        }

        .btn-modal-apply:hover {
            background: var(--maroon-light);
            transform: translateY(-2px);
            color: white;
        }

        /* ========== QUICK LINKS SECTION ========== */
        .quick-links {
            padding: 50px 0;
            background: var(--bg-primary);
        }

        .quick-links h3 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-links h3 i {
            color: var(--maroon);
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .quick-link-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            padding: 22px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
        }

        .quick-link-card:hover {
            transform: translateY(-4px);
            border-color: var(--maroon);
            box-shadow: var(--shadow-md);
        }

        .quick-link-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--alabaster);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--midnight);
            font-size: 1.3rem;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .quick-link-card:hover .quick-link-icon {
            background: var(--midnight);
            color: var(--gold-light);
        }

        .quick-link-info h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .quick-link-info p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* ========== FAQ SECTION ========== */
        .faq-section {
            padding: 60px 0;
            background: var(--alabaster);
            border-top: 1px solid var(--border-color);
        }

        .section-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .section-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .section-header h2 i {
            color: var(--maroon);
        }

        .section-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .faq-container {
            max-width: 820px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .faq-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-element);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .faq-question {
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.98rem;
        }

        .faq-question:hover {
            color: var(--maroon);
        }

        .faq-question i {
            transition: transform 0.3s ease;
            color: var(--text-muted);
        }

        .faq-question.active i {
            transform: rotate(180deg);
            color: var(--maroon);
        }

        .faq-answer {
            padding: 0 22px 18px;
            display: none;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.65;
            border-top: 1px solid var(--border-color);
            padding-top: 14px;
        }

        .faq-answer.show {
            display: block;
        }

        /* Toast */
        .toast-bubble {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--midnight);
            color: white;
            padding: 12px 20px;
            border-radius: var(--radius-pill);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            font-size: 0.88rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 99999;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast-bubble.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast-bubble i {
            color: var(--gold-light);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 968px) {
            .services-hero h1 { font-size: 2.2rem; }
            .categories-grid { grid-template-columns: 1fr; }
            .public-notice-banner { flex-direction: column; align-items: flex-start; }
            .notice-actions { width: 100%; }
            .btn-notice-login { width: 100%; justify-content: center; }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 12px !important;
            }
            .services-hero {
                padding: 85px 0 24px !important;
            }
            .hero-badge {
                font-size: 0.7rem !important;
                padding: 4px 10px !important;
                margin-bottom: 8px !important;
            }
            .services-hero h1 {
                font-size: 1.45rem !important;
                margin-bottom: 6px !important;
            }
            .services-hero p {
                font-size: 0.8rem !important;
                line-height: 1.4 !important;
                margin-bottom: 12px !important;
            }
            .trust-bar {
                gap: 6px !important;
                margin-top: 10px !important;
            }
            .trust-item {
                font-size: 0.68rem !important;
                padding: 4px 8px !important;
                border-radius: 6px !important;
            }
            .public-notice-wrapper {
                padding: 10px 0 0 !important;
            }
            .public-notice-banner {
                padding: 12px 14px !important;
                border-radius: 14px !important;
                gap: 10px !important;
            }
            .notice-icon-circle {
                width: 34px !important;
                height: 34px !important;
                font-size: 0.95rem !important;
            }
            .notice-text h3 {
                font-size: 0.88rem !important;
                margin-bottom: 2px !important;
            }
            .notice-text p {
                font-size: 0.74rem !important;
                line-height: 1.35 !important;
            }
            .categories-grid {
                grid-template-columns: 1fr !important;
                gap: 14px !important;
                margin-top: 14px !important;
            }
            .category-card {
                border-radius: 16px !important;
            }
            .card-image-container {
                height: 135px !important;
            }
            .slide-caption {
                padding: 18px 12px 8px !important;
                font-size: 0.76rem !important;
            }
            .slide-dots {
                bottom: 8px !important;
                right: 12px !important;
                gap: 4px !important;
            }
            .dot {
                width: 5px !important;
                height: 5px !important;
            }
            .dot.active {
                width: 14px !important;
            }
            .category-content {
                padding: 14px 14px !important;
            }
            .card-header-row {
                gap: 10px !important;
                margin-bottom: 8px !important;
            }
            .category-icon {
                width: 36px !important;
                height: 36px !important;
                font-size: 1.05rem !important;
                border-radius: 10px !important;
            }
            .category-title-group h3 {
                font-size: 1.02rem !important;
            }
            .category-desc {
                font-size: 0.78rem !important;
                line-height: 1.4 !important;
                margin-bottom: 10px !important;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .category-subservices {
                padding: 8px 10px !important;
                margin-bottom: 12px !important;
                border-radius: 8px !important;
            }
            .subservice-label {
                font-size: 0.65rem !important;
                margin-bottom: 5px !important;
            }
            .subservice-pills {
                gap: 5px !important;
            }
            .subservice-pill {
                padding: 4px 8px !important;
                font-size: 0.72rem !important;
                border-radius: 6px !important;
            }
            .category-btn-row {
                gap: 8px !important;
            }
            .category-btn {
                padding: 8px 10px !important;
                font-size: 0.78rem !important;
                border-radius: 10px !important;
            }
            .category-apply-btn {
                padding: 8px 12px !important;
                font-size: 0.78rem !important;
                border-radius: 10px !important;
            }
            .quick-links {
                padding: 28px 0 !important;
            }
            .quick-links h3 {
                font-size: 1.1rem !important;
                margin-bottom: 14px !important;
            }
            .links-grid {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
            .quick-link-card {
                padding: 12px 14px !important;
                border-radius: 12px !important;
                gap: 12px !important;
            }
            .quick-link-icon {
                width: 38px !important;
                height: 38px !important;
                font-size: 1.05rem !important;
                border-radius: 10px !important;
            }
            .quick-link-info h4 {
                font-size: 0.88rem !important;
            }
            .quick-link-info p {
                font-size: 0.74rem !important;
            }
            .faq-section {
                padding: 30px 0 !important;
            }
            /* Service Details Modal - Tiny Mobile UI */
            .modal-backdrop {
                padding: 8px !important;
            }
            .modal-dialog {
                max-height: 94vh !important;
                border-radius: 14px !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .modal-header {
                padding: 10px 14px !important;
                gap: 10px !important;
            }
            .modal-header-left {
                gap: 10px !important;
            }
            .modal-icon-badge {
                width: 34px !important;
                height: 34px !important;
                font-size: 1rem !important;
                border-radius: 10px !important;
            }
            .modal-title-group h2 {
                font-size: 0.95rem !important;
                margin-bottom: 2px !important;
            }
            .modal-category-tag {
                font-size: 0.62rem !important;
                padding: 2px 7px !important;
            }
            .modal-close-btn {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.85rem !important;
            }
            .modal-summary-bar {
                padding: 6px 12px !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6px !important;
            }
            .summary-stat {
                gap: 6px !important;
            }
            .summary-stat-icon {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.72rem !important;
                border-radius: 6px !important;
            }
            .summary-stat-text .label {
                font-size: 0.58rem !important;
            }
            .summary-stat-text .value {
                font-size: 0.72rem !important;
            }
            .modal-nav-tabs {
                padding: 0 8px !important;
                border-bottom-width: 1.5px !important;
            }
            .modal-tab-link {
                padding: 8px 10px !important;
                font-size: 0.72rem !important;
                gap: 5px !important;
            }
            .modal-body {
                padding: 10px 12px !important;
                max-height: calc(94vh - 210px) !important;
            }
            .tab-section-title {
                font-size: 0.84rem !important;
                margin-bottom: 8px !important;
            }
            .checklist-item {
                padding: 6px 8px !important;
                margin-bottom: 6px !important;
                border-radius: 8px !important;
                gap: 8px !important;
            }
            .checklist-checkbox {
                width: 18px !important;
                height: 18px !important;
                font-size: 0.65rem !important;
                border-radius: 4px !important;
            }
            .checklist-title {
                font-size: 0.76rem !important;
                line-height: 1.3 !important;
            }
            .checklist-source {
                font-size: 0.66rem !important;
            }
            .badge-mandatory,
            .badge-conditional {
                font-size: 0.58rem !important;
                padding: 1px 5px !important;
            }
            .step-timeline {
                gap: 10px !important;
                padding-left: 2px !important;
            }
            .step-item {
                gap: 8px !important;
            }
            .step-number-circle {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.72rem !important;
            }
            .step-content-card {
                padding: 8px 10px !important;
                border-radius: 8px !important;
            }
            .step-title {
                font-size: 0.78rem !important;
            }
            .step-duration,
            .step-actor {
                font-size: 0.62rem !important;
                padding: 1px 5px !important;
            }
            .step-desc {
                font-size: 0.72rem !important;
                line-height: 1.35 !important;
            }
            .eligibility-list {
                gap: 6px !important;
            }
            .eligibility-list li {
                font-size: 0.76rem !important;
                line-height: 1.4 !important;
                padding-left: 22px !important;
            }
            .modal-footer {
                padding: 8px 12px !important;
                flex-direction: column !important;
                gap: 6px !important;
            }
            .modal-footer-left,
            .modal-footer-right {
                width: 100% !important;
                display: flex !important;
                gap: 6px !important;
            }
            .btn-modal-tool,
            .btn-modal-apply,
            .btn-modal-close {
                flex: 1 !important;
                justify-content: center !important;
                padding: 6px 10px !important;
                font-size: 0.72rem !important;
                border-radius: 8px !important;
            }
        }

        @media print {
            body * { visibility: hidden; }
            .modal-dialog, .modal-dialog * { visibility: visible; }
            .modal-backdrop { position: absolute; left: 0; top: 0; background: white; display: block !important; opacity: 1 !important; }
            .modal-dialog { border: none; box-shadow: none; max-width: 100%; }
            .modal-close-btn, .modal-footer, .modal-nav-tabs { display: none !important; }
            .modal-tab-panel { display: block !important; }
        }
    </style>
</head>
<body>
    <!-- Global Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <header class="services-hero">
        <div class="container">
            <div class="hero-badge">
                <i class="fas fa-shield-halved"></i> CSWDO Palayan City Citizen's Charter
            </div>
            <h1>Our <span>Services</span> &amp; Citizen's Charter</h1>
            <p>Comprehensive social welfare assistance, housing programs, senior &amp; PWD benefits, and priority municipal services designed for every Palayan City resident.</p>
            
            <div class="trust-bar">
                <div class="trust-item"><i class="fas fa-coins"></i> 100% Free Government Services</div>
                <div class="trust-item"><i class="fas fa-id-card-clip"></i> Official Citizen's Charter</div>
                <div class="trust-item"><i class="fas fa-universal-access"></i> Public Exploration</div>
                <div class="trust-item"><i class="fas fa-clock"></i> Express Priority Windows</div>
            </div>
        </div>
    </header>

    <!-- Public Notice Banner -->
    <div class="public-notice-wrapper">
        <div class="public-notice-banner">
            <div class="notice-content">
                <div class="notice-icon-circle">
                    <i class="fas fa-unlock-keyhole"></i>
                </div>
                <div class="notice-text">
                    <h3>Public Exploration — No Account Required</h3>
                    <p>You can freely browse all service descriptions, eligibility rules, documentary checklists, and Citizen's Charter procedures. An account is only needed when submitting an official application or uploading confidential documents.</p>
                </div>
            </div>
            <div class="notice-actions">
                <?php if (!$is_authenticated): ?>
                    <a href="login.php?redirect=services.php" class="btn-notice-login">
                        <i class="fas fa-arrow-right-to-bracket"></i> Sign In to Apply
                    </a>
                <?php else: ?>
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-success); display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-circle-check"></i> Signed In as Resident
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Clean Search & Filter Controls -->
    <section class="controls-section">
        <div class="container">
            <div class="search-control-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="serviceSearchInput" class="search-control-input" placeholder="Search any service or program (e.g. medical, in-site housing, burial, senior, PWD, window 1)..." autocomplete="off">
                <button type="button" id="clearSearchBtn" class="clear-search-btn" title="Clear"><i class="fas fa-times-circle"></i></button>
                <button type="button" class="search-btn-action" onclick="applySearchFilter()">Search</button>
            </div>

            <!-- Category Filter Chips -->
            <div class="category-filter-nav" id="categoryFilterNav">
                <button type="button" class="filter-chip active" data-category="all">
                    <i class="fas fa-th-large"></i> All Programs (<?php echo (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR) ? '5' : '3'; ?>)
                </button>
                <button type="button" class="filter-chip" data-category="housing">
                    <i class="fas fa-home"></i> Housing Programs
                </button>
                <button type="button" class="filter-chip" data-category="aics">
                    <i class="fas fa-hand-holding-heart"></i> AICS Assistance
                </button>
                <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                <button type="button" class="filter-chip" data-category="senior">
                    <i class="fas fa-user-clock"></i> Senior Citizen (OSCA)
                </button>
                <button type="button" class="filter-chip" data-category="pwd">
                    <i class="fas fa-wheelchair"></i> PWD Services
                </button>
                <?php endif; ?>
                <button type="button" class="filter-chip" data-category="appointments">
                    <i class="fas fa-calendar-check"></i> Appointments
                </button>
            </div>
        </div>
    </section>

    <!-- 6 Core Categories with Existing Pictures and Slideshows -->
    <section class="categories-section">
        <div class="container">
            <div class="categories-grid" id="categoriesGrid">
                
                <!-- CARD 1: HOUSING PROGRAMS -->
                <div class="category-card" data-category="housing" data-keywords="housing relocation akap village san lorenzo in-site insite own lot land shelter">
                    <?php 
                    $housing_slides = cms_get_slides('housing', [
                        'images/san-lorenzo.png',
                        'images/housing-sanlorenzo1.png',
                        'images/housing-sanlorenzo2.png'
                    ]);
                    ?>
                    <div class="card-image-container" id="housingSlideshow">
                        <div class="slideshow-container">
                            <?php foreach ($housing_slides as $idx => $s_img): ?>
                                <div class="slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($s_img); ?>');"></div>
                            <?php endforeach; ?>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-home"></i> <?php echo htmlspecialchars(cms_get('prog_housing_caption', 'Affordable & In-Site Housing for Palayan Families')); ?></div>
                            <div class="slide-dots" id="housingDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="card-header-row">
                            <div class="category-icon"><i class="fas fa-home"></i></div>
                            <div class="category-title-group">
                                <h3><?php echo htmlspecialchars(cms_get('prog_housing_title', 'Housing Programs')); ?></h3>
                                <span style="font-size: 0.75rem; color: var(--color-success); font-weight: 700;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(cms_get('prog_housing_badge', 'Relocation & In-Site (Own Lot)')); ?></span>
                            </div>
                        </div>
                        <p class="category-desc">
                            <?php echo nl2br(htmlspecialchars(cms_get('prog_housing_desc', 'Apply for socialized housing opportunities in Palayan City. Includes designated resettlement communities (Akap Village and San Lorenzo NHA) and on-site building grants for lot owners.'))); ?>
                        </p>

                        <!-- Clean Minimalist Program Options -->
                        <div class="category-subservices">
                            <div class="subservice-label">Housing Pathways:</div>
                            <div class="subservice-pills">
                                <button type="button" class="subservice-pill" onclick="openServiceModal('housing-akap')">Akap Village</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('housing-sanlorenzo')">San Lorenzo NHA</button>
                                <button type="button" class="subservice-pill highlight" onclick="openServiceModal('housing-insite')">In-Site Housing (Own Lot)</button>
                            </div>
                        </div>

                        <div class="category-btn-row">
                            <button type="button" class="category-btn" onclick="openServiceModal('housing-akap')">
                                <i class="fas fa-list-check"></i> <?php echo htmlspecialchars(cms_get('prog_housing_btn1_text', 'View Requirements')); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars(cms_get('prog_housing_btn2_link', $is_authenticated ? 'apply-housing.php' : 'login.php?redirect=apply-housing.php')); ?>" class="category-apply-btn">
                                <?php echo htmlspecialchars(cms_get('prog_housing_btn2_text', 'Apply Housing')); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: AICS ASSISTANCE -->
                <div class="category-card" data-category="aics" data-keywords="aics crisis assistance medical funeral burial educational transportation food cash relief emergency">
                    <?php 
                    $aics_slides = cms_get_slides('aics', [
                        'images/Aics1.png',
                        'images/aics2.png',
                        'images/aics3.png'
                    ]);
                    ?>
                    <div class="card-image-container" id="aicsSlideshow">
                        <div class="slideshow-container">
                            <?php foreach ($aics_slides as $idx => $s_img): ?>
                                <div class="slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($s_img); ?>');"></div>
                            <?php endforeach; ?>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-hand-holding-heart"></i> <?php echo htmlspecialchars(cms_get('prog_aics_caption', 'Comprehensive Assistance in Times of Crisis')); ?></div>
                            <div class="slide-dots" id="aicsDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="card-header-row">
                            <div class="category-icon"><i class="fas fa-hand-holding-heart"></i></div>
                            <div class="category-title-group">
                                <h3><?php echo htmlspecialchars(cms_get('prog_aics_title', 'AICS Assistance')); ?></h3>
                                <span style="font-size: 0.75rem; color: var(--color-info); font-weight: 700;"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars(cms_get('prog_aics_badge', '6 Types of Crisis Relief')); ?></span>
                            </div>
                        </div>
                        <p class="category-desc">
                            <?php echo nl2br(htmlspecialchars(cms_get('prog_aics_desc', 'Assistance to Individuals in Crisis Situation provides urgent financial relief, medical subsidies, burial support, education aid, transportation fare, and food packs for distressed residents.'))); ?>
                        </p>

                        <!-- Clean Minimalist Program Options -->
                        <div class="category-subservices">
                            <div class="subservice-label">Crisis Relief Programs:</div>
                            <div class="subservice-pills">
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-medical')">Medical</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-funeral')">Funeral / Burial</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-educational')">Educational</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-transportation')">Transportation</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-food')">Food Aid</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('aics-cash-relief')">Cash Relief</button>
                            </div>
                        </div>

                        <div class="category-btn-row">
                            <button type="button" class="category-btn" onclick="openServiceModal('aics-medical')">
                                <i class="fas fa-list-check"></i> <?php echo htmlspecialchars(cms_get('prog_aics_btn1_text', 'View Checklists')); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars(cms_get('prog_aics_btn2_link', 'aics.php')); ?>" class="category-apply-btn">
                                <?php echo htmlspecialchars(cms_get('prog_aics_btn2_text', 'Apply AICS')); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR): ?>
                <!-- CARD 3: SENIOR CITIZEN SERVICES (OSCA) -->
                <div class="category-card" data-category="senior" data-keywords="senior citizen osca id booklet 20% discount window 3 pension social pension ra 9994 elderly">
                    <?php 
                    $senior_slides = cms_get_slides('senior', [
                        'images/senior2.png',
                        'images/senior1.png',
                        'images/senior4.png'
                    ]);
                    ?>
                    <div class="card-image-container" id="seniorSlideshow">
                        <div class="slideshow-container">
                            <?php foreach ($senior_slides as $idx => $s_img): ?>
                                <div class="slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($s_img); ?>');"></div>
                            <?php endforeach; ?>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-user-clock"></i> <?php echo htmlspecialchars(cms_get('prog_senior_caption', 'Honoring & Empowering Palayan Senior Citizens')); ?></div>
                            <div class="slide-dots" id="seniorDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="card-header-row">
                            <div class="category-icon"><i class="fas fa-user-clock"></i></div>
                            <div class="category-title-group">
                                <h3><?php echo htmlspecialchars(cms_get('prog_senior_title', 'Senior Citizen Services')); ?></h3>
                                <span style="font-size: 0.75rem; color: var(--maroon); font-weight: 700;"><i class="fas fa-star"></i> <?php echo htmlspecialchars(cms_get('prog_senior_badge', 'Window 3 Express Priority Lane')); ?></span>
                            </div>
                        </div>
                        <p class="category-desc">
                            <?php echo nl2br(htmlspecialchars(cms_get('prog_senior_desc', 'Dedicated programs for seniors aged 60+. Apply for your official Senior Citizen ID, Medicine & Grocery Purchase Booklets, social pension stipends, and welfare benefits.'))); ?>
                        </p>

                        <!-- Clean Minimalist Program Options -->
                        <div class="category-subservices">
                            <div class="subservice-label">Senior Citizen Programs:</div>
                            <div class="subservice-pills">
                                <button type="button" class="subservice-pill highlight" onclick="openServiceModal('senior-id')">OSCA ID &amp; Purchase Booklets (Window 3)</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('senior-pension')">Social Pension Assistance</button>
                            </div>
                        </div>

                        <div class="category-btn-row">
                            <button type="button" class="category-btn" onclick="openServiceModal('senior-id')">
                                <i class="fas fa-list-check"></i> <?php echo htmlspecialchars(cms_get('prog_senior_btn1_text', 'Citizen\'s Charter')); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars(cms_get('prog_senior_btn2_link', $is_authenticated ? 'senior.php' : 'login.php?redirect=senior.php')); ?>" class="category-apply-btn">
                                <?php echo htmlspecialchars(cms_get('prog_senior_btn2_text', 'Senior Portal')); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- CARD 4: PWD SERVICES -->
                <div class="category-card" data-category="pwd" data-keywords="pwd person with disability id booklet 20% discount window 1 pdao assistive devices wheelchair crutches ra 7277 ra 10754">
                    <?php 
                    $pwd_slides = cms_get_slides('pwd', [
                        'images/PWD1.png',
                        'images/PWD2.png',
                        'images/PWD3.png'
                    ]);
                    ?>
                    <div class="card-image-container" id="pwdSlideshow">
                        <div class="slideshow-container">
                            <?php foreach ($pwd_slides as $idx => $s_img): ?>
                                <div class="slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($s_img); ?>');"></div>
                            <?php endforeach; ?>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-wheelchair"></i> <?php echo htmlspecialchars(cms_get('prog_pwd_caption', 'Inclusive Support for Persons with Disabilities')); ?></div>
                            <div class="slide-dots" id="pwdDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="card-header-row">
                            <div class="category-icon"><i class="fas fa-wheelchair"></i></div>
                            <div class="category-title-group">
                                <h3><?php echo htmlspecialchars(cms_get('prog_pwd_title', 'PWD Services')); ?></h3>
                                <span style="font-size: 0.75rem; color: var(--color-info); font-weight: 700;"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars(cms_get('prog_pwd_badge', 'Window 1 Accessible Lane')); ?></span>
                            </div>
                        </div>
                        <p class="category-desc">
                            <?php echo nl2br(htmlspecialchars(cms_get('prog_pwd_desc', 'Registration and issuance of PWD Identification Cards and Purchase Booklets granting 20% discounts and VAT exemption, alongside free mobility assistive devices.'))); ?>
                        </p>

                        <!-- Clean Minimalist Program Options -->
                        <div class="category-subservices">
                            <div class="subservice-label">PWD Programs &amp; Aids:</div>
                            <div class="subservice-pills">
                                <button type="button" class="subservice-pill highlight" onclick="openServiceModal('pwd-id')">PWD ID &amp; Purchase Booklets (Window 1)</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('pwd-assistive')">Assistive Devices &amp; Mobility Aids</button>
                            </div>
                        </div>

                        <div class="category-btn-row">
                            <button type="button" class="category-btn" onclick="openServiceModal('pwd-id')">
                                <i class="fas fa-list-check"></i> <?php echo htmlspecialchars(cms_get('prog_pwd_btn1_text', 'Citizen\'s Charter')); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars(cms_get('prog_pwd_btn2_link', $is_authenticated ? 'pwd.php' : 'login.php?redirect=pwd.php')); ?>" class="category-apply-btn">
                                <?php echo htmlspecialchars(cms_get('prog_pwd_btn2_text', 'PWD Portal')); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CARD 5: APPOINTMENT SERVICES -->
                <div class="category-card" data-category="appointments" data-keywords="appointment booking scheduling schedule online qr slip sms reminders queue priority">
                    <?php 
                    $appointments_slides = cms_get_slides('appointments', [
                        'images/ap1.png',
                        'images/ap2.png',
                        'images/ap3.png'
                    ]);
                    ?>
                    <div class="card-image-container" id="appointmentSlideshow">
                        <div class="slideshow-container">
                            <?php foreach ($appointments_slides as $idx => $s_img): ?>
                                <div class="slide <?php echo $idx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($s_img); ?>');"></div>
                            <?php endforeach; ?>
                            <div class="card-overlay"></div>
                            <div class="slide-caption"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars(cms_get('prog_appointments_caption', 'Schedule Your Consultation Online')); ?></div>
                            <div class="slide-dots" id="appointmentDots"></div>
                        </div>
                    </div>
                    <div class="category-content">
                        <div class="card-header-row">
                            <div class="category-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="category-title-group">
                                <h3><?php echo htmlspecialchars(cms_get('prog_appointments_title', 'Appointment Services')); ?></h3>
                                <span style="font-size: 0.75rem; color: var(--color-success); font-weight: 700;"><i class="fas fa-clock"></i> <?php echo htmlspecialchars(cms_get('prog_appointments_badge', 'Zero Physical Waiting Line')); ?></span>
                            </div>
                        </div>
                        <p class="category-desc">
                            <?php echo nl2br(htmlspecialchars(cms_get('prog_appointments_desc', 'Schedule social welfare consultations and document submissions online. Choose your preferred date and time, receive instant digital confirmations, and enjoy priority service.'))); ?>
                        </p>

                        <!-- Clean Minimalist Program Options -->
                        <div class="category-subservices">
                            <div class="subservice-label">Appointment Options:</div>
                            <div class="subservice-pills">
                                <button type="button" class="subservice-pill highlight" onclick="openServiceModal('appointments')">Express Priority Booking</button>
                                <button type="button" class="subservice-pill" onclick="openServiceModal('appointments')">SMS &amp; Email Reminders</button>
                            </div>
                        </div>

                        <div class="category-btn-row">
                            <button type="button" class="category-btn" onclick="openServiceModal('appointments')">
                                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars(cms_get('prog_appointments_btn1_text', 'Booking Guide')); ?>
                            </button>
                            <a href="<?php echo htmlspecialchars(cms_get('prog_appointments_btn2_link', $is_authenticated ? 'appointments.php' : 'login.php?redirect=appointments.php')); ?>" class="category-apply-btn">
                                <?php echo htmlspecialchars(cms_get('prog_appointments_btn2_text', 'Book Online')); ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Zero Search Results Fallback -->
                <div class="no-results-state" id="noResultsState" style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: var(--radius-card); display: none;">
                    <i class="fas fa-search-minus" style="font-size: 2.6rem; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                    <h3 style="font-size: 1.25rem; color: var(--text-primary); margin-bottom: 6px;">No Matching Programs Found</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 18px;">Try searching with a different keyword or reset to view all available programs.</p>
                    <button type="button" class="category-btn" onclick="resetSearchFilters()" style="display: inline-flex; width: auto; margin: 0 auto; padding: 10px 24px;">
                        <i class="fas fa-rotate-left"></i> View All Programs
                    </button>
                </div>

            </div>
        </div>
    </section>

    <!-- Frequently Accessed Quick Services -->
    <section class="quick-links">
        <div class="container">
            <h3><i class="fas fa-bolt"></i> Frequently Accessed Quick Services</h3>
            <div class="links-grid">
                <a href="track-aics.php" class="quick-link-card">
                    <div class="quick-link-icon"><i class="fas fa-search"></i></div>
                    <div class="quick-link-info">
                        <h4>Track AICS Status</h4>
                        <p>Check financial assistance request</p>
                    </div>
                </a>
                <a href="<?php echo $is_authenticated ? 'my-appointments.php' : 'login.php?redirect=my-appointments.php'; ?>" class="quick-link-card">
                    <div class="quick-link-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="quick-link-info">
                        <h4>My Appointments</h4>
                        <p>View confirmed booking slips</p>
                    </div>
                </a>
                <a href="housing-requirements.php" class="quick-link-card">
                    <div class="quick-link-icon"><i class="fas fa-home"></i></div>
                    <div class="quick-link-info">
                        <h4>Housing Checklist</h4>
                        <p>Printable bilingual requirements</p>
                    </div>
                </a>
                <a href="<?php echo $is_authenticated ? 'documents.php' : 'login.php?redirect=documents.php'; ?>" class="quick-link-card">
                    <div class="quick-link-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                    <div class="quick-link-info">
                        <h4>Upload Requirements</h4>
                        <p>Online pre-verification</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Frequently Asked Questions -->
    <section class="faq-section">
        <div class="container">
            <div class="section-header">
                <h2><i class="fas fa-circle-question"></i> Frequently Asked Questions</h2>
                <p>Common questions about eligibility, application processes, and service guarantees</p>
            </div>
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Do I need to pay any fees to avail of CSWDO services?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        No. All CSWDO social welfare services, including AICS financial assistance, housing evaluations, Senior Citizen ID issuance, PWD registration, and online appointments, are 100% Free of Charge. No fees or charges should ever be requested.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Can I explore service requirements without creating an account?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Yes! This portal is publicly accessible. Anyone can explore complete service descriptions, eligibility rules, documentary checklists, and Citizen's Charter processing workflows without logging in or having an account.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>What is the difference between Relocation Housing and In-Site Housing?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Relocation Housing (Akap Village and San Lorenzo NHA) is designed for families living in danger zones or without land who need to be resettled to designated municipal housing communities. In-Site Housing Assistance is for residents who already own or hold verified legal consent over their own lot and want to construct or repair a durable home directly on that site without relocating.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How fast is the Senior Citizen and PWD ID issuance?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Under our official Citizen's Charter, walk-in applicants with complete documentary requirements receive their Senior Citizen ID &amp; Purchase Booklets at Window 3, or PWD ID &amp; Booklets at Window 1, within approximately 25 to 30 minutes on the same day.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>How does the Online Appointment system work?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Residents can reserve their preferred date and time slot 24/7 online. You will receive an instant digital confirmation slip and SMS reminder. When you arrive at Palayan City Hall, simply present your reference slip for priority lane access without long queues.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Details Modal Dialog -->
    <div class="modal-backdrop" id="serviceDetailsModal" onclick="handleBackdropClick(event)">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalServiceTitle">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-icon-badge" id="modalServiceIcon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="modal-title-group">
                        <span class="modal-category-tag" id="modalCategoryBadge">CRISIS ASSISTANCE</span>
                        <h2 id="modalServiceTitle">Service Name</h2>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeServiceModal()" title="Close details modal" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Quick Summary Bar -->
            <div class="modal-summary-bar">
                <div class="summary-stat">
                    <div class="summary-stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="summary-stat-text">
                        <div class="label">Fees</div>
                        <div class="value free" id="modalFees">None — Free of Charge</div>
                    </div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="summary-stat-text">
                        <div class="label">Processing Time</div>
                        <div class="value" id="modalProcessingTime">3-5 days</div>
                    </div>
                </div>
                <div class="summary-stat">
                    <div class="summary-stat-icon"><i class="fas fa-location-dot"></i></div>
                    <div class="summary-stat-text">
                        <div class="label">Where to Apply</div>
                        <div class="value" id="modalLocationSummary">CSWDO Ground Floor</div>
                    </div>
                </div>
            </div>

            <!-- Modal Nav Tabs -->
            <div class="modal-nav-tabs">
                <button type="button" class="modal-tab-link active" onclick="switchModalTab('overview')">
                    <i class="fas fa-circle-info"></i> Overview &amp; Eligibility
                </button>
                <button type="button" class="modal-tab-link" onclick="switchModalTab('requirements')">
                    <i class="fas fa-clipboard-check"></i> Requirements Checklist (<span id="modalReqCount">0</span>)
                </button>
                <button type="button" class="modal-tab-link" onclick="switchModalTab('procedure')">
                    <i class="fas fa-route"></i> Step-by-Step Procedure
                </button>
                <button type="button" class="modal-tab-link" onclick="switchModalTab('reminders')">
                    <i class="fas fa-bell"></i> Office Location &amp; Reminders
                </button>
            </div>

            <!-- Modal Content Body -->
            <div class="modal-body">
                <!-- TAB 1: OVERVIEW & ELIGIBILITY -->
                <div class="modal-tab-panel active" id="modalTabOverview">
                    <div class="panel-section">
                        <h4 class="panel-section-title"><i class="fas fa-book-open"></i> Service Overview &amp; Purpose</h4>
                        <p class="panel-text" id="modalOverviewText"></p>
                    </div>

                    <div class="panel-section">
                        <h4 class="panel-section-title"><i class="fas fa-user-check"></i> Who Can Apply (Eligibility Criteria)</h4>
                        <ul class="eligibility-list" id="modalEligibilityList"></ul>
                    </div>
                </div>

                <!-- TAB 2: REQUIREMENTS CHECKLIST -->
                <div class="modal-tab-panel" id="modalTabRequirements">
                    <div class="panel-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <h4 class="panel-section-title" style="margin-bottom: 0;">
                                <i class="fas fa-clipboard-list"></i> Documentary Requirements Checklist
                            </h4>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                Click items to check off documents you already have
                            </span>
                        </div>
                        <div class="checklist-group" id="modalChecklistGroup"></div>
                    </div>
                </div>

                <!-- TAB 3: STEP-BY-STEP PROCEDURE -->
                <div class="modal-tab-panel" id="modalTabProcedure">
                    <div class="panel-section">
                        <h4 class="panel-section-title"><i class="fas fa-network-wired"></i> Citizen's Charter Processing Workflow</h4>
                        <div class="step-timeline" id="modalStepTimeline"></div>
                    </div>
                </div>

                <!-- TAB 4: REMINDERS & CONTACT INFO -->
                <div class="modal-tab-panel" id="modalTabReminders">
                    <div class="panel-section">
                        <h4 class="panel-section-title"><i class="fas fa-building"></i> Designated Office &amp; Department</h4>
                        <p class="panel-text" id="modalFullLocation"></p>
                    </div>

                    <div class="reminder-box">
                        <h4><i class="fas fa-circle-exclamation"></i> Important Reminders &amp; Guidelines</h4>
                        <ul id="modalRemindersList"></ul>
                    </div>

                    <div class="panel-section" style="margin-top: 20px;">
                        <h4 class="panel-section-title"><i class="fas fa-headset"></i> CSWDO Palayan City Contact Channels</h4>
                        <p class="panel-text">
                            <strong>Office Hours:</strong> Monday to Friday, 8:00 AM – 5:00 PM (No Noon Break)<br>
                            <strong>Hotline:</strong> (044) 123-4567 / 0917-123-4567<br>
                            <strong>Official Email:</strong> cswdo@palayancity.gov.ph<br>
                            <strong>Address:</strong> Ground Floor &amp; 2nd Floor, Palayan City Hall, Nueva Ecija
                        </p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <div class="modal-footer-left">
                    <button type="button" class="btn-modal-tool" onclick="printModalChecklist()" title="Print requirements checklist">
                        <i class="fas fa-print"></i> Print Checklist
                    </button>
                    <button type="button" class="btn-modal-tool" onclick="copyChecklistToClipboard()" title="Copy requirements list to clipboard">
                        <i class="fas fa-copy"></i> Copy List
                    </button>
                </div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-modal-close" onclick="closeServiceModal()">Close</button>
                    <a href="#" id="modalApplyButton" class="btn-modal-apply">
                        Proceed to Online Application <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Bubble -->
    <div class="toast-bubble" id="toastBubble">
        <i class="fas fa-check-circle"></i>
        <span id="toastBubbleMsg">Notification</span>
    </div>

    <!-- Global Unified Green Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Pass Services Catalog to Client-Side JS -->
    <script>
        const SERVICES_DATA = <?php echo json_encode($services_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const IS_AUTHENTICATED = <?php echo $is_authenticated ? 'true' : 'false'; ?>;
        let activeServiceId = null;

        // Modal Elements
        const modal = document.getElementById('serviceDetailsModal');
        const modalIcon = document.getElementById('modalServiceIcon');
        const modalCategory = document.getElementById('modalCategoryBadge');
        const modalTitle = document.getElementById('modalServiceTitle');
        const modalFees = document.getElementById('modalFees');
        const modalProcessing = document.getElementById('modalProcessingTime');
        const modalLocationSummary = document.getElementById('modalLocationSummary');
        const modalFullLocation = document.getElementById('modalFullLocation');
        const modalOverview = document.getElementById('modalOverviewText');
        const modalEligibility = document.getElementById('modalEligibilityList');
        const modalChecklist = document.getElementById('modalChecklistGroup');
        const modalReqCount = document.getElementById('modalReqCount');
        const modalTimeline = document.getElementById('modalStepTimeline');
        const modalReminders = document.getElementById('modalRemindersList');
        const modalApplyBtn = document.getElementById('modalApplyButton');

        // ========== SLIDESHOW SYSTEM (PRESERVING ALL PICTURES) ==========
        function initSlideshow(containerId, dotsId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const slides = container.querySelectorAll('.slide');
            const dotsContainer = document.getElementById(dotsId);
            if (slides.length === 0 || !dotsContainer) return;

            let currentIndex = 0;
            let interval;

            dotsContainer.innerHTML = '';
            for (let i = 0; i < slides.length; i++) {
                const dot = document.createElement('span');
                dot.className = 'dot';
                if (i === 0) dot.classList.add('active');
                dot.onclick = function(e) {
                    e.stopPropagation();
                    showSlide(i);
                    resetInterval();
                };
                dotsContainer.appendChild(dot);
            }

            const dots = dotsContainer.querySelectorAll('.dot');

            function showSlide(index) {
                slides.forEach(s => s.classList.remove('active'));
                dots.forEach(d => d.classList.remove('active'));

                currentIndex = (index + slides.length) % slides.length;
                slides[currentIndex].classList.add('active');
                if (dots[currentIndex]) dots[currentIndex].classList.add('active');
            }

            function nextSlide() {
                showSlide(currentIndex + 1);
            }

            function resetInterval() {
                if (interval) clearInterval(interval);
                interval = setInterval(nextSlide, 5000);
            }

            container.addEventListener('mouseenter', () => {
                if (interval) clearInterval(interval);
            });
            container.addEventListener('mouseleave', () => {
                resetInterval();
            });

            resetInterval();
        }

        // Initialize all original card slideshows
        document.addEventListener('DOMContentLoaded', () => {
            initSlideshow('housingSlideshow', 'housingDots');
            initSlideshow('aicsSlideshow', 'aicsDots');
            initSlideshow('seniorSlideshow', 'seniorDots');
            initSlideshow('pwdSlideshow', 'pwdDots');
            initSlideshow('appointmentSlideshow', 'appointmentDots');
        });

        // ========== SEARCH & FILTERING SYSTEM ==========
        const searchInput = document.getElementById('serviceSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const filterChips = document.querySelectorAll('.filter-chip');
        const categoryCards = document.querySelectorAll('.category-card');
        const noResults = document.getElementById('noResultsState');

        let activeFilter = 'all';

        function applySearchFilter() {
            const query = (searchInput.value || '').trim().toLowerCase();

            if (query.length > 0) {
                clearSearchBtn.style.display = 'block';
            } else {
                clearSearchBtn.style.display = 'none';
            }

            let visibleCount = 0;

            categoryCards.forEach(card => {
                const category = card.getAttribute('data-category');
                const keywords = card.getAttribute('data-keywords') || '';
                const cardText = card.textContent.toLowerCase();

                const matchesFilter = (activeFilter === 'all') || (category === activeFilter);
                const matchesQuery = (query === '') || keywords.includes(query) || cardText.includes(query);

                if (matchesFilter && matchesQuery) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (noResults) {
                noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }

        function resetSearchFilters() {
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';
            activeFilter = 'all';
            filterChips.forEach(c => {
                if (c.getAttribute('data-category') === 'all') c.classList.add('active');
                else c.classList.remove('active');
            });
            applySearchFilter();
        }

        searchInput.addEventListener('input', applySearchFilter);

        clearSearchBtn.addEventListener('click', () => {
            resetSearchFilters();
            searchInput.focus();
        });

        filterChips.forEach(chip => {
            chip.addEventListener('click', function() {
                filterChips.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                activeFilter = this.getAttribute('data-category');
                applySearchFilter();
            });
        });

        // ========== SERVICE DETAILS MODAL ==========
        function openServiceModal(serviceId) {
            const svc = SERVICES_DATA[serviceId];
            if (!svc) return;

            activeServiceId = serviceId;

            // Populate Header & Metas
            modalIcon.innerHTML = `<i class="${svc.icon}"></i>`;
            modalCategory.textContent = svc.badge || svc.category_name;
            modalTitle.textContent = svc.name;
            modalFees.textContent = svc.fees;
            modalProcessing.textContent = svc.processing_time;
            modalLocationSummary.textContent = svc.where_to_apply.split(',')[0];
            modalFullLocation.innerHTML = `<i class="fas fa-map-pin" style="color: var(--maroon); margin-right: 6px;"></i> ${svc.where_to_apply}`;

            // Populate Overview & Eligibility
            modalOverview.textContent = svc.overview;
            modalEligibility.innerHTML = svc.who_can_apply.map(item => `<li>${item}</li>`).join('');

            // Populate Requirements Checklist
            modalReqCount.textContent = svc.requirements.length;
            modalChecklist.innerHTML = svc.requirements.map(req => `
                <div class="checklist-item" onclick="toggleChecklistItem(this)">
                    <div class="checklist-checkbox"><i class="fas fa-check"></i></div>
                    <div class="checklist-content">
                        <div class="checklist-title">
                            ${req.name}
                            <span class="${req.type === 'mandatory' ? 'badge-mandatory' : 'badge-conditional'}">
                                ${req.type === 'mandatory' ? 'Required' : 'Conditional'}
                            </span>
                        </div>
                        <div class="checklist-source"><i class="fas fa-building" style="margin-right: 4px; opacity: 0.7;"></i> Secure at: ${req.source}</div>
                    </div>
                </div>
            `).join('');

            // Populate Procedure Timeline
            modalTimeline.innerHTML = svc.procedure.map(p => `
                <div class="step-item">
                    <div class="step-number-circle">${p.step}</div>
                    <div class="step-content-card">
                        <div class="step-header">
                            <span class="step-title">${p.title}</span>
                            <div class="step-meta">
                                <span class="step-duration"><i class="fas fa-stopwatch"></i> ${p.duration}</span>
                                <span class="step-actor"><i class="fas fa-user-tie"></i> ${p.actor}</span>
                            </div>
                        </div>
                        <p class="step-desc">${p.desc}</p>
                    </div>
                </div>
            `).join('');

            // Populate Reminders
            modalReminders.innerHTML = svc.additional_info.map(info => `<li>${info}</li>`).join('');

            // Setup Apply Button
            let targetApplyUrl = svc.apply_url;
            if (!IS_AUTHENTICATED) {
                targetApplyUrl = `login.php?redirect=${encodeURIComponent(svc.apply_url)}`;
                modalApplyBtn.innerHTML = `Sign In to Apply Online <i class="fas fa-arrow-right"></i>`;
                modalApplyBtn.title = "You will be redirected to sign in, then directly to the application form.";
            } else {
                modalApplyBtn.innerHTML = `Proceed to Online Application <i class="fas fa-arrow-right"></i>`;
                modalApplyBtn.title = "Open application form";
            }
            modalApplyBtn.href = targetApplyUrl;

            // Switch to Overview tab by default
            switchModalTab('overview');

            // Show Modal with animation
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Update URL hash without reload
            history.replaceState(null, '', `#service-${serviceId}`);
        }

        function closeServiceModal() {
            modal.classList.remove('show');
            document.body.style.overflow = '';
            activeServiceId = null;
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        function handleBackdropClick(e) {
            if (e.target === modal) {
                closeServiceModal();
            }
        }

        function switchModalTab(tabName) {
            const tabButtons = document.querySelectorAll('.modal-tab-link');
            tabButtons.forEach(btn => {
                if (btn.getAttribute('onclick').includes(tabName)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            const panels = document.querySelectorAll('.modal-tab-panel');
            panels.forEach(p => p.classList.remove('active'));

            const targetPanel = document.getElementById('modalTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
        }

        function toggleChecklistItem(item) {
            item.classList.toggle('checked');
        }

        // ========== COPY & PRINT UTILITIES ==========
        function showToast(message) {
            const toast = document.getElementById('toastBubble');
            const msg = document.getElementById('toastBubbleMsg');
            msg.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3500);
        }

        function copyChecklistToClipboard() {
            if (!activeServiceId || !SERVICES_DATA[activeServiceId]) return;
            const svc = SERVICES_DATA[activeServiceId];

            let text = `ONE PALAYAN-KALINGA HUB (CSWDO PALAYAN CITY)\n`;
            text += `Service: ${svc.name}\n`;
            text += `Fees: ${svc.fees} | Processing Time: ${svc.processing_time}\n`;
            text += `Where to Apply: ${svc.where_to_apply}\n\n`;
            text += `DOCUMENTARY REQUIREMENTS CHECKLIST:\n`;

            svc.requirements.forEach((req, idx) => {
                text += `[ ] ${idx + 1}. ${req.name} (${req.type.toUpperCase()})\n    Source: ${req.source}\n`;
            });

            text += `\nCSWDO Hotline: (044) 123-4567 | Email: cswdo@palayancity.gov.ph`;

            navigator.clipboard.writeText(text).then(() => {
                showToast("Checklist copied to clipboard!");
            }).catch(() => {
                showToast("Unable to copy to clipboard.");
            });
        }

        function printModalChecklist() {
            window.print();
        }

        // ========== FAQ ACCORDION ==========
        function toggleFAQ(element) {
            element.classList.toggle('active');
            const answer = element.nextElementSibling;
            answer.classList.toggle('show');
        }

        // ========== DEEP LINKING ON PAGE LOAD ==========
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const serviceParam = urlParams.get('service');
            const catParam = urlParams.get('cat') || urlParams.get('category');
            const hash = window.location.hash;

            // Handle Category Filter Deep Link
            if (catParam) {
                const targetChip = document.querySelector(`.filter-chip[data-category="${catParam}"]`);
                if (targetChip) {
                    targetChip.click();
                }
            }

            // Handle Direct Service Deep Link via Query or Hash
            let targetService = serviceParam;
            if (!targetService && hash && hash.startsWith('#service-')) {
                targetService = hash.replace('#service-', '');
            } else if (!targetService && hash && SERVICES_DATA[hash.replace('#', '')]) {
                targetService = hash.replace('#', '');
            }

            if (targetService && SERVICES_DATA[targetService]) {
                setTimeout(() => {
                    openServiceModal(targetService);
                }, 150);
            }
        });

        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                closeServiceModal();
            }
        });
    </script>
    <?php include_once __DIR__ . '/includes/smart-suggestions.php'; ?>
</body>
</html>

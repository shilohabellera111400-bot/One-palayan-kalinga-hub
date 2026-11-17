<?php
// includes/smart-suggestions-helper.php - Smart Service Suggestion & Recommendation Engine
// Provides contextual suggestions based on resident profile, applications, and current page.

if (!function_exists('getSmartSuggestions')) {
    /**
     * Compute and return prioritized smart service suggestions for a logged-in resident.
     *
     * @param PDO|null $db
     * @param int|null $user_id
     * @param string   $current_page
     * @return array
     */
    function getSmartSuggestions(?PDO $db, ?int $user_id = null, string $current_page = ''): array
    {
        if (!$db) {
            return [];
        }

        // Strictly restrict proactive smart suggestions to services.php ONLY (never on dashboard.php or other pages)
        if ($current_page !== 'services.php') {
            return [];
        }

        // Check if smart suggestions are disabled in user cookies or session
        if (isset($_COOKIE['smart_suggestions_enabled']) && $_COOKIE['smart_suggestions_enabled'] === '0') {
            return [];
        }
        if (isset($_SESSION['smart_suggestions']) && $_SESSION['smart_suggestions'] === 'disabled') {
            return [];
        }

        // If user is not logged in (guest), provide contextual public service suggestions
        if (!$user_id || $user_id <= 0) {
            $guest_candidates = [];
            if (strpos($current_page, 'senior') !== false) {
                $guest_candidates[] = [
                    'id'                  => 'senior',
                    'service_name'        => 'Senior Citizen Registration (OSCA)',
                    'title'               => 'Are you ready to register as a Senior Citizen?',
                    'tagline'             => 'CSWDO • Window 3 Special Lane',
                    'badge'               => 'Senior Citizen (60+)',
                    'badge_icon'          => 'fas fa-person-cane',
                    'badge_color'         => '#0288D1',
                    'icon'                => 'fas fa-person-cane',
                    'accent_color'        => '#0288D1',
                    'message'             => 'Eligible for 20% discounts on medicines, groceries, and OSCA benefits under RA 9994.',
                    'disclaimer'          => 'Subject to CSWDO Window 3 document review.',
                    'apply_url'           => 'senior.php#tab-apply',
                    'apply_label'         => 'Apply (Window 3)',
                    'print_url'           => 'print-forms.php?form=senior',
                    'print_label'         => 'Print OSCA Form',
                    'eligibility_summary' => 'Palayan City resident, at least 60 years old.',
                    'processing_time'     => '1 Day • Free of Charge',
                    'window_info'         => 'Window 3 (OSCA)',
                    'requirements_list'   => [
                        '2 pcs 1x1 ID photos (white background)',
                        'Birth Certificate or Baptismal Certificate (Proof of Age)',
                        'COMELEC Voter’s Certification or Barangay Certificate of Residency',
                        'Valid Government ID',
                        'Barangay Clearance & Community Tax Certificate (Cedula)'
                    ],
                    'score'               => 100
                ];
            }
            if (strpos($current_page, 'pwd') !== false) {
                $guest_candidates[] = [
                    'id'                  => 'pwd',
                    'service_name'        => 'PWD ID & Purchase Booklet',
                    'title'               => 'Looking for PWD ID & Assistance? Discover benefits here!',
                    'tagline'             => 'CSWDO • Window 1 Special Lane',
                    'badge'               => 'PWD Welfare',
                    'badge_icon'          => 'fas fa-wheelchair',
                    'badge_color'         => '#1A237E',
                    'icon'                => 'fas fa-wheelchair',
                    'accent_color'        => '#1A237E',
                    'message'             => 'Avail discount booklets and social assistance for persons with disabilities or caregivers.',
                    'disclaimer'          => 'Requires Medical Certificate from a licensed physician.',
                    'apply_url'           => 'pwd.php#tab-apply',
                    'apply_label'         => 'Apply (Window 1)',
                    'print_url'           => 'print-forms.php?form=pwd',
                    'print_label'         => 'Print DOH PWD Form',
                    'eligibility_summary' => 'Palayan resident with permanent disability or authorized caregiver.',
                    'processing_time'     => '1-2 Days • Free of Charge',
                    'window_info'         => 'Window 1 (PWD Affairs)',
                    'requirements_list'   => [
                        'Medical Certificate with disability diagnosis by licensed physician',
                        '2 pcs 1x1 ID photos',
                        'Valid Government ID or Birth Certificate',
                        'Barangay Certificate of Residency'
                    ],
                    'score'               => 95
                ];
            }
            // AICS Crisis Aid
            $guest_candidates[] = [
                'id'                  => 'aics',
                'service_name'        => 'Crisis Assistance (AICS)',
                'title'               => 'Ready to apply for AICS Assistance?',
                'tagline'             => 'CSWDO • Crisis Intervention Unit',
                'badge'               => 'Financial & Medical Aid',
                'badge_icon'          => 'fas fa-hand-holding-heart',
                'badge_color'         => '#7F0303',
                'icon'                => 'fas fa-hand-holding-heart',
                'accent_color'        => '#7F0303',
                'message'             => 'Financial aid for emergency medical, hospital, funeral, or educational expenses.',
                'disclaimer'          => 'Subject to CSWDO social worker evaluation.',
                'apply_url'           => 'aics.php',
                'apply_label'         => 'Apply for AICS',
                'print_url'           => 'print-forms.php?form=intake',
                'print_label'         => 'Print Intake Sheet',
                'eligibility_summary' => 'Palayan City resident experiencing financial crisis or emergency.',
                'processing_time'     => '1-3 Days • Free of Charge',
                'window_info'         => 'Crisis Intervention Unit (CIU)',
                'requirements_list'   => [
                    'Medical / Hospital / Burial / Educational document with cost estimate',
                    'Valid Government ID of Applicant & Beneficiary',
                    'Barangay Certificate of Indigency / Residency'
                ],
                'score'               => 90
            ];
            // Housing
            $guest_candidates[] = [
                'id'                  => 'housing',
                'service_name'        => 'Housing Assistance & Relocation',
                'title'               => 'Looking for Housing Assistance? Apply here!',
                'tagline'             => 'City Housing & Relocation Division',
                'badge'               => 'Housing Program',
                'badge_icon'          => 'fas fa-home',
                'badge_color'         => '#00695C',
                'icon'                => 'fas fa-home',
                'accent_color'        => '#00695C',
                'message'             => 'Socialized housing packages and relocation support for qualified families.',
                'disclaimer'          => 'Subject to City Housing Office validation.',
                'apply_url'           => 'housing.php',
                'apply_label'         => 'Explore Housing',
                'print_url'           => 'housing-requirements.php',
                'print_label'         => 'Housing Guidelines',
                'eligibility_summary' => 'Palayan resident without residential real property.',
                'processing_time'     => 'Batch evaluation & site validation',
                'window_info'         => 'City Housing Office',
                'requirements_list'   => [
                    'Barangay Certificate of Indigency & Proof of No Real Property',
                    'Proof of Income (Payslip or Certificate of No Income)',
                    'Marriage / Birth Certificates (if applicable)',
                    'Valid Government IDs',
                    'Voter’s / Residency Certification'
                ],
                'score'               => 85
            ];
            // Appointment Desk
            $guest_candidates[] = [
                'id'                  => 'appointment',
                'service_name'        => 'Appointment Desk',
                'title'               => 'Need to schedule an appointment?',
                'tagline'             => 'CSWDO • Online Booking',
                'badge'               => 'Appointment Desk',
                'badge_icon'          => 'fas fa-calendar-check',
                'badge_color'         => '#C2185B',
                'icon'                => 'fas fa-calendar-check',
                'accent_color'        => '#C2185B',
                'message'             => 'Book online for face-to-face consultations, case interviews, or document releases.',
                'disclaimer'          => 'Free appointment reservation with instant confirmation.',
                'apply_url'           => 'appointments.php',
                'apply_label'         => 'Book Appointment',
                'print_url'           => 'appointments.php',
                'print_label'         => 'Available Slots',
                'eligibility_summary' => 'Open to all Palayan City residents and clients seeking CSWDO services.',
                'processing_time'     => 'Instant Online Confirmation',
                'window_info'         => 'CSWDO Appointment Desk',
                'requirements_list'   => [
                    'Valid Government-issued ID',
                    'Application tracking number or relevant supporting documents',
                    'Appointment confirmation slip or QR reference code'
                ],
                'score'               => 80
            ];
            return $guest_candidates;
        }

        try {
            // 1. Fetch resident & user profile
            $user_stmt = $db->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.verification_status,
                       r.id AS resident_id, r.birth_date, r.gender, r.civil_status,
                       r.senior_citizen, r.pwd, r.monthly_income, r.occupation,
                       r.barangay, r.address, r.household_number
                FROM users u
                LEFT JOIN residents r ON u.user_id = r.user_id
                WHERE u.user_id = :uid
                LIMIT 1
            ");
            $user_stmt->execute([':uid' => $user_id]);
            $resident = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resident) {
                return getSmartSuggestions($db, 0, $current_page);
            }

            // Calculate age from birth_date
            $age = null;
            if (!empty($resident['birth_date']) && $resident['birth_date'] !== '0000-00-00') {
                try {
                    $birth = new DateTime($resident['birth_date']);
                    $today = new DateTime();
                    $age = $today->diff($birth)->y;
                } catch (Exception $e) {
                    $age = null;
                }
            }

            $is_senior = ($age !== null && $age >= 60) || ((int)($resident['senior_citizen'] ?? 0) === 1);
            $is_pwd    = ((int)($resident['pwd'] ?? 0) === 1);
            $income    = (float)($resident['monthly_income'] ?? 0.00);

            // 2. Check active/recent applications across all programs
            
            // A) Senior Citizen Application Check
            $has_active_senior = false;
            try {
                $sc_stmt = $db->prepare("
                    SELECT application_id, status FROM senior_applications 
                    WHERE user_id = :uid AND status IN ('submitted', 'reviewing', 'approved', 'released')
                    LIMIT 1
                ");
                $sc_stmt->execute([':uid' => $user_id]);
                if ($sc_stmt->fetch()) {
                    $has_active_senior = true;
                }
            } catch (Exception $e) {
                // Table might not exist or error
            }

            // B) PWD Application Check
            $has_active_pwd = false;
            try {
                $pwd_stmt = $db->prepare("
                    SELECT application_id, status FROM pwd_applications 
                    WHERE user_id = :uid AND status IN ('submitted', 'reviewing', 'approved', 'released')
                    LIMIT 1
                ");
                $pwd_stmt->execute([':uid' => $user_id]);
                if ($pwd_stmt->fetch()) {
                    $has_active_pwd = true;
                }
            } catch (Exception $e) {
                // Table might not exist or error
            }

            // C) Housing Application Check (housing_beneficiaries)
            $has_active_housing = false;
            try {
                $h_stmt = $db->prepare("
                    SELECT beneficiary_id, status FROM housing_beneficiaries 
                    WHERE user_id = :uid AND status IN ('pending', 'qualified', 'approved', 'released')
                    LIMIT 1
                ");
                $h_stmt->execute([':uid' => $user_id]);
                if ($h_stmt->fetch()) {
                    $has_active_housing = true;
                }
            } catch (Exception $e) {
                // Table might not exist or error
            }

            // D) AICS Assistance Check (aics_requests)
            $has_active_aics = false;
            $aics_count = 0;
            try {
                $aics_stmt = $db->prepare("
                    SELECT aics_id, status FROM aics_requests 
                    WHERE user_id = :uid AND status IN ('submitted', 'reviewing', 'approved')
                    LIMIT 1
                ");
                $aics_stmt->execute([':uid' => $user_id]);
                if ($aics_stmt->fetch()) {
                    $has_active_aics = true;
                }

                $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM aics_requests WHERE user_id = :uid");
                $cnt_stmt->execute([':uid' => $user_id]);
                $aics_count = (int)$cnt_stmt->fetchColumn();
            } catch (Exception $e) {
                // Table might not exist or error
            }

            // 3. Build candidate suggestions
            $candidates = [];

            // --- SUGGESTION: SENIOR CITIZEN REGISTRATION ---
            if (isPwdSeniorScopeActive() && !$has_active_senior && ($is_senior || strpos($current_page, 'senior') !== false)) {
                $score = 80;
                if ($is_senior) $score += 50;
                if (strpos($current_page, 'senior') !== false) $score += 40;

                $candidates['senior'] = [
                    'id'                  => 'senior',
                    'service_name'        => 'Senior Citizen Registration (OSCA)',
                    'title'               => 'Are you ready to register as a Senior Citizen?',
                    'tagline'             => 'CSWDO • Window 3 Special Lane',
                    'badge'               => 'Senior Citizen 60+',
                    'badge_icon'          => 'fas fa-person-cane',
                    'badge_color'         => '#0288D1',
                    'icon'                => 'fas fa-person-cane',
                    'accent_color'        => '#0288D1',
                    'message'             => 'Eligible for 20% discounts on medicines, groceries, and OSCA benefits under RA 9994.',
                    'disclaimer'          => 'Subject to CSWDO Window 3 document review.',
                    'apply_url'           => 'senior.php#tab-apply',
                    'apply_label'         => 'Apply (Window 3)',
                    'print_url'           => 'print-forms.php?form=senior',
                    'print_label'         => 'Print OSCA Form',
                    'eligibility_summary' => 'Palayan City resident, at least 60 years old.',
                    'processing_time'     => '1 Day • Free of Charge',
                    'window_info'         => 'Window 3 (OSCA)',
                    'requirements_list'   => [
                        '2 pcs 1x1 ID photos (white background)',
                        'Birth Certificate or Baptismal Certificate (Proof of Age)',
                        'COMELEC Certification or Barangay Residency',
                        'Valid Government ID',
                        'Barangay Clearance & Community Tax Certificate (Cedula)'
                    ],
                    'score'               => $score
                ];
            }

            // --- SUGGESTION: PWD REGISTRATION & BOOKLET ---
            if (isPwdSeniorScopeActive() && !$has_active_pwd && ($is_pwd || strpos($current_page, 'pwd') !== false)) {
                $score = 80;
                if ($is_pwd) $score += 50;
                if (strpos($current_page, 'pwd') !== false) $score += 40;

                $candidates['pwd'] = [
                    'id'                  => 'pwd',
                    'service_name'        => 'PWD ID & Purchase Booklet',
                    'title'               => 'Looking for PWD ID & Assistance? Discover benefits here!',
                    'tagline'             => 'CSWDO • Window 1 Special Lane',
                    'badge'               => 'PWD Welfare',
                    'badge_icon'          => 'fas fa-wheelchair',
                    'badge_color'         => '#1A237E',
                    'icon'                => 'fas fa-wheelchair',
                    'accent_color'        => '#1A237E',
                    'message'             => 'Avail discount booklets and social assistance for persons with disabilities or caregivers.',
                    'disclaimer'          => 'Requires Medical Certificate from a licensed physician.',
                    'apply_url'           => 'pwd.php#tab-apply',
                    'apply_label'         => 'Apply (Window 1)',
                    'print_url'           => 'print-forms.php?form=pwd',
                    'print_label'         => 'Print DOH PWD Form',
                    'eligibility_summary' => 'Palayan resident with permanent disability or authorized caregiver.',
                    'processing_time'     => '1-2 Days • Free of Charge',
                    'window_info'         => 'Window 1 (PWD Affairs)',
                    'requirements_list'   => [
                        'Medical Certificate with disability diagnosis by licensed physician',
                        '2 pcs 1x1 ID photos',
                        'Valid Government ID or Birth Certificate',
                        'Barangay Certificate of Residency'
                    ],
                    'score'               => $score
                ];
            }

            // --- SUGGESTION: AICS CRISIS ASSISTANCE ---
            if (!$has_active_aics) {
                $score = 70;
                if ($income <= 15000) $score += 30;
                if ($aics_count === 0) $score += 15;
                if (strpos($current_page, 'aics') !== false) $score += 40;

                $candidates['aics'] = [
                    'id'                  => 'aics',
                    'service_name'        => 'Crisis Assistance (AICS)',
                    'title'               => 'Ready to apply for AICS Assistance?',
                    'tagline'             => 'CSWDO • Crisis Intervention Unit',
                    'badge'               => 'Financial & Medical Aid',
                    'badge_icon'          => 'fas fa-hand-holding-heart',
                    'badge_color'         => '#7F0303',
                    'icon'                => 'fas fa-hand-holding-heart',
                    'accent_color'        => '#7F0303',
                    'message'             => 'Financial aid for emergency medical, hospital, funeral, or educational expenses.',
                    'disclaimer'          => 'Subject to CSWDO social worker evaluation.',
                    'apply_url'           => 'aics.php',
                    'apply_label'         => 'Apply for AICS',
                    'print_url'           => 'print-forms.php?form=intake',
                    'print_label'         => 'Print Intake Sheet',
                    'eligibility_summary' => 'Palayan City resident experiencing financial crisis or emergency.',
                    'processing_time'     => '1-3 Days • Free of Charge',
                    'window_info'         => 'Crisis Intervention Unit (CIU)',
                    'requirements_list'   => [
                        'Medical / Hospital / Burial / Educational document with cost estimate',
                        'Valid Government ID of Applicant & Beneficiary',
                        'Barangay Certificate of Indigency / Residency'
                    ],
                    'score'               => $score
                ];
            }

            // --- SUGGESTION: HOUSING ASSISTANCE & RELOCATION ---
            if (!$has_active_housing) {
                $score = 65;
                if (strpos($current_page, 'housing') !== false) $score += 45;
                if (!empty($resident['civil_status']) && in_array($resident['civil_status'], ['Married', 'Widowed', 'Separated'])) {
                    $score += 20;
                }

                $candidates['housing'] = [
                    'id'                  => 'housing',
                    'service_name'        => 'Housing Assistance & Relocation',
                    'title'               => 'Looking for Housing Assistance? Apply here!',
                    'tagline'             => 'City Housing & Relocation Division',
                    'badge'               => 'Housing Program',
                    'badge_icon'          => 'fas fa-home',
                    'badge_color'         => '#00695C',
                    'icon'                => 'fas fa-home',
                    'accent_color'        => '#00695C',
                    'message'             => 'Socialized housing packages and relocation support for qualified families.',
                    'disclaimer'          => 'Subject to City Housing Office validation.',
                    'apply_url'           => 'housing.php',
                    'apply_label'         => 'Apply for Housing',
                    'print_url'           => 'housing-requirements.php',
                    'print_label'         => 'Housing Guidelines',
                    'eligibility_summary' => 'Palayan resident without residential real property.',
                    'processing_time'     => 'Batch evaluation & site validation',
                    'window_info'         => 'City Housing Office',
                    'requirements_list'   => [
                        'Barangay Certificate of Indigency & Proof of No Real Property',
                        'Proof of Income (Payslip or Certificate of No Income)',
                        'Marriage / Birth Certificates (if applicable)',
                        'Valid Government IDs',
                        'Voter’s / Residency Certification'
                    ],
                    'score'               => $score
                ];
            }

            // --- SUGGESTION: APPOINTMENT DESK ---
            $candidates['appointment'] = [
                'id'                  => 'appointment',
                'service_name'        => 'Appointment Desk',
                'title'               => 'Need to schedule an appointment?',
                'tagline'             => 'CSWDO • Online Booking',
                'badge'               => 'Appointment Desk',
                'badge_icon'          => 'fas fa-calendar-check',
                'badge_color'         => '#C2185B',
                'icon'                => 'fas fa-calendar-check',
                'accent_color'        => '#C2185B',
                'message'             => 'Book online for face-to-face consultations, case interviews, or document releases.',
                'disclaimer'          => 'Free appointment reservation with instant confirmation.',
                'apply_url'           => 'appointments.php',
                'apply_label'         => 'Book Appointment',
                'print_url'           => 'appointments.php',
                'print_label'         => 'Available Slots',
                'eligibility_summary' => 'Open to all Palayan City residents and clients seeking CSWDO services.',
                'processing_time'     => 'Instant Online Confirmation',
                'window_info'         => 'CSWDO Appointment Desk',
                'requirements_list'   => [
                    'Valid Government-issued ID',
                    'Application tracking number or relevant supporting documents',
                    'Appointment confirmation slip or QR reference code'
                ],
                'score'               => 60
            ];

            // Sort candidates by score descending
            uasort($candidates, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return array_values($candidates);

        } catch (Exception $e) {
            error_log('[Smart Suggestions Error] ' . $e->getMessage());
            return [];
        }
    }
}
